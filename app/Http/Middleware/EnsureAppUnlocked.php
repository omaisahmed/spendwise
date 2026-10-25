<?php

namespace App\Http\Middleware;

use App\Support\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppUnlocked
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = AppSettings::get();

        if (! $settings->app_lock_enabled || $request->session()->get('spendwise_unlocked') === true) {
            return $next($request);
        }

        if ($request->routeIs('lock.*')) {
            return $next($request);
        }

        return redirect()->route('lock.show');
    }
}
