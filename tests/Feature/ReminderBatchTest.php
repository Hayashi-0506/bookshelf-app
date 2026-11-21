<?php

namespace Tests\Feature;

use App\Enums\PlanReminderType;
use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use App\Notifications\PlanReminderNotification;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReminderBatchTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // テストの途中で日付が変わっても「今日」がずれないよう、現在時刻を固定する
        $this->freezeTime();

        $this->user = User::factory()->create();
    }

    /**
     * 期日を今日から指定した日数後にした読書計画を作成します。
     */
    private function planDueIn(int $days, ReadingPlanStatus $status = ReadingPlanStatus::InProgress): ReadingPlan
    {
        return ReadingPlan::factory()->for($this->user)->create([
            'target_date' => today()->addDays($days),
            'status' => $status,
        ]);
    }

    /**
     * 指定した計画・種別の通知が送られたことを確認します。
     */
    private function assertReminded(ReadingPlan $plan, PlanReminderType $type): void
    {
        Notification::assertSentTo(
            $this->user,
            PlanReminderNotification::class,
            fn (PlanReminderNotification $notification) => $notification->toArray($this->user)['plan_id'] === $plan->id
                && $notification->toArray($this->user)['timing'] === $type->value,
        );
    }

    /**
     * 期日3日前の進行中の計画に予告の通知を送る。
     */
    public function test_sends_upcoming_reminder_three_days_before(): void
    {
        Notification::fake();
        $plan = $this->planDueIn(3);

        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->assertReminded($plan, PlanReminderType::Upcoming);
        Notification::assertSentToTimes($this->user, PlanReminderNotification::class, 1);
    }

    /**
     * 期日当日の進行中の計画に最終リマインドの通知を送る。
     */
    public function test_sends_due_today_reminder_on_target_date(): void
    {
        Notification::fake();
        $plan = $this->planDueIn(0);

        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->assertReminded($plan, PlanReminderType::DueToday);
        Notification::assertSentToTimes($this->user, PlanReminderNotification::class, 1);
    }

    /**
     * 期日3日後の期限切れの計画に再エンゲージメントの通知を送る。
     */
    public function test_sends_re_engagement_three_days_after_expiry(): void
    {
        Notification::fake();
        $plan = $this->planDueIn(-3, ReadingPlanStatus::Expired);

        $this->artisan('reading-plans:process')->assertSuccessful();

        $this->assertReminded($plan, PlanReminderType::ReEngagement);
        Notification::assertSentToTimes($this->user, PlanReminderNotification::class, 1);
    }

    /**
     * リマインド対象外の計画には通知を送らない。
     */
    public function test_does_not_notify_non_target_plans(): void
    {
        Notification::fake();
        $this->planDueIn(7);
        $this->planDueIn(2);
        $this->planDueIn(1);
        $this->planDueIn(-1, ReadingPlanStatus::Expired);
        $this->planDueIn(-4, ReadingPlanStatus::Expired);
        $this->planDueIn(3, ReadingPlanStatus::Completed);
        $this->planDueIn(0, ReadingPlanStatus::Completed);
        $this->planDueIn(-3, ReadingPlanStatus::Completed);

        $this->artisan('reading-plans:process')->assertSuccessful();

        Notification::assertNothingSent();
    }

    /**
     * 計画の作成者にだけ通知を送る。
     */
    public function test_notifies_only_plan_owner(): void
    {
        Notification::fake();
        $other = User::factory()->create();
        $this->planDueIn(3);

        $this->artisan('reading-plans:process');

        Notification::assertNotSentTo($other, PlanReminderNotification::class);
    }

    /**
     * 通知はデータベースに計画ID・書籍名・タイミング・タイトル・本文を保存する。
     */
    public function test_notification_stores_title_and_body_with_book_title(): void
    {
        $plan = ReadingPlan::factory()
            ->for($this->user)
            ->for(Book::factory()->create(['title' => 'リーダブルコード']))
            ->create(['target_date' => today()->addDays(3)]);

        $this->artisan('reading-plans:process');

        $notification = $this->user->notifications()->sole();
        $this->assertSame(PlanReminderNotification::class, $notification->type);
        $this->assertNull($notification->read_at);
        $this->assertSame([
            'plan_id' => $plan->id,
            'book_title' => 'リーダブルコード',
            'timing' => 'three_days_before',
            'title' => '読書期日が近づいています',
            'body' => '「リーダブルコード」の読書期日まであと3日です。',
        ], $notification->data);
    }

    /**
     * 通知はデータベースチャネルで送られる。
     */
    public function test_notification_is_sent_via_database_channel(): void
    {
        $notification = new PlanReminderNotification(ReadingPlan::factory()->create(), PlanReminderType::DueToday);

        $this->assertSame(['database'], $notification->via($this->user));
    }

    /**
     * 届いた通知が通知一覧に表示され既読にできる。
     */
    public function test_notification_is_listed_and_can_be_marked_as_read(): void
    {
        ReadingPlan::factory()
            ->for($this->user)
            ->for(Book::factory()->create(['title' => 'リーダブルコード']))
            ->create(['target_date' => today()]);
        $this->artisan('reading-plans:process');
        $notification = $this->user->notifications()->sole();

        $this->actingAs($this->user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('本日が読書期日です')
            ->assertSee('「リーダブルコード」の読書期日になりました。');

        $this->actingAs($this->user)
            ->post(route('notifications.read', $notification->id))
            ->assertRedirect(route('notifications.index'))
            ->assertSessionHas('success', '通知を既読にしました。');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    /**
     * 他のユーザーの通知は既読にできない。
     */
    public function test_cannot_mark_other_users_notification_as_read(): void
    {
        $other = User::factory()->create();
        ReadingPlan::factory()->for($other)->create(['target_date' => today()]);
        $this->artisan('reading-plans:process');

        $this->actingAs($this->user)
            ->post(route('notifications.read', $other->notifications()->sole()->id))
            ->assertNotFound();

        $this->assertNull($other->notifications()->sole()->read_at);
    }

    /**
     * 毎日20時にスケジュールが登録されている。
     */
    public function test_command_is_scheduled_daily_at_20(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn (Event $event) => str_contains($event->command, 'reading-plans:process'));

        $this->assertNotNull($event);
        $this->assertSame('0 20 * * *', $event->expression);
    }
}
