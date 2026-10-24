<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\IndexBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Http\Resources\BookCollection;
use App\Http\Resources\BookResource;
use App\Models\Book;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class BookController extends Controller
{
    /**
     * 書籍の一覧を表示します。
     */
    public function index(IndexBookRequest $request): BookCollection
    {
        $keyword = $request->input('keyword');
        $genreId = $request->input('genre_id');
        $perPage = $request->integer('per_page', 20);

        $books = Book::with(['genres'])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->when($keyword, function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('title', 'like', "%{$keyword}%")
                        ->orWhere('author', 'like', "%{$keyword}%");
                });
            })
            ->when($genreId, function ($query, $genreId) {
                $query->whereHas('genres', fn ($q) => $q->where('genres.id', $genreId));
            })
            ->orderBy('id')
            ->paginate($perPage);

        return new BookCollection($books);
    }

    /**
     * 書籍を新規登録します。
     */
    public function store(StoreBookRequest $request): BookResource
    {
        $book = DB::transaction(function () use ($request) {
            $book = $request->user()->books()->create($request->safe()->except('genres'));
            $book->genres()->sync($request->validated('genres'));

            return $book;
        });

        return new BookResource($book->load(['genres', 'user']));
    }

    /**
     * 指定された書籍を表示します。
     */
    public function show(Book $book): BookResource
    {
        $book->load(['genres', 'reviews']);

        return new BookResource($book);
    }

    /**
     * 指定された書籍を更新します。
     */
    public function update(UpdateBookRequest $request, Book $book): BookResource
    {
        $this->authorize('update', $book);

        $book = DB::transaction(function () use ($request, $book) {
            $book->update([
                $request->safe()->except('genres'),
            ]);
            $book->genres()->sync($request->validated('genres'));

            return $book;
        });

        return new BookResource($book->load(['genres', 'user']));
    }

    /**
     * 指定された書籍を削除します。
     */
    public function destroy(Book $book): Response
    {
        $this->authorize('delete', $book);

        $book->delete();

        return response()->noContent();
    }
}
