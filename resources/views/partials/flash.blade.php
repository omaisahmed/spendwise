@php
    $flashMessage = session('success') ?? session('error') ?? session('info');
    $flashTone = session('error') ? 'alert-danger' : (session('info') ? 'alert-info' : 'alert-success');
    $flashIcon = session('error') ? 'circle-alert' : (session('info') ? 'info' : 'circle-check');
@endphp
@if($flashMessage)
    <div
        class="flash-wrap"
        x-data="{ show: true }"
        x-init="setTimeout(() => show = false, 4200)"
        x-show="show"
        x-transition.opacity
        role="status"
        aria-live="polite"
    >
        <div class="flash {{ $flashTone }}">
            <x-icon :name="$flashIcon" class="mt-0.5 h-4 w-4 shrink-0" />
            <div class="min-w-0 flex-1">{{ $flashMessage }}</div>
            <button type="button" class="shrink-0 opacity-60 hover:opacity-100" @click="show = false" aria-label="Dismiss">
                <x-icon name="x" class="h-4 w-4" />
            </button>
        </div>
    </div>
@endif
