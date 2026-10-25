@extends('layouts.app')

@section('title', 'Accounts')

@section('content')
<div class="stagger">
    <section class="mb-5 flex items-start justify-between gap-3">
        <div>
            <a href="{{ route('settings.index') }}" class="mb-2 inline-flex items-center gap-1 text-[0.7rem] font-semibold text-[var(--muted)]">
                <x-icon name="arrow-left" class="h-3.5 w-3.5" /> More
            </a>
            <h1 class="page-title">Accounts</h1>
            <p class="page-subtitle">Every place your money lives.</p>
        </div>
        <a href="{{ route('accounts.create') }}" class="btn btn-primary btn-sm mt-2"><x-icon name="plus" class="h-4 w-4" /> Add</a>
    </section>

    <section class="hero-card">
        <p class="text-[0.66rem] font-semibold uppercase tracking-[0.12em] text-white/65">Combined balance</p>
        <x-money :amount="$totalBalance" class="hero-amount mt-1.5 block" />
        <div class="mt-4 flex items-center justify-between text-[0.66rem] text-white/70">
            <span>{{ $accounts->count() }} active {{ Str::plural('account', $accounts->count()) }}</span>
            <span>Opening balances included</span>
        </div>
    </section>

    <section class="mt-5 space-y-2.5">
        @forelse($accounts as $account)
            @php
                $balance = $balances[$account->id]['balance'] ?? $account->opening_balance_minor;
            @endphp
            <article class="card p-4">
                <div class="flex items-center gap-3">
                    <span class="icon-bubble h-11 w-11 flex-[0_0_44px] rounded-[15px]" style="color: {{ $account->color }}; background: color-mix(in srgb, {{ $account->color }} 12%, var(--surface));">
                        <x-icon :name="$account->icon" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5">
                            <h2 class="truncate text-[0.88rem] font-bold">{{ $account->name }}</h2>
                            @if($account->is_default)
                                <span class="badge badge-brand">Default</span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-[0.67rem] text-[var(--muted)]">
                            {{ \App\Models\Account::TYPES[$account->type] ?? ucfirst($account->type) }}
                            · {{ $account->transactions_count }} {{ Str::plural('entry', $account->transactions_count) }}
                        </p>
                    </div>
                    <x-money :amount="$balance" class="shrink-0 text-[0.98rem] font-[720] {{ $balance < 0 ? 'text-[var(--expense)]' : '' }}" />
                </div>
                <div class="mt-3 flex items-center justify-end gap-1.5 border-t border-[var(--line)] pt-3">
                    <a href="{{ route('accounts.edit', $account) }}" class="btn btn-ghost btn-sm"><x-icon name="pencil" class="h-3.5 w-3.5" /> Edit</a>
                    @if(!$account->is_default)
                        <form method="POST" action="{{ route('accounts.destroy', $account) }}" onsubmit="return confirm('Archive {{ addslashes($account->name) }}? Its transactions stay in history.')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-ghost btn-sm text-[var(--muted)]"><x-icon name="archive" class="h-3.5 w-3.5" /> Archive</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <x-empty-state icon="landmark" title="No accounts yet" message="Add a cash wallet or bank account to begin tracking balances.">
                <a href="{{ route('accounts.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" class="h-4 w-4" /> Add account</a>
            </x-empty-state>
        @endforelse
    </section>

    @if($archived->isNotEmpty())
        <details class="mt-5">
            <summary class="flex cursor-pointer list-none items-center gap-2 text-[0.72rem] font-semibold text-[var(--muted)]">
                <x-icon name="archive" class="h-4 w-4" /> Archived accounts ({{ $archived->count() }})
                <x-icon name="chevron-down" class="ml-auto h-4 w-4" />
            </summary>
            <div class="mt-2 space-y-2">
                @foreach($archived as $account)
                    <div class="card-flat flex items-center gap-3 p-3.5 opacity-75">
                        <span class="icon-bubble h-9 w-9 flex-[0_0_36px] rounded-xl" style="color: {{ $account->color }}; background: color-mix(in srgb, {{ $account->color }} 10%, var(--surface-2));">
                            <x-icon :name="$account->icon" class="h-4 w-4" />
                        </span>
                        <span class="min-w-0 flex-1 truncate text-[0.8rem] font-semibold">{{ $account->name }}</span>
                        <form method="POST" action="{{ route('accounts.restore', $account) }}">
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
