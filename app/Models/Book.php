<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    use HasFactory;

    /**
     * 複数代入（一括割り当て）を許可する属性。
     */
    protected $fillable = [
        'title',
        'author',
        'isbn',
        'published_date',
        'description',
        'image_url',
        'user_id',
    ];

    /**
     * 型キャスト（データのデータ型変換）を適用する属性。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'published_date' => 'date',
    ];

    /**
     * 書籍に紐づくジャンルを取得します。
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(Genre::class)->withTimestamps();
    }

    /**
     * 書籍の作成者を取得します。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 書籍のレビューを取得します。
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * お気に入りに登録しているユーザーを取得します。
     */
    public function favoriteUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favorites')->withTimestamps();
    }

    /**
     * キーワードとジャンルで書籍を絞り込みます。
     */
    public function scopeSearch(Builder $query, ?string $keyword, ?int $genreId): Builder
    {
        return $query
            ->when($keyword, fn (Builder $q) => $q->where(fn (Builder $q) => $q
                ->where('title', 'like', "%{$keyword}%")
                ->orWhere('author', 'like', "%{$keyword}%")))
            ->when($genreId, fn (Builder $q) => $q->whereHas('genres', fn (Builder $q) => $q->where('genres.id', $genreId)));
    }
}
