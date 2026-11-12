<?php

namespace Database\Seeders;

use App\Enums\ReadingPlanStatus;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReadingPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * 実行日（Carbon::today()）を起点に期日を設定し、
     * いつ実行してもバッチ処理の各シナリオを確認できるようにします。
     */
    public function run(): void
    {
        $today = Carbon::today();

        $yamada = User::findOrFail(1);
        $suzuki = User::findOrFail(2);

        $bookIds = Book::orderBy('id')->limit(6)->pluck('id');

        collect([
            // 1. 期日3日前：予告リマインダーの対象
            [
                'user_id' => $yamada->id,
                'target_date' => $today->copy()->addDays(3),
                'status' => ReadingPlanStatus::InProgress,
            ],
            // 2. 期日当日：最終リマインダーの対象
            [
                'user_id' => $yamada->id,
                'target_date' => $today->copy(),
                'status' => ReadingPlanStatus::InProgress,
            ],
            // 3. 期日3日経過：バッチで期限切れ化 → 再エンゲージメント通知の対象
            [
                'user_id' => $yamada->id,
                'target_date' => $today->copy()->subDays(3),
                'status' => ReadingPlanStatus::InProgress,
            ],
            // 4. 期日7日後：リマインダーの対象外
            [
                'user_id' => $yamada->id,
                'target_date' => $today->copy()->addDays(7),
                'status' => ReadingPlanStatus::InProgress,
            ],
            // 5. 読了済み
            [
                'user_id' => $yamada->id,
                'target_date' => $today->copy()->subDays(10),
                'status' => ReadingPlanStatus::Completed,
                'completed_at' => $today->copy()->subDays(5),
            ],
            // 6. 他ユーザーの計画：認可（403）の確認用
            [
                'user_id' => $suzuki->id,
                'target_date' => $today->copy()->addDays(5),
                'status' => ReadingPlanStatus::InProgress,
            ],
        ])->each(fn (array $plan, int $index) => ReadingPlan::create([
            ...$plan,
            'book_id' => $bookIds[$index],
        ]));
    }
}
