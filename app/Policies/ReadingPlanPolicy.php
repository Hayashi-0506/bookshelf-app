<?php

namespace App\Policies;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;

class ReadingPlanPolicy
{
    /**
     * ユーザーが読書計画を編集できるか判断します。
     */
    public function update(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id && $readingPlan->status !== ReadingPlanStatus::Completed;
    }

    /**
     * ユーザーが読書計画を削除できるか判断します。
     */
    public function delete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id;
    }

    /**
     * ユーザーが読書計画を読了にするか判断します。
     */
    public function complete(User $user, ReadingPlan $readingPlan): bool
    {
        return $user->id === $readingPlan->user_id && $readingPlan->status !== ReadingPlanStatus::Completed;
    }
}
