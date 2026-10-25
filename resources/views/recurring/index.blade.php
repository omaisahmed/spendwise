@extends('layouts.app')

@section('title', 'Recurring')

@section('content')
@php
    $totalSchedules = $due->count() + $upcoming->count() + $inactive->count();
@endphp

<div class="stagger">
    <section class="mb-5 flex items-start justify-between gap-3">
        <div>
            <a href="{{ route('settings.index') }}" class="mb-2 inline-flex items-center gap-1 text-[0.7rem] font-semibold text-[var(--muted)]">
                <x-icon name="arrow-left" class="h-3.5 w-3.5" /> More
            </a>
            <h1 class="page-title">Recurring</h1>
            <p class="page-subtitle">Bills and commitments, on your radar.</p>
        </div>
        <a href="{{ route('recurring.create') }}" class="btn btn-primary btn-sm mt-2"><x-icon name="plus" class="h-4 w-4" /> Add</a>
    </section>

    @if($totalSchedules === 0)
        <x-empty-state icon="repeat" title="No recurring schedules" message="Add rent, subscriptions, salary or regular transfers so they never surprise you.">
            <a href="{{ route('recurring.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" class="h-4 w-4" /> Add your first schedule</a>
        </x-empty-state>
    @endif

    @if($due->isNotEmpty())
        <section class="mb-6">
            <div class="section-head">
                <h2 class="section-title flex items-center gap-2"><span class="inline-block h-2 w-2 rounded-full bg-[var(--warning)]"></span> Due now</h2>
                <span class="badge badge-warning">{{ $due->count() }} due</span>
            </div>
            <div class="space-y-2.5">
                @foreach($due as $schedule)
                    <article class="card border-[color-mix(in_srgb,var(--warning)_30%,var(--line))] p-4">
                        <div class="flex items-start gap-3">
                            <span class="icon-bubble h-10 w-10 flex-[0_0_40px] rounded-[13px]" style="color: {{ $schedule->category?->color ?? 'var(--brand)' }}; background: color-mix(in srgb, {{ $schedule->category?->color ?? '#635BFF' }} 12%, var(--surface));">
                                <x-icon :name="$schedule->category?->icon ?? ($schedule->type === 'transfer' ? 'arrow-left-right' : 'repeat')" class="h-[18px] w-[18px]" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <h3 class="truncate text-[0.84rem] font-bold">{{ $schedule->merchant ?: ($schedule->category?->name ?? 'Recurring transfer') }}</h3>
                                        <p class="mt-0.5 text-[0.64rem] text-[var(--muted)]">
                                            {{ $frequencies[$schedule->frequency] ?? ucfirst($schedule->frequency) }}
                                            @if($schedule->frequency === 'monthly') · day {{ $schedule->anchor_day }} @endif
                                        </p>
                                    </div>
                                    <x-money :amount="$schedule->amount_minor" class="shrink-0 text-[0.9rem] font-bold" />
                                </div>
                                <p class="mt-2 text-[0.64rem] font-medium text-[var(--warning)]">Due {{ $schedule->next_due_date->format('d M Y') }}</p>
                            </div>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <form method="POST" action="{{ route('recurring.paid', $schedule) }}" class="flex-1" onsubmit="return confirm('Record this payment?')">
                                @csrf
                                <button class="btn btn-primary btn-sm w-full"><x-icon name="check" class="h-3.5 w-3.5" /> Record payment</button>
                            </form>
                            <form method="POST" action="{{ route('recurring.skipped', $schedule) }}" onsubmit="return confirm('Skip this occurrence?')">
                                @csrf
                                <button class="btn btn-secondary btn-sm">Skip</button>
                            </form>
                            <a href="{{ route('recurring.edit', $schedule) }}" class="icon-btn h-8 w-8 self-center border-0" aria-label="Edit schedule"><x-icon name="pencil" class="h-3.5 w-3.5" /></a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if($upcoming->isNotEmpty())
        <section class="mb-6">
            <div class="section-head">
                <h2 class="section-title">Upcoming</h2>
                <span class="text-[0.66rem] text-[var(--muted)]">{{ $upcoming->count() }} {{ Str::plural('schedule', $upcoming->count()) }}</span>
            </div>
            <div class="card divide-y divide-[var(--line)] px-4">
                @foreach($upcoming as $schedule)
                    <article class="flex items-center gap-3 py-3.5">
                        <span class="icon-bubble h-10 w-10 flex-[0_0_40px] rounded-[13px]" style="color: {{ $schedule->category?->color ?? 'var(--brand)' }}; background: color-mix(in srgb, {{ $schedule->category?->color ?? '#635BFF' }} 12%, var(--surface));">
                            <x-icon :name="$schedule->category?->icon ?? ($schedule->type === 'transfer' ? 'arrow-left-right' : 'repeat')" class="h-[18px] w-[18px]" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[0.8rem] font-semibold">{{ $schedule->merchant ?: ($schedule->category?->name ?? 'Recurring transfer') }}</p>
                            <p class="mt-0.5 text-[0.63rem] text-[var(--muted)]">{{ $frequencies[$schedule->frequency] ?? ucfirst($schedule->frequency) }} · next {{ $schedule->next_due_date->format('d M') }}</p>
                        </div>
                        <div class="text-right">
                            <x-money :amount="$schedule->amount_minor" class="block text-[0.8rem] font-semibold" />
                            <a href="{{ route('recurring.edit', $schedule) }}" class="text-[0.6rem] font-medium text-[var(--brand)]">Edit</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    @if($inactive->isNotEmpty())
        <details class="mt-4" @if($due->isEmpty() && $upcoming->isEmpty()) open @endif>
            <summary class="flex cursor-pointer list-none items-center gap-2 text-[0.72rem] font-semibold text-[var(--muted)]">
                <x-icon name="archive" class="h-4 w-4" /> Paused &amp; cancelled ({{ $inactive->count() }})
                <x-icon name="chevron-down" class="ml-auto h-4 w-4" />
            </summary>
            <div class="mt-2 space-y-2">
                @foreach($inactive as $schedule)
                    <article class="card flex items-center gap-3 p-3.5 opacity-80">
                        <span class="icon-bubble h-9 w-9 flex-[0_0_36px] rounded-xl bg-[var(--surface-2)] text-[var(--muted)]">
                            <x-icon :name="$schedule->status === 'paused' ? 'pause' : 'ban'" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-[0.78rem] font-semibold">{{ $schedule->merchant ?: ($schedule->category?->name ?? 'Recurring transfer') }}</p>
                            <p class="mt-0.5 text-[0.6rem] capitalize text-[var(--muted)]">{{ $schedule->status }} · <x-money :amount="$schedule->amount_minor" /></p>
                        </div>
                        @if($schedule->status === 'paused')
                            <form method="POST" action="{{ route('recurring.resume', $schedule) }}">
                                @csrf
                                <button class="btn btn-secondary btn-sm">Resume</button>
                            </form>
                        @else
                            <a href="{{ route('recurring.edit', $schedule) }}" class="icon-btn h-8 w-8 border-0" aria-label="View schedule"><x-icon name="pencil" class="h-3.5 w-3.5" /></a>
                        @endif
                    </article>
                @endforeach
            </div>
        </details>
    @endif

    @if($due->isEmpty() && $totalSchedules > 0)
        <div class="mt-4 flex items-center gap-2.5 rounded-2xl bg-[var(--income-soft)] px-4 py-3 text-[var(--income)]">
            <x-icon name="circle-check" class="h-4 w-4 shrink-0" />
            <p class="text-[0.7rem] font-medium">Nothing due right now. You are up to date.</p>
        </div>
    @endif
</div>
@endsection
