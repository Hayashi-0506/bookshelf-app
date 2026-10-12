<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Review extends Model
{
    use HasFactory;

    /**
     * レビューの投稿者を取得します。
     */
    public function user(): HasOne
    {
        return $this->belongsTo(User::class);
    }

    /**
     * レビュー対象の書籍を取得します。
     */
    public function book(): HasOne
    {
        return $this->hasOne(Book::class);
    }
}
