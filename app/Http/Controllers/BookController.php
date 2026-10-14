<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookRequest;
use App\Models\Book;
use App\Models\Genre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * 書籍の一覧を表示します。
     */
    public function index(): View
    {
        $books = Book::paginate(10);

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
     * 新しく作成した書籍を登録します。
     */
    public function store(StoreBookRequest $request): RedirectResponse
    {
        $book = DB::transaction(function () use ($request) {
            $book = Book::create([
                ...$request->safe()->except('genres'),
                'created_by' => $request->user()->id,
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
        $book->load(['reviews']);

        return view('books.show', compact('book'));
    }

    /**
     * 指定された書籍を編集するためのフォームを表示します。
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * 指定された書籍を更新します。
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * 指定された書籍を削除します。
     */
    public function destroy(string $id)
    {
        //
    }
}
