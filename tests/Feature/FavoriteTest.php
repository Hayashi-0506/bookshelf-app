<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FavoriteTest extends TestCase
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
     * お気に入りに登録できる。
     */
    public function test_user_can_add_favorite(): void
    {
        $this->actingAs($this->user)
            ->post(route('favorites.toggle', $this->book))
            ->assertRedirect(route('books.show', $this->book))
            ->assertSessionHas('success', 'お気に入りに登録しました。');

        $this->assertDatabaseHas('favorites', ['user_id' => $this->user->id, 'book_id' => $this->book->id]);
    }

    /**
     * お気に入り済みの書籍をもう一度押すと解除される。
     */
    public function test_toggling_again_removes_favorite(): void
    {
        $this->user->favoriteBooks()->attach($this->book);

        $this->actingAs($this->user)
            ->post(route('favorites.toggle', $this->book))
            ->assertSessionHas('success', 'お気に入りを解除しました。');

        $this->assertDatabaseMissing('favorites', ['user_id' => $this->user->id, 'book_id' => $this->book->id]);
    }

    /**
     * 他のユーザーのお気に入りには影響しない。
     */
    public function test_toggle_does_not_affect_other_users_favorites(): void
    {
        $other = User::factory()->create();
        $other->favoriteBooks()->attach($this->book);

        $this->actingAs($this->user)->post(route('favorites.toggle', $this->book));
        $this->actingAs($this->user)->post(route('favorites.toggle', $this->book));

        $this->assertDatabaseHas('favorites', ['user_id' => $other->id, 'book_id' => $this->book->id]);
        $this->assertDatabaseCount('favorites', 1);
    }

    /**
     * お気に入り一覧には自分のお気に入りの書籍だけが表示される。
     */
    public function test_favorites_index_displays_only_own_favorites(): void
    {
        $this->book->update(['title' => '自分のお気に入り']);
        $this->user->favoriteBooks()->attach($this->book);
        User::factory()->create()->favoriteBooks()->attach(Book::factory()->create(['title' => '他人のお気に入り']));

        $this->actingAs($this->user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('自分のお気に入り')
            ->assertDontSee('他人のお気に入り');
    }

    /**
     * お気に入りが0件の場合はメッセージが表示される。
     */
    public function test_favorites_index_displays_message_when_empty(): void
    {
        $this->actingAs($this->user)
            ->get(route('favorites.index'))
            ->assertOk()
            ->assertSee('お気に入りに登録された書籍はありません。');
    }

    /**
     * 未ログインではお気に入りに登録できない。
     */
    public function test_guest_cannot_add_favorite(): void
    {
        $this->post(route('favorites.toggle', $this->book))->assertRedirect(route('login'));

        $this->assertDatabaseCount('favorites', 0);
    }

    /**
     * 存在しない書籍はお気に入りに登録できない。
     */
    public function test_cannot_favorite_nonexistent_book(): void
    {
        $this->actingAs($this->user)->post(route('favorites.toggle', 9999))->assertNotFound();
    }
}
