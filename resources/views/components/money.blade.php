@props(['amount', 'symbol' => true, 'class' => ''])
<span {{ $attributes->merge(['class' => 'money sensitive '.$class]) }}>{{ \App\Support\Money::format((int) $amount, null, $symbol) }}</span>
