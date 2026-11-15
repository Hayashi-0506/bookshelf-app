<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SortTest extends TestCase
{
    use RefreshDatabase;

    private Book $old;

    private Book $middle;

    private Book $new;

    protected function setUp(): void
    {
        parent::setUp();

        $this->old = Book::factory()->create(['title' => 'Charlie', 'created_at' => now()->subDays(3)]);
        $this->middle = Book::factory()->create(['title' => 'Alpha', 'created_at' => now()->subDays(2)]);
        $this->new = Book::factory()->create(['title' => 'Bravo', 'created_at' => now()->subDay()]);
    }

    /**
     * 書籍一覧の表示順のIDを返します。
     */
    private function sortedIds(string $query = ''): array
    {
        return $this->get(route('books.index').$query)
            ->assertOk()
            ->viewData('books')
            ->pluck('id')
            ->all();
    }

    /**
     * 並び順を指定しない場合は登録が新しい順になる。
     */
    public function test_default_order_is_newest(): void
    {
        $this->assertSame([$this->new->id, $this->middle->id, $this->old->id], $this->sortedIds());
    }

    /**
     * 新しい順を指定できる。
     */
    public function test_can_sort_by_newest(): void
    {
        $this->assertSame([$this->new->id, $this->middle->id, $this->old->id], $this->sortedIds('?sort=newest'));
    }

    /**
     * 古い順を指定できる。
     */
    public function test_can_sort_by_oldest(): void
    {
        $this->assertSame([$this->old->id, $this->middle->id, $this->new->id], $this->sortedIds('?sort=oldest'));
    }

    /**
     * タイトル順を指定できる。
     */
    public function test_can_sort_by_title(): void
    {
        $this->assertSame([$this->middle->id, $this->new->id, $this->old->id], $this->sortedIds('?sort=title'));
    }

    /**
     * 評価順を指定するとレビュー平均評価の高い順になる。
     */
    public function test_rating_sort_orders_by_average_rating(): void
    {
        Review::factory()->for($this->old)->create(['rating' => 5]);
        Review::factory()->for($this->middle)->create(['rating' => 2]);
        Review::factory()->for($this->new)->create(['rating' => 4]);

        $this->assertSame([$this->old->id, $this->new->id, $this->middle->id], $this->sortedIds('?sort=rating'));
    }

    /**
     * 登録日時が同じ場合はID順になる。
     */
    public function test_ties_are_ordered_by_id(): void
    {
        $time = now()->subHour();
        $first = Book::factory()->create(['created_at' => $time]);
        $second = Book::factory()->create(['created_at' => $time]);

        $ids = $this->sortedIds('?sort=newest');

        $this->assertLessThan(array_search($second->id, $ids), array_search($first->id, $ids));
    }

    /**
     * 並び替えと検索条件を組み合わせられる。
     */
    public function test_can_combine_sort_and_search(): void
    {
        // タイトルに「r」を含む Bravo と Charlie だけが、タイトル順に並ぶ
        $this->assertSame([$this->new->id, $this->old->id], $this->sortedIds('?keyword=r&sort=title'));
    }

    /**
     * ページを移動しても並び順が引き継がれる。
     */
    public function test_pagination_keeps_sort_order(): void
    {
        Book::factory()->count(10)->create();

        $books = $this->get(route('books.index').'?sort=title')->viewData('books');

        $this->assertStringContainsString('sort=title', $books->nextPageUrl());
    }

    /**
     * 定義されていない並び順はバリデーションエラーになる。
     */
    public function test_undefined_sort_fails_validation(): void
    {
        $this->get(route('books.index').'?sort=unknown')->assertSessionHasErrors('sort');
    }
}
