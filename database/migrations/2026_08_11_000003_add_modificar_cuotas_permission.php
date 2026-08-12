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

        Permission::firstOrCreate(['name' => 'modificar cuotas', 'guard_name' => 'web']);

        $administrador = Role::where('name', 'administrador')->where('guard_name', 'web')->first();
        if ($administrador && ! $administrador->hasPermissionTo('modificar cuotas')) {
            $administrador->givePermissionTo('modificar cuotas');
        }

        $otrosRoles = Role::where('guard_name', 'web')
            ->whereIn('name', ['gerente', 'cajero', 'supervisor', 'vendedor'])
            ->get();

        foreach ($otrosRoles as $rol) {
            if ($rol->hasPermissionTo('modificar cuotas')) {
                $rol->revokePermissionTo('modificar cuotas');
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $administrador = Role::where('name', 'administrador')->where('guard_name', 'web')->first();
        if ($administrador?->hasPermissionTo('modificar cuotas')) {
            $administrador->revokePermissionTo('modificar cuotas');
        }

        Permission::where('name', 'modificar cuotas')->where('guard_name', 'web')->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
