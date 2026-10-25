<?php

namespace App\Http\Middleware;

use App\Services\NotificationService;
use App\Support\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshReminders
{
    public function __construct(private NotificationService $notifications) {}

    public function handle(Request $request, Closure $next): Response
    {
        $settings = AppSettings::get();
        $onboarded = $settings->onboarding_completed || $settings->onboarding_skipped;
        $isRead = in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true);
        $stale = ! $settings->last_due_check_at || $settings->last_due_check_at->lt(now()->subMinutes(30));

        if ($isRead && $stale && $onboarded) {
            $this->refresh();
        }

        $response = $next($request);

        if (! $isRead && $onboarded) {
            $this->refresh();
        }

        return $response;
    }

    private function refresh(): void
    {
        try {
            $this->notifications->refreshAll();
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
