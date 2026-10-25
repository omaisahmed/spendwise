<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;

class NotificationController extends Controller
{
    public function index()
    {
        return view('notifications.index', [
            'notifications' => AppNotification::query()->latest()->paginate(30),
            'unreadCount' => AppNotification::query()->whereNull('read_at')->count(),
        ]);
    }

    public function read(AppNotification $notification)
    {
        $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();

        return back();
    }

    public function readAll()
    {
        AppNotification::query()->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }

    public function destroy(AppNotification $notification)
    {
        $notification->delete();

        return back()->with('success', 'Notification removed.');
    }
}
