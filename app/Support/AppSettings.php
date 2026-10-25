<?php

namespace App\Support;

use App\Models\AppSetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

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

    public static function dateFormat(): string
    {
        try {
            return self::get()->date_format ?: 'd M Y';
        } catch (\Throwable) {
            return 'd M Y';
        }
    }

    public static function formatDate(CarbonInterface|string|null $date, string $fallback = '-'): string
    {
        return self::formatWithDate($date, self::dateFormat(), $fallback);
    }

    public static function formatDateTime(CarbonInterface|string|null $date, string $fallback = '-'): string
    {
        return self::formatWithDate($date, self::dateFormat().', H:i', $fallback);
    }

    private static function formatWithDate(CarbonInterface|string|null $date, string $format, string $fallback): string
    {
        if ($date === null || $date === '') {
            return $fallback;
        }

        try {
            $value = $date instanceof CarbonInterface ? $date : CarbonImmutable::parse($date);
        } catch (\Throwable) {
            return $fallback;
        }

        return $value->format($format);
    }

    public static function forget(): void
    {
        self::$cached = null;
    }
}
