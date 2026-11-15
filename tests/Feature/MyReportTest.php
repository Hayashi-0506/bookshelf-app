<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\Genre;
use App\Models\ReadingPlan;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    /**
     * ログインユーザーで、指定した評価のレビューを投稿します。
     */
    private function review(int $rating, ?Book $book = null): Review
    {
        return Review::factory()
            ->for($this->user)
            ->for($book ?? Book::factory()->create())
            ->create(['rating' => $rating]);
    }

    /**
     * マイ読書レポートの統計データを取得します。
     */
    private function stats(): array
    {
        return $this->actingAs($this->user)
            ->get(route('reports.index'))
            ->assertOk()
            ->viewData('stats');
    }

    /**
     * 未ログインではマイ読書レポートを表示できない。
     */
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('reports.index'))->assertRedirect(route('login'));
    }

    /**
     * データがない場合も表示でき各値は0または空になる。
     */
    public function test_displays_zero_values_when_no_data(): void
    {
        $stats = $this->stats();

        $this->assertSame(['total_reviews' => 0, 'books_read' => 0, 'average_rating' => 0.0], $stats['summary']);
        $this->assertSame([0, 0, 0, 0, 0], $stats['rating_distribution']->all());
        $this->assertCount(0, $stats['top_rated_books']);
        $this->assertCount(0, $stats['genre_ratings']);
    }

    /**
     * 総レビュー数と平均評価を集計する。
     */
    public function test_summarizes_total_reviews_and_average_rating(): void
    {
        collect([5, 4, 4])->each(fn (int $rating) => $this->review($rating));

        $summary = $this->stats()['summary'];

        $this->assertSame(3, $summary['total_reviews']);
        $this->assertSame(4.3, $summary['average_rating']);
    }

    /**
     * 読了冊数は読了した読書計画の書籍数を重複なしで数える。
     */
    public function test_books_read_counts_distinct_books_of_completed_plans(): void
    {
        $book = Book::factory()->create();
        ReadingPlan::factory()->for($this->user)->for($book)->create(['status' => ReadingPlanStatus::Completed, 'completed_at' => today()->subMonth()]);
        ReadingPlan::factory()->for($this->user)->for($book)->create(['status' => ReadingPlanStatus::Completed, 'completed_at' => today()]);
        ReadingPlan::factory()->for($this->user)->create(['status' => ReadingPlanStatus::Completed, 'completed_at' => today()]);
        ReadingPlan::factory()->for($this->user)->create(['status' => ReadingPlanStatus::InProgress]);
        ReadingPlan::factory()->for($this->user)->create(['status' => ReadingPlanStatus::Expired, 'target_date' => today()->subDay()]);

        $this->assertSame(2, $this->stats()['summary']['books_read']);
    }

    /**
     * 評価分布は星1から星5の順に件数を集計する。
     */
    public function test_rating_distribution_counts_from_one_to_five_stars(): void
    {
        collect([1, 3, 3, 5, 5, 5])->each(fn (int $rating) => $this->review($rating));

        $this->assertSame([1, 0, 2, 0, 3], $this->stats()['rating_distribution']->all());
    }

    /**
     * 高評価書籍は評価4以上の書籍を評価の高い順に最大5件表示する。
     */
    public function test_top_rated_books_lists_up_to_five_books_rated_four_or_more(): void
    {
        $four = $this->review(4, Book::factory()->create(['title' => '評価4の本']));
        $fives = collect(range(1, 5))->map(fn () => $this->review(5));
        $this->review(3, Book::factory()->create(['title' => '評価3の本']));

        $topRated = $this->stats()['top_rated_books'];

        $this->assertCount(5, $topRated);
        $this->assertSame(array_fill(0, 5, 5), $topRated->pluck('rating')->all());
        // 評価が同じ書籍どうしの順番は投稿日時で決まり、同じ秒に作成すると一定にならないため、順番は問わずに比較する
        $this->assertEqualsCanonicalizing($fives->pluck('book_id')->all(), $topRated->pluck('id')->all());
        $this->assertNotContains($four->book_id, $topRated->pluck('id'));
        $this->assertSame([0, 1, 2, 3, 4], $topRated->keys()->all());
    }

    /**
     * 高評価書籍には書籍のIDとタイトルと著者と評価が含まれる。
     */
    public function test_top_rated_books_include_id_title_author_and_rating(): void
    {
        $book = Book::factory()->create(['title' => 'リーダブルコード', 'author' => 'Dustin Boswell']);
        $this->review(4, $book);

        $this->assertSame(
            [['id' => $book->id, 'title' => 'リーダブルコード', 'author' => 'Dustin Boswell', 'rating' => 4]],
            $this->stats()['top_rated_books']->all(),
        );
    }

    /**
     * 評価4以上の書籍がない場合はメッセージが表示される。
     */
    public function test_displays_message_when_no_book_rated_four_or_more(): void
    {
        $this->review(3);

        $this->actingAs($this->user)
            ->get(route('reports.index'))
            ->assertSee('4星以上の書籍がありません');
    }

    /**
     * ジャンル別評価は平均評価の高い順にレビュー件数と平均を集計する。
     */
    public function test_genre_ratings_summarize_count_and_average_ordered_by_average(): void
    {
        $novel = Genre::factory()->create(['name' => '小説']);
        $business = Genre::factory()->create(['name' => 'ビジネス']);
        $this->review(5, Book::factory()->hasAttached([$novel, $business])->create());
        $this->review(3, Book::factory()->hasAttached($novel)->create());
        $this->review(2, Book::factory()->hasAttached($business)->create());

        $genreRatings = $this->stats()['genre_ratings'];

        $this->assertSame([
            ['id' => $novel->id, 'name' => '小説', 'count' => 2, 'average_rating' => 4.0],
            ['id' => $business->id, 'name' => 'ビジネス', 'count' => 2, 'average_rating' => 3.5],
        ], $genreRatings->all());
    }

    /**
     * ジャンル別評価は上位5件までで平均が同じ場合は件数の多い順になる。
     */
    public function test_genre_ratings_are_limited_to_five_and_tie_broken_by_count(): void
    {
        $genres = Genre::factory()->count(6)->create();
        $genres->each(fn (Genre $genre) => $this->review(4, Book::factory()->hasAttached($genre)->create()));
        $popular = $genres->last();
        $this->review(4, Book::factory()->hasAttached($popular)->create());

        $genreRatings = $this->stats()['genre_ratings'];

        $this->assertCount(5, $genreRatings);
        $this->assertSame($popular->id, $genreRatings->first()['id']);
        $this->assertSame(2, $genreRatings->first()['count']);
    }

    /**
     * 他のユーザーのレビューや読書計画は集計に含まれない。
     */
    public function test_excludes_other_users_data(): void
    {
        $this->review(5);
        $other = User::factory()->create();
        Review::factory()->for($other)->create(['rating' => 1]);
        ReadingPlan::factory()->for($other)->create(['status' => ReadingPlanStatus::Completed, 'completed_at' => today()]);

        $stats = $this->stats();

        $this->assertSame(1, $stats['summary']['total_reviews']);
        $this->assertSame(5.0, $stats['summary']['average_rating']);
        $this->assertSame(0, $stats['summary']['books_read']);
        $this->assertSame([0, 0, 0, 0, 1], $stats['rating_distribution']->all());
    }
}
