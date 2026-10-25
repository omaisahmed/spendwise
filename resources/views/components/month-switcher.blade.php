@props(['route', 'month', 'label', 'previous', 'next', 'canNext' => true, 'params' => []])
<div class="month-switcher" aria-label="Period navigation">
    <a href="{{ route($route, array_merge($params, ['month' => $previous])) }}" aria-label="Previous month">
        <x-icon name="chevron-left" class="h-4 w-4" />
    </a>
    <span class="w-auto min-w-24 px-2 text-[0.72rem] font-semibold text-[var(--ink-soft)]">{{ $label }}</span>
    <a href="{{ $canNext ? route($route, array_merge($params, ['month' => $next])) : '#' }}"
       class="{{ $canNext ? '' : 'is-disabled' }}"
       aria-label="Next month">
        <x-icon name="chevron-right" class="h-4 w-4" />
    </a>
</div>
