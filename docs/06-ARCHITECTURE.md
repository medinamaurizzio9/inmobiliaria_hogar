# ARCHITECTURE.md

# ARQUITECTURA BASE — PLATAFORMA TERRENOS

## 1. OBJETIVO

Este documento define la arquitectura técnica recomendada para todas las implementaciones derivadas de Terrenos.

La arquitectura debe permitir:

* Reutilizar el CORE.
* Personalizar cada empresa.
* Agregar módulos sin romper funcionalidades existentes.
* Mantener reglas de negocio centralizadas.
* Facilitar pruebas.
* Facilitar trabajo con agentes de IA.
* Reducir duplicación de código.
* Preparar el sistema para crecimiento futuro.

---

# 2. STACK BASE

Backend:

Laravel

Base de datos:

MySQL

Frontend:

Blade como opción base.

Puede utilizarse posteriormente:

* Livewire
* Vue
* React
* Inertia

siempre que exista una razón clara.

API:

REST API.

Autenticación web:

Laravel Auth.

Autenticación API:

Laravel Sanctum.

Control de versiones:

Git.

Servidor:

Linux + Nginx recomendado.

---

# 3. PRINCIPIO ARQUITECTÓNICO

La aplicación debe separar:

PRESENTACIÓN

↓

CONTROLADOR

↓

VALIDACIÓN

↓

AUTORIZACIÓN

↓

LÓGICA DE NEGOCIO

↓

MODELOS / BASE DE DATOS

No colocar toda la lógica dentro de los controladores.

---

# 4. ESTRUCTURA GENERAL

Estructura recomendada:

```text
app/
├── Actions/
├── Console/
├── Enums/
├── Events/
├── Exceptions/
├── Http/
│   ├── Controllers/
│   ├── Middleware/
│   ├── Requests/
│   └── Resources/
├── Jobs/
├── Listeners/
├── Models/
├── Notifications/
├── Policies/
├── Providers/
├── Services/
├── Support/
└── ValueObjects/
```

No es obligatorio crear todas las carpetas desde el inicio.

Solo deben utilizarse cuando exista una necesidad real.

---

# 5. MODELOS

Los modelos representan entidades persistentes.

Ejemplos:

```text
Company
User
Urbanizacion
Manzano
Lote
Cliente
Reserva
Venta
PlanPago
Cuota
Pago
Comision
AuditLog
```

Los modelos pueden contener:

* Relaciones.
* Casts.
* Scopes.
* Accessors.
* Mutators simples.

No deben convertirse en archivos gigantes con toda la lógica del sistema.

---

# 6. CONTROLADORES

Los controladores deben ser delgados.

Responsabilidades:

1. Recibir solicitud.
2. Autorizar.
3. Validar.
4. Invocar acción o servicio.
5. Devolver respuesta.

Ejemplo recomendado:

```php
public function store(StoreReservaRequest $request)
{
    $reserva = $this->createReserva->execute(
        $request->validated(),
        auth()->user()
    );

    return redirect()
        ->route('reservas.show', $reserva)
        ->with('success', 'Reserva creada correctamente.');
}
```

No recomendado:

Un método `store()` de cientos de líneas que:

* valida,
* cambia lote,
* crea cliente,
* registra pago,
* genera cuotas,
* envía WhatsApp,
* actualiza reportes,
* etc.

---

# 7. FORM REQUESTS

Toda validación importante debe utilizar:

```text
app/Http/Requests/
```

Ejemplos:

```text
StoreClienteRequest
UpdateClienteRequest

StoreReservaRequest
ExtendReservaRequest
CancelReservaRequest

StoreVentaRequest
ConfirmVentaRequest
CancelVentaRequest

StorePagoRequest
ConfirmPagoRequest
CancelPagoRequest
```

Los controladores no deberían tener grandes bloques de `$request->validate()` repetidos.

---

# 8. POLICIES

La autorización debe centralizarse utilizando Policies cuando sea apropiado.

Ejemplos:

```text
LotePolicy
ClientePolicy
ReservaPolicy
VentaPolicy
PagoPolicy
UserPolicy
```

Una policy puede validar:

* Rol.
* Permiso.
* Empresa.
* Alcance.
* Propiedad del registro.

Ejemplo conceptual:

```text
Usuario
+
permiso ventas.ver
+
misma company
+
alcance permitido

=
puede ver venta
```

---

# 9. MIDDLEWARE

Utilizar middleware para condiciones transversales.

Ejemplos:

```text
EnsureCompanyIsActive
EnsureUserIsActive
SetCurrentCompany
EnsureApiAccess
```

No crear middleware para cada pequeña regla comercial.

---

# 10. SERVICES

Los servicios contienen lógica reutilizable que puede participar en distintos flujos.

Ejemplos:

```text
ReservationService
SaleService
PaymentService
InstallmentService
CommissionService
DocumentService
AuditService
```

Un Service puede coordinar múltiples operaciones relacionadas.

---

# 11. ACTIONS

Para operaciones concretas se recomienda utilizar Actions.

Ejemplos:

```text
CreateReservation
CancelReservation
ExtendReservation
ConvertReservationToSale

CreateSale
CancelSale
ConfirmSale

RegisterPayment
ConfirmPayment
CancelPayment

GeneratePaymentPlan
ApplyPaymentToInstallments
```

Una Action debe representar una acción empresarial clara.

---

# 12. SERVICES VS ACTIONS

Usar Action cuando la operación tenga un objetivo concreto.

Ejemplo:

```text
CreateReservation
```

Usar Service cuando exista lógica reutilizable o coordinación más amplia.

Ejemplo:

```text
PaymentAllocationService
```

No crear capas innecesarias solo por arquitectura.

---

# 13. TRANSACCIONES

Las operaciones que modifican varias entidades deben ejecutarse dentro de:

```php
DB::transaction(...)
```

Ejemplo:

Crear una venta puede modificar:

* Venta.
* Lote.
* Reserva.
* Plan de pagos.
* Cuotas.
* Pago inicial.

Debe tratarse como una sola operación lógica.

---

# 14. LOCKS Y CONCURRENCIA

Reservas y ventas deben comprobar nuevamente el estado del lote dentro de la transacción.

Cuando corresponda utilizar:

```php
lockForUpdate()
```

Ejemplo:

```php
$lote = Lote::query()
    ->whereKey($loteId)
    ->lockForUpdate()
    ->firstOrFail();
```

Esto ayuda a evitar:

Usuario A reserva lote.

Mientras:

Usuario B vende el mismo lote.

---

# 15. ENUMS

Los estados importantes deberían centralizarse.

Ejemplos:

```text
LoteStatus
ReservaStatus
VentaStatus
PagoStatus
CuotaStatus
```

Ejemplo:

```php
enum LoteStatus: string
{
    case DISPONIBLE = 'disponible';
    case RESERVADO = 'reservado';
    case VENDIDO = 'vendido';
    case BLOQUEADO = 'bloqueado';
}
```

Evitar cadenas escritas manualmente en decenas de archivos.

---

# 16. EVENTS

Utilizar eventos para efectos secundarios.

Ejemplos:

```text
ReservationCreated
ReservationExpired
SaleConfirmed
PaymentConfirmed
InstallmentOverdue
```

Los eventos permiten separar la operación principal de procesos secundarios.

---

# 17. LISTENERS

Ejemplo:

Evento:

```text
PaymentConfirmed
```

Listeners:

```text
UpdateSaleBalance
GenerateReceipt
CreateAuditEntry
SendPaymentNotification
```

No todo debe ejecutarse necesariamente dentro del controlador.

---

# 18. JOBS

Procesos que pueden ejecutarse en segundo plano:

```text
ExpireReservationsJob
SendPaymentReminderJob
GenerateLargeReportJob
SendWhatsAppNotificationJob
GenerateContractPdfJob
```

Los procesos pesados no deberían bloquear la solicitud del usuario.

---

# 19. SCHEDULER

Laravel Scheduler puede gestionar:

Reservas vencidas.

Cuotas vencidas.

Recordatorios.

Notificaciones.

Ejemplo conceptual:

```text
Cada hora:
revisar reservas vencidas.

Cada día:
revisar cuotas vencidas.

Cada mañana:
enviar recordatorios configurados.
```

---

# 20. NOTIFICACIONES

Utilizar el sistema de notificaciones de Laravel cuando sea apropiado.

Canales posibles:

* Interna.
* Email.
* WhatsApp.
* Push.
* SMS.

No acoplar directamente la lógica de venta a un proveedor específico.

---

# 21. INTEGRACIONES EXTERNAS

Toda integración debe estar aislada.

Ejemplo:

```text
app/
└── Services/
    └── Integrations/
        ├── WhatsApp/
        ├── Maps/
        ├── Banking/
        └── Ai/
```

No llamar directamente una API externa desde múltiples controladores.

---

# 22. INTERFACES PARA INTEGRACIONES

Cuando existan distintos proveedores, utilizar contratos.

Ejemplo:

```php
interface WhatsAppProvider
{
    public function sendMessage(string $phone, string $message): void;
}
```

Implementaciones:

```text
MetaWhatsAppProvider
TwilioWhatsAppProvider
OtherProvider
```

Esto permite cambiar proveedor sin modificar el CORE.

---

# 23. CONFIGURACIÓN POR EMPRESA

Las reglas variables deben obtenerse desde configuración.

Ejemplo:

```text
reservation_days

advisor_max_discount

supervisor_max_discount

default_currency

late_fee_rate

commission_percentage
```

No hacer:

```php
if ($company->name === 'Empresa X') {
    $days = 10;
}
```

Eso genera deuda técnica rápidamente.

---

# 24. COMPANY CONTEXT

La aplicación debe conocer la empresa activa.

Conceptualmente:

```text
CurrentCompany
```

Puede resolverse mediante:

* usuario,
* subdominio,
* dominio,
* sesión,
* token API.

Las consultas empresariales deben quedar limitadas a esa empresa.

---

# 25. MULTIEMPRESA

Toda consulta sensible debe filtrar por:

```text
company_id
```

No confiar únicamente en IDs enviados por frontend.

Ejemplo prohibido:

```php
Venta::find($request->venta_id);
```

sin verificar empresa.

Preferible:

```php
Venta::query()
    ->where('company_id', currentCompanyId())
    ->findOrFail($id);
```

---

# 26. GLOBAL SCOPES

Puede evaluarse un Global Scope para `company_id`.

Pero debe usarse cuidadosamente.

Ventaja:

Reduce riesgo de consultas cruzadas.

Riesgo:

Puede ocultar comportamiento y complicar operaciones de Super Admin.

La decisión final debe documentarse antes de implementarlo.

---

# 27. QUERY SCOPES

Los modelos pueden incluir scopes útiles.

Ejemplo:

```php
Lote::available()
Lote::reserved()
Venta::active()
Cuota::overdue()
```

Esto mejora legibilidad y evita repetir consultas.

---

# 28. API

Las rutas API deben versionarse.

Ejemplo:

```text
/api/v1/
```

No usar endpoints sin versión para aplicaciones móviles importantes.

Ejemplo:

```text
/api/v1/auth/login
/api/v1/urbanizaciones
/api/v1/lotes
/api/v1/clientes
/api/v1/reservas
/api/v1/ventas
```

---

# 29. API RESOURCES

Las respuestas JSON deberían utilizar:

```text
Http/Resources
```

Ejemplos:

```text
LoteResource
ClienteResource
ReservaResource
VentaResource
PagoResource
```

Evitar devolver modelos completos sin control.

---

# 30. FORMATO API

Mantener respuestas consistentes.

Ejemplo exitoso:

```json
{
    "success": true,
    "data": {},
    "message": "Operación realizada correctamente."
}
```

Error:

```json
{
    "success": false,
    "message": "El lote ya no está disponible.",
    "errors": {}
}
```

---

# 31. ANDROID

Si existe aplicación Android:

Android
↓
API REST
↓
Laravel
↓
MySQL

La aplicación móvil no debe conectarse directamente a MySQL.

---

# 32. WEB Y APP

Web y móvil deben reutilizar:

* mismas reglas,
* mismos servicios,
* mismas Policies,
* misma base de datos.

No crear una lógica diferente para vender desde Android y desde web.

---

# 33. FRONTEND

El frontend debe ser principalmente una capa de presentación.

Evitar lógica crítica únicamente en JavaScript.

Ejemplo:

El frontend puede impedir presionar dos veces "Vender".

Pero el backend igualmente debe comprobar que el lote siga disponible.

---

# 34. COMPONENTES REUTILIZABLES

Crear componentes cuando haya patrones repetidos.

Ejemplos:

```text
StatusBadge
MoneyDisplay
ClientCard
LotCard
PaymentTable
ConfirmModal
```

Esto facilita personalización visual por empresa.

---

# 35. BRANDING

Los colores y logos deben provenir de configuración.

Ejemplo CSS:

```css
--primary-color
--secondary-color
--accent-color
```

No repetir colores específicos del cliente en múltiples archivos Blade.

---

# 36. LAYOUT

Mantener un layout central.

Ejemplo:

```text
resources/views/layouts/app.blade.php
```

Las personalizaciones deberían aplicarse desde:

* configuración,
* variables,
* componentes,
* temas.

---

# 37. ARCHIVOS Y DOCUMENTOS

No guardar archivos grandes directamente en MySQL.

Guardar:

* ruta,
* nombre,
* tipo,
* tamaño,
* metadata.

El archivo debe almacenarse mediante Laravel Storage.

---

# 38. STORAGE

Utilizar:

```text
Storage
```

y discos configurables.

Ejemplo:

```text
local
public
s3
```

Esto permitirá migrar de almacenamiento local a AWS S3 sin reescribir módulos.

---

# 39. CONTRATOS

El generador de contratos debe separar:

Plantilla

*

Datos de venta

=

Documento generado

La lógica no debería estar escrita dentro de una vista Blade gigante.

---

# 40. AUDITORÍA

Crear un servicio central:

```text
AuditService
```

o utilizar un paquete compatible.

Registrar:

* usuario,
* empresa,
* acción,
* entidad,
* cambios,
* fecha.

---

# 41. LOGGING

Los logs técnicos deben utilizar:

```text
Log::info()
Log::warning()
Log::error()
```

No utilizar auditoría empresarial como sustituto de logs técnicos.

Son cosas diferentes.

---

# 42. ERRORES

Los errores empresariales deberían ser claros.

Ejemplo:

```text
El lote ya fue reservado por otro usuario.
```

No mostrar al usuario:

```text
SQLSTATE[23000]...
```

Los detalles técnicos deben registrarse en logs.

---

# 43. EXCEPTIONS

Para reglas comerciales complejas pueden utilizarse excepciones específicas.

Ejemplo:

```text
LotNotAvailableException
ReservationExpiredException
PaymentAlreadyCancelledException
```

---

# 44. TESTS

Estructura recomendada:

```text
tests/
├── Feature/
├── Unit/
└── Integration/
```

---

# 45. FEATURE TESTS

Prioridad alta.

Ejemplos:

```text
CreateReservationTest
CancelReservationTest
CreateSaleTest
RegisterPaymentTest
PermissionsTest
CompanyIsolationTest
```

---

# 46. UNIT TESTS

Para lógica aislada.

Ejemplos:

```text
PaymentAllocationServiceTest
CommissionCalculatorTest
InstallmentCalculatorTest
```

---

# 47. TESTS DE AISLAMIENTO

Si existe multiempresa, estos tests son obligatorios.

Ejemplo:

Usuario Empresa A

intenta consultar:

Venta Empresa B

Resultado esperado:

403 o 404.

---

# 48. FACTORIES

Crear factories para:

```text
Company
User
Urbanizacion
Manzano
Lote
Cliente
Reserva
Venta
Pago
```

Esto acelera enormemente las pruebas.

---

# 49. SEEDERS

Separar:

Datos del sistema.

Datos demo.

Ejemplo:

```text
RolePermissionSeeder
DefaultSettingsSeeder
DemoCompanySeeder
DemoUrbanizacionSeeder
```

No mezclar información ficticia con configuración necesaria para producción.

---

# 50. MIGRACIONES

Nunca modificar una migración ya ejecutada en producción.

Crear una nueva migración.

Ejemplo:

Incorrecto:

editar:

```text
2026_01_01_create_ventas_table.php
```

después de estar en producción.

Correcto:

crear:

```text
2026_08_10_add_contract_number_to_ventas_table.php
```

---

# 51. GIT

Flujo recomendado:

```text
main
```

Producción estable.

```text
develop
```

Integración opcional.

Features:

```text
feature/reservations
feature/payment-module
feature/client-branding
```

Hotfix:

```text
hotfix/payment-calculation
```

---

# 52. COMMITS

Cada commit debe representar un cambio lógico.

Ejemplo:

```text
feat: add reservation expiration workflow
```

```text
fix: prevent duplicate lot sales
```

```text
test: add company isolation tests
```

Evitar commits enormes con múltiples módulos sin relación.

---

# 53. CONFIGURACIÓN

Todo secreto debe mantenerse fuera del repositorio.

Usar:

```text
.env
```

Ejemplos:

* DB_PASSWORD
* API_KEYS
* AWS_SECRET
* WHATSAPP_TOKEN

Nunca subir secretos a GitHub.

---

# 54. ENTORNOS

Definir claramente:

LOCAL

STAGING

PRODUCTION

Cuando el proyecto crezca, evitar probar cambios directamente en producción.

---

# 55. DEPLOYMENT

El despliegue debería incluir:

```text
git pull
composer install
php artisan migrate --force
php artisan optimize
npm build
restart queue
```

La secuencia exacta se documentará en:

```text
DEPLOYMENT.md
```

---

# 56. QUEUES

Si se utilizan Jobs:

Configurar queue worker.

Ejemplo:

```text
php artisan queue:work
```

En producción usar:

* Supervisor,
* systemd,
* otro administrador de procesos.

---

# 57. CACHE

Puede utilizarse cache para:

* configuraciones,
* catálogos,
* dashboard,
* permisos,
* datos poco variables.

No cachear información crítica sin estrategia de invalidación.

---

# 58. PERFORMANCE

Evitar:

N+1 queries.

Utilizar:

```php
with()
```

cuando corresponda.

Indexar:

* company_id,
* estados,
* foreign keys,
* fechas de búsqueda.

---

# 59. PAGINACIÓN

Listados grandes deben utilizar paginación.

No cargar:

10.000 clientes

o

50.000 pagos

en una sola petición.

---

# 60. REPORTES GRANDES

Los reportes pesados deberían:

* ejecutarse mediante Jobs,
* exportarse,
* utilizar filtros,
* procesarse por chunks.

---

# 61. SEGURIDAD

La arquitectura debe incluir:

* CSRF.
* Rate limiting.
* Validation.
* Authorization.
* Password hashing.
* HTTPS.
* Sanitización adecuada.
* Protección de archivos privados.

---

# 62. ARCHIVOS PRIVADOS

Contratos, CI y comprobantes no deberían estar públicamente accesibles mediante URL directa sin autorización.

Utilizar almacenamiento privado y endpoints controlados cuando corresponda.

---

# 63. IA

Los módulos de IA deben considerarse integraciones externas.

Ejemplo:

```text
AiService
```

No permitir que un proveedor de IA quede acoplado directamente al CORE.

---

# 64. PERSONALIZACIONES

Cuando una nueva empresa solicite una característica:

Primero preguntar:

¿Es configuración?

Si no:

¿Es módulo adicional?

Si no:

¿Realmente pertenece al CORE?

No modificar estructuras centrales por comodidad.

---

# 65. MODULARIDAD

No es obligatorio usar una arquitectura de paquetes Laravel desde el inicio.

Mientras el sistema sea manejable, puede mantenerse en una aplicación Laravel monolítica bien organizada.

Solo separar en paquetes/microservicios cuando exista una necesidad real.

---

# 66. NO USAR MICROSERVICIOS PREMATURAMENTE

Terrenos no necesita microservicios únicamente porque pueda tener varias empresas.

Un monolito modular bien diseñado suele ser suficiente inicialmente.

Microservicios aumentan:

* complejidad,
* despliegue,
* monitoreo,
* costos,
* debugging.

---

# 67. ORDEN DE DEPENDENCIAS

Dependencia recomendada:

Controller
→ Action / Service
→ Model

Evitar:

Model
→ Controller

o

Service
→ HTTP Request

Las capas inferiores no deberían depender de la capa web.

---

# 68. REGLA PARA AGENTES DE IA

Antes de programar una nueva función:

1. Leer `BASE_MASTER.md`.
2. Leer `BUSINESS_RULES.md`.
3. Leer `ROLES_PERMISSIONS.md`.
4. Leer `DATABASE_BASE.md`.
5. Leer este documento.
6. Leer `CLIENT_TEMPLATE.md`.
7. Identificar módulo afectado.
8. Inspeccionar solamente archivos necesarios.
9. Definir si requiere Action, Service, Request, Policy o Job.
10. No poner lógica empresarial compleja en Controller.
11. No modificar CORE si puede resolverse mediante configuración.
12. Ejecutar tests específicos.
13. Actualizar documentación cuando cambie arquitectura.

---

# 69. REGLA DE AHORRO DE TOKENS

El agente NO debe comenzar cada tarea analizando todo el repositorio.

Orden recomendado:

```text
AGENTS.md
↓
CURRENT_STATUS.md
↓
Documento específico
↓
Archivos directamente relacionados
```

Ejemplo:

Tarea:

Modificar reservas.

Leer:

```text
AGENTS.md
BUSINESS_RULES.md
CURRENT_STATUS.md
```

Después:

```text
Reserva.php
ReservationService.php
CreateReservation.php
ReservaController.php
StoreReservaRequest.php
ReservaPolicy.php
tests relacionados
```

No revisar módulos de pagos, usuarios, reportes o contratos salvo que la tarea los afecte.

---

# 70. CURRENT_STATUS

Todo proyecto derivado debe mantener:

```text
docs/CURRENT_STATUS.md
```

Debe contener:

* Funciones terminadas.
* Funciones en desarrollo.
* Bugs conocidos.
* Próximos pasos.
* Decisiones recientes.
* Áreas que no deben modificarse.

---

# 71. PRINCIPIO FINAL

La arquitectura de Terrenos debe priorizar:

CLARIDAD

*

MODULARIDAD

*

REUTILIZACIÓN

*

SEGURIDAD

*

PRUEBAS

*

AISLAMIENTO POR EMPRESA

La solución preferida debe ser siempre la más simple que mantenga correctamente estas propiedades.

La arquitectura no debe complicarse únicamente para parecer más avanzada.
