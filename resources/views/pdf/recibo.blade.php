<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #17202a; line-height: 1.45; }
        .header { display: table; width: 100%; padding-bottom: 12px; border-bottom: 4px solid {{ $settings['secondary_color'] ?? '#0f2530' }}; }
        .logo, .company, .receipt-number { display: table-cell; vertical-align: middle; }
        .logo { width: 125px; vertical-align: middle; }
        .logo img { display: block; max-width: 108px; max-height: 102px; margin: 0 auto; }
        .company { text-align: center; }
        .company-name { color: {{ $settings['primary_color'] ?? '#0f766e' }}; font-size: 21px; font-weight: bold; }
        .system-name { margin-top: 3px; font-size: 13px; font-weight: bold; }
        .company-detail { margin-top: 3px; color: #5d6a70; font-size: 9px; }
        .receipt-number { width: 130px; text-align: right; }
        .receipt-number span { display: inline-block; padding: 10px; border: 2px solid {{ $settings['primary_color'] ?? '#0f766e' }}; border-radius: 5px; color: {{ $settings['primary_color'] ?? '#0f766e' }}; font-size: 13px; font-weight: bold; }
        .accent-line { height: 2px; margin-bottom: 20px; background: {{ $settings['primary_color'] ?? '#0f766e' }}; }
        .document-title { margin: 0 0 14px; color: {{ $settings['primary_color'] ?? '#0f766e' }}; font-size: 18px; text-align: center; text-transform: uppercase; }
        .details { width: 100%; border-collapse: separate; border-spacing: 0; border: 1px solid #dce4e8; border-radius: 5px; overflow: hidden; }
        .details td { padding: 7px 9px; border-bottom: 1px solid #e8edf0; }
        .details tr:last-child td { border-bottom: 0; }
        .details .label { width: 25%; background: #f3f6f7; color: #4d5d64; font-weight: bold; }
        .amount-box { margin: 16px 0; padding: 13px; border-left: 5px solid {{ $settings['primary_color'] ?? '#0f766e' }}; background: #f3f8f7; text-align: center; }
        .amount-label { color: #5d6a70; font-size: 10px; text-transform: uppercase; }
        .amount { margin-top: 2px; color: {{ $settings['primary_color'] ?? '#0f766e' }}; font-size: 23px; font-weight: bold; }
        .bottom-block { width: 100%; margin-top: 18px; border-collapse: separate; border-spacing: 0; border: 1px solid #cbd9d6; background: #f7faf9; }
        .conditions { width: 76%; padding: 12px 14px; border-left: 5px solid {{ $settings['secondary_color'] ?? '#0f2530' }}; vertical-align: top; }
        .conditions-title { margin-bottom: 7px; color: {{ $settings['secondary_color'] ?? '#0f2530' }}; font-size: 12px; font-weight: bold; }
        .conditions p { margin: 0 0 8px; text-align: justify; }
        .conditions p:last-child { margin-bottom: 0; }
        .applications { width: 100%; margin-top: 16px; border-collapse: separate; border-spacing: 0; border: 1px solid #dce4e8; border-radius: 5px; overflow: hidden; }
        .applications th { background: #f3f6f7; color: #4d5d64; }
        .applications th, .applications td { padding: 6px 9px; border-bottom: 1px solid #e8edf0; text-align: left; }
        .applications tr:last-child td { border-bottom: 0; }
        .applications .num { width: 10%; }
        .applications .date { width: 30%; }
        .applications .total-label { font-weight: bold; }
        .qr-cell { width: 24%; padding: 10px; border-left: 1px solid #cbd9d6; text-align: center; vertical-align: middle; }
        .qr-cell img { display: block; width: 105px; height: 105px; margin: 0 auto 6px; }
        .qr-label { color: {{ $settings['secondary_color'] ?? '#0f2530' }}; font-size: 8px; font-weight: bold; }
        .qr-only { width: 100%; margin-top: 18px; text-align: center; }
        .footer { position: fixed; right: 34px; bottom: 22px; left: 34px; padding-top: 8px; border-top: 1px solid #dce4e8; color: #637177; font-size: 9px; text-align: center; }
    </style>
</head>
<body>
@php
    $logo = ($settings['logo_pdf'] ?? '') ?: ($settings['logo_main'] ?? null);
    $conceptoNormalizado = strtolower(str_replace('_', ' ', (string) $movimiento->concepto));
    $muestraCondiciones = in_array($conceptoNormalizado, ['reserva', 'anticipo', 'cuota inicial'], true);
@endphp
<div class="header">
    <div class="logo">@if($logo)<img src="{{ public_path('storage/'.$logo) }}" alt="Logo">@endif</div>
    <div class="company">
        <div class="company-name">{{ $settings['company_name'] ?? $settings['system_name'] ?? 'IMPACTO URBANIZACIONES' }}</div>
        <div class="system-name">{{ $settings['system_name'] ?? 'IMPACTO URBANIZACIONES' }} - {{ $settings['system_subtitle'] ?? 'Sistema Integral de Terrenos' }}</div>
        <div class="company-detail">{{ $settings['razon_social'] ?? '' }} {{ !empty($settings['nit']) ? '| NIT: '.$settings['nit'] : '' }}</div>
        <div class="company-detail">{{ $settings['direccion'] ?? '' }} {{ !empty($settings['telefono']) ? '| Tel: '.$settings['telefono'] : '' }} {{ !empty($settings['email']) ? '| '.$settings['email'] : '' }}</div>
    </div>
    <div class="receipt-number"><span>RECIBO<br>N.º {{ $numeroRecibo ?? str_pad((string) $movimiento->id, 8, '0', STR_PAD_LEFT) }}</span></div>
</div>
<div class="accent-line"></div>

<h1 class="document-title">Comprobante de pago</h1>
<table class="details">
    <tr><td class="label">Fecha y hora</td><td>{{ $movimiento->created_at?->format('d/m/Y H:i') ?? $movimiento->fecha?->format('d/m/Y') }}</td></tr>
    <tr><td class="label">Cliente</td><td>{{ $movimiento->cliente?->nombre ?? 'Sin cliente registrado' }}</td></tr>
    <tr><td class="label">Documento</td><td>{{ $movimiento->cliente?->documento ?? 'Sin documento' }}</td></tr>
    <tr><td class="label">Urbanización</td><td>{{ $lote?->manzano?->urbanizacion?->nombre ?? 'No aplica' }}</td></tr>
    <tr><td class="label">Manzano / Lote</td><td>{{ $lote ? $lote->manzano->codigo.' / '.$lote->codigo : 'No aplica' }}</td></tr>
    <tr><td class="label">Concepto</td><td>{{ ucfirst($conceptoNormalizado) }}</td></tr>
    @isset($reserva)
    <tr><td class="label">Fecha de reserva</td><td>{{ $reserva->fecha_reserva?->format('d/m/Y') }}</td></tr>
    <tr><td class="label">Fecha de vencimiento</td><td>{{ $reserva->fecha_vencimiento?->format('d/m/Y') }}</td></tr>
    @endisset
    <tr><td class="label">Método de pago</td><td>{{ ucfirst($movimiento->metodo_pago) }}</td></tr>
    <tr><td class="label">Referencia</td><td>{{ $movimiento->referencia ?: 'Sin referencia' }}</td></tr>
    <tr><td class="label">Asesor que registró</td><td>{{ $movimiento->user?->name ?? 'Usuario no registrado' }}</td></tr>
    <tr><td class="label">Estado</td><td>{{ ucfirst($movimiento->estado) }}</td></tr>
</table>

<div class="amount-box">
    <div class="amount-label">Monto recibido</div>
    <div class="amount">Bs {{ number_format($movimiento->monto, 2) }}</div>
</div>

@if($movimiento->pagoAplicaciones->isNotEmpty())
<table class="applications">
    <thead>
        <tr>
            <th class="num">Cuota</th>
            <th class="date">Vencimiento</th>
            <th>Monto aplicado</th>
        </tr>
    </thead>
    <tbody>
        @php($totalAplicado = 0)
        @foreach($movimiento->pagoAplicaciones as $aplicacion)
            <tr>
                <td>Cuota {{ $aplicacion->cuota?->numero ?? '-' }}</td>
                <td>{{ $aplicacion->cuota?->fecha_vencimiento?->format('d/m/Y') ?? '-' }}</td>
                <td>Bs {{ number_format((float) $aplicacion->monto_aplicado, 2) }}</td>
            </tr>
            @php($totalAplicado += (float) $aplicacion->monto_aplicado)
        @endforeach
        <tr>
            <td colspan="2" class="total-label">Total aplicado</td>
            <td class="total-label">Bs {{ number_format($totalAplicado, 2) }}</td>
        </tr>
    </tbody>
</table>
@endif

@if(isset($saldoRestanteTerreno) && $saldoRestanteTerreno > 0)
<div class="amount-box" style="border-left-color: {{ $settings['secondary_color'] ?? '#0f2530' }}; background: #f7faf9;">
    <div class="amount-label">Saldo pendiente del terreno</div>
    <div class="amount" style="color: {{ $settings['secondary_color'] ?? '#0f2530' }}; font-size: 16px;">Bs {{ number_format($saldoRestanteTerreno, 2) }}</div>
</div>
@endif

@if($muestraCondiciones)
<table class="bottom-block"><tr>
    <td class="conditions">
        <div class="conditions-title">CONDICIONES DE RESERVA</div>
        <p>La reserva tiene una vigencia máxima de 5 días hábiles. Si el cliente no concreta la venta, no completa el pago total o no cubre el saldo de la cuota inicial dentro de este plazo, la reserva quedará sin efecto y el monto entregado por concepto de reserva se perderá sin derecho a reclamo.</p>
        <p>Para reservas mayores a Bs 100, el pago deberá realizarse directamente a la cuenta bancaria del propietario o titular autorizado de la urbanización. El asesor comercial proporcionará los datos correspondientes para el depósito o transferencia.</p>
    </td>
    <td class="qr-cell"><img src="{{ $qrDataUri }}" alt="QR institucional"><div class="qr-label">Comprobante generado por el sistema</div></td>
</tr></table>
@else
<div class="qr-only"><img src="{{ $qrDataUri }}" width="105" height="105" alt="QR institucional"><div class="qr-label">Comprobante generado por el sistema</div></div>
@endif

<div class="footer">{{ $settings['footer_text'] ?? 'Versión piloto - MVP funcional.' }}</div>
</body>
</html>
