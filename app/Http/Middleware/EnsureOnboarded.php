<?php

namespace App\Http\Middleware;

use App\Support\AppSettings;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboarded
{
    public function handle(Request $request, Closure $next): Response
    {
        $settings = AppSettings::get();

        if ($settings->onboarding_completed || $settings->onboarding_skipped) {
            return $next($request);
        }

        if ($request->routeIs('onboarding.*')) {
            return $next($request);
        }

        return redirect()->route('onboarding.show');
    }
}
