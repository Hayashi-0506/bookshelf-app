<?php

namespace App\Http\Controllers;

use App\Models\Book;
use Illuminate\Http\Request;

class BookController extends Controller
{
    /**
     * 書籍の一覧を表示します。
     */
    public function index()
    {
        $books = Book::paginate(10);

        return view('books.index', compact('books'));
    }

    /**
     * 新しい書籍を作成するためのフォームを表示します。
     */
    public function create()
    {
        //
    }

    /**
     * 新しく作成した書籍を登録します。
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * 指定された書籍を表示します。
     */
    public function show(Book $book)
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
