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

Fase 1C.2: autorización estricta para modificar cuotas con pagos o pagadas
(regla F-32).

Fecha:

2026-08-11

Resultado:

Implementado y verificado con tests:

- Permiso nuevo `modificar cuotas` (solo rol `administrador`) en
  `DatabaseSeeder`. La lista de permisos del seed no perdió ningún
  elemento; se conservó `ver reservas equipo`.
- `CuotaController::update()`: regla de autorización F-32. Modificar una
  cuota que ya tenga pagos (`monto_pagado > 0`) o esté pagada requiere el
  permiso `modificar cuotas`; quien no lo tenga recibe **403**.
  El permiso `cobrar cuotas` sigue permitiendo registrar pagos sobre
  cuotas pendientes/vencidas (cajero/gerente conservan `cobrar cuotas`).
- La validación por excepción de rol (302 con error) se reemplazó por
  autorización backend genuina (403), alineada con AGENTS.md (no depender
  solo de ocultar botones). Rol `vendedor` y `supervisor` ya recibían 403
  por el middleware `can:cobrar cuotas` en la ruta de `cuotas.update`.
- Tests en `StabilityAuditTest`:
  - gerente → 403 al modificar cuota pagada (antes 302 con error);
  - cajero → 403 al modificar cuota pagada;
  - supervisor → 403 al modificar cuota pagada;
  - vendedor → 403 al modificar cuota pagada (se mantuvo);
  - administrador → puede modificar cuota pagada (redirect + monto no
    disminuye).

Nota: sin commit ni push, en rama `hogar-inmobiliaria`.

Archivos principales modificados:

- `database/seeders/DatabaseSeeder.php`
- `app/Http/Controllers/CuotaController.php`
- `tests/Feature/StabilityAuditTest.php`

Migraciones:

- Ninguna nueva (permiso gestionado por seeder).

Pruebas ejecutadas:

```bash
php artisan test tests/Feature/StabilityAuditTest.php tests/Feature/PagoAplicacionesTest.php
# 36 passed, 118 assertions
php artisan test
# 406 passed, 1568 assertions
```

Pendiente (siguiente tarea): amortización extraordinaria F-11 y revisión
UX de pago anticipado en `cuotas/index`.
