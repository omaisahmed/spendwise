<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public const DECIMALS = 2;

    public const SYMBOLS = [
        'PKR' => 'Rs',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹',
        'AED' => 'AED',
        'SAR' => 'SAR',
    ];

    public static function parse(?string $value): int
    {
        if ($value === null) {
            throw new InvalidArgumentException('An amount is required.');
        }

        $value = trim($value);

        if ($value === '' || ! preg_match('/^[+-]?(?:\d{1,15}|\d{1,3}(?:[,\s]\d{3})+)(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('Enter a valid amount with up to 2 decimal places.');
        }

        $value = str_replace([',', ' '], '', $value);
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');

        if (strlen($whole) > 15) {
            throw new InvalidArgumentException('Enter a valid amount with up to 2 decimal places.');
        }

        $minor = ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');

        return $negative ? -$minor : $minor;
    }

    public static function toDecimal(int $minor): string
    {
        $negative = $minor < 0;
        $absolute = abs($minor);
        $whole = intdiv($absolute, 100);
        $fraction = str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '').$whole.'.'.$fraction;
    }

    public static function format(int $minor, ?string $currency = null, bool $withSymbol = true): string
    {
        $currency = $currency ?: AppSettings::currency();
        $symbol = self::SYMBOLS[$currency] ?? $currency;
        $absolute = abs($minor);
        $whole = (string) intdiv($absolute, 100);
        $fraction = str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
        $amount = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole).'.'.$fraction;

        if ($minor < 0) {
            $amount = '-'.$amount;
        }

        return $withSymbol ? $symbol.' '.$amount : $amount;
    }

    public static function formatCompact(int $minor, ?string $currency = null): string
    {
        $absolute = abs($minor);
        $sign = $minor < 0 ? '-' : '';
        $currency = $currency ?: AppSettings::currency();
        $symbol = self::SYMBOLS[$currency] ?? $currency;

        [$divisor, $suffix] = match (true) {
            $absolute >= 1000000000 => [1000000000, 'Cr'],
            $absolute >= 10000000 => [10000000, 'L'],
            $absolute >= 100000 => [100000, 'k'],
            default => [100, ''],
        };

        if ($suffix === '') {
            return $symbol.' '.$sign.number_format(intdiv($absolute, 100));
        }

        $tenths = intdiv(($absolute * 10) + intdiv($divisor, 2), $divisor);
        $value = intdiv($tenths, 10).($tenths % 10 !== 0 ? '.'.($tenths % 10) : '');

        return $symbol.' '.$sign.$value.$suffix;
    }

    public static function percent(int $value, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        $sign = $value < 0 ? -1 : 1;

        return $sign * intdiv((abs($value) * 100) + intdiv($total, 2), $total);
    }
}
