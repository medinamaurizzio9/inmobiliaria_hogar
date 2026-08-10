# BUSINESS_OVERRIDES.md

# Reglas de Negocio Específicas del Cliente

Este documento registra únicamente las reglas comerciales del cliente
que difieren del comportamiento estándar definido por el CORE.

Documentación CORE relacionada:

- `/docs/01-BASE_MASTER.md`
- `/docs/03-BUSINESS_RULES.md`
- `/docs/04-ROLES_PERMISSIONS.md`
- `/docs/05-DATABASE_BASE.md`

---

## 1. Principio fundamental

El CORE contiene las reglas generales del sistema.

Este archivo contiene únicamente:

- excepciones;
- configuraciones especiales;
- límites particulares;
- políticas comerciales;
- comportamientos específicos del cliente.

NO copiar aquí todas las reglas del CORE.

Si una regla no aparece en este documento:

> Se aplica la regla estándar del CORE.

---

## 2. Clasificación de cambios

Cada diferencia solicitada por el cliente debe clasificarse como:

### CONFIG

Puede resolverse mediante configuración.

Ejemplos:

- duración de reserva;
- porcentaje de cuota inicial;
- número máximo de cuotas;
- monto mínimo;
- días de tolerancia.

### FEATURE_FLAG

Funcionalidad opcional que puede activarse o desactivarse.

Ejemplos:

- comisiones;
- reservas;
- portal cliente;
- mora;
- aprobación de descuentos.

### OVERRIDE

Regla realmente distinta al CORE.

Debe utilizarse solamente cuando CONFIG o FEATURE_FLAG no sean suficientes.

### CORE_CANDIDATE

Cambio inicialmente solicitado por un cliente que puede resultar útil
para todos los clientes.

Debe evaluarse antes de modificar el CORE.

---

## 3. Registro de overrides

Cada excepción debe documentarse utilizando:

ID:

`OVR-0001`

Estado:

- proposed
- approved
- implemented
- deprecated

Tipo:

- CONFIG
- FEATURE_FLAG
- OVERRIDE
- CORE_CANDIDATE

Módulo:

Regla CORE relacionada:

Descripción:

Motivo:

Comportamiento estándar:

Comportamiento solicitado:

Impacto:

Aprobado por:

Fecha:

Implementado:

Pruebas:

---

# RESERVAS

## 4. Configuración de reservas

Aplicar CORE salvo las siguientes configuraciones.

### Duración

- Duración predeterminada:
- Unidad: `hours`
- Duración máxima:
- Permitir extensión:

### Extensiones

- Máximo de extensiones:
- Requiere autorización:
- Roles autorizados:

### Pago de reserva

- Pago obligatorio:
- Monto fijo:
- Porcentaje:
- Monto mínimo:

### Vencimiento

Al vencer una reserva:

- [ ] liberar automáticamente lote;
- [ ] requerir confirmación;
- [ ] enviar alerta;
- [ ] permitir período de gracia.

Período de gracia:

---

## 5. Reserva simultánea

Permitir más de una reserva activa sobre el mismo lote:

`false`

RECOMENDACIÓN:

Mantener `false`.

La disponibilidad del lote debe validarse en servidor y dentro de una
transacción.

Nunca confiar únicamente en la interfaz.

---

## 6. Cancelación

Permitir cancelación:

`true`

Requiere motivo:

`true`

Requiere autorización:

`false`

Monto de reserva:

- [ ] reembolsable;
- [ ] parcialmente reembolsable;
- [ ] no reembolsable;
- [ ] depende del caso.

Observaciones:

---

# VENTAS

## 7. Tipos de venta

Habilitar:

- [ ] Contado
- [ ] Crédito

---

## 8. Venta al contado

Descuento máximo sin autorización:

`0%`

Descuento máximo con autorización:

Monto mínimo permitido:

Permitir pago parcial antes de consolidar:

---

## 9. Venta a crédito

### Cuota inicial

Obligatoria:

`true`

Tipo:

- [ ] porcentaje;
- [ ] monto fijo;
- [ ] configurable por operación.

Porcentaje mínimo:

Monto mínimo:

Permitir inicial cero:

`false`

---

## 10. Financiamiento

Número mínimo de cuotas:

Número máximo de cuotas:

Frecuencias permitidas:

- [ ] semanal;
- [ ] quincenal;
- [ ] mensual;
- [ ] personalizada.

Frecuencia predeterminada:

`monthly`

Interés:

- Habilitado:
- Tipo:
- Tasa predeterminada:

---

## 11. Descuentos

Permitir descuentos:

`true`

### Vendedor

Máximo:

### Supervisor

Máximo:

### Administrador

Máximo:

Descuentos superiores:

- [ ] prohibidos;
- [ ] requieren autorización especial.

Toda autorización debe quedar registrada.

---

## 12. Cambio de precio

Una venta consolidada debe conservar el precio pactado.

Cambiar posteriormente el precio del lote NO debe alterar ventas históricas.

Permitir renegociación:

`false`

Si se habilita, debe existir:

- autorización;
- motivo;
- auditoría;
- valores anteriores;
- valores nuevos.

---

# CUOTAS

## 13. Generación

Generación automática:

`true`

Frecuencia predeterminada:

`monthly`

Día de vencimiento:

Permitir primera cuota personalizada:

`true`

---

## 14. Mora

Mora habilitada:

`false`

Si se habilita:

Tipo:

- [ ] monto fijo;
- [ ] porcentaje;
- [ ] interés diario;
- [ ] interés mensual.

Valor:

Días de gracia:

Máximo:

---

## 15. Pagos anticipados

Permitir:

`true`

Aplicación:

- [ ] próximas cuotas;
- [ ] capital;
- [ ] saldo total;
- [ ] selección manual.

Recalcular plan:

`false`

---

## 16. Pagos parciales

Permitir:

`true`

Monto mínimo:

Mantener cuota pendiente hasta completar:

`true`

---

# PAGOS

## 17. Métodos

Habilitados:

- [ ] Efectivo
- [ ] QR
- [ ] Transferencia
- [ ] Depósito
- [ ] Tarjeta
- [ ] Otro

---

## 18. Comprobantes

Requerir comprobante para:

### Efectivo

`false`

### QR

`true`

### Transferencia

`true`

### Depósito

`true`

---

## 19. Confirmación

Confirmación automática:

- Efectivo:
- QR:
- Transferencia:
- Depósito:

Roles autorizados para confirmar:

- cashier
- admin

---

## 20. Anulación

Permitir anular pagos:

`true`

Requiere:

- motivo;
- usuario;
- fecha;
- auditoría.

NO eliminar físicamente pagos confirmados.

La anulación debe conservar el historial financiero.

---

# CLIENTES

## 21. Documento de identidad

Obligatorio:

`true`

Tipos aceptados:

- CI
- NIT
- Pasaporte
- Otro

Permitir duplicados:

`false`

---

## 22. Datos obligatorios

Marcar:

- [ ] Nombre
- [ ] Apellido
- [ ] CI
- [ ] Teléfono
- [ ] WhatsApp
- [ ] Email
- [ ] Dirección
- [ ] Fecha nacimiento
- [ ] Estado civil
- [ ] Ocupación

No convertir campos opcionales en obligatorios globalmente por una necesidad
exclusiva de este cliente.

---

# LOTES

## 23. Estados

Utilizar estados CORE.

No crear estados específicos sin analizar primero el impacto.

Configuraciones adicionales:

Permitir bloqueo manual:

`true`

Requiere motivo:

`true`

Permitir desbloqueo:

`true`

---

## 24. Cambio de precio

Roles autorizados:

- admin

Requiere auditoría:

`true`

Mantener historial:

`true`

---

## 25. Reserva por asesor

Un asesor puede reservar:

- [ ] cualquier lote;
- [ ] únicamente urbanizaciones asignadas;
- [ ] únicamente lotes asignados.

Máximo de reservas activas por asesor:

---

# COMISIONES

## 26. Habilitación

Comisiones habilitadas:

`false`

Si `false`, utilizar `MODULES.md` para desactivar el módulo.

---

## 27. Cálculo

Base:

- [ ] precio venta;
- [ ] monto cobrado;
- [ ] utilidad;
- [ ] monto fijo.

Porcentaje vendedor:

Porcentaje supervisor:

Otro:

---

## 28. Devengamiento

La comisión se genera:

- [ ] al reservar;
- [ ] al vender;
- [ ] al recibir inicial;
- [ ] al alcanzar porcentaje cobrado;
- [ ] al pagar completamente.

RECOMENDACIÓN:

No generar comisión definitiva únicamente por crear una reserva.

---

## 29. Reversión

Si una venta se anula:

- [ ] revertir comisión;
- [ ] mantener comisión;
- [ ] revisión manual.

Toda reversión debe quedar auditada.

---

# CANCELACIONES

## 30. Venta

Permitir cancelación:

`true`

Roles autorizados:

Motivo obligatorio:

`true`

---

## 31. Dinero recibido

Cuando una venta se cancela:

- [ ] devolución completa;
- [ ] devolución parcial;
- [ ] retención;
- [ ] saldo a favor;
- [ ] decisión manual.

No eliminar pagos históricos.

---

# TRANSFERENCIAS

## 32. Cambio de titular

Habilitado:

`false`

Si se habilita:

Requiere autorización:

Costo administrativo:

Documentación requerida:

Mantener historial de titulares:

`true`

---

# NOTIFICACIONES

## 33. Canales

- [ ] Sistema
- [ ] Email
- [ ] WhatsApp
- [ ] SMS
- [ ] Push

---

## 34. Eventos

Notificar:

- [ ] Reserva creada
- [ ] Reserva próxima a vencer
- [ ] Reserva vencida
- [ ] Venta creada
- [ ] Pago recibido
- [ ] Cuota próxima a vencer
- [ ] Cuota vencida
- [ ] Venta cancelada

---

# DOCUMENTOS

## 35. Contratos

Generación automática:

`false`

Plantilla:

Firma digital:

`false`

Numeración automática:

`true`

---

## 36. Recibos

Generación automática:

`true`

Numeración:

- automática;
- correlativa;
- única.

Permitir reimpresión:

`true`

Toda reimpresión debe conservar el mismo número de recibo.

---

# AUDITORÍA

## 37. Operaciones sensibles

Auditar obligatoriamente:

- cambio de precio;
- descuentos;
- reserva;
- extensión de reserva;
- cancelación;
- venta;
- modificación de venta;
- pago;
- anulación de pago;
- cambio de cuota;
- cambio de titular;
- cambio de permisos;
- impersonación;
- modificaciones de configuración sensible.

---

# APROBACIONES

## 38. Flujo

Cuando una operación supera los límites del usuario:

NO rechazar automáticamente si existe un flujo de aprobación habilitado.

Estados sugeridos:

`pending`

`approved`

`rejected`

`expired`

Registrar:

- solicitante;
- aprobador;
- fecha;
- motivo;
- valores solicitados;
- valores aprobados.

---

# REGLAS TÉCNICAS

## 39. Prohibición de condiciones por cliente

NO implementar:

```php
if ($client->name === 'Empresa X') {
    // comportamiento especial
}