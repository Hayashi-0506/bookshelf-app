<?php

namespace App\Enums;

enum PlanReminderType: string
{
    case Upcoming = 'upcoming';         // 期日3日前
    case DueToday = 'due_today';        // 期日当日
    case ReEngagement = 're_engagement'; // 期日3日後

    /**
     * 通知のタイトルを取得します。
     */
    public function title(): string
    {
        return match ($this) {
            self::Upcoming => '読書期日が近づいています',
            self::DueToday => '今日が読書期日です',
            self::ReEngagement => '読書計画を再開しませんか？',
        };
    }

    /**
     * 通知の本文を取得します。
     */
    public function body(string $bookTitle): string
    {
        return match ($this) {
            self::Upcoming => "「{$bookTitle}」の読書期日まであと3日です。",
            self::DueToday => "「{$bookTitle}」の読書期日になりました。本日中に読み終えましょう。",
            self::ReEngagement => "「{$bookTitle}」の読書計画が期限切れになっています。期日を延ばして再開しませんか？",
        };
    }
}
