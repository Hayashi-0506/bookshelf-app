<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Genre;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 一括代入できる属性が定義されている。
     */
    public function test_fillable_attributes_are_defined(): void
    {
        $this->assertSame(
            ['title', 'author', 'isbn', 'published_date', 'description', 'image_url', 'user_id'],
            (new Book)->getFillable(),
        );
    }

    /**
     * 出版日はCarbonにキャストされる。
     */
    public function test_published_date_is_cast_to_carbon(): void
    {
        $book = Book::factory()->create(['published_date' => '2012-06-23']);

        $this->assertInstanceOf(Carbon::class, $book->fresh()->published_date);
        $this->assertSame('2012-06-23', $book->fresh()->published_date->format('Y-m-d'));
    }

    /**
     * 作成者のユーザーに属する。
     */
    public function test_belongs_to_creator_user(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();

        $this->assertInstanceOf(BelongsTo::class, $book->user());
        $this->assertTrue($book->user->is($user));
    }

    /**
     * 複数のジャンルを持ち中間テーブルに日時が記録される。
     */
    public function test_has_many_genres_with_pivot_timestamps(): void
    {
        $book = Book::factory()->create();
        $genres = Genre::factory()->count(2)->create();

        $book->genres()->attach($genres);

        $this->assertInstanceOf(BelongsToMany::class, $book->genres());
        $this->assertCount(2, $book->genres);
        $this->assertNotNull($book->genres->first()->pivot->created_at);
    }

    /**
     * 複数のレビューを持つ。
     */
    public function test_has_many_reviews(): void
    {
        $book = Book::factory()->create();
        Review::factory()->count(3)->for($book)->create();

        $this->assertInstanceOf(HasMany::class, $book->reviews());
        $this->assertCount(3, $book->reviews);
    }

    /**
     * お気に入りに登録したユーザーを取得できる。
     */
    public function test_can_get_users_who_favorited(): void
    {
        $book = Book::factory()->create();
        $users = User::factory()->count(2)->create();

        $book->favoriteUsers()->attach($users);

        $this->assertInstanceOf(BelongsToMany::class, $book->favoriteUsers());
        $this->assertEqualsCanonicalizing($users->pluck('id')->all(), $book->favoriteUsers->pluck('id')->all());
    }

    /**
     * 書籍を削除すると関連するレビューとお気に入りとジャンルの紐付けも削除される。
     */
    public function test_deleting_book_cascades_reviews_favorites_and_genre_links(): void
    {
        $book = Book::factory()->hasAttached(Genre::factory())->create();
        Review::factory()->for($book)->create();
        $book->favoriteUsers()->attach(User::factory()->create());

        $book->delete();

        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('favorites', 0);
        $this->assertDatabaseCount('book_genre', 0);
        $this->assertDatabaseCount('genres', 1);
    }
}
