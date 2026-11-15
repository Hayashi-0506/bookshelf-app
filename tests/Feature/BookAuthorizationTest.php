<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private User $other;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->other = User::factory()->create();
        $this->book = Book::factory()->for($this->owner)->create(['title' => '元のタイトル']);
    }

    /**
     * 更新用の正しい入力値を返します。
     */
    private function updateData(): array
    {
        return [
            'title' => '書き換えられたタイトル',
            'author' => '著者',
            'isbn' => '9784873115658',
            'published_date' => '2012-06-23',
            'genres' => [Genre::factory()->create()->id],
        ];
    }

    /**
     * ポリシーは登録者本人にだけ更新と削除を許可する。
     */
    public function test_policy_allows_only_owner_to_update_and_delete(): void
    {
        $this->assertTrue($this->owner->can('update', $this->book));
        $this->assertTrue($this->owner->can('delete', $this->book));
        $this->assertFalse($this->other->can('update', $this->book));
        $this->assertFalse($this->other->can('delete', $this->book));
    }

    /**
     * 他のユーザーの書籍の編集画面は403になる。
     */
    public function test_other_user_cannot_view_edit_screen(): void
    {
        $this->actingAs($this->other)->get(route('books.edit', $this->book))->assertForbidden();
    }

    /**
     * 他のユーザーの書籍は更新できず内容も変わらない。
     */
    public function test_other_user_cannot_update_book(): void
    {
        $this->actingAs($this->other)
            ->put(route('books.update', $this->book), $this->updateData())
            ->assertForbidden();

        $this->assertSame('元のタイトル', $this->book->fresh()->title);
    }

    /**
     * 他のユーザーの書籍は削除できない。
     */
    public function test_other_user_cannot_delete_book(): void
    {
        $this->actingAs($this->other)
            ->delete(route('books.destroy', $this->book))
            ->assertForbidden();

        $this->assertModelExists($this->book);
    }

    /**
     * 未ログインでは書籍の登録・編集・更新・削除ができずログイン画面に移動する。
     */
    public function test_guest_is_redirected_to_login_for_write_actions(): void
    {
        $this->get(route('books.create'))->assertRedirect(route('login'));
        $this->post(route('books.store'), $this->updateData())->assertRedirect(route('login'));
        $this->get(route('books.edit', $this->book))->assertRedirect(route('login'));
        $this->put(route('books.update', $this->book), $this->updateData())->assertRedirect(route('login'));
        $this->delete(route('books.destroy', $this->book))->assertRedirect(route('login'));

        $this->assertSame('元のタイトル', $this->book->fresh()->title);
        $this->assertDatabaseCount('books', 1);
    }

    /**
     * 書籍詳細では登録者本人にだけ編集と削除のボタンが表示される。
     */
    public function test_edit_and_delete_buttons_are_shown_only_to_owner(): void
    {
        $editUrl = route('books.edit', $this->book);

        $this->actingAs($this->owner)->get(route('books.show', $this->book))->assertSee($editUrl);
        $this->actingAs($this->other)->get(route('books.show', $this->book))->assertDontSee($editUrl);
        auth()->logout();
        $this->get(route('books.show', $this->book))->assertDontSee($editUrl);
    }

    /**
     * 登録者本人は編集画面を表示できる。
     */
    public function test_owner_can_view_edit_screen(): void
    {
        $this->actingAs($this->owner)->get(route('books.edit', $this->book))->assertOk();
    }

    /**
     * APIでも他のユーザーの書籍は更新・削除できない。
     */
    public function test_other_user_cannot_update_or_delete_book_via_api(): void
    {
        Sanctum::actingAs($this->other);

        $this->putJson("/api/v1/books/{$this->book->id}", $this->updateData())->assertForbidden();
        $this->deleteJson("/api/v1/books/{$this->book->id}")->assertForbidden();

        $this->assertSame('元のタイトル', $this->book->fresh()->title);
        $this->assertModelExists($this->book);
    }
}
