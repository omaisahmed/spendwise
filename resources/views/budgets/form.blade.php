@extends('layouts.app')

@section('title', $budget ? 'Edit budget' : 'New budget')

@section('content')
@php
    $editing = $budget !== null;
    $currency = \App\Support\AppSettings::currency();
    $symbol = \App\Support\Money::SYMBOLS[$currency] ?? $currency;
    $allocated = $budget ? (int) $budget->categoryBudgets()->sum('amount_minor') : 0;
@endphp

<div class="page-narrow">
    <section class="mb-5 flex items-start gap-3">
        <a href="{{ route('budgets.index', ['month' => $month]) }}" class="icon-btn mt-0.5" aria-label="Back"><x-icon name="arrow-left" class="h-[18px] w-[18px]" /></a>
        <div>
            <h1 class="text-[1.45rem] font-[750] tracking-[-0.04em]">{{ $editing ? 'Edit budget' : 'New monthly budget' }}</h1>
            <p class="page-subtitle">{{ $monthLabel }}</p>
        </div>
    </section>

    @include('partials.errors')

    <form method="POST" action="{{ $editing ? route('budgets.update', $budget) : route('budgets.store') }}" class="space-y-4">
        @csrf
        @if($editing) @method('PUT') @endif
        <input type="hidden" name="month" value="{{ $month }}">

        <div class="card p-5 space-y-4">
            <div>
                <label class="label" for="name">Budget name</label>
                <input id="name" name="name" value="{{ old('name', $budget?->name ?? 'Monthly budget') }}" class="input" required maxlength="100">
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="amount">Spending limit</label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[0.8rem] font-semibold text-[var(--muted)]">{{ $symbol }}</span>
                    <input id="amount" name="amount" inputmode="decimal" value="{{ old('amount', $budget ? \App\Support\Money::toDecimal($budget->amount_minor) : '') }}" class="input pl-12 text-[1.1rem] font-bold" placeholder="0.00" required>
                </div>
                @if($allocated > 0)
                    <p class="hint"><x-money :amount="$allocated" class="font-medium" /> is already allocated across category limits.</p>
                @endif
                @error('amount')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div x-data="{ threshold: {{ old('warning_threshold', $budget?->warning_threshold ?? 80) }} }">
                <label class="label" for="warning_threshold">Warn me at <span class="font-semibold text-[var(--brand)]" x-text="threshold + '%'"></span></label>
                <input
                    id="warning_threshold"
                    type="range"
                    name="warning_threshold"
                    min="50"
                    max="100"
                    step="5"
                    value="{{ old('warning_threshold', $budget?->warning_threshold ?? 80) }}"
                    class="mt-2 w-full accent-[var(--brand)]"
                    x-model="threshold"
                >
                <div class="mt-1 flex justify-between text-[0.6rem] text-[var(--muted)]"><span>50%</span><span>100%</span></div>
                @error('warning_threshold')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="notes">Notes <span class="font-normal text-[var(--muted)]">(optional)</span></label>
                <textarea id="notes" name="notes" class="textarea" maxlength="500" placeholder="What is this budget protecting?">{{ old('notes', $budget?->notes) }}</textarea>
                @error('notes')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="alert alert-info">
            <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
            <p>Categories and transfers are separate. Transfers never count as spending, and refunds reduce spending in the category.</p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('budgets.index', ['month' => $month]) }}" class="btn btn-secondary flex-1">Cancel</a>
            <button class="btn btn-primary flex-[1.3]"><x-icon name="check" class="h-4 w-4" /> {{ $editing ? 'Save budget' : 'Create budget' }}</button>
        </div>
    </form>
</div>
@endsection
