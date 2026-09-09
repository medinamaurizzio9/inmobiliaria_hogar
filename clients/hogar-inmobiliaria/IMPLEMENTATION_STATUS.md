# Estado de Implementación — Hogar Inmobiliaria

Estado real: `ready_for_acceptance`

Rama: `hogar-inmobiliaria`

Fecha de actualización: 2026-09-09

## NEXT_TASK

UAT / Deploy de Hogar Inmobiliaria.

Ejecutar `ACCEPTANCE_CHECKLIST.md` en staging, resolver incidencias reales y
desplegar siguiendo `docs/09-DEPLOYMENT.md`. No marcar producción hasta que el
despliegue y el smoke test hayan ocurrido realmente.

## LAST_COMPLETED

Estandarización reusable y protección visual de logos del sistema.

Resultado verificado:

- Se creó el componente Blade `brand-logo` como fuente única de renderizado web para sidebar, topbar móvil, login, modal público, portal, página pública de urbanización/disponibilidad, verificación pública de recibos, footer y PWA offline.
- El componente obtiene `logo_main` o `logo_login` mediante `SystemSettingsService`, conserva compatibilidad con la configuración actual y muestra iniciales accesibles cuando el archivo no existe.
- Las variantes `sidebar`, `topbar`, `login`, `login-hero`, `public-header`, `footer` y `pwa` tienen límites propios de ancho/alto, `object-fit: contain`, `display: block` y protección contra contracción o expansión accidental.
- Se eliminó el límite inline del sidebar y las reglas `!important` antiguas que competían por el tamaño del logo. Los selectores responsive ahora ocultan solo el nombre de marca, no el componente completo.
- Logos horizontales, verticales, cuadrados, transparentes, WebP y de alta resolución comparten el mismo encuadre visual; las dimensiones originales del archivo no determinan su tamaño en pantalla.
- Las plantillas PDF conservan su flujo especializado con rutas/data URI para DomPDF; la excepción y el procedimiento de reutilización están documentados en `docs/UI-BRAND-STANDARD.md`.
- No se cambió el logo configurado, no se crearon migraciones y no se modificaron coordenadas, mapas, reservas, ventas, pagos ni lógica financiera.

Pruebas:

```text
php artisan optimize:clear: passed
php artisan test --filter=BrandLogoRegressionTest: 8 passed, 58 assertions
tests dirigidos de branding/portal/urbanización/PWA: 31 passed, 216 assertions
php artisan test: 592 passed, 2427 assertions
npm.cmd run build: passed
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Pendiente real: UAT visual con los logos productivos en desktop, tablet y móvil antes del deploy. Mantener el cambio sin commit para incorporarlo al commit consolidado posterior.

## PREVIOUS_COMPLETED_20

Auditoría y optimización de rendimiento basada en mediciones locales.

Resultado verificado:

- Se añadió `scripts/performance-audit.php`, auditor de solo lectura para rutas públicas y autenticadas que registra estado HTTP, tiempo aproximado, queries, duplicadas, tiempo SQL, consulta más lenta, memoria incremental y tamaño HTML.
- Causa principal: `CommercialSettingsService::settings()` ejecutaba `Urbanizacion::exists()` y `firstOrCreate()` cada vez que `LotPricingService` solicitaba un dato. Como `payload()` consulta varios valores por lote, `/lotes` llegó a 1.679 queries y `/disponibilidad`/`/u/{slug}` a 283.
- La configuración comercial ahora se resuelve una vez por urbanización y por instancia del servicio, con invalidación inmediata después de cada actualización. Las fórmulas y valores comerciales no cambiaron.
- Dashboard administrativo reutiliza agregados por estado y consolida ventas/reservas. Dashboard asesor bajó de 45 a 36 queries; administrador de 48 a 39 y supervisor de 28 a 27.
- Cobranza consolida cinco agregados diarios en una consulta condicional, sin cambiar montos ni estados; pasó de 38 a 35 queries en la medición local.
- La página pública elimina una consulta de urbanizaciones no utilizada y selecciona solo columnas requeridas de urbanización, configuración, características, manzanos y lotes.
- Reportes de lotes, reservas, cuotas e ingresos se paginan a 50 filas en pantalla; exportaciones conservan el dataset completo. Los indicadores se calculan con agregados SQL sobre todo el filtro, no solo sobre la página.
- Reportes de cuotas redujo 21 a 19 queries y su HTML de 36,7 KB a 28,4 KB con los datos locales. Ingresos bajó de 17 a 16 queries. El índice de reportes bajó de 16 a 15.
- Las imágenes secundarias del plano público y QR usan carga diferida; la imagen principal del hero mantiene su estrategia LCP.
- No se añadieron índices: los filtros críticos ya cuentan con índices y ninguna consulta individual superó 8,2 ms en la muestra actual. El problema medido era fan-out de queries, no infraestructura.
- No se modificaron permisos, reglas financieras, coordenadas, zoom, estados, service worker ni datos. No se añadieron Redis, Octane, CDN ni migraciones.

Medición local representativa antes/después:

```text
/lotes:                    872,00 ms / 1.679 queries -> 81,50 ms / 25 queries
/disponibilidad:           179,94 ms /   283 queries -> 54,16 ms / 19 queries
/u/{slug}:                 150,38 ms /   283 queries -> 35,11 ms / 19 queries
/dashboard administrador:   83,47 ms /    48 queries -> 75,59 ms / 39 queries
/dashboard asesor:          62,23 ms /    45 queries -> 54,51 ms / 36 queries
/dashboard supervisor:      53,17 ms /    28 queries -> 39,58 ms / 27 queries
/cobranza:                  84,86 ms /    38 queries -> 85,58 ms / 35 queries
```

El mapa público no serializa modelos como JSON: genera marcadores HTML. Su respuesta total permaneció funcionalmente estable (17,2 KB antes, 17,3 KB después por atributos `loading`/`decoding`), mientras la consulta de lotes dejó de seleccionar observaciones y timestamps no usados.

Pruebas:

```text
php artisan optimize:clear: passed
php artisan test --filter=PerformanceRegressionTest: 3 passed, 18 assertions
php artisan test: 584 passed, 2369 assertions
npm.cmd run build: passed
vendor/bin/pint --test --dirty: passed
git diff --check: passed
php artisan config:cache: passed
php artisan route:cache: passed
php artisan view:cache: passed
```

Pendiente real: la muestra posterior con las tres cachés activas no pudo repetirse porque el proceso MySQL de Laragon se detuvo y `127.0.0.1:3306` rechazó la conexión. Las mediciones posteriores sin cachés sí fueron completadas. Repetir `php scripts/performance-audit.php` cuando MySQL esté activo. Mantener este trabajo pendiente para el commit consolidado posterior.

## PREVIOUS_COMPLETED_19

Responsive comercial para supervisor/asesor y preparación base PWA.

Resultado verificado:

- El topbar móvil incorpora branding compacto, hamburguesa, nombre de usuario y menú con perfil/seguridad y cierre de sesión.
- El sidebar conserva el comportamiento de escritorio y funciona como drawer en móvil con overlay, cierre al navegar o pulsar Escape, bloqueo de scroll, retorno de foco y trampa de foco accesible.
- Dashboard supervisor prioriza reservas, equipo y ventas; el detalle de asesores se convierte en cards móviles. Dashboard asesor incorpora accesos táctiles a nueva reserva, lotes, clientes, perfil y urbanizaciones asignadas.
- Listados de clientes, reservas, ventas y urbanizaciones mantienen tablas en escritorio y se convierten en cards con información esencial y acciones de al menos 44 px en móvil.
- Formularios y filtros pasan a una columna, controles de al menos 44 px y acciones principales de ancho completo en móvil.
- Portal, página pública por urbanización, video, características, CTA y modal mantienen responsive. El mapa conserva zoom, pinch, paneo, marcadores de 28 px y modal, con superficie táctil contenida para evitar scroll accidental.
- Corrección puntual del login móvil: el checkbox `remember` queda aislado de los estilos globales de inputs, mide 18 × 18 px y permanece alineado con su texto dentro de una etiqueta completamente pulsable. El modal conserva margen lateral, scroll vertical seguro y controles táctiles.
- El menú del header público conserva logo, botón Ingresar y hamburguesa; ahora también se cierra al seleccionar un enlace, tocar fuera o pulsar Escape.
- PWA base: manifest público, iconos estáticos 192/512 derivados del logo existente, metadatos, registro de service worker y pantalla offline simple.
- El service worker solo interviene en GET; usa network-first para navegación y nunca guarda HTML autenticado. Cachea únicamente CSS, JS, manifest e iconos públicos. POST/PUT/PATCH/DELETE y endpoints de negocio permanecen en red.
- Se mantiene la autenticación Laravel y no se creó SPA, login paralelo ni lógica de negocio nueva.

Pruebas:

```text
php artisan optimize:clear: passed
php artisan test --filter=PwaResponsiveTest: 8 passed, 64 assertions
php artisan test --filter=CommercialReservationRulesTest: 8 passed, 32 assertions
php artisan test: 581 passed, 2351 assertions
npm.cmd run build: passed
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Pendiente real: validar manualmente en Chrome DevTools 390x844, 430x932, 768x1024 y 1024x1366, incluida la pestaña Application/Manifest/Service Workers. El navegador integrado negó acceso al servidor local durante esta tarea, por lo que no se declara instalabilidad visualmente comprobada. Incluir este trabajo junto con fix CSV 500, optimización de planos y página pública en el commit completo posterior.

## PREVIOUS_COMPLETED_18

Optimización automática de planos raster mayores de 2 MB.

Resultado verificado:

- Se reutilizó `ManagedImageService`; no se creó un segundo sistema de imágenes ni se modificaron mapa, zoom, estados o coordenadas.
- Raster JPG/JPEG/PNG/WEBP de hasta 2 MB conserva el procesamiento existente. Si supera 2 MB mantiene exactamente ancho y alto originales y se recodifica preferentemente a WebP.
- Compresión adaptativa: calidad 88, 85, 82, 80 y 78, deteniéndose al alcanzar 2 MB. Si no alcanza el objetivo, conserva la salida de calidad mínima segura y muestra una advertencia sin reducir resolución.
- La recodificación GD elimina metadatos no necesarios, valida el contenido real y usa nombres UUID y rutas controladas.
- Orden de reemplazo corregido: procesar, verificar existencia, actualizar BD y recién entonces eliminar plano/thumbnail anterior. Un fallo mantiene el plano previo.
- La página pública y el mapa continúan usando `urbanizaciones.plano_imagen`; `coord_x` y `coord_y` no se actualizan durante la carga.
- PDF conserva su flujo previo de conversión y original funcional. No se crearon migraciones.

Medición reproducible con PNG sintético:

```text
entrada: 2200x1400, 9,256,425 bytes, PNG
salida: 2200x1400, 5,608 bytes, WebP calidad 88
```

El fixture es altamente compresible; no se afirma que todo plano pueda quedar por debajo de 2 MB sin reducir resolución.

Pruebas:

```text
tests dirigidos de imágenes/planos/mapa: 29 passed, 123 assertions
php artisan test --filter=Urbanizacion: 59 passed, 286 assertions
php artisan test --filter=Map: 27 passed, 167 assertions
php artisan test: 573 passed, 2287 assertions
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Pendiente real: incluir esta tarea en el commit completo posterior y validar con un plano productivo complejo en staging.

## PREVIOUS_COMPLETED_17

Corrección del error 500 en el importador CSV de lotes.

Resultado verificado:

- Causa raíz: `array_pad()` completaba silenciosamente filas con columnas faltantes y no truncaba filas con columnas adicionales; estas últimas llegaban a `array_combine()` con distinta cantidad de claves y valores y provocaban `ValueError`.
- Antes de combinar se valida la cantidad exacta de columnas. Una fila incorrecta informa línea, columnas esperadas/encontradas y recomendaciones sobre separadores, comas y campos vacíos.
- Se conservan los tres encabezados admitidos, BOM UTF-8, delimitadores coma/punto y coma y parsing real mediante `fgetcsv`/`str_getcsv`.
- Las líneas completamente vacías se omiten; archivos vacíos, ilegibles o con solo cabecera devuelven errores controlados.
- Campos vacíos conservan su posición y comas entre comillas permanecen dentro del mismo campo.
- Preview con errores elimina las filas de sesión y no crea lotes. La confirmación completa ahora usa una transacción para impedir escrituras parciales ante fallos.
- No se crearon migraciones ni se modificó lógica financiera.

Pruebas:

```text
regresión del importador: 19 passed, 69 assertions
php artisan test --filter=Lot: 69 passed, 305 assertions
php artisan test: 567 passed, 2259 assertions
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Pendiente real: desplegar mediante el proceso autorizado y repetir el CSV problemático en staging; esta tarea trabajó únicamente en local.

## PREVIOUS_COMPLETED_16

Mejora visual y UX de la página pública por urbanización.

Resultado verificado:

- Se compactaron hero, video/descripción, características, plano, CTA y footer bajo un ancho coherente de hasta 1280 px.
- Header con mayor presencia de logo, botón de login con gradiente de marca y tamaños adaptativos para desktop, tablet y móvil.
- CTA primario del hero reforzado y WhatsApp convertido a verde profesional con estados hover accesibles.
- Video/descripción usan columnas 50/50; características ocupan 3, 2 y 1 columnas según breakpoint, con tarjetas más amplias.
- Causa de baja visibilidad de marcadores corregida: tamaño global máximo de 22/16 px, `pointer-events: none`, borde público eliminado y `z-index: 2`.
- Marcadores públicos ahora miden 32 px desktop y 28–29 px móvil, usan borde blanco de 3 px, sombra, colores de alto contraste, hover y selección visible.
- Coordenadas y zoom no se modificaron: imagen y puntos permanecen dentro del mismo `plan-map-layer` transformado. El paneo ignora el marcador para preservar el click.
- Leyenda ampliada y reubicada cerca del título; mapa mantiene proporción natural, zoom, navegación táctil y modal existente.
- Prueba dirigida confirma simultáneamente un marcador disponible, vendido, reservado y bloqueado.

Pruebas:

```text
tests dirigidos: 51 passed, 247 assertions
php artisan test: 559 passed, 2227 assertions
vendor/bin/pint --test --dirty: passed
git diff --check: passed
npm.cmd run build: passed
```

Pendiente real: UAT visual humana con logo y plano productivos en 1920, 1600, 1366, 1024, 768, 430 y 390 px.

## PREVIOUS_COMPLETED_15

Corrección UX de la página pública por urbanización.

Resultado verificado:

- Se añadieron `titulo_descripcion` y `descripcion_principal` a la configuración pública existente; no se duplicaron tablas ni se creó otro CMS.
- La navegación administrativa muestra claramente Datos generales y Página pública. La pantalla pública permite hero, video con preview seguro, descripción principal y hasta 10 características.
- Orden público final: header, hero, video/descripción, características, plano interactivo, CTA WhatsApp y footer.
- Se eliminó el grid/listado público completo de lotes. La disponibilidad masiva solo se presenta mediante el plano y sus conteos.
- Todos los puntos ubicados muestran modal público según estado; únicamente Disponible ofrece WhatsApp. Reservado, Vendido y Bloqueado no incluyen enlace comercial.
- El modal muestra precio solo cuando `mostrar_precio_publico` está activo. No expone comprador, cliente, deuda, cuotas, pagos ni reservas internas.
- WhatsApp continúa usando Configuración General y ahora incluye urbanización, manzano, lote y superficie con encoding correcto.
- Migración local aplicada: `2026_08_23_000002_add_description_to_urbanizacion_public_settings.php`.

Pruebas:

```text
tests dirigidos: 47 passed, 231 assertions
php artisan test: 559 passed, 2226 assertions
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Pendiente real: UAT visual humana con contenido real en 1920, 1366, 1024, 768, 430 y 390 px.

## PREVIOUS_COMPLETED_14

Rediseño y personalización de la página pública por urbanización.

Resultado verificado:

- `/u/{slug}` usa automáticamente nombre y ubicación de la urbanización, hero configurable con fallback, video YouTube seguro y hasta 10 bloques informativos ordenables/activables.
- Administración incorpora `Urbanizaciones → Editar → Página pública`, protegida por `editar urbanizaciones`; el gerente actual no posee ese permiso y recibe 403.
- Hero optimizado mediante `ManagedImageService` a un máximo de 1920 × 1080, calidad 82; reemplazo/eliminación preservan la imagen anterior hasta guardar la nueva configuración.
- El mapa, plano, zoom, estados y QR existentes se conservan. Un lote disponible abre primero un diálogo con manzano, lote, superficie y estado; WhatsApp solo abre desde el CTA del diálogo.
- WhatsApp usa exclusivamente Configuración General mediante `SystemSettingsService` y `WhatsAppLink`, con mensaje contextual sin datos privados ni financieros.
- Nuevas tablas separadas de configuración comercial/financiera: `urbanizacion_public_settings` y `urbanizacion_public_features`.
- Tests dirigidos de página pública y regresión relacionada: 46 passed, 212 assertions. Suite completa: 558 passed, 2207 assertions. Migración local aplicada.

Pendiente real: UAT visual humana en los anchos objetivo y con contenido real de cada urbanización.

## PREVIOUS_COMPLETED_13

Optimización de carga y entrega de imágenes configurables.

Resultado verificado:

- `ManagedImageService` procesa con GD las nuevas cargas JPG/JPEG/PNG/WEBP, valida la imagen real, corrige orientación EXIF JPEG, redimensiona proporcionalmente sin ampliar y usa nombres UUID.
- WebP se genera cuando `imagewebp` está disponible; en este entorno GD y WebP están habilitados. Si WebP no está disponible se conserva salida JPEG/PNG compatible.
- Límites de procesamiento: logos 800 px/calidad 85; fondo 1920 px/calidad 82; noticias y planos raster 1600 px/calidad 84; perfiles 600 px/calidad 84; QR hasta 2000 px conservando PNG y calidad alta.
- Noticias y urbanizaciones generan thumbnails derivados bajo `thumbs/` (600 px); perfiles generan avatar de 240 px. No se añadieron columnas ni migraciones.
- Cards, listados y avatares usan thumbnails; detalles, mapas y hero conservan la imagen principal. Imágenes no críticas incorporan `loading="lazy"` y `decoding="async"`.
- Reemplazos eliminan principal y thumbnail anteriores únicamente después de guardar correctamente el nuevo archivo. Rutas inválidas o con traversal se rechazan.
- QR continúa sin conversión agresiva y logos/fondos/configuración financiera reutilizan el mismo servicio. El PDF original de un plano permanece sin recomprimir.
- Rutas históricas siguen resolviéndose y usan la imagen principal si no existe thumbnail; archivos ausentes muestran fallback y no generan `<img>` roto.
- No se convirtieron ni eliminaron imágenes históricas, no se introdujeron colas ni dependencias y no se modificó Nginx automáticamente.
- `docs/09-DEPLOYMENT.md` documenta cache inmutable de imágenes por 30 días y verificación de GD/WebP.

Pruebas:

```text
tests dirigidos: 77 passed, 277 assertions
tests de regresión de mapas: 40 passed, 232 assertions
php artisan test: 550 passed, 2168 assertions
vendor/bin/pint --test --dirty: passed
git diff --check: passed
```

Medición reproducible con fixture JPEG generado de 4032x3024:

```text
entrada: 4032x3024, 1,169,121 bytes
principal WebP: 1600x1200, 18,920 bytes
thumbnail WebP: 600x450, 3,566 bytes
```

Pendiente real: UAT y verificación de límites `client_max_body_size`, `upload_max_filesize` y `post_max_size` en producción. Una conversión masiva de históricos queda fuera de esta fase.

## PREVIOUS_COMPLETED_12

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
