@extends('layouts.app')

@section('title', $category ? 'Edit category' : 'New category')

@section('content')
@php
    $editing = $category !== null;
    $selectedIcon = old('icon', $category?->icon ?? 'circle');
    $selectedColor = old('color', $category?->color ?? '#635BFF');
@endphp

<div
    class="page-narrow"
    x-data="{ type: @js(old('type', $type)), essential: @js((bool) old('is_essential', $category?->is_essential)) }"
>
    <section class="mb-5 flex items-start gap-3">
        <a href="{{ route('categories.index', ['type' => $type]) }}" class="icon-btn mt-0.5" aria-label="Back"><x-icon name="arrow-left" class="h-[18px] w-[18px]" /></a>
        <div>
            <h1 class="text-[1.45rem] font-[750] tracking-[-0.04em]">{{ $editing ? 'Edit category' : 'New category' }}</h1>
            <p class="page-subtitle">{{ $editing ? 'Fine-tune its name and visual style.' : 'A clear label makes every entry easier to read.' }}</p>
        </div>
    </section>

    @include('partials.errors')

    <form method="POST" action="{{ $editing ? route('categories.update', $category) : route('categories.store') }}" class="space-y-4">
        @csrf
        @if($editing) @method('PUT') @endif
        <input type="hidden" name="type" :value="type">

        <div class="card p-5 space-y-4">
            @if(!$editing)
                <div>
                    <span class="label">Category type</span>
                    <div class="segmented">
                        <button type="button" @click="type = 'expense'" :class="type === 'expense' ? 'is-active' : ''">Expense</button>
                        <button type="button" @click="type = 'income'" :class="type === 'income' ? 'is-active' : ''">Income</button>
                    </div>
                </div>
            @else
                <div class="flex items-center justify-between rounded-2xl bg-[var(--surface-2)] px-3.5 py-3">
                    <span class="text-[0.75rem] text-[var(--muted)]">Category type</span>
                    <span class="badge {{ $type === 'income' ? 'badge-income' : 'badge-brand' }}">{{ ucfirst($type) }}</span>
                </div>
            @endif

            <div>
                <label class="label" for="name">Name</label>
                <input id="name" name="name" value="{{ old('name', $category?->name) }}" class="input" placeholder="e.g. Coffee" required maxlength="80">
                @error('name')<p class="field-error">{{ $message }}</p>@enderror
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

            <div x-show="type === 'expense'" x-cloak class="flex items-center justify-between gap-4 rounded-2xl bg-[var(--surface-2)] p-3.5">
                <div>
                    <p class="text-[0.8rem] font-semibold">Essential spending</p>
                    <p class="mt-0.5 text-[0.68rem] text-[var(--muted)]">Needs like rent, food and health — not wants.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="is_essential" value="1" x-model="essential" @checked(old('is_essential', $category?->is_essential))>
                    <span class="sr-only">Essential category</span>
                </label>
            </div>
        </div>

        <div class="flex gap-2">
            <a href="{{ route('categories.index', ['type' => $type]) }}" class="btn btn-secondary flex-1">Cancel</a>
            <button class="btn btn-primary flex-[1.3]"><x-icon name="check" class="h-4 w-4" /> {{ $editing ? 'Save category' : 'Create category' }}</button>
        </div>
    </form>
</div>
@endsection
