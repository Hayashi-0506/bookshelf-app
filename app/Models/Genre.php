<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Genre extends Model
{
    use HasFactory;

    /**
     * ジャンルに紐づく書籍を取得します。
     */
    public function books(): BelongsToMany
    {
        return $this->belongsToMany(Book::class);
    }
}
