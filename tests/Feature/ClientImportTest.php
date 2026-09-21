<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\ClientImportBatch;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Services\UserSpreadsheetService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ClientImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_authorized_roles_can_open_importer(): void
    {
        [$admin, $manager, $seller, $urbanizacion] = $this->context();
        foreach ([$admin, $manager] as $user) {
            $this->actingAs($user)->withSession(['urbanizacion_id' => $urbanizacion->id])->get(route('clientes.import.create'))->assertOk();
        }
        $this->actingAs($seller)->withSession(['urbanizacion_id' => $urbanizacion->id])->get(route('clientes.import.create'))->assertForbidden();
    }

    public function test_csv_preview_detects_flexible_headers_existing_matches_and_internal_duplicates_without_writes(): void
    {
        [$admin, , , $urbanizacion] = $this->context();
        Cliente::create(['nombre' => 'Cliente existente', 'documento' => '123456', 'telefono' => '70011111', 'email' => 'existente@example.com']);
        $before = Cliente::count();
        $csv = "Nombre Completo,C.I.,Celular,Correo,Dirección,Asesor Email\n".
            "Cliente existente,123.456,70099999,nuevo@example.com,Calle 1,\n".
            "Persona teléfono,654321,70011111,otra@example.com,Calle 2,\n".
            "Persona lista,999111,70022222,lista@example.com,Calle 3,\n".
            "Persona lista,999-111,70022222,lista@example.com,Calle 3,\n".
            "Nombre A,888777,70033333,a@example.com,Calle 4,\n".
            "Nombre B,888.777,70033333,a@example.com,Calle 4,\n".
            ",,telefono,correo-invalido,Calle 5,noexiste@example.com\n";

        $response = $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.import.preview'), ['archivo' => $this->upload('clientes.csv', $csv, 'text/csv')]);
        $batch = ClientImportBatch::latest('id')->firstOrFail();
        $response->assertRedirect(route('clientes.import.show', $batch));

        $this->assertSame($before, Cliente::count());
        $this->assertSame(7, $batch->total_rows);
        $this->assertSame(2, $batch->rows()->where('status', 'existing')->count());
        $this->assertSame(1, $batch->rows()->where('status', 'match')->count());
        $this->assertSame(3, $batch->rows()->where('status', 'error')->count());
        $this->assertSame(1, $batch->rows()->where('status', 'ready')->count());
    }

    public function test_xlsx_preview_and_template_work_without_extra_dependency(): void
    {
        [$admin, , , $urbanizacion] = $this->context();
        $content = app(UserSpreadsheetService::class)->xlsx(['Nombre', 'Documento', 'Teléfono', 'Email', 'Dirección', 'Asesor'], [
            ['Persona XLSX', 'XLSX-100', '70040000', 'xlsx@example.com', 'Zona norte', ''],
        ]);
        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('clientes.import.template'))->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.import.preview'), ['archivo' => $this->upload('clientes.xlsx', $content, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')])->assertRedirect();
        $this->assertDatabaseHas('client_import_rows', ['status' => 'ready']);
    }

    public function test_confirmation_applies_protection_actor_advisor_and_explicit_match_acceptance_without_operations(): void
    {
        [$admin, , $seller, $urbanizacion] = $this->context();
        Cliente::create(['nombre' => 'Referencia familiar', 'documento' => 'REF-1', 'telefono' => '70055555']);
        $csv = "nombre,ci,telefono,email,direccion,asesor_email\n".
            "Persona lista,NEW-1,70066666,nueva@example.com,Calle A,{$seller->email}\n".
            "Persona diferente,NEW-2,70055555,diferente@example.com,Calle B,{$seller->email}\n";
        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.import.preview'), ['archivo' => $this->upload('confirmar.csv', $csv, 'text/csv')]);
        $batch = ClientImportBatch::latest('id')->firstOrFail();
        $match = $batch->rows()->where('status', 'match')->firstOrFail();
        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('clientes.import.confirm', $batch), ['accepted_matches' => [$match->id]])->assertRedirect(route('clientes.import.show', $batch));

        $clients = Cliente::whereIn('documento_normalizado', ['NEW1', 'NEW2'])->get();
        $this->assertCount(2, $clients);
        foreach ($clients as $client) {
            $this->assertSame($admin->id, $client->created_by);
            $this->assertSame($seller->id, $client->responsible_user_id);
            $this->assertSame(30, (int) $client->protected_at->diffInDays($client->protection_expires_at));
        }
        $this->assertDatabaseCount('ventas', 0);
        $this->assertDatabaseCount('reservas', 0);
        $this->assertDatabaseCount('cuotas', 0);
        $this->assertDatabaseCount('cash_movements', 0);
        $this->assertSame(2, $batch->fresh()->created_rows);
    }

    public function test_unaccepted_match_is_skipped_existing_is_not_overwritten_and_repeat_is_idempotent(): void
    {
        [$admin, , , $urbanizacion] = $this->context();
        $existing = Cliente::create(['nombre' => 'Nombre original', 'documento' => 'KEEP-1', 'telefono' => '70077777', 'email' => 'original@example.com']);
        $csv = "nombre,ci,telefono,email,direccion,asesor_email\nNombre cambiado,KEEP1,70000000,cambio@example.com,,\nCoincidencia,OTHER-1,70077777,otro@example.com,,\nNueva,NEW-9,70099999,new9@example.com,,\n";

        for ($run = 0; $run < 2; $run++) {
            $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])->post(route('clientes.import.preview'), ['archivo' => $this->upload('repetido.csv', $csv, 'text/csv')]);
            $batch = ClientImportBatch::latest('id')->firstOrFail();
            $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])->post(route('clientes.import.confirm', $batch))->assertRedirect();
        }

        $this->assertSame('Nombre original', $existing->fresh()->nombre);
        $this->assertSame('original@example.com', $existing->fresh()->email);
        $this->assertSame(1, Cliente::where('documento_normalizado', 'NEW9')->count());
        $this->assertSame(0, Cliente::where('documento_normalizado', 'OTHER1')->count());
        $this->assertSame(ClientImportBatch::first()->file_hash, ClientImportBatch::latest('id')->first()->file_hash);
    }

    public function test_invalid_files_row_limit_missing_advisor_and_result_report_are_controlled(): void
    {
        [$admin, , , $urbanizacion] = $this->context();
        $session = ['urbanizacion_id' => $urbanizacion->id];
        $this->actingAs($admin)->withSession($session)->post(route('clientes.import.preview'), ['archivo' => $this->upload('clientes.txt', 'x', 'text/plain')])->assertSessionHasErrors('archivo');

        $many = "nombre,ci\n";
        for ($i = 1; $i <= 5001; $i++) {
            $many .= "Persona {$i},CI{$i}\n";
        }
        $this->actingAs($admin)->withSession($session)->post(route('clientes.import.preview'), ['archivo' => $this->upload('muchos.csv', $many, 'text/csv')])->assertSessionHasErrors('archivo');

        $csv = "nombre,ci,asesor_email\nPersona,ADV-1,inexistente@example.com\n";
        $this->actingAs($admin)->withSession($session)->post(route('clientes.import.preview'), ['archivo' => $this->upload('asesor.csv', $csv, 'text/csv')]);
        $batch = ClientImportBatch::latest('id')->firstOrFail();
        $this->assertDatabaseHas('client_import_rows', ['batch_id' => $batch->id, 'status' => 'error']);
        $this->actingAs($admin)->withSession($session)->get(route('clientes.import.report', $batch))->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_size_limit_and_constraint_race_are_reported_without_duplicate_or_sql_error(): void
    {
        [$admin, , , $urbanizacion] = $this->context();
        $session = ['urbanizacion_id' => $urbanizacion->id];
        $this->actingAs($admin)->withSession($session)
            ->post(route('clientes.import.preview'), ['archivo' => UploadedFile::fake()->create('grande.csv', 5121, 'text/csv')])
            ->assertSessionHasErrors('archivo');

        $csv = "nombre,ci\nPersona carrera,RACE-100\n";
        $this->actingAs($admin)->withSession($session)->post(route('clientes.import.preview'), ['archivo' => $this->upload('race.csv', $csv, 'text/csv')]);
        $batch = ClientImportBatch::latest('id')->firstOrFail();
        Cliente::create(['nombre' => 'Creado concurrentemente', 'documento' => 'RACE100']);

        $this->actingAs($admin)->withSession($session)->post(route('clientes.import.confirm', $batch))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, Cliente::where('documento_normalizado', 'RACE100')->count());
        $this->assertDatabaseHas('client_import_rows', ['batch_id' => $batch->id, 'status' => 'existing']);
    }

    private function context(): array
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $urbanizacion = Urbanizacion::create(['nombre' => 'Importación', 'ubicacion' => 'Local', 'estado' => 'activa']);
        $admin = User::factory()->create(['email' => 'admin-import@test.local']);
        $manager = User::factory()->create(['email' => 'manager-import@test.local']);
        $seller = User::factory()->create(['email' => 'seller-import@test.local']);
        $admin->assignRole('administrador');
        $manager->assignRole('gerente');
        $seller->assignRole('vendedor');
        foreach ([$admin, $manager, $seller] as $user) {
            $user->urbanizacionesAsignadas()->attach($urbanizacion->id, ['activo' => true]);
        }

        return [$admin, $manager, $seller, $urbanizacion];
    }

    private function upload(string $name, string $content, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'client-import-');
        file_put_contents($path, $content);
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        return new UploadedFile($path, $name, $mime, null, true);
    }
}
