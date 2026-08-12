<?php

use App\Http\Controllers\AsesorController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashMovementController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\CommercialSettingController;
use App\Http\Controllers\ConfiguracionUrbanizacionGpsController;
use App\Http\Controllers\CuotaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DevolucionController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FinancialSettingController;
use App\Http\Controllers\GrupoComercialController;
use App\Http\Controllers\LoteCommercialUpdateController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\LotImportController;
use App\Http\Controllers\ManzanoController;
use App\Http\Controllers\MapaController;
use App\Http\Controllers\MiCuentaController;
use App\Http\Controllers\PasswordChangeController;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\PublicDisponibilidadController;
use App\Http\Controllers\PublicReceiptVerificationController;
use App\Http\Controllers\ReestructuracionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservaController;
use App\Http\Controllers\SupervisorController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\UrbanizacionAssignmentController;
use App\Http\Controllers\UrbanizacionController;
use App\Http\Controllers\UrbanizacionSelectionController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

Route::get('/disponibilidad', PublicDisponibilidadController::class)->name('disponibilidad.publica');
Route::get('/u/{slug}', [PublicDisponibilidadController::class, 'showBySlug'])->name('disponibilidad.urbanizacion');
Route::get('/recibos/verificar/{numero}', PublicReceiptVerificationController::class)->name('recibos.verificar');

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthController::class, 'showLogin']);
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/cambiar-password', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::post('/cambiar-password', [PasswordChangeController::class, 'update'])->name('password.change.update');

    Route::middleware('password.changed')->group(function (): void {
        Route::middleware('role:cliente')->prefix('mi-cuenta')->group(function (): void {
            Route::get('/', [MiCuentaController::class, 'index'])->name('clientes.mi-cuenta');
            Route::get('/terrenos/{venta}', [MiCuentaController::class, 'show'])->name('portal.terrenos.show');
            Route::get('/terrenos/{venta}/pagar', [MiCuentaController::class, 'pay'])->name('portal.pagar');
            Route::post('/terrenos/{venta}/pagar', [MiCuentaController::class, 'storePayment'])->name('portal.pagar.store');
            Route::get('/terrenos/{venta}/documentos', [MiCuentaController::class, 'documents'])->name('portal.documentos');
            Route::get('/terrenos/{venta}/estado-cuenta.pdf', [PdfController::class, 'saleAccountStatement'])->name('portal.estado-cuenta.pdf');
            Route::get('/terrenos/{venta}/plan.pdf', [PdfController::class, 'paymentPlan'])->name('portal.plan');
            Route::get('/terrenos/{venta}/contrato.pdf', [PdfController::class, 'contract'])->name('portal.contrato');
            Route::get('/recibos/{cashMovement}.pdf', [PdfController::class, 'receipt'])->name('portal.recibo');
        });
        Route::get('/clientes/{cliente}/pdf', [PdfController::class, 'clientProfile'])->name('clientes.pdf');
        Route::get('/clientes/{cliente}/estado-cuenta/pdf', [PdfController::class, 'clientAccountStatement'])->name('clientes.estado-cuenta.pdf');
        Route::get('/clientes/{cliente}/reservas/pdf', [PdfController::class, 'clientReservations'])->name('clientes.reservas.pdf');
        Route::get('/seleccionar-urbanizacion', [UrbanizacionSelectionController::class, 'index'])->name('urbanizaciones.select');
        Route::post('/seleccionar-urbanizacion', [UrbanizacionSelectionController::class, 'store'])->name('urbanizaciones.select.store');
        Route::get('/urbanizaciones/asignaciones', [UrbanizacionAssignmentController::class, 'index'])->name('urbanizaciones.asignaciones');
        Route::put('/urbanizaciones/asignaciones/{user}', [UrbanizacionAssignmentController::class, 'update'])->name('urbanizaciones.asignaciones.update');
        Route::get('/asesores/excel', [AsesorController::class, 'excel'])->middleware('can:exportar reportes')->name('asesores.excel');
        Route::get('/asesores/pdf', [AsesorController::class, 'pdf'])->middleware('can:exportar reportes')->name('asesores.pdf');
        Route::get('/asesores/importar', [AsesorController::class, 'importForm'])->name('asesores.import');
        Route::post('/asesores/importar', [AsesorController::class, 'import'])->name('asesores.import.store');
        Route::get('/asesores/plantilla', [AsesorController::class, 'template'])->name('asesores.template');
        Route::resource('asesores', AsesorController::class)->parameters(['asesores' => 'asesor'])->except('show')->middlewareFor(['index'], 'can:editar asesores')->middlewareFor(['create', 'store'], 'can:crear asesores')->middlewareFor(['edit', 'update'], 'can:editar asesores')->middlewareFor(['destroy'], 'can:desactivar asesores');
        Route::post('/asesores/{asesor}/reset-password', [AsesorController::class, 'resetPassword'])->middleware('can:resetear contraseña asesor')->name('asesores.reset-password');
        Route::get('/supervisores/excel', [SupervisorController::class, 'excel'])->middleware('can:exportar reportes')->name('supervisores.excel');
        Route::get('/supervisores/pdf', [SupervisorController::class, 'pdf'])->middleware('can:exportar reportes')->name('supervisores.pdf');
        Route::resource('supervisores', SupervisorController::class)->parameters(['supervisores' => 'supervisor'])->except('show')->middleware('can:administrar usuarios');
        Route::get('/grupos-comerciales/excel', [GrupoComercialController::class, 'excel'])->middleware('can:exportar reportes')->name('grupos-comerciales.excel');
        Route::get('/grupos-comerciales/pdf', [GrupoComercialController::class, 'pdf'])->middleware('can:exportar reportes')->name('grupos-comerciales.pdf');
        Route::resource('grupos-comerciales', GrupoComercialController::class)->parameters(['grupos-comerciales' => 'grupoComercial'])->except('show')->middlewareFor(['index'], 'can:editar asesores')->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:administrar usuarios');

        Route::prefix('administracion')->name('admin.')->group(function (): void {
            Route::resource('urbanizacion-gps', ConfiguracionUrbanizacionGpsController::class)
                ->parameters(['urbanizacion-gps' => 'urbanizacionReferencia'])
                ->except('show');
        });

        Route::middleware(['urbanizacion.selected', 'urbanizacion.access'])->group(function (): void {
            Route::get('/dashboard', DashboardController::class)->middleware('can:ver dashboard')->name('dashboard');
            Route::get('/mapa', MapaController::class)->middleware('can:ver lotes')->name('mapa');
            Route::get('/mapa/lote/{lote}', [MapaController::class, 'loteJson'])->middleware('can:ver lotes')->name('mapa.lotes.show-json');
            Route::post('/mapa/mi-ubicacion', [MapaController::class, 'myLocation'])->middleware('can:ver lotes')->name('mapa.mi-ubicacion');
            Route::patch('/mapa/lotes/{lote}/posicion', [MapaController::class, 'updateLotePosition'])->middleware('can:editar lotes')->name('mapa.lotes.posicion');
            Route::delete('/mapa/lotes/{lote}/posicion', [MapaController::class, 'clearLotePosition'])->middleware('can:editar lotes')->name('mapa.lotes.posicion.clear');

            Route::resource('urbanizaciones', UrbanizacionController::class)->parameters(['urbanizaciones' => 'urbanizacion'])->except('show')->middlewareFor(['index'], 'can:ver lotes')->middlewareFor(['create', 'store'], 'can:crear urbanizaciones')->middlewareFor(['edit', 'update'], 'can:editar urbanizaciones')->middlewareFor(['destroy'], 'can:eliminar urbanizaciones');
            Route::resource('manzanos', ManzanoController::class)->except('show')->middlewareFor(['index'], 'can:ver lotes')->middlewareFor(['create', 'store'], 'can:crear manzanos')->middlewareFor(['edit', 'update'], 'can:editar manzanos')->middlewareFor(['destroy'], 'can:eliminar manzanos');
            Route::patch('/lotes/{lote}/comercial-rapido', [LoteCommercialUpdateController::class, 'updateQuick'])->middleware('can:ver lotes')->name('lotes.comercial-rapido');
            Route::post('/lotes/comercial-masivo', [LoteCommercialUpdateController::class, 'bulkUpdate'])->middleware('can:ver lotes')->name('lotes.comercial-masivo');
            Route::resource('lotes', LoteController::class)->middlewareFor(['index', 'show'], 'can:ver lotes')->middlewareFor(['create', 'store'], 'can:crear lotes')->middlewareFor(['edit', 'update'], 'can:editar lotes')->middlewareFor(['destroy'], 'can:eliminar lotes');
            Route::get('/clientes/buscar', [ClienteController::class, 'buscar'])->middleware('can:ver clientes')->name('clientes.buscar');
            Route::resource('clientes', ClienteController::class)->middlewareFor(['index', 'show'], 'can:ver clientes')->middlewareFor(['create', 'store'], 'can:crear clientes')->middlewareFor(['edit', 'update'], 'can:editar clientes')->middlewareFor(['destroy'], 'can:eliminar clientes');
            Route::resource('ventas', VentaController::class)->middlewareFor(['index', 'show'], 'can:ver ventas')->middlewareFor(['edit', 'update'], 'can:editar ventas')->middlewareFor(['create', 'store'], 'can:crear ventas')->middlewareFor(['destroy'], 'can:anular ventas');
            Route::get('/ventas/{venta}/reestructurar', [ReestructuracionController::class, 'create'])->name('ventas.reestructurar');
            Route::post('/ventas/{venta}/reestructurar', [ReestructuracionController::class, 'store'])->name('ventas.reestructurar.store');
            Route::get('/ventas/{venta}/rescindir', [DevolucionController::class, 'create'])->name('ventas.rescindir');
            Route::post('/ventas/{venta}/rescindir', [DevolucionController::class, 'store'])->name('ventas.rescindir.store');
            Route::resource('reservas', ReservaController::class)->except('show')->middlewareFor(['index'], 'can:ver reservas')->middlewareFor(['create', 'store'], 'can:crear reservas')->middlewareFor(['destroy'], 'can:cancelar reservas');
            Route::get('reservas/{reserva}/recibo', [PdfController::class, 'reservationReceipt'])->name('reservas.recibo');
            Route::post('reservas/{reserva}/vencer', [ReservaController::class, 'expire'])->middleware('can:cancelar reservas')->name('reservas.expire');
            Route::resource('cuotas', CuotaController::class)->middleware('can:cobrar cuotas')->only('index', 'update');
            Route::get('/lotes-importar', [LotImportController::class, 'create'])->middleware('can:crear lotes')->name('lotes.import.create');
            Route::post('/lotes-importar/preview', [LotImportController::class, 'preview'])->middleware('can:crear lotes')->name('lotes.import.preview');
            Route::post('/lotes-importar', [LotImportController::class, 'store'])->middleware('can:crear lotes')->name('lotes.import.store');

            Route::get('/caja', [CashMovementController::class, 'index'])->middleware('can:cobrar cuotas')->name('caja.index');
            Route::get('/caja/{cashMovement}', [CashMovementController::class, 'show'])->middleware('can:cobrar cuotas')->name('caja.show');
            Route::post('/caja/{cashMovement}/confirmar', [CashMovementController::class, 'confirm'])->middleware('can:cobrar cuotas')->name('caja.confirm');
            Route::post('/caja/{cashMovement}/rechazar', [CashMovementController::class, 'reject'])->middleware('can:cobrar cuotas')->name('caja.reject');
            Route::post('/caja/{cashMovement}/anular', [CashMovementController::class, 'annul'])->middleware('can:anular caja')->name('caja.annul');

            Route::get('/pdf/recibo/{cashMovement}', [PdfController::class, 'receipt'])->name('pdf.recibo');
            Route::get('/pdf/plan-pagos/{venta}', [PdfController::class, 'paymentPlan'])->name('pdf.plan');
            Route::get('/pdf/contrato/{venta}', [PdfController::class, 'contract'])->name('pdf.contrato');

            Route::middleware('can:ver reportes')->prefix('reportes')->name('reportes.')->group(function (): void {
                Route::get('/', [ReportController::class, 'index'])->name('index');
                Route::get('/lotes-estado', [ReportController::class, 'lotesEstado'])->name('lotes-estado');
                Route::get('/reservas', [ReportController::class, 'reservas'])->middleware('can:ver reporte reservas')->name('reservas');
                Route::get('/reservas/excel', [ReportController::class, 'reservasExcel'])->middleware('can:exportar reporte reservas')->name('reservas.excel');
                Route::get('/reservas/pdf', [ReportController::class, 'reservasPdf'])->middleware('can:exportar reporte reservas')->name('reservas.pdf');
                Route::get('/cuotas', [ReportController::class, 'cuotas'])->name('cuotas');
                Route::get('/ingresos', [ReportController::class, 'ingresos'])->name('ingresos');
                Route::get('/estado-cuenta', [ReportController::class, 'estadoCuenta'])->name('estado-cuenta');
                Route::get('/mejor-vendedor', [ReportController::class, 'mejorVendedor'])->middleware('can:ver reporte mejor vendedor')->name('mejor-vendedor');
                Route::get('/mejor-vendedor/excel', [ReportController::class, 'mejorVendedorExcel'])->middleware('can:exportar reporte mejor vendedor')->name('mejor-vendedor.excel');
                Route::get('/mejor-vendedor/pdf', [ReportController::class, 'mejorVendedorPdf'])->middleware('can:exportar reporte mejor vendedor')->name('mejor-vendedor.pdf');
                Route::get('/exportaciones', [ReportController::class, 'exportaciones'])->name('exportaciones');
                Route::get('/{reporte}/csv', [ReportController::class, 'csv'])->middleware('can:exportar reportes')->name('csv');
            });

            Route::prefix('administracion')->name('admin.')->group(function (): void {
                Route::get('/configuracion-comercial', [CommercialSettingController::class, 'edit'])->name('configuracion');
                Route::put('/configuracion-comercial', [CommercialSettingController::class, 'update'])->name('configuracion.update');
                Route::get('/configuracion-financiera', [FinancialSettingController::class, 'edit'])->name('configuracion-financiera');
                Route::put('/configuracion-financiera', [FinancialSettingController::class, 'update'])->name('configuracion-financiera.update');
            });

            Route::middleware('can:administrar usuarios')->prefix('administracion')->name('admin.')->group(function (): void {
                Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios');
                Route::get('/usuarios/create', [UsuarioController::class, 'create'])->name('usuarios.create');
                Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
                Route::get('/usuarios/importar', [UsuarioController::class, 'importForm'])->name('usuarios.import');
                Route::post('/usuarios/importar', [UsuarioController::class, 'import'])->name('usuarios.import.store');
                Route::get('/usuarios/exportar', [UsuarioController::class, 'export'])->name('usuarios.export');
                Route::get('/usuarios/plantilla', [UsuarioController::class, 'template'])->name('usuarios.template');
                Route::get('/usuarios/{usuario}/edit', [UsuarioController::class, 'edit'])->name('usuarios.edit');
                Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
                Route::delete('/usuarios/{usuario}', [UsuarioController::class, 'destroy'])->name('usuarios.destroy');

                Route::get('/roles-permisos', function () {
                    $roles = Role::with('permissions')
                        ->orderBy('name')
                        ->get();

                    $permissions = Permission::orderBy('name')->get();

                    return view('administracion.roles.index', compact('roles', 'permissions'));
                })->name('roles');

                Route::get('/configuracion-general', [SystemSettingController::class, 'edit'])->name('configuracion-general');
                Route::put('/configuracion-general', [SystemSettingController::class, 'update'])->name('configuracion-general.update');

                Route::get('/auditoria', function () {
                    $audits = DB::table('audit_logs')
                        ->latest('created_at')
                        ->paginate(15);

                    return view('administracion.auditoria.index', compact('audits'));
                })->name('auditoria');

                Route::get('/backups', function () {
                    return view('administracion.backups.index');
                })->name('backups');
            });
        });
        Route::get('/exportar/{tipo}', ExportController::class)->middleware('can:exportar reportes')->name('export.csv');

    });
});
