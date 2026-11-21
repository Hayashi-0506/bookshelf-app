<?php

namespace App\Notifications;

use App\Enums\PlanReminderType;
use App\Models\ReadingPlan;
use Illuminate\Notifications\Notification;

class PlanReminderNotification extends Notification
{
    public function __construct(
        private ReadingPlan $plan,
        private PlanReminderType $type,
    ) {}

    /**
     * 通知の送信チャネルを取得します。
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * データベースに保存する通知内容を取得します。
     */
    public function toArray(object $notifiable): array
    {
        return [
            'plan_id' => $this->plan->id,
            'book_title' => $this->plan->book->title,
            'timing' => $this->type->value,
            'title' => $this->type->title(),
            'body' => $this->type->body($this->plan->book->title),
        ];
    }
}
