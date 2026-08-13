@props(['label', 'value', 'icon' => 'fa-chart-line', 'tone' => 'olive', 'hint' => null])
<article {{ $attributes->class(['crm-kpi', 'tone-'.$tone]) }}>
    <span class="crm-kpi-icon"><i class="fa-solid {{ $icon }}"></i></span>
    <div><span class="crm-kpi-label">{{ $label }}</span><strong>{{ $value }}</strong>@if($hint)<small>{{ $hint }}</small>@endif</div>
</article>
