<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->book = Book::factory()->create();
    }

    /**
     * 書籍にレビューを投稿できる。
     */
    public function test_user_can_post_review(): void
    {
        $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), ['rating' => 4, 'comment' => '読みやすくておすすめです。'])
            ->assertRedirect(route('books.show', $this->book))
            ->assertSessionHas('success', 'レビューを投稿しました。');

        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->user->id,
            'book_id' => $this->book->id,
            'rating' => 4,
            'comment' => '読みやすくておすすめです。',
        ]);
    }

    /**
     * コメントは未入力でも投稿できる。
     */
    public function test_comment_is_optional(): void
    {
        $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), ['rating' => 3])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('reviews', ['book_id' => $this->book->id, 'comment' => null]);
    }

    /**
     * 評価は1から5の範囲でのみ投稿できる。
     */
    public function test_rating_must_be_between_one_and_five(): void
    {
        collect([null, 0, 6, 'abc'])->each(
            fn ($rating) => $this->actingAs($this->user)
                ->post(route('reviews.store', $this->book), ['rating' => $rating])
                ->assertSessionHasErrors('rating')
        );

        collect([1, 5])->each(function (int $rating) {
            $book = Book::factory()->create();

            $this->actingAs($this->user)
                ->post(route('reviews.store', $book), ['rating' => $rating])
                ->assertSessionHasNoErrors();
        });

        $this->assertDatabaseCount('reviews', 2);
    }

    /**
     * 同じ書籍に2回レビューを投稿することはできない。
     */
    public function test_cannot_review_same_book_twice(): void
    {
        Review::factory()->for($this->user)->for($this->book)->create();

        $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), ['rating' => 5])
            ->assertSessionHasErrors(['book' => 'この書籍にはすでにレビューを投稿しています。']);

        $this->assertDatabaseCount('reviews', 1);
    }

    /**
     * 他のユーザーがレビュー済みの書籍にもレビューを投稿できる。
     */
    public function test_can_review_book_reviewed_by_others(): void
    {
        Review::factory()->for($this->book)->create();

        $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), ['rating' => 5])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('reviews', 2);
    }

    /**
     * 投稿したレビューが書籍詳細に投稿者名と一緒に表示される。
     */
    public function test_review_is_displayed_with_author_name(): void
    {
        $author = User::factory()->create(['name' => 'レビュー太郎']);
        Review::factory()->for($author)->for($this->book)->create(['comment' => '何度も読み返しています。']);

        $this->get(route('books.show', $this->book))
            ->assertOk()
            ->assertSee('レビュー太郎')
            ->assertSee('何度も読み返しています。');
    }

    /**
     * 自分のレビューを編集できる。
     */
    public function test_owner_can_edit_review(): void
    {
        $review = Review::factory()->for($this->user)->for($this->book)->create(['rating' => 2]);

        $this->actingAs($this->user)
            ->get(route('reviews.edit', $review))
            ->assertOk();

        $this->actingAs($this->user)
            ->put(route('reviews.update', $review), ['rating' => 5, 'comment' => '読み直したら良かった。'])
            ->assertRedirect(route('books.show', $this->book))
            ->assertSessionHas('success', 'レビューを更新しました。');

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 5, 'comment' => '読み直したら良かった。']);
    }

    /**
     * レビューの更新時も評価の範囲がチェックされる。
     */
    public function test_rating_is_validated_on_update(): void
    {
        $review = Review::factory()->for($this->user)->create(['rating' => 3]);

        $this->actingAs($this->user)
            ->put(route('reviews.update', $review), ['rating' => 6])
            ->assertSessionHasErrors('rating');

        $this->assertSame(3, $review->fresh()->rating);
    }

    /**
     * 自分のレビューを削除できる。
     */
    public function test_owner_can_delete_review(): void
    {
        $review = Review::factory()->for($this->user)->for($this->book)->create();

        $this->actingAs($this->user)
            ->delete(route('reviews.destroy', $review))
            ->assertRedirect(route('books.show', $this->book))
            ->assertSessionHas('success', 'レビューを削除しました。');

        $this->assertModelMissing($review);
    }

    /**
     * 他のユーザーのレビューは編集・更新・削除できない。
     */
    public function test_other_user_cannot_edit_update_or_delete_review(): void
    {
        $review = Review::factory()->for($this->book)->create(['rating' => 3]);

        $this->actingAs($this->user)->get(route('reviews.edit', $review))->assertForbidden();
        $this->actingAs($this->user)->put(route('reviews.update', $review), ['rating' => 1])->assertForbidden();
        $this->actingAs($this->user)->delete(route('reviews.destroy', $review))->assertForbidden();

        $this->assertSame(3, $review->fresh()->rating);
    }

    /**
     * 未ログインではレビューを投稿できない。
     */
    public function test_guest_cannot_post_review(): void
    {
        $this->post(route('reviews.store', $this->book), ['rating' => 5])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('reviews', 0);
    }
}
