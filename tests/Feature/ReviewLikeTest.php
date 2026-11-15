<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewLikeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Review $review;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->review = Review::factory()->create();
    }

    /**
     * レビューにいいねできる。
     */
    public function test_user_can_like_review(): void
    {
        $this->actingAs($this->user)
            ->post(route('reviews.like', $this->review))
            ->assertRedirect(route('books.show', $this->review->book_id))
            ->assertSessionHas('success', 'レビューに『いいね』しました。');

        $this->assertDatabaseHas('review_likes', ['user_id' => $this->user->id, 'review_id' => $this->review->id]);
    }

    /**
     * いいね済みのレビューをもう一度押すと取り消される。
     */
    public function test_toggling_again_removes_like(): void
    {
        $this->review->likedByUsers()->attach($this->user);

        $this->actingAs($this->user)
            ->post(route('reviews.like', $this->review))
            ->assertSessionHas('success', 'レビューの『いいね』を取り消しました。');

        $this->assertDatabaseMissing('review_likes', ['user_id' => $this->user->id, 'review_id' => $this->review->id]);
    }

    /**
     * 複数のユーザーのいいねが数えられ書籍詳細に件数が表示される。
     */
    public function test_likes_from_multiple_users_are_counted_and_displayed(): void
    {
        $this->review->likedByUsers()->attach(User::factory()->count(2)->create());

        $this->actingAs($this->user)->post(route('reviews.like', $this->review));

        $this->assertSame(3, $this->review->likedByUsers()->count());

        $review = $this->get(route('books.show', $this->review->book_id))
            ->assertOk()
            ->viewData('book')
            ->reviews
            ->firstWhere('id', $this->review->id);
        $this->assertSame(3, $review->liked_by_users_count);
    }

    /**
     * 未ログインではいいねできない。
     */
    public function test_guest_cannot_like_review(): void
    {
        $this->post(route('reviews.like', $this->review))->assertRedirect(route('login'));

        $this->assertDatabaseCount('review_likes', 0);
    }

    /**
     * 存在しないレビューにはいいねできない。
     */
    public function test_cannot_like_nonexistent_review(): void
    {
        $this->actingAs($this->user)->post(route('reviews.like', 9999))->assertNotFound();
    }
}
