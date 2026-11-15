<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SanctumAuthTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * 書籍登録・更新 API の正しい入力値を返します。
     */
    private function bookData(array $overrides = []): array
    {
        return [
            'title' => 'リーダブルコード',
            'author' => 'Dustin Boswell',
            'isbn' => '9784873115658',
            'published_date' => '2012-06-23',
            'description' => 'より良いコードを書くためのテクニック',
            'genres' => Genre::factory()->count(2)->create()->pluck('id')->all(),
            ...$overrides,
        ];
    }

    /**
     * トークンなしでは書籍の登録・更新・削除APIは401になる。
     */
    public function test_write_apis_return_401_without_token(): void
    {
        $book = Book::factory()->for($this->user)->create();

        $this->postJson('/api/v1/books', $this->bookData())->assertUnauthorized();
        $this->putJson("/api/v1/books/{$book->id}", $this->bookData())->assertUnauthorized();
        $this->deleteJson("/api/v1/books/{$book->id}")->assertUnauthorized();

        $this->assertDatabaseCount('books', 1);
    }

    /**
     * 無効なトークンでは401になる。
     */
    public function test_invalid_token_returns_401(): void
    {
        $this->withToken('1|invalid-token')
            ->postJson('/api/v1/books', $this->bookData())
            ->assertUnauthorized();
    }

    /**
     * 発行したトークンをBearerで送ると書籍を登録でき201を返す。
     */
    public function test_can_store_book_with_bearer_token(): void
    {
        $token = $this->user->createToken('api')->plainTextToken;
        $data = $this->bookData();

        $this->withToken($token)
            ->postJson('/api/v1/books', $data)
            ->assertCreated()
            ->assertJsonPath('data.title', 'リーダブルコード')
            ->assertJsonPath('data.user.id', $this->user->id)
            ->assertJsonCount(2, 'data.genres');

        $book = Book::firstWhere('isbn', '9784873115658');
        $this->assertSame($this->user->id, $book->user_id);
        $this->assertEqualsCanonicalizing($data['genres'], $book->genres->pluck('id')->all());
    }

    /**
     * 書籍登録APIでもバリデーションが行われ422を返す。
     */
    public function test_store_api_returns_422_for_invalid_input(): void
    {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/v1/books', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'author', 'isbn', 'published_date', 'genres']);
    }

    /**
     * 自分の書籍を更新APIで更新できる。
     */
    public function test_owner_can_update_book_via_api(): void
    {
        Sanctum::actingAs($this->user);
        $book = Book::factory()->for($this->user)->create(['title' => '更新前']);

        $this->putJson("/api/v1/books/{$book->id}", $this->bookData(['title' => '更新後']))
            ->assertOk()
            ->assertJsonPath('data.title', '更新後');

        $this->assertSame('更新後', $book->fresh()->title);
    }

    /**
     * 自分の書籍を削除APIで削除でき204を返す。
     */
    public function test_owner_can_delete_book_via_api(): void
    {
        Sanctum::actingAs($this->user);
        $book = Book::factory()->for($this->user)->create();

        $this->deleteJson("/api/v1/books/{$book->id}")->assertNoContent();

        $this->assertModelMissing($book);
    }

    /**
     * 他のユーザーの書籍は更新・削除APIで403になる。
     */
    public function test_other_user_gets_403_on_update_and_delete(): void
    {
        Sanctum::actingAs($this->user);
        $book = Book::factory()->create(['title' => '他人の書籍']);

        $this->putJson("/api/v1/books/{$book->id}", $this->bookData())->assertForbidden();
        $this->deleteJson("/api/v1/books/{$book->id}")->assertForbidden();

        $this->assertSame('他人の書籍', $book->fresh()->title);
    }

    /**
     * 削除したトークンは使えなくなる。
     */
    public function test_deleted_token_cannot_be_used(): void
    {
        $token = $this->user->createToken('api');
        $token->accessToken->delete();

        $this->withToken($token->plainTextToken)
            ->postJson('/api/v1/books', $this->bookData())
            ->assertUnauthorized();
    }

    /**
     * 参照系のAPIはトークンなしで利用できる。
     */
    public function test_read_apis_are_available_without_token(): void
    {
        $book = Book::factory()->create();

        $this->getJson('/api/v1/books')->assertOk();
        $this->getJson("/api/v1/books/{$book->id}")->assertOk();
    }
}
