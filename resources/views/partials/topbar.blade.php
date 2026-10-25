@php
    $unreadNotifications = \App\Models\AppNotification::query()->whereNull('read_at')->count();
    $settings = \App\Support\AppSettings::get();
@endphp
<header class="topbar">
    <a href="{{ route('dashboard') }}" class="flex min-w-0 items-center gap-2.5" aria-label="SpendWise home">
        <span class="brand-mark">
            <x-icon name="wallet-minimal" class="h-5 w-5" />
        </span>
        <span class="min-w-0">
            <span class="block truncate text-[0.95rem] font-bold tracking-[-0.03em]">SpendWise</span>
            <span class="block truncate text-[0.66rem] font-medium text-[var(--muted)]">
                {{ $settings->display_name ? 'Hi, '.$settings->display_name : 'Private by design' }}
            </span>
        </span>
    </a>

    <div class="flex items-center gap-1.5">
        <button type="button" class="icon-btn" data-theme-toggle onclick="toggleTheme()" aria-label="Toggle theme">
            <x-icon name="sun-moon" class="h-[18px] w-[18px]" />
        </button>
        <button type="button" class="icon-btn" data-privacy-toggle onclick="togglePrivacy()" aria-label="Hide amounts">
            <x-icon name="eye" class="h-[18px] w-[18px]" />
        </button>
        <a href="{{ route('notifications.index') }}" class="icon-btn relative" aria-label="Notifications">
            <x-icon name="bell" class="h-[18px] w-[18px]" />
            @if($unreadNotifications > 0)
                <span class="absolute right-2 top-2 grid min-w-4 place-items-center rounded-full bg-[var(--expense)] px-1 text-[0.55rem] font-bold leading-4 text-white">
                    {{ $unreadNotifications > 9 ? '9+' : $unreadNotifications }}
                </span>
            @endif
        </a>
    </div>
</header>
