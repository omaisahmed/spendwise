<?php

namespace App\Services;

use App\Support\AppSettings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

class PeriodService
{
    public function startDay(): int
    {
        return max(1, min(31, AppSettings::get()->month_start_day ?: 1));
    }

    public function monthKeyFor(CarbonInterface $date, ?int $startDay = null): string
    {
        $startDay ??= $this->startDay();
        $date = CarbonImmutable::instance($date)->setTimezone(config('app.timezone'));

        $candidateKey = $date->format('Y-m');
        $candidateStart = $this->monthStart($candidateKey, $startDay);

        if ($date->greaterThanOrEqualTo($candidateStart)) {
            return $candidateKey;
        }

        return $this->shiftMonth($candidateKey, -1);
    }

    public function currentMonthKey(): string
    {
        return $this->monthKeyFor(CarbonImmutable::now());
    }

    public function monthStart(string $month, ?int $startDay = null): CarbonImmutable
    {
        $this->assertMonth($month);
        $startDay ??= $this->startDay();

        $base = CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone'));
        $day = min($startDay, $base->daysInMonth);

        return $base->setDay($day)->startOfDay();
    }

    public function monthRange(string $month, ?int $startDay = null): array
    {
        $start = $this->monthStart($month, $startDay);
        $end = $this->monthStart($this->shiftMonth($month, 1), $startDay);

        return [$start, $end];
    }

    public function shiftMonth(string $month, int $offset): string
    {
        $this->assertMonth($month);
        $base = CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone'));

        return $base->addMonthsNoOverflow($offset)->format('Y-m');
    }

    public function monthLabel(string $month, string $format = 'F Y'): string
    {
        $this->assertMonth($month);

        return CarbonImmutable::createFromFormat('!Y-m', $month, config('app.timezone'))->format($format);
    }

    public function rangeLabel(string $month): string
    {
        [$start, $end] = $this->monthRange($month);
        $last = $end->subDay();

        if ($start->day === 1 && $last->isSameMonth($start)) {
            return $start->format('F Y');
        }

        return $start->format('d M').' - '.$last->format('d M Y');
    }

    public function assertMonth(string $month): void
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            throw new InvalidArgumentException('The selected month is invalid.');
        }
    }

    public function rangeForDates(CarbonInterface $start, CarbonInterface $endInclusive): array
    {
        return [
            CarbonImmutable::instance($start)->startOfDay()->setTimezone(config('app.timezone')),
            CarbonImmutable::instance($endInclusive)->startOfDay()->addDay()->setTimezone(config('app.timezone')),
        ];
    }

    public function normalizeMonth(?string $month): string
    {
        return is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)
            ? $month
            : $this->currentMonthKey();
    }
}
