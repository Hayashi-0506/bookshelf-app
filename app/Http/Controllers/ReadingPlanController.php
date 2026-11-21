<?php

namespace App\Http\Controllers;

use App\Enums\ReadingPlanStatus;
use App\Http\Requests\StoreReadingPlanRequest;
use App\Http\Requests\UpdateReadingPlanRequest;
use App\Models\Book;
use App\Models\ReadingPlan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReadingPlanController extends Controller
{
    /**
     * 読書計画を表示します。
     */
    public function index(Request $request): View
    {
        $currentStatus = ReadingPlanStatus::tryFrom($request->integer('status'))?->value;

        /** @var User $user */
        $user = auth()->user();
        $readingPlans = $user->readingPlans()
            ->with('book')
            ->when($currentStatus, fn ($query, $status) => $query->whereStatus(ReadingPlanStatus::from($status)))
            ->orderBy('status')
            ->orderByDesc('completed_at')
            ->orderBy('target_date')
            ->orderBy('id')
            ->get();

        return view('reading-plans.index', compact('readingPlans', 'currentStatus'));
    }

    /**
     * 読書計画を作成するためのフォームを表示します。
     */
    public function create(): View
    {
        $books = Book::orderBy('id')->get();

        return view('reading-plans.create', compact('books'));
    }

    /**
     * 読書計画を新規登録します。
     */
    public function store(StoreReadingPlanRequest $request): RedirectResponse
    {
        $request->user()->readingPlans()->create([
            ...$request->validated(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を登録しました。');
    }

    /**
     * 指定された読書計画を編集するためのフォームを表示します。
     */
    public function edit(ReadingPlan $readingPlan): View
    {
        $this->authorize('update', $readingPlan);

        $readingPlan->load('book');

        return view('reading-plans.edit', compact('readingPlan'));
    }

    /**
     * 指定された読書計画を更新します。
     */
    public function update(UpdateReadingPlanRequest $request, ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('update', $readingPlan);

        $readingPlan->update([
            ...$request->validated(),
            'status' => ReadingPlanStatus::InProgress,
        ]);

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を更新しました。');
    }

    /**
     * 指定された読書計画を削除します。
     */
    public function destroy(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('delete', $readingPlan);

        $readingPlan->delete();

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を削除しました。');
    }

    /**
     * 指定された読書計画を読了に変更します。
     */
    public function complete(ReadingPlan $readingPlan): RedirectResponse
    {
        $this->authorize('complete', $readingPlan);

        $readingPlan->update([
            'status' => ReadingPlanStatus::Completed,
            'completed_at' => now(),
        ]);

        return redirect()->route('reading-plans.index')
            ->with('success', '読書計画を完了しました。');
    }
}
