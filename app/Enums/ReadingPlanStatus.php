<?php

namespace App\Enums;

enum ReadingPlanStatus: int
{
    case InProgress = 1;
    case Expired = 2;
    case Completed = 3;

    /**
     * 画面に表示する状態名を取得します。
     */
    public function label(): string
    {
        return match ($this) {
            self::InProgress => '進行中',
            self::Expired => '期限切れ',
            self::Completed => '読了',
        };
    }

    /**
     * 状態バッジに適用する CSS クラスを取得します。
     */
    public function badgeClass(): string
    {
        return match ($this) {
            self::InProgress => 'bg-yellow-100 text-yellow-800',
            self::Expired => 'bg-red-100 text-red-800',
            self::Completed => 'bg-green-100 text-green-800',
        };
    }
}
