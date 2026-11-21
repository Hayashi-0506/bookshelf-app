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
     * コメントが未入力の場合は投稿・更新できない。
     */
    public function test_comment_is_required(): void
    {
        collect([null, ''])->each(
            fn (?string $comment) => $this->actingAs($this->user)
                ->post(route('reviews.store', $this->book), ['rating' => 3, 'comment' => $comment])
                ->assertSessionHasErrors(['comment' => 'コメントを入力してください。'])
        );

        $this->assertDatabaseCount('reviews', 0);

        $review = Review::factory()->for($this->user)->create(['comment' => '元のコメント']);

        $this->actingAs($this->user)
            ->put(route('reviews.update', $review), ['rating' => 3, 'comment' => ''])
            ->assertSessionHasErrors(['comment' => 'コメントを入力してください。']);

        $this->assertSame('元のコメント', $review->fresh()->comment);
    }

    /**
     * コメントは1000文字を超えると投稿・更新できず、1000文字なら投稿できる。
     */
    public function test_comment_must_not_exceed_1000_characters(): void
    {
        $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), ['rating' => 3, 'comment' => str_repeat('あ', 1001)])
            ->assertSessionHasErrors(['comment' => 'コメントは1000文字以内で入力してください。']);

        $this->assertDatabaseCount('reviews', 0);

        $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), ['rating' => 3, 'comment' => str_repeat('あ', 1000)])
            ->assertSessionHasNoErrors();

        $review = Review::firstOrFail();
        $this->assertSame(1000, mb_strlen($review->comment));

        $this->actingAs($this->user)
            ->put(route('reviews.update', $review), ['rating' => 3, 'comment' => str_repeat('い', 1001)])
            ->assertSessionHasErrors(['comment' => 'コメントは1000文字以内で入力してください。']);

        $this->assertSame(str_repeat('あ', 1000), $review->fresh()->comment);
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
                ->post(route('reviews.store', $book), ['rating' => $rating, 'comment' => '評価のテストです。'])
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
     * レビュー投稿枠は未レビューなら表示され、自分がレビュー済みなら表示されない。
     */
    public function test_review_form_is_hidden_after_user_reviewed(): void
    {
        $formAction = route('reviews.store', $this->book);

        // 他のユーザーだけがレビューしている場合は表示される
        Review::factory()->for($this->book)->create();

        $this->actingAs($this->user)
            ->get(route('books.show', $this->book))
            ->assertOk()
            ->assertSee('レビューを投稿')
            ->assertSee($formAction);

        // 自分がレビューすると表示されなくなる
        Review::factory()->for($this->user)->for($this->book)->create(['comment' => '自分のレビューです。']);

        $this->actingAs($this->user)
            ->get(route('books.show', $this->book))
            ->assertOk()
            ->assertSee('自分のレビューです。')
            ->assertDontSee('レビューを投稿')
            ->assertDontSee($formAction);
    }

    /**
     * 他のユーザーがレビュー済みの書籍にもレビューを投稿できる。
     */
    public function test_can_review_book_reviewed_by_others(): void
    {
        Review::factory()->for($this->book)->create();

        $this->actingAs($this->user)
            ->post(route('reviews.store', $this->book), ['rating' => 5, 'comment' => '素晴らしい本でした！'])
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
        $this->actingAs($this->user)->put(route('reviews.update', $review), ['rating' => 1, 'comment' => '書き換えようとしたコメント'])->assertForbidden();
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
