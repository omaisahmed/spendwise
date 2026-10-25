@extends('layouts.auth')

@section('title', 'Locked')

@section('content')
<div class="flex min-h-dvh flex-col items-center justify-center px-6 pb-12 pt-[calc(32px+env(safe-area-inset-top))] text-center">
    <div class="relative mb-6">
        <div class="absolute inset-0 scale-150 rounded-full bg-[var(--brand-soft)] blur-2xl"></div>
        <span class="relative grid h-20 w-20 place-items-center rounded-[28px] text-white shadow-[0_20px_40px_rgba(78,70,229,.32)]" style="background: linear-gradient(135deg, #6f67ff, #4e46e5);">
            <x-icon name="lock-keyhole" class="h-8 w-8" />
        </span>
    </div>

    <h1 class="text-[1.6rem] font-[750] tracking-[-0.04em]">Welcome back</h1>
    <p class="mt-2 max-w-xs text-[0.84rem] leading-relaxed text-[var(--muted)]">
        {{ $settings->display_name ? $settings->display_name . ', enter your PIN to continue.' : 'Enter your PIN to open SpendWise.' }}
    </p>

    @if(session('error'))
        <div class="alert alert-danger mt-5 w-full max-w-xs justify-center">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger mt-5 w-full max-w-xs">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('lock.unlock') }}" class="mt-6 w-full max-w-xs">
        @csrf
        <label class="sr-only" for="pin">PIN</label>
        <input
            id="pin"
            name="pin"
            type="password"
            inputmode="numeric"
            pattern="[0-9]{4,6}"
            maxlength="6"
            autocomplete="off"
            autofocus
            required
            class="input text-center text-[1.7rem] font-bold tracking-[0.55em] {{ $errors->any() || session('error') ? 'input-error shake' : '' }}"
            placeholder="••••"
            aria-label="PIN"
        >
        <button type="submit" class="btn btn-primary mt-4 w-full">
            <x-icon name="unlock" class="h-4 w-4" /> Unlock
        </button>
    </form>
    <p class="mt-6 flex items-center gap-1.5 text-[0.68rem] text-[var(--muted)]">
        <x-icon name="shield-check" class="h-3.5 w-3.5" /> Protected on this device
    </p>
</div>
@endsection
