<?php

use App\Http\Controllers\BookController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GenreController;
use App\Http\Controllers\MyReportController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\RankingController;
use App\Http\Controllers\ReadingPlanController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ReviewLikeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('books.index');
});

Route::middleware('auth')->group(function () {
    Route::resource('books', BookController::class)->except('index', 'show');
    Route::get('favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('favorites/{book}/toggle', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::post('books/{book}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    Route::post('reviews/{review}/like', [ReviewLikeController::class, 'toggle'])->name('reviews.like');
    Route::resource('reviews', ReviewController::class)->only('edit', 'update', 'destroy');
    Route::resource('genres', GenreController::class);
    Route::post('reading-plans/{readingPlan}/complete', [ReadingPlanController::class, 'complete'])->name('reading-plans.complete');
    Route::resource('reading-plans', ReadingPlanController::class)->except('show');
    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('reports', [MyReportController::class, 'index'])->name('reports.index');
});

Route::resource('books', BookController::class)->only('index', 'show');
Route::get('ranking', [RankingController::class, 'index'])->name('ranking.index');
