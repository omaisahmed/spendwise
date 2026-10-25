<?php

namespace App\Http\Middleware;

use App\Services\RecurringService;
use App\Support\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RefreshReminders
{
    public function __construct(private RecurringService $recurring) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $settings = AppSettings::get();
        $stale = ! $settings->last_due_check_at || $settings->last_due_check_at->lt(now()->subMinutes(30));

        if ($stale && ($settings->onboarding_completed || $settings->onboarding_skipped)) {
            try {
                $this->recurring->refreshDueNotifications();
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        return $response;
    }
}
