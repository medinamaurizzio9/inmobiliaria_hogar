# Protección comercial de clientes

## Propósito

El cliente representa a una persona, no a una operación ni a una urbanización. Un CI normalizado identifica una única fila en `clientes`; esa persona puede mantener varias reservas, ventas, lotes y créditos independientes, incluso en urbanizaciones distintas. La cuenta del portal continúa vinculada a `cliente_id`, por lo que sigue siendo única.

## Identidad y coincidencias

- `documento_normalizado` elimina espacios y signos, convierte a mayúsculas y conserva letras y números para no destruir complementos legítimos. Es la identidad exacta y cuenta con índice único.
- `telefono_normalizado` conserva dígitos y elimina el prefijo boliviano `591` solo cuando el resultado corresponde al formato local esperado.
- `email_normalizado` elimina espacios externos y usa minúsculas.
- Solo el CI exacto bloquea la creación. Teléfono o correo generan una advertencia que requiere confirmación explícita, pero nunca fusionan personas.
- Los registros históricos con CI normalizados repetidos quedan deliberadamente con `documento_normalizado = NULL` durante la migración. No se eliminan ni fusionan. Deben revisarse antes de normalizarlos individualmente.

La validación anticipada del formulario usa `clientes.matches`, pero la decisión definitiva siempre se repite en backend. `ClientRegistrationService` combina transacción, bloqueo de lectura y el constraint único para cubrir carreras concurrentes.

## Protección

Al registrar una persona se guardan el usuario original (`created_by`), el asesor responsable actual (`responsible_user_id`), `protected_at` y `protection_expires_at`. El plazo se lee de `urbanizacion_commercial_settings.proteccion_cliente_dias`; su valor inicial es 30 y administrador o gerente puede cambiarlo entre 1 y 365 días desde Configuración comercial.

El estado es calculado:

- `protected`: el vencimiento todavía es futuro.
- `expired`: el plazo terminó o no existe.
- `has_operations`: existen reservas o ventas reales.

Una reserva o venta nueva no renueva el plazo ni reasigna automáticamente al responsable. Si se realiza durante una protección vigente, la operación continúa y se registra un evento comercial.

## Auditoría y privacidad

`client_commercial_events` registra intento de CI duplicado, coincidencias por teléfono/correo, operación sobre cliente protegido y deja preparado el tipo de reasignación. Guarda IDs de cliente, urbanización, actor, asesor original e involucrado, además de metadatos mínimos de la operación.

Administrador y gerente ven el CI completo y el reporte general. Supervisor solo ve eventos de su equipo. Un vendedor recibe CI enmascarado en coincidencias y no ve precios, cuotas ni saldos en la ficha comercial; sí puede identificar responsable, vigencia y propiedades necesarias para reutilizar la persona en una nueva operación.

## Reporte

`Reportes > Control comercial de clientes` presenta indicadores, pendientes de revisión y eventos paginados de 50 en 50. Permite filtrar por fechas, persona/CI, asesor, supervisor/equipo, evento y vigencia. El alcance del supervisor reutiliza `ReservationVisibilityService`.

## Reutilización e importador futuro

La lógica no depende del controlador y puede usarse desde futuros importadores:

- `ClientMatchingService::findExactClient()`
- `ClientMatchingService::findPotentialMatches()`
- `ClientCommercialProtectionService::getCommercialProtection()`
- `ClientCommercialEventService::registerCommercialEvent()`
- `ClientRegistrationService::register()`

Para otra inmobiliaria se deben ejecutar las migraciones incrementales, asignar el permiso de control comercial a los roles equivalentes y configurar el plazo por urbanización. Un importador futuro debe reutilizar estos servicios y nunca insertar clientes directamente sin resolver coincidencias.

## Despliegue seguro

La instalación actual de Hogar Inmobiliaria fue verificada con la tabla `clientes` vacía, por lo que no requiere auditoría ni reconciliación histórica. La migración se ejecutó localmente y creó el esquema de identidad única desde el primer cliente. En futuras reutilizaciones del CORE que sí contengan datos, la migración continúa siendo conservadora: no modifica el CI visible, no elimina registros y no fusiona personas automáticamente.
