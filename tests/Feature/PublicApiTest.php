<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 書籍一覧APIは認証なしで取得できる。
     */
    public function test_index_api_is_available_without_authentication(): void
    {
        Book::factory()->count(3)->create();

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    /**
     * 書籍一覧APIのレスポンス形式が正しい。
     */
    public function test_index_api_response_structure(): void
    {
        $genre = Genre::factory()->create(['name' => '技術書']);
        $book = Book::factory()->hasAttached($genre)->create();
        Review::factory()->for($book)->create(['rating' => 4]);
        Review::factory()->for($book)->create(['rating' => 5]);

        $this->getJson('/api/v1/books')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'title', 'author', 'isbn', 'published_date', 'image_url', 'genres' => [['id', 'name']], 'reviews_count', 'reviews_avg_rating']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('data.0.genres.0.name', '技術書')
            ->assertJsonPath('data.0.reviews_count', 2)
            ->assertJsonPath('data.0.reviews_avg_rating', 4.5)
            ->assertJsonMissingPath('links')
            ->assertJsonMissingPath('meta.links')
            ->assertJsonMissingPath('meta.path')
            ->assertJsonMissingPath('data.0.description');
    }

    /**
     * 書籍一覧APIはID順に1ページ20件ずつ返し件数を指定できる。
     */
    public function test_index_api_paginates_by_twenty_in_id_order_and_accepts_per_page(): void
    {
        $books = Book::factory()->count(25)->create();

        $this->getJson('/api/v1/books')
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('data.0.id', $books->first()->id)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 2);

        $this->getJson('/api/v1/books?per_page=10&page=3')
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 3);
    }

    /**
     * 書籍一覧APIのパラメータが不正な場合は422エラーになる。
     */
    public function test_index_api_returns_422_for_invalid_parameters(): void
    {
        $this->getJson('/api/v1/books?per_page=101')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/books?per_page=0')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/v1/books?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    /**
     * 書籍詳細APIは説明文とレビューを含めて返す。
     */
    public function test_show_api_includes_description_and_reviews(): void
    {
        $book = Book::factory()->create(['title' => 'リーダブルコード', 'description' => 'より良いコードを書くためのテクニック']);
        Review::factory()->for($book)->create(['rating' => 5, 'comment' => '素晴らしい本でした！']);

        $this->getJson("/api/v1/books/{$book->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $book->id)
            ->assertJsonPath('data.title', 'リーダブルコード')
            ->assertJsonPath('data.description', 'より良いコードを書くためのテクニック')
            ->assertJsonPath('data.reviews.0.rating', 5)
            ->assertJsonPath('data.reviews.0.comment', '素晴らしい本でした！');
    }

    /**
     * 存在しない書籍の詳細APIは404になる。
     */
    public function test_show_api_returns_404_for_nonexistent_book(): void
    {
        $this->getJson('/api/v1/books/9999')->assertNotFound();
    }
}
