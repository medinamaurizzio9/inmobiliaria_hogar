<!doctype html><html><head><meta charset="utf-8"><style>body{font-family:DejaVu Sans,sans-serif;font-size:12px;line-height:1.55}.header{border-bottom:2px solid {{ $settings['primary_color'] ?? '#111' }};margin-bottom:18px;padding-bottom:10px}.title{font-size:18px;font-weight:bold}.logo{max-height:70px}.footer{border-top:1px solid #ddd;margin-top:20px;padding-top:8px;text-align:center;color:#555}</style></head><body>
@php($logo = $settings['logo_pdf_path'] ?: ($settings['logo_main_path'] ?? null))
<div class="header">@if($logo)<img class="logo" src="{{ $logo }}" alt="Logo">@endif<div class="title">{{ $settings['system_name'] ?? 'IMPACTO URBANIZACIONES' }} - {{ $settings['system_subtitle'] ?? 'Sistema Integral de Terrenos' }}</div><p>Contrato basico de compra venta</p></div>
@php($saldo = max(0, $venta->precio_final - $venta->cuota_inicial - $venta->cuotas->sum('monto_pagado')))
<p>Conste por el presente documento privado que {{ $settings['razon_social'] ?: ($settings['company_name'] ?? 'IMPACTO URBANIZACIONES') }} transfiere en calidad de compra venta el lote {{ $venta->lote->manzano->codigo }}-{{ $venta->lote->codigo }} ubicado en {{ $venta->lote->manzano->urbanizacion->nombre }}, {{ $venta->lote->manzano->urbanizacion->ubicacion }}.</p>
<p><strong>Comprador:</strong> {{ $venta->cliente->nombre }}<br><strong>Documento:</strong> {{ $venta->cliente->documento }}<br><strong>Telefono:</strong> {{ $venta->cliente->telefono }}<br><strong>Email:</strong> {{ $venta->cliente->email }}<br><strong>Direccion:</strong> {{ $venta->cliente->direccion }}</p>
<p><strong>Datos del lote:</strong> superficie {{ number_format($venta->lote->superficie, 2) }} m2, manzano {{ $venta->lote->manzano->codigo }}, lote {{ $venta->lote->codigo }}.</p>
<p>El precio pactado es de {{ number_format($venta->precio_final, 2) }}, con anticipo de {{ number_format($venta->cuota_inicial, 2) }} y saldo actual de {{ number_format($saldo, 2) }} sujeto al plan de pagos asociado.</p>
<p>Ambas partes declaran su conformidad con las condiciones registradas en {{ $settings['system_subtitle'] ?? 'Sistema Integral de Terrenos' }}.</p>
<br><br><p>_________________________<br>{{ $settings['company_name'] ?? 'IMPACTO URBANIZACIONES' }}</p><br><p>_________________________<br>{{ $venta->cliente->nombre }}</p>
<div class="footer">{{ $settings['footer_text'] ?? '' }}</div>
</body></html>
