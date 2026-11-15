<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RankingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 指定した評価のレビューを、それぞれ別のユーザーで書籍に投稿します。
     */
    private function reviewBook(Book $book, array $ratings): void
    {
        collect($ratings)->each(fn (int $rating) => Review::factory()->for($book)->create(['rating' => $rating]));
    }

    /**
     * 未ログインでもランキングを表示できる。
     */
    public function test_guest_can_view_ranking(): void
    {
        $book = Book::factory()->create(['title' => 'ランキング対象の書籍']);
        $this->reviewBook($book, [5]);

        $this->get(route('ranking.index'))
            ->assertOk()
            ->assertSee('ランキング対象の書籍');
    }

    /**
     * レビュー平均評価の高い順に並ぶ。
     */
    public function test_books_are_ordered_by_average_rating(): void
    {
        $low = Book::factory()->create();
        $high = Book::factory()->create();
        $middle = Book::factory()->create();
        $this->reviewBook($low, [2, 3]);
        $this->reviewBook($high, [5, 5]);
        $this->reviewBook($middle, [4, 4]);

        $ranked = $this->get(route('ranking.index'))->viewData('rankedBooks');

        $this->assertSame([$high->id, $middle->id, $low->id], $ranked->pluck('id')->all());
        $this->assertEquals(5.0, $ranked->first()->reviews_avg_rating);
    }

    /**
     * 平均評価が同じ場合はレビュー件数の多い順、さらに同じ場合はID順に並ぶ。
     */
    public function test_ties_are_broken_by_review_count_then_id(): void
    {
        $fewReviews = Book::factory()->create();
        $manyReviews = Book::factory()->create();
        $sameAsFew = Book::factory()->create();
        $this->reviewBook($fewReviews, [4]);
        $this->reviewBook($manyReviews, [4, 4, 4]);
        $this->reviewBook($sameAsFew, [4]);

        $ranked = $this->get(route('ranking.index'))->viewData('rankedBooks');

        $this->assertSame([$manyReviews->id, $fewReviews->id, $sameAsFew->id], $ranked->pluck('id')->all());
    }

    /**
     * レビューがない書籍はランキングに含まれない。
     */
    public function test_books_without_reviews_are_excluded(): void
    {
        $reviewed = Book::factory()->create();
        $this->reviewBook($reviewed, [3]);
        Book::factory()->create(['title' => 'レビューのない書籍']);

        $ranked = $this->get(route('ranking.index'))
            ->assertDontSee('レビューのない書籍')
            ->viewData('rankedBooks');

        $this->assertSame([$reviewed->id], $ranked->pluck('id')->all());
    }

    /**
     * 上位10件だけが表示される。
     */
    public function test_only_top_ten_books_are_displayed(): void
    {
        Book::factory()->count(12)->create()->each(fn (Book $book) => $this->reviewBook($book, [3]));

        $ranked = $this->get(route('ranking.index'))->viewData('rankedBooks');

        $this->assertCount(10, $ranked);
    }
}
