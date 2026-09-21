<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public const ROLES = ['super administrador', 'administrador', 'gerente', 'supervisor', 'vendedor', 'cajero', 'cliente'];

    public const PERMISSIONS = [
        'ver dashboard', 'ver lotes', 'ver clientes', 'ver ventas', 'ver reservas',
        'crear urbanizaciones', 'editar urbanizaciones', 'eliminar urbanizaciones',
        'crear manzanos', 'editar manzanos', 'eliminar manzanos',
        'crear lotes', 'editar lotes', 'eliminar lotes',
        'crear clientes', 'editar clientes', 'eliminar clientes',
        'crear ventas', 'editar ventas', 'editar ventas anuladas', 'anular ventas',
        'crear reservas', 'editar reservas', 'cancelar reservas',
        'ver recibo reserva', 'descargar recibo reserva', 'imprimir recibo reserva',
        'cobrar cuotas', 'modificar cuotas', 'convertir reservas', 'ver reservas equipo',
        'anular caja', 'ver reportes', 'ver control comercial clientes', 'exportar reportes',
        'ver reporte reservas', 'exportar reporte reservas', 'ver reporte mejor vendedor',
        'exportar reporte mejor vendedor', 'administrar usuarios', 'crear supervisores',
        'editar supervisores', 'desactivar supervisores', 'crear grupos comerciales',
        'editar grupos comerciales', 'crear asesores', 'editar asesores',
        'desactivar asesores', 'asignar urbanizaciones a asesores', 'resetear contraseña asesor',
        'importar clientes',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles = collect(self::ROLES)->mapWithKeys(fn (string $name) => [
            $name => Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']),
        ]);

        $roles['super administrador']->syncPermissions(self::PERMISSIONS);
        $roles['administrador']->syncPermissions(self::PERMISSIONS);
        $roles['gerente']->syncPermissions([
            'ver dashboard', 'ver lotes', 'ver clientes', 'ver ventas', 'ver reservas',
            'crear lotes', 'editar lotes', 'crear clientes', 'editar clientes',
            'crear ventas', 'editar ventas', 'anular ventas', 'crear reservas',
            'cancelar reservas', 'cobrar cuotas', 'anular caja', 'ver reportes',
            'ver control comercial clientes', 'exportar reportes', 'ver reporte reservas',
            'exportar reporte reservas', 'ver reporte mejor vendedor', 'exportar reporte mejor vendedor',
            'importar clientes',
        ]);
        $roles['supervisor']->syncPermissions([
            'ver dashboard', 'ver lotes', 'ver clientes', 'ver ventas', 'ver reservas',
            'ver reservas equipo', 'crear clientes', 'editar clientes', 'crear ventas',
            'crear reservas', 'cancelar reservas', 'ver recibo reserva',
            'descargar recibo reserva', 'imprimir recibo reserva', 'crear asesores',
            'editar asesores', 'desactivar asesores', 'asignar urbanizaciones a asesores',
            'resetear contraseña asesor', 'ver control comercial clientes', 'ver reportes',
            'ver reporte reservas', 'exportar reporte reservas',
        ]);
        $roles['vendedor']->syncPermissions([
            'ver dashboard', 'ver lotes', 'ver clientes', 'ver reservas', 'crear clientes',
            'editar clientes', 'crear reservas', 'ver recibo reserva',
            'descargar recibo reserva', 'imprimir recibo reserva',
        ]);
        $roles['cajero']->syncPermissions([
            'ver dashboard', 'ver lotes', 'ver clientes', 'cobrar cuotas',
            'ver recibo reserva', 'descargar recibo reserva', 'imprimir recibo reserva',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
