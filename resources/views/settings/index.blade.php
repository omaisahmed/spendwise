@extends('layouts.app')

@section('title', 'Settings')

@section('content')
@php
    $initial = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($settings->display_name ?: 'SpendWise', 0, 1));
    $unread = \App\Models\AppNotification::query()->whereNull('read_at')->count();
    $quickLinks = [
        ['route' => 'categories.index', 'params' => ['type' => 'expense'], 'icon' => 'tags', 'label' => 'Categories'],
        ['route' => 'accounts.index', 'params' => [], 'icon' => 'wallet', 'label' => 'Accounts'],
        ['route' => 'recurring.index', 'params' => [], 'icon' => 'repeat', 'label' => 'Recurring'],
        ['route' => 'savings.index', 'params' => [], 'icon' => 'piggy-bank', 'label' => 'Savings'],
        ['route' => 'reports.index', 'params' => [], 'icon' => 'file-chart-column', 'label' => 'Reports'],
        ['route' => 'notifications.index', 'params' => [], 'icon' => 'bell', 'label' => 'Alerts', 'badge' => $unread],
    ];
@endphp

<div class="stagger">
    <section class="mb-5">
        <a href="{{ route('dashboard') }}" class="mb-2 inline-flex items-center gap-1 text-[0.7rem] font-semibold text-[var(--muted)]">
            <x-icon name="arrow-left" class="h-3.5 w-3.5" /> Home
        </a>
        <h1 class="page-title">Settings</h1>
        <p class="page-subtitle">Make SpendWise feel like yours.</p>
    </section>

    <section class="card mb-4 flex items-center gap-3.5 p-4">
        <div class="grid h-14 w-14 shrink-0 place-items-center rounded-[18px] bg-[var(--navy)] text-[1.3rem] font-bold text-white shadow-[0_8px_24px_rgba(23,27,52,0.2)]">{{ $initial }}</div>
        <div class="min-w-0 flex-1">
            <h2 class="truncate text-[1rem] font-bold">{{ $settings->display_name ?: 'SpendWise' }}</h2>
            <p class="mt-0.5 text-[0.66rem] text-[var(--muted)]">Offline · {{ $settings->currency }} · {{ $settings->timezone }}</p>
            <p class="mt-0.5 text-[0.62rem] text-[var(--muted)]">Default account: {{ $defaultAccount?->name ?? 'None set' }}</p>
        </div>
    </section>

    <section class="mb-5 grid grid-cols-3 gap-2">
        @foreach($quickLinks as $link)
            <a href="{{ route($link['route'], $link['params']) }}" class="relative flex flex-col items-center gap-1.5 rounded-2xl border border-[var(--line)] bg-[var(--surface)] px-1 py-3 text-center">
                <span class="grid h-8 w-8 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)]"><x-icon :name="$link['icon']" class="h-4 w-4" /></span>
                <span class="text-[0.61rem] font-semibold">{{ $link['label'] }}</span>
                @if(!empty($link['badge']))
                    <span class="absolute right-2 top-2 grid h-4 min-w-4 place-items-center rounded-full bg-[var(--expense)] px-1 text-[0.5rem] font-bold text-white">{{ $link['badge'] > 9 ? '9+' : $link['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </section>

    @include('partials.errors')

    <form method="POST" action="{{ route('settings.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <section class="card p-4">
            <h2 class="section-title mb-3">Preferences</h2>
            <div class="space-y-3.5">
                <div>
                    <label class="label" for="display_name">Your name <span class="font-normal text-[var(--muted)]">(optional)</span></label>
                    <input id="display_name" name="display_name" value="{{ old('display_name', $settings->display_name) }}" class="input" maxlength="80" placeholder="What should we call you?">
                </div>
                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="label" for="currency">Currency</label>
                        <select id="currency" name="currency" class="select">
                            @foreach($currencies as $code => $symbol)
                                <option value="{{ $code }}" @selected(old('currency', $settings->currency) === $code)>{{ $code }} · {{ $symbol }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label" for="date_format">Date format</label>
                        <select id="date_format" name="date_format" class="select">
                            @foreach($dateFormats as $format => $example)
                                <option value="{{ $format }}" @selected(old('date_format', $settings->date_format) === $format)>{{ $example }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="label" for="month_start_day">Month starts on</label>
                        <input id="month_start_day" type="number" min="1" max="31" name="month_start_day" value="{{ old('month_start_day', $settings->month_start_day) }}" class="input" required>
                        <p class="hint">Use 25 if your cycle starts on payday.</p>
                    </div>
                    <div>
                        <label class="label" for="monthly_income">Expected income</label>
                        <input id="monthly_income" name="monthly_income" inputmode="decimal" value="{{ old('monthly_income', $settings->monthly_income_minor ? \App\Support\Money::toDecimal($settings->monthly_income_minor) : '') }}" class="input" placeholder="Optional">
                    </div>
                </div>
                <div x-data="{ threshold: {{ old('budget_warning_threshold', $settings->budget_warning_threshold) }} }">
                    <label class="label" for="budget_warning_threshold">Budget warning at <span class="font-semibold text-[var(--brand)]" x-text="threshold + '%'"></span></label>
                    <input id="budget_warning_threshold" type="range" name="budget_warning_threshold" min="50" max="100" step="5" value="{{ old('budget_warning_threshold', $settings->budget_warning_threshold) }}" class="mt-2 w-full accent-[var(--brand)]" x-model="threshold">
                    <div class="mt-1 flex justify-between text-[0.58rem] text-[var(--muted)]"><span>50%</span><span>100%</span></div>
                </div>
            </div>
        </section>

        <section class="card p-4">
            <h2 class="section-title mb-3">Appearance</h2>
            <div class="grid grid-cols-3 gap-2">
                @foreach(['system' => ['monitor', 'System'], 'light' => ['sun', 'Light'], 'dark' => ['moon', 'Dark']] as $value => $option)
                    <label class="theme-tile relative">
                        <input type="radio" name="theme" value="{{ $value }}" class="peer sr-only" @checked(old('theme', $settings->theme) === $value)>
                        <span class="flex flex-col items-center gap-1.5 rounded-2xl border border-[var(--line)] bg-[var(--surface-2)] py-3 text-[var(--muted)] transition peer-checked:border-[var(--brand)] peer-checked:bg-[var(--brand-soft)] peer-checked:text-[var(--brand)]">
                            <x-icon :name="$option[0]" class="h-4 w-4" />
                            <span class="text-[0.63rem] font-semibold">{{ $option[1] }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>

        <section class="card divide-y divide-[var(--line)] px-4">
            <div class="flex items-center justify-between gap-4 py-3.5">
                <div>
                    <p class="text-[0.8rem] font-semibold">Reminders</p>
                    <p class="mt-0.5 text-[0.64rem] text-[var(--muted)]">Due schedules and budget alerts in the app.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="notifications_enabled" value="1" @checked(old('notifications_enabled', $settings->notifications_enabled))>
                    <span class="sr-only">Enable reminders</span>
                </label>
            </div>
            <div class="flex items-center justify-between gap-4 py-3.5">
                <div>
                    <p class="text-[0.8rem] font-semibold">Privacy mode</p>
                    <p class="mt-0.5 text-[0.64rem] text-[var(--muted)]">Hide amounts until you tap the eye icon.</p>
                </div>
                <label class="switch">
                    <input type="checkbox" name="privacy_mode" value="1" @checked(old('privacy_mode', $settings->privacy_mode))>
                    <span class="sr-only">Enable privacy mode</span>
                </label>
            </div>
        </section>

        <button class="btn btn-primary w-full"><x-icon name="check" class="h-4 w-4" /> Save preferences</button>
    </form>

    <section class="card mt-4 p-4">
        <div class="mb-3 flex items-center gap-2">
            <span class="grid h-8 w-8 place-items-center rounded-xl bg-[var(--brand-soft)] text-[var(--brand)]"><x-icon name="shield-check" class="h-4 w-4" /></span>
            <div>
                <h2 class="text-[0.85rem] font-bold">App lock</h2>
                <p class="text-[0.63rem] text-[var(--muted)]">{{ $settings->app_lock_enabled ? 'PIN protection is on' : 'Protect this app with a PIN' }}</p>
            </div>
            <span class="ml-auto badge {{ $settings->app_lock_enabled ? 'badge-income' : 'badge-muted' }}">{{ $settings->app_lock_enabled ? 'On' : 'Off' }}</span>
        </div>

        @if($settings->app_lock_enabled)
            <form method="POST" action="{{ route('lock.lock') }}" class="mb-2 border-t border-[var(--line)] pt-3">
                @csrf
                <button class="btn btn-secondary btn-sm w-full"><x-icon name="lock" class="h-3.5 w-3.5" /> Lock now</button>
            </form>
            <form method="POST" action="{{ route('settings.security') }}" class="space-y-2.5 border-t border-[var(--line)] pt-3">
                @csrf
                <input type="hidden" name="action" value="change">
                <div>
                    <label class="label" for="current_pin">Current PIN</label>
                    <input id="current_pin" type="password" name="current_pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="input" required autocomplete="current-password">
                </div>
                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="label" for="new_pin">New PIN</label>
                        <input id="new_pin" type="password" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="input" required autocomplete="new-password">
                    </div>
                    <div>
                        <label class="label" for="new_pin_confirmation">Confirm</label>
                        <input id="new_pin_confirmation" type="password" name="pin_confirmation" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="input" required autocomplete="new-password">
                    </div>
                </div>
                <button class="btn btn-secondary btn-sm w-full">Change PIN</button>
            </form>
            <form method="POST" action="{{ route('settings.security') }}" class="mt-2 border-t border-[var(--line)] pt-3" onsubmit="return confirm('Turn off app lock?')">
                @csrf
                <input type="hidden" name="action" value="disable">
                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <label class="label" for="disable_pin">PIN to disable</label>
                        <input id="disable_pin" type="password" name="current_pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="input h-10" required>
                    </div>
                    <button class="btn btn-ghost btn-sm h-10 text-[var(--expense)]">Turn off</button>
                </div>
            </form>
        @else
            <form method="POST" action="{{ route('settings.security') }}" class="space-y-2.5 border-t border-[var(--line)] pt-3">
                @csrf
                <input type="hidden" name="action" value="enable">
                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="label" for="enable_pin">Choose PIN</label>
                        <input id="enable_pin" type="password" name="pin" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="input" required autocomplete="new-password">
                    </div>
                    <div>
                        <label class="label" for="enable_pin_confirmation">Confirm PIN</label>
                        <input id="enable_pin_confirmation" type="password" name="pin_confirmation" inputmode="numeric" pattern="[0-9]*" maxlength="6" class="input" required autocomplete="new-password">
                    </div>
                </div>
                <p class="hint">4 to 6 digits. Five wrong attempts pause entry for one minute.</p>
                <button class="btn btn-secondary btn-sm w-full"><x-icon name="lock" class="h-3.5 w-3.5" /> Enable app lock</button>
            </form>
        @endif
    </section>

    <section class="card mt-4 p-4">
        <div class="mb-3 flex items-center gap-2">
            <span class="grid h-8 w-8 place-items-center rounded-xl bg-[var(--info-soft)] text-[var(--info)]"><x-icon name="database-backup" class="h-4 w-4" /></span>
            <div>
                <h2 class="text-[0.85rem] font-bold">Backup &amp; restore</h2>
                <p class="text-[0.63rem] text-[var(--muted)]">Everything stays on this device.</p>
            </div>
        </div>
        <a href="{{ route('settings.backup') }}" class="btn btn-secondary btn-sm w-full"><x-icon name="download" class="h-3.5 w-3.5" /> Download JSON backup</a>
        <form method="POST" action="{{ route('settings.restore') }}" enctype="multipart/form-data" class="mt-3 space-y-2.5 border-t border-[var(--line)] pt-3" onsubmit="return confirm('Restoring replaces all current data. Continue?')">
            @csrf
            <div>
                <label class="label" for="backup">Restore from backup</label>
                <input id="backup" type="file" name="backup" accept=".json,.txt,application/json" class="input py-2 text-[0.7rem]" required>
            </div>
            <button class="btn btn-ghost btn-sm w-full text-[var(--expense)]">Replace data with this backup</button>
        </form>
    </section>

    <section class="mt-4 px-1 pb-2 text-center">
        <p class="text-[0.62rem] font-semibold text-[var(--muted)]">SpendWise</p>
        <p class="mt-0.5 text-[0.56rem] text-[var(--muted)]">Version {{ config('nativephp.version', '1.0.0') }} · Offline-first</p>
        <p class="mt-1 text-[0.56rem] text-[var(--muted)]">Made for calmer money decisions.</p>
    </section>
</div>
@endsection
