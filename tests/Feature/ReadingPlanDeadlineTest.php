<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanDeadlineTest extends TestCase
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
     * 編集画面に現在の期日と書籍が表示される。
     */
    public function test_edit_screen_displays_current_target_date_and_book(): void
    {
        $plan = ReadingPlan::factory()->for($this->user)->create(['target_date' => '2030-01-15']);

        $this->actingAs($this->user)
            ->get(route('reading-plans.edit', $plan))
            ->assertOk()
            ->assertSee('2030-01-15')
            ->assertSee($plan->book->title);
    }

    /**
     * 進行中の計画の期日を延ばせる。
     */
    public function test_can_extend_target_date_of_in_progress_plan(): void
    {
        $plan = ReadingPlan::factory()->for($this->user)->create(['target_date' => today()->addDay()]);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => today()->addMonth()->format('Y-m-d')])
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を更新しました。');

        $plan->refresh();
        $this->assertTrue($plan->target_date->isSameDay(today()->addMonth()));
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
    }

    /**
     * 期限切れの計画の期日を延ばすと進行中に戻る。
     */
    public function test_extending_expired_plan_returns_it_to_in_progress(): void
    {
        $plan = ReadingPlan::factory()->for($this->user)->create([
            'status' => ReadingPlanStatus::Expired,
            'target_date' => today()->subDays(3),
        ]);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => today()->addWeek()->format('Y-m-d')])
            ->assertSessionHasNoErrors();

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
        $this->assertTrue($plan->target_date->isSameDay(today()->addWeek()));
    }

    /**
     * 期日を今日に変更できる。
     */
    public function test_can_change_target_date_to_today(): void
    {
        $plan = ReadingPlan::factory()->for($this->user)->create(['status' => ReadingPlanStatus::Expired, 'target_date' => today()->subDay()]);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => today()->format('Y-m-d')])
            ->assertSessionHasNoErrors();

        $this->assertTrue($plan->fresh()->target_date->isToday());
    }

    /**
     * 過去の日付には変更できない。
     */
    public function test_cannot_change_target_date_to_past(): void
    {
        $plan = ReadingPlan::factory()->for($this->user)->create([
            'status' => ReadingPlanStatus::Expired,
            'target_date' => today()->subDays(3),
        ]);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => today()->subDay()->format('Y-m-d')])
            ->assertSessionHasErrors(['target_date' => '期日は今日以降の日付を入力してください。']);

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::Expired, $plan->status);
        $this->assertTrue($plan->target_date->isSameDay(today()->subDays(3)));
    }

    /**
     * 期日が未入力または形式が正しくない場合は変更できない。
     */
    public function test_target_date_is_required_and_must_be_valid_format(): void
    {
        $plan = ReadingPlan::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => ''])
            ->assertSessionHasErrors(['target_date' => '期日を入力してください。']);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => '2030/01/01'])
            ->assertSessionHasErrors(['target_date' => '期日は「YYYY-MM-DD」の形式で入力してください。']);
    }

    /**
     * 期日の変更で書籍や作成者は変わらない。
     */
    public function test_update_does_not_change_book_or_user(): void
    {
        $plan = ReadingPlan::factory()->for($this->user)->create();
        $original = $plan->only(['user_id', 'book_id']);

        $this->actingAs($this->user)->put(route('reading-plans.update', $plan), [
            'target_date' => today()->addMonth()->format('Y-m-d'),
            'book_id' => 9999,
            'user_id' => 9999,
        ]);

        $this->assertSame($original, $plan->fresh()->only(['user_id', 'book_id']));
    }

    /**
     * 他のユーザーの計画の期日は変更できない。
     */
    public function test_other_user_cannot_change_target_date(): void
    {
        $plan = ReadingPlan::factory()->create(['status' => ReadingPlanStatus::Expired, 'target_date' => today()->subDays(3)]);

        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => today()->addWeek()->format('Y-m-d')])
            ->assertForbidden();

        $this->assertSame(ReadingPlanStatus::Expired, $plan->fresh()->status);
    }
}
