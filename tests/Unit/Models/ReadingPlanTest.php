<?php

namespace Tests\Unit\Models;

use App\Enums\PlanReminderType;
use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReadingPlanTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 一括代入できる属性が定義されている。
     */
    public function test_fillable_attributes_are_defined(): void
    {
        $this->assertSame(
            ['user_id', 'book_id', 'target_date', 'status', 'completed_at'],
            (new ReadingPlan)->getFillable(),
        );
    }

    /**
     * 状態はEnumに、期日と完了日はCarbonにキャストされる。
     */
    public function test_status_is_cast_to_enum_and_dates_to_carbon(): void
    {
        $plan = ReadingPlan::factory()->create([
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => today(),
        ])->fresh();

        $this->assertSame(ReadingPlanStatus::Completed, $plan->status);
        $this->assertInstanceOf(Carbon::class, $plan->target_date);
        $this->assertInstanceOf(Carbon::class, $plan->completed_at);
    }

    /**
     * 状態は整数でデータベースに保存される。
     */
    public function test_status_is_stored_as_integer(): void
    {
        $plan = ReadingPlan::factory()->create(['status' => ReadingPlanStatus::Expired]);

        $this->assertDatabaseHas('reading_plans', ['id' => $plan->id, 'status' => 2]);
    }

    /**
     * 作成者と書籍に属する。
     */
    public function test_belongs_to_user_and_book(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->create();
        $plan = ReadingPlan::factory()->for($user)->for($book)->create();

        $this->assertInstanceOf(BelongsTo::class, $plan->user());
        $this->assertInstanceOf(BelongsTo::class, $plan->book());
        $this->assertTrue($plan->user->is($user));
        $this->assertTrue($plan->book->is($book));
    }

    /**
     * 状態のEnumは画面表示用のラベルとバッジのクラスを返す。
     */
    public function test_status_enum_returns_label_and_badge_class(): void
    {
        $this->assertSame('進行中', ReadingPlanStatus::InProgress->label());
        $this->assertSame('期限切れ', ReadingPlanStatus::Expired->label());
        $this->assertSame('読了', ReadingPlanStatus::Completed->label());

        $this->assertStringContainsString('yellow', ReadingPlanStatus::InProgress->badgeClass());
        $this->assertStringContainsString('red', ReadingPlanStatus::Expired->badgeClass());
        $this->assertStringContainsString('green', ReadingPlanStatus::Completed->badgeClass());
    }

    /**
     * 状態のEnumは進行中・期限切れ・読了の順に番号が振られている。
     */
    public function test_status_enum_values_are_ordered_in_progress_expired_completed(): void
    {
        $this->assertSame([1, 2, 3], array_map(fn ($case) => $case->value, ReadingPlanStatus::cases()));
    }

    /**
     * リマインド種別のEnumは書籍名を含む通知の本文を返す。
     */
    public function test_reminder_type_enum_returns_title_and_body_with_book_title(): void
    {
        $this->assertSame('読書期日が近づいています', PlanReminderType::Upcoming->title());
        $this->assertSame('本日が読書期日です', PlanReminderType::DueToday->title());
        $this->assertSame('読書計画を再開しませんか？', PlanReminderType::ReEngagement->title());

        collect(PlanReminderType::cases())->each(
            fn (PlanReminderType $type) => $this->assertStringContainsString('「リーダブルコード」', $type->body('リーダブルコード'))
        );
    }
}
