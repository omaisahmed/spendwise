@extends('layouts.app')

@section('title', 'Overview')

@section('content')
@php
    $hour = (int) now()->format('G');
    $greeting = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $displayName = $settings->display_name ?: 'there';
    $previousExpenses = $comparison['totals']['expenses'];
    $expenseDelta = $previousExpenses > 0 ? \App\Support\Money::percent($totals['expenses'] - $previousExpenses, $previousExpenses) : null;
    $donutSegments = [];
    $cursor = 0;
    foreach (array_slice(array_filter($categories, fn ($category) => $category['total'] > 0), 0, 5) as $category) {
        $share = \App\Support\Money::percent($category['total'], max(1, $totals['expenses']));
        $to = min(100, $cursor + $share);
        $donutSegments[] = $category['color'].' '.$cursor.'% '.$to.'%';
        $cursor = $to;
    }
    $donutGradient = $donutSegments ? 'conic-gradient('.implode(', ', $donutSegments).')' : 'conic-gradient(var(--surface-3) 0 100%)';
    $maxDaily = max(1, collect($daily)->max('total'));
    $hasTransactions = $totals['income'] > 0 || $totals['expenses'] > 0 || $totals['transfers'] > 0;
@endphp

<div class="stagger">
    <section class="mb-5 flex items-end justify-between gap-3">
        <div class="min-w-0">
            <p class="eyebrow">{{ $greeting }}</p>
            <h1 class="page-title mt-1 truncate">{{ $displayName }}</h1>
        </div>
        <x-month-switcher
            route="dashboard"
            :month="$month"
            :label="$monthLabel"
            :previous="$previousMonth"
            :next="$nextMonth"
            :can-next="$canGoNext"
        />
    </section>

    <section class="hero-card fade-up">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-[0.66rem] font-semibold uppercase tracking-[0.12em] text-white/65">Total available</p>
                <div class="hero-amount mt-1.5">
                    <x-money :amount="$total_balance" class="text-white" />
                </div>
                <p class="mt-1.5 text-[0.68rem] font-medium text-white/65">Across {{ count(\App\Models\Account::query()->active()->get()) }} active {{ Str::plural('account', \App\Models\Account::query()->active()->count()) }}</p>
            </div>
            <span class="grid h-10 w-10 place-items-center rounded-2xl bg-white/14 text-white backdrop-blur">
                <x-icon name="wallet-minimal" class="h-5 w-5" />
            </span>
        </div>

        <div class="mt-5 grid grid-cols-2 gap-2.5">
            <div class="rounded-2xl bg-white/12 px-3.5 py-3 backdrop-blur-sm">
                <div class="flex items-center gap-1.5 text-[0.62rem] font-semibold text-white/65">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#67E8C2]"></span> Income
                </div>
                <x-money :amount="$totals['income']" class="mt-1 block text-[0.92rem] font-bold text-white" />
            </div>
            <div class="rounded-2xl bg-white/12 px-3.5 py-3 backdrop-blur-sm">
                <div class="flex items-center gap-1.5 text-[0.62rem] font-semibold text-white/65">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#FFC2C9]"></span> Spent
                </div>
                <x-money :amount="$totals['expenses']" class="mt-1 block text-[0.92rem] font-bold text-white" />
            </div>
        </div>

        <div class="mt-4 flex items-center justify-between text-[0.66rem] text-white/70">
            <span>{{ $rangeLabel }}</span>
            <a href="{{ route('transactions.index', ['month' => $month]) }}" class="flex items-center gap-1 font-semibold text-white">
                View activity <x-icon name="arrow-right" class="h-3.5 w-3.5" />
            </a>
        </div>
    </section>

    <section class="quick-grid mt-4">
        <a href="{{ route('transactions.create', ['type' => 'expense']) }}" class="quick-action">
            <span class="icon-bubble" style="color: var(--expense); background: var(--expense-soft);"><x-icon name="arrow-up-right" class="h-4 w-4" /></span>
            Expense
        </a>
        <a href="{{ route('transactions.create', ['type' => 'income']) }}" class="quick-action">
            <span class="icon-bubble" style="color: var(--income); background: var(--income-soft);"><x-icon name="arrow-down-left" class="h-4 w-4" /></span>
            Income
        </a>
        <a href="{{ route('transfers.create') }}" class="quick-action">
            <span class="icon-bubble" style="color: var(--info); background: var(--info-soft);"><x-icon name="arrow-left-right" class="h-4 w-4" /></span>
            Transfer
        </a>
        <a href="{{ route('budgets.index', ['month' => $month]) }}" class="quick-action">
            <span class="icon-bubble" style="color: var(--warning); background: var(--warning-soft);"><x-icon name="chart-pie" class="h-4 w-4" /></span>
            Budget
        </a>
    </section>

    @if(count($observations))
        <section class="mt-5">
            <div class="section-head">
                <h2 class="section-title">Worth knowing</h2>
                <span class="badge badge-brand"><x-icon name="sparkles" class="h-3 w-3" /> Smart notes</span>
            </div>
            <div class="card divide-y divide-[var(--line)] px-4">
                @foreach($observations as $observation)
                    @php
                        $toneColor = match($observation['tone']) {
                            'income' => 'var(--income)',
                            'expense' => 'var(--expense)',
                            'warning' => 'var(--warning)',
                            default => 'var(--brand)',
                        };
                    @endphp
                    <div class="flex items-center gap-3 py-3">
                        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $toneColor }}; background: color-mix(in srgb, {{ $toneColor }} 12%, var(--surface));">
                            <x-icon :name="$observation['icon']" class="h-4 w-4" />
                        </span>
                        <p class="text-[0.78rem] leading-relaxed text-[var(--ink-soft)]">{{ $observation['text'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-6">
        @if($budget)
            <a href="{{ route('budgets.index', ['month' => $month]) }}" class="card block p-[18px]">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="eyebrow">Monthly budget</p>
                        <p class="mt-1.5 text-[0.92rem] font-bold">{{ $budget['budget']->name }}</p>
                    </div>
                    @if($budget['status'] === 'exceeded')
                        <span class="badge badge-expense">Over budget</span>
                    @elseif($budget['status'] === 'warning')
                        <span class="badge badge-warning">{{ $budget['percent'] }}% used</span>
                    @else
                        <span class="badge badge-income">{{ $budget['percent'] }}% used</span>
                    @endif
                </div>
                <div class="mt-4 flex items-end justify-between gap-3">
                    <div>
                        <x-money :amount="$budget['spent']" class="text-[1.15rem] font-[750]" />
                        <span class="text-[0.68rem] text-[var(--muted)]"> of <x-money :amount="$budget['budget']->amount_minor" class="font-medium" /></span>
                    </div>
                    <span class="text-right text-[0.68rem] {{ $budget['remaining'] < 0 ? 'expense' : 'muted' }}">
                        {{ $budget['remaining'] < 0 ? 'Over by' : 'Left' }}<br>
                        <x-money :amount="abs($budget['remaining'])" class="text-[0.78rem] font-bold {{ $budget['remaining'] < 0 ? 'expense' : 'text-[var(--ink)]' }}" />
                    </span>
                </div>
                <x-progress :value="$budget['percent']" :tone="$budget['status'] === 'exceeded' ? 'expense' : ($budget['status'] === 'warning' ? 'warning' : 'brand')" class="mt-3" />
            </a>
        @else
            <div class="card flex items-center gap-3 p-4">
                <span class="icon-bubble" style="color: var(--warning); background: var(--warning-soft);">
                    <x-icon name="target" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-[0.83rem] font-bold">Give this month a plan</p>
                    <p class="mt-0.5 text-[0.7rem] text-[var(--muted)]">Set a gentle spending limit and track it automatically.</p>
                </div>
                <a href="{{ route('budgets.create', ['month' => $month]) }}" class="btn btn-soft btn-sm">Set budget</a>
            </div>
        @endif
    </section>

    @if($hasTransactions)
        <section class="mt-6">
            <div class="section-head">
                <h2 class="section-title">Spending picture</h2>
                <a href="{{ route('insights.index', ['month' => $month]) }}" class="link flex items-center gap-0.5 text-[0.72rem]">Insights <x-icon name="chevron-right" class="h-3.5 w-3.5" /></a>
            </div>
            <div class="card p-[18px]">
                <div class="flex items-center gap-5">
                    <div class="donut" style="background: {{ $donutGradient }}">
                        <div class="donut-center">
                            <span class="text-[0.58rem] font-semibold uppercase tracking-wider text-[var(--muted)]">Spent</span>
                            <x-money :amount="$totals['expenses']" :symbol="false" class="px-3 text-[0.82rem] font-bold" />
                        </div>
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        @forelse(array_slice($categories, 0, 4) as $category)
                            <div class="flex items-center gap-2">
                                <span class="legend-dot" style="background: {{ $category['color'] }}"></span>
                                <span class="min-w-0 flex-1 truncate text-[0.73rem] text-[var(--ink-soft)]">{{ $category['name'] }}</span>
                                <span class="text-[0.68rem] font-semibold text-[var(--muted)]">{{ \App\Support\Money::percent($category['total'], max(1, $totals['expenses'])) }}%</span>
                            </div>
                        @empty
                            <p class="text-[0.75rem] text-[var(--muted)]">No spending in this period yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="mt-5 divider"></div>
                <div class="mt-4 flex items-center justify-between">
                    <p class="text-[0.72rem] font-semibold">Daily rhythm</p>
                    <p class="text-[0.66rem] text-[var(--muted)]">Avg <x-money :amount="$average_daily" class="font-semibold text-[var(--ink-soft)]" /> / day</p>
                </div>
                <div class="bar-chart chart-grid mt-1" aria-label="Daily spending chart">
                    @foreach($daily as $day)
                        <div
                            class="bar {{ $highest_day && $highest_day['date'] === $day['date'] && $day['total'] > 0 ? 'is-peak' : '' }}"
                            style="height: {{ $day['total'] > 0 ? max(6, (int) round($day['total'] / $maxDaily * 100)) : 3 }}%"
                            title="{{ \Carbon\Carbon::parse($day['date'])->format('d M') }}: {{ \App\Support\Money::format($day['total']) }}"
                        ></div>
                    @endforeach
                </div>
                <div class="mt-1.5 flex justify-between text-[0.58rem] font-medium text-[var(--muted)]">
                    <span>{{ $daily[0]['label'] ?? '' }} {{ \Carbon\Carbon::parse($daily[0]['date'] ?? now())->format('M') }}</span>
                    <span>{{ $daily[count($daily) - 1]['label'] ?? '' }} {{ \Carbon\Carbon::parse($daily[count($daily) - 1]['date'] ?? now())->format('M') }}</span>
                </div>
            </div>
        </section>
    @endif

    <section class="mt-6">
        <div class="section-head">
            <h2 class="section-title">Recent activity</h2>
            <a href="{{ route('transactions.index', ['month' => $month]) }}" class="link flex items-center gap-0.5 text-[0.72rem]">All <x-icon name="chevron-right" class="h-3.5 w-3.5" /></a>
        </div>
        @if($recent->isNotEmpty())
            <div class="card px-4 py-1.5">
                @foreach($recent as $transaction)
                    <x-transaction-row :transaction="$transaction" class="{{ !$loop->last ? 'border-b border-[var(--line)]' : '' }}" />
                @endforeach
            </div>
        @else
            <x-empty-state icon="receipt-text" title="A clean slate" message="Your first transaction will appear here. Add one whenever you're ready.">
                <a href="{{ route('transactions.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" class="h-4 w-4" /> Add transaction</a>
            </x-empty-state>
        @endif
    </section>

    <section class="mt-6 grid gap-3">
        <div class="card p-[18px]">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="icon-bubble h-9 w-9 flex-[0_0_36px] rounded-xl" style="color: var(--income); background: var(--income-soft);"><x-icon name="piggy-bank" class="h-4 w-4" /></span>
                    <div>
                        <p class="text-[0.82rem] font-bold">Savings goals</p>
                        <p class="text-[0.64rem] text-[var(--muted)]"><x-money :amount="$savings['total_saved']" class="font-semibold" /> of <x-money :amount="$savings['total_target']" class="font-medium" /></p>
                    </div>
                </div>
                <a href="{{ route('savings.index') }}" class="btn btn-ghost btn-sm">View</a>
            </div>
            @if(count($active_goals))
                <div class="mt-3 space-y-3">
                    @foreach($active_goals as $item)
                        <div>
                            <div class="flex items-center justify-between text-[0.68rem]">
                                <span class="font-semibold">{{ $item['goal']->name }}</span>
                                <span class="text-[var(--muted)]">{{ $item['percent'] }}%</span>
                            </div>
                            <x-progress :value="$item['percent']" tone="income" class="mt-1.5 h-1.5" />
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-3 text-[0.73rem] leading-relaxed text-[var(--muted)]">Turn a wish into a plan. Create a goal and watch it grow.</p>
                <a href="{{ route('savings.create') }}" class="btn btn-soft btn-sm mt-3 w-full">Create a savings goal</a>
            @endif
        </div>

        <div class="card p-[18px]">
            <div class="flex items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <span class="icon-bubble h-9 w-9 flex-[0_0_36px] rounded-xl" style="color: var(--info); background: var(--info-soft);"><x-icon name="repeat" class="h-4 w-4" /></span>
                    <div>
                        <p class="text-[0.82rem] font-bold">Upcoming bills</p>
                        <p class="text-[0.64rem] text-[var(--muted)]"><x-money :amount="$recurring_commitment" class="font-semibold" /> monthly commitments</p>
                    </div>
                </div>
                <a href="{{ route('recurring.index') }}" class="btn btn-ghost btn-sm">Manage</a>
            </div>
            @if(count($upcoming_recurring))
                <div class="mt-3">
                    @foreach($upcoming_recurring as $schedule)
                        <div class="flex items-center gap-2.5 py-2 {{ !$loop->last ? 'border-b border-[var(--line)]' : '' }}">
                            <span class="legend-dot" style="background: {{ $schedule->category?->color ?? 'var(--info)' }}"></span>
                            <span class="min-w-0 flex-1 truncate text-[0.74rem] font-medium">{{ $schedule->merchant ?: ($schedule->category?->name ?? ucfirst($schedule->type)) }}</span>
                            <span class="text-[0.68rem] text-[var(--muted)]">{{ $schedule->next_due_date->isToday() ? 'Today' : $schedule->next_due_date->format('d M') }}</span>
                            <x-money :amount="$schedule->amount_minor" class="text-[0.72rem] font-semibold" />
                        </div>
                    @endforeach
                </div>
            @else
                <p class="mt-3 text-[0.73rem] leading-relaxed text-[var(--muted)]">No recurring payments yet. Add rent, subscriptions or bills to stay ahead.</p>
                <a href="{{ route('recurring.create') }}" class="btn btn-soft btn-sm mt-3 w-full">Add a recurring item</a>
            @endif
        </div>
    </section>
</div>
@endsection
