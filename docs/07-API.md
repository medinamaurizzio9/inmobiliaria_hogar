# API.md

# API BASE — PLATAFORMA TERRENOS

## 1. OBJETIVO

Este documento define la estructura base de la API para todas las implementaciones derivadas de Terrenos.

La API debe permitir:

* Aplicaciones Android.
* Portales de clientes.
* Integraciones externas.
* Automatizaciones.
* Aplicaciones futuras.

Principio:

WEB

y

APP MÓVIL

deben utilizar las mismas reglas de negocio y la misma fuente de datos.

---

# 2. VERSIONADO

Todas las rutas deben versionarse.

Base:

```text
/api/v1/
```

Ejemplo:

```text
/api/v1/auth/login
/api/v1/lots
/api/v1/clients
/api/v1/reservations
/api/v1/sales
```

No crear rutas móviles sin versión.

---

# 3. AUTENTICACIÓN

Recomendado:

Laravel Sanctum.

Flujo:

Login
→ token
→ solicitudes autenticadas
→ logout / revocación

---

# 4. LOGIN

Endpoint:

```text
POST /api/v1/auth/login
```

Entrada:

```json
{
  "email": "usuario@empresa.com",
  "password": "********"
}
```

Respuesta:

```json
{
  "success": true,
  "data": {
    "token": "TOKEN",
    "user": {}
  },
  "message": "Inicio de sesión correcto."
}
```

---

# 5. USUARIO ACTUAL

```text
GET /api/v1/auth/me
```

Debe devolver:

* usuario,
* empresa,
* roles,
* permisos,
* alcance básico.

---

# 6. LOGOUT

```text
POST /api/v1/auth/logout
```

Debe revocar el token utilizado.

---

# 7. EMPRESA

La API debe identificar la empresa del usuario autenticado.

Toda consulta empresarial debe respetar:

```text
company_id
```

El cliente móvil no debe poder elegir libremente un `company_id` para acceder a otra empresa.

---

# 8. FORMATO DE RESPUESTA

Respuesta exitosa:

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
  "message": "No se pudo completar la operación.",
  "errors": {}
}
```

---

# 9. CÓDIGOS HTTP

Utilizar correctamente:

```text
200 OK
201 Created
204 No Content
400 Bad Request
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Unprocessable Entity
429 Too Many Requests
500 Internal Server Error
```

---

# 10. VALIDACIONES

Los errores de validación deben responder:

```text
422
```

Ejemplo:

```json
{
  "success": false,
  "message": "Los datos enviados no son válidos.",
  "errors": {
    "cliente_id": [
      "El cliente es obligatorio."
    ]
  }
}
```

---

# 11. AUTORIZACIÓN

La API debe aplicar exactamente las mismas reglas de permisos que la web.

No basta con que la app oculte opciones.

Debe validar:

ROL

*

PERMISO

*

ALCANCE

*

EMPRESA

---

# 12. CATÁLOGOS

Endpoint recomendado:

```text
GET /api/v1/catalogs
```

Puede devolver:

* estados de lote,
* tipos de venta,
* métodos de pago,
* monedas,
* estados de reserva,
* estados de pago,
* configuraciones públicas necesarias.

---

# 13. URBANIZACIONES

Listar:

```text
GET /api/v1/urbanizaciones
```

Ver una:

```text
GET /api/v1/urbanizaciones/{id}
```

Crear:

```text
POST /api/v1/urbanizaciones
```

Editar:

```text
PUT /api/v1/urbanizaciones/{id}
```

Archivar:

```text
POST /api/v1/urbanizaciones/{id}/archive
```

---

# 14. MANZANOS

```text
GET /api/v1/manzanos
GET /api/v1/manzanos/{id}
POST /api/v1/manzanos
PUT /api/v1/manzanos/{id}
```

Filtros:

```text
?urbanizacion_id=10
```

---

# 15. LOTES

Listar:

```text
GET /api/v1/lotes
```

Detalle:

```text
GET /api/v1/lotes/{id}
```

Filtros sugeridos:

```text
?urbanizacion_id=
?manzano_id=
?estado=
?precio_min=
?precio_max=
?search=
```

---

# 16. DISPONIBILIDAD DE LOTES

Endpoint opcional:

```text
GET /api/v1/lotes/disponibles
```

Debe respetar el alcance del usuario.

---

# 17. CAMBIO DE ESTADO DE LOTE

No utilizar un endpoint genérico que permita establecer cualquier estado sin reglas.

Preferir acciones explícitas:

```text
POST /api/v1/lotes/{id}/block
POST /api/v1/lotes/{id}/unblock
```

Los estados derivados de ventas y reservas deben cambiarse mediante sus respectivos flujos.

---

# 18. CLIENTES

```text
GET /api/v1/clientes
GET /api/v1/clientes/{id}
POST /api/v1/clientes
PUT /api/v1/clientes/{id}
```

Búsqueda:

```text
GET /api/v1/clientes?search=Juan
```

o

```text
GET /api/v1/clientes?documento=1234567
```

---

# 19. DOCUMENTOS DE CLIENTE

```text
GET /api/v1/clientes/{id}/documentos
POST /api/v1/clientes/{id}/documentos
```

El acceso debe estar protegido.

---

# 20. RESERVAS

Listar:

```text
GET /api/v1/reservas
```

Detalle:

```text
GET /api/v1/reservas/{id}
```

Crear:

```text
POST /api/v1/reservas
```

Ejemplo:

```json
{
  "cliente_id": 15,
  "lote_id": 28,
  "monto_reserva": 1000,
  "observaciones": "Reserva inicial."
}
```

---

# 21. CREACIÓN DE RESERVA

El backend debe:

1. Validar usuario.
2. Validar empresa.
3. Validar lote.
4. Validar disponibilidad.
5. Evitar reserva concurrente.
6. Crear reserva.
7. Cambiar lote a reservado.
8. Registrar auditoría.

Todo dentro de una operación consistente.

---

# 22. EXTENDER RESERVA

```text
POST /api/v1/reservas/{id}/extend
```

Entrada:

```json
{
  "fecha_vencimiento": "2026-09-20",
  "motivo": "Cliente solicitó ampliación."
}
```

---

# 23. CANCELAR RESERVA

```text
POST /api/v1/reservas/{id}/cancel
```

Entrada:

```json
{
  "motivo": "Cliente desistió."
}
```

---

# 24. CONVERTIR RESERVA EN VENTA

Puede manejarse desde creación de venta:

```text
POST /api/v1/ventas
```

enviando:

```json
{
  "reserva_id": 50,
  "cliente_id": 15,
  "lote_id": 28
}
```

No crear dos procesos independientes que puedan quedar inconsistentes.

---

# 25. VENTAS

```text
GET /api/v1/ventas
GET /api/v1/ventas/{id}
POST /api/v1/ventas
PUT /api/v1/ventas/{id}
```

---

# 26. CREAR VENTA

Ejemplo:

```json
{
  "cliente_id": 15,
  "lote_id": 28,
  "reserva_id": 50,
  "tipo_venta": "cuotas",
  "precio_final": 95000,
  "cuota_inicial": 10000,
  "numero_cuotas": 24
}
```

El backend debe recalcular y validar los importes.

Nunca confiar ciegamente en totales enviados por la app.

---

# 27. CONFIRMAR VENTA

```text
POST /api/v1/ventas/{id}/confirm
```

Debe validar permisos y estado actual.

---

# 28. CANCELAR VENTA

```text
POST /api/v1/ventas/{id}/cancel
```

Entrada:

```json
{
  "motivo": "Resolución contractual."
}
```

No eliminar físicamente la venta.

---

# 29. PLAN DE PAGOS

```text
GET /api/v1/ventas/{id}/plan-pago
```

Debe devolver:

* monto total,
* inicial,
* saldo,
* cuotas,
* vencimientos,
* estados.

---

# 30. CUOTAS

```text
GET /api/v1/cuotas
GET /api/v1/cuotas/{id}
```

Filtros:

```text
?estado=vencida
?cliente_id=
?venta_id=
?fecha_desde=
?fecha_hasta=
```

---

# 31. PAGOS

```text
GET /api/v1/pagos
GET /api/v1/pagos/{id}
POST /api/v1/pagos
```

---

# 32. REGISTRAR PAGO

Ejemplo:

```json
{
  "venta_id": 100,
  "monto": 1500,
  "metodo_pago": "qr",
  "numero_referencia": "ABC123"
}
```

La moneda y cliente deberían derivarse de la venta cuando corresponda.

---

# 33. COMPROBANTES

Puede utilizarse:

```text
multipart/form-data
```

para subir comprobantes.

Endpoint:

```text
POST /api/v1/pagos/{id}/comprobante
```

---

# 34. CONFIRMAR PAGO

```text
POST /api/v1/pagos/{id}/confirm
```

Solo usuarios autorizados.

Al confirmar:

* reducir saldo,
* aplicar a cuotas,
* actualizar estados,
* registrar auditoría.

---

# 35. ANULAR PAGO

```text
POST /api/v1/pagos/{id}/cancel
```

Debe requerir:

```json
{
  "motivo": "Pago registrado dos veces."
}
```

El sistema debe recalcular automáticamente los saldos.

---

# 36. COMISIONES

```text
GET /api/v1/comisiones
GET /api/v1/comisiones/mias
```

Opcionales:

```text
POST /api/v1/comisiones/{id}/approve
POST /api/v1/comisiones/{id}/pay
```

---

# 37. DASHBOARD

```text
GET /api/v1/dashboard
```

La respuesta debe depender del rol.

Ejemplo asesor:

```json
{
  "reservas_activas": 5,
  "ventas_mes": 3,
  "clientes": 20
}
```

Ejemplo administrador:

Puede incluir indicadores globales.

---

# 38. REPORTES

```text
GET /api/v1/reportes/ventas
GET /api/v1/reportes/pagos
GET /api/v1/reportes/mora
GET /api/v1/reportes/disponibilidad
```

Los reportes deben tener filtros.

---

# 39. EXPORTACIONES

Para reportes pesados:

```text
POST /api/v1/reportes/ventas/export
```

Respuesta:

```text
202 Accepted
```

Luego el sistema puede procesarlo mediante Job.

---

# 40. PAGINACIÓN

Los listados deben paginarse.

Ejemplo:

```text
GET /api/v1/clientes?page=2&per_page=25
```

Respuesta recomendada:

```json
{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 2,
    "last_page": 10,
    "per_page": 25,
    "total": 250
  }
}
```

---

# 41. ORDENAMIENTO

Ejemplo:

```text
?sort=fecha_venta
?direction=desc
```

Solo permitir campos autorizados.

No concatenar directamente entradas del usuario en SQL.

---

# 42. FILTROS

Los filtros deben ser consistentes.

Ejemplo:

```text
?estado=
?search=
?fecha_desde=
?fecha_hasta=
?asesor_id=
?urbanizacion_id=
```

---

# 43. API RESOURCES

Utilizar Resources.

Ejemplos:

```text
UrbanizacionResource
LoteResource
ClienteResource
ReservaResource
VentaResource
PagoResource
CuotaResource
```

No devolver automáticamente todos los campos del modelo.

---

# 44. DATOS SENSIBLES

No exponer:

* password,
* tokens,
* secretos,
* claves API,
* datos internos de auditoría innecesarios.

---

# 45. ARCHIVOS PRIVADOS

Los documentos privados no deberían devolverse como enlaces públicos permanentes.

Puede utilizarse:

```text
GET /api/v1/documentos/{id}/download
```

El backend debe autorizar la descarga.

---

# 46. RATE LIMITING

Aplicar límites especialmente a:

```text
/login
/password
/search
/uploads
```

y APIs públicas.

---

# 47. IDEMPOTENCIA

Muy importante para aplicaciones móviles.

Operaciones financieras y reservas pueden incluir:

```text
Idempotency-Key
```

Ejemplo:

```text
POST /api/v1/pagos
Idempotency-Key: 8fe7...
```

Si la app reintenta por pérdida de conexión, el sistema no debe duplicar el pago.

---

# 48. CLIENT REQUEST ID

Recomendado:

```text
X-Request-ID
```

Permite rastrear una operación desde:

App
→ API
→ Logs
→ Auditoría

---

# 49. CONCURRENCIA

La API debe manejar casos como:

Usuario A:

```text
POST /reservas
```

y Usuario B:

```text
POST /ventas
```

sobre el mismo lote.

Una de las operaciones debe fallar de forma controlada.

Respuesta sugerida:

```text
409 Conflict
```

Ejemplo:

```json
{
  "success": false,
  "message": "El lote ya no está disponible."
}
```

---

# 50. SINCRONIZACIÓN MÓVIL

Si una app necesita modo offline, agregar posteriormente endpoints específicos.

Ejemplo:

```text
GET /api/v1/mobile/sync?since=...
```

Pero no implementarlo en el CORE hasta que exista necesidad real.

---

# 51. FECHAS

Usar formato estándar:

```text
ISO 8601
```

Ejemplo:

```text
2026-08-09T14:30:00-04:00
```

Evitar formatos ambiguos como:

```text
09/08/26
```

---

# 52. MONEDA

Los valores deberían devolver:

```json
{
  "amount": "1000.00",
  "currency": "BOB"
}
```

No asumir moneda por el idioma de la aplicación.

---

# 53. DECIMALES

Evitar representar dinero internamente con float.

En JSON puede enviarse como string decimal cuando sea conveniente.

---

# 54. ERRORES DE NEGOCIO

Ejemplos:

```text
LOT_NOT_AVAILABLE
RESERVATION_EXPIRED
PAYMENT_ALREADY_CANCELLED
DISCOUNT_NOT_AUTHORIZED
```

Puede devolverse un código además del mensaje:

```json
{
  "success": false,
  "code": "LOT_NOT_AVAILABLE",
  "message": "El lote ya no está disponible."
}
```

Esto facilita Android.

---

# 55. COMPATIBILIDAD

Una modificación de API no debe romper versiones móviles existentes sin planificación.

Evitar cambiar:

```text
cliente_id
```

por:

```text
buyer
```

arbitrariamente en una API publicada.

Cuando haya cambios incompatibles:

crear nueva versión.

---

# 56. DOCUMENTACIÓN

La API debería documentarse posteriormente con:

* OpenAPI,
* Swagger,
* Postman Collection,

o herramienta equivalente.

La documentación debe derivarse del comportamiento real de la API.

---

# 57. PRUEBAS DE API

Debe existir cobertura para:

* Login válido.
* Login inválido.
* Usuario desactivado.
* Permisos.
* Aislamiento de empresa.
* Crear reserva.
* Conflicto de reserva.
* Crear venta.
* Registrar pago.
* Anular pago.
* Validaciones.
* Paginación.

---

# 58. TEST DE AISLAMIENTO

Escenario obligatorio:

Usuario Empresa A

solicita:

```text
GET /api/v1/ventas/VENTA_EMPRESA_B
```

Resultado:

```text
403
```

o

```text
404
```

Nunca devolver la venta.

---

# 59. API PARA SUPER ADMIN

Si posteriormente existe plataforma SaaS:

Las rutas globales de administración deberían separarse.

Ejemplo:

```text
/api/v1/platform/companies
```

No mezclar administración de plataforma con endpoints comerciales normales.

---

# 60. WEBHOOKS

Si una integración necesita notificar eventos:

Ejemplos:

* pago bancario confirmado,
* firma digital,
* WhatsApp,
* facturación.

Crear endpoints específicos.

Ejemplo:

```text
POST /api/v1/webhooks/payments/provider
```

Debe validarse:

* firma,
* secreto,
* origen,
* idempotencia.

---

# 61. NUNCA CONFIAR EN EL CLIENTE

La API debe recalcular y verificar reglas críticas.

Ejemplo:

La app envía:

```json
{
  "precio_final": 50000
}
```

El backend debe comprobar:

* precio listado,
* descuento,
* límite del usuario,
* configuración empresarial.

La aplicación cliente nunca es fuente de autoridad.

---

# 62. REGLA PARA NUEVOS ENDPOINTS

Antes de crear un endpoint:

1. Identificar recurso.
2. Identificar operación.
3. Definir permiso.
4. Definir alcance.
5. Definir validación.
6. Definir respuesta.
7. Definir errores.
8. Definir auditoría.
9. Definir tests.

---

# 63. REGLA PARA AGENTES DE IA

Antes de modificar la API:

1. Leer `BASE_MASTER.md`.
2. Leer `BUSINESS_RULES.md`.
3. Leer `ROLES_PERMISSIONS.md`.
4. Leer `DATABASE_BASE.md`.
5. Leer `ARCHITECTURE.md`.
6. Leer este documento.
7. Leer `CLIENT_TEMPLATE.md`.
8. Revisar solo endpoints afectados.
9. Mantener compatibilidad cuando corresponda.
10. No duplicar lógica existente.
11. Utilizar Actions/Services existentes.
12. Ejecutar tests de API específicos.
13. Actualizar documentación si cambia el contrato API.

---

# 64. AHORRO DE CONTEXTO

Para tareas API el agente debe leer:

```text
AGENTS.md
CURRENT_STATUS.md
API.md
```

Después:

* rutas relacionadas,
* controlador relacionado,
* Form Request,
* Resource,
* Policy,
* Action/Service,
* tests correspondientes.

No analizar automáticamente toda la aplicación.

---

# 65. PRINCIPIO FINAL

La API debe ser:

ESTABLE

*

SEGURA

*

VERSIONADA

*

PREDECIBLE

*

REUTILIZABLE

*

AISLADA POR EMPRESA

Todas las aplicaciones externas deben utilizar la API como puerta de entrada al CORE de Terrenos.

No debe existir una lógica empresarial distinta para web, Android o integraciones.
