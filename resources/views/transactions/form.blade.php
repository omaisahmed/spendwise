@extends('layouts.app')

@section('title', $transaction ? 'Edit transaction' : 'Add transaction')

@section('content')
@php
    $editing = $transaction !== null;
    $oldType = old('type', $type);
    $expenseCategories = $categories->where('type', 'expense');
    $incomeCategories = $categories->where('type', 'income');
    $selectedCategory = old('category_id', $transaction?->category_id);
    $tagValue = old('tags', $transaction?->tags->pluck('name')->implode(', '));
    $currency = \App\Support\AppSettings::currency();
    $symbol = \App\Support\Money::SYMBOLS[$currency] ?? $currency;
@endphp

<div
    class="page-narrow"
    x-data="{
        type: @js($oldType),
        refund: @js((bool) old('is_refund', $transaction?->is_refund ?? false))
    }"
>
    <section class="mb-5 flex items-start gap-3">
        <a href="{{ url()->previous() }}" class="icon-btn mt-0.5" aria-label="Back">
            <x-icon name="arrow-left" class="h-[18px] w-[18px]" />
        </a>
        <div class="min-w-0 flex-1">
            <h1 class="text-[1.45rem] font-[750] tracking-[-0.04em]">{{ $editing ? 'Edit transaction' : 'Add transaction' }}</h1>
            <p class="page-subtitle">{{ $editing ? 'Adjust the details of this entry.' : 'A few details and it is done.' }}</p>
        </div>
        @if($editing)
            <form method="POST" action="{{ route('transactions.destroy', $transaction) }}" onsubmit="return confirm('Delete this transaction? This cannot be undone.')">
                @csrf
                @method('DELETE')
                <button class="icon-btn text-[var(--expense)]" aria-label="Delete transaction"><x-icon name="trash-2" class="h-[18px] w-[18px]" /></button>
            </form>
        @endif
    </section>

    @include('partials.errors')

    <form method="POST" action="{{ $editing ? route('transactions.update', $transaction) : route('transactions.store') }}" class="space-y-4">
        @csrf
        @if($editing) @method('PUT') @endif
        <input type="hidden" name="type" :value="type">

        <div class="segmented">
            <button type="button" @click="type = 'expense'" :class="type === 'expense' ? 'is-active !text-[var(--expense)]' : ''">Expense</button>
            <button type="button" @click="type = 'income'" :class="type === 'income' ? 'is-active !text-[var(--income)]' : ''">Income</button>
            <button type="button" @click="type = 'transfer'" :class="type === 'transfer' ? 'is-active !text-[var(--info)]' : ''">Transfer</button>
        </div>

        <div class="card p-5">
            <label class="label" for="amount">{{ $editing ? 'Amount' : 'How much?' }}</label>
            <div class="relative">
                <span class="pointer-events-none absolute bottom-4 left-1 text-[1.2rem] font-semibold text-[var(--muted)]">{{ $symbol }}</span>
                <input
                    id="amount"
                    name="amount"
                    value="{{ old('amount', $transaction ? \App\Support\Money::toDecimal($transaction->amount_minor) : '') }}"
                    inputmode="decimal"
                    autocomplete="off"
                    class="w-full border-0 border-b border-[var(--line)] bg-transparent px-0 pb-3 pl-10 text-[2.25rem] font-[750] tracking-[-0.05em] text-[var(--ink)] outline-none transition-colors focus:border-[var(--brand)] @error('amount') border-[var(--expense)] @enderror"
                    placeholder="0.00"
                    autofocus
                    required
                >
            </div>
            @error('amount')<p class="field-error">{{ $message }}</p>@enderror
            <p class="hint">Use up to 2 decimal places.</p>
        </div>

        <div class="card p-5 space-y-4">
            <div x-show="type !== 'transfer'" x-cloak>
                <label class="label" for="category-expense">Category</label>
                <div class="relative">
                    <template x-if="type === 'expense'">
                        <div>
                            <select id="category-expense" name="category_id" class="select" :disabled="type !== 'expense'">
                                <option value="">Choose a category</option>
                                @foreach($expenseCategories as $category)
                                    <option value="{{ $category->id }}" @selected($oldType === 'expense' && (int) $selectedCategory === $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>
                    <template x-if="type === 'income'">
                        <div>
                            <select id="category-income" name="category_id" class="select" :disabled="type !== 'income'">
                                <option value="">Choose a category</option>
                                @foreach($incomeCategories as $category)
                                    <option value="{{ $category->id }}" @selected($oldType === 'income' && (int) $selectedCategory === $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </template>
                </div>
                @error('category_id')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="account_id">{{ $editing ? 'Account' : 'From account' }}</label>
                    <select id="account_id" name="account_id" class="select" required>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected((int) old('account_id', $defaultAccountId) === $account->id)>{{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('account_id')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div x-show="type !== 'transfer'" x-cloak>
                    <label class="label" for="payment_method">Payment method</label>
                    <select id="payment_method" name="payment_method" class="select">
                        @foreach($paymentMethods as $value => $label)
                            <option value="{{ $value }}" @selected(old('payment_method', $transaction?->payment_method ?? ($oldType === 'transfer' ? 'bank_transfer' : 'cash')) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div x-show="type === 'transfer'" x-cloak>
                <label class="label" for="to_account_id">To account</label>
                <select id="to_account_id" name="to_account_id" class="select" :disabled="type !== 'transfer'">
                    <option value="">Choose destination</option>
                    @foreach($accounts as $account)
                        <option value="{{ $account->id }}" @selected((int) old('to_account_id', $transaction?->to_account_id) === $account->id)>{{ $account->name }}</option>
                    @endforeach
                </select>
                @error('to_account_id')<p class="field-error">{{ $message }}</p>@enderror
                <p class="hint">A transfer moves money between accounts without changing your income or spending totals.</p>
            </div>
        </div>

        <div class="card p-5 space-y-4">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="occurred_at">Date</label>
                    <input id="occurred_at" type="date" name="occurred_at" value="{{ old('occurred_at', $defaultDate) }}" class="input" required>
                    @error('occurred_at')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="occurred_time">Time</label>
                    <input id="occurred_time" type="time" name="occurred_time" value="{{ old('occurred_time', $defaultTime) }}" class="input">
                    @error('occurred_time')<p class="field-error">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="label" for="merchant">Payee or note <span class="font-normal text-[var(--muted)]">(optional)</span></label>
                <input id="merchant" name="merchant" value="{{ old('merchant', $transaction?->merchant) }}" class="input" placeholder="{{ $oldType === 'income' ? 'e.g. Monthly salary' : 'e.g. Grocery store' }}" maxlength="160">
                @error('merchant')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="notes">Details <span class="font-normal text-[var(--muted)]">(optional)</span></label>
                <textarea id="notes" name="notes" class="textarea" maxlength="2000" placeholder="Add context you may want later">{{ old('notes', $transaction?->notes) }}</textarea>
                @error('notes')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="tags">Tags <span class="font-normal text-[var(--muted)]">(optional)</span></label>
                <input id="tags" name="tags" value="{{ $tagValue }}" class="input" placeholder="work, family, reimbursable" maxlength="300">
                <p class="hint">Separate tags with commas.</p>
                @error('tags')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div x-show="type === 'expense'" x-cloak class="flex items-center justify-between gap-4 rounded-2xl bg-[var(--surface-2)] p-3.5">
                <div>
                    <p class="text-[0.8rem] font-semibold">This is a refund</p>
                    <p class="mt-0.5 text-[0.68rem] text-[var(--muted)]">A refund reduces spending instead of adding to it.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="is_refund" value="1" x-model="refund" @checked(old('is_refund', $transaction?->is_refund))>
                    <span class="sr-only">Mark as refund</span>
                </label>
            </div>
        </div>

        <div class="flex gap-2 pt-1">
            <a href="{{ route('transactions.index') }}" class="btn btn-secondary flex-1">Cancel</a>
            <button type="submit" class="btn btn-primary flex-[1.4]">
                <x-icon name="check" class="h-4 w-4" /> {{ $editing ? 'Save changes' : 'Add transaction' }}
            </button>
        </div>
    </form>
</div>
@endsection
