@extends('layouts.app')
@section('content')
<div class="topbar"><div><h1 class="title">Configuración financiera</h1><p class="muted">Medios de pago, cobranza, mora y límites de financiamiento.</p></div></div>
@if(session('status')) <div class="status">{{ session('status') }}</div> @endif
@if($errors->any()) <div class="errors">{{ $errors->first() }}</div> @endif

<form class="form card" method="POST" action="{{ route('admin.configuracion-financiera.update') }}" enctype="multipart/form-data">
    @csrf @method('PUT')
    <fieldset @disabled(!$canEdit) style="display:contents">
        <div class="field full"><h2 class="section-title">A. Medios de pago</h2></div>
        <div class="field">
            <label>Imagen QR institucional</label>
            <input type="file" name="qr_institucional_imagen" accept="image/png,image/jpeg,image/webp">
            @if($settings['qr_institucional_url'])<img src="{{ $settings['qr_institucional_url'] }}" alt="QR institucional" style="display:block;max-width:180px;max-height:180px;margin-top:10px">@endif
        </div>
        <div class="field"><label>Nombre o descripción del QR</label><input name="qr_institucional_nombre" maxlength="255" value="{{ old('qr_institucional_nombre', $settings['qr_institucional_nombre']) }}"></div>
        <input type="hidden" name="qr_institucional_activo" value="0">
        <label class="check-row"><input type="checkbox" name="qr_institucional_activo" value="1" @checked(old('qr_institucional_activo', $settings['qr_institucional_activo']))> QR activo</label>

        <div class="field"><label>Banco</label><input name="banco_nombre" maxlength="150" value="{{ old('banco_nombre', $settings['banco_nombre']) }}"></div>
        <div class="field"><label>Titular</label><input name="banco_titular" maxlength="200" value="{{ old('banco_titular', $settings['banco_titular']) }}"></div>
        <div class="field"><label>Número de cuenta</label><input name="banco_numero_cuenta" maxlength="100" value="{{ old('banco_numero_cuenta', $settings['banco_numero_cuenta']) }}"></div>
        <div class="field"><label>Tipo de cuenta</label><input name="banco_tipo_cuenta" maxlength="100" value="{{ old('banco_tipo_cuenta', $settings['banco_tipo_cuenta']) }}"></div>
        <div class="field"><label>Moneda</label><select name="banco_moneda">@foreach(['BOB' => 'Bolivianos (BOB)', 'USD' => 'Dólares (USD)'] as $value => $label)<option value="{{ $value }}" @selected(old('banco_moneda', $settings['banco_moneda']) === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="field full"><label>Instrucciones adicionales</label><textarea name="banco_instrucciones" maxlength="1000">{{ old('banco_instrucciones', $settings['banco_instrucciones']) }}</textarea></div>
        <input type="hidden" name="banco_activo" value="0">
        <label class="check-row"><input type="checkbox" name="banco_activo" value="1" @checked(old('banco_activo', $settings['banco_activo']))> Cuenta bancaria activa</label>

        <div class="field full"><h2 class="section-title">B. Cobranza</h2></div>
        <div class="field"><label>Días de aviso antes del vencimiento</label><input type="number" min="0" max="30" name="dias_aviso_vencimiento" value="{{ old('dias_aviso_vencimiento', $settings['dias_aviso_vencimiento']) }}" required></div>

        <div class="field full"><h2 class="section-title">C. Mora</h2><p class="muted">La configuración no aplica recargos automáticamente. La fórmula queda pendiente de aprobación.</p></div>
        <input type="hidden" name="mora_habilitada" value="0">
        <label class="check-row"><input type="checkbox" name="mora_habilitada" value="1" @checked(old('mora_habilitada', $settings['mora_habilitada']))> Mora habilitada</label>
        <div class="field"><label>Tipo de mora</label><select name="tipo_mora"><option value="">Sin definir</option>@foreach(['monto_fijo' => 'Monto fijo', 'porcentaje' => 'Porcentaje'] as $value => $label)<option value="{{ $value }}" @selected(old('tipo_mora', $settings['tipo_mora']) === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="field"><label>Valor de mora</label><input type="number" min="0" step="0.01" name="valor_mora" value="{{ old('valor_mora', $settings['valor_mora']) }}"></div>
        <div class="field"><label>Días de gracia</label><input type="number" min="0" max="365" name="dias_gracia" value="{{ old('dias_gracia', $settings['dias_gracia']) }}"></div>

        <div class="field full"><h2 class="section-title">D. Financiamiento</h2></div>
        <div class="field"><label>Máximo cuotas semicontado</label><input type="number" min="1" max="120" name="max_cuotas_semicontado" value="{{ old('max_cuotas_semicontado', $settings['max_cuotas_semicontado']) }}" required></div>
        <div class="field"><label>Máximo cuotas crédito</label><input type="number" min="1" max="120" name="max_cuotas_credito" value="{{ old('max_cuotas_credito', $settings['max_cuotas_credito']) }}" required></div>
    </fieldset>
    @if($canEdit)<div class="field full"><button class="btn">Guardar configuración financiera</button></div>@else<div class="field full"><p class="muted">Vista de solo lectura para gerencia.</p></div>@endif
</form>
@endsection
