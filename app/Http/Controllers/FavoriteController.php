<?php

namespace App\Http\Controllers;

use App\Models\Book;

class FavoriteController extends Controller
{
    /**
     * お気に入りの登録または削除を行います。
     */
    public function toggle(Book $book)
    {
        $book->loadExists([
            'favoriteUsers as is_favorited' => fn ($query) => $query->where('user_id', auth()->id()),
        ]);

        $result = $book->favoriteUsers()->toggle(auth()->id());

        $message = empty($result['attached'])
            ? 'お気に入りを解除しました。'
            : 'お気に入りに登録しました。';

        return redirect()->route('books.show', $book->id)
            ->with('success', $message);
    }
}
