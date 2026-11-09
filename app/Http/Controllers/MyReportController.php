<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class MyReportController extends Controller
{
    /**
     * マイレポートを表示します。
     */
    public function index(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $reviews = $user->reviews()->with('book.genres')->get();

        $stats = [
            'summary' => [
                'total_reviews' => $reviews->count(),
                'books_read' => $user->readingPlans()
                    ->where('status', ReadingPlanStatus::Completed)
                    ->distinct()
                    ->count('book_id'),
                'average_rating' => round($reviews->avg('rating') ?? 0, 1),
            ],
            'rating_distribution' => $this->ratingDistribution($reviews),
            'top_rated_books' => $this->topRatedBooks($reviews),
            'genre_ratings' => $this->genreRatings($reviews),
        ];

        return view('reports.index', compact('stats'));
    }

    /**
     * 評価ごとのレビュー件数を取得します。
     */
    private function ratingDistribution(Collection $reviews): Collection
    {
        $counts = $reviews->countBy('rating');

        return collect(range(1, 5))->map(fn (int $rating) => $counts->get($rating, 0));
    }

    /**
     * 評価4以上の書籍を、評価の高い順に最大5件取得します。
     */
    private function topRatedBooks(Collection $reviews): Collection
    {
        return $reviews
            ->filter(fn (Review $review) => $review->rating >= 4)
            ->sortBy([['rating', 'desc'], ['book_id', 'asc']])
            ->take(5)
            ->map(fn (Review $review) => [
                'id' => $review->book->id,
                'title' => $review->book->title,
                'author' => $review->book->author,
                'rating' => $review->rating,
            ])
            ->values();
    }

    /**
     * ジャンルごとのレビュー件数と平均評価を、平均評価の高い順に最大5件取得します。
     */
    private function genreRatings(Collection $reviews): Collection
    {
        return $reviews
            ->flatMap(fn (Review $review) => $review->book->genres->map(fn ($genre) => [
                'genre' => $genre,
                'rating' => $review->rating,
            ]))
            ->groupBy(fn (array $item) => $item['genre']->id)
            ->map(fn (Collection $items) => [
                'id' => $items->first()['genre']->id,
                'name' => $items->first()['genre']->name,
                'count' => $items->count(),
                'average_rating' => round($items->avg('rating'), 1),
            ])
            ->sortBy([['average_rating', 'desc'], ['count', 'desc'], ['id', 'asc']])
            ->take(5)
            ->values();
    }
}
