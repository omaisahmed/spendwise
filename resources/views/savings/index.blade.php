@extends('layouts.app')

@section('title', 'Savings goals')

@section('content')
@php
    $overallPercent = \App\Support\Money::percent($overview['total_saved'], max(1, $overview['total_target']));
@endphp

<div class="stagger">
    <section class="mb-5 flex items-start justify-between gap-3">
        <div>
            <a href="{{ route('settings.index') }}" class="mb-2 inline-flex items-center gap-1 text-[0.7rem] font-semibold text-[var(--muted)]">
                <x-icon name="arrow-left" class="h-3.5 w-3.5" /> More
            </a>
            <h1 class="page-title">Savings</h1>
            <p class="page-subtitle">Small steps, visible progress.</p>
        </div>
        <a href="{{ route('savings.create') }}" class="btn btn-primary btn-sm mt-2"><x-icon name="plus" class="h-4 w-4" /> New goal</a>
    </section>

    @if(count($overview['items']))
        <section class="hero-card">
            <p class="text-[0.66rem] font-semibold uppercase tracking-[0.12em] text-white/65">Total saved</p>
            <x-money :amount="$overview['total_saved']" class="hero-amount mt-1.5 block" />
            <div class="mt-1 text-[0.68rem] text-white/70">of <x-money :amount="$overview['total_target']" class="font-medium text-white" /> across {{ count($overview['items']) }} {{ Str::plural('goal', count($overview['items'])) }}</div>
            <div class="mt-5 h-2 overflow-hidden rounded-full bg-white/20">
                <div class="h-full rounded-full bg-white" style="width: {{ min(100, $overallPercent) }}%"></div>
            </div>
            <div class="mt-2 text-right text-[0.64rem] text-white/70">{{ $overallPercent }}% of all targets</div>
        </section>
    @endif

    <section class="mt-5 space-y-3">
        @forelse($overview['items'] as $item)
            @php
                $goal = $item['goal'];
                $isReached = $item['status'] === 'reached';
                $tone = $isReached ? 'income' : ($goal->status === 'paused' ? 'warning' : 'brand');
            @endphp
            <article class="card p-4" x-data="{ contributing: false, history: false }">
                <div class="flex items-start gap-3">
                    <span class="icon-bubble h-11 w-11 flex-[0_0_44px] rounded-[15px]" style="color: {{ $goal->color }}; background: color-mix(in srgb, {{ $goal->color }} 12%, var(--surface));">
                        <x-icon :name="$goal->icon" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <h2 class="truncate text-[0.9rem] font-bold">{{ $goal->name }}</h2>
                            @if($isReached)
                                <span class="badge badge-income">Reached</span>
                            @elseif($goal->status === 'paused')
                                <span class="badge badge-warning">Paused</span>
                            @elseif($goal->status === 'completed')
                                <span class="badge badge-muted">Completed</span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-[0.66rem] text-[var(--muted)]">
                            @if($goal->target_date)
                                Target {{ $goal->target_date->format('d M Y') }}
                            @else
                                No target date
                            @endif
                        </p>
                    </div>
                    <a href="{{ route('savings.edit', $goal) }}" class="icon-btn h-8 w-8 border-0 bg-transparent" aria-label="Edit goal"><x-icon name="pencil" class="h-3.5 w-3.5" /></a>
                </div>

                <div class="mt-4 flex items-end justify-between gap-3">
                    <div>
                        <x-money :amount="$item['saved']" class="text-[1.08rem] font-[740]" />
                        <span class="text-[0.67rem] text-[var(--muted)]"> of <x-money :amount="$goal->target_minor" class="font-medium" /></span>
                    </div>
                    <span class="text-[0.68rem] font-semibold {{ $isReached ? 'text-[var(--income)]' : 'text-[var(--muted)]' }}">{{ $item['percent'] }}%</span>
                </div>
                <x-progress :value="$item['percent']" :tone="$tone" class="mt-2" />
                <div class="mt-2 flex items-center justify-between text-[0.63rem] text-[var(--muted)]">
                    <span>{{ $goal->contributions->count() }} {{ Str::plural('contribution', $goal->contributions->count()) }}</span>
                    <span>{{ $isReached ? 'Goal complete' : \App\Support\Money::format($item['remaining']).' to go' }}</span>
                </div>

                <div class="mt-3 flex gap-2">
                    <button type="button" class="btn btn-soft btn-sm flex-1" @click="contributing = !contributing">
                        <x-icon name="plus" class="h-3.5 w-3.5" /> Add money
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" @click="history = !history">
                        <x-icon name="history" class="h-3.5 w-3.5" /> History
                    </button>
                </div>

                <form x-show="contributing" x-cloak x-transition method="POST" action="{{ route('savings.contributions.store', $goal) }}" class="mt-3 space-y-3 border-t border-[var(--line)] pt-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="label" for="contribution-{{ $goal->id }}">Amount</label>
                            <input id="contribution-{{ $goal->id }}" name="amount" inputmode="decimal" class="input h-10" placeholder="5000" required>
                            <p class="mt-1 text-[0.6rem] text-[var(--muted)]">Use a negative amount to withdraw.</p>
                        </div>
                        <div>
                            <label class="label" for="contribution-date-{{ $goal->id }}">Date</label>
                            <input id="contribution-date-{{ $goal->id }}" type="date" name="occurred_at" value="{{ now()->format('Y-m-d') }}" class="input h-10" required>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-2.5">
                        <div>
                            <label class="label" for="contribution-account-{{ $goal->id }}">From account</label>
                            <select id="contribution-account-{{ $goal->id }}" name="account_id" class="select h-10">
                                <option value="">Not linked</option>
                                @foreach($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label" for="contribution-note-{{ $goal->id }}">Note</label>
                            <input id="contribution-note-{{ $goal->id }}" name="note" class="input h-10" maxlength="200" placeholder="Optional">
                        </div>
                    </div>
                    <button class="btn btn-primary btn-sm w-full">Save contribution</button>
                </form>

                <div x-show="history" x-cloak x-transition class="mt-3 border-t border-[var(--line)] pt-2">
                    @forelse($goal->contributions->sortByDesc('occurred_at')->take(6) as $contribution)
                        <div class="flex items-center gap-2.5 py-2 {{ !$loop->last ? 'border-b border-[var(--line)]' : '' }}">
                            <span class="legend-dot" style="background: {{ $contribution->amount_minor >= 0 ? 'var(--income)' : 'var(--expense)' }}"></span>
                            <span class="min-w-0 flex-1 truncate text-[0.69rem] text-[var(--muted)]">
                                {{ $contribution->occurred_at->format('d M Y') }}
                                @if($contribution->note) · {{ $contribution->note }} @endif
                                @if($contribution->account) · {{ $contribution->account->name }} @endif
                            </span>
                            <x-money :amount="$contribution->amount_minor" class="text-[0.7rem] font-semibold {{ $contribution->amount_minor < 0 ? 'text-[var(--expense)]' : 'text-[var(--income)]' }}" />
                            <form method="POST" action="{{ route('savings.contributions.destroy', [$goal, $contribution]) }}" onsubmit="return confirm('Remove this contribution?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-[var(--muted)]" aria-label="Delete contribution"><x-icon name="x" class="h-3.5 w-3.5" /></button>
                            </form>
                        </div>
                    @empty
                        <p class="py-2 text-[0.7rem] text-[var(--muted)]">No contributions yet.</p>
                    @endforelse
                </div>

                <details class="mt-2">
                    <summary class="cursor-pointer list-none text-[0.62rem] font-medium text-[var(--muted)]">Goal settings</summary>
                    <form method="POST" action="{{ route('savings.destroy', $goal) }}" class="mt-2" onsubmit="return confirm('Delete {{ addslashes($goal->name) }} and all of its contribution history?')">
                        @csrf
                        @method('DELETE')
                        <button class="text-[0.65rem] font-semibold text-[var(--expense)]">Delete this goal</button>
                    </form>
                </details>
            </article>
        @empty
            <x-empty-state icon="piggy-bank" title="Nothing saved yet" message="Create a goal for something you are looking forward to. Every contribution adds up." class="mt-3">
                <a href="{{ route('savings.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" class="h-4 w-4" /> Create a goal</a>
            </x-empty-state>
        @endforelse
    </section>
</div>
@endsection
