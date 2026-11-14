<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Requests\UpdateBookRequest;
use App\Models\Book;
use App\Models\Genre;
use App\Services\GoogleBooksService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class BookController extends Controller
{
    /**
     * 書籍の一覧を表示します。
     */
    public function index(IndexBookRequest $request): View
    {
        $keyword = $request?->input('keyword');
        $genreId = $request?->integer('genre');
        $sort = $request?->input('sort');

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
            ->tap(fn ($query) => match ($sort) {
                'oldest' => $query->orderBy('created_at'),
                'rating' => $query->orderBy('reviews_avg_rating', 'desc'),
                'title' => $query->orderBy('title'),
                default => $query->orderBy('created_at', 'desc'),
            })
            ->orderBy('id')
            ->paginate(10)
            ->withQueryString();

        $genres = Genre::all();

        return view('books.index', compact('books', 'genres'));
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
     * 書籍を新規登録します。
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
        $book->load([
            'genres',
            'reviews' => fn ($query) => $query->with('user')->withCount('likedByUsers')->latest(),
        ]);

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
        $this->authorize('update', $book);

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

    /**
     * ISBN で Google Books API から書籍情報を検索します。
     */
    public function searchByIsbn(string $isbn, GoogleBooksService $googleBooks): JsonResponse
    {
        try {
            $book = $googleBooks->findByIsbn($isbn);
        } catch (RequestException|ConnectionException) {
            return response()->json(['error' => '書籍情報の取得に失敗しました。時間をおいて再度お試しください。'], 503);
        }

        if ($book === null) {
            return response()->json(['error' => '該当する書籍が見つかりませんでした。'], 404);
        }

        return response()->json($book);
    }
}
