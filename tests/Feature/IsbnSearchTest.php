<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IsbnSearchTest extends TestCase
{
    use RefreshDatabase;

    private const ISBN = '9784873115658';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        // 偽装していない外部 API へのリクエストを禁止する
        Http::preventStrayRequests();
    }

    /**
     * Google Books API の書籍データ（volume）を返します。
     */
    private function volume(string $isbn, array $overrides = []): array
    {
        return [
            'volumeInfo' => [
                'title' => 'リーダブルコード',
                'authors' => ['Dustin Boswell', 'Trevor Foucher'],
                'publishedDate' => '2012-06-23',
                'description' => 'より良いコードを書くためのシンプルで実践的なテクニック',
                'imageLinks' => ['thumbnail' => 'http://books.google.com/books/content?id=xxx'],
                'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => $isbn]],
                ...$overrides,
            ],
        ];
    }

    private function search(string $isbn = self::ISBN)
    {
        return $this->actingAs($this->user)->getJson("/books/isbn/{$isbn}");
    }

    /**
     * ISBNで書籍情報を取得しフォーム入力用の形で返す。
     */
    public function test_returns_book_info_for_form_by_isbn(): void
    {
        Http::fake([
            'www.googleapis.com/books/v1/volumes*' => Http::response(['totalItems' => 1, 'items' => [$this->volume(self::ISBN)]]),
        ]);

        $this->search()
            ->assertOk()
            ->assertExactJson([
                'title' => 'リーダブルコード',
                'author' => 'Dustin Boswell、Trevor Foucher',
                'published_date' => '2012-06-23',
                'description' => 'より良いコードを書くためのシンプルで実践的なテクニック',
                'image_url' => 'https://books.google.com/books/content?id=xxx',
            ]);

        Http::assertSent(fn (Request $request) => $request['q'] === 'isbn:'.self::ISBN);
        Http::assertSentCount(1);
    }

    /**
     * ISBN指定で見つからない場合はISBNだけで検索し直しISBNが一致する書籍を返す。
     */
    public function test_falls_back_to_plain_query_and_returns_matching_isbn(): void
    {
        Http::fake(fn (Request $request) => str_starts_with($request['q'], 'isbn:')
            ? Http::response(['totalItems' => 0])
            : Http::response(['items' => [
                $this->volume('9780000000000', ['title' => '関係のない本']),
                $this->volume(self::ISBN),
            ]]));

        $this->search()
            ->assertOk()
            ->assertJsonPath('title', 'リーダブルコード');

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request['q'] === self::ISBN);
    }

    /**
     * ISBNが一致しない検索結果は採用しない。
     */
    public function test_ignores_results_with_different_isbn(): void
    {
        Http::fake([
            '*' => Http::response(['items' => [$this->volume('9780000000000')]]),
        ]);

        $this->search()->assertNotFound();
    }

    /**
     * 書籍が見つからない場合は404とエラーメッセージを返す。
     */
    public function test_returns_404_with_message_when_not_found(): void
    {
        Http::fake(['*' => Http::response(['totalItems' => 0])]);

        $this->search()
            ->assertNotFound()
            ->assertExactJson(['error' => '該当する書籍が見つかりませんでした。']);
    }

    /**
     * 任意項目がない書籍でもnullとして返す。
     */
    public function test_returns_null_for_missing_optional_fields(): void
    {
        Http::fake(['*' => Http::response(['items' => [[
            'volumeInfo' => [
                'title' => 'タイトルだけの本',
                'industryIdentifiers' => [['type' => 'ISBN_13', 'identifier' => self::ISBN]],
            ],
        ]]])]);

        $this->search()
            ->assertOk()
            ->assertExactJson([
                'title' => 'タイトルだけの本',
                'author' => null,
                'published_date' => null,
                'description' => null,
                'image_url' => null,
            ]);
    }

    /**
     * APIがエラーを返した場合は503とエラーメッセージを返す。
     */
    public function test_returns_503_when_api_returns_error(): void
    {
        Http::fake(['*' => Http::response(['error' => 'backendFailed'], 503)]);

        $this->search()
            ->assertStatus(503)
            ->assertExactJson(['error' => '書籍情報の取得に失敗しました。時間をおいて再度お試しください。']);
    }

    /**
     * APIに接続できない場合は503とエラーメッセージを返す。
     */
    public function test_returns_503_when_connection_fails(): void
    {
        Http::fake(fn () => throw new ConnectionException('Connection timed out'));

        $this->search()
            ->assertStatus(503)
            ->assertJsonPath('error', '書籍情報の取得に失敗しました。時間をおいて再度お試しください。');
    }

    /**
     * 一時的なエラーは再試行して取得できる。
     */
    public function test_retries_on_temporary_error(): void
    {
        Http::fakeSequence()
            ->push(['error' => 'backendFailed'], 503)
            ->push(['items' => [$this->volume(self::ISBN)]]);

        $this->search()
            ->assertOk()
            ->assertJsonPath('title', 'リーダブルコード');
    }

    /**
     * APIキーが設定されている場合はリクエストに含める。
     */
    public function test_includes_api_key_when_configured(): void
    {
        config(['services.google_books.key' => 'test-api-key']);
        Http::fake(['*' => Http::response(['items' => [$this->volume(self::ISBN)]])]);

        $this->search()->assertOk();

        Http::assertSent(fn (Request $request) => $request['key'] === 'test-api-key');
    }

    /**
     * 13桁の数字以外は検索できない。
     */
    public function test_rejects_non_13_digit_isbn(): void
    {
        Http::fake();

        $this->search('978487311565')->assertNotFound();
        $this->search('abcdefghijklm')->assertNotFound();

        Http::assertNothingSent();
    }

    /**
     * 未ログインでは検索できない。
     */
    public function test_guest_cannot_search(): void
    {
        Http::fake();

        $this->getJson('/books/isbn/'.self::ISBN)->assertUnauthorized();

        Http::assertNothingSent();
    }
}
