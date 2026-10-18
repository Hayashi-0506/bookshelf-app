<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * 書籍の一覧を表示します。
     */
    public function index(): View
    {
        $books = Book::withAvg('reviews', 'rating')->latest()->paginate(10);

        return view('books.index', compact('books'));
    }

    /**
     * 新しい書籍を作成するためのフォームを表示します。
     */
    public function create(): View
    {
        $genres = Genre::orderBy('id')->get();

        return view('books.create', compact('genres'));
    }

    /**
     * 新規の書籍を登録します。
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $book = DB::transaction(function () use ($request) {
            $book = Book::create([
                ...$request->safe()->except('genres'),
                'user_id' => $request->user()->id,
            ]);
            $book->genres()->sync($request->validated('genres'));

            return $book;
        });

        return redirect()->route('books.show', $book)
            ->with('success', '書籍を登録しました。');
    }

    /**
     * 指定された書籍を表示します。
     */
    public function show(Book $book): View
    {
        $book->loadAvg('reviews', 'rating');

        return view('books.show', compact('book'));
    }

    /**
     * 指定された書籍を編集するためのフォームを表示します。
     */
    public function edit(Book $book): View
    {
        $this->authorize('update', $book);

        $genres = Genre::orderBy('id')->get();

        return view('books.edit', compact('book', 'genres'));
    }

    /**
     * 指定された書籍を更新します。
     */
    public function update(UpdateBookRequest $request, Book $book): RedirectResponse
    {
        $book = DB::transaction(function () use ($request, $book) {
            $book->update([
                $request->safe()->except('genres'),
            ]);
            $book->genres()->sync($request->validated('genres'));

            return $book;
        });

        return redirect()->route('books.show', $book)
            ->with('success', '書籍を更新しました。');
    }

    /**
     * 指定された書籍を削除します。
     */
    public function destroy(Book $book): RedirectResponse
    {
        $this->authorize('delete', $book);

        $book->delete();

        return redirect()->route('books.index')
            ->with('success', '書籍を削除しました。');
    }
}
