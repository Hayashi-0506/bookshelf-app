<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * ジャンル一覧にジャンル名と書籍数が表示される。
     */
    public function test_index_displays_genre_names_and_book_counts(): void
    {
        $genre = Genre::factory()->hasAttached(Book::factory()->count(2))->create(['name' => 'ビジネス']);

        $response = $this->actingAs($this->user)->get(route('genres.index'))->assertOk()->assertSee('ビジネス');

        $this->assertSame(2, $response->viewData('genres')->firstWhere('id', $genre->id)->books_count);
    }

    /**
     * ジャンルを登録できる。
     */
    public function test_user_can_create_genre(): void
    {
        $this->actingAs($this->user)
            ->post(route('genres.store'), ['name' => '料理'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを登録しました。');

        $this->assertDatabaseHas('genres', ['name' => '料理']);
    }

    /**
     * ジャンル名が未入力または255文字を超える場合は登録できない。
     */
    public function test_name_is_required_and_max_255_characters(): void
    {
        $this->actingAs($this->user)
            ->post(route('genres.store'), ['name' => ''])
            ->assertSessionHasErrors(['name' => 'ジャンル名を入力してください。']);

        $this->actingAs($this->user)
            ->post(route('genres.store'), ['name' => str_repeat('あ', 256)])
            ->assertSessionHasErrors(['name' => 'ジャンル名は255文字以内で入力してください。']);

        $this->assertDatabaseCount('genres', 0);
    }

    /**
     * 登録済みのジャンル名は登録できない。
     */
    public function test_name_must_be_unique(): void
    {
        Genre::factory()->create(['name' => '小説']);

        $this->actingAs($this->user)
            ->post(route('genres.store'), ['name' => '小説'])
            ->assertSessionHasErrors(['name' => 'このジャンル名はすでに登録されています。']);
    }

    /**
     * ジャンル詳細にそのジャンルの書籍だけが表示される。
     */
    public function test_show_displays_only_books_in_genre(): void
    {
        $genre = Genre::factory()->create();
        Book::factory()->hasAttached($genre)->create(['title' => '対象ジャンルの書籍']);
        Book::factory()->create(['title' => '別ジャンルの書籍']);

        $this->actingAs($this->user)
            ->get(route('genres.show', $genre))
            ->assertOk()
            ->assertSee('対象ジャンルの書籍')
            ->assertDontSee('別ジャンルの書籍');
    }

    /**
     * ジャンル名を更新できる。
     */
    public function test_user_can_update_genre(): void
    {
        $genre = Genre::factory()->create(['name' => '旧ジャンル名']);

        $this->actingAs($this->user)
            ->put(route('genres.update', $genre), ['name' => '新ジャンル名'])
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを更新しました。');

        $this->assertSame('新ジャンル名', $genre->fresh()->name);
    }

    /**
     * ジャンル名を変更せずに更新できるが他のジャンルと同じ名前にはできない。
     */
    public function test_can_keep_same_name_but_not_duplicate_another(): void
    {
        $genre = Genre::factory()->create(['name' => '歴史']);
        Genre::factory()->create(['name' => '科学']);

        $this->actingAs($this->user)
            ->put(route('genres.update', $genre), ['name' => '歴史'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->user)
            ->put(route('genres.update', $genre), ['name' => '科学'])
            ->assertSessionHasErrors(['name' => 'このジャンル名はすでに登録されています。']);
    }

    /**
     * 書籍が紐付いていないジャンルは削除できる。
     */
    public function test_genre_without_books_can_be_deleted(): void
    {
        $genre = Genre::factory()->create();

        $this->actingAs($this->user)
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('success', 'ジャンルを削除しました。');

        $this->assertModelMissing($genre);
    }

    /**
     * 書籍が紐付いているジャンルは削除できない。
     */
    public function test_genre_with_books_cannot_be_deleted(): void
    {
        $genre = Genre::factory()->hasAttached(Book::factory())->create();

        $this->actingAs($this->user)
            ->delete(route('genres.destroy', $genre))
            ->assertRedirect(route('genres.index'))
            ->assertSessionHas('error', '書籍に登録されているジャンルは削除できません。');

        $this->assertModelExists($genre);
    }
}
