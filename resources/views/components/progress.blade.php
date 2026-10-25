@props(['value' => 0, 'tone' => 'brand'])
@php
    $width = max(0, min(100, (int) $value));
    $toneClass = match($tone) {
        'income' => 'progress-income',
        'expense' => 'progress-expense',
        'warning' => 'progress-warning',
        default => '',
    };
@endphp
<div {{ $attributes->merge(['class' => 'progress '.$toneClass]) }} role="progressbar" aria-valuenow="{{ $width }}" aria-valuemin="0" aria-valuemax="100">
    <span style="width: {{ $width }}%"></span>
</div>
