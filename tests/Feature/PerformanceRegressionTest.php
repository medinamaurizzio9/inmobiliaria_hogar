<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Urbanizacion;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PerformanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_map_does_not_query_commercial_settings_per_lot(): void
    {
        $this->seed();
        Storage::fake('public');
        Storage::disk('public')->put('planos/performance.webp', 'image');

        $urbanizacion = Urbanizacion::query()->whereNotNull('slug')->firstOrFail();
        $urbanizacion->update(['plano_imagen' => 'planos/performance.webp']);
        $manzano = $urbanizacion->manzanos()->firstOrFail();

        foreach (range(1, 30) as $number) {
            Lote::create([
                'manzano_id' => $manzano->id,
                'codigo' => 'PERF-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'superficie' => 300,
                'precio' => 20000,
                'cuota_inicial_tipo' => 'monto',
                'cuota_inicial_valor' => 1000,
                'estado' => 'disponible',
                'coord_x' => 20,
                'coord_y' => 30,
            ]);
        }

        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        $this->get(route('disponibilidad.urbanizacion', $urbanizacion->slug))
            ->assertOk()
            ->assertSee('PERF-30');

        $commercialQueries = array_filter($queries, fn (string $sql): bool => str_contains($sql, 'urbanizacion_commercial_settings'));
        $lotQuery = collect($queries)->first(fn (string $sql): bool => str_contains($sql, ' from "lotes"'));

        $this->assertLessThanOrEqual(4, count($commercialQueries));
        $this->assertNotNull($lotQuery);
        $this->assertStringNotContainsString('observaciones', $lotQuery);
        $this->assertStringNotContainsString('created_at', $lotQuery);
    }

    public function test_lot_listing_query_count_does_not_grow_with_pricing_rows(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $manzano = $urbanizacion->manzanos()->firstOrFail();

        foreach (range(1, 55) as $number) {
            Lote::create([
                'manzano_id' => $manzano->id,
                'codigo' => 'QUERY-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'superficie' => 300,
                'precio' => 20000,
                'estado' => 'disponible',
            ]);
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->actingAs($admin)
            ->withSession(['urbanizacion_id' => $urbanizacion->id])
            ->get(route('lotes.index'))
            ->assertOk();

        $this->assertLessThanOrEqual(45, $queryCount);
    }

    public function test_large_report_screens_use_paginators(): void
    {
        $this->seed();
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacion = Urbanizacion::firstOrFail();
        $session = ['urbanizacion_id' => $urbanizacion->id];

        foreach ([
            'reportes.lotes-estado' => 'lotes',
            'reportes.reservas' => 'reservas',
            'reportes.cuotas' => 'cuotas',
            'reportes.ingresos' => 'movimientos',
        ] as $route => $variable) {
            $this->actingAs($admin)
                ->withSession($session)
                ->get(route($route))
                ->assertOk()
                ->assertViewHas($variable, fn ($records): bool => $records instanceof LengthAwarePaginator && $records->perPage() === 50);
        }
    }
}
