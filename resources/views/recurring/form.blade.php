@extends('layouts.app')

@section('title', $recurring ? 'Edit recurring' : 'New recurring')

@section('content')
@php
    $editing = $recurring !== null;
    $initialType = old('type', $recurring?->type ?? 'expense');
    $initialFrequency = old('frequency', $recurring?->frequency ?? 'monthly');
    $expenseCategories = $categories->where('type', 'expense');
    $incomeCategories = $categories->where('type', 'income');
    $selectedCategory = old('category_id', $recurring?->category_id);
    $currency = \App\Support\AppSettings::currency();
    $symbol = \App\Support\Money::SYMBOLS[$currency] ?? $currency;
@endphp

<div
    class="page-narrow"
    x-data="{ type: @js($initialType), frequency: @js($initialFrequency), reminder: @js((bool) old('reminder_enabled', $recurring?->reminder_enabled ?? true)) }"
>
    <section class="mb-5 flex items-start gap-3">
        <a href="{{ route('recurring.index') }}" class="icon-btn mt-0.5" aria-label="Back"><x-icon name="arrow-left" class="h-[18px] w-[18px]" /></a>
        <div>
            <h1 class="text-[1.45rem] font-[750] tracking-[-0.04em]">{{ $editing ? 'Edit schedule' : 'New recurring schedule' }}</h1>
            <p class="page-subtitle">{{ $editing ? 'Changes apply to future occurrences.' : 'Set it once. Review it when it is due.' }}</p>
        </div>
    </section>

    @include('partials.errors')

    @if($accounts->isEmpty())
        <div class="alert alert-warning mb-4">Add an account before creating a recurring schedule.</div>
    @endif

    <form method="POST" action="{{ $editing ? route('recurring.update', $recurring) : route('recurring.store') }}" class="space-y-4">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card p-5 space-y-4">
            <div>
                <span class="label">What repeats?</span>
                <div class="segmented">
                    <button type="button" @click="type = 'expense'" :class="type === 'expense' ? 'is-active' : ''">Expense</button>
                    <button type="button" @click="type = 'income'" :class="type === 'income' ? 'is-active' : ''">Income</button>
                    <button type="button" @click="type = 'transfer'" :class="type === 'transfer' ? 'is-active' : ''">Transfer</button>
                </div>
                <input type="hidden" name="type" :value="type">
            </div>

            <div>
                <label class="label" for="amount">Amount</label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[0.78rem] font-semibold text-[var(--muted)]">{{ $symbol }}</span>
                    <input id="amount" name="amount" inputmode="decimal" value="{{ old('amount', $recurring ? \App\Support\Money::toDecimal($recurring->amount_minor) : '') }}" class="input pl-12 text-[1.05rem] font-bold" placeholder="0.00" required>
                </div>
                @error('amount')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div x-show="type !== 'transfer'" x-cloak>
                <div x-show="type === 'expense'">
                    <label class="label" for="category-expense">Category</label>
                    <select id="category-expense" name="category_id" class="select" :disabled="type !== 'expense'">
                        <option value="">Choose category</option>
                        @foreach($expenseCategories as $category)
                            <option value="{{ $category->id }}" @selected($selectedCategory == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div x-show="type === 'income'">
                    <label class="label" for="category-income">Income category</label>
                    <select id="category-income" name="category_id" class="select" :disabled="type !== 'income'">
                        <option value="">Choose category</option>
                        @foreach($incomeCategories as $category)
                            <option value="{{ $category->id }}" @selected($selectedCategory == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                @error('category_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="card p-5 space-y-4">
            <div>
                <label class="label" for="account">Paid from</label>
                <select id="account" name="account_id" class="select" required>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected(old('account_id', $defaultAccountId) == $account->id)>{{ $account->name }} · {{ $account->type }}</option>
                    @endforeach
                </select>
                @error('account_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div x-show="type === 'transfer'" x-cloak>
                <label class="label" for="to_account">Paid into</label>
                <select id="to_account" name="to_account_id" class="select" :disabled="type !== 'transfer'">
                    <option value="">Choose destination</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected(old('to_account_id', $recurring?->to_account_id) == $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
                @error('to_account_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="frequency">Repeats</label>
                    <select id="frequency" name="frequency" class="select" x-model="frequency" required>
                        <option value="daily">Every day</option>
                        <option value="weekly">Every week</option>
                        <option value="monthly">Every month</option>
                        <option value="yearly">Every year</option>
                    </select>
                    @error('frequency')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div x-show="frequency === 'monthly' || frequency === 'yearly'">
                    <label class="label" for="anchor_day">Day of month</label>
                    <input id="anchor_day" type="number" name="anchor_day" min="1" max="31" value="{{ old('anchor_day', $recurring?->anchor_day ?? now()->day) }}" class="input">
                    <p class="hint">Short months use their last day.</p>
                    @error('anchor_day')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="start_date">Starts</label>
                    <input id="start_date" type="date" name="start_date" value="{{ old('start_date', $startDate) }}" class="input" required>
                    @error('start_date')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="end_date">Ends <span class="font-normal text-[var(--muted)]">(optional)</span></label>
                    <input id="end_date" type="date" name="end_date" value="{{ old('end_date', $recurring?->end_date?->format('Y-m-d')) }}" class="input">
                    @error('end_date')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>
        </div>

        <div class="card p-5 space-y-4">
            <div>
                <label class="label" for="merchant">Merchant / payee <span class="font-normal text-[var(--muted)]">(optional)</span></label>
                <input id="merchant" name="merchant" value="{{ old('merchant', $recurring?->merchant) }}" class="input" placeholder="e.g. Netflix" maxlength="160">
                @error('merchant')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="payment_method">Payment method</label>
                <select id="payment_method" name="payment_method" class="select" required>
                    @foreach($paymentMethods as $value => $label)
                        <option value="{{ $value }}" @selected(old('payment_method', $recurring?->payment_method ?? 'cash') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('payment_method')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="notes">Notes <span class="font-normal text-[var(--muted)]">(optional)</span></label>
                <textarea id="notes" name="notes" class="textarea" maxlength="2000" placeholder="Anything to remember">{{ old('notes', $recurring?->notes) }}</textarea>
                @error('notes')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center justify-between gap-4 rounded-2xl bg-[var(--surface-2)] p-3.5">
                <div>
                    <p class="text-[0.8rem] font-semibold">Remind me</p>
                    <p class="mt-0.5 text-[0.66rem] leading-relaxed text-[var(--muted)]">Shows an in-app reminder when this becomes due.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="reminder_enabled" value="1" x-model="reminder" @checked(old('reminder_enabled', $recurring?->reminder_enabled ?? true))>
                    <span class="sr-only">Enable reminder</span>
                </label>
            </div>
        </div>

        <div class="alert alert-info">
            <x-icon name="info" class="mt-0.5 h-4 w-4 shrink-0" />
            <p>Nothing is posted silently. When a schedule is due, you choose to record or skip it.</p>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('recurring.index') }}" class="btn btn-secondary flex-1">Cancel</a>
            <button class="btn btn-primary flex-[1.3]"><x-icon name="check" class="h-4 w-4" /> {{ $editing ? 'Save schedule' : 'Create schedule' }}</button>
        </div>

        @if($editing && $recurring->status !== 'cancelled')
            <div class="flex justify-end">
                <form method="POST" action="{{ route('recurring.destroy', $recurring) }}" onsubmit="return confirm('Cancel this recurring schedule? Existing entries stay.')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-ghost btn-sm text-[var(--expense)]">Cancel schedule</button>
                </form>
            </div>
        @endif
    </form>
</div>
@endsection
