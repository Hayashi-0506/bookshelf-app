<?php

namespace Tests\Feature;

use App\Enums\PlanReminderType;
use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\PlanReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AutoExpireBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // テストの途中で日付が変わっても「今日」がずれないよう、現在時刻を固定する
        $this->freezeTime();

        $this->user = User::factory()->create();
        Notification::fake();
    }

    /**
     * 期日を今日から指定した日数後にした読書計画を作成します。
     */
    private function planDueIn(int $days, ReadingPlanStatus $status = ReadingPlanStatus::InProgress): ReadingPlan
    {
        return ReadingPlan::factory()->for($this->user)->create([
            'target_date' => today()->addDays($days),
            'status' => $status,
            'completed_at' => $status === ReadingPlanStatus::Completed ? today()->subDays(5) : null,
        ]);
    }

    /**
     * 期日を過ぎた進行中の計画は期限切れになる。
     */
    public function test_overdue_in_progress_plans_are_expired(): void
    {
        $yesterday = $this->planDueIn(-1);
        $longAgo = $this->planDueIn(-10);

        $this->artisan('reading-plans:process')
            ->expectsOutput('期限切れに更新: 2件')
            ->assertSuccessful();

        $this->assertSame(ReadingPlanStatus::Expired, $yesterday->fresh()->status);
        $this->assertSame(ReadingPlanStatus::Expired, $longAgo->fresh()->status);
    }

    /**
     * 期日が今日または未来の計画は進行中のままになる。
     */
    public function test_plans_due_today_or_later_remain_in_progress(): void
    {
        $today = $this->planDueIn(0);
        $tomorrow = $this->planDueIn(1);

        $this->artisan('reading-plans:process')->expectsOutput('期限切れに更新: 0件');

        $this->assertSame(ReadingPlanStatus::InProgress, $today->fresh()->status);
        $this->assertSame(ReadingPlanStatus::InProgress, $tomorrow->fresh()->status);
    }

    /**
     * 読了済みの計画は期日を過ぎていても期限切れにならない。
     */
    public function test_completed_plans_are_not_expired(): void
    {
        $completed = $this->planDueIn(-10, ReadingPlanStatus::Completed);

        $this->artisan('reading-plans:process');

        $completed->refresh();
        $this->assertSame(ReadingPlanStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
    }

    /**
     * 期限切れ化では期日や完了日は変更されない。
     */
    public function test_expiring_does_not_change_target_date_or_completed_at(): void
    {
        $plan = $this->planDueIn(-1);

        $this->artisan('reading-plans:process');

        $plan->refresh();
        $this->assertTrue($plan->target_date->isYesterday());
        $this->assertNull($plan->completed_at);
    }

    /**
     * 複数ユーザーの計画をまとめて期限切れにする。
     */
    public function test_expires_plans_of_multiple_users(): void
    {
        $others = ReadingPlan::factory()->count(3)->create(['target_date' => today()->subDay()]);

        $this->artisan('reading-plans:process');

        $others->each(fn (ReadingPlan $plan) => $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status));
    }

    /**
     * 期日3日経過の進行中の計画は同じ実行で期限切れになり再エンゲージメント通知も届く。
     */
    public function test_plan_three_days_overdue_is_expired_and_receives_re_engagement_in_same_run(): void
    {
        $plan = $this->planDueIn(-3);

        $this->artisan('reading-plans:process');

        $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
        Notification::assertSentTo(
            $this->user,
            PlanReminderNotification::class,
            fn (PlanReminderNotification $notification) => $notification->toArray($this->user)['timing'] === PlanReminderType::ReEngagement->value,
        );
    }

    /**
     * 2回実行しても状態は変わらない。
     */
    public function test_running_twice_is_idempotent(): void
    {
        $plan = $this->planDueIn(-1);

        $this->artisan('reading-plans:process');
        $this->artisan('reading-plans:process')->expectsOutput('期限切れに更新: 0件');

        $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
    }
}
