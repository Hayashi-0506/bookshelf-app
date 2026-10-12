<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('books.index');
});

Route::resource('books', BookController::class);

Route::middleware('auth')->group(function () {
    Route::post('favorites/{book}/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
});

// 仮ルート
Route::get('/ranking', fn () => 'ランキング（準備中）')->name('ranking.index');
Route::get('/favorites', fn () => 'お気に入り（準備中）')->name('favorites.index');
Route::get('/genres', fn () => 'ジャンル管理（準備中）')->name('genres.index');
Route::get('/reviews', fn () => 'レビュー（準備中）')->name('reviews.store');
