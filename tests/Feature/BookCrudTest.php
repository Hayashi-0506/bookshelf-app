<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * 正しい入力値の書籍データを返します。
     */
    private function validData(array $overrides = []): array
    {
        return [
            'title' => 'リーダブルコード',
            'author' => 'Dustin Boswell',
            'isbn' => '9784873115658',
            'published_date' => '2012-06-23',
            'description' => 'より良いコードを書くためのテクニック',
            'image_url' => 'https://placehold.co/200x300',
            'genres' => Genre::factory()->count(2)->create()->pluck('id')->all(),
            ...$overrides,
        ];
    }

    /**
     * 書籍一覧に登録済みの書籍が表示される。
     */
    public function test_index_displays_books(): void
    {
        Book::factory()->create(['title' => '一覧に表示される書籍']);

        $this->get(route('books.index'))
            ->assertOk()
            ->assertSee('一覧に表示される書籍');
    }

    /**
     * 書籍一覧は10件ずつ表示される。
     */
    public function test_index_is_paginated_by_ten(): void
    {
        Book::factory()->count(11)->create();

        $response = $this->get(route('books.index'))->assertOk();

        $this->assertCount(10, $response->viewData('books'));
        $this->assertSame(11, $response->viewData('books')->total());
    }

    /**
     * 書籍詳細に書籍の情報とジャンルとレビューが表示される。
     */
    public function test_show_displays_book_details_genres_and_reviews(): void
    {
        $genre = Genre::factory()->create(['name' => 'プログラミング']);
        $book = Book::factory()->hasAttached($genre)->create([
            'title' => 'リーダブルコード',
            'author' => 'Dustin Boswell',
        ]);
        $book->reviews()->create(['user_id' => $this->user->id, 'rating' => 5, 'comment' => 'とても参考になりました。']);

        $this->get(route('books.show', $book))
            ->assertOk()
            ->assertSee('リーダブルコード')
            ->assertSee('Dustin Boswell')
            ->assertSee('プログラミング')
            ->assertSee('とても参考になりました。');
    }

    /**
     * 書籍登録画面にジャンルの選択肢が表示される。
     */
    public function test_create_screen_displays_genre_options(): void
    {
        Genre::factory()->create(['name' => '技術書']);

        $this->actingAs($this->user)
            ->get(route('books.create'))
            ->assertOk()
            ->assertSee('技術書');
    }

    /**
     * 書籍を登録するとログインユーザーの書籍として保存されジャンルが紐付く。
     */
    public function test_store_saves_book_for_logged_in_user_with_genres(): void
    {
        $data = $this->validData();

        $response = $this->actingAs($this->user)->post(route('books.store'), $data);

        $book = Book::firstWhere('isbn', '9784873115658');
        $response->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', '書籍を登録しました。');
        $this->assertDatabaseHas('books', [
            'title' => 'リーダブルコード',
            'author' => 'Dustin Boswell',
            'user_id' => $this->user->id,
        ]);
        $this->assertEqualsCanonicalizing($data['genres'], $book->genres->pluck('id')->all());
    }

    /**
     * 説明文と画像URLは未入力でも登録できる。
     */
    public function test_description_and_image_url_are_optional(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['description' => null, 'image_url' => null]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('books', ['isbn' => '9784873115658', 'description' => null, 'image_url' => null]);
    }

    /**
     * 必須項目が未入力の場合は登録できない。
     */
    public function test_store_fails_when_required_fields_are_missing(): void
    {
        $this->actingAs($this->user)
            ->from(route('books.create'))
            ->post(route('books.store'), [])
            ->assertRedirect(route('books.create'))
            ->assertSessionHasErrors([
                'title' => 'タイトルを入力してください。',
                'author' => '著者を入力してください。',
                'isbn' => 'ISBNを入力してください。',
                'published_date' => '出版日を入力してください。',
                'genres' => 'ジャンルを1つ以上選択してください。',
            ]);

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * タイトルと著者は255文字を超えると登録できない。
     */
    public function test_title_and_author_must_not_exceed_255_characters(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData([
                'title' => str_repeat('あ', 256),
                'author' => str_repeat('い', 256),
            ]))
            ->assertSessionHasErrors([
                'title' => 'タイトルは255文字以内で入力してください。',
                'author' => '著者は255文字以内で入力してください。',
            ]);
    }

    /**
     * ISBNが13桁の数字でない場合は登録できない。
     */
    public function test_isbn_must_be_13_digits(): void
    {
        collect(['978487311565', '97848731156580', '978-4873115658', 'abcdefghijklm'])->each(
            fn (string $isbn) => $this->actingAs($this->user)
                ->post(route('books.store'), $this->validData(['isbn' => $isbn]))
                ->assertSessionHasErrors(['isbn' => 'ISBNは13桁の数字で入力してください。'])
        );

        $this->assertDatabaseCount('books', 0);
    }

    /**
     * 登録済みのISBNは登録できない。
     */
    public function test_isbn_must_be_unique(): void
    {
        Book::factory()->create(['isbn' => '9784873115658']);

        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData())
            ->assertSessionHasErrors(['isbn' => 'このISBNの書籍はすでに登録されています。']);
    }

    /**
     * 出版日と画像URLの形式が正しくない場合は登録できない。
     */
    public function test_published_date_and_image_url_must_be_valid_format(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData([
                'published_date' => '2012/06/23',
                'image_url' => 'not-a-url',
            ]))
            ->assertSessionHasErrors([
                'published_date' => '出版日は「YYYY-MM-DD」の形式で入力してください。',
                'image_url' => '画像URLは正しいURLの形式で入力してください。',
            ]);
    }

    /**
     * 存在しないジャンルは選択できない。
     */
    public function test_genres_must_exist(): void
    {
        $this->actingAs($this->user)
            ->post(route('books.store'), $this->validData(['genres' => [9999]]))
            ->assertSessionHasErrors(['genres.0' => '選択されたジャンルが存在しません。']);
    }

    /**
     * 自分の書籍の編集画面に登録済みの内容が表示される。
     */
    public function test_edit_screen_displays_current_values(): void
    {
        $book = Book::factory()->for($this->user)->create(['title' => '編集前のタイトル']);

        $this->actingAs($this->user)
            ->get(route('books.edit', $book))
            ->assertOk()
            ->assertSee('編集前のタイトル');
    }

    /**
     * 自分の書籍を更新できジャンルの紐付けも入れ替わる。
     */
    public function test_owner_can_update_book_and_sync_genres(): void
    {
        $oldGenre = Genre::factory()->create();
        $book = Book::factory()->for($this->user)->hasAttached($oldGenre)->create();
        $data = $this->validData(['title' => '更新後のタイトル']);

        $this->actingAs($this->user)
            ->put(route('books.update', $book), $data)
            ->assertRedirect(route('books.show', $book))
            ->assertSessionHas('success', '書籍を更新しました。');

        $book->refresh();
        $this->assertSame('更新後のタイトル', $book->title);
        $this->assertSame('9784873115658', $book->isbn);
        $this->assertEqualsCanonicalizing($data['genres'], $book->genres->pluck('id')->all());
    }

    /**
     * ISBNを変更せずに更新できる。
     */
    public function test_can_update_without_changing_isbn(): void
    {
        $book = Book::factory()->for($this->user)->create(['isbn' => '9784873115658']);

        $this->actingAs($this->user)
            ->put(route('books.update', $book), $this->validData(['title' => 'タイトルだけ変更']))
            ->assertSessionHasNoErrors();

        $this->assertSame('タイトルだけ変更', $book->fresh()->title);
    }

    /**
     * 他の書籍のISBNには更新できない。
     */
    public function test_cannot_update_to_another_books_isbn(): void
    {
        Book::factory()->create(['isbn' => '9784873115658']);
        $book = Book::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->put(route('books.update', $book), $this->validData())
            ->assertSessionHasErrors(['isbn' => 'このISBNの書籍はすでに登録されています。']);
    }

    /**
     * 自分の書籍を削除できる。
     */
    public function test_owner_can_delete_book(): void
    {
        $book = Book::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->delete(route('books.destroy', $book))
            ->assertRedirect(route('books.index'))
            ->assertSessionHas('success', '書籍を削除しました。');

        $this->assertModelMissing($book);
    }
}
