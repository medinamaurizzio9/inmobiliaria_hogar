<meta charset="utf-8">
<h2>IMPACTO URBANIZACIONES - Reporte mejor vendedor</h2>
<p>Ambito: {{ $ambitoLabel }} | Periodo: {{ $mes }}/{{ $anio }}</p>
<table border="1">
    <thead><tr><th>Ranking</th><th>Asesor</th><th>Supervisor</th><th>Reservas</th><th>Activas</th><th>Canceladas</th><th>Vencidas</th><th>Convertidas</th><th>Ventas cerradas</th><th>Monto vendido</th><th>Conversion %</th></tr></thead>
    <tbody>
    @foreach($ranking as $row)
        <tr>
            <td>{{ $row['ranking'] }}</td>
            <td>{{ $row['asesor'] }}</td>
            <td>{{ $row['supervisor'] }}</td>
            <td>{{ $row['reservas'] }}</td>
            <td>{{ $row['activas'] }}</td>
            <td>{{ $row['canceladas'] }}</td>
            <td>{{ $row['vencidas'] }}</td>
            <td>{{ $row['convertidas'] }}</td>
            <td>{{ $row['ventas_cerradas'] }}</td>
            <td>{{ $row['monto_vendido'] }}</td>
            <td>{{ $row['conversion'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
