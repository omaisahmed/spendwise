@extends('layouts.app')

@section('title', 'Notifications')

@section('content')
@php
    $tones = [
        'info' => ['brand', 'info'],
        'reminder' => ['warning', 'bell'],
        'recurring' => ['warning', 'repeat'],
        'budget' => ['expense', 'triangle-alert'],
        'savings' => ['income', 'piggy-bank'],
        'success' => ['income', 'circle-check'],
    ];
@endphp

<div class="stagger">
    <section class="mb-5 flex items-start justify-between gap-3">
        <div>
            <a href="{{ route('settings.index') }}" class="mb-2 inline-flex items-center gap-1 text-[0.7rem] font-semibold text-[var(--muted)]">
                <x-icon name="arrow-left" class="h-3.5 w-3.5" /> More
            </a>
            <h1 class="page-title">Notifications</h1>
            <p class="page-subtitle">{{ $unreadCount > 0 ? $unreadCount.' unread update'.($unreadCount === 1 ? '' : 's') : 'You are all caught up.' }}</p>
        </div>
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}" class="mt-2">
                @csrf
                <button class="btn btn-secondary btn-sm">Mark all read</button>
            </form>
        @endif
    </section>

    @if($notifications->isEmpty())
        <x-empty-state icon="bell" title="No notifications" message="Budget alerts, recurring reminders and useful updates will appear here." />
    @else
        <div class="space-y-2">
            @foreach($notifications as $notification)
                @php
                    [$tone, $icon] = $tones[$notification->type] ?? ['brand', 'bell'];
                    $isUnread = $notification->read_at === null;
                @endphp
                <article class="card p-3.5 {{ $isUnread ? 'border-[color-mix(in_srgb,var(--brand)_24%,var(--line))]' : 'opacity-75' }}">
                    <div class="flex items-start gap-3">
                        <span class="icon-bubble h-9 w-9 flex-[0_0_36px] rounded-xl bg-[var(--{{ $tone }}-soft)] text-[var(--{{ $tone }})]">
                            <x-icon :name="$icon" class="h-4 w-4" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-start gap-2">
                                <h2 class="min-w-0 flex-1 text-[0.78rem] font-semibold leading-snug">{{ $notification->title }}</h2>
                                @if($isUnread)<span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-[var(--brand)]"></span>@endif
                            </div>
                            <p class="mt-1 text-[0.68rem] leading-relaxed text-[var(--muted)]">{{ $notification->body }}</p>
                            <div class="mt-2 flex items-center gap-3">
                                <span class="text-[0.58rem] text-[var(--muted)]">{{ $notification->created_at->diffForHumans() }}</span>
                                @if(!empty($notification->data['url']))
                                    <form method="POST" action="{{ route('notifications.open', $notification) }}">
                                        @csrf
                                        <button class="text-[0.6rem] font-semibold text-[var(--brand)]">Open</button>
                                    </form>
                                @endif
                                @if($isUnread)
                                    <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="text-[0.6rem] font-semibold text-[var(--brand)]">Mark read</button>
                                    </form>
                                @endif
                                <form method="POST" action="{{ route('notifications.destroy', $notification) }}" class="ml-auto">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-[var(--muted)]" aria-label="Delete notification"><x-icon name="trash-2" class="h-3.5 w-3.5" /></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        @if($notifications->hasPages())
            <nav class="pagination" aria-label="Notification pages">
                @if($notifications->onFirstPage())
                    <span class="is-disabled"><x-icon name="chevron-left" class="h-4 w-4" /></span>
                @else
                    <a href="{{ $notifications->previousPageUrl() }}" aria-label="Previous page"><x-icon name="chevron-left" class="h-4 w-4" /></a>
                @endif
                <span class="is-active">{{ $notifications->currentPage() }}</span>
                <span class="is-disabled">of {{ $notifications->lastPage() }}</span>
                @if($notifications->hasMorePages())
                    <a href="{{ $notifications->nextPageUrl() }}" aria-label="Next page"><x-icon name="chevron-right" class="h-4 w-4" /></a>
                @else
                    <span class="is-disabled"><x-icon name="chevron-right" class="h-4 w-4" /></span>
                @endif
            </nav>
        @endif
    @endif
</div>
@endsection
