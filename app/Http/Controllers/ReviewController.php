<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReviewRequest;
use App\Http\Requests\UpdateReviewRequest;
use App\Models\Book;
use App\Models\Review;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ReviewController extends Controller
{
    /**
     * 新規のレビューを登録します。
     */
    public function store(StoreReviewRequest $request, Book $book): RedirectResponse
    {
        $validated = $request->validated();
        $validated['user_id'] = $request->user()->id;
        $validated['book_id'] = $book->id;

        Review::create($validated);

        return redirect()->route('books.show', $book)
            ->with('success', 'レビューを投稿しました。');
    }

    /**
     * 指定されたレビューを編集するためのフォームを表示します。
     */
    public function edit(Review $review): View
    {
        $this->authorize('update', $review);

        return view('reviews.edit', compact('review'));
    }

    /**
     * 指定されたレビューを更新します。
     */
    public function update(UpdateReviewRequest $request, Review $review): RedirectResponse
    {
        $review->update($request->validated());

        return redirect()->route('books.show', $review->book_id)
            ->with('success', 'レビューを更新しました。');
    }

    /**
     * 指定されたレビューを削除します。
     */
    public function destroy(Review $review): RedirectResponse
    {
        $this->authorize('delete', $review);

        $review->delete();

        return redirect()->route('books.show', $review->book_id)
            ->with('success', 'レビューを削除しました。');
    }
}
