# DATABASE_BASE.md

# MODELO BASE DE DATOS — PLATAFORMA TERRENOS

## 1. OBJETIVO

Este documento define el modelo de datos base para todas las implementaciones derivadas de Terrenos.

La estructura debe soportar:

* Urbanizaciones
* Manzanos
* Lotes
* Clientes
* Reservas
* Ventas
* Pagos
* Cuotas
* Usuarios
* Roles y permisos
* Auditoría
* Configuración por empresa

Principio:

La base debe permitir reutilizar el sistema para distintas inmobiliarias sin reconstruir las relaciones principales.

---

# 2. ENFOQUE MULTIEMPRESA

Las tablas comerciales principales deben considerar:

`company_id`

Esto permite:

* Una instalación por empresa.
* Varias empresas en una misma instalación.
* Migración futura hacia SaaS.

Aunque una instalación tenga una sola empresa, `company_id` puede mantenerse.

Regla:

Ninguna consulta empresarial debe mezclar información de compañías diferentes.

---

# 3. TABLA COMPANIES

Tabla:

`companies`

Campos sugeridos:

* id
* legal_name
* commercial_name
* nit nullable
* email nullable
* phone nullable
* whatsapp nullable
* address nullable
* city nullable
* department nullable
* country
* website nullable
* logo nullable
* favicon nullable
* primary_color nullable
* secondary_color nullable
* accent_color nullable
* is_active
* created_at
* updated_at
* deleted_at nullable

---

# 4. USERS

Tabla:

`users`

Campos:

* id
* company_id nullable
* name
* email
* password
* phone nullable
* is_active
* last_login_at nullable
* created_at
* updated_at
* deleted_at nullable

Relación:

Company
→ Users

Un usuario normal pertenece a una empresa.

Un Super Administrador de plataforma podría tener `company_id` nullable si la arquitectura futura lo requiere.

---

# 5. ROLES Y PERMISOS

Se recomienda utilizar una implementación basada en roles y permisos.

Conceptualmente:

`roles`

`permissions`

`model_has_roles`

`role_has_permissions`

Si se utiliza Spatie Laravel Permission, adaptar la estructura a la arquitectura de la aplicación.

Si el sistema es multiempresa:

Los roles deben mantenerse aislados por compañía cuando corresponda.

---

# 6. URBANIZACIONES

Tabla:

`urbanizaciones`

Campos sugeridos:

* id
* company_id
* nombre
* codigo nullable
* descripcion nullable
* propietario nullable
* superficie_total nullable
* direccion nullable
* ciudad nullable
* departamento nullable
* latitude nullable
* longitude nullable
* plano_imagen nullable
* estado
* created_by nullable
* created_at
* updated_at
* deleted_at nullable

Relaciones:

Company
→ Urbanizaciones

Urbanización
→ Manzanos

---

# 7. MANZANOS

Tabla:

`manzanos`

Campos:

* id
* company_id
* urbanizacion_id
* codigo
* nombre nullable
* descripcion nullable
* estado
* created_at
* updated_at
* deleted_at nullable

Restricción recomendada:

UNIQUE:

`urbanizacion_id + codigo`

Esto evita códigos duplicados dentro de la misma urbanización.

---

# 8. LOTES

Tabla:

`lotes`

Campos sugeridos:

* id
* company_id
* manzano_id
* codigo
* superficie
* precio_lista
* moneda
* estado
* latitude nullable
* longitude nullable
* frente nullable
* fondo nullable
* observaciones nullable
* created_at
* updated_at
* deleted_at nullable

Estados base:

* disponible
* reservado
* vendido
* bloqueado

Restricción:

UNIQUE:

`manzano_id + codigo`

---

# 9. LOTES — CAMPOS OPCIONALES

Dependiendo del cliente pueden agregarse:

* precio_m2
* uso_suelo
* tipo_lote
* esquina boolean
* avenida boolean
* servicios_basicos
* documentacion_estado
* imagen_principal
* plano_individual
* metadata JSON nullable

Evitar agregar columnas específicas de un solo cliente al CORE cuando puedan resolverse mediante configuración o metadata.

---

# 10. CLIENTES

Tabla:

`clientes`

Campos sugeridos:

* id
* company_id
* tipo_documento
* numero_documento
* nombres
* apellidos
* telefono nullable
* whatsapp nullable
* email nullable
* direccion nullable
* fecha_nacimiento nullable
* nacionalidad nullable
* estado_civil nullable
* ocupacion nullable
* observaciones nullable
* created_by nullable
* created_at
* updated_at
* deleted_at nullable

Restricción sugerida:

UNIQUE:

`company_id + tipo_documento + numero_documento`

---

# 11. DOCUMENTOS DE CLIENTES

Tabla:

`cliente_documentos`

Campos:

* id
* company_id
* cliente_id
* tipo_documento
* archivo
* numero nullable
* fecha_emision nullable
* fecha_vencimiento nullable
* observaciones nullable
* created_at
* updated_at

Ejemplos:

* CI
* Pasaporte
* Poder
* Formulario
* Contrato
* Otro

---

# 12. RESERVAS

Tabla:

`reservas`

Campos:

* id
* company_id
* cliente_id
* lote_id
* asesor_user_id
* fecha_reserva
* fecha_vencimiento
* monto_reserva nullable
* moneda nullable
* estado
* motivo_cancelacion nullable
* extendida_hasta nullable
* autorizada_por nullable
* observaciones nullable
* created_at
* updated_at
* deleted_at nullable

Estados:

* pendiente
* activa
* vencida
* cancelada
* convertida_venta

---

# 13. HISTORIAL DE RESERVAS

Tabla recomendada:

`reserva_historial`

Campos:

* id
* reserva_id
* user_id nullable
* estado_anterior nullable
* estado_nuevo
* fecha_anterior nullable
* fecha_nueva nullable
* motivo nullable
* metadata JSON nullable
* created_at

Esta tabla permite conservar:

* Extensiones
* Cancelaciones
* Vencimientos
* Cambios administrativos

---

# 14. VENTAS

Tabla:

`ventas`

Campos sugeridos:

* id
* company_id
* cliente_id
* lote_id
* reserva_id nullable
* asesor_user_id nullable
* numero_venta nullable
* precio_lista
* descuento
* precio_final
* moneda
* tipo_venta
* fecha_venta
* estado
* observaciones nullable
* confirmed_by nullable
* confirmed_at nullable
* created_at
* updated_at
* deleted_at nullable

Tipos:

* contado
* credito
* cuotas

Estados sugeridos:

* borrador
* pendiente
* activa
* pagada
* cancelada
* resuelta

---

# 15. REGLA DE VENTA ÚNICA

Un lote no debe tener más de una venta activa.

Esta regla debe garantizarse mediante:

* Validación de negocio.
* Transacción.
* Bloqueo cuando sea necesario.
* Pruebas de concurrencia.

---

# 16. PLANES DE PAGO

Tabla:

`planes_pago`

Campos:

* id
* company_id
* venta_id
* monto_total
* cuota_inicial
* saldo_financiado
* numero_cuotas
* interes_porcentaje nullable
* mora_porcentaje nullable
* fecha_inicio
* estado
* created_at
* updated_at

Una venta financiada debería tener un único plan activo salvo regla explícita.

---

# 17. CUOTAS

Tabla:

`cuotas`

Campos:

* id
* company_id
* plan_pago_id
* numero_cuota
* fecha_vencimiento
* capital
* interes nullable
* mora nullable
* monto_total
* saldo
* estado
* paid_at nullable
* created_at
* updated_at

Estados:

* pendiente
* parcial
* pagada
* vencida

Restricción:

UNIQUE:

`plan_pago_id + numero_cuota`

---

# 18. PAGOS

Tabla:

`pagos`

Campos:

* id
* company_id
* cliente_id
* venta_id
* cuota_id nullable
* monto
* moneda
* metodo_pago
* fecha_pago
* estado
* numero_referencia nullable
* comprobante nullable
* registrado_por
* confirmado_por nullable
* confirmado_at nullable
* anulado_por nullable
* anulado_at nullable
* motivo_anulacion nullable
* observaciones nullable
* created_at
* updated_at

Estados:

* pendiente
* confirmado
* rechazado
* anulado

---

# 19. APLICACIÓN DE PAGOS

Si un pago puede distribuirse entre varias cuotas, no guardar únicamente `cuota_id`.

Crear:

`pago_aplicaciones`

Campos:

* id
* pago_id
* cuota_id
* monto_aplicado
* created_at

Esto permite:

Pago de 2.000 Bs

→ Cuota 1: 1.000 Bs

→ Cuota 2: 1.000 Bs

sin duplicar pagos.

---

# 20. MÉTODOS DE PAGO

Tabla opcional:

`metodos_pago`

Campos:

* id
* company_id nullable
* nombre
* codigo
* requiere_comprobante
* is_active
* created_at
* updated_at

Ejemplos:

* efectivo
* transferencia
* QR
* depósito
* tarjeta

---

# 21. COMISIONES

Tabla:

`comisiones`

Campos:

* id
* company_id
* venta_id
* asesor_user_id
* tipo
* porcentaje nullable
* monto
* estado
* fecha_generacion
* aprobada_por nullable
* pagada_por nullable
* pagada_at nullable
* observaciones nullable
* created_at
* updated_at

Estados:

* pendiente
* aprobada
* pagada
* anulada

---

# 22. ASIGNACIÓN DE URBANIZACIONES

Para limitar usuarios a determinadas urbanizaciones:

Tabla:

`user_urbanizaciones`

Campos:

* id
* user_id
* urbanizacion_id
* created_at

Restricción:

UNIQUE:

`user_id + urbanizacion_id`

Esto permite:

Asesor Juan

→ Urbanización Norte

→ Urbanización Paraíso

sin darle acceso a todas.

---

# 23. EQUIPOS COMERCIALES

Si se requiere manejo de supervisores y asesores:

Tabla:

`teams`

Campos:

* id
* company_id
* nombre
* supervisor_user_id nullable
* is_active
* created_at
* updated_at

Tabla:

`team_user`

Campos:

* team_id
* user_id

---

# 24. SUCURSALES

Tabla opcional:

`sucursales`

Campos:

* id
* company_id
* nombre
* direccion nullable
* ciudad nullable
* telefono nullable
* is_active
* created_at
* updated_at

Tabla:

`branch_user`

Campos:

* sucursal_id
* user_id

---

# 25. CONTRATOS

Tabla opcional pero recomendada:

`contratos`

Campos:

* id
* company_id
* venta_id
* cliente_id
* numero_contrato
* tipo
* archivo nullable
* contenido_snapshot nullable
* estado
* generado_por nullable
* firmado_at nullable
* created_at
* updated_at

No depender únicamente de una plantilla actual.

Guardar una representación/snapshot del contrato generado para conservar el documento histórico.

---

# 26. ARCHIVOS

Cuando la aplicación maneje múltiples documentos puede utilizarse:

`attachments`

Campos:

* id
* company_id
* attachable_type
* attachable_id
* categoria
* nombre_original
* path
* mime_type
* size
* uploaded_by nullable
* created_at

Relación polimórfica.

Puede utilizarse para:

* Lotes
* Clientes
* Urbanizaciones
* Ventas
* Pagos
* Contratos

---

# 27. CONFIGURACIÓN

Tabla:

`company_settings`

Campos:

* id
* company_id
* key
* value
* type nullable
* created_at
* updated_at

Ejemplos:

`reservation_days`

`max_discount_advisor`

`max_discount_supervisor`

`default_currency`

`interest_rate`

`late_fee_rate`

`commission_rule`

Esto evita codificar reglas particulares directamente.

---

# 28. BRANDING

Puede almacenarse dentro de configuración o mediante tabla específica:

`company_branding`

Campos:

* company_id
* logo
* logo_dark nullable
* favicon nullable
* primary_color
* secondary_color
* accent_color nullable
* background_color nullable
* login_background nullable
* created_at
* updated_at

---

# 29. NOTIFICACIONES

Tabla:

`notifications`

Puede usarse el sistema estándar de Laravel.

Eventos posibles:

* Reserva creada
* Reserva por vencer
* Reserva vencida
* Pago registrado
* Pago confirmado
* Cuota por vencer
* Cuota vencida
* Venta creada

---

# 30. AUDITORÍA

Tabla:

`audit_logs`

Campos recomendados:

* id
* company_id nullable
* user_id nullable
* action
* auditable_type nullable
* auditable_id nullable
* old_values JSON nullable
* new_values JSON nullable
* ip_address nullable
* user_agent nullable
* metadata JSON nullable
* created_at

Registrar especialmente:

* Cambios de precio
* Cambios de estado
* Reservas
* Ventas
* Pagos
* Anulaciones
* Descuentos
* Roles
* Permisos
* Configuración

---

# 31. NO AUDITAR DATOS SENSIBLES

No guardar directamente en auditoría:

* Contraseñas
* Tokens
* API keys
* Secretos
* Credenciales externas

Los documentos personales deberían registrarse mediante referencia, no copiando su contenido completo al log.

---

# 32. SOFT DELETES

Utilizar Soft Deletes cuando corresponda para:

* Clientes
* Urbanizaciones
* Manzanos
* Lotes
* Reservas
* Ventas
* Usuarios

Para:

Pagos

preferir anulación mediante estado antes que eliminación.

---

# 33. ÍNDICES IMPORTANTES

Crear índices sobre:

`company_id`

`urbanizacion_id`

`manzano_id`

`lote_id`

`cliente_id`

`venta_id`

`asesor_user_id`

`estado`

`fecha_venta`

`fecha_vencimiento`

Esto será especialmente importante cuando aumente el número de registros.

---

# 34. RELACIONES PRINCIPALES

```text
COMPANY
│
├── USERS
│
├── URBANIZACIONES
│     └── MANZANOS
│           └── LOTES
│
├── CLIENTES
│
├── RESERVAS
│     ├── CLIENTE
│     ├── LOTE
│     └── ASESOR
│
├── VENTAS
│     ├── CLIENTE
│     ├── LOTE
│     ├── RESERVA
│     └── ASESOR
│
├── PLANES DE PAGO
│     └── CUOTAS
│
├── PAGOS
│     └── PAGO_APLICACIONES
│
└── AUDITORÍA
```

---

# 35. RELACIÓN TERRITORIAL

Modelo obligatorio:

Urbanización
→ Manzano
→ Lote

No almacenar `urbanizacion_id` directamente en lote salvo que exista una razón real de rendimiento o arquitectura.

La urbanización puede obtenerse mediante el manzano.

Esto evita información duplicada.

---

# 36. SNAPSHOT DE PRECIOS

La venta debe guardar:

`precio_lista`

`descuento`

`precio_final`

aunque posteriormente cambie el precio actual del lote.

Ejemplo:

Lote hoy:
120.000 Bs

Venta histórica:
100.000 Bs

La venta histórica debe mantenerse en 100.000 Bs.

Nunca recalcular una venta antigua usando el precio actual del lote.

---

# 37. SNAPSHOT DE DATOS COMERCIALES

Para información que deba conservarse históricamente puede utilizarse snapshot.

Ejemplo:

* Precio
* Condiciones del contrato
* Plan financiero
* Descuento autorizado

No asumir que las configuraciones actuales son iguales a las existentes cuando se realizó la venta.

---

# 38. TRANSACCIONES

Operaciones críticas deben usar transacciones de base de datos.

Ejemplo:

CREAR VENTA:

BEGIN TRANSACTION

1. Validar lote.
2. Bloquear registro si corresponde.
3. Crear venta.
4. Actualizar estado lote.
5. Actualizar reserva.
6. Crear plan.
7. Crear cuotas.
8. Crear pago inicial.

COMMIT

Si falla:

ROLLBACK

---

# 39. CONCURRENCIA

Debe evitarse:

Usuario A:
reserva lote 10

al mismo tiempo que

Usuario B:
vende lote 10

La validación visual no es suficiente.

El backend debe volver a comprobar el estado dentro de la operación transaccional.

---

# 40. IDS PÚBLICOS

Considerar UUID o ULID para identificadores expuestos públicamente.

Ejemplo:

URL pública de reserva

no necesariamente debería mostrar:

`/reservas/142`

Puede utilizar:

`/reservas/01J...`

No es obligatorio para claves internas si la arquitectura no lo requiere.

---

# 41. MONEDAS

Las operaciones financieras deben guardar moneda.

Ejemplo:

`BOB`

`USD`

Nunca asumir la moneda a partir de la configuración actual cuando se consulta una transacción histórica.

---

# 42. IMPORTES

Los valores monetarios deben utilizar DECIMAL.

Ejemplo:

DECIMAL(15,2)

No utilizar FLOAT para dinero.

---

# 43. FECHAS

Guardar:

* created_at
* updated_at

y campos comerciales específicos cuando corresponda:

* fecha_reserva
* fecha_vencimiento
* fecha_venta
* fecha_pago
* confirmed_at
* cancelled_at

No utilizar solamente `created_at` para representar eventos comerciales.

---

# 44. ESTADOS

Los estados deben manejarse consistentemente.

No mezclar:

`activo`

`ACTIVO`

`Active`

`1`

para representar lo mismo.

Definir enums, constantes o value objects según la arquitectura.

---

# 45. DATOS CONFIGURABLES

No agregar columnas como:

`descuento_empresa_x`

`mora_cliente_y`

`reserva_10_dias`

Las reglas variables pertenecen a configuración.

---

# 46. CAMPOS ESPECÍFICOS

Si una empresa necesita información adicional poco común:

Primero evaluar:

1. ¿Es útil para todas las empresas?
2. ¿Debe formar parte del CORE?
3. ¿Puede almacenarse en metadata?
4. ¿Requiere una tabla adicional?

Evitar contaminar tablas centrales con personalizaciones aisladas.

---

# 47. INTEGRIDAD REFERENCIAL

Utilizar foreign keys siempre que sea razonable.

Ejemplo:

`manzanos.urbanizacion_id`

→ `urbanizaciones.id`

`lotes.manzano_id`

→ `manzanos.id`

`ventas.cliente_id`

→ `clientes.id`

No depender únicamente de validación desde PHP.

---

# 48. BORRADO DE PADRES

No usar CASCADE indiscriminadamente en información comercial.

Ejemplo:

Eliminar cliente

NO debe eliminar automáticamente:

* ventas
* pagos
* contratos

En información histórica, preferir:

RESTRICT

o

Soft Delete.

---

# 49. REPORTES

Los reportes deben construirse a partir de esta fuente transaccional.

No crear tablas duplicadas solamente para guardar:

* total ventas
* total pagos
* saldo

salvo que exista necesidad real de optimización.

---

# 50. DATOS CALCULADOS

Ejemplo:

Saldo venta:

## Precio final

Pagos confirmados

Puede calcularse.

Si posteriormente se decide almacenar valores agregados por rendimiento:

Debe existir una estrategia para mantenerlos sincronizados.

---

# 51. ESCALABILIDAD

La arquitectura debe soportar crecimiento en:

* Empresas
* Urbanizaciones
* Lotes
* Clientes
* Ventas
* Pagos
* Usuarios

Sin necesidad de rediseñar las relaciones principales.

---

# 52. AISLAMIENTO POR EMPRESA

Toda entidad empresarial debe validar:

`company_id`

Ejemplo prohibido:

Usuario de Empresa A

consulta:

Venta de Empresa B

aunque conozca el ID.

La autorización debe comprobar:

ROL

*

PERMISO

*

ALCANCE

*

COMPANY

---

# 53. API MÓVIL

Si posteriormente existe aplicación Android:

La API debe utilizar las mismas entidades.

No crear una segunda base de datos paralela para móvil.

Web

y

Android

deben operar sobre la misma fuente de verdad mediante API.

---

# 54. REGLA PARA AGENTES DE IA

Antes de modificar base de datos:

1. Leer `BASE_MASTER.md`.
2. Leer `BUSINESS_RULES.md`.
3. Leer `ROLES_PERMISSIONS.md`.
4. Leer este documento.
5. Leer `CLIENT_TEMPLATE.md`.
6. Identificar si el cambio pertenece al CORE.
7. Revisar relaciones existentes.
8. No modificar migraciones ya ejecutadas en producción.
9. Crear nuevas migraciones.
10. Crear o actualizar pruebas.
11. Revisar índices.
12. Revisar foreign keys.
13. Revisar aislamiento por empresa.

---

# 55. PRINCIPIO FINAL

La estructura base debe mantenerse:

COMPANY

*

USUARIOS Y AUTORIZACIÓN

*

URBANIZACIONES
→ MANZANOS
→ LOTES

*

CLIENTES
→ RESERVAS
→ VENTAS
→ PLANES
→ CUOTAS
→ PAGOS

*

AUDITORÍA

*

CONFIGURACIÓN

Las personalizaciones deben agregarse alrededor de esta estructura sin romper las relaciones centrales.
