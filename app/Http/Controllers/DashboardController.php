<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use App\Services\FinanceService;
use App\Services\PeriodService;
use App\Support\AppSettings;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private FinanceService $finance,
        private PeriodService $periods,
    ) {}

    public function index(Request $request)
    {
        $month = $this->periods->normalizeMonth($request->query('month'));
        $data = $this->finance->dashboard($month);
        $settings = AppSettings::get();

        $notifications = AppNotification::query()
            ->whereNull('read_at')
            ->latest()
            ->limit(3)
            ->get();

        return view('dashboard', [
            ...$data,
            'settings' => $settings,
            'monthLabel' => $this->periods->monthLabel($month),
            'rangeLabel' => $this->periods->rangeLabel($month),
            'monthStartDay' => $this->periods->startDay(),
            'previousMonth' => $this->periods->shiftMonth($month, -1),
            'nextMonth' => $this->periods->shiftMonth($month, 1),
            'canGoNext' => $month < $this->periods->currentMonthKey(),
            'notifications' => $notifications,
        ]);
    }
}
