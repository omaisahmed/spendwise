<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;

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

    public function open(Request $request, AppNotification $notification)
    {
        $notification->forceFill(['read_at' => $notification->read_at ?? now()])->save();

        $url = $notification->data['url'] ?? null;

        if (! is_string($url) || $url === '') {
            return redirect()->route('notifications.index');
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return redirect($url);
        }

        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($host === $request->getHost() && in_array($scheme, ['http', 'https'], true)) {
            return redirect()->away($url);
        }

        return redirect()->route('notifications.index');
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
