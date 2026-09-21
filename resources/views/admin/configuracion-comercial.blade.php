@extends('layouts.app')
@section('content')
<div class="topbar">
    <div>
        <h1 class="title">Configuracion comercial</h1>
        <p class="context-title">Urbanizacion actual: {{ $urbanizacion?->nombre ?? 'Sin seleccionar' }}</p>
    </div>
</div>
@if (session('status')) <div class="status">{{ session('status') }}</div> @endif
@if ($errors->any()) <div class="errors">{{ $errors->first() }}</div> @endif
<form class="form card" method="POST" action="{{ route('admin.configuracion.update') }}">
    @csrf @method('PUT')
    <div class="field">
        <label>Dias habiles de reserva para asesores</label>
        <input type="number" min="1" max="30" name="reserva_dias_habiles_asesor" value="{{ old('reserva_dias_habiles_asesor', $reservaDiasHabilesAsesor) }}" required>
        <span class="muted">No cuenta sabados ni domingos.</span>
    </div>
    <div class="field">
        <label for="proteccion_cliente_dias">Días de protección comercial del cliente</label>
        <input id="proteccion_cliente_dias" type="number" min="1" max="365" name="proteccion_cliente_dias" value="{{ old('proteccion_cliente_dias', $proteccionClienteDias) }}" required>
        <span class="muted">Se aplica al registrar una persona por primera vez. No se renueva automáticamente.</span>
    </div>
    <div class="field">
        <label>Tipo de cambio USD a Bs</label>
        <input type="number" min="0" step="0.01" name="tipo_cambio_usd_bs" value="{{ old('tipo_cambio_usd_bs', $priceSettings['tipo_cambio_usd_bs']) }}" required>
    </div>
    <div class="field">
        <label>Tipo incremento credito</label>
        <select name="incremento_credito_tipo" required>
            @foreach(['monto' => 'Monto fijo', 'porcentaje' => 'Porcentaje'] as $value => $label)
                <option value="{{ $value }}" @selected(old('incremento_credito_tipo', $priceSettings['incremento_credito_tipo']) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Valor incremento credito</label>
        <input type="number" min="0" step="0.01" name="incremento_credito_valor" value="{{ old('incremento_credito_valor', $priceSettings['incremento_credito_valor']) }}" required>
    </div>
    <div class="field full">
        <h2 class="section-title">Calculadora credito</h2>
    </div>
    <div class="field">
        <label>Inicial minima USD</label>
        <input type="number" min="0" step="0.01" name="inicial_minima_usd" value="{{ old('inicial_minima_usd', $priceSettings['inicial_minima_usd']) }}" required>
    </div>
    <input type="hidden" name="plazo_12_habilitado" value="0">
    <label class="check-row"><input type="checkbox" name="plazo_12_habilitado" value="1" @checked(old('plazo_12_habilitado', $priceSettings['plazo_12_habilitado']))> Habilitar plazo 12 meses</label>
    <input type="hidden" name="plazo_24_habilitado" value="0">
    <label class="check-row"><input type="checkbox" name="plazo_24_habilitado" value="1" @checked(old('plazo_24_habilitado', $priceSettings['plazo_24_habilitado']))> Habilitar plazo 24 meses</label>
    <input type="hidden" name="plazo_36_habilitado" value="0">
    <label class="check-row"><input type="checkbox" name="plazo_36_habilitado" value="1" @checked(old('plazo_36_habilitado', $priceSettings['plazo_36_habilitado']))> Habilitar plazo 36 meses</label>

    <div class="field full">
        <h2 class="section-title">Calculadora semi contado</h2>
    </div>
    <input type="hidden" name="descuento_contado_activo" value="0">
    <label class="check-row"><input type="checkbox" name="descuento_contado_activo" value="1" @checked(old('descuento_contado_activo', $priceSettings['descuento_contado_activo']))> Activar descuento por compra al contado</label>
    <div class="field">
        <label>Tipo descuento contado</label>
        <select name="descuento_contado_tipo" required>
            @foreach(['monto' => 'Monto fijo', 'porcentaje' => 'Porcentaje'] as $value => $label)
                <option value="{{ $value }}" @selected(old('descuento_contado_tipo', $priceSettings['descuento_contado_tipo']) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Valor descuento contado</label>
        <input type="number" min="0" step="0.01" name="descuento_contado_valor" value="{{ old('descuento_contado_valor', $priceSettings['descuento_contado_valor']) }}" required>
    </div>

    <div class="field full">
        <h2 class="section-title">Promocion adicional</h2>
    </div>
    <input type="hidden" name="descuento_promo_activo" value="0">
    <label class="check-row"><input type="checkbox" name="descuento_promo_activo" value="1" @checked(old('descuento_promo_activo', $priceSettings['descuento_promo_activo']))> Activar promocion</label>
    <div class="field">
        <label>Tipo descuento promo</label>
        <select name="descuento_promo_tipo" required>
            @foreach(['monto' => 'Monto fijo', 'porcentaje' => 'Porcentaje'] as $value => $label)
                <option value="{{ $value }}" @selected(old('descuento_promo_tipo', $priceSettings['descuento_promo_tipo']) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="field">
        <label>Valor descuento promo</label>
        <input type="number" min="0" step="0.01" name="descuento_promo_valor" value="{{ old('descuento_promo_valor', $priceSettings['descuento_promo_valor']) }}" required>
    </div>
    <div class="field">
        <label>Nombre promocion</label>
        <input name="descuento_promo_nombre" value="{{ old('descuento_promo_nombre', $priceSettings['descuento_promo_nombre']) }}" maxlength="255">
    </div>
    <div class="field full">
        <label>Descripcion promocion</label>
        <textarea name="descuento_promo_descripcion" rows="3">{{ old('descuento_promo_descripcion', $priceSettings['descuento_promo_descripcion']) }}</textarea>
    </div>
    <div class="field full"><button class="btn">Guardar configuracion</button></div>
</form>
@endsection
