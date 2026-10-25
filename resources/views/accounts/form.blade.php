@extends('layouts.app')

@section('title', $account ? 'Edit account' : 'New account')

@section('content')
@php
    $editing = $account !== null;
    $icons = ['wallet', 'landmark', 'smartphone', 'piggy-bank', 'credit-card', 'coins'];
    $colors = ['#635BFF', '#16A878', '#4395F7', '#F2AE43', '#F05B6B', '#8B7CFF', '#14B8A6', '#64748B', '#EC4899', '#EF4444'];
    $selectedIcon = old('icon', $account?->icon ?? 'wallet');
    $selectedColor = old('color', $account?->color ?? '#635BFF');
    $currency = \App\Support\AppSettings::currency();
    $symbol = \App\Support\Money::SYMBOLS[$currency] ?? $currency;
@endphp

<div class="page-narrow">
    <section class="mb-5 flex items-start gap-3">
        <a href="{{ route('accounts.index') }}" class="icon-btn mt-0.5" aria-label="Back"><x-icon name="arrow-left" class="h-[18px] w-[18px]" /></a>
        <div>
            <h1 class="text-[1.45rem] font-[750] tracking-[-0.04em]">{{ $editing ? 'Edit account' : 'New account' }}</h1>
            <p class="page-subtitle">{{ $editing ? 'Keep its details and balance accurate.' : 'Add a wallet, bank or savings account.' }}</p>
        </div>
    </section>

    @include('partials.errors')

    <form method="POST" action="{{ $editing ? route('accounts.update', $account) : route('accounts.store') }}" class="space-y-4">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card p-5 space-y-4">
            <div>
                <label class="label" for="name">Account name</label>
                <input id="name" name="name" value="{{ old('name', $account?->name) }}" class="input" placeholder="e.g. HBL Current" required maxlength="80">
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="type">Account type</label>
                <select id="type" name="type" class="select">
                    @foreach($types as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $account?->type ?? 'cash') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="opening_balance">Opening balance</label>
                <div class="relative">
                    <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[0.8rem] font-semibold text-[var(--muted)]">{{ $symbol }}</span>
                    <input id="opening_balance" name="opening_balance" inputmode="decimal" value="{{ old('opening_balance', $account ? \App\Support\Money::toDecimal($account->opening_balance_minor) : '0') }}" class="input pl-12" required>
                </div>
                <p class="hint">The balance before SpendWise transactions. Negative values are allowed.</p>
                @error('opening_balance')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <span class="label">Icon</span>
                <div class="choice-grid">
                    @foreach($icons as $icon)
                        <label class="choice-tile">
                            <input type="radio" name="icon" value="{{ $icon }}" @checked($selectedIcon === $icon)>
                            <x-icon :name="$icon" class="h-5 w-5" />
                        </label>
                    @endforeach
                </div>
                @error('icon')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <span class="label">Colour</span>
                <div class="flex flex-wrap gap-3">
                    @foreach($colors as $color)
                        <label class="relative">
                            <input class="color-input" type="radio" name="color" value="{{ $color }}" @checked($selectedColor === $color)>
                            <span class="color-dot block" style="background: {{ $color }}"></span>
                        </label>
                    @endforeach
                </div>
                @error('color')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="flex items-center justify-between gap-4 rounded-2xl bg-[var(--surface-2)] p-3.5">
                <div>
                    <p class="text-[0.8rem] font-semibold">Make this my default</p>
                    <p class="mt-0.5 text-[0.68rem] text-[var(--muted)]">New transactions will use this account first.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $account?->is_default))>
                    <span class="sr-only">Default account</span>
                </label>
            </div>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('accounts.index') }}" class="btn btn-secondary flex-1">Cancel</a>
            <button class="btn btn-primary flex-[1.3]"><x-icon name="check" class="h-4 w-4" /> {{ $editing ? 'Save account' : 'Create account' }}</button>
        </div>
    </form>
</div>
@endsection
