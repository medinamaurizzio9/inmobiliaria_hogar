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

Fase 2.2 — Reestructuración administrativa F-33 y devoluciones/rescisiones F-34/F-35.

Prioridad:

`HIGH`

Estado:

`pending`

Objetivo:

Diseñar e implementar reestructuraciones sin editar cuotas históricas y el
flujo trazable de devolución/rescisión, incluyendo monto pagado, devuelto y
retenido para reportes financieros.

Archivos probablemente involucrados:

- `app/Services/InstallmentService.php`
- `app/Services/CashMovementService.php`
- `app/Models/Venta.php`
- `app/Models/Cuota.php`
- controlador/request/vista administrativa por definir tras revisar el flujo existente.

Documentación requerida:

- `/AGENTS.md`
- `./MODULES.md`
- `./BUSINESS_OVERRIDES.md` (reglas F-33, F-34 y F-35)
- `/docs/05-DATABASE_BASE.md`
- `/docs/06-ARCHITECTURE.md`

Agregar únicamente los documentos CORE necesarios.

---

## Criterios de aceptación

La tarea estará terminada cuando:

- [ ] cuotas históricas preservadas;
- [ ] reestructuración registra antes/después, administrador y motivo;
- [ ] rescisión conserva pagos y registra devolución/retención;
- [ ] monto retenido aparece en reportes;
- [ ] pruebas correspondientes pasan;
- [ ] no existen regresiones conocidas.

---

# 5. Última tarea completada

## LAST_COMPLETED_TASK

Título:

Fase 2.1 — Configuración financiera administrativa.

Fecha:

2026-08-11

Resultado:

Implementado y verificado con tests:

- Pantalla única `Configuración financiera`: administrador modifica, gerente
  consulta; cajero, supervisor, vendedor y cliente no modifican.
- QR institucional global con imagen en Storage público, descripción y estado.
- Cuenta bancaria institucional global con banco, titular, cuenta, tipo,
  moneda, instrucciones y estado, sin integración bancaria.
- `dias_aviso_vencimiento` global (default 3, rango 0..30).
- Alertas calculadas `proxima_vencer`/`vencida` sin agregar estados a cuotas;
  excluyen pagadas y preservan el saldo real de cuotas parciales.
- Mora configurable y deshabilitada por defecto. No existe cálculo automático
  ni modificación de capital, cuota o saldo contractual.
- Límites de semicontado/crédito integrados en la misma pantalla y conservados
  por urbanización.
- `paymentInstructions()` expone solo QR y cuenta activos, sin parámetros
  administrativos.
- Cambios auditados con usuario y valores anteriores/nuevos.

Nota: sin commit ni push, en rama `hogar-inmobiliaria`.

Archivos principales modificados:

- `app/Services/FinancialSettingsService.php`
- `app/Services/PaymentAlertService.php`
- `app/Services/CommercialSettingsService.php`
- `app/Http/Controllers/FinancialSettingController.php`
- `resources/views/admin/configuracion-financiera.blade.php`
- `resources/views/layouts/partials/sidebar.blade.php`
- `routes/web.php`
- `tests/Feature/FinancialSettingsTest.php`

Migraciones:

- Ninguna nueva en Fase 2.1. Se reutilizó `system_settings` para parámetros
  globales y la configuración comercial existente para límites por urbanización.
- La migración pendiente de la fase anterior continúa sujeta a disponibilidad
  de MySQL local.

Pruebas ejecutadas:

```bash
php artisan test tests/Feature/FinancialSettingsTest.php tests/Feature/CommercialSettingsPerUrbanizacionTest.php tests/Feature/SemicontadoVentaTest.php tests/Feature/PagoVerificacionTest.php tests/Feature/PagoAplicacionesTest.php tests/Feature/StabilityAuditTest.php tests/Feature/SystemConfigurationAndCommercialStructureTest.php
# 146 passed, 427 assertions
php artisan test
# 451 passed, 1691 assertions
```

Pendiente (siguiente tarea): Fase 2.2 — Reestructuración administrativa F-33
y devoluciones/rescisiones F-34/F-35.
