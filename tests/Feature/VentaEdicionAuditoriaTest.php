<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VentaEdicionAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    public function test_vendedor_no_ve_editar_venta_y_recibe_403_por_url(): void
    {
        $this->seed();

        $vendedor = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $vendedor->givePermissionTo('ver ventas');
        $urbanizacion = $vendedor->urbanizacionesAsignadas()->firstOrFail();
        $venta = Venta::whereHas('lote.manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacion->id))->firstOrFail();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('ventas.index'))
            ->assertForbidden();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('ventas.edit', $venta))
            ->assertForbidden();

        $this->actingAs($vendedor)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->put(route('ventas.update', $venta), $this->payload($venta, ['motivo_cambio' => 'Intento no autorizado']))
            ->assertForbidden();
    }

    public function test_administrador_ve_editar_y_puede_actualizar_con_motivo_y_auditoria(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $venta = Venta::with('lote.manzano')->firstOrFail();
        $urbanizacionId = $venta->lote->manzano->urbanizacion_id;

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacionId])
            ->get(route('ventas.index'))
            ->assertOk()
            ->assertSee('>Editar</a>', false);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacionId])
            ->put(route('ventas.update', $venta), $this->payload($venta, [
                'observaciones' => 'Observacion administrativa actualizada.',
                'motivo_cambio' => 'Correccion solicitada por gerencia.',
            ]))
            ->assertRedirect(route('ventas.index'));

        $this->assertDatabaseHas('ventas', [
            'id' => $venta->id,
            'observaciones' => 'Observacion administrativa actualizada.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'modelo' => 'Venta',
            'modelo_id' => $venta->id,
            'accion' => 'venta_actualizada',
            'descripcion' => 'Correccion solicitada por gerencia.',
            'user_id' => $admin->id,
        ]);

        $audit = AuditLog::where('modelo', 'Venta')->where('modelo_id', $venta->id)->where('accion', 'venta_actualizada')->firstOrFail();
        $this->assertSame('Correccion solicitada por gerencia.', $audit->datos_nuevos['motivo_cambio']);
        $this->assertNotNull($audit->ip);
    }

    public function test_administrador_no_puede_actualizar_sin_motivo(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $venta = Venta::with('lote.manzano')->firstOrFail();

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])
            ->from(route('ventas.edit', $venta))
            ->put(route('ventas.update', $venta), $this->payload($venta, ['motivo_cambio' => '']))
            ->assertRedirect(route('ventas.edit', $venta))
            ->assertSessionHasErrors('motivo_cambio');

        $this->assertDatabaseMissing('audit_logs', [
            'modelo' => 'Venta',
            'modelo_id' => $venta->id,
            'accion' => 'venta_actualizada',
        ]);
    }

    public function test_venta_anulada_no_se_edita_sin_permiso_especial(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        Role::findByName('administrador')->revokePermissionTo('editar ventas anuladas');
        $venta = Venta::with('lote.manzano')->firstOrFail();
        $venta->update(['estado' => 'anulada']);

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $venta->lote->manzano->urbanizacion_id])
            ->get(route('ventas.edit', $venta))
            ->assertForbidden()
            ->assertSee('No tienes permiso especial para editar una venta anulada.', false);
    }

    private function payload(Venta $venta, array $overrides = []): array
    {
        return [
            ...[
                'lote_id' => $venta->lote_id,
                'cliente_id' => $venta->cliente_id,
                'fecha_venta' => $venta->fecha_venta->format('Y-m-d'),
                'precio_final' => $venta->precio_final,
                'cuota_inicial' => $venta->cuota_inicial,
                'numero_cuotas' => $venta->numero_cuotas,
                'estado' => $venta->estado,
                'observaciones' => $venta->observaciones,
            ],
            ...$overrides,
        ];
    }
}
