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
- [x] Amortización extraordinaria aplicada desde las últimas cuotas (F-11),
  conservando la mensualidad y reduciendo el plazo pendiente.

---

# 4. Próxima tarea

Esta sección es la principal referencia para continuar el desarrollo.

## NEXT_TASK

Título:

Reestructuración administrativa del plan de pagos (F-33).

Prioridad:

`HIGH`

Estado:

`pending`

Objetivo:

Implementar F-33 sin editar cuotas históricas: registrar saldo anterior,
cuotas pendientes anteriores, nuevo plazo, nuevas cuotas, fecha,
administrador y observaciones, con autorización, auditoría y transacción.

Archivos probablemente involucrados:

- `app/Services/InstallmentService.php`
- `app/Models/Cuota.php`
- `app/Models/Venta.php`
- controlador/request/vista administrativa por definir tras revisar el flujo
  existente de edición de venta;
- migración nueva solo si el esquema actual no permite conservar el historial.

Documentación requerida:

- `/AGENTS.md`
- `./MODULES.md`
- `./BUSINESS_OVERRIDES.md` (regla F-33)
- `/docs/05-DATABASE_BASE.md`
- `/docs/06-ARCHITECTURE.md`

Agregar únicamente los documentos CORE necesarios.

---

## Criterios de aceptación

La tarea estará terminada cuando:

- [ ] las cuotas históricas no se editan ni eliminan;
- [ ] la reestructuración conserva el antes y después con responsable y motivo;
- [ ] solo administrador puede ejecutarla;
- [ ] pruebas correspondientes pasan;
- [ ] no existen regresiones conocidas.

---

# 5. Última tarea completada

## LAST_COMPLETED_TASK

Título:

Amortización extraordinaria F-11 y UX post-F-10.

Fecha:

2026-08-11

Resultado:

Implementado y verificado con tests:

- F-11 queda diferenciado de F-10: un cobro normal aplica desde la cuota
  elegida hacia las siguientes; una amortización aplica desde la última cuota
  pendiente hacia atrás.
- La amortización conserva el monto pactado de las mensualidades completas,
  reduce la cantidad de cuotas pendientes y deja solo una cuota residual
  cuando el monto no completa una mensualidad.
- El movimiento se registra con concepto `amortizacion` y cada importe queda
  trazado en `pago_aplicaciones`; al anularlo se restaura el plan afectado.
- Se rechaza una amortización mayor al saldo pendiente de la venta dentro de
  la transacción, sin dejar movimiento de caja parcial.
- `cuotas/index` permite elegir entre `Cobrar próximas` (F-10) y
  `Amortizar plazo` (F-11), usando el saldo total de la venta como límite.
- Se conserva la autorización backend existente `cobrar cuotas` y el
  aislamiento por urbanización.

Nota: sin commit ni push, en rama `hogar-inmobiliaria`.

Archivos principales modificados:

- `app/Services/PaymentAllocationService.php`
- `app/Services/InstallmentService.php`
- `app/Services/CashMovementService.php`
- `app/Models/CashMovement.php`
- `app/Http/Controllers/CuotaController.php`
- `app/Http/Requests/PayCuotaRequest.php`
- `resources/views/cuotas/index.blade.php`
- `tests/Feature/PagoAplicacionesTest.php`

Migraciones:

- Ninguna nueva.

Pruebas ejecutadas:

```bash
php artisan test tests/Feature/PagoAplicacionesTest.php tests/Feature/PagoVerificacionTest.php tests/Feature/CashMovementAnnulTest.php tests/Feature/StabilityAuditTest.php
# 94 passed, 276 assertions
php artisan test
# 410 passed, 1591 assertions
```

Pendiente (siguiente tarea): reestructuración administrativa F-33 con
preservación del historial de cuotas.
