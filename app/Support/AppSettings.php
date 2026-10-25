<?php

namespace App\Support;

use App\Models\AppSetting;

final class AppSettings
{
    private static ?AppSetting $cached = null;

    public static function get(): AppSetting
    {
        return self::$cached ??= AppSetting::current();
    }

    public static function currency(): string
    {
        try {
            return self::get()->currency ?: 'PKR';
        } catch (\Throwable) {
            return 'PKR';
        }
    }

    public static function theme(): string
    {
        try {
            return self::get()->theme ?: 'system';
        } catch (\Throwable) {
            return 'system';
        }
    }

    public static function forget(): void
    {
        self::$cached = null;
    }
}
