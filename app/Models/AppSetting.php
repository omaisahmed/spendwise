<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class AppSetting extends Model
{
    protected $fillable = [
        'display_name', 'currency', 'locale', 'timezone', 'month_start_day', 'theme',
        'monthly_income_minor', 'onboarding_completed', 'onboarding_skipped', 'default_account_id',
        'budget_warning_threshold', 'app_lock_enabled', 'pin_hash', 'biometric_enabled',
        'notifications_enabled', 'privacy_mode', 'date_format', 'lock_attempts', 'locked_until',
        'last_due_check_at',
    ];

    protected $hidden = ['pin_hash'];

    protected function casts(): array
    {
        return [
            'month_start_day' => 'integer',
            'monthly_income_minor' => 'integer',
            'onboarding_completed' => 'boolean',
            'onboarding_skipped' => 'boolean',
            'default_account_id' => 'integer',
            'budget_warning_threshold' => 'integer',
            'app_lock_enabled' => 'boolean',
            'biometric_enabled' => 'boolean',
            'notifications_enabled' => 'boolean',
            'privacy_mode' => 'boolean',
            'lock_attempts' => 'integer',
            'locked_until' => 'datetime',
            'last_due_check_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        $settings = static::query()->first();

        if ($settings) {
            return $settings;
        }

        $defaultAccount = Account::query()->where('is_default', true)->first()
            ?? Account::query()->orderBy('id')->first();

        return static::query()->create([
            'currency' => 'PKR',
            'locale' => 'en',
            'timezone' => config('app.timezone', 'Asia/Karachi'),
            'month_start_day' => 1,
            'theme' => 'system',
            'budget_warning_threshold' => 80,
            'notifications_enabled' => true,
            'default_account_id' => $defaultAccount?->id,
            'date_format' => 'd M Y',
        ]);
    }

    public function verifyPin(string $pin): bool
    {
        return filled($this->pin_hash) && Hash::check($pin, $this->pin_hash);
    }

    public function setPin(string $pin): void
    {
        $this->forceFill([
            'pin_hash' => Hash::make($pin),
            'app_lock_enabled' => true,
            'lock_attempts' => 0,
            'locked_until' => null,
        ])->save();
    }

    public function isLockedOut(): bool
    {
        return $this->locked_until !== null && $this->locked_until->isFuture();
    }

    public function registerFailedPinAttempt(): void
    {
        $attempts = $this->lock_attempts + 1;
        $lockedUntil = $attempts >= 5 ? now()->addMinutes(1) : null;

        $this->forceFill([
            'lock_attempts' => $attempts >= 5 ? 0 : $attempts,
            'locked_until' => $lockedUntil,
        ])->save();
    }

    public function clearPinAttempts(): void
    {
        $this->forceFill(['lock_attempts' => 0, 'locked_until' => null])->save();
    }
}
