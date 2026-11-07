<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class NotificationController extends Controller
{
    /**
     * 通知の一覧を表示します。
     */
    public function index(): View
    {
        $notifications = auth()->user()->notifications;

        return view('notifications.index', compact('notifications'));
    }

    /**
     * 通知を既読にします。
     */
    public function read(string $notification): RedirectResponse
    {
        /** @var User $user */
        $user = auth()->user();
        $user->notifications()->findOrFail($notification)->markAsRead();

        return redirect()->route('notifications.index')
            ->with('success', '通知を既読にしました。');
    }
}
