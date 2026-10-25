<?php

namespace App\Http\Controllers;

use App\Http\Requests\SettingsRequest;
use App\Models\Account;
use App\Services\BackupService;
use App\Services\ExportDeliveryService;
use App\Support\AppSettings;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function __construct(
        private BackupService $backups,
        private ExportDeliveryService $delivery,
    ) {}

    public function index()
    {
        $settings = AppSettings::get();

        return view('settings.index', [
            'settings' => $settings,
            'currencies' => Money::SYMBOLS,
            'dateFormats' => ['d M Y' => now()->format('d M Y'), 'd/m/Y' => now()->format('d/m/Y'), 'Y-m-d' => now()->format('Y-m-d'), 'M d, Y' => now()->format('M d, Y')],
            'defaultAccount' => Account::query()->find($settings->default_account_id),
        ]);
    }

    public function update(SettingsRequest $request)
    {
        $settings = AppSettings::get();
        $monthlyIncome = $request->filled('monthly_income') ? Money::parse($request->input('monthly_income')) : null;

        $settings->update([
            'display_name' => $request->input('display_name') ?: null,
            'currency' => $request->input('currency'),
            'month_start_day' => $request->integer('month_start_day'),
            'monthly_income_minor' => $monthlyIncome,
            'budget_warning_threshold' => $request->integer('budget_warning_threshold'),
            'theme' => $request->input('theme'),
            'notifications_enabled' => $request->boolean('notifications_enabled'),
            'privacy_mode' => $request->boolean('privacy_mode'),
            'date_format' => $request->input('date_format'),
        ]);

        AppSettings::forget();

        return redirect()->route('settings.index')->with('success', 'Preferences saved.');
    }

    public function theme(Request $request)
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:system,light,dark'],
        ]);

        $settings = AppSettings::get();
        $settings->forceFill(['theme' => $validated['theme']])->save();
        AppSettings::forget();

        return response()->json(['theme' => $validated['theme']]);
    }

    public function security(Request $request)
    {
        $settings = AppSettings::get();
        $validated = $request->validate([
            'action' => ['required', 'in:enable,change,disable'],
            'current_pin' => ['nullable', 'digits_between:4,6'],
            'pin' => ['nullable', 'digits_between:4,6', 'confirmed'],
        ]);

        if ($validated['action'] === 'disable') {
            $this->assertCurrentPin($settings, $request->input('current_pin'));
            $settings->forceFill([
                'app_lock_enabled' => false,
                'pin_hash' => null,
                'biometric_enabled' => false,
                'lock_attempts' => 0,
                'locked_until' => null,
            ])->save();
            $request->session()->put('spendwise_unlocked', true);
            $request->session()->put('spendwise_unlocked_at', now()->timestamp);

            return redirect()->route('settings.index')->with('success', 'App lock disabled.');
        }

        if ($settings->app_lock_enabled) {
            $this->assertCurrentPin($settings, $request->input('current_pin'));
        }

        $pin = $request->input('pin');
        if (! is_string($pin) || ! preg_match('/^\d{4,6}$/', $pin)) {
            throw ValidationException::withMessages(['pin' => 'Choose a PIN with 4 to 6 digits.']);
        }

        $wasEnabled = $settings->app_lock_enabled;
        $settings->setPin($pin);
        AppSettings::forget();
        $request->session()->put('spendwise_unlocked', true);
        $request->session()->put('spendwise_unlocked_at', now()->timestamp);

        return redirect()->route('settings.index')->with('success', $wasEnabled ? 'PIN updated.' : 'App lock enabled.');
    }

    public function backup()
    {
        $file = $this->backups->export();

        return $this->delivery->deliver($file, 'Your SpendWise backup is ready.');
    }

    public function restore(Request $request)
    {
        $request->validate([
            'backup' => ['required', 'file', 'mimes:json,txt', 'max:20480'],
        ]);

        $path = $request->file('backup')->getRealPath();
        $json = $path ? file_get_contents($path) : false;

        if ($json === false) {
            return back()->with('error', 'The uploaded backup could not be read.');
        }

        try {
            $result = $this->backups->restore($json);
        } catch (\RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        AppSettings::forget();

        $count = array_sum($result['counts']);

        return redirect()->route('settings.index')->with('success', 'Backup restored: '.$count.' records imported.');
    }

    private function assertCurrentPin($settings, ?string $pin): void
    {
        if (! $settings->verifyPin((string) $pin)) {
            throw ValidationException::withMessages(['current_pin' => 'The current PIN is incorrect.']);
        }
    }
}
