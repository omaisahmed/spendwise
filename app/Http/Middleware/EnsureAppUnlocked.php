<?php

namespace App\Http\Middleware;

use App\Support\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppUnlocked
{
    private const IDLE_MINUTES = 30;

    public function handle(Request $request, Closure $next): Response
    {
        $settings = AppSettings::get();

        if (! $settings->app_lock_enabled) {
            return $next($request);
        }

        if ($request->routeIs('lock.*')) {
            return $next($request);
        }

        $unlocked = $request->session()->get('spendwise_unlocked') === true;
        $unlockedAt = $request->session()->get('spendwise_unlocked_at');

        if ($unlocked && is_numeric($unlockedAt) && (int) $unlockedAt < now()->subMinutes(self::IDLE_MINUTES)->timestamp) {
            $unlocked = false;
            $request->session()->put('spendwise_unlocked', false);
            $request->session()->forget('spendwise_unlocked_at');
        }

        if (! $unlocked) {
            $request->session()->forget('spendwise_unlocked_at');

            if ($request->isMethod('GET')) {
                $request->session()->put('url.intended', $request->fullUrl());
            }

            return redirect()->route('lock.show');
        }

        $request->session()->put('spendwise_unlocked_at', now()->timestamp);

        return $next($request);
    }
}
