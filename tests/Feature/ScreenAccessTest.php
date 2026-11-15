<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScreenAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * トップページにアクセスすると書籍一覧に移動する。
     */
    public function test_top_page_redirects_to_books_index(): void
    {
        $this->get('/')->assertRedirect(route('books.index'));
    }

    /**
     * 未ログインでも書籍一覧と書籍詳細とランキングを表示できる。
     */
    public function test_guest_can_view_books_index_show_and_ranking(): void
    {
        $book = Book::factory()->create(['title' => '公開されている書籍']);

        $this->get(route('books.index'))->assertOk()->assertSee('公開されている書籍');
        $this->get(route('books.show', $book))->assertOk()->assertSee('公開されている書籍');
        $this->get(route('ranking.index'))->assertOk();
    }

    /**
     * 未ログインでもログイン画面と会員登録画面を表示できる。
     */
    public function test_guest_can_view_login_and_register_screens(): void
    {
        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk();
    }

    /**
     * ログインが必要な画面のルート名を返します。
     */
    public static function authRequiredRoutes(): array
    {
        return [
            '書籍登録' => ['books.create'],
            'お気に入り一覧' => ['favorites.index'],
            'ジャンル一覧' => ['genres.index'],
            'ジャンル登録' => ['genres.create'],
            '読書計画一覧' => ['reading-plans.index'],
            '読書計画登録' => ['reading-plans.create'],
            '通知一覧' => ['notifications.index'],
            'マイ読書レポート' => ['reports.index'],
        ];
    }

    /**
     * 未ログインでログインが必要な画面にアクセスするとログイン画面に移動する。
     */
    #[DataProvider('authRequiredRoutes')]
    public function test_guest_is_redirected_to_login_from_protected_screens(string $routeName): void
    {
        $this->get(route($routeName))->assertRedirect(route('login'));
    }

    /**
     * ログイン中はログインが必要な画面を表示できる。
     */
    #[DataProvider('authRequiredRoutes')]
    public function test_authenticated_user_can_view_protected_screens(string $routeName): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route($routeName))
            ->assertOk();
    }

    /**
     * ログイン中は自分の書籍・レビュー・読書計画の編集画面とジャンルの画面を表示できる。
     */
    public function test_authenticated_user_can_view_own_edit_screens_and_genre_screens(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create();
        $review = Review::factory()->for($user)->create();
        $plan = ReadingPlan::factory()->for($user)->create();
        $genre = Genre::factory()->create();

        $this->actingAs($user);

        $this->get(route('books.edit', $book))->assertOk();
        $this->get(route('reviews.edit', $review))->assertOk();
        $this->get(route('reading-plans.edit', $plan))->assertOk();
        $this->get(route('genres.show', $genre))->assertOk();
        $this->get(route('genres.edit', $genre))->assertOk();
    }

    /**
     * ログイン中にログイン画面にアクセスすると書籍一覧に移動する。
     */
    public function test_authenticated_user_is_redirected_from_login_to_books_index(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('login'))
            ->assertRedirect('/books');
    }

    /**
     * 存在しない書籍の詳細画面は404になる。
     */
    public function test_show_returns_404_for_nonexistent_book(): void
    {
        $this->get(route('books.show', 9999))->assertNotFound();
    }
}
