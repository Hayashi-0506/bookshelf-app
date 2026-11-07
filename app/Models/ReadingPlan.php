<?php

namespace App\Models;

use App\Enums\ReadingPlanStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReadingPlan extends Model
{
    use HasFactory;

    /**
     * 複数代入（一括割り当て）を許可する属性。
     */
    protected $fillable = [
        'user_id',
        'book_id',
        'target_date',
        'status',
        'completed_at',
    ];

    /**
     * 型キャスト（データのデータ型変換）を適用する属性。
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ReadingPlanStatus::class,
        'target_date' => 'date',
        'completed_at' => 'datetime',
    ];

    /**
     * 読書計画の作成者を取得します。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 読書計画の書籍を取得します。
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
