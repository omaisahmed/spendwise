@extends('layouts.app')

@section('title', 'Reports')

@section('content')
@php
    $exportQuery = $filters;
    $showsCategories = in_array($report['type'], ['category', 'monthly_summary', 'custom', 'expense'], true);
    $showsAccounts = in_array($report['type'], ['account', 'custom'], true);
    $showsBudgets = in_array($report['type'], ['budget', 'custom', 'monthly_summary'], true) && count($report['budgets']) > 0;
    $previewed = $report['transactions']->count();
    $isLimited = $report['transaction_count'] > $previewed;
@endphp

<div class="stagger">
    <section class="mb-5 flex items-start justify-between gap-3">
        <div>
            <a href="{{ route('settings.index') }}" class="mb-2 inline-flex items-center gap-1 text-[0.7rem] font-semibold text-[var(--muted)]">
                <x-icon name="arrow-left" class="h-3.5 w-3.5" /> More
            </a>
            <h1 class="page-title">Reports</h1>
            <p class="page-subtitle">Clear answers, ready to share.</p>
        </div>
        <x-icon name="file-chart-column" class="mt-2 h-7 w-7 text-[var(--brand)]" />
    </section>

    <form method="GET" action="{{ route('reports.index') }}" class="card p-4">
        <div>
            <label class="label" for="type">Report type</label>
            <select id="type" name="type" class="select h-10">
                @foreach($types as $value => $label)
                    <option value="{{ $value }}" @selected($report['type'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="mt-2.5 grid grid-cols-2 gap-2.5">
            <div>
                <label class="label" for="from">From</label>
                <input id="from" type="date" name="from" value="{{ $filters['from'] }}" class="input h-10">
            </div>
            <div>
                <label class="label" for="to">To</label>
                <input id="to" type="date" name="to" value="{{ $filters['to'] }}" class="input h-10">
            </div>
        </div>
        <details class="mt-3">
            <summary class="cursor-pointer list-none text-[0.68rem] font-semibold text-[var(--brand)]">More filters</summary>
            <div class="mt-2.5 space-y-2.5">
                <div>
                    <label class="label" for="category_id">Category</label>
                    <select id="category_id" name="category_id" class="select h-10">
                        <option value="">All categories</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected($filters['category_id'] == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="account_id">Account</label>
                    <select id="account_id" name="account_id" class="select h-10">
                        <option value="">All accounts</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected($filters['account_id'] == $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="payment_method">Payment method</label>
                    <select id="payment_method" name="payment_method" class="select h-10">
                        <option value="">Any method</option>
                        @foreach($paymentMethods as $value => $label)
                            <option value="{{ $value }}" @selected($filters['payment_method'] === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </details>
        <button class="btn btn-primary mt-3 w-full">Generate report</button>
    </form>

    <section class="mt-5">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-[0.62rem] font-semibold uppercase tracking-[0.12em] text-[var(--brand)]">Preview</p>
                <h2 class="mt-1 text-[1.1rem] font-bold tracking-tight">{{ $report['title'] }}</h2>
                <p class="mt-0.5 text-[0.66rem] text-[var(--muted)]">{{ $report['start']->format('d M Y') }} — {{ $report['end']->format('d M Y') }}</p>
            </div>
            <span class="badge badge-muted">{{ $report['transaction_count'] }} {{ Str::plural('entry', $report['transaction_count']) }}</span>
        </div>

        <div class="mt-4 grid grid-cols-3 gap-2">
            <div class="rounded-2xl bg-[var(--income-soft)] p-3">
                <p class="text-[0.56rem] font-semibold uppercase tracking-wide text-[var(--income)]">Income</p>
                <x-money :amount="$report['totals']['income']" class="mt-1 block text-[0.8rem] font-bold" />
            </div>
            <div class="rounded-2xl bg-[var(--expense-soft)] p-3">
                <p class="text-[0.56rem] font-semibold uppercase tracking-wide text-[var(--expense)]">Expenses</p>
                <x-money :amount="$report['totals']['expenses']" class="mt-1 block text-[0.8rem] font-bold" />
            </div>
            <div class="rounded-2xl bg-[var(--brand-soft)] p-3">
                <p class="text-[0.56rem] font-semibold uppercase tracking-wide text-[var(--brand)]">Net</p>
                <x-money :amount="$report['totals']['balance']" class="mt-1 block text-[0.8rem] font-bold" />
            </div>
        </div>

        <div class="mt-2.5 grid grid-cols-2 gap-2">
            <a href="{{ route('reports.csv', $exportQuery) }}" class="btn btn-secondary btn-sm"><x-icon name="file-text" class="h-3.5 w-3.5" /> CSV</a>
            <a href="{{ route('reports.pdf', $exportQuery) }}" class="btn btn-primary btn-sm"><x-icon name="file-down" class="h-3.5 w-3.5" /> PDF</a>
        </div>
    </section>

    @if($showsCategories && count($report['categories']))
        <section class="mt-5">
            <div class="section-head">
                <h3 class="section-title">Category breakdown</h3>
                <span class="text-[0.62rem] text-[var(--muted)]">{{ count($report['categories']) }}</span>
            </div>
            <div class="card divide-y divide-[var(--line)] px-4">
                @foreach($report['categories'] as $category)
                    @php $share = \App\Support\Money::percent($category['total'], max(1, $report['totals']['expenses'])); @endphp
                    <div class="flex items-center gap-2.5 py-3">
                        <span class="icon-bubble h-8 w-8 flex-[0_0_32px] rounded-[10px]" style="color: {{ $category['color'] }}; background: color-mix(in srgb, {{ $category['color'] }} 11%, var(--surface));">
                            <x-icon :name="$category['icon']" class="h-3.5 w-3.5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[0.76rem] font-semibold">{{ $category['name'] }}</p>
                            <p class="text-[0.6rem] text-[var(--muted)]">{{ $category['entries'] }} entries · {{ $share }}%</p>
                        </div>
                        <x-money :amount="$category['total']" class="text-[0.75rem] font-semibold" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if($showsAccounts)
        <section class="mt-5">
            <div class="section-head">
                <h3 class="section-title">Accounts</h3>
                <span class="text-[0.62rem] text-[var(--muted)]">Current balances</span>
            </div>
            <div class="card divide-y divide-[var(--line)] px-4">
                @foreach($report['accounts'] as $entry)
                    <div class="flex items-center gap-3 py-3">
                        <span class="icon-bubble h-8 w-8 flex-[0_0_32px] rounded-[10px] bg-[var(--brand-soft)] text-[var(--brand)]"><x-icon name="wallet" class="h-3.5 w-3.5" /></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[0.76rem] font-semibold">{{ $entry['account']->name }}</p>
                            <p class="text-[0.6rem] capitalize text-[var(--muted)]">{{ $entry['account']->type }}</p>
                        </div>
                        <x-money :amount="$entry['balance']" class="text-[0.75rem] font-semibold" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if($showsBudgets)
        <section class="mt-5">
            <div class="section-head">
                <h3 class="section-title">Budget performance</h3>
                <span class="text-[0.62rem] text-[var(--muted)]">{{ count($report['budgets']) }}</span>
            </div>
            <div class="space-y-2">
                @foreach($report['budgets'] as $budget)
                    <div class="card p-3.5">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-[0.76rem] font-semibold">{{ $budget['budget']->name }}</p>
                            <span class="badge {{ $budget['status'] === 'exceeded' ? 'badge-expense' : ($budget['status'] === 'warning' ? 'badge-warning' : 'badge-income') }}">{{ $budget['percent'] }}%</span>
                        </div>
                        <div class="mt-2 flex justify-between text-[0.62rem] text-[var(--muted)]">
                            <span><x-money :amount="$budget['spent']" /> of <x-money :amount="$budget['budget']->amount_minor" /></span>
                            <span><x-money :amount="$budget['remaining']" /> left</span>
                        </div>
                        <x-progress :value="$budget['percent']" :tone="$budget['status'] === 'exceeded' ? 'expense' : ($budget['status'] === 'warning' ? 'warning' : 'brand')" class="mt-2 h-1" />
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-5">
        <div class="section-head">
            <h3 class="section-title">Transactions</h3>
            @if($isLimited)
                <span class="text-[0.62rem] text-[var(--muted)]">First {{ $previewed }} of {{ $report['transaction_count'] }}</span>
            @endif
        </div>
        @if($report['transactions']->isNotEmpty())
            <div class="card divide-y divide-[var(--line)] px-3.5">
                @foreach($report['transactions'] as $transaction)
                    <div class="flex items-center gap-2.5 py-2.5">
                        <div class="w-10 shrink-0">
                            <p class="text-[0.6rem] font-semibold leading-tight">{{ $transaction->occurred_at->format('d M') }}</p>
                            <p class="text-[0.55rem] text-[var(--muted)]">{{ $transaction->occurred_at->format('H:i') }}</p>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[0.72rem] font-semibold">{{ $transaction->merchant ?: ($transaction->category?->name ?? ucfirst($transaction->type)) }}</p>
                            <p class="truncate text-[0.57rem] text-[var(--muted)]">
                                {{ $transaction->account?->name }}
                                @if($transaction->type === 'transfer' && $transaction->toAccount) → {{ $transaction->toAccount->name }} @endif
                                · {{ $transaction->paymentMethodLabel() }}
                            </p>
                        </div>
                        <x-money :amount="$transaction->is_refund ? -$transaction->amount_minor : $transaction->amount_minor" :signed="$transaction->type !== 'expense' || $transaction->is_refund" class="text-[0.7rem] font-semibold" />
                    </div>
                @endforeach
            </div>
            @if($isLimited)
                <p class="mt-2 px-1 text-[0.62rem] text-[var(--muted)]">Exports include all {{ $report['transaction_count'] }} matching transactions.</p>
            @endif
        @else
            <x-empty-state icon="receipt-text" title="No transactions" message="Nothing matches these filters for the selected period." />
        @endif
    </section>

    <p class="mt-5 text-center text-[0.58rem] text-[var(--muted)]">Generated {{ $report['generated_at']->format('d M Y, H:i') }}</p>
</div>
@endsection
