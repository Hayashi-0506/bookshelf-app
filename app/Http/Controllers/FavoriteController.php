<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\RedirectResponse;

class FavoriteController extends Controller
{
    /**
     * お気に入りの登録または削除を行います。
     */
    public function toggle(Book $book): RedirectResponse
    {
        $result = $book->favoriteUsers()->toggle(auth()->id());

        $message = empty($result['attached'])
            ? 'お気に入りを解除しました。'
            : 'お気に入りに登録しました。';

        return redirect()->route('books.show', $book->id)
            ->with('success', $message);
    }
}
