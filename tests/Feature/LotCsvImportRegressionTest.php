<?php

namespace Tests\Feature;

use App\Models\Lote;
use App\Models\Urbanizacion;
use App\Models\User;
use App\Services\LotCsvImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class LotCsvImportRegressionTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = 'urbanizacion,manzano,lote,superficie_m2,precio_m2,precio_total,cuota_inicial_tipo,cuota_inicial_valor,estado,coord_x,coord_y,observaciones';

    protected function tearDown(): void
    {
        @unlink(storage_path('framework/testing-lotes-regression.csv'));

        parent::tearDown();
    }

    public function test_valid_csv_preview_works_without_creating_lots(): void
    {
        $this->seed();
        $before = Lote::count();
        $response = $this->preview(self::HEADER."\r\nCSV PREVIEW,A,9001,300,60,18000,monto,0,disponible,25,30,Correcto");

        $response->assertOk()->assertSee('Archivo valido. 1 lotes listos para importar.')
            ->assertSessionHas('lotes_import_rows');
        $this->assertSame($before, Lote::count());
    }

    public function test_row_with_missing_column_returns_structural_error_instead_of_500(): void
    {
        $this->seed();
        $response = $this->preview(self::HEADER."\nCSV FALTANTE,A,9002,300,60,18000,monto,0,disponible,25,30");

        $response->assertOk()->assertSee('Línea 2: estructura CSV inválida. Se esperaban 12 columnas y se encontraron 11.')
            ->assertSessionMissing('lotes_import_rows');
    }

    public function test_row_with_additional_column_returns_structural_error_instead_of_500(): void
    {
        $this->seed();
        $response = $this->preview(self::HEADER."\nCSV EXTRA,A,9003,300,60,18000,monto,0,disponible,25,30,Observación,Sobrante");

        $response->assertOk()->assertSee('Línea 2: estructura CSV inválida. Se esperaban 12 columnas y se encontraron 13.')
            ->assertSessionMissing('lotes_import_rows');
    }

    public function test_empty_field_keeps_its_position_and_quoted_comma_is_one_field(): void
    {
        $this->seed();
        $path = $this->csvPath(self::HEADER."\nCSV CAMPOS,A,9004,300,60,18000,monto,0,disponible,25,30,\nCSV COMILLA,A,9005,300,60,18000,monto,0,disponible,25,30,\"Frente a plaza, sobre avenida\"");
        $result = app(LotCsvImportService::class)->parse($path);

        $this->assertSame([], $result['errors']);
        $this->assertSame('', $result['rows'][0]['observaciones']);
        $this->assertSame('Frente a plaza, sobre avenida', $result['rows'][1]['observaciones']);
    }

    public function test_blank_lines_are_ignored_and_bom_header_is_recognized_with_lf(): void
    {
        $this->seed();
        $path = $this->csvPath("\xEF\xBB\xBF".self::HEADER."\n\nCSV BOM,A,9006,300,60,18000,monto,0,disponible,25,30,BOM\n");
        $result = app(LotCsvImportService::class)->parse($path);

        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame('9006', $result['rows'][0]['lote']);
    }

    public function test_semicolon_delimiter_preserves_empty_and_quoted_fields(): void
    {
        $this->seed();
        $header = str_replace(',', ';', self::HEADER);
        $path = $this->csvPath($header."\r\nCSV PUNTO COMA;A;9007;300,00;60,00;18000,00;monto;0;disponible;25;30;\"Texto, con coma\"");
        $result = app(LotCsvImportService::class)->parse($path);

        $this->assertSame([], $result['errors']);
        $this->assertSame('300.00', $result['rows'][0]['superficie_m2']);
        $this->assertSame('Texto, con coma', $result['rows'][0]['observaciones']);
    }

    public function test_empty_header_only_and_invalid_files_return_controlled_errors(): void
    {
        $this->seed();

        $empty = app(LotCsvImportService::class)->parse($this->csvPath(''));
        $this->assertSame([], $empty['rows']);
        $this->assertContains('El archivo CSV está vacío.', $empty['errors']);

        $headerOnly = app(LotCsvImportService::class)->parse($this->csvPath(self::HEADER));
        $this->assertSame([], $headerOnly['rows']);
        $this->assertContains('El archivo CSV no contiene filas de datos para importar.', $headerOnly['errors']);

        $invalid = app(LotCsvImportService::class)->parse($this->csvPath("contenido totalmente inválido\notra línea"));
        $this->assertSame([], $invalid['rows']);
        $this->assertNotEmpty($invalid['errors']);
    }

    public function test_invalid_preview_cannot_produce_partial_import(): void
    {
        $this->seed();
        $before = Lote::count();
        $contents = self::HEADER."\nCSV SIN PARCIAL,A,9008,300,60,18000,monto,0,disponible,25,30,Válido\nCSV SIN PARCIAL,A,9009,300,60,18000,monto,0,disponible,25,30";

        $this->preview($contents)->assertOk()->assertSessionMissing('lotes_import_rows');

        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacionId = Urbanizacion::firstOrFail()->id;
        $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacionId])
            ->post(route('lotes.import.store'))->assertRedirect(route('lotes.import.create'))->assertSessionHasErrors();

        $this->assertSame($before, Lote::count());
        $this->assertDatabaseMissing('urbanizaciones', ['nombre' => 'CSV SIN PARCIAL']);
    }

    private function preview(string $contents)
    {
        $admin = User::where('email', 'admin@impacto.test')->firstOrFail();
        $urbanizacionId = Urbanizacion::firstOrFail()->id;
        $path = $this->csvPath($contents);

        return $this->actingAs($admin)->withSession(['urbanizacion_id' => $urbanizacionId])
            ->post(route('lotes.import.preview'), ['csv' => new UploadedFile($path, 'lotes.csv', 'text/csv', null, true)]);
    }

    private function csvPath(string $contents): string
    {
        $path = storage_path('framework/testing-lotes-regression.csv');
        file_put_contents($path, $contents);

        return $path;
    }
}
