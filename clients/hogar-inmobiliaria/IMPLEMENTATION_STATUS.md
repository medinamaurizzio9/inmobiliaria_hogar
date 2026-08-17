# Estado de Implementación — Hogar Inmobiliaria

Estado real: `ready_for_acceptance`

Rama: `hogar-inmobiliaria`

Fecha de actualización: 2026-08-13

## NEXT_TASK

UAT / Deploy de Hogar Inmobiliaria.

Ejecutar `ACCEPTANCE_CHECKLIST.md` en staging, resolver incidencias reales y
desplegar siguiendo `docs/09-DEPLOYMENT.md`. No marcar producción hasta que el
despliegue y el smoke test hayan ocurrido realmente.

## LAST_COMPLETED

Módulo administrativo completo de Noticias y novedades.

Resultado verificado:

- Se reutilizaron `Noticia`, `NoticiaController`, portal público y vistas existentes; no se creó un CMS paralelo.
- Administración muestra miniatura/fallback, título, fecha, estado, destacada, orden y acciones Editar, Publicar/Ocultar y Eliminar.
- Formulario conserva slug automático y añade estado Borrador/Publicada, destacada y orden opcional; contenido continúa como textarea escapado, sin librerías pesadas.
- Nueva migración `2026_08_16_000001_add_featured_and_order_to_noticias_table.php`, aplicada correctamente; mantiene `publicada` como fuente de estado compatible y añade `destacada` y `orden`.
- Imágenes JPG/JPEG/PNG/WEBP hasta 4 MB usan disk `public` y `ManagedImageService`; reemplazo y eliminación limpian únicamente rutas seguras bajo `noticias/`.
- Portal muestra solo publicaciones con fecha no futura, ordenadas por destacada, fecha descendente e ID descendente; las tarjetas usan placeholder si falta el archivo.
- Detalle `/noticias/{slug}` mantiene branding, contenido escapado y devuelve 404 para borradores, futuras o inexistentes.
- Administración continúa protegida por `can:administrar usuarios`, permiso exclusivo del administrador en la configuración real; vendedor, supervisor y cliente reciben 403.
- Auditoría implementada: `noticia_creada`, `noticia_actualizada`, `noticia_publicada`, `noticia_ocultada` y `noticia_eliminada`.
- `MODULES.md` registra Noticias y novedades como módulo habilitado. No se modificó lógica financiera ni se realizó commit o push.

Pruebas:

```text
tests dirigidos: 20 passed, 117 assertions
php artisan test: 540 passed, 2147 assertions
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Pendiente real: UAT visual del contenido editorial e imágenes reales.

## PREVIOUS_COMPLETED_11

Corrección del reporte Mejor vendedor con ámbito por urbanización y global.

Resultado verificado:

- Causa de los ceros: `SaleService` registra en `ventas.user_id` al usuario que ejecuta el cierre; cuando administración convertía una reserva, la venta quedaba atribuida al administrador y no al asesor originador.
- Vendedor real del reporte: `reservas.usuario_id` en ventas convertidas desde reserva; `ventas.user_id` como fuente para ventas directas.
- Urbanización real: `ventas.lote_id -> lotes.manzano_id -> manzanos.urbanizacion_id`; fecha de cierre: `ventas.fecha_venta`.
- Solo cuentan ventas `activa` o `completada`; se excluyen anuladas, rescindidas y reservas sin venta. Monto vendido suma `ventas.precio_final` histórico pactado.
- Ámbito predeterminado Urbanización actual aplica la sesión tanto a ventas como reservas. Ámbito Global ignora ese filtro de sesión y usa todas las urbanizaciones accesibles al usuario.
- Se conservaron mes, año, supervisor, grupo y asesor; orden: ventas cerradas, monto vendido, conversión y desempate estable.
- Listado, Excel y PDF reutilizan el mismo cálculo y reciben los mismos filtros y etiqueta de ámbito.
- Las ventas y reservas del período se consultan una sola vez y se agrupan en memoria por vendedor real, sin consultas por fila.
- No se modificaron datos, estados, ventas, reservas ni otros reportes. No se crearon migraciones ni se realizó commit o push.

Pruebas:

```text
tests dirigidos: 13 passed, 61 assertions
php artisan test: 534 passed, 2106 assertions
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Pendiente real: UAT con datos productivos históricos para confirmar casos antiguos sin `reserva_id`.

## PREVIOUS_COMPLETED_10

Corrección definitiva del flujo inicial del comprador y separación de Mi perfil.

Resultado verificado:

- `InitialDestinationService` ya definía correctamente `clientes.mi-cuenta`; se confirmó con test de login real que el comprador siempre entra a `/mi-cuenta`, tenga uno o varios terrenos.
- El flujo de cambio obligatorio de contraseña conserva el mismo servicio de destino y termina también en `/mi-cuenta`.
- `/mi-cuenta` permanece como dashboard con saludo, terrenos independientes, métricas, acciones, urbanizaciones y WhatsApp; Ver detalle es la única navegación explícita a una venta.
- Nueva ruta `/mi-cuenta/perfil`: muestra únicamente foto desde `clientes.foto`, nombre, CI, celular, correo y dirección. No se inventaron acciones de edición o cambio voluntario inexistentes.
- Mi perfil solo queda activo en su ruta; en el dashboard no se marca ningún submenú específico. El logo del cliente enlaza a `/mi-cuenta`.
- El detalle se tituló Detalle de mi terreno, incorpora regreso a Mi cuenta, resumen jerarquizado, saldo, acciones, cuotas con badges y pagos.
- Se mantuvo la autorización de ownership existente; un comprador continúa sin acceso a ventas ajenas.
- No se modificaron saldos, cuotas, pagos, contratos ni servicios financieros. No se crearon migraciones ni se realizó commit o push.

Pruebas:

```text
tests dirigidos: 39 passed, 206 assertions
php artisan test: 529 passed, 2077 assertions
npm.cmd run build: passed (Vite 8.0.14)
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Pendiente real: validación visual humana del perfil y detalle en UAT.

## PREVIOUS_COMPLETED_9

Unificación de tarjetas de urbanizaciones entre portal público y portal comprador.

Resultado verificado:

- La sección pública Nuestros proyectos dejó de duplicar HTML y reutiliza `x-client.urbanization-card`, también usado por Mi cuenta, catálogo y Reserva de visitas.
- Estructura compartida: plano/imagen superior sin texto superpuesto, contenido separado, cuatro estadísticas reales y acciones ancladas al pie.
- `plano_imagen` se presenta sobre fondo claro, con padding y `object-fit: contain` para no recortar planos técnicos; el fallback existente permanece limpio.
- Estadísticas en cuatro columnas desktop y 2x2 móvil: total, disponibles en verde, vendidos en grafito y reservados en ámbar.
- Ver disponibilidad conserva la ruta pública por slug y no expone datos de compradores.
- Reservar visita usa WhatsApp comercial configurado; público genera mensaje anónimo y comprador autenticado incluye su nombre. No crea reservas.
- Grid responsive: tres tarjetas desktop, dos tablet y una móvil; altura consistente y acciones al fondo.
- No se modificaron hero, login, noticias, contacto, datos ni lógica financiera. No se crearon migraciones ni se realizó commit o push.

Pruebas:

```text
tests dirigidos: 21 passed, 114 assertions
php artisan test: 526 passed, 2061 assertions
npm.cmd run build: passed (Vite 8.0.14)
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Pendiente real: validación visual humana de proporciones de planos reales durante UAT.

## PREVIOUS_COMPLETED_8

Rediseño premium del dashboard del comprador y navegación orientada al cliente.

Resultado verificado:

- Sidebar comprador reducido a Mi perfil, Mis terrenos, Reserva de visitas, Ver urbanizaciones y Cerrar sesión; las rutas financieras/documentales continúan disponibles desde cada terreno.
- Cabecera personalizada con nombre real y fotografía única desde `clientes.foto`, usando iniciales como fallback mediante el componente de avatar existente.
- Una tarjeta independiente por venta/terreno con urbanización, lote, modalidad, valores históricos, pagos confirmados, saldo de cuotas real, próxima cuota y accesos existentes.
- KPI de saldo total informativo, pagos pendientes de verificación y cuotas vencidas calculados solo con datos del cliente autenticado.
- Catálogo de urbanizaciones activas reutiliza `withLotStats()` y un componente compartido; no ejecuta consultas por tarjeta.
- Reserva de visitas abre WhatsApp comercial configurado con nombre del cliente y urbanización, sin CI, datos financieros, agenda ni reserva contractual.
- Se añadieron `/mi-cuenta/reserva-visitas` y `/mi-cuenta/urbanizaciones`, ambas dentro del grupo autenticado y rol cliente.
- No se crearon migraciones ni se modificó el motor financiero, permisos administrativos o estados de lotes. No se realizó commit ni push.

Pruebas:

```text
tests dirigidos: 30 passed, 154 assertions
php artisan test: 525 passed, 2053 assertions
npm.cmd run build: passed (Vite 8.0.14)
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Pendiente real: validación visual humana en los anchos objetivo durante UAT.

## PREVIOUS_COMPLETED_7

Portal público premium con login integrado.

Resultado verificado:

- `/` presenta header, hero configurable a ancho completo, indicadores reales, proyectos activos, acceso a disponibilidad, beneficios, noticias, contacto y footer; el CRM conserva su layout independiente.
- `/login` reutiliza el portal y abre/enfoca el acceso; se conservaron POST login, remember, rate limiting, cambio obligatorio de contraseña y destinos por rol.
- Login compacto horizontal en escritorio y diálogo accesible en móvil, con cierre por botón, Escape y backdrop; los errores reabren el diálogo móvil.
- La disponibilidad pública existente fue reutilizada: no expone compradores ni datos financieros y añade consulta/WhatsApp contextual sin crear reservas ni cambiar lotes.
- Se creó el módulo mínimo `noticias` con publicación pública y CRUD administrativo protegido por el permiso existente de administración de usuarios.
- Se creó `public_leads` porque no existía infraestructura equivalente; las consultas web no se convierten en clientes, validan proyecto/lote, usan CSRF y tienen límite de cinco envíos por minuto.
- El hero reutiliza la fotografía configurable del disk público (952881 bytes), con `cover`, overlay y sin duplicar el recurso.
- Nueva migración aplicada: `2026_08_13_000001_create_noticias_and_public_leads_tables.php`.
- No se modificaron autenticación, roles, lógica financiera ni estados de lotes. No se realizó commit ni push.

Pruebas:

```text
tests dirigidos del portal/branding/disponibilidad: 46 passed, 219 assertions
php artisan test: 522 passed, 2033 assertions
npm.cmd run build: passed (Vite 8.0.14)
vendor/bin/pint --test --dirty: passed
git diff --check: passed
php artisan route:list: 186 routes; rutas públicas e internas sin conflicto
```

Pendiente real: UAT visual humana en los anchos objetivo y despliegue siguiendo el checklist existente.

## PREVIOUS_COMPLETED_6

Prototipo UX/UI SaaS Premium — primera fase.

Resultado verificado:

- Design System consolidado en `public/css/app.css` con fondo frío, superficies blancas, primario violeta, semánticos consistentes y dorado reservado para identidad.
- Se mantiene Instrument Sans local; pesos principales 400/500/600, sin dependencias tipográficas nuevas.
- Sidebar grafito con jerarquía discreta, hover `#22262e`, activo violeta suave e indicador lateral; permisos y comportamiento conservados.
- Topbar liviana con buscador gris, foco violeta, controles compactos y avatar existente.
- Botones, cards, KPI, tablas, badges, formularios, alertas, vacíos y acciones rápidas comparten tokens y transiciones de 150 ms.
- Dashboard administrativo con saludo contextual, alertas prioritarias, acciones rápidas y KPI claros; Dashboard supervisor usa el mismo sistema con datos propios.
- Cobranza funciona como segunda pantalla prototipo: cabecera descriptiva, resumen y filtros compactos, fichas financieras claras, modales y pagos pendientes estilizados.
- Cabeceras de tabla migradas a superficie clara; se conserva `position: static` para evitar superposición.
- Selector de urbanización y vistas no migradas heredan fondo, tipografía, cards, botones, formularios, tablas y responsive sin reescritura manual.
- No se modificó lógica financiera, permisos, rutas, cálculos ni migraciones. No se realizó commit ni push.

Pruebas:

```text
tests dirigidos: 43 passed, 215 assertions
php artisan test: 517 passed, 2002 assertions
npm run build: passed
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Pendiente real: aprobación visual humana en navegador/dispositivos. El entorno bloqueó iniciar un servidor auxiliar para capturas; la segunda fase deberá migrar manualmente reportes, formularios especializados y administración una vez aprobada la dirección.

## PREVIOUS_COMPLETED_5

Contacto WhatsApp directo desde cuotas vencidas del Dashboard.

Resultado verificado:

- La fuente real de contacto es `clientes.telefono`; no se añadieron columnas ni migraciones.
- El bloque mantiene una fila por cuota vencida y el contador representa cuotas, por lo que un cliente puede aparecer varias veces.
- Se reutilizó `WhatsAppLink`; acepta celulares bolivianos locales válidos de 8 dígitos iniciados en 6/7 o formato internacional `591`, elimina separadores y no duplica el prefijo.
- La tabla muestra Cliente, Celular, Lote, Vence, Saldo y Contacto; números ausentes/inválidos muestran `Sin celular` y no generan enlace.
- Mensaje profesional codificado con nombre del cliente, lote y nombre comercial configurable, sin incluir monto.
- Contacto visible solo con permisos `ver clientes` y `cobrar cuotas`.
- `venta.cliente` y `venta.lote.manzano` siguen precargadas en una sola consulta; no se añadió una consulta por fila.
- No se modificó lógica financiera. No se realizó commit ni push.

Pruebas:

```text
tests dirigidos: 20 passed, 97 assertions
php artisan test: 517 passed, 2002 assertions
npm run build: passed
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Pendiente real: revisión visual manual responsive durante UAT/deploy.

## PREVIOUS_COMPLETED_4

Dashboard inicial diferenciado para asesor, supervisor y administración.

Resultado verificado:

- Roles reales respetados: `administrador`, `gerente`, `supervisor`, `vendedor`, `cajero` y `cliente`; no se crearon roles ni permisos.
- Precedencia multirrol explícita: cliente; superadministración/administración/gerencia/finanzas; supervisor; vendedor.
- Destino inicial centralizado en `InitialDestinationService`: vendedor conserva selección de urbanización; supervisor ingresa al Dashboard comercial; administrador/gerente/cajero ingresan al Centro de Operaciones; cliente conserva Mi Cuenta.
- Perfiles administrativos reciben de forma segura la primera urbanización activa accesible si aún no existe contexto, sin eliminar el selector ni mezclar datos entre urbanizaciones.
- Supervisor recibe una variante propia bajo `/dashboard`, limitada por `asesores.supervisor_id` y urbanizaciones asignadas, con métricas de asesores activos, ventas/monto del mes, reservas activas y clientes atendidos.
- Secciones supervisor: alertas reales de reservas vencidas/próximas, accesos rápidos por permisos, tabla Mi Equipo y urbanizaciones accesibles con conteos de lotes.
- Ventas y reservas por asesor se calculan mediante agregados agrupados y relaciones precargadas, sin consultas dentro del recorrido del equipo.
- Dashboard administrativo convertido en Centro de Operaciones con alerta real de pagos `pendiente_verificacion`, accesos rápidos por permisos y campana con contador.
- Se reutilizan `CashMovement`, `UrbanizacionContext`, Cobranza y rutas existentes. No se modificó lógica financiera ni autorización backend.
- Responsive: accesos en cuatro columnas de escritorio, dos en tablet/móvil y alerta adaptable.
- No se crearon migraciones. No se realizó commit ni push.

Pruebas:

```text
tests dirigidos: 55 passed, 275 assertions
php artisan test: 513 passed, 1987 assertions
npm run build: passed
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Pendiente real: revisión visual manual por rol en navegador/dispositivos y UAT/deploy.

## PREVIOUS_COMPLETED_3

Segunda pasada UX/UI y gestión diferenciada de usuarios.

Resultado verificado:

- Usuarios internos separados de usuarios compradores mediante la relación real `users.cliente_id` y rol `cliente`.
- Listado comprador paginado con estado de acceso, ventas agregadas, urbanización y acciones administrativas protegidas.
- Reset de comprador a CI hasheado, `must_change_password`, invalidación de sesiones y auditoría sin registrar secretos.
- Fotografías opcionales para usuarios y clientes mediante disk `public`, validación de 2 MB, reemplazo/eliminación segura y avatar por iniciales.
- Imágenes configurables pueden quitarse con confirmación, limpieza segura, invalidación de caché y fallback existente.
- Tablas CRM globales con zebra, divisores, hover oliva, cabecera sticky y acciones compactas.
- Cobranza más compacta y preparada para mostrar la foto real del comprador.
- Migración nueva `2026_08_12_000003_add_photo_paths_to_users_and_clientes.php`; aplicada correctamente.
- No se modificó lógica financiera. No se realizó commit ni push.

Pruebas:

```text
tests dirigidos: 55 passed, 281 assertions
php artisan test: 502 passed, 1936 assertions
php artisan route:list: 178 routes
npm run build: passed
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Pendiente real: revisión visual manual en navegadores/dispositivos y UAT/deploy.

## PREVIOUS_COMPLETED_2

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
