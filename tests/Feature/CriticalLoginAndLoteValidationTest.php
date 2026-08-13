<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class CriticalLoginAndLoteValidationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Urbanizacion $urbanizacion;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        $this->admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $this->urbanizacion = Urbanizacion::firstOrFail();
    }

    public function test_get_login_responde_ok(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Iniciar sesión');
    }

    public function test_post_login_sigue_funcionando(): void
    {
        $this->post('/login', [
            'email' => 'admin@impacto.test',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
    }

    public function test_crear_lote_duplicado_devuelve_error_de_validacion(): void
    {
        $lote = $this->loteBase();

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->from(route('lotes.create'))
            ->post(route('lotes.store'), $this->payload($lote, ['codigo' => $lote->codigo]))
            ->assertRedirect(route('lotes.create'))
            ->assertSessionHasErrors(['codigo' => 'Ya existe un lote con ese código en este manzano.']);
    }

    public function test_editar_lote_sin_cambiar_codigo_permite_guardar(): void
    {
        $lote = $this->loteBase();

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->put(route('lotes.update', $lote), $this->payload($lote, [
                'superficie' => (float) $lote->superficie + 10,
            ]))
            ->assertRedirect(route('lotes.index'));
    }

    public function test_editar_lote_con_codigo_duplicado_bloquea(): void
    {
        $manzano = $this->urbanizacion->manzanos()->firstOrFail();
        $loteExistente = Lote::where('manzano_id', $manzano->id)->firstOrFail();
        $loteAEditar = Lote::where('manzano_id', $manzano->id)
            ->whereKeyNot($loteExistente->id)
            ->firstOrFail();

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $this->urbanizacion->id])
            ->from(route('lotes.edit', $loteAEditar))
            ->put(route('lotes.update', $loteAEditar), $this->payload($loteAEditar, [
                'codigo' => $loteExistente->codigo,
            ]))
            ->assertRedirect(route('lotes.edit', $loteAEditar))
            ->assertSessionHasErrors(['codigo' => 'Ya existe un lote con ese código en este manzano.']);
    }

    public function test_importar_csv_con_lote_duplicado_muestra_error_amigable(): void
    {
        $lote = $this->loteBase();
        $urbanizacion = $lote->manzano->urbanizacion;
        $csv = implode("\n", [
            'urbanizacion,manzano,lote,superficie_m2,precio_m2,precio_total,cuota_inicial_tipo,cuota_inicial_valor,estado,coord_x,coord_y,observaciones',
            "{$urbanizacion->nombre},{$lote->manzano->codigo},{$lote->codigo},300,100,30000,monto,0,disponible,10,20,duplicado",
        ]);

        $file = $this->csvFile($csv);

        $this->actingAs($this->admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->post(route('lotes.import.preview'), ['csv' => $file])
            ->assertOk()
            ->assertSee('Ya existe un lote con ese código en este manzano.');
    }

    private function loteBase(): Lote
    {
        return Lote::with('manzano.urbanizacion')
            ->whereHas('manzano', fn ($query) => $query->where('urbanizacion_id', $this->urbanizacion->id))
            ->firstOrFail();
    }

    private function payload(Lote $lote, array $overrides = []): array
    {
        return array_merge([
            'manzano_id' => $lote->manzano_id,
            'codigo' => $lote->codigo,
            'superficie' => $lote->superficie,
            'precio' => $lote->precio,
            'cuota_inicial_tipo' => $lote->cuota_inicial_tipo ?? 'monto',
            'cuota_inicial_valor' => $lote->cuota_inicial_valor ?? 0,
            'estado' => $lote->estado,
            'fila' => $lote->fila ?? 1,
            'columna' => $lote->columna ?? 1,
            'coord_x' => $lote->coord_x,
            'coord_y' => $lote->coord_y,
            'observaciones' => $lote->observaciones,
        ], $overrides);
    }

    private function csvFile(string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'lotes_csv_');
        file_put_contents($path, $contents);

        return new UploadedFile($path, 'lotes.csv', 'text/csv', null, true);
    }
}
