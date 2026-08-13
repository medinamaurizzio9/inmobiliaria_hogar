<?php

namespace Tests\Feature;

use App\Models\Cuota;
use App\Models\SystemSetting;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Services\SystemSettingsService;
use App\Support\WhatsAppLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardOverdueWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_table_shows_phone_and_encoded_whatsapp_message(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $cuota = $this->overdueInstallment($urbanizacion);
        $cuota->venta->cliente->update(['telefono' => '6016-9612']);
        SystemSetting::updateOrCreate(['key' => 'company_name'], ['value' => 'Hogar Inmobiliaria']);
        app(SystemSettingsService::class)->setMany(['company_name' => 'Hogar Inmobiliaria']);

        $message = "Hola {$cuota->venta->cliente->nombre}, le contactamos de Hogar Inmobiliaria para informarle que registra una cuota pendiente correspondiente al lote {$cuota->venta->lote->manzano->codigo}-{$cuota->venta->lote->codigo}. Puede comunicarse con nosotros para coordinar su pago. Gracias.";
        $url = 'https://wa.me/59160169612?text='.rawurlencode($message);

        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Celular')
            ->assertSee('6016-9612')
            ->assertSee('href="'.$url.'"', false)
            ->assertSee('WhatsApp');
    }

    public function test_country_prefix_is_not_duplicated(): void
    {
        $this->assertSame('59160169612', WhatsAppLink::phone('+591 60169612'));
        $this->assertSame('59160169612', WhatsAppLink::phone('60169612'));
    }

    public function test_missing_or_invalid_phone_does_not_render_whatsapp_link(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $cuota = $this->overdueInstallment($urbanizacion);
        $cuota->venta->cliente->update(['telefono' => null]);

        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacion->id])->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Sin celular')
            ->assertDontSee('https://wa.me/', false);

        $this->assertNull(WhatsAppLink::phone('123'));
    }

    public function test_user_without_client_and_collection_permissions_does_not_see_contact_data(): void
    {
        $this->seed();
        $urbanizacion = Urbanizacion::firstOrFail();
        $cuota = $this->overdueInstallment($urbanizacion);
        $cuota->venta->cliente->update(['telefono' => '60169612']);
        $user = User::where('email', 'vendedor@impacto.test')->firstOrFail();
        $user->urbanizacionesAsignadas()->syncWithoutDetaching([$urbanizacion->id => ['activo' => true]]);

        $this->actingAs($user)->withSession(['urbanizacion_id' => $urbanizacion->id])->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('60169612')
            ->assertDontSee('https://wa.me/', false)
            ->assertDontSee('Contacto');
    }

    private function overdueInstallment(Urbanizacion $urbanizacion): Cuota
    {
        $cuota = Cuota::with('venta.cliente', 'venta.lote.manzano')
            ->whereHas('venta.lote.manzano', fn ($query) => $query->where('urbanizacion_id', $urbanizacion->id))
            ->firstOrFail();
        $cuota->update(['estado' => 'vencida', 'fecha_programada' => today()->subDay(), 'fecha_vencimiento' => today()->subDay()]);

        return $cuota->fresh(['venta.cliente', 'venta.lote.manzano']);
    }
}
