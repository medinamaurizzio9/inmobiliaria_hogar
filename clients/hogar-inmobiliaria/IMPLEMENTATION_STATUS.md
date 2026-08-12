# IMPLEMENTATION_STATUS.md

# Estado de Implementación del Cliente

Este documento registra el estado REAL de implementación de esta instancia.

Su objetivo principal es permitir que una nueva sesión de desarrollo pueda
continuar el proyecto sin reconstruir todo el contexto.

Este archivo debe mantenerse corto, actualizado y basado en hechos.

---

# 1. Identificación

Cliente:

Hogar Inmobiliaria

Código cliente:

Proyecto:

Repositorio:

Rama actual:

Entorno:

- [ ] Local
- [ ] Development
- [ ] Staging
- [ ] Production

Fecha última actualización:

2026-08-11

Actualizado por:

Agente de desarrollo

Versión CORE:

---

# 2. Estado general

Estado actual:

`development`

Estados permitidos:

- planning
- development
- testing
- ready_for_deploy
- production
- maintenance
- blocked

Progreso estimado:

`0%`

IMPORTANTE:

El porcentaje es únicamente orientativo.

Las pruebas y criterios de aceptación tienen prioridad sobre el porcentaje.

---

# 3. Objetivo actual

## Fase actual

Nombre:

Diseño financiero / créditos

Estado:

`development`

Objetivo:

Configurar el CORE Terrenos para Hogar Inmobiliaria
sin modificar todavía las reglas globales del CORE.

Descripción breve:

---

## Resultado esperado

Al finalizar esta fase debe existir:

- [x] Esquema financiero actual auditado (ventas, pagos, cuotas, clientes,
  usuarios, roles, recibos y reportes).
- [x] Gaps entre el CORE y las reglas F-1..F-37 identificados.
- [x] Diseño técnico del módulo financiero aprobado (en este documento).
- [x] Flujo de pagos con verificación implementado.
- [x] Excedentes de pago aplicados a cuotas siguientes de la misma venta
  (F-10) con trazabilidad en `pago_aplicaciones`.

---

# 4. Próxima tarea

Esta sección es la principal referencia para continuar el desarrollo.

## NEXT_TASK

Título:

Amortización extraordinaria (F-11) y seguimiento post-F-10.

Prioridad:

`MEDIUM`

Estado:

`pending`

Objetivo:

Sobre el pago multi-cuota ya implementado (F-10):

- evaluar si la amortización extraordinaria F-11 debe REDUCIR el monto de
  las cuotas restantes (adelanto de capital) o si el pago anticipado a
  cuotas futuras implementado satisface la regla;
- si se confirma la reducción de cuotas restantes, diseñar e implementar
  la opción de "amortización" sin intereses;
- revisar posibles botones/UX de pago en `cuotas/index` para el pago
  anticipado multi-cuota.

Archivos probablemente involucrados:

- `app/Services/PaymentAllocationService.php`
- `app/Models/Cuota.php`
- `app/Models/Venta.php`
- `app/Http/Controllers/CuotaController.php`
- `resources/views/cuotas/index.blade.php`

Documentación requerida:

- `/AGENTS.md`
- `./MODULES.md`
- `./BUSINESS_OVERRIDES.md` (regla F-11)

Agregar únicamente los documentos CORE necesarios.

---

## Criterios de aceptación

La tarea estará terminada cuando:

- [ ] el comportamiento F-11 queda definido documentalmente (satisfecho por
  el anticipo a cuotas o reducción de cuotas restantes);
- [ ] si aplica, la amortización extraordinaria reduce cuotas restantes sin
  intereses (F-11);
- [ ] pruebas correspondientes pasan;
- [ ] no existen regresiones conocidas.

---

# 5. Última tarea completada

## LAST_COMPLETED_TASK

Título:

Fase 1C.1: pagos multi-cuota y excedentes (F-10) con rol cajero.

Fecha:

2026-08-11

Resultado:

Implementado y verificado con tests:

- Migración `2026_08_11_000001_create_pago_aplicaciones_table`:
  `pago_aplicaciones` (cash_movement_id, cuota_id, monto_aplicado).
  Tabla de trazabilidad: la aplicación de un pago a cuotas queda
  registrada y NO se elimina al anular el pago.
- Modelo `PagoAplicacion` (tabla opcional `pago_aplicaciones`).
- `PaymentAllocationService::allocate()`: distribuye un pago confirmado
  sobre la cuota seleccionada y luego las demás cuotas pendientes o
  parciales de la MISMA venta ordenadas por `fecha_vencimiento` e id;
  nunca aplica más que el saldo pendiente de cada cuota ni más que el
  monto del pago; el sobrante no aplicado no se pierde en el movimiento.
  Bloquea cuotas con `lockForUpdate` dentro de la transacción.
  Audita `cobrar_cuota` por cuota y `pago_aplicado` por movimiento.
- `PaymentAllocationService::reverse()`: al anular un pago revierte las
  cuotas afectadas por sus aplicaciones (nunca más de lo aplicado),
  conserva `pago_aplicaciones` como historial y audita
  `cuota_restaurada_por_anulacion`.
- `PaymentAllocationService::cuotaEstado()`: estado centralizado
  (pagada / parcial / vencida / pendiente), compartido con la anulación.
- `InstallmentService::pay()` y `CashMovementService::confirm()`:
  reutilizan `allocate()`; el pago ya no se limita al saldo de una sola
  cuota. Se eliminó la regla que rechazaba montos superiores al saldo de
  la cuota seleccionada.
- `CashMovementService::annul()`: si el movimiento tiene aplicaciones usa
  `reverse()`; si no (datos históricos), conserva el camino anterior
  (`restoreCuotaAfterAnnulment`) para compatibilidad.
- Rol `cajero` (migración `2026_08_11_000002` y `DatabaseSeeder`):
  permisos mínimos (`cobrar cuotas`, ver dashboard/lotes/clientes y
  recibos de reserva). El rol `vendedor` ya NO tiene `cobrar cuotas`.
- `CashMovementController` y vistas `caja/index` y `caja/show`: el rol
  `cajero` puede ver Caja y cobrar cuotas; confirmar/rechazar sigue
  reservado a administrador/gerente; anular sigue reservado a
  `can:anular caja`. Sidebar muestra Finanzas a cajero.
- Recibo PDF multi-cuota: `PdfController` y `pdf/recibo` muestran el
  desglose de `pago_aplicaciones` y el saldo restante del terreno.
- Tests: `PagoAplicacionesTest` (25 escenarios: distribución en varias
  cuotas, excedente por orden de vencimiento, aislamiento de venta,
  anticipado, anulación total/parcial con restauración, compatibilidad
  histórica sin aplicaciones, recibo y detalle de caja multi-cuota,
  permisos del rol cajero y restricciones vendedor/supervisor).
  `StabilityAuditTest` ajustado: vendedor hoy recibe 403 (sin
  `cobrar cuotas`) y el caso "modificar cuota pagada" se valida con
  gerente (`assertSessionHasErrors`).

Nota: sin commit ni push, en rama `hogar-inmobiliaria`.

Archivos principales modificados:

- `app/Models/PagoAplicacion.php` (nuevo)
- `app/Services/PaymentAllocationService.php` (nuevo)
- `database/migrations/2026_08_11_000001_create_pago_aplicaciones_table.php`
- `database/migrations/2026_08_11_000002_add_cajero_role_and_financial_permissions.php`
- `app/Models/CashMovement.php`
- `app/Models/Cuota.php`
- `app/Services/CashMovementService.php`
- `app/Services/InstallmentService.php`
- `app/Services/AuditService.php`
- `database/seeders/DatabaseSeeder.php`
- `app/Http/Requests/PayCuotaRequest.php`
- `app/Http/Controllers/CashMovementController.php`
- `app/Http/Controllers/CuotaController.php`
- `app/Http/Controllers/PdfController.php`
- `routes/web.php`
- `resources/views/caja/index.blade.php`
- `resources/views/caja/show.blade.php`
- `resources/views/layouts/partials/sidebar.blade.php`
- `resources/views/pdf/recibo.blade.php`
- `public/css/app.css`
- `tests/Feature/PagoAplicacionesTest.php` (nuevo)
- `tests/Feature/StabilityAuditTest.php`

Migraciones:

- `2026_08_11_000001_create_pago_aplicaciones_table`
- `2026_08_11_000002_add_cajero_role_and_financial_permissions`

Pruebas ejecutadas:

```bash
php artisan test
# 403 passed, 1563 assertions
vendor/bin/pint --dirty
# applied
```

Pendiente (siguiente tarea): amortización extraordinaria F-11 y revisión
UX de pago anticipado en `cuotas/index`.
