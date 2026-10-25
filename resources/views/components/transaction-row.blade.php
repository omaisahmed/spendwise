@props(['transaction'])
@php
    $isIncome = $transaction->type === \App\Models\Transaction::TYPE_INCOME;
    $isTransfer = $transaction->type === \App\Models\Transaction::TYPE_TRANSFER;
    $color = $transaction->category?->color ?? ($isTransfer ? '#4395F7' : '#788396');
    $icon = $transaction->category?->icon ?? ($isTransfer ? 'arrow-left-right' : 'circle');
@endphp
<a href="{{ route('transactions.edit', $transaction) }}" class="flex items-center gap-3 py-2.5" aria-label="Edit {{ $transaction->displayTitle() }}">
    <span class="icon-bubble" style="color: {{ $color }}; background: color-mix(in srgb, {{ $color }} 13%, var(--surface));">
        <x-icon :name="$icon" class="h-[19px] w-[19px]" />
    </span>
    <span class="min-w-0 flex-1">
        <span class="flex items-center gap-1.5">
            <span class="truncate text-[0.84rem] font-semibold">{{ $transaction->displayTitle() }}</span>
            @if($transaction->is_refund)
                <span class="badge badge-muted">Refund</span>
            @endif
        </span>
        <span class="mt-0.5 block truncate text-[0.69rem] text-[var(--muted)]">
            {{ $transaction->category?->name ?? ($isTransfer ? 'Transfer' : 'Uncategorised') }}
            · {{ $transaction->occurred_at->format('d M, H:i') }}
            @if($transaction->account)
                · {{ $transaction->account->name }}
            @endif
        </span>
    </span>
    <span class="money sensitive shrink-0 text-right text-[0.86rem] font-bold {{ $isIncome ? 'income' : ($isTransfer ? '' : 'expense') }}">
        {{ $isIncome ? '+' : ($isTransfer ? '' : ($transaction->is_refund ? '+' : '−')) }}{{ \App\Support\Money::format($transaction->amount_minor, null, false) }}
    </span>
</a>
