<?php

use App\Http\Controllers\BookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('books.index');
});

Route::resource('books', BookController::class);

// 仮ルート
Route::get('/ranking', fn () => 'ランキング（準備中）')->name('ranking.index');
Route::get('/favorites', fn () => 'お気に入り（準備中）')->name('favorites.index');
Route::get('/genres', fn () => 'ジャンル管理（準備中）')->name('genres.index');
