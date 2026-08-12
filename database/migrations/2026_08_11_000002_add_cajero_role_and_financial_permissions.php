<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'ver dashboard',
            'ver lotes',
            'ver clientes',
            'cobrar cuotas',
            'ver recibo reserva',
            'descargar recibo reserva',
            'imprimir recibo reserva',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $cajero = Role::firstOrCreate(['name' => 'cajero', 'guard_name' => 'web']);
        $cajero->syncPermissions($permissions);

        $vendedor = Role::where('name', 'vendedor')->where('guard_name', 'web')->first();
        if ($vendedor?->hasPermissionTo('cobrar cuotas')) {
            $vendedor->revokePermissionTo('cobrar cuotas');
        }

        $supervisor = Role::where('name', 'supervisor')->where('guard_name', 'web')->first();
        if ($supervisor?->hasPermissionTo('cobrar cuotas')) {
            $supervisor->revokePermissionTo('cobrar cuotas');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $cajero = Role::where('name', 'cajero')->where('guard_name', 'web')->first();
        $cajero?->delete();

        Role::where('name', 'vendedor')->where('guard_name', 'web')->first()?->givePermissionTo('cobrar cuotas');
        Role::where('name', 'supervisor')->where('guard_name', 'web')->first()?->givePermissionTo('cobrar cuotas');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
