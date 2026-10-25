@props(['icon' => 'sparkles', 'title' => 'Nothing here yet', 'message' => null])
<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    <span class="empty-icon">
        <x-icon :name="$icon" class="h-6 w-6" />
    </span>
    <h3 class="text-[0.95rem] font-bold tracking-[-0.02em]">{{ $title }}</h3>
    @if($message)
        <p class="mt-1 max-w-xs text-[0.8rem] leading-relaxed text-[var(--muted)]">{{ $message }}</p>
    @endif
    @if(trim($slot))
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
