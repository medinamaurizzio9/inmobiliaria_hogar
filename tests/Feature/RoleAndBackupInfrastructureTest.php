<?php

namespace Tests\Feature;

use App\Models\Urbanizacion;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleAndBackupInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_structural_roles_initialize_without_demo_users_and_are_idempotent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertEqualsCanonicalizing(RolesAndPermissionsSeeder::ROLES, Role::where('guard_name', 'web')->pluck('name')->all());
        $this->assertSame(0, User::count());
        $this->assertSame(count(RolesAndPermissionsSeeder::ROLES), Role::where('guard_name', 'web')->count());
    }

    public function test_admin_routes_work_with_zero_supervisors_and_sellers(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->create(['email' => 'admin.roles@test.local']);
        $admin->assignRole('administrador');
        $urbanizacion = Urbanizacion::create([
            'nombre' => 'Entorno estructural',
            'ubicacion' => 'Local',
            'estado' => 'activa',
        ]);
        $admin->urbanizacionesAsignadas()->attach($urbanizacion->id, ['activo' => true]);
        $session = ['urbanizacion_id' => $urbanizacion->id];

        $this->assertSame(0, User::role('supervisor')->count());
        $this->assertSame(0, User::role('vendedor')->count());

        foreach (['asesores.index', 'grupos-comerciales.index', 'urbanizaciones.asignaciones', 'reportes.mejor-vendedor'] as $route) {
            $this->actingAs($admin)->withSession($session)->get(route($route))->assertOk();
        }
    }

    public function test_backup_action_reports_missing_mysqldump_without_leaving_partial_file(): void
    {
        $directory = storage_path('framework/testing-backups');
        File::deleteDirectory($directory);
        $originalConnection = config('database.default');
        config([
            'database.default' => 'mysql',
            'backup.directory' => $directory,
            'backup.mysqldump_path' => 'missing-mysqldump-test-binary',
        ]);

        try {
            app(DatabaseBackupService::class)->create();
            $this->fail('El servicio debía informar que mysqldump no está disponible.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('MYSQLDUMP_PATH', $exception->getMessage());
        } finally {
            config(['database.default' => $originalConnection]);
        }

        $this->assertSame([], File::files($directory));
        File::deleteDirectory($directory);
    }
}
