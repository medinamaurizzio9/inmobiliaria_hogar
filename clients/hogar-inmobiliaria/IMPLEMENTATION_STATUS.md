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

Fase 3 — Creación automática de cuenta cliente y Portal Financiero.

Prioridad:

`HIGH`

Estado:

`pending`

Objetivo:

Crear automáticamente la cuenta vinculada al cliente y habilitar el Portal
Financiero con acceso seguro a su estado de cuenta, cuotas y pagos.

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

- [ ] cuenta cliente creada y vinculada sin duplicados;
- [ ] acceso aislado a la información financiera propia;
- [ ] cuotas, pagos y saldo visibles;
- [ ] autorización backend verificada;
- [ ] pruebas correspondientes pasan.

---

# 5. Última tarea completada

## LAST_COMPLETED_TASK

Título:

Fase 2.2 — Reestructuración administrativa y devoluciones/rescisiones.

Fecha:

2026-08-12

Resultado:

Implementado y verificado con tests:

- Reestructuración exclusiva de administrador para crédito y semicontado con
  saldo real, snapshots antes/después, motivo, responsable y nuevo vencimiento.
- Cuotas pagadas y aplicaciones históricas intactas; cuotas activas anteriores
  anuladas sin borrado y pagos parciales preservados.
- Nuevo plan exacto con `Money`, sin intereses ni cambios al precio/descuento;
  la última cuota absorbe el residuo.
- Rescisión transaccional exclusiva de administrador con bloqueo de venta/lote,
  preservación de pagos, aplicaciones y cuotas, y cancelación de deuda activa.
- Registro de total pagado confirmado, monto devuelto y monto retenido. Solo el
  monto devuelto genera egreso de caja con concepto `devolucion`.
- Venta anulada y lote sincronizado con operaciones vigentes.
- Historial visible, métricas de devolución/retención y auditoría de ambas acciones.

Nota: sin commit ni push, en rama `hogar-inmobiliaria`.

Archivos principales modificados:

- `app/Services/DebtRestructuringService.php`
- `app/Services/SaleRescissionService.php`
- `app/Http/Controllers/ReestructuracionController.php`
- `app/Http/Controllers/DevolucionController.php`
- `app/Models/Reestructuracion.php`
- `app/Models/Devolucion.php`
- `resources/views/ventas/reestructurar.blade.php`
- `resources/views/ventas/rescindir.blade.php`
- `resources/views/ventas/show.blade.php`
- `routes/web.php`
- `tests/Feature/FinancialRestructuringAndRefundTest.php`

Migraciones:

- `2026_08_12_000002_create_reestructuraciones_and_devoluciones_tables.php`,
  aplicada correctamente en MySQL `hogar_inmobiliaria`.

Pruebas ejecutadas:

```bash
php artisan test tests/Feature/FinancialRestructuringAndRefundTest.php
# 10 passed, 42 assertions
php artisan test
# 461 passed, 1733 assertions
```

Pendiente (siguiente tarea): Fase 3 — Creación automática de cuenta cliente y
Portal Financiero.
