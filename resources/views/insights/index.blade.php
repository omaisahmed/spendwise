@extends('layouts.app')

@section('title', 'Insights')

@section('content')
@php
    $expenses = $totals['expenses'];
    $previousExpenses = $comparison['totals']['expenses'];
    $difference = $expenses - $previousExpenses;
    $differencePercent = $previousExpenses > 0 ? (int) round(abs($difference) / $previousExpenses * 100) : null;
    $essentialPercent = \App\Support\Money::percent($essential, max(1, $expenses));
    $discretionaryPercent = max(0, 100 - $essentialPercent);
    $maxSeries = max(1, ...array_column($series, 'total'));
    $labelEvery = count($series) > 16 ? (int) ceil(count($series) / 8) : 1;
    $fromDisplay = \Carbon\CarbonImmutable::parse($from)->format('d M Y');
    $toDisplay = \Carbon\CarbonImmutable::parse($to)->format('d M Y');
@endphp

<div class="stagger">
    <section class="mb-5">
        <a href="{{ route('dashboard') }}" class="mb-2 inline-flex items-center gap-1 text-[0.7rem] font-semibold text-[var(--muted)]">
            <x-icon name="arrow-left" class="h-3.5 w-3.5" /> Home
        </a>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="page-title">Insights</h1>
                <p class="page-subtitle">{{ $fromDisplay }} — {{ $toDisplay }}</p>
            </div>
            <span class="badge badge-brand mt-2 capitalize">{{ $granularity }} view</span>
        </div>
    </section>

    <form method="GET" action="{{ route('insights.index') }}" class="card mb-5 p-4">
        <div class="grid grid-cols-2 gap-2.5">
            <div>
                <label class="label" for="from">From</label>
                <input id="from" type="date" name="from" value="{{ $from }}" class="input h-10">
            </div>
            <div>
                <label class="label" for="to">To</label>
                <input id="to" type="date" name="to" value="{{ $to }}" class="input h-10">
            </div>
        </div>
        <div class="mt-2.5 grid grid-cols-[1fr_auto] gap-2">
            <select name="granularity" class="select h-10" aria-label="Group by">
                <option value="day" @selected($granularity === 'day')>Group by day</option>
                <option value="week" @selected($granularity === 'week')>Group by week</option>
                <option value="month" @selected($granularity === 'month')>Group by month</option>
            </select>
            <button class="btn btn-primary btn-sm h-10 px-5">Apply</button>
        </div>
        <div class="mt-2.5 flex flex-wrap gap-1.5">
            <a href="{{ route('insights.index', ['from' => now()->startOfMonth()->format('Y-m-d'), 'to' => now()->format('Y-m-d'), 'granularity' => 'day']) }}" class="chip">This month</a>
            <a href="{{ route('insights.index', ['from' => now()->subDays(29)->format('Y-m-d'), 'to' => now()->format('Y-m-d'), 'granularity' => 'day']) }}" class="chip">Last 30 days</a>
            <a href="{{ route('insights.index', ['from' => now()->subDays(89)->format('Y-m-d'), 'to' => now()->format('Y-m-d'), 'granularity' => 'week']) }}" class="chip">Last 90 days</a>
            <a href="{{ route('insights.index', ['from' => now()->startOfYear()->format('Y-m-d'), 'to' => now()->format('Y-m-d'), 'granularity' => 'month']) }}" class="chip">This year</a>
        </div>
    </form>

    <section class="hero-card">
        <p class="text-[0.66rem] font-semibold uppercase tracking-[0.12em] text-white/65">Total spending</p>
        <x-money :amount="$expenses" class="hero-amount mt-1.5 block" />
        <div class="mt-2 flex items-center gap-2 text-[0.68rem] text-white/75">
            @if($differencePercent !== null)
                <span class="inline-flex items-center gap-1 rounded-full bg-white/15 px-2 py-1 font-semibold text-white">
                    <x-icon :name="$difference > 0 ? 'trending-up' : ($difference < 0 ? 'trending-down' : 'minus')" class="h-3 w-3" />
                    {{ $differencePercent }}%
                </span>
                <span>{{ $difference > 0 ? 'more' : ($difference < 0 ? 'less' : 'same') }} than the previous {{ $days }}-day period</span>
            @else
                <span>Your baseline period starts here.</span>
            @endif
        </div>
    </section>

    <div class="mt-3 grid grid-cols-2 gap-2.5">
        <div class="metric-card">
            <span class="metric-icon text-[var(--income)] bg-[var(--income-soft)]"><x-icon name="arrow-down-left" class="h-4 w-4" /></span>
            <p class="metric-label">Income</p>
            <x-money :amount="$totals['income']" class="metric-value" />
        </div>
        <div class="metric-card">
            <span class="metric-icon text-[var(--expense)] bg-[var(--expense-soft)]"><x-icon name="arrow-up-right" class="h-4 w-4" /></span>
            <p class="metric-label">Spent</p>
            <x-money :amount="$expenses" class="metric-value" />
        </div>
        <div class="metric-card">
            <span class="metric-icon text-[var(--info)] bg-[var(--info-soft)]"><x-icon name="calendar-days" class="h-4 w-4" /></span>
            <p class="metric-label">Active day average</p>
            <x-money :amount="$average_daily" class="metric-value" />
        </div>
        <div class="metric-card">
            <span class="metric-icon text-[var(--brand)] bg-[var(--brand-soft)]"><x-icon name="activity" class="h-4 w-4" /></span>
            <p class="metric-label">Active days</p>
            <p class="metric-value">{{ $active_days }}<span class="ml-1 text-[0.66rem] font-medium text-[var(--muted)]">of {{ $days }}</span></p>
        </div>
    </div>

    <section class="card mt-4 p-4">
        <div class="section-head mb-4">
            <h2 class="section-title">Spending trend</h2>
            <span class="text-[0.64rem] text-[var(--muted)]">Peak <x-money :amount="$maxSeries" class="font-medium" /></span>
        </div>
        @if($expenses > 0)
            <div class="flex h-36 items-end gap-[2px]">
                @foreach($series as $point)
                    @php $height = max(2, (int) round($point['total'] / $maxSeries * 100)); @endphp
                    <div class="group relative flex h-full min-w-0 flex-1 items-end">
                        <div class="w-full rounded-t-[3px] bg-[var(--brand)] transition-opacity group-hover:opacity-75" style="height: {{ $height }}%; opacity: {{ $point['total'] > 0 ? '1' : '0.15' }};"></div>
                        <div class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded-lg bg-[var(--navy)] px-2 py-1 text-[0.6rem] font-semibold text-white group-hover:block">
                            {{ \App\Support\Money::format($point['total']) }} · {{ $point['label'] }}
                        </div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[0.56rem] text-[var(--muted)]">
                @foreach($series as $index => $point)
                    @if($index % $labelEvery === 0 || $loop->last)
                        <span class="min-w-0 truncate">{{ $point['label'] }}</span>
                    @endif
                @endforeach
            </div>
        @else
            <p class="py-8 text-center text-[0.72rem] text-[var(--muted)]">No spending in this period.</p>
        @endif
    </section>

    <section class="card mt-4 p-4">
        <div class="section-head mb-3">
            <h2 class="section-title">Needs vs wants</h2>
            <span class="text-[0.64rem] text-[var(--muted)]">Essential share</span>
        </div>
        <div class="flex h-3 overflow-hidden rounded-full bg-[var(--surface-2)]">
            <div class="bg-[var(--brand)]" style="width: {{ $essentialPercent }}%"></div>
            <div class="bg-[var(--accent)] opacity-50" style="width: {{ $discretionaryPercent }}%"></div>
        </div>
        <div class="mt-3 grid grid-cols-2 gap-3">
            <div>
                <div class="flex items-center gap-1.5 text-[0.66rem] text-[var(--muted)]"><span class="legend-dot bg-[var(--brand)]"></span> Essential</div>
                <x-money :amount="$essential" class="mt-1 block text-[0.85rem] font-bold" />
                <p class="text-[0.61rem] text-[var(--muted)]">{{ $essentialPercent }}% of spending</p>
            </div>
            <div>
                <div class="flex items-center gap-1.5 text-[0.66rem] text-[var(--muted)]"><span class="legend-dot bg-[var(--accent)] opacity-60"></span> Discretionary</div>
                <x-money :amount="$discretionary" class="mt-1 block text-[0.85rem] font-bold" />
                <p class="text-[0.61rem] text-[var(--muted)]">{{ $discretionaryPercent }}% of spending</p>
            </div>
        </div>
    </section>

    @if(count($top_categories))
        <section class="mt-4">
            <div class="section-head">
                <h2 class="section-title">Top categories</h2>
                <span class="text-[0.64rem] text-[var(--muted)]">By total spend</span>
            </div>
            <div class="card divide-y divide-[var(--line)] px-4">
                @foreach($top_categories as $category)
                    @php $share = \App\Support\Money::percent($category['total'], max(1, $expenses)); @endphp
                    <div class="py-3">
                        <div class="flex items-center gap-2.5">
                            <span class="icon-bubble h-8 w-8 flex-[0_0_32px] rounded-[10px]" style="color: {{ $category['color'] }}; background: color-mix(in srgb, {{ $category['color'] }} 11%, var(--surface));">
                                <x-icon :name="$category['icon']" class="h-3.5 w-3.5" />
                            </span>
                            <span class="min-w-0 flex-1 truncate text-[0.78rem] font-semibold">{{ $category['name'] }}</span>
                            <span class="text-[0.7rem] font-semibold"><x-money :amount="$category['total']" /></span>
                            <span class="w-8 text-right text-[0.6rem] text-[var(--muted)]">{{ $share }}%</span>
                        </div>
                        <div class="mt-2 h-1 overflow-hidden rounded-full bg-[var(--surface-2)]">
                            <div class="h-full rounded-full" style="width: {{ min(100, $share) }}%; background: {{ $category['color'] }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="mt-4 grid gap-2.5 sm:grid-cols-2">
        <div class="card p-4">
            <div class="flex items-center gap-2">
                <span class="grid h-8 w-8 place-items-center rounded-xl bg-[var(--warning-soft)] text-[var(--warning)]"><x-icon name="repeat" class="h-4 w-4" /></span>
                <p class="text-[0.75rem] font-bold">Recurring commitment</p>
            </div>
            <x-money :amount="$recurring_commitment" class="mt-3 block text-[1.05rem] font-bold" />
            <p class="mt-0.5 text-[0.63rem] text-[var(--muted)]">Estimated monthly</p>
        </div>
        <div class="card p-4">
            <div class="flex items-center gap-2">
                <span class="grid h-8 w-8 place-items-center rounded-xl bg-[var(--income-soft)] text-[var(--income)]"><x-icon name="piggy-bank" class="h-4 w-4" /></span>
                <p class="text-[0.75rem] font-bold">Savings</p>
            </div>
            <x-money :amount="$savings['total_saved']" class="mt-3 block text-[1.05rem] font-bold" />
            <p class="mt-0.5 text-[0.63rem] text-[var(--muted)]">Across all goals</p>
        </div>
    </div>

    @if($budget)
        <a href="{{ route('budgets.index', ['month' => \Illuminate\Support\Carbon::parse($from)->format('Y-m')]) }}" class="mt-4 card block p-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[0.63rem] font-semibold uppercase tracking-wider text-[var(--muted)]">Budget snapshot</p>
                    <p class="mt-1 text-[0.8rem] font-bold">{{ $budget['budget']->name }}</p>
                </div>
                <span class="badge {{ $budget['status'] === 'exceeded' ? 'badge-expense' : ($budget['status'] === 'warning' ? 'badge-warning' : 'badge-income') }}">{{ $budget['percent'] }}% used</span>
            </div>
            <div class="mt-3">
                <x-progress :value="$budget['percent']" :tone="$budget['status'] === 'exceeded' ? 'expense' : ($budget['status'] === 'warning' ? 'warning' : 'brand')" />
            </div>
        </a>
    @endif
</div>
@endsection
