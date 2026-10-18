<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\View\View;

class RankingController extends Controller
{
    /**
     * 評価ランキングの一覧を取得します。
     */
    public function index(): View
    {
        $rankedBooks = Book::has('reviews')
            ->withAvg('reviews', 'rating')
            ->orderBy('reviews_avg_rating', 'desc')
            ->orderBy('id')
            ->limit(10)
            ->get();

        return view('ranking.index', compact('rankedBooks'));
    }
}
