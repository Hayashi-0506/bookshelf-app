<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    /**
     * パスワードはハッシュ化して保存され配列化しても含まれない。
     */
    public function test_password_is_hashed_and_hidden(): void
    {
        $user = User::factory()->create(['password' => 'secret-password']);

        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    /**
     * 登録した書籍を取得できる。
     */
    public function test_can_get_registered_books(): void
    {
        $user = User::factory()->create();
        Book::factory()->count(2)->for($user)->create();
        Book::factory()->create();

        $this->assertInstanceOf(HasMany::class, $user->books());
        $this->assertCount(2, $user->books);
    }

    /**
     * 投稿したレビューを取得できる。
     */
    public function test_can_get_posted_reviews(): void
    {
        $user = User::factory()->create();
        Review::factory()->count(2)->for($user)->create();

        $this->assertInstanceOf(HasMany::class, $user->reviews());
        $this->assertCount(2, $user->reviews);
    }

    /**
     * お気に入りの書籍を取得できる。
     */
    public function test_can_get_favorite_books(): void
    {
        $user = User::factory()->create();
        $books = Book::factory()->count(3)->create();

        $user->favoriteBooks()->attach($books->take(2));

        $this->assertInstanceOf(BelongsToMany::class, $user->favoriteBooks());
        $this->assertCount(2, $user->favoriteBooks);
    }

    /**
     * いいねしたレビューを取得できる。
     */
    public function test_can_get_liked_reviews(): void
    {
        $user = User::factory()->create();
        $review = Review::factory()->create();

        $user->likedReviews()->attach($review);

        $this->assertInstanceOf(BelongsToMany::class, $user->likedReviews());
        $this->assertTrue($user->likedReviews->first()->is($review));
    }

    /**
     * 読書計画を取得できる。
     */
    public function test_can_get_reading_plans(): void
    {
        $user = User::factory()->create();
        ReadingPlan::factory()->count(2)->for($user)->create();

        $this->assertInstanceOf(HasMany::class, $user->readingPlans());
        $this->assertCount(2, $user->readingPlans);
    }

    /**
     * ユーザーを削除するとレビューと読書計画とお気に入りも削除される。
     */
    public function test_deleting_user_cascades_reviews_reading_plans_and_favorites(): void
    {
        $user = User::factory()->create();
        Review::factory()->for($user)->create();
        ReadingPlan::factory()->for($user)->create();
        $user->favoriteBooks()->attach(Book::factory()->create());

        $user->delete();

        $this->assertDatabaseCount('reviews', 0);
        $this->assertDatabaseCount('reading_plans', 0);
        $this->assertDatabaseCount('favorites', 0);
    }
}
