<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class FavoriteController extends Controller
{
    /**
     * ログインユーザーのお気に入り書籍の一覧を表示します。
     */
    public function index(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $books = $user->favoriteBooks()
            ->orderBy('books.id')
            ->paginate(10);

        return view('favorites.index', compact('books'));
    }

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
