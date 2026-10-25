@extends('layouts.app')

@section('title', $goal ? 'Edit savings goal' : 'New savings goal')

@section('content')
@php
    $editing = $goal !== null;
    $selectedIcon = old('icon', $goal?->icon ?? 'piggy-bank');
    $selectedColor = old('color', $goal?->color ?? '#16A878');
    $currency = \App\Support\AppSettings::currency();
    $symbol = \App\Support\Money::SYMBOLS[$currency] ?? $currency;
@endphp

<div class="page-narrow">
    <section class="mb-5 flex items-start gap-3">
        <a href="{{ route('savings.index') }}" class="icon-btn mt-0.5" aria-label="Back"><x-icon name="arrow-left" class="h-[18px] w-[18px]" /></a>
        <div>
            <h1 class="text-[1.45rem] font-[750] tracking-[-0.04em]">{{ $editing ? 'Edit goal' : 'New savings goal' }}</h1>
            <p class="page-subtitle">{{ $editing ? 'Adjust the target or change its pace.' : 'What are you making room for?' }}</p>
        </div>
    </section>

    @include('partials.errors')

    <form method="POST" action="{{ $editing ? route('savings.update', $goal) : route('savings.store') }}" class="space-y-4">
        @csrf
        @if($editing) @method('PUT') @endif

        <div class="card p-5 space-y-4">
            <div>
                <label class="label" for="name">Goal name</label>
                <input id="name" name="name" value="{{ old('name', $goal?->name) }}" class="input" placeholder="e.g. Emergency fund" required maxlength="100">
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="label" for="target">Target amount</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[0.75rem] font-semibold text-[var(--muted)]">{{ $symbol }}</span>
                        <input id="target" name="target" inputmode="decimal" value="{{ old('target', $goal ? \App\Support\Money::toDecimal($goal->target_minor) : '') }}" class="input pl-10" placeholder="0.00" required>
                    </div>
                    @error('target')<p class="field-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="target_date">Target date</label>
                    <input id="target_date" type="date" name="target_date" value="{{ old('target_date', $goal?->target_date?->format('Y-m-d')) }}" class="input">
                    @error('target_date')<p class="field-error">{{ $message }}</p>@enderror
                </div>
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

            <div>
                <label class="label" for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="active" @selected(old('status', $goal?->status ?? 'active') === 'active')>Active</option>
                    <option value="paused" @selected(old('status', $goal?->status) === 'paused')>Paused</option>
                    <option value="completed" @selected(old('status', $goal?->status) === 'completed')>Completed</option>
                </select>
                @error('status')<p class="field-error">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="notes">Notes <span class="font-normal text-[var(--muted)]">(optional)</span></label>
                <textarea id="notes" name="notes" class="textarea" maxlength="1000" placeholder="Why does this goal matter?">{{ old('notes', $goal?->notes) }}</textarea>
                @error('notes')<p class="field-error">{{ $message }}</p>@enderror
            </div>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('savings.index') }}" class="btn btn-secondary flex-1">Cancel</a>
            <button class="btn btn-primary flex-[1.3]"><x-icon name="check" class="h-4 w-4" /> {{ $editing ? 'Save goal' : 'Create goal' }}</button>
        </div>
    </form>
</div>
@endsection
