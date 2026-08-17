<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>body{font-family:DejaVu Sans,sans-serif;font-size:10px}table{width:100%;border-collapse:collapse}th,td{border:1px solid #ddd;padding:4px}th{background:#eef2f4}.header{margin-bottom:12px}.muted{color:#555}</style>
</head>
<body>
    <div class="header">
        @php($logo = ($settings['logo_pdf_path'] ?? '') ?: ($settings['logo_main_path'] ?? ''))
        @if($logo)<img src="{{ $logo }}" style="max-height:70px;" alt="Logo">@endif
        <h2>{{ $settings['system_name'] ?? 'IMPACTO URBANIZACIONES' }} - {{ $settings['system_subtitle'] ?? 'Sistema Integral de Terrenos' }}</h2>
        <div class="muted">Reporte mejor vendedor - {{ $ambitoLabel }} | {{ $mes }}/{{ $anio }}</div>
    </div>
    <table>
        <thead><tr><th>Ranking</th><th>Asesor</th><th>Supervisor</th><th>Reservas</th><th>Activas</th><th>Canceladas</th><th>Vencidas</th><th>Convertidas</th><th>Ventas</th><th>Monto</th><th>Conversion</th></tr></thead>
        <tbody>
        @foreach($ranking as $row)
            <tr><td>{{ $row['ranking'] }}</td><td>{{ $row['asesor'] }}</td><td>{{ $row['supervisor'] }}</td><td>{{ $row['reservas'] }}</td><td>{{ $row['activas'] }}</td><td>{{ $row['canceladas'] }}</td><td>{{ $row['vencidas'] }}</td><td>{{ $row['convertidas'] }}</td><td>{{ $row['ventas_cerradas'] }}</td><td>{{ number_format($row['monto_vendido'], 2) }}</td><td>{{ number_format($row['conversion'], 2) }}%</td></tr>
        @endforeach
        </tbody>
    </table>
</body>
</html>
