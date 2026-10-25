@php
    $moreActive = request()->routeIs('settings.*', 'accounts.*', 'categories.*', 'savings.*', 'recurring.*', 'reports.*', 'notifications.*');
@endphp
<nav class="nav-wrap" aria-label="Main navigation">
    <div class="bottom-nav">
        <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">
            <x-icon name="house" class="h-[19px] w-[19px]" />
            <span>Home</span>
        </a>
        <a href="{{ route('transactions.index') }}" class="nav-item {{ request()->routeIs('transactions.*', 'transfers.*') ? 'is-active' : '' }}">
            <x-icon name="arrow-left-right" class="h-[19px] w-[19px]" />
            <span>Activity</span>
        </a>
        <a href="{{ route('transactions.create') }}" class="nav-add" aria-label="Add transaction">
            <x-icon name="plus" class="h-6 w-6" />
        </a>
        <a href="{{ route('insights.index') }}" class="nav-item {{ request()->routeIs('insights.*') ? 'is-active' : '' }}">
            <x-icon name="chart-no-axes-combined" class="h-[19px] w-[19px]" />
            <span>Insights</span>
        </a>
        <a href="{{ route('settings.index') }}" class="nav-item {{ $moreActive ? 'is-active' : '' }}">
            <x-icon name="layout-grid" class="h-[19px] w-[19px]" />
            <span>More</span>
        </a>
    </div>
</nav>
