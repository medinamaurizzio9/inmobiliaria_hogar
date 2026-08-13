# Estado de Implementación — Hogar Inmobiliaria

Estado real: `ready_for_acceptance`

Rama: `hogar-inmobiliaria`

Fecha de actualización: 2026-08-12

## NEXT_TASK

UAT / Deploy de Hogar Inmobiliaria.

Ejecutar `ACCEPTANCE_CHECKLIST.md` en staging, resolver incidencias reales y
desplegar siguiendo `docs/09-DEPLOYMENT.md`. No marcar producción hasta que el
despliegue y el smoke test hayan ocurrido realmente.

## LAST_COMPLETED

Rediseño y optimización visual del CRM inmobiliario, con foco en navegación y módulos financieros.

Resultado verificado:

- Sidebar negro/oliva optimizado: estado persistido aplicado antes del render, transición estructural de 150 ms, logo oculto en modo contraído y tooltips por sección.
- Dashboard, login, disponibilidad pública, topbar y componentes CRM unificados con la identidad negro, verde oliva y blanco.
- Cobranza paginada a 20 operaciones, con búsqueda y filtros por estado, vencimiento y asesor; tarjetas financieras con cliente, iniciales, lote, saldo, progreso, próxima cuota y acciones existentes.
- El esquema real de clientes no posee foto/avatar ni relación equivalente; se usa el fallback de iniciales sin inventar columnas ni migraciones.
- Cuotas incorpora filtros, paginación y resumen financiero; Caja conserva sus filtros avanzados y añade KPI calculados sobre la consulta filtrada.
- Consultas de Cobranza/Cuotas/Caja conservan el alcance de urbanización y usan eager loading para evitar N+1.
- No se modificó la lógica financiera, no se crearon migraciones y no se realizó commit ni push.

Pruebas:

```text
php artisan route:list
170 routes

php artisan test tests/Feature/SystemConfigurationAndCommercialStructureTest.php tests/Feature/QuickCollectionTest.php tests/Feature/CajaFiltrosAvanzadosTest.php tests/Feature/SidebarMenuTest.php
45 passed, 209 assertions

php artisan test
497 passed, 1915 assertions

npm run build
passed

vendor/bin/pint --test --dirty
passed

git diff --check
passed
```

Pendiente real: UAT manual, prueba responsive en dispositivos/navegadores,
restauración controlada del backup y despliegue en infraestructura real.

## PREVIOUS_COMPLETED

Fase 6 — Hardening final, UX, pruebas de aceptación y preparación de producción.

Resultado verificado:

- Acceso directo de vendedor y supervisor bloqueado para deuda, detalle de
  venta, plan de pagos, contrato y estado de cuenta.
- Acciones de verificación visibles al cajero conforme a la autorización del backend.
- Ownership del portal cliente y documentos ajenos cubierto por pruebas existentes.
- Checklist manual de aceptación y despliegue específico documentados.
- `.env.example` completado con opciones de sesión configurables para HTTPS.
- `route:cache` compatible y cache limpiada tras la comprobación.
- Todas las migraciones existentes figuran `Ran`; no se crearon migraciones.
- No se realizó commit ni push.

Archivos principales:

- `app/Http/Controllers/VentaController.php`
- `app/Http/Controllers/PdfController.php`
- `resources/views/caja/show.blade.php`
- `tests/Feature/StabilityAuditTest.php`
- `tests/Feature/VentaEdicionAuditoriaTest.php`
- `.env.example`
- `docs/09-DEPLOYMENT.md`
- `clients/hogar-inmobiliaria/ACCEPTANCE_CHECKLIST.md`

Pruebas:

```text
php artisan test tests/Feature/VentaEdicionAuditoriaTest.php tests/Feature/StabilityAuditTest.php
17 passed, 48 assertions

php artisan test
494 passed, 1892 assertions

vendor/bin/pint --test --dirty
passed

git diff --check
passed

php artisan route:cache
passed

php artisan route:clear
passed
```

Pendiente real: UAT manual, prueba responsive en dispositivos/navegadores,
restauración controlada del backup y despliegue en infraestructura real.
