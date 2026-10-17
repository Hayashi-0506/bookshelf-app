<?php

namespace App\Http\Controllers;

use App\Models\Review;
use Illuminate\Http\RedirectResponse;

class ReviewLikeController extends Controller
{
    /**
     * いいねの登録または削除を行います。
     */
    public function toggle(Review $review): RedirectResponse
    {
        $result = $review->likedByUsers()->toggle(auth()->id());

        $message = empty($result['attached'])
            ? 'レビューの『いいね』を取り消しました。'
            : 'レビューに『いいね』しました。';

        return redirect()->route('books.show', $review->book_id)
            ->with('success', $message);
    }
}
