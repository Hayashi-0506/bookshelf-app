<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchFilterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 書籍一覧に表示された書籍のIDを返します。
     */
    private function bookIds(string $query = ''): array
    {
        return $this->get(route('books.index').$query)
            ->assertOk()
            ->viewData('books')
            ->pluck('id')
            ->sort()
            ->values()
            ->all();
    }

    /**
     * キーワードでタイトルを部分一致検索できる。
     */
    public function test_can_search_title_by_keyword(): void
    {
        $hit = Book::factory()->create(['title' => 'リーダブルコード']);
        Book::factory()->create(['title' => '吾輩は猫である']);

        $this->assertSame([$hit->id], $this->bookIds('?keyword=コード'));
    }

    /**
     * キーワードで著者名を部分一致検索できる。
     */
    public function test_can_search_author_by_keyword(): void
    {
        $hit = Book::factory()->create(['title' => '坊っちゃん', 'author' => '夏目漱石']);
        Book::factory()->create(['title' => '火花', 'author' => '又吉直樹']);

        $this->assertSame([$hit->id], $this->bookIds('?keyword=漱石'));
    }

    /**
     * キーワードがタイトルか著者のどちらかに一致すれば表示される。
     */
    public function test_keyword_matches_title_or_author(): void
    {
        $byTitle = Book::factory()->create(['title' => '夏の本', 'author' => '著者A']);
        $byAuthor = Book::factory()->create(['title' => '冬の本', 'author' => '夏目漱石']);
        Book::factory()->create(['title' => '春の本', 'author' => '著者B']);

        $this->assertSame([$byTitle->id, $byAuthor->id], $this->bookIds('?keyword=夏'));
    }

    /**
     * ジャンルで絞り込める。
     */
    public function test_can_filter_by_genre(): void
    {
        $novel = Genre::factory()->create();
        $business = Genre::factory()->create();
        $hit = Book::factory()->hasAttached($novel)->create();
        $both = Book::factory()->hasAttached([$novel, $business])->create();
        Book::factory()->hasAttached($business)->create();

        $this->assertSame([$hit->id, $both->id], $this->bookIds("?genre={$novel->id}"));
    }

    /**
     * キーワードとジャンルを組み合わせるとどちらにも一致する書籍だけが表示される。
     */
    public function test_keyword_and_genre_are_combined_with_and(): void
    {
        $genre = Genre::factory()->create();
        $hit = Book::factory()->hasAttached($genre)->create(['title' => 'コードの本', 'author' => '著者A']);
        Book::factory()->create(['title' => 'コードの本2', 'author' => '著者B']);
        Book::factory()->hasAttached($genre)->create(['title' => '別の本', 'author' => 'コード太郎']);
        Book::factory()->hasAttached($genre)->create(['title' => '関係ない本', 'author' => '著者C']);

        $ids = $this->bookIds("?keyword=コード&genre={$genre->id}");

        $this->assertCount(2, $ids);
        $this->assertContains($hit->id, $ids);
        $this->assertNotContains(Book::firstWhere('title', 'コードの本2')->id, $ids);
        $this->assertNotContains(Book::firstWhere('title', '関係ない本')->id, $ids);
    }

    /**
     * 一致する書籍がない場合は0件になる。
     */
    public function test_returns_no_books_when_nothing_matches(): void
    {
        Book::factory()->create(['title' => 'リーダブルコード']);

        $this->assertSame([], $this->bookIds('?keyword=存在しないキーワード'));
    }

    /**
     * 条件を指定しない場合はすべての書籍が表示される。
     */
    public function test_displays_all_books_without_conditions(): void
    {
        Book::factory()->count(3)->create();

        $this->assertCount(3, $this->bookIds());
    }

    /**
     * ページを移動しても検索条件が引き継がれる。
     */
    public function test_pagination_keeps_search_conditions(): void
    {
        $genre = Genre::factory()->create();
        Book::factory()->count(11)->hasAttached($genre)->create(['title' => '検索対象の本']);

        $books = $this->get(route('books.index')."?keyword=検索&genre={$genre->id}")->viewData('books');

        $this->assertStringContainsString('keyword=', $books->nextPageUrl());
        $this->assertStringContainsString("genre={$genre->id}", $books->nextPageUrl());
    }

    /**
     * 存在しないジャンルや長すぎるキーワードはバリデーションエラーになる。
     */
    public function test_invalid_genre_or_too_long_keyword_fails_validation(): void
    {
        $this->get(route('books.index').'?genre=9999')->assertSessionHasErrors('genre');
        $this->get(route('books.index').'?keyword='.str_repeat('a', 256))->assertSessionHasErrors('keyword');
    }

    /**
     * 書籍一覧APIでもキーワードとジャンルで絞り込める。
     */
    public function test_index_api_can_filter_by_keyword_and_genre(): void
    {
        $genre = Genre::factory()->create();
        $hit = Book::factory()->hasAttached($genre)->create(['title' => 'コードの本']);
        Book::factory()->create(['title' => 'コードの本2']);
        Book::factory()->hasAttached($genre)->create(['title' => '別の本']);

        $this->getJson('/api/v1/books?keyword=コード')->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/books?genre_id={$genre->id}")->assertJsonCount(2, 'data');
        $this->getJson("/api/v1/books?keyword=コード&genre_id={$genre->id}")
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $hit->id);
        $this->getJson('/api/v1/books?genre_id=9999')->assertUnprocessable()->assertJsonValidationErrors('genre_id');
    }
}
