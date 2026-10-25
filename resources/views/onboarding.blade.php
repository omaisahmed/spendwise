@extends('layouts.auth')

@section('title', 'Welcome')

@section('content')
<div
    class="flex min-h-dvh flex-col px-5 pb-[calc(24px+env(safe-area-inset-bottom))] pt-[calc(22px+env(safe-area-inset-top))]"
    x-data="{ step: 1, accountType: @js(old('account_type', 'cash')), theme: @js(old('theme', 'system')) }"
>
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-2.5">
            <span class="brand-mark"><x-icon name="wallet-minimal" class="h-5 w-5" /></span>
            <span class="text-[0.95rem] font-bold tracking-[-0.03em]">SpendWise</span>
        </div>
        <form method="POST" action="{{ route('onboarding.skip') }}">
            @csrf
            <button class="text-[0.75rem] font-semibold text-[var(--muted)]">Skip for now</button>
        </form>
    </div>

    <div class="mt-7 flex gap-1.5" aria-label="Setup progress">
        <template x-for="index in 3" :key="index">
            <span class="h-1.5 flex-1 rounded-full transition-colors" :class="step >= index ? 'bg-[var(--brand)]' : 'bg-[var(--surface-3)]'"></span>
        </template>
    </div>

    <form method="POST" action="{{ route('onboarding.store') }}" class="mt-6 flex flex-1 flex-col">
        @csrf
        @include('partials.errors')

        <div x-show="step === 1" x-transition.opacity class="flex flex-1 flex-col">
            <div class="mt-3">
                <span class="eyebrow">Welcome to clarity</span>
                <h1 class="mt-3 text-[2.15rem] font-[760] leading-[1.07] tracking-[-0.05em]">
                    Your money,<br><span class="text-[var(--brand)]">without the noise.</span>
                </h1>
                <p class="mt-4 max-w-sm text-[0.9rem] leading-relaxed text-[var(--muted)]">
                    A calm, private place to track spending, plan budgets and build savings — right on this device.
                </p>
            </div>

            <div class="mt-7 grid grid-cols-3 gap-2.5">
                @foreach([
                    ['lock-keyhole', 'Private', 'Local-only data'],
                    ['wifi-off', 'Offline', 'Works anywhere'],
                    ['zap', 'Instant', 'No sign-up'],
                ] as [$icon, $title, $desc])
                    <div class="card-flat p-3">
                        <span class="grid h-9 w-9 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)]">
                            <x-icon :name="$icon" class="h-4 w-4" />
                        </span>
                        <p class="mt-2.5 text-[0.73rem] font-bold">{{ $title }}</p>
                        <p class="mt-0.5 text-[0.62rem] leading-snug text-[var(--muted)]">{{ $desc }}</p>
                    </div>
                @endforeach
            </div>

            <div class="mt-auto pt-8">
                <label class="label" for="display_name">What should we call you?</label>
                <input id="display_name" name="display_name" class="input" value="{{ old('display_name') }}" placeholder="Your first name" autocomplete="given-name" required>
                @error('display_name')<p class="field-error">{{ $message }}</p>@enderror
                <button type="button" class="btn btn-primary mt-4 w-full" @click="step = 2">
                    Continue <x-icon name="arrow-right" class="h-4 w-4" />
                </button>
            </div>
        </div>

        <div x-show="step === 2" x-transition.opacity class="flex flex-1 flex-col" x-cloak>
            <div>
                <span class="eyebrow">Step 2 of 3</span>
                <h1 class="mt-2 text-[1.75rem] font-[750] tracking-[-0.04em]">Set up your money</h1>
                <p class="mt-2 text-[0.84rem] text-[var(--muted)]">Start with one account. You can add wallets, banks and savings accounts later.</p>
            </div>

            <div class="mt-6 space-y-4">
                <div>
                    <label class="label" for="account_name">Account name</label>
                    <input id="account_name" name="account_name" class="input" value="{{ old('account_name', 'Cash') }}" required>
                    @error('account_name')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <span class="label">Account type</span>
                    <div class="grid grid-cols-4 gap-2">
                        @foreach(['cash' => ['wallet', 'Cash'], 'bank' => ['landmark', 'Bank'], 'wallet' => ['smartphone', 'Wallet'], 'savings' => ['piggy-bank', 'Savings']] as $value => [$icon, $label])
                            <label class="choice-tile aspect-auto flex-col gap-1.5 py-3">
                                <input type="radio" name="account_type" value="{{ $value }}" x-model="accountType" @checked(old('account_type', 'cash') === $value)>
                                <x-icon :name="$icon" class="h-[18px] w-[18px]" />
                                <span class="text-[0.64rem] font-semibold">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <label class="label" for="starting_balance">Current balance</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[0.82rem] font-semibold text-[var(--muted)]">Rs</span>
                        <input id="starting_balance" name="starting_balance" inputmode="decimal" class="input pl-11 text-[1.05rem] font-bold" value="{{ old('starting_balance', '0') }}" required>
                    </div>
                    <p class="hint">Not sure? Enter 0 and adjust later.</p>
                    @error('starting_balance')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="currency">Currency</label>
                        <select id="currency" name="currency" class="select">
                            @foreach($currencies as $code => $symbol)
                                <option value="{{ $code }}" @selected(old('currency', 'PKR') === $code)>{{ $code }} · {{ $symbol }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="month_start_day">Month starts</label>
                        <select id="month_start_day" name="month_start_day" class="select">
                            @foreach(range(1, 28) as $day)
                                <option value="{{ $day }}" @selected((int) old('month_start_day', 1) === $day)>Day {{ $day }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="mt-auto flex gap-2 pt-8">
                <button type="button" class="btn btn-secondary btn-icon" @click="step = 1" aria-label="Back"><x-icon name="arrow-left" class="h-4 w-4" /></button>
                <button type="button" class="btn btn-primary flex-1" @click="step = 3">Continue <x-icon name="arrow-right" class="h-4 w-4" /></button>
            </div>
        </div>

        <div x-show="step === 3" x-transition.opacity class="flex flex-1 flex-col" x-cloak>
            <div>
                <span class="eyebrow">Step 3 of 3</span>
                <h1 class="mt-2 text-[1.75rem] font-[750] tracking-[-0.04em]">Make it yours</h1>
                <p class="mt-2 text-[0.84rem] text-[var(--muted)]">A quick baseline helps SpendWise spot patterns. You can leave income blank.</p>
            </div>

            <div class="mt-6 space-y-5">
                <div>
                    <label class="label" for="monthly_income">Expected monthly income <span class="font-normal text-[var(--muted)]">(optional)</span></label>
                    <div class="relative">
                        <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[0.82rem] font-semibold text-[var(--muted)]">Rs</span>
                        <input id="monthly_income" name="monthly_income" inputmode="decimal" class="input pl-11" value="{{ old('monthly_income') }}" placeholder="e.g. 150000">
                    </div>
                    @error('monthly_income')<p class="field-error">{{ $message }}</p>@enderror
                </div>

                <div>
                    <span class="label">Appearance</span>
                    <div class="segmented">
                        @foreach(['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'] as $value => $label)
                            <button type="button" @click="theme = @js($value)" :class="theme === @js($value) ? 'is-active' : ''">{{ $label }}</button>
                        @endforeach
                    </div>
                    <input type="hidden" name="theme" :value="theme">
                </div>

                <div class="rounded-2xl border border-[var(--line)] bg-[var(--surface-2)] p-4">
                    <div class="flex items-start gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[var(--surface)] text-[var(--brand)]">
                            <x-icon name="shield-check" class="h-[18px] w-[18px]" />
                        </span>
                        <div>
                            <p class="text-[0.8rem] font-bold">Your data stays here</p>
                            <p class="mt-1 text-[0.72rem] leading-relaxed text-[var(--muted)]">No account, no cloud sync, no advertisers. Export a backup whenever you want.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-auto flex gap-2 pt-8">
                <button type="button" class="btn btn-secondary btn-icon" @click="step = 2" aria-label="Back"><x-icon name="arrow-left" class="h-4 w-4" /></button>
                <button type="submit" class="btn btn-primary flex-1">Enter SpendWise <x-icon name="sparkles" class="h-4 w-4" /></button>
            </div>
        </div>
    </form>
</div>
@endsection
