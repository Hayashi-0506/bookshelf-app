<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('books.index');
});

Route::middleware('auth')->group(function () {
    Route::get('books/create', [BookController::class, 'create'])->name('books.create');
    Route::post('books/store', [BookController::class, 'store'])->name('books.store');
    Route::post('favorites/{book}/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
});

Route::resource('books', BookController::class)->only('index', 'show');

// 仮ルート
Route::get('/ranking', fn () => 'ランキング（準備中）')->name('ranking.index');
Route::get('/favorites', fn () => 'お気に入り（準備中）')->name('favorites.index');
Route::get('/genres', fn () => 'ジャンル管理（準備中）')->name('genres.index');
Route::get('/reviews', fn () => 'レビュー（準備中）')->name('reviews.store');
