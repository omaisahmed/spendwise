<?php

namespace App\Http\Controllers;

use App\Support\AppSettings;
use Illuminate\Http\Request;

class LockController extends Controller
{
    public function show(Request $request)
    {
        $settings = AppSettings::get();

        if (! $settings->app_lock_enabled || $request->session()->get('spendwise_unlocked') === true) {
            return redirect()->route('dashboard');
        }

        return view('lock', ['settings' => $settings]);
    }

    public function unlockForm()
    {
        return redirect()->route('lock.show');
    }

    public function unlock(Request $request)
    {
        $settings = AppSettings::get();
        $request->validate(['pin' => ['required', 'digits_between:4,6']]);

        if ($settings->isLockedOut()) {
            $seconds = max(1, (int) now()->diffInSeconds($settings->locked_until));

            return back()->with('error', 'Too many attempts. Try again in '.$seconds.' seconds.');
        }

        if (! $settings->verifyPin($request->input('pin'))) {
            $settings->registerFailedPinAttempt();
            AppSettings::forget();

            return back()->with('error', 'Incorrect PIN. Please try again.');
        }

        $settings->clearPinAttempts();
        AppSettings::forget();
        $request->session()->regenerate();
        $request->session()->put('spendwise_unlocked', true);
        $request->session()->put('spendwise_unlocked_at', now()->timestamp);

        return redirect()->intended(route('dashboard'));
    }

    public function lock(Request $request)
    {
        $request->session()->put('spendwise_unlocked', false);
        $request->session()->forget('spendwise_unlocked_at');

        if (! AppSettings::get()->app_lock_enabled) {
            return redirect()->route('settings.index')->with('error', 'Turn on app lock first.');
        }

        $referer = $request->headers->get('referer');
        $host = $referer ? parse_url($referer, PHP_URL_HOST) : null;
        $scheme = $referer ? parse_url($referer, PHP_URL_SCHEME) : null;

        if ($referer && $host === $request->getHost() && in_array($scheme, ['http', 'https'], true)) {
            $request->session()->put('url.intended', $referer);
        }

        return redirect()->route('lock.show');
    }
}
