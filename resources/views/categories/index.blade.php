@extends('layouts.app')

@section('title', 'Categories')

@section('content')
@php
    $activeCategories = $categories->whereNull('archived_at');
    $archivedCategories = $categories->whereNotNull('archived_at');
@endphp

<div class="stagger">
    <section class="mb-5 flex items-start justify-between gap-3">
        <div>
            <a href="{{ route('settings.index') }}" class="mb-2 inline-flex items-center gap-1 text-[0.7rem] font-semibold text-[var(--muted)]">
                <x-icon name="arrow-left" class="h-3.5 w-3.5" /> More
            </a>
            <h1 class="page-title">Categories</h1>
            <p class="page-subtitle">Shape your spending the way you think.</p>
        </div>
        <a href="{{ route('categories.create', ['type' => $type]) }}" class="btn btn-primary btn-sm mt-2"><x-icon name="plus" class="h-4 w-4" /> Add</a>
    </section>

    <div class="segmented mb-4">
        <a href="{{ route('categories.index', ['type' => 'expense']) }}" class="{{ $type === 'expense' ? 'is-active' : '' }}">Expenses</a>
        <a href="{{ route('categories.index', ['type' => 'income']) }}" class="{{ $type === 'income' ? 'is-active' : '' }}">Income</a>
    </div>

    <div class="card px-4">
        @forelse($activeCategories as $category)
            <article class="flex items-center gap-3 py-3 {{ !$loop->last ? 'border-b border-[var(--line)]' : '' }}">
                <span class="icon-bubble" style="color: {{ $category->color }}; background: color-mix(in srgb, {{ $category->color }} 12%, var(--surface));">
                    <x-icon :name="$category->icon" class="h-[18px] w-[18px]" />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5">
                        <p class="truncate text-[0.83rem] font-semibold">{{ $category->name }}</p>
                        @if($category->is_essential)
                            <span class="badge badge-muted">Essential</span>
                        @endif
                    </div>
                    <p class="mt-0.5 text-[0.65rem] text-[var(--muted)]">{{ $category->transactions_count }} {{ Str::plural('entry', $category->transactions_count) }}</p>
                </div>
                <div class="flex items-center gap-0.5">
                    <a href="{{ route('categories.edit', $category) }}" class="icon-btn h-8 w-8 border-0 bg-transparent" aria-label="Edit {{ $category->name }}"><x-icon name="pencil" class="h-3.5 w-3.5" /></a>
                    <form method="POST" action="{{ route('categories.destroy', $category) }}" onsubmit="return confirm('{{ $category->transactions_count ? 'Archive' : 'Delete' }} {{ addslashes($category->name) }}?')">
                        @csrf
                        @method('DELETE')
                        <button class="icon-btn h-8 w-8 border-0 bg-transparent text-[var(--muted)]" aria-label="{{ $category->transactions_count ? 'Archive' : 'Delete' }}"><x-icon :name="$category->transactions_count ? 'archive' : 'trash-2'" class="h-3.5 w-3.5" /></button>
                    </form>
                </div>
            </article>
        @empty
            <div class="py-6">
                <x-empty-state icon="tags" title="No categories" message="Create a category that fits how you spend.">
                    <a href="{{ route('categories.create', ['type' => $type]) }}" class="btn btn-primary btn-sm">Create category</a>
                </x-empty-state>
            </div>
        @endforelse
    </div>

    @if($archivedCategories->isNotEmpty())
        <details class="mt-5">
            <summary class="flex cursor-pointer list-none items-center gap-2 text-[0.72rem] font-semibold text-[var(--muted)]">
                <x-icon name="archive" class="h-4 w-4" /> Archived ({{ $archivedCategories->count() }})
                <x-icon name="chevron-down" class="ml-auto h-4 w-4" />
            </summary>
            <div class="card mt-2 divide-y divide-[var(--line)] px-4 opacity-75">
                @foreach($archivedCategories as $category)
                    <div class="flex items-center gap-3 py-3">
                        <span class="icon-bubble h-9 w-9 flex-[0_0_36px] rounded-xl" style="color: {{ $category->color }}; background: color-mix(in srgb, {{ $category->color }} 10%, var(--surface-2));">
                            <x-icon :name="$category->icon" class="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1 truncate text-[0.79rem] font-semibold">{{ $category->name }}</span>
                        <form method="POST" action="{{ route('categories.restore', $category) }}">
                            @csrf
                            @method('PATCH')
                            <button class="btn btn-secondary btn-sm">Restore</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </details>
    @endif
</div>
@endsection
