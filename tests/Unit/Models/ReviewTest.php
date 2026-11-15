<?php

namespace Tests\Unit\Models;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 一括代入できる属性が定義されている。
     */
    public function test_fillable_attributes_are_defined(): void
    {
        $this->assertSame(['user_id', 'book_id', 'rating', 'comment'], (new Review)->getFillable());
    }

    /**
     * 投稿者と対象書籍に属する。
     */
    public function test_belongs_to_user_and_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $review = Review::factory()->for($user)->for($book)->create();

        $this->assertInstanceOf(BelongsTo::class, $review->user());
        $this->assertInstanceOf(BelongsTo::class, $review->book());
        $this->assertTrue($review->user->is($user));
        $this->assertTrue($review->book->is($book));
    }

    /**
     * いいねしたユーザーを取得できる。
     */
    public function test_can_get_users_who_liked(): void
    {
        $review = Review::factory()->create();
        $users = User::factory()->count(2)->create();

        $review->likedByUsers()->attach($users);

        $this->assertInstanceOf(BelongsToMany::class, $review->likedByUsers());
        $this->assertCount(2, $review->likedByUsers);
    }

    /**
     * 同じユーザーが同じ書籍に2件レビューすることはできない。
     */
    public function test_user_cannot_review_same_book_twice(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        Review::factory()->for($user)->for($book)->create();

        $this->expectException(QueryException::class);

        Review::factory()->for($user)->for($book)->create();
    }

    /**
     * レビューを削除するといいねも削除される。
     */
    public function test_deleting_review_cascades_likes(): void
    {
        $review = Review::factory()->create();
        $review->likedByUsers()->attach(User::factory()->create());

        $review->delete();

        $this->assertDatabaseCount('review_likes', 0);
    }
}
