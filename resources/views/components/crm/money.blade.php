@props(['value'])
<span {{ $attributes->class('crm-money') }}>Bs {{ number_format((float) $value, 2, ',', '.') }}</span>
