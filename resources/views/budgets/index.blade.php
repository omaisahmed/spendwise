@extends('layouts.app')

@section('title', 'Budgets')

@section('content')
@php
    if ($budgetStatus) {
        $budget = $budgetStatus['budget'];
        $allocated = collect($budgetStatus['categories'])->sum(fn ($item) => $item['model']->amount_minor);
        $unallocated = $budget->amount_minor - $allocated;
        $allocatedCategoryIds = collect($budgetStatus['categories'])->pluck('category.id')->all();
        $availableCategories = $expenseCategories->whereNotIn('id', $allocatedCategoryIds);
    }
@endphp

<div class="stagger">
    <section class="mb-5 flex items-end justify-between gap-3">
        <div>
            <a href="{{ route('dashboard') }}" class="mb-2 inline-flex items-center gap-1 text-[0.7rem] font-semibold text-[var(--muted)]">
                <x-icon name="arrow-left" class="h-3.5 w-3.5" /> Home
            </a>
            <h1 class="page-title">Budget</h1>
            <p class="page-subtitle">{{ $rangeLabel }}</p>
        </div>
        <x-month-switcher
            route="budgets.index"
            :month="$month"
            :label="$monthLabel"
            :previous="$previousMonth"
            :next="$nextMonth"
            :can-next="$canGoNext"
        />
    </section>

    @if($budgetStatus)
        @php
            $status = $budgetStatus['status'];
            $remainingTone = $budgetStatus['remaining'] < 0 ? 'text-[var(--expense)]' : 'text-[var(--ink)]';
        @endphp
        <section class="hero-card">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-[0.66rem] font-semibold uppercase tracking-[0.12em] text-white/65">Monthly plan</p>
                    <x-money :amount="$budget->amount_minor" class="hero-amount mt-1.5 block" />
                    <p class="mt-1 text-[0.68rem] text-white/70">{{ $budget->name }}</p>
                </div>
                <a href="{{ route('budgets.edit', $budget) }}" class="grid h-9 w-9 place-items-center rounded-xl bg-white/14 text-white backdrop-blur" aria-label="Edit budget"><x-icon name="pencil" class="h-4 w-4" /></a>
            </div>

            <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/20">
                <div class="h-full rounded-full bg-white transition-all" style="width: {{ min(100, $budgetStatus['percent']) }}%"></div>
            </div>
            <div class="mt-2.5 flex items-center justify-between text-[0.67rem] text-white/75">
                <span><x-money :amount="$budgetStatus['spent']" class="font-semibold text-white" /> spent</span>
                <span>{{ $budgetStatus['percent'] }}% used</span>
            </div>
            <div class="mt-4 grid grid-cols-2 gap-2.5">
                <div class="rounded-2xl bg-white/12 p-3">
                    <p class="text-[0.58rem] font-semibold uppercase tracking-wider text-white/60">{{ $budgetStatus['remaining'] < 0 ? 'Over budget' : 'Still available' }}</p>
                    <x-money :amount="abs($budgetStatus['remaining'])" class="mt-1 block text-[1rem] font-bold text-white" />
                </div>
                <div class="rounded-2xl bg-white/12 p-3">
                    <p class="text-[0.58rem] font-semibold uppercase tracking-wider text-white/60">Unallocated</p>
                    <x-money :amount="$unallocated" class="mt-1 block text-[1rem] font-bold text-white" />
                </div>
            </div>
        </section>

        <section class="mt-5">
            <div class="section-head">
                <h2 class="section-title">Category limits</h2>
                <span class="text-[0.66rem] text-[var(--muted)]">{{ count($budgetStatus['categories']) }} {{ Str::plural('limit', count($budgetStatus['categories'])) }}</span>
            </div>

            <div class="space-y-2.5">
                @forelse($budgetStatus['categories'] as $item)
                    <article class="card p-4" x-data="{ editing: false }">
                        <div class="flex items-center gap-3">
                            <span class="icon-bubble h-10 w-10 flex-[0_0_40px] rounded-[13px]" style="color: {{ $item['category']->color }}; background: color-mix(in srgb, {{ $item['category']->color }} 12%, var(--surface));">
                                <x-icon :name="$item['category']->icon" class="h-[18px] w-[18px]" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="truncate text-[0.82rem] font-semibold">{{ $item['category']->name }}</p>
                                    @if($item['status'] === 'exceeded')
                                        <span class="badge badge-expense">Over</span>
                                    @elseif($item['status'] === 'warning')
                                        <span class="badge badge-warning">{{ $item['percent'] }}%</span>
                                    @else
                                        <span class="text-[0.66rem] text-[var(--muted)]">{{ $item['percent'] }}%</span>
                                    @endif
                                </div>
                                <p class="mt-0.5 text-[0.66rem] text-[var(--muted)]">
                                    <x-money :amount="$item['spent']" class="font-medium text-[var(--ink-soft)]" /> of <x-money :amount="$item['model']->amount_minor" class="font-medium" />
                                </p>
                            </div>
                            <button type="button" class="icon-btn h-8 w-8 border-0 bg-transparent" @click="editing = !editing" aria-label="Edit limit">
                                <x-icon name="pencil" class="h-3.5 w-3.5" />
                            </button>
                        </div>
                        <x-progress
                            :value="$item['percent']"
                            :tone="$item['status'] === 'exceeded' ? 'expense' : ($item['status'] === 'warning' ? 'warning' : 'brand')"
                            class="mt-3 h-1.5"
                        />

                        <div x-show="editing" x-cloak x-transition class="mt-4 border-t border-[var(--line)] pt-3">
                            <form method="POST" action="{{ route('budgets.categories.update', [$budget, $item['model']]) }}" class="flex items-end gap-2">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="category_id" value="{{ $item['category']->id }}">
                                <div class="min-w-0 flex-1">
                                    <label class="label" for="limit-{{ $item['model']->id }}">Limit</label>
                                    <input id="limit-{{ $item['model']->id }}" name="amount" value="{{ \App\Support\Money::toDecimal($item['model']->amount_minor) }}" inputmode="decimal" class="input h-10" required>
                                </div>
                                <button class="btn btn-primary btn-sm h-10">Save</button>
                            </form>
                            <form method="POST" action="{{ route('budgets.categories.destroy', [$budget, $item['model']]) }}" class="mt-2" onsubmit="return confirm('Remove this category limit?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-[0.68rem] font-semibold text-[var(--expense)]">Remove limit</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <x-empty-state icon="chart-pie" title="No category limits yet" message="The overall budget works on its own, or add limits to protect the categories that matter most." />
                @endforelse
            </div>
        </section>

        @if($availableCategories->isNotEmpty() && $unallocated > 0)
            <section class="mt-4 card p-4" x-data="{ open: false }">
                <button type="button" class="flex w-full items-center justify-between gap-3" @click="open = !open">
                    <div class="flex items-center gap-2.5">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)]"><x-icon name="plus" class="h-4 w-4" /></span>
                        <div class="text-left">
                            <p class="text-[0.82rem] font-bold">Add a category limit</p>
                            <p class="text-[0.66rem] text-[var(--muted)]"><x-money :amount="$unallocated" class="font-medium" /> still unallocated</p>
                        </div>
                    </div>
                    <span class="transition-transform" :class="open ? 'rotate-180' : ''">
                        <x-icon name="chevron-down" class="h-4 w-4 text-[var(--muted)]" />
                    </span>
                </button>
                <form x-show="open" x-cloak x-transition method="POST" action="{{ route('budgets.categories.store', $budget) }}" class="mt-4 space-y-3 border-t border-[var(--line)] pt-4">
                    @csrf
                    <div>
                        <label class="label" for="new-category">Category</label>
                        <select id="new-category" name="category_id" class="select" required>
                            <option value="">Choose category</option>
                            @foreach($availableCategories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="new-limit">Limit</label>
                        <input id="new-limit" name="amount" inputmode="decimal" class="input" placeholder="e.g. 15000" required>
                    </div>
                    <button class="btn btn-primary w-full">Add limit</button>
                </form>
            </section>
        @endif

        <section class="mt-4 flex items-center justify-between gap-3 rounded-2xl border border-[var(--line)] px-4 py-3">
            <p class="text-[0.68rem] leading-relaxed text-[var(--muted)]">Removing this budget keeps all transactions and category limits are deleted with it.</p>
            <form method="POST" action="{{ route('budgets.destroy', $budget) }}" onsubmit="return confirm('Remove this monthly budget?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-ghost btn-sm shrink-0 text-[var(--expense)]">Remove</button>
            </form>
        </section>
    @else
        <x-empty-state icon="target" title="No budget for {{ $monthLabel }}" message="A budget is a gentle guardrail, not a restriction. Set one to see how spending tracks against your plan." class="mt-3">
            <a href="{{ route('budgets.create', ['month' => $month]) }}" class="btn btn-primary btn-sm"><x-icon name="plus" class="h-4 w-4" /> Create monthly budget</a>
        </x-empty-state>

        @if($categorySpend->isNotEmpty())
            <section class="mt-6">
                <div class="section-head">
                    <h2 class="section-title">Where money went</h2>
                    <span class="text-[0.66rem] text-[var(--muted)]">No limits set</span>
                </div>
                <div class="card divide-y divide-[var(--line)] px-4">
                    @foreach($categorySpend->sortByDesc('total')->take(6) as $spend)
                        <div class="flex items-center gap-3 py-3">
                            <span class="legend-dot" style="background: {{ $spend['color'] }}"></span>
                            <span class="min-w-0 flex-1 truncate text-[0.78rem] font-medium">{{ $spend['name'] }}</span>
                            <x-money :amount="$spend['total']" class="text-[0.76rem] font-semibold" />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
</div>
@endsection
