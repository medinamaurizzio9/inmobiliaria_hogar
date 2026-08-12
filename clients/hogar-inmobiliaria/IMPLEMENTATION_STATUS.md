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
- [x] Semicontado implementado como modalidad financiada real, reutilizando
  ventas, cuotas, pagos y el motor financiero existente.
- [x] Configuración financiera administrativa centralizada para medios de
  pago, alertas, mora y límites de financiamiento.

---

# 4. Próxima tarea

Esta sección es la principal referencia para continuar el desarrollo.

## NEXT_TASK

Título:

Fase 4 — Cobranza / Caja Rápida: buscador único, cobro mediante modal,
previsualización, confirmación y recibo inmediato.

Prioridad:

`HIGH`

Estado:

`pending`

Objetivo:

Optimizar la cobranza interna con búsqueda única, previsualización de deuda por
terreno, confirmación segura y emisión inmediata del recibo.

Archivos probablemente involucrados:

- `app/Http/Controllers/CashMovementController.php`
- `app/Services/CashMovementService.php`
- `app/Services/PaymentAllocationService.php`
- `resources/views/caja/`

Documentación requerida:

- `/AGENTS.md`
- `./MODULES.md`
- `./BUSINESS_OVERRIDES.md` (reglas F-9, F-10, F-20, F-22 y F-23)
- `/docs/06-ARCHITECTURE.md`

Agregar únicamente los documentos CORE necesarios.

---

## Criterios de aceptación

La tarea estará terminada cuando:

- [ ] buscador único identifica cliente/terreno/cuota;
- [ ] previsualización no modifica saldos;
- [ ] confirmación reutiliza el motor financiero;
- [ ] recibo inmediato disponible;
- [ ] pruebas correspondientes pasan.

---

# 5. Última tarea completada

## LAST_COMPLETED_TASK

Título:

Fase 3 — Cuenta automática de cliente y Portal Financiero.

Fecha:

2026-08-12

Resultado:

Implementado y verificado con tests:

- Al cerrar una venta se crea un único `User` vinculado al cliente cuando existe
  correo válido; se asigna rol cliente y cambio obligatorio de contraseña.
- Contraseña temporal criptográficamente aleatoria, almacenada solo como hash y
  mostrada una vez al usuario interno mediante sesión flash.
- Venta preservada con advertencia administrativa cuando falta correo válido.
- Portal móvil por terreno con saldos independientes, cuotas, pagos, alertas,
  documentos y estados financieros.
- Solicitudes QR/transferencia reutilizan `CashMovementService`, quedan pendientes
  y no modifican cuotas ni crean aplicaciones hasta su confirmación.
- Estado de cuenta PDF por terreno, contratos, planes y recibos confirmados.
- Ownership backend por `auth()->user()->cliente_id` para todos los recursos.

Nota: sin commit ni push, en rama `hogar-inmobiliaria`.

Archivos principales modificados:

- `app/Services/ClientAccountProvisioner.php`
- `app/Services/SaleService.php`
- `app/Http/Controllers/MiCuentaController.php`
- `app/Http/Controllers/PdfController.php`
- `resources/views/clientes/mi-cuenta.blade.php`
- `resources/views/clientes/terreno.blade.php`
- `resources/views/clientes/pagar.blade.php`
- `resources/views/clientes/documentos.blade.php`
- `resources/views/pdf/venta-estado-cuenta.blade.php`
- `routes/web.php`
- `tests/Feature/ClientPortalTest.php`

Migraciones:

- Ninguna. Se reutilizaron `users.cliente_id`, `must_change_password`, ventas,
  cuotas, `cash_movements` y `pago_aplicaciones` existentes.

Pruebas ejecutadas:

```bash
php artisan test tests/Feature/ClientPortalTest.php
# 12 passed, 55 assertions
php artisan test
# 473 passed, 1788 assertions
```

Pendiente (siguiente tarea): Fase 4 — Cobranza / Caja Rápida.
