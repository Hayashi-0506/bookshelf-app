<?php

namespace App\Console\Commands;

use App\Enums\PlanReminderType;
use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Notifications\PlanReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class ProcessReadingPlans extends Command
{
    protected $signature = 'reading-plans:process';

    protected $description = '読書計画の期限切れ処理とリマインド通知を行います。';

    public function handle(): int
    {
        $today = today();

        // 1. 期日を過ぎた進行中の計画を一括で期限切れにする
        $expiredCount = ReadingPlan::where('status', ReadingPlanStatus::InProgress)
            ->where('target_date', '<', $today)
            ->update(['status' => ReadingPlanStatus::Expired->value]);

        $this->info("期限切れに更新: {$expiredCount}件");

        // 2. 期日3日前の進行中の計画に予告を通知
        $this->notifyPlans(ReadingPlanStatus::InProgress, $today->copy()->addDays(3), PlanReminderType::Upcoming);

        // 3. 期日当日の進行中の計画に最終リマインドを通知
        $this->notifyPlans(ReadingPlanStatus::InProgress, $today, PlanReminderType::DueToday);

        // 4. 期日3日後の期限切れの計画に再エンゲージメントを通知
        $this->notifyPlans(ReadingPlanStatus::Expired, $today->copy()->subDays(3), PlanReminderType::ReEngagement);

        return self::SUCCESS;
    }

    /**
     * 指定した状態・期日の読書計画のユーザーに通知を送信します。
     */
    private function notifyPlans(ReadingPlanStatus $status, Carbon $targetDate, PlanReminderType $type): void
    {
        $plans = ReadingPlan::with(['user', 'book'])
            ->where('status', $status)
            ->whereDate('target_date', $targetDate)
            ->lazyById();

        $plans->each(fn (ReadingPlan $plan) => $plan->user->notify(new PlanReminderNotification($plan, $type)));

        $this->info("{$type->value}: {$plans->count()}件");
    }
}
