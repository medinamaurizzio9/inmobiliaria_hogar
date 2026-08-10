# BUSINESS_RULES.md

# REGLAS DE NEGOCIO — SISTEMA TERRENOS

Este documento define las reglas operativas del sistema base Terrenos.

Estas reglas deben ser respetadas por todas las implementaciones derivadas del proyecto, salvo que un cliente tenga una excepción explícitamente registrada en su `CLIENT_TEMPLATE.md`.

---

# 1. PRINCIPIOS GENERALES

El sistema debe mantener consistencia entre:

* Urbanizaciones
* Manzanos
* Lotes
* Clientes
* Reservas
* Ventas
* Pagos
* Cuotas
* Usuarios
* Roles
* Reportes

Una operación no debe modificar información relacionada de forma inconsistente.

Toda acción importante debe quedar asociada al usuario responsable.

Las operaciones críticas deben quedar registradas en auditoría cuando exista el módulo correspondiente.

---

# 2. URBANIZACIONES

Una urbanización puede contener uno o varios manzanos.

Una urbanización no debe eliminarse físicamente si tiene:

* Manzanos
* Lotes
* Reservas
* Ventas
* Pagos relacionados

En esos casos debe utilizarse un estado como:

* Activa
* Inactiva
* Archivada

La eliminación física solo debería permitirse cuando no existan dependencias.

---

# 3. MANZANOS

Todo manzano debe pertenecer a una urbanización.

No debe existir un manzano sin urbanización.

El código del manzano debe ser único dentro de cada urbanización.

Ejemplo permitido:

Urbanización A → Manzano 01

Urbanización B → Manzano 01

Ejemplo no permitido:

Urbanización A → Manzano 01

Urbanización A → Manzano 01

---

# 4. LOTES

Todo lote debe pertenecer a un manzano.

El lote hereda indirectamente la urbanización mediante:

Urbanización
→ Manzano
→ Lote

El código del lote debe ser único dentro del manzano.

---

# 5. ESTADOS DE LOTES

Estados base:

* Disponible
* Reservado
* Vendido
* Bloqueado

Opcionalmente pueden existir:

* En negociación
* No disponible
* Observado

Las nuevas versiones no deben crear estados adicionales sin una razón comercial.

---

# 6. LOTE DISPONIBLE

Un lote Disponible puede:

* Visualizarse
* Cotizarse
* Reservarse
* Venderse directamente si el flujo lo permite

Un lote Disponible no debe tener una venta activa.

---

# 7. LOTE RESERVADO

Cuando se crea una reserva válida:

Disponible
→ Reservado

Un lote Reservado no puede ser reservado por otro cliente mientras la reserva permanezca activa.

Excepción:

Solo si el cliente tiene una regla comercial explícita que permita reservas múltiples.

---

# 8. LOTE VENDIDO

Cuando una venta se confirma:

Disponible
o
Reservado

→ Vendido

Un lote Vendido no puede volver a venderse.

Para revertirlo debe existir un proceso administrativo de:

* Anulación
* Resolución de contrato
* Reversión autorizada

Nunca debe cambiarse manualmente de Vendido a Disponible sin registrar el motivo.

---

# 9. LOTE BLOQUEADO

Un lote puede bloquearse por:

* Problema legal
* Decisión administrativa
* Mantenimiento de información
* Reserva institucional
* Observación documental
* Otro motivo autorizado

Mientras esté Bloqueado:

No puede reservarse.

No puede venderse.

---

# 10. CLIENTES

Cada cliente debe tener un identificador único.

Preferentemente:

CI / documento de identidad.

No deben crearse múltiples clientes con el mismo documento, salvo casos excepcionales correctamente justificados.

Antes de crear un cliente nuevo, el sistema debe comprobar si ya existe.

---

# 11. CLIENTE EXISTENTE

Si el cliente ya existe:

No crear otro registro.

Actualizar únicamente los datos que correspondan.

Toda nueva:

* Reserva
* Venta
* Pago
* Cuota

debe relacionarse con el mismo cliente existente.

---

# 12. RESERVAS

Una reserva debe contener como mínimo:

* Cliente
* Lote
* Fecha de reserva
* Fecha de vencimiento
* Asesor
* Estado

Opcionalmente:

* Monto
* Comprobante
* Observaciones
* Forma de pago

---

# 13. ESTADOS DE RESERVA

Estados base:

* Pendiente
* Activa
* Vencida
* Cancelada
* Convertida en venta

---

# 14. CREACIÓN DE RESERVA

Antes de crear una reserva el sistema debe validar:

1. Que el lote exista.
2. Que esté Disponible.
3. Que el cliente exista o sea creado correctamente.
4. Que el usuario tenga permiso.
5. Que no exista otra reserva activa.
6. Que se cumpla cualquier monto mínimo requerido.

Si todas las condiciones son correctas:

Reserva → Activa

Lote → Reservado

---

# 15. VENCIMIENTO DE RESERVA

Cuando una reserva vence:

Reserva
→ Vencida

Lote
→ Disponible

Siempre que no exista:

* Venta
* Extensión autorizada
* Otra condición comercial válida

La liberación puede realizarse automáticamente mediante tarea programada.

---

# 16. EXTENSIÓN DE RESERVA

Una reserva puede extenderse únicamente si:

* Sigue activa.
* El usuario tiene permiso.
* La empresa permite extensiones.
* Se registra la nueva fecha.
* Se registra quién autorizó.

Debe conservarse historial de la fecha anterior.

---

# 17. CANCELACIÓN DE RESERVA

Cuando una reserva se cancela:

Reserva → Cancelada

Lote → Disponible

Debe registrarse:

* Motivo
* Usuario
* Fecha

Si hubo dinero pagado, la cancelación no debe eliminar el pago.

Debe existir un proceso separado para devolución o aplicación del monto.

---

# 18. CONVERSIÓN DE RESERVA A VENTA

Cuando una reserva se convierte en venta:

Reserva
→ Convertida en venta

Lote
→ Vendido

No debe crearse una segunda reserva para completar la operación.

La venta debe mantener referencia a la reserva original.

---

# 19. VENTAS

Toda venta debe relacionar:

* Cliente
* Lote
* Usuario o asesor
* Precio
* Fecha
* Forma de pago
* Estado

---

# 20. PRECIO DEL LOTE

El sistema debe distinguir:

Precio listado

y

Precio final de venta

Ejemplo:

Precio listado:
100.000 Bs

Descuento:
5.000 Bs

Precio final:
95.000 Bs

El precio listado no debe modificarse automáticamente al aplicar un descuento.

---

# 21. DESCUENTOS

Los descuentos deben estar controlados por permisos.

Ejemplo:

Asesor:
hasta 2 %

Supervisor:
hasta 5 %

Administrador:
hasta 10 %

Super administrador:
sin límite configurado

Estos valores son configurables por empresa.

Toda excepción debe registrar quién la autorizó.

---

# 22. TIPOS DE VENTA

Tipos base:

* Contado
* Crédito
* Cuotas

Cada empresa podrá habilitar solo los que utilice.

---

# 23. VENTA AL CONTADO

Una venta al contado debe quedar completamente pagada antes de considerarse finalizada, salvo autorización administrativa.

Estado posible:

Pendiente de pago

→ Pagada

→ Finalizada

---

# 24. VENTA A CRÉDITO

Una venta a crédito debe generar:

* Monto inicial
* Saldo financiado
* Número de cuotas
* Fechas de vencimiento
* Cronograma de pagos

El saldo total de las cuotas debe coincidir con el monto financiado, considerando intereses cuando correspondan.

---

# 25. ESTADOS DE VENTA

Estados sugeridos:

* Borrador
* Pendiente
* Activa
* Pagada
* Cancelada
* Resuelta

Las empresas podrán adaptar nombres, pero no deben perderse los conceptos.

---

# 26. PAGOS

Cada pago debe almacenarse como una transacción independiente.

Nunca debe reemplazarse el valor acumulado de pagos sin conservar cada movimiento.

Cada pago debe registrar:

* Cliente
* Venta
* Monto
* Fecha
* Método
* Usuario
* Estado

---

# 27. ESTADOS DE PAGO

Estados sugeridos:

* Pendiente
* Confirmado
* Rechazado
* Anulado

---

# 28. CONFIRMACIÓN DE PAGO

Un pago solo debe afectar el saldo cuando esté Confirmado.

Ejemplo:

Pago pendiente de validación:

No reduce deuda.

Pago confirmado:

Reduce deuda.

---

# 29. ANULACIÓN DE PAGOS

Los pagos confirmados no deben eliminarse.

Si existe un error:

Pago
→ Anulado

Debe registrarse:

* Motivo
* Usuario que anuló
* Fecha
* Referencia al pago

El saldo debe recalcularse automáticamente.

---

# 30. COMPROBANTES

Cuando el pago requiera respaldo:

Debe permitirse adjuntar:

* Imagen
* PDF
* Número de transacción
* Referencia bancaria

El comprobante no reemplaza la validación del pago.

---

# 31. CUOTAS

Una cuota debe contener como mínimo:

* Número
* Fecha de vencimiento
* Monto
* Saldo
* Estado

---

# 32. ESTADOS DE CUOTA

Estados:

* Pendiente
* Parcial
* Pagada
* Vencida

---

# 33. PAGO PARCIAL

Si el cliente paga menos del monto de una cuota:

Cuota
→ Parcial

El saldo restante debe conservarse.

Ejemplo:

Cuota:
1.000 Bs

Pago:
600 Bs

Saldo:
400 Bs

Estado:
Parcial

---

# 34. CUOTA PAGADA

Cuando los pagos aplicados alcancen el total:

Cuota
→ Pagada

Saldo:
0

---

# 35. CUOTA VENCIDA

Si:

Fecha actual > fecha de vencimiento

y

Saldo > 0

Entonces:

Cuota
→ Vencida

---

# 36. PAGOS ANTICIPADOS

El sistema puede permitir adelantar pagos.

La empresa debe definir cómo se aplicarán:

Opción A:
A cuotas siguientes.

Opción B:
A capital.

Opción C:
A la última cuota.

La regla seleccionada debe estar definida por cliente.

---

# 37. SALDOS

El saldo de una venta debe calcularse a partir de:

## Monto total

# Pagos confirmados

Saldo pendiente

El saldo nunca debe ser escrito manualmente sin justificación.

---

# 38. MONEDA

Debe definirse la moneda principal.

Para Bolivia normalmente:

BOB / Bs.

Si una empresa opera con varias monedas:

Cada transacción debe almacenar su moneda.

No asumir que todas las operaciones utilizan la misma.

---

# 39. ASESORES

Cada reserva y venta debe poder relacionarse con un asesor.

El sistema debe permitir identificar:

* Clientes atendidos
* Reservas realizadas
* Ventas
* Cobros relacionados
* Comisiones

---

# 40. COMISIONES

Las comisiones deben calcularse sobre un evento claramente definido.

Ejemplos:

* Reserva confirmada
* Venta firmada
* Primer pago
* Pago completo

Nunca deben generarse comisiones simplemente por crear un registro.

---

# 41. ANULACIÓN DE VENTA

Una venta no debe eliminarse físicamente.

Si debe revertirse:

Venta
→ Cancelada / Resuelta

Debe registrarse:

* Motivo
* Usuario
* Fecha
* Autorización

El lote podrá volver a Disponible únicamente si las reglas comerciales y legales lo permiten.

---

# 42. CAMBIO DE PROPIETARIO

Si una venta ya formalizada cambia de comprador:

No sobrescribir simplemente el cliente.

Debe utilizarse un proceso de:

* Cesión
* Transferencia
* Resolución y nueva venta

según corresponda.

---

# 43. USUARIOS

Cada persona debe utilizar su propia cuenta.

No deben compartirse usuarios.

Las operaciones críticas deben registrar:

user_id

fecha

acción

---

# 44. ROLES BASE

## Super Administrador

Acceso global.

## Administrador

Gestión administrativa.

## Supervisor

Control de operaciones y asesores.

## Asesor

Operación comercial restringida.

---

# 45. AUTORIZACIÓN

No basta con ocultar botones en la interfaz.

Cada acción debe validarse también en backend.

Ejemplo:

Un asesor sin permiso de eliminar venta no debe poder hacerlo mediante URL o petición directa.

---

# 46. ELIMINACIÓN DE INFORMACIÓN

Preferir:

Soft Deletes

o

Estados

para información crítica.

Especialmente:

* Clientes
* Reservas
* Ventas
* Pagos
* Cuotas

---

# 47. AUDITORÍA

Registrar al menos las operaciones críticas:

* Creación de ventas
* Cambio de precio
* Descuento
* Reserva
* Cancelación
* Pago
* Anulación de pago
* Cambio de estado de lote
* Cambios de permisos
* Eliminaciones
* Modificaciones financieras

---

# 48. REPORTES

Los reportes deben obtener información desde las transacciones reales.

No mantener números manuales separados si pueden calcularse.

Ejemplo:

Ventas del mes

debe calcularse desde ventas confirmadas.

No desde un contador editable.

---

# 49. CONSISTENCIA DE DATOS

Ejemplos de inconsistencias prohibidas:

Lote = Disponible
pero existe venta activa.

Lote = Vendido
sin venta relacionada.

Reserva = Activa
pero lote = Disponible.

Cuota = Pagada
pero tiene saldo.

Venta = Pagada
pero saldo > 0.

Estas condiciones deben detectarse mediante validaciones y pruebas.

---

# 50. TRANSACCIONES DE BASE DE DATOS

Las operaciones que modifiquen varias entidades deben ejecutarse dentro de una transacción.

Ejemplo:

Crear venta:

1. Crear venta.
2. Cambiar lote a Vendido.
3. Convertir reserva.
4. Generar cuotas.
5. Registrar pago inicial.

Si cualquier paso falla:

Toda la operación debe revertirse.

---

# 51. CONCURRENCIA

El sistema debe evitar que dos usuarios vendan o reserven simultáneamente el mismo lote.

Antes de confirmar:

Debe volver a comprobarse el estado real del lote.

En operaciones críticas se recomienda bloqueo transaccional cuando corresponda.

---

# 52. REGLAS CONFIGURABLES

No deberían codificarse directamente si pueden variar entre clientes:

* Duración de reservas
* Descuento máximo
* Cantidad máxima de cuotas
* Interés
* Mora
* Moneda
* Comisión
* Métodos de pago
* Documentos requeridos

Estas reglas deberían almacenarse en configuración cuando sea técnicamente conveniente.

---

# 53. REGLAS CORE VS CLIENTE

Si una nueva empresa solicita:

“Las reservas duran 10 días.”

Eso es:

Configuración del cliente.

No debe modificarse el CORE para cambiar:

5 días

por

10 días.

---

# 54. EXCEPCIONES

Toda excepción comercial debe registrarse en:

`CLIENT_TEMPLATE.md`

o en un archivo específico de configuración del cliente.

Nunca asumir que una excepción de una empresa aplica a todas las demás.

---

# 55. REGLA PARA IA

Antes de modificar lógica comercial:

1. Leer este documento.
2. Leer `CLIENT_TEMPLATE.md`.
3. Identificar si la regla es CORE o específica.
4. Revisar modelos, servicios y pruebas relacionados.
5. No duplicar reglas en múltiples controladores.
6. Centralizar lógica comercial en servicios o acciones cuando corresponda.
7. Crear o actualizar pruebas.
8. Actualizar documentación si cambia una regla.

---

# 56. PRUEBAS MÍNIMAS

El sistema debe probar al menos:

* No reservar lote vendido.
* No reservar lote bloqueado.
* No vender lote vendido.
* Cancelar reserva libera lote.
* Reserva vencida libera lote.
* Venta convierte lote en vendido.
* Pago confirmado reduce saldo.
* Pago pendiente no reduce saldo.
* Pago anulado devuelve saldo.
* Pago parcial mantiene saldo.
* Cuota completa queda pagada.
* Usuario sin permiso no accede.
* Dos operaciones simultáneas no pueden vender el mismo lote.

---

# 57. PRINCIPIO FINAL

El sistema debe priorizar:

Integridad de información

*

trazabilidad

*

reglas consistentes

*

configuración por empresa

antes que soluciones rápidas basadas en modificaciones directas de código.

El CORE debe representar las reglas comunes del negocio inmobiliario.

Las diferencias comerciales de cada empresa deben mantenerse configurables y separadas.
