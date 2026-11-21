<?php

namespace Tests\Feature;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Book $book;

    protected function setUp(): void
    {
        parent::setUp();

        // テストの途中で日付が変わっても「今日」がずれないよう、現在時刻を固定する
        $this->freezeTime();

        $this->user = User::factory()->create();
        $this->book = Book::factory()->create(['title' => '計画対象の書籍']);
    }

    /**
     * 読書計画一覧には自分の計画だけが表示される。
     */
    public function test_index_displays_only_own_plans(): void
    {
        ReadingPlan::factory()->for($this->user)->for($this->book)->create();
        ReadingPlan::factory()->create(['book_id' => Book::factory()->create(['title' => '他人の計画の書籍'])]);

        $this->actingAs($this->user)
            ->get(route('reading-plans.index'))
            ->assertOk()
            ->assertSee('計画対象の書籍')
            ->assertDontSee('他人の計画の書籍');
    }

    /**
     * 読書計画一覧は進行中・期限切れ・読了の順に並ぶ。
     */
    public function test_index_is_ordered_in_progress_expired_completed(): void
    {
        $completed = ReadingPlan::factory()->for($this->user)->create(['status' => ReadingPlanStatus::Completed, 'completed_at' => today()]);
        $expired = ReadingPlan::factory()->for($this->user)->create(['status' => ReadingPlanStatus::Expired, 'target_date' => today()->subDays(2)]);
        $later = ReadingPlan::factory()->for($this->user)->create(['target_date' => today()->addDays(10)]);
        $sooner = ReadingPlan::factory()->for($this->user)->create(['target_date' => today()->addDay()]);

        $plans = $this->actingAs($this->user)->get(route('reading-plans.index'))->viewData('readingPlans');

        $this->assertSame([$sooner->id, $later->id, $expired->id, $completed->id], $plans->pluck('id')->all());
    }

    /**
     * 状態で絞り込める。
     */
    public function test_can_filter_by_status(): void
    {
        $inProgress = ReadingPlan::factory()->for($this->user)->create();
        $expired = ReadingPlan::factory()->for($this->user)->create(['status' => ReadingPlanStatus::Expired, 'target_date' => today()->subDay()]);
        $completed = ReadingPlan::factory()->for($this->user)->create(['status' => ReadingPlanStatus::Completed, 'completed_at' => today()]);

        collect([
            ReadingPlanStatus::InProgress->value => $inProgress,
            ReadingPlanStatus::Expired->value => $expired,
            ReadingPlanStatus::Completed->value => $completed,
        ])->each(function (ReadingPlan $plan, int $status) {
            $response = $this->actingAs($this->user)->get(route('reading-plans.index', ['status' => $status]));

            $this->assertSame([$plan->id], $response->viewData('readingPlans')->pluck('id')->all());
            $this->assertSame($status, $response->viewData('currentStatus'));
        });
    }

    /**
     * 不正な状態を指定した場合はすべての計画が表示される。
     */
    public function test_invalid_status_displays_all_plans(): void
    {
        ReadingPlan::factory()->count(2)->for($this->user)->create();

        $response = $this->actingAs($this->user)->get(route('reading-plans.index', ['status' => 99]))->assertOk();

        $this->assertCount(2, $response->viewData('readingPlans'));
        $this->assertNull($response->viewData('currentStatus'));
    }

    /**
     * 読書計画を登録すると進行中の計画として保存される。
     */
    public function test_store_saves_plan_as_in_progress(): void
    {
        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), [
                'book_id' => $this->book->id,
                'target_date' => today()->addWeek()->format('Y-m-d'),
            ])
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を登録しました。');

        $plan = ReadingPlan::firstOrFail();
        $this->assertSame($this->user->id, $plan->user_id);
        $this->assertSame($this->book->id, $plan->book_id);
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
        $this->assertTrue($plan->target_date->isSameDay(today()->addWeek()));
        $this->assertNull($plan->completed_at);
    }

    /**
     * 期日は今日なら登録でき過去の日付は登録できない。
     */
    public function test_target_date_must_be_today_or_later(): void
    {
        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => $this->book->id, 'target_date' => today()->subDay()->format('Y-m-d')])
            ->assertSessionHasErrors(['target_date' => '期日は今日以降の日付を入力してください。']);

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => $this->book->id, 'target_date' => today()->format('Y-m-d')])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('reading_plans', 1);
    }

    /**
     * 書籍と期日が未入力または不正な場合は登録できない。
     */
    public function test_store_fails_with_missing_or_invalid_input(): void
    {
        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), [])
            ->assertSessionHasErrors([
                'book_id' => '書籍を選択してください。',
                'target_date' => '期日を入力してください。',
            ]);

        $this->actingAs($this->user)
            ->post(route('reading-plans.store'), ['book_id' => 9999, 'target_date' => '2030/01/01'])
            ->assertSessionHasErrors([
                'book_id',
                'target_date' => '期日は「YYYY-MM-DD」の形式で入力してください。',
            ]);

        $this->assertDatabaseCount('reading_plans', 0);
    }

    /**
     * 進行中または期限切れの計画がある書籍は新しく登録できない。
     */
    public function test_cannot_create_plan_for_book_with_unfinished_plan(): void
    {
        $expiredBook = Book::factory()->create();
        ReadingPlan::factory()->for($this->user)->for($this->book)->create();
        ReadingPlan::factory()->for($this->user)->for($expiredBook)->create(['status' => ReadingPlanStatus::Expired, 'target_date' => today()->subDay()]);
        $message = 'この書籍にはすでに未読了の読書計画があります。期日を延ばす場合は、既存の計画を編集してください。';

        collect([$this->book, $expiredBook])->each(
            fn (Book $book) => $this->actingAs($this->user)
                ->post(route('reading-plans.store'), ['book_id' => $book->id, 'target_date' => today()->addWeek()->format('Y-m-d')])
                ->assertSessionHasErrors(['book_id' => $message])
        );

        $this->assertDatabaseCount('reading_plans', 2);
    }

    /**
     * 読了済みの書籍や他のユーザーが計画中の書籍は登録できる。
     */
    public function test_can_create_plan_for_completed_book_or_book_planned_by_others(): void
    {
        $otherBook = Book::factory()->create();
        ReadingPlan::factory()->for($this->user)->for($this->book)->create(['status' => ReadingPlanStatus::Completed, 'completed_at' => today()]);
        ReadingPlan::factory()->for(User::factory()->create())->for($otherBook)->create();

        collect([$this->book, $otherBook])->each(
            fn (Book $book) => $this->actingAs($this->user)
                ->post(route('reading-plans.store'), ['book_id' => $book->id, 'target_date' => today()->addWeek()->format('Y-m-d')])
                ->assertSessionHasNoErrors()
        );

        $this->assertSame(2, $this->user->readingPlans()->where('status', ReadingPlanStatus::InProgress)->count());
    }

    /**
     * 読了にすると状態が読了になり完了日が記録される。
     */
    public function test_complete_sets_status_and_completed_at(): void
    {
        $plan = ReadingPlan::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->post(route('reading-plans.complete', $plan))
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を完了しました。');

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::Completed, $plan->status);
        $this->assertTrue($plan->completed_at->isToday());
    }

    /**
     * 期限切れの計画も読了にできる。
     */
    public function test_expired_plan_can_be_completed(): void
    {
        $plan = ReadingPlan::factory()->for($this->user)->create(['status' => ReadingPlanStatus::Expired, 'target_date' => today()->subDays(3)]);

        $this->actingAs($this->user)->post(route('reading-plans.complete', $plan));

        $this->assertSame(ReadingPlanStatus::Completed, $plan->fresh()->status);
    }

    /**
     * 読了済みの計画は編集・更新・再読了できないが、削除はできる。
     */
    public function test_completed_plan_cannot_be_edited_updated_or_completed_again(): void
    {
        $completedAt = now()->subDays(2)->startOfSecond();
        $plan = ReadingPlan::factory()->for($this->user)->for($this->book)->create([
            'status' => ReadingPlanStatus::Completed,
            'target_date' => today()->subDays(3),
            'completed_at' => $completedAt,
        ]);
        // 同じ書籍で進行中の計画がある場合も、読了済みの計画を戻して重複させられない
        ReadingPlan::factory()->for($this->user)->for($this->book)->create();

        $this->actingAs($this->user)->get(route('reading-plans.edit', $plan))->assertForbidden();
        $this->actingAs($this->user)
            ->put(route('reading-plans.update', $plan), ['target_date' => today()->addWeek()->format('Y-m-d')])
            ->assertForbidden();
        $this->actingAs($this->user)->post(route('reading-plans.complete', $plan))->assertForbidden();

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::Completed, $plan->status);
        $this->assertTrue($plan->target_date->isSameDay(today()->subDays(3)));
        $this->assertTrue($plan->completed_at->equalTo($completedAt));
        $this->assertSame(1, $this->user->readingPlans()->where('status', '!=', ReadingPlanStatus::Completed)->count());

        $this->actingAs($this->user)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertRedirect(route('reading-plans.index'));
        $this->assertModelMissing($plan);
    }

    /**
     * 自分の読書計画を削除できる。
     */
    public function test_owner_can_delete_plan(): void
    {
        $plan = ReadingPlan::factory()->for($this->user)->create();

        $this->actingAs($this->user)
            ->delete(route('reading-plans.destroy', $plan))
            ->assertRedirect(route('reading-plans.index'))
            ->assertSessionHas('success', '読書計画を削除しました。');

        $this->assertModelMissing($plan);
    }

    /**
     * 他のユーザーの読書計画は編集・更新・読了・削除できない。
     */
    public function test_other_user_cannot_edit_update_complete_or_delete_plan(): void
    {
        $plan = ReadingPlan::factory()->create(['target_date' => today()->addDays(5)]);

        $this->actingAs($this->user)->get(route('reading-plans.edit', $plan))->assertForbidden();
        $this->actingAs($this->user)->put(route('reading-plans.update', $plan), ['target_date' => today()->addMonth()->format('Y-m-d')])->assertForbidden();
        $this->actingAs($this->user)->post(route('reading-plans.complete', $plan))->assertForbidden();
        $this->actingAs($this->user)->delete(route('reading-plans.destroy', $plan))->assertForbidden();

        $plan->refresh();
        $this->assertSame(ReadingPlanStatus::InProgress, $plan->status);
        $this->assertTrue($plan->target_date->isSameDay(today()->addDays(5)));
    }

    /**
     * 未ログインでは読書計画を登録できない。
     */
    public function test_guest_cannot_create_plan(): void
    {
        $this->post(route('reading-plans.store'), ['book_id' => $this->book->id, 'target_date' => today()->format('Y-m-d')])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('reading_plans', 0);
    }
}
