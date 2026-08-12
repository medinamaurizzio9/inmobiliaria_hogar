<?php

namespace Database\Seeders;

use App\Models\Asesor;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Cliente;
use App\Models\Cuota;
use App\Models\GrupoComercial;
use App\Models\Lote;
use App\Models\LotHistory;
use App\Models\Manzano;
use App\Models\Reserva;
use App\Models\SupervisorProfile;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'ver dashboard',
            'ver lotes',
            'ver clientes',
            'ver ventas',
            'ver reservas',
            'crear urbanizaciones',
            'editar urbanizaciones',
            'eliminar urbanizaciones',
            'crear manzanos',
            'editar manzanos',
            'eliminar manzanos',
            'crear lotes',
            'editar lotes',
            'eliminar lotes',
            'crear clientes',
            'editar clientes',
            'eliminar clientes',
            'crear ventas',
            'editar ventas',
            'editar ventas anuladas',
            'anular ventas',
            'crear reservas',
            'editar reservas',
            'cancelar reservas',
            'ver recibo reserva',
            'descargar recibo reserva',
            'imprimir recibo reserva',
            'cobrar cuotas',
            'convertir reservas',
            'ver reservas equipo',
            'anular caja',
            'ver reportes',
            'exportar reportes',
            'ver reporte reservas',
            'exportar reporte reservas',
            'ver reporte mejor vendedor',
            'exportar reporte mejor vendedor',
            'administrar usuarios',
            'crear supervisores',
            'editar supervisores',
            'desactivar supervisores',
            'crear grupos comerciales',
            'editar grupos comerciales',
            'crear asesores',
            'editar asesores',
            'desactivar asesores',
            'asignar urbanizaciones a asesores',
            'resetear contraseña asesor',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $administrador = Role::firstOrCreate(['name' => 'administrador', 'guard_name' => 'web']);
        $gerente = Role::firstOrCreate(['name' => 'gerente', 'guard_name' => 'web']);
        $supervisor = Role::firstOrCreate(['name' => 'supervisor', 'guard_name' => 'web']);
        $vendedor = Role::firstOrCreate(['name' => 'vendedor', 'guard_name' => 'web']);
        $cajero = Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'cliente', 'guard_name' => 'web']);

        $cajero->syncPermissions([
            'ver dashboard',
            'ver lotes',
            'ver clientes',
            'cobrar cuotas',
            'ver recibo reserva',
            'descargar recibo reserva',
            'imprimir recibo reserva',
        ]);

        $administrador->syncPermissions($permissions);
        $gerente->syncPermissions(['ver dashboard', 'ver lotes', 'ver clientes', 'ver ventas', 'ver reservas', 'crear lotes', 'editar lotes', 'crear clientes', 'editar clientes', 'crear ventas', 'editar ventas', 'anular ventas', 'crear reservas', 'cancelar reservas', 'cobrar cuotas', 'anular caja', 'ver reportes', 'exportar reportes', 'ver reporte reservas', 'exportar reporte reservas', 'ver reporte mejor vendedor', 'exportar reporte mejor vendedor']);
        $supervisor->syncPermissions(['ver dashboard', 'ver lotes', 'ver clientes', 'ver ventas', 'ver reservas', 'ver reservas equipo', 'crear clientes', 'editar clientes', 'crear ventas', 'crear reservas', 'cancelar reservas', 'ver recibo reserva', 'descargar recibo reserva', 'imprimir recibo reserva', 'crear asesores', 'editar asesores', 'desactivar asesores', 'asignar urbanizaciones a asesores', 'resetear contraseña asesor']);
        $vendedor->syncPermissions(['ver dashboard', 'ver lotes', 'ver clientes', 'ver reservas', 'crear clientes', 'editar clientes', 'crear reservas', 'ver recibo reserva', 'descargar recibo reserva', 'imprimir recibo reserva']);
        $supervisor->givePermissionTo(['ver reportes', 'ver reporte reservas', 'exportar reporte reservas']);

        $admin = User::factory()->create(['name' => 'Administrador Impacto', 'email' => 'admin@impacto.test']);
        $admin->assignRole('administrador');
        AuditLog::create([
            'user_id' => $admin->id,
            'modelo' => 'Role',
            'accion' => 'configurar_roles_permisos',
            'descripcion' => 'Roles y permisos iniciales configurados por seeder.',
            'datos_nuevos' => ['permissions' => $permissions],
            'created_at' => now(),
        ]);

        User::factory()->create(['name' => 'Gerente Comercial', 'email' => 'gerente@impacto.test'])->assignRole('gerente');
        $supervisorDemo = User::factory()->create(['name' => 'Supervisor Comercial', 'email' => 'supervisor@impacto.test']);
        $supervisorDemo->assignRole('supervisor');
        SupervisorProfile::create(['user_id' => $supervisorDemo->id, 'nombre' => 'Supervisor Comercial', 'ci' => 'SUP-100', 'celular' => '70000001', 'email' => 'supervisor@impacto.test', 'direccion' => 'Oficina central', 'activo' => true]);
        $vendedorDemo = User::factory()->create(['name' => 'Asesor de Ventas', 'email' => 'vendedor@impacto.test']);
        $vendedorDemo->assignRole('vendedor');
        User::factory()->create(['name' => 'Cajero Oficina', 'email' => 'cajero@impacto.test'])->assignRole('cajero');
        $grupoNorte = GrupoComercial::create(['nombre' => 'Grupo Norte', 'descripcion' => 'Equipo comercial zona norte.', 'supervisor_id' => $supervisorDemo->id, 'activo' => true]);
        GrupoComercial::create(['nombre' => 'Grupo Sur', 'descripcion' => 'Equipo comercial zona sur.', 'supervisor_id' => $supervisorDemo->id, 'activo' => true]);
        GrupoComercial::create(['nombre' => 'Grupo Centro', 'descripcion' => 'Equipo comercial zona centro.', 'supervisor_id' => $supervisorDemo->id, 'activo' => true]);
        Asesor::create(['user_id' => $vendedorDemo->id, 'supervisor_id' => $supervisorDemo->id, 'grupo_comercial_id' => $grupoNorte->id, 'nombre' => 'Asesor', 'apellido' => 'de Ventas', 'ci' => 'VEN-100', 'celular' => '70000002', 'email' => 'vendedor@impacto.test', 'direccion' => 'Oficina comercial', 'activo' => true]);

        $clientes = collect([
            ['nombre' => 'Mariela Fernandez Rojas', 'documento' => 'CI-4829137 SC', 'telefono' => '77012345', 'email' => 'mariela.fernandez@example.com', 'direccion' => 'Av. Banzer 5to anillo, Santa Cruz'],
            ['nombre' => 'Carlos Alberto Rojas Perez', 'documento' => 'CI-6392841 SC', 'telefono' => '72100455', 'email' => 'carlos.rojas@example.com', 'direccion' => 'Barrio Las Palmas, calle 4 #28'],
            ['nombre' => 'Ana Lucia Vargas Medina', 'documento' => 'CI-7519283 CB', 'telefono' => '69033412', 'email' => 'ana.vargas@example.com', 'direccion' => 'Condominio Sevilla Norte, bloque B'],
            ['nombre' => 'Luis Fernando Mercado Salvatierra', 'documento' => 'CI-5849302 SC', 'telefono' => '73188220', 'email' => 'luis.mercado@example.com', 'direccion' => 'Av. Virgen de Cotoca km 7'],
            ['nombre' => 'Patricia Suarez Aguilera', 'documento' => 'CI-8263145 SC', 'telefono' => '75611908', 'email' => 'patricia.suarez@example.com', 'direccion' => 'Zona Equipetrol, calle Los Tajibos #17'],
            ['nombre' => 'Jorge Andres Quiroga Mendez', 'documento' => 'CI-4927610 LP', 'telefono' => '70844591', 'email' => 'jorge.quiroga@example.com', 'direccion' => 'Av. Mutualista, condominio El Portal'],
            ['nombre' => 'Natalia Rivero Chavez', 'documento' => 'CI-9182736 SC', 'telefono' => '67753122', 'email' => 'natalia.rivero@example.com', 'direccion' => 'Plan 3000, barrio Primavera'],
        ])->map(fn (array $data) => Cliente::create($data));

        User::factory()->create([
            'cliente_id' => $clientes[0]->id,
            'name' => 'Cliente Maria Fernandez',
            'email' => 'cliente@impacto.test',
        ])->assignRole('cliente');

        $urbanizacionesData = [
            ['nombre' => 'Colinas del Urubo', 'ubicacion' => 'Zona Urubo, Santa Cruz', 'superficie_total' => 165000],
            ['nombre' => 'Jardines del Norte', 'ubicacion' => 'Carretera al Norte, km 12', 'superficie_total' => 148000],
        ];

        foreach ($urbanizacionesData as $uIndex => $urbanizacionData) {
            $urbanizacion = Urbanizacion::create([
                ...$urbanizacionData,
                'descripcion' => 'Proyecto residencial de demostracion comercial para el Sistema Integral de Terrenos.',
                'estado' => 'activa',
                'mostrar_precio_publico' => true,
            ]);

            if ($uIndex === 0) {
                $supervisorDemo->urbanizacionesAsignadas()->syncWithoutDetaching([$urbanizacion->id => ['activo' => true]]);
                $vendedorDemo->urbanizacionesAsignadas()->syncWithoutDetaching([$urbanizacion->id => ['activo' => true]]);
            }

            foreach (['A', 'B', 'C', 'D'] as $mIndex => $codigoManzano) {
                $manzano = Manzano::create([
                    'urbanizacion_id' => $urbanizacion->id,
                    'codigo' => $codigoManzano,
                    'nombre' => 'Manzano '.$codigoManzano,
                    'orden' => $mIndex + 1,
                ]);

                for ($i = 1; $i <= 15; $i++) {
                    Lote::create([
                        'manzano_id' => $manzano->id,
                        'codigo' => str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                        'superficie' => 280 + ($i * 8) + ($uIndex * 12),
                        'precio' => 17000 + ($i * 750) + ($uIndex * 1200),
                        'estado' => $i === 15 ? 'bloqueado' : 'disponible',
                        'fila' => (int) ceil($i / 5),
                        'columna' => (($i - 1) % 5) + 1,
                        'coord_x' => null,
                        'coord_y' => null,
                    ]);
                }
            }
        }

        $urbanizacionPrincipal = Urbanizacion::orderBy('id')->firstOrFail();
        Cliente::query()->update([
            'urbanizacion_id' => $urbanizacionPrincipal->id,
            'created_by' => $admin->id,
        ]);
        $clientes = Cliente::orderBy('id')->get();

        $this->crearVentaContado($clientes[0], $admin);
        $this->crearVentaCredito($clientes[1], $admin);
        $this->crearReservaActiva($clientes[2], $admin);
        $this->crearReservaVencida($clientes[3], $admin);
        $this->crearReservaConvertida($clientes[4], $admin);
        $this->crearVentaCreditoReciente($clientes[5], $admin);

        Venta::with('cuotas')->get()->each(function (Venta $venta): void {
            $venta->update([
                'saldo_financiar' => (int) $venta->numero_cuotas === 0
                    ? 0
                    : max(0, (float) $venta->precio_final - (float) $venta->cuota_inicial - (float) $venta->cuotas->sum('monto_pagado')),
            ]);
        });
    }

    private function crearVentaContado(Cliente $cliente, User $admin): void
    {
        $lote = Lote::where('estado', 'disponible')->firstOrFail();
        $venta = Venta::create([
            'lote_id' => $lote->id,
            'cliente_id' => $cliente->id,
            'user_id' => $admin->id,
            'fecha_venta' => now()->subDays(3),
            'precio_final' => $lote->precio,
            'cuota_inicial' => 0,
            'numero_cuotas' => 0,
            'estado' => 'completada',
            'observaciones' => 'Venta al contado de demostracion.',
        ]);

        $lote->update(['estado' => 'vendido']);
        $this->historial($lote, $admin, 'lote_vendido', 'Venta al contado.', 'disponible', 'vendido');
        CashMovement::create($this->movimientoBase($cliente, $admin, ['sale_id' => $venta->id, 'concepto' => 'contado', 'metodo_pago' => 'transferencia', 'monto' => $venta->precio_final, 'fecha' => now()->subDays(3), 'referencia' => 'TRF-00045']));
    }

    private function crearVentaCredito(Cliente $cliente, User $admin): void
    {
        $lote = Lote::where('estado', 'disponible')->firstOrFail();
        $venta = Venta::create([
            'lote_id' => $lote->id,
            'cliente_id' => $cliente->id,
            'user_id' => $admin->id,
            'fecha_venta' => now()->subMonths(2),
            'precio_final' => $lote->precio,
            'cuota_inicial' => 3000,
            'numero_cuotas' => 6,
            'estado' => 'activa',
            'observaciones' => 'Venta a credito de demostracion.',
        ]);

        $lote->update(['estado' => 'vendido']);
        $this->historial($lote, $admin, 'lote_vendido', 'Venta a credito.', 'disponible', 'vendido');
        CashMovement::create($this->movimientoBase($cliente, $admin, ['sale_id' => $venta->id, 'concepto' => 'anticipo', 'metodo_pago' => 'QR', 'monto' => 3000, 'fecha' => now()->subMonths(2), 'referencia' => 'QR-ANT-102']));

        $saldo = $venta->precio_final - $venta->cuota_inicial;
        $monto = round($saldo / $venta->numero_cuotas, 2);

        for ($i = 1; $i <= $venta->numero_cuotas; $i++) {
            $programada = Carbon::parse($venta->fecha_venta)->addMonths($i);
            $pagado = $i === 1 ? $monto : ($i === 2 ? round($monto / 2, 2) : 0);
            $estado = $pagado >= $monto ? 'pagada' : ($pagado > 0 ? 'parcial' : ($programada->isPast() ? 'vencida' : 'pendiente'));

            $cuota = Cuota::create([
                'venta_id' => $venta->id,
                'numero' => $i,
                'fecha_programada' => $programada,
                'fecha_vencimiento' => $programada,
                'monto' => $monto,
                'monto_pagado' => $pagado,
                'saldo_pendiente' => round($monto - $pagado, 2),
                'fecha_pago' => $pagado >= $monto ? $programada->copy()->subDays(2) : null,
                'estado' => $estado,
            ]);

            if ($pagado > 0) {
                CashMovement::create($this->movimientoBase($cliente, $admin, ['sale_id' => $venta->id, 'installment_id' => $cuota->id, 'concepto' => 'cuota', 'metodo_pago' => $i === 1 ? 'banco' : 'efectivo', 'monto' => $pagado, 'fecha' => now()->subDays(10 + $i), 'referencia' => 'REC-CUOTA-'.$i]));
            }
        }
    }

    private function crearReservaActiva(Cliente $cliente, User $admin): void
    {
        $lote = Lote::where('estado', 'disponible')->firstOrFail();
        $reserva = Reserva::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'usuario_id' => $admin->id,
            'fecha_reserva' => now()->subDay(),
            'fecha_vencimiento' => now()->addDays(6),
            'monto_reserva' => 800,
            'estado' => 'activa',
            'tipo_operacion' => 'credito',
            'observaciones' => 'Reserva activa de demostracion.',
        ]);

        $lote->update(['estado' => 'reservado']);
        $this->historial($lote, $admin, 'reserva_creada', 'Reserva activa.', 'disponible', 'reservado');
        CashMovement::create($this->movimientoBase($cliente, $admin, ['reservation_id' => $reserva->id, 'concepto' => 'reserva', 'metodo_pago' => 'efectivo', 'monto' => 800, 'fecha' => now()->subDay(), 'referencia' => 'RES-ACT-001']));
    }

    private function crearReservaVencida(Cliente $cliente, User $admin): void
    {
        $lote = Lote::where('estado', 'disponible')->firstOrFail();
        $reserva = Reserva::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'usuario_id' => $admin->id,
            'fecha_reserva' => now()->subDays(12),
            'fecha_vencimiento' => now()->subDays(5),
            'monto_reserva' => 500,
            'estado' => 'vencida',
            'tipo_operacion' => 'contado',
            'observaciones' => 'Reserva vencida de demostracion; lote liberado.',
        ]);

        $this->historial($lote, $admin, 'reserva_vencida', 'Reserva vencida y lote liberado.', 'reservado', 'disponible');
        CashMovement::create($this->movimientoBase($cliente, $admin, ['reservation_id' => $reserva->id, 'concepto' => 'reserva', 'metodo_pago' => 'otro', 'monto' => 500, 'fecha' => now()->subDays(12), 'referencia' => 'RES-VEN-001']));
    }

    private function crearReservaConvertida(Cliente $cliente, User $admin): void
    {
        $lote = Lote::where('estado', 'disponible')->firstOrFail();
        $reserva = Reserva::create([
            'cliente_id' => $cliente->id,
            'lote_id' => $lote->id,
            'usuario_id' => $admin->id,
            'fecha_reserva' => now()->subDays(18),
            'fecha_vencimiento' => now()->subDays(11),
            'monto_reserva' => 1000,
            'estado' => 'convertida',
            'tipo_operacion' => 'semicontado',
            'observaciones' => 'Reserva convertida en venta durante la demo.',
        ]);

        $venta = Venta::create([
            'lote_id' => $lote->id,
            'cliente_id' => $cliente->id,
            'user_id' => $admin->id,
            'reserva_id' => $reserva->id,
            'fecha_venta' => now()->subDays(10),
            'precio_final' => $lote->precio,
            'cuota_inicial' => 4500,
            'numero_cuotas' => 4,
            'estado' => 'activa',
            'observaciones' => 'Venta originada desde reserva convertida.',
        ]);

        $lote->update(['estado' => 'vendido']);
        $this->historial($lote, $admin, 'reserva_convertida', 'La reserva fue convertida en venta.', 'reservado', 'vendido');
        CashMovement::create($this->movimientoBase($cliente, $admin, ['reservation_id' => $reserva->id, 'concepto' => 'reserva', 'metodo_pago' => 'QR', 'monto' => 1000, 'fecha' => now()->subDays(18), 'referencia' => 'RES-CONV-001']));
        CashMovement::create($this->movimientoBase($cliente, $admin, ['sale_id' => $venta->id, 'concepto' => 'anticipo', 'metodo_pago' => 'transferencia', 'monto' => 4500, 'fecha' => now()->subDays(10), 'referencia' => 'ANT-CONV-001']));

        $saldo = $venta->precio_final - $venta->cuota_inicial;
        $monto = round($saldo / $venta->numero_cuotas, 2);
        for ($i = 1; $i <= $venta->numero_cuotas; $i++) {
            Cuota::create([
                'venta_id' => $venta->id,
                'numero' => $i,
                'fecha_programada' => now()->addMonths($i),
                'fecha_vencimiento' => now()->addMonths($i),
                'monto' => $monto,
                'monto_pagado' => 0,
                'saldo_pendiente' => $monto,
                'estado' => 'pendiente',
            ]);
        }
    }

    private function crearVentaCreditoReciente(Cliente $cliente, User $admin): void
    {
        $lote = Lote::where('estado', 'disponible')->firstOrFail();
        $venta = Venta::create([
            'lote_id' => $lote->id,
            'cliente_id' => $cliente->id,
            'user_id' => $admin->id,
            'fecha_venta' => now()->startOfMonth()->addDays(2),
            'precio_final' => $lote->precio,
            'cuota_inicial' => 2500,
            'numero_cuotas' => 5,
            'estado' => 'activa',
            'observaciones' => 'Venta a credito reciente para grafico mensual.',
        ]);

        $lote->update(['estado' => 'vendido']);
        $this->historial($lote, $admin, 'lote_vendido', 'Venta a credito reciente.', 'disponible', 'vendido');
        CashMovement::create($this->movimientoBase($cliente, $admin, ['sale_id' => $venta->id, 'concepto' => 'anticipo', 'metodo_pago' => 'banco', 'monto' => 2500, 'fecha' => now()->startOfMonth()->addDays(2), 'referencia' => 'ANT-MES-001']));

        $saldo = $venta->precio_final - $venta->cuota_inicial;
        $monto = round($saldo / $venta->numero_cuotas, 2);
        for ($i = 1; $i <= $venta->numero_cuotas; $i++) {
            Cuota::create([
                'venta_id' => $venta->id,
                'numero' => $i,
                'fecha_programada' => now()->addMonths($i),
                'fecha_vencimiento' => now()->addMonths($i),
                'monto' => $monto,
                'monto_pagado' => 0,
                'saldo_pendiente' => $monto,
                'estado' => 'pendiente',
            ]);
        }
    }

    private function movimientoBase(Cliente $cliente, User $admin, array $overrides): array
    {
        return [
            ...[
                'user_id' => $admin->id,
                'cliente_id' => $cliente->id,
                'tipo' => 'ingreso',
                'concepto' => 'ajuste',
                'metodo_pago' => 'efectivo',
                'monto' => 0,
                'fecha' => now(),
                'referencia' => null,
                'estado' => 'confirmado',
            ],
            ...$overrides,
        ];
    }

    private function historial(Lote $lote, User $admin, string $accion, string $descripcion, ?string $estadoAnterior, ?string $estadoNuevo): void
    {
        LotHistory::create([
            'lote_id' => $lote->id,
            'user_id' => $admin->id,
            'accion' => $accion,
            'descripcion' => $descripcion,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo,
        ]);
    }
}
