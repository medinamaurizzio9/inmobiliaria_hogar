<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>body{font-family:DejaVu Sans,sans-serif;font-size:11px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:5px}th{background:#eef2f4}.header{margin-bottom:12px}.muted{color:#555}</style>
</head>
<body>
    <div class="header">
        @php($logo = ($settings['logo_pdf_path'] ?? '') ?: ($settings['logo_main_path'] ?? ''))
        @if($logo)<img src="{{ $logo }}" style="max-height:70px;" alt="Logo">@endif
        <h2>{{ $settings['system_name'] ?? 'IMPACTO URBANIZACIONES' }} - {{ $settings['system_subtitle'] ?? 'Sistema Integral de Terrenos' }}</h2>
        <div class="muted">Reporte de reservas {{ $urbanizacion?->nombre ? ' - '.$urbanizacion->nombre : '' }}</div>
    </div>
    <table>
        <thead><tr><th>Fecha</th><th>Cliente</th><th>Documento</th><th>Manzano</th><th>Lote</th><th>Tipo</th><th>Estado</th><th>Asesor</th><th>Vence</th></tr></thead>
        <tbody>
        @foreach($reservas as $reserva)
            <tr><td>{{ $reserva->fecha_reserva?->format('d/m/Y') }}</td><td>{{ $reserva->cliente->nombre }}</td><td>{{ $reserva->cliente->documento }}</td><td>{{ $reserva->lote->manzano->codigo }}</td><td>{{ $reserva->lote->codigo }}</td><td>{{ $reserva->tipo_operacion }}</td><td>{{ $reserva->estado }}</td><td>{{ $reserva->usuario?->name }}</td><td>{{ $reserva->fecha_vencimiento?->format('d/m/Y') }}</td></tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
