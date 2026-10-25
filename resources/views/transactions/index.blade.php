@extends('layouts.app')

@section('title', 'Activity')

@section('content')
@php
    $activeFilters = collect(['type', 'category_id', 'account_id', 'payment_method', 'search'])->filter(fn ($key) => request()->filled($key))->count();
    $groups = $transactions->getCollection()->groupBy(fn ($transaction) => $transaction->occurred_at->format('Y-m-d'));
@endphp

<div class="stagger">
    <section class="mb-4 flex items-end justify-between gap-3">
        <div>
            <h1 class="page-title">Activity</h1>
            <p class="page-subtitle">{{ $period === 'all' ? 'Every transaction' : $monthLabel }}</p>
        </div>
        <x-month-switcher
            route="transactions.index"
            :month="$month"
            :label="$monthLabel"
            :previous="$previousMonth"
            :next="$nextMonth"
            :can-next="$canGoNext"
            :params="['period' => 'month']"
        />
    </section>

    <section class="card grid grid-cols-3 divide-x divide-[var(--line)] px-1 py-3.5">
        <div class="px-3">
            <p class="text-[0.6rem] font-semibold uppercase tracking-wider text-[var(--muted)]">Income</p>
            <x-money :amount="$totals['income']" class="mt-1 block text-[0.82rem] font-bold text-[var(--income)]" />
        </div>
        <div class="px-3">
            <p class="text-[0.6rem] font-semibold uppercase tracking-wider text-[var(--muted)]">Spent</p>
            <x-money :amount="$totals['expenses']" class="mt-1 block text-[0.82rem] font-bold text-[var(--expense)]" />
        </div>
        <div class="px-3">
            <p class="text-[0.6rem] font-semibold uppercase tracking-wider text-[var(--muted)]">Net</p>
            <x-money :amount="$totals['balance']" class="mt-1 block text-[0.82rem] font-bold {{ $totals['balance'] >= 0 ? 'text-[var(--ink)]' : 'text-[var(--expense)]' }}" />
        </div>
    </section>

    <div class="mt-4 flex gap-2" x-data="{ filters: {{ $activeFilters > 0 ? 'true' : 'false' }} }">
        <form method="GET" action="{{ route('transactions.index') }}" class="flex min-w-0 flex-1 gap-2">
            <input type="hidden" name="month" value="{{ $month }}">
            <input type="hidden" name="period" value="{{ $period }}">
            <div class="relative min-w-0 flex-1">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-[var(--muted)]" />
                <input name="search" value="{{ request('search') }}" class="input h-[46px] pl-9" placeholder="Search payee, note or tag" aria-label="Search transactions">
            </div>
            @if(request()->filled('search'))
                <a href="{{ route('transactions.index', ['month' => $month, 'period' => $period]) }}" class="icon-btn h-[46px] w-[46px]" aria-label="Clear search"><x-icon name="x" class="h-4 w-4" /></a>
            @endif
        </form>
        <button type="button" class="icon-btn h-[46px] w-[46px] relative" @click="filters = !filters" :class="filters ? 'border-[var(--brand)] text-[var(--brand)]' : ''" aria-label="Filters">
            <x-icon name="sliders-horizontal" class="h-[18px] w-[18px]" />
            @if($activeFilters)
                <span class="absolute -right-0.5 -top-0.5 h-2.5 w-2.5 rounded-full bg-[var(--brand)] ring-2 ring-[var(--page)]"></span>
            @endif
        </button>

        <div x-show="filters" x-cloak x-transition class="fixed inset-0 z-40" @click.self="filters = false">
            <div class="absolute inset-0 bg-[#10131C]/40 backdrop-blur-[2px]"></div>
            <form method="GET" action="{{ route('transactions.index') }}" class="absolute inset-x-0 bottom-0 mx-auto max-w-[540px] rounded-t-[28px] border border-[var(--line)] bg-[var(--surface)] p-5 pb-[calc(24px+env(safe-area-inset-bottom))] shadow-2xl">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="period" value="{{ $period }}">
                <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-[var(--surface-3)]"></div>
                <div class="flex items-center justify-between">
                    <h2 class="text-[1rem] font-bold">Filter activity</h2>
                    <button type="button" class="icon-btn h-8 w-8" @click="filters = false" aria-label="Close"><x-icon name="x" class="h-4 w-4" /></button>
                </div>

                <div class="mt-4 grid gap-3">
                    <div>
                        <label class="label" for="filter-type">Type</label>
                        <select id="filter-type" name="type" class="select">
                            <option value="">All types</option>
                            @foreach(['expense' => 'Expense', 'income' => 'Income', 'transfer' => 'Transfer'] as $value => $label)
                                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="label" for="filter-category">Category</label>
                            <select id="filter-category" name="category_id" class="select">
                                <option value="">Any</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}" @selected((int) request('category_id') === $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="filter-account">Account</label>
                            <select id="filter-account" name="account_id" class="select">
                                <option value="">Any</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}" @selected((int) request('account_id') === $account->id)>{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="label" for="filter-payment">Payment method</label>
                        <select id="filter-payment" name="payment_method" class="select">
                            <option value="">Any method</option>
                            @foreach($paymentMethods as $value => $label)
                                <option value="{{ $value }}" @selected(request('payment_method') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="filter-search">Search</label>
                        <input id="filter-search" name="search" value="{{ request('search') }}" class="input" placeholder="Payee, note or tag">
                    </div>
                </div>

                <div class="mt-5 flex gap-2">
                    <a href="{{ route('transactions.index', ['month' => $month, 'period' => $period]) }}" class="btn btn-secondary flex-1">Reset</a>
                    <button type="submit" class="btn btn-primary flex-1">Apply filters</button>
                </div>
            </form>
        </div>
    </div>

    <div class="mt-3 flex items-center justify-between">
        <p class="text-[0.69rem] text-[var(--muted)]">{{ $transactions->total() }} {{ Str::plural('entry', $transactions->total()) }}</p>
        @if($period === 'month')
            <a href="{{ route('transactions.index', ['period' => 'all'] + request()->except('month', 'period')) }}" class="text-[0.68rem] font-semibold text-[var(--brand)]">Show all time</a>
        @else
            <a href="{{ route('transactions.index', ['month' => $month, 'period' => 'month'] + request()->except('month', 'period')) }}" class="text-[0.68rem] font-semibold text-[var(--brand)]">Back to {{ $monthLabel }}</a>
        @endif
    </div>

    @if($groups->isEmpty())
        <x-empty-state icon="receipt-text" title="No matching activity" message="{{ $activeFilters ? 'Try clearing a filter or searching for something else.' : 'Add your first expense, income or transfer to get started.' }}" class="mt-4">
            @if($activeFilters)
                <a href="{{ route('transactions.index', ['month' => $month, 'period' => $period]) }}" class="btn btn-secondary btn-sm">Clear filters</a>
            @else
                <a href="{{ route('transactions.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" class="h-4 w-4" /> Add transaction</a>
            @endif
        </x-empty-state>
    @else
        <div class="mt-3 space-y-4">
            @foreach($groups as $date => $items)
                @php
                    $day = \Carbon\CarbonImmutable::parse($date);
                    $dayNet = $items->sum(fn ($item) => match ($item->type) {
                        'income' => $item->amount_minor,
                        'expense' => $item->is_refund ? $item->amount_minor : -$item->amount_minor,
                        default => 0,
                    });
                @endphp
                <section>
                    <div class="mb-1.5 flex items-center justify-between px-1">
                        <div class="flex items-baseline gap-2">
                            <h2 class="text-[0.72rem] font-bold">{{ $day->isToday() ? 'Today' : ($day->isYesterday() ? 'Yesterday' : $day->format('D, d M')) }}</h2>
                            <span class="text-[0.62rem] text-[var(--muted)]">{{ $items->count() }} {{ Str::plural('entry', $items->count()) }}</span>
                        </div>
                        @if($dayNet !== 0)
                            <span class="text-[0.66rem] font-semibold {{ $dayNet > 0 ? 'text-[var(--income)]' : 'text-[var(--muted)]' }}">
                                {{ $dayNet > 0 ? '+' : '−' }}<x-money :amount="abs($dayNet)" :symbol="false" />
                            </span>
                        @endif
                    </div>
                    <div class="card px-4">
                        @foreach($items as $transaction)
                            <x-transaction-row :transaction="$transaction" class="{{ !$loop->last ? 'border-b border-[var(--line)]' : '' }}" />
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        @if($transactions->hasPages())
            <nav class="pagination" aria-label="Pagination">
                @if($transactions->onFirstPage())
                    <span class="is-disabled"><x-icon name="chevron-left" class="h-4 w-4" /></span>
                @else
                    <a href="{{ $transactions->previousPageUrl() }}" aria-label="Previous page"><x-icon name="chevron-left" class="h-4 w-4" /></a>
                @endif
                <span>Page {{ $transactions->currentPage() }} of {{ $transactions->lastPage() }}</span>
                @if($transactions->hasMorePages())
                    <a href="{{ $transactions->nextPageUrl() }}" aria-label="Next page"><x-icon name="chevron-right" class="h-4 w-4" /></a>
                @else
                    <span class="is-disabled"><x-icon name="chevron-right" class="h-4 w-4" /></span>
                @endif
            </nav>
        @endif
    @endif
</div>
@endsection
