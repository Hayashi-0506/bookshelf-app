<?php

use App\Http\Controllers\Api\BookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {
    Route::get('books', [BookController::class, 'index'])->name('api.books.index');
    Route::get('books/{book}', [BookController::class, 'show'])->name('api.books.show');

    // 認証必須
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('books', [BookController::class, 'store'])->name('api.books.store');
        Route::put('books/{book}', [BookController::class, 'update'])->name('api.books.update');
        Route::delete('books/{book}', [BookController::class, 'destroy'])->name('api.books.destroy');
    });
});
