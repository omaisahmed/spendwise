<?php

namespace App\Http\Controllers;

use App\Services\FinanceService;
use App\Services\PeriodService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InsightsController extends Controller
{
    public function __construct(
        private FinanceService $finance,
        private PeriodService $periods,
    ) {}

    public function index(Request $request)
    {
        $month = $this->periods->normalizeMonth($request->query('month'));
        [$monthStart, $monthEnd] = $this->periods->monthRange($month);
        $defaultTo = $month === $this->periods->currentMonthKey()
            ? now()->toDateString()
            : $monthEnd->subDay()->toDateString();

        $validated = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'granularity' => ['nullable', Rule::in(['day', 'week', 'month'])],
        ]);

        $from = CarbonImmutable::parse($validated['from'] ?? $monthStart->toDateString(), config('app.timezone'))->startOfDay();
        $to = CarbonImmutable::parse($validated['to'] ?? $defaultTo, config('app.timezone'))->startOfDay();
        $end = $to->addDay();

        if ($from->diffInDays($end) > 366) {
            return redirect()->route('insights.index', ['month' => $month])->with('error', 'Choose a date range of one year or less.');
        }

        $data = $this->finance->insights($from, $end, $validated['granularity'] ?? 'day');

        return view('insights.index', [
            ...$data,
            'month' => $month,
            'monthLabel' => $this->periods->monthLabel($month),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'granularity' => $validated['granularity'] ?? 'day',
            'previousMonth' => $this->periods->shiftMonth($month, -1),
            'nextMonth' => $this->periods->shiftMonth($month, 1),
            'canGoNext' => $month < $this->periods->currentMonthKey(),
        ]);
    }
}
