<?php

namespace App\Http\Controllers;

use App\Models\ClientImportBatch;
use App\Services\AuditService;
use App\Services\ClientImportService;
use App\Services\UserSpreadsheetService;
use App\Support\UrbanizacionContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

class ClientImportController extends Controller
{
    public function create(): View
    {
        return view('clientes.import.create');
    }

    public function template(UserSpreadsheetService $spreadsheets): Response
    {
        return response($spreadsheets->xlsx(ClientImportService::HEADERS, [
            ['María Pérez', '7519283', '70012345', 'maria@example.com', 'Av. Principal 123', 'asesor@empresa.com'],
        ]), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="plantilla-clientes.xlsx"',
        ]);
    }

    public function preview(Request $request, ClientImportService $imports): RedirectResponse
    {
        $data = $request->validate([
            'archivo' => ['required', 'file', 'max:5120', 'extensions:csv,xlsx', 'mimetypes:text/plain,text/csv,application/csv,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/zip'],
        ], [
            'archivo.max' => 'El archivo no puede superar 5 MB.',
            'archivo.extensions' => 'Use un archivo CSV o XLSX.',
            'archivo.mimetypes' => 'El contenido del archivo no corresponde a CSV o XLSX.',
        ]);

        try {
            $batch = $imports->preview($data['archivo'], $request->user(), UrbanizacionContext::currentId());
        } catch (RuntimeException $exception) {
            return back()->withInput()->withErrors(['archivo' => $exception->getMessage()]);
        }

        return redirect()->route('clientes.import.show', $batch);
    }

    public function show(Request $request, ClientImportBatch $batch): View
    {
        $this->authorizeBatch($request, $batch);

        return view('clientes.import.show', ['batch' => $batch->load('rows.cliente', 'user')]);
    }

    public function confirm(Request $request, ClientImportBatch $batch, ClientImportService $imports, AuditService $audit): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);
        $data = $request->validate(['accepted_matches' => ['nullable', 'array'], 'accepted_matches.*' => ['integer']]);

        try {
            $result = $imports->confirm($batch, $request->user(), $data['accepted_matches'] ?? []);
        } catch (RuntimeException $exception) {
            return back()->withErrors(['import' => $exception->getMessage()]);
        }

        foreach ($result['created'] as $cliente) {
            $audit->log($cliente, 'importar_cliente', 'Cliente creado mediante importación masiva.', null, $cliente->toArray(), $request);
        }

        return redirect()->route('clientes.import.show', $batch)->with('status', 'Importación completada.');
    }

    public function cancel(Request $request, ClientImportBatch $batch, ClientImportService $imports): RedirectResponse
    {
        $this->authorizeBatch($request, $batch);
        $imports->cancel($batch);

        return redirect()->route('clientes.index')->with('status', 'Importación cancelada.');
    }

    public function report(Request $request, ClientImportBatch $batch): Response
    {
        $this->authorizeBatch($request, $batch);
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['fila', 'nombre', 'ci', 'telefono', 'email', 'asesor_email', 'estado', 'detalle']);
        foreach ($batch->rows()->orderBy('row_number')->get() as $row) {
            fputcsv($handle, [$row->row_number, $row->payload['nombre'], $row->payload['documento'], $row->payload['telefono'], $row->payload['email'], $row->payload['asesor_email'], $row->status, $row->detail]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="resultado-importacion-'.$batch->id.'.csv"',
        ]);
    }

    private function authorizeBatch(Request $request, ClientImportBatch $batch): void
    {
        abort_unless((int) $batch->user_id === (int) $request->user()->id, 403);
    }
}
