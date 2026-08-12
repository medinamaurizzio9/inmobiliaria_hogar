<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificacion de recibo - {{ $settings['system_name'] ?? 'INMOLIDER CRM' }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body>
<main class="public-page receipt-verification-page">
    <section class="card receipt-verification-card">
        <header class="receipt-public-header">
            @if(!empty($settings['logo_main']))
                <img src="{{ asset('storage/'.$settings['logo_main']) }}" alt="Logo">
            @endif
            <div>
                <h1>{{ $settings['system_name'] ?? 'INMOLIDER CRM' }}</h1>
                <p>{{ $settings['system_subtitle'] ?? 'Sistema Integral de Terrenos' }}</p>
            </div>
        </header>

        @if(! $movimiento)
            <div class="receipt-status receipt-status-missing">RECIBO NO ENCONTRADO</div>
            <p class="muted">No existe un recibo registrado con el numero {{ $numero }}.</p>
        @else
            @php
                $estado = $movimiento->estado;
                $esValido = $estado === 'confirmado';
                $esAnulado = $estado === 'anulado';
                $esPendiente = $estado === 'pendiente_verificacion';
                $esRechazado = $estado === 'rechazado';
            @endphp
            <div @class([
                'receipt-status',
                'receipt-status-valid' => $esValido,
                'receipt-status-annulled' => $esAnulado,
                'receipt-status-pending' => $esPendiente,
                'receipt-status-rejected' => $esRechazado,
            ])>
                {{ match (true) {
                    $esAnulado => 'RECIBO ANULADO',
                    $esPendiente => 'PAGO EN VERIFICACIÓN',
                    $esRechazado => 'PAGO RECHAZADO',
                    default => 'RECIBO VÁLIDO',
                } }}
            </div>

            <dl class="receipt-public-details">
                <div><dt>Numero recibo</dt><dd>{{ $numero }}</dd></div>
                <div><dt>Cliente</dt><dd>{{ $movimiento->cliente?->nombre ?? 'No registrado' }}</dd></div>
                <div><dt>Documento</dt><dd>{{ $documento }}</dd></div>
                <div><dt>Urbanizacion</dt><dd>{{ $lote?->manzano?->urbanizacion?->nombre ?? 'No aplica' }}</dd></div>
                <div><dt>Manzano / lote</dt><dd>{{ $lote ? $lote->manzano->codigo.' / '.$lote->codigo : 'No aplica' }}</dd></div>
                <div><dt>Monto</dt><dd>Bs {{ number_format((float) $movimiento->monto, 2) }}</dd></div>
                <div><dt>Fecha</dt><dd>{{ $movimiento->fecha?->format('d/m/Y') ?? $movimiento->created_at?->format('d/m/Y') }}</dd></div>
                <div><dt>Estado</dt><dd>{{ ucfirst($movimiento->estado) }}</dd></div>
            </dl>
        @endif
    </section>
</main>
</body>
</html>
