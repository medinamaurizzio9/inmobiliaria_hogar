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
- [x] monto fijo;
- [x] configurable por operación.

Porcentaje mínimo:

NO aplica: sin porcentaje obligatorio (ver F-4). Administrador o gerente define el monto.

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

- Habilitado: `false`
- Tipo:
- Tasa predeterminada:

NO calcular intereses durante la vida del crédito (ver F-2).

El precio final pactado ya incorpora cualquier condición financiera o comercial.

---

## 11. Descuentos

Permitir descuentos:

`true`

### Vendedor

Máximo:

`0%` — el vendedor NO modifica precio ni aplica descuentos (ver F-3).

### Supervisor

Máximo:

### Administrador

Máximo:

Descuentos superiores:

- [ ] prohibidos;
- [x] requieren autorización especial.

Gerente y administrador pueden autorizar descuentos (ver F-3).

Toda autorización debe quedar registrada con usuario autorizador y fecha.

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

`false`

### Transferencia

`false`

### Depósito

`false`

NO exigir imagen/PDF del comprobante actualmente (ver F-17).

El cliente registra monto, fecha, banco y número/referencia de transacción.

---

## 19. Confirmación

Confirmación automática:

- Efectivo: `true` (pagos en oficina se confirman inmediatamente, ver F-22)
- QR: `false`
- Transferencia: `false`
- Depósito:

Roles autorizados para confirmar:

- gerente
- cajero
- admin

Pagos en oficina: gerente y cajero, se confirman inmediatamente (ver F-22).

Los pagos QR/transferencia quedan `PENDIENTE_VERIFICACION` hasta confirmación de administración (ver F-18 y F-21).

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
```
---

# REGLAS FINANCIERAS — HOGAR INMOBILIARIA

> Sección oficial de reglas financieras del cliente.
> Prevalece sobre los valores genéricos de las secciones anteriores cuando exista conflicto.

## F-1. Modalidades de venta

- `CONTADO`: pago total inmediato.
- `SEMICONTADO`: cuota inicial + mensualidades. Cantidad máxima configurable desde administración.
- `CREDITO`: cuota inicial + mensualidades. Cantidad máxima configurable desde administración.

## F-2. Sin intereses durante la vida del crédito

No calcular intereses durante la vida del crédito.

El precio final pactado ya incorpora cualquier condición financiera o comercial.

## F-3. Precio final

- El sistema muestra precios comerciales.
- El vendedor no puede cambiar el precio.
- Gerente y administrador pueden autorizar descuentos.
- Guardar: precio lista, descuento, precio final pactado, usuario autorizador y fecha.

## F-4. Cuota inicial

- No usar porcentaje obligatorio.
- Administrador o gerente define el monto de la inicial.
- Saldo financiado = precio final pactado - cuota inicial.

## F-5. Mensualidad

mensualidad = saldo financiado / plazo

Si existe residuo por redondeo, la última cuota absorbe la diferencia.

## F-6. Primera fecha de vencimiento

La define administración al cerrar la venta.

## F-7. Estados de cuota

- PROXIMA
- PENDIENTE
- PARCIAL
- VENCIDA
- PAGADA
- ANULADA

## F-8. Pagos anticipados

Permitidos.

## F-9. Pago parcial

Permitido.

Mantener saldo pendiente de la misma cuota.

## F-10. Pago superior a una cuota

Si un pago supera una cuota, aplicar automáticamente el excedente a las siguientes
cuotas del mismo terreno hasta consumir el monto.

## F-11. Amortización extraordinaria

- Aplicar directamente al saldo.
- Mantener valor de mensualidad.
- Reducir cantidad de cuotas restantes.
- No calcular intereses.

## F-12. Mora / recargos

No aplicar por defecto.

Debe quedar configurable desde administración para futuro uso.

## F-13. Obligación financiera por terreno

Cada terreno es una obligación financiera independiente.

Un cliente puede tener varios terrenos, cada uno con:

- venta;
- plan;
- cuotas;
- pagos;
- saldo;
- documentos;
- estado de cuenta.

Todo independiente.

## F-14. No combinar deudas

No combinar deudas de terrenos diferentes automáticamente.

## F-15. QR

- Un único QR institucional.
- Imagen fija.
- Configurable desde administración.
- Futuro soporte para API bancaria: fuera del alcance actual.

## F-16. Transferencia

- Una sola cuenta bancaria activa.
- Configurable desde administración.

## F-17. Pago QR / transferencia

El cliente registra:

- monto;
- fecha;
- banco;
- número/referencia de transacción.

No exigir imagen/PDF del comprobante actualmente.

## F-18. Estados de pago

- PENDIENTE_VERIFICACION
- CONFIRMADO
- RECHAZADO
- ANULADO
- DEVOLUCION

## F-19. Pago pendiente de verificación

Un pago pendiente de verificación NO reduce saldo.

## F-20. Pago confirmado

Solo un pago CONFIRMADO afecta cuotas y saldo.

## F-21. Referencia incorrecta

Si la referencia es incorrecta, administración puede RECHAZAR indicando motivo.

## F-22. Pagos en oficina

Roles autorizados:

- gerente;
- cajero.

Se confirman inmediatamente.

## F-23. Recibo PDF

Todo pago confirmado genera recibo PDF numerado.

## F-24. Contenido del recibo

- cliente;
- terreno;
- monto;
- método;
- fecha;
- saldo anterior;
- pago;
- saldo restante;
- número de recibo.

## F-25. Alertas al cliente

Mostrar al iniciar sesión, 3 días antes del vencimiento.

Mostrar también cuotas vencidas.

## F-26. Portal cliente — por terreno

Debe mostrar:

- urbanización;
- manzano;
- lote;
- precio pactado;
- modalidad;
- total pagado;
- saldo;
- próxima cuota;
- fecha de vencimiento;
- estado de cuenta.

## F-27. Descargas del cliente

El cliente puede descargar:

- contrato;
- recibos;
- estado de cuenta;
- documentos asociados al terreno.

## F-28. Estado de cuenta

Incluir:

- fecha;
- concepto;
- número de cuota;
- importe programado;
- importe pagado;
- método;
- número de recibo;
- estado;
- saldo posterior.

## F-29. Reportes gerenciales

Filtros por:

- cliente;
- urbanización;
- terreno;
- vendedor;
- rango de fechas;
- modalidad de venta.

Métricas:

- precio pactado;
- inicial;
- total cobrado;
- saldo;
- cuotas pagadas;
- cuotas pendientes;
- cuotas vencidas;
- devoluciones;
- saldo retenido por la empresa.

## F-30. Reporte de cartera

- total vendido financiado;
- total cobrado;
- saldo por cobrar;
- cartera al día;
- cartera vencida.

## F-31. Caja diaria

Registrar cobros por:

- efectivo;
- QR;
- transferencia;
- usuario/cajero;
- fecha.

## F-32. Modificación de cuotas

Solo administrador.

- Motivo obligatorio.
- Auditoría obligatoria.
- No editar silenciosamente cuotas pagadas.

## F-33. Reestructuración

Permitida administrativamente.

No editar cuotas históricas.

Registrar:

- saldo antes;
- cuotas pendientes anteriores;
- nuevo plazo;
- nuevas cuotas;
- fecha;
- administrador;
- observaciones.

## F-34. Anulación / rescisión

Marcar como devolución.

Mantener historial de pagos.

Registrar:

- monto pagado;
- monto devuelto;
- monto retenido por la empresa;
- motivo;
- observaciones;
- responsable.

## F-35. Monto retenido en reportes

El monto retenido por la empresa debe aparecer en reportes financieros.

## F-36. Vendedores / asesores

NO pueden ver deuda ni pagos.

Solo:

- administración;
- caja;
- gerencia.

## F-37. Acceso cliente

Al cerrar una venta, crear cuenta del cliente.

- Usuario principal: correo electrónico.
- Generar contraseña temporal segura aleatoria.
- Obligar cambio de contraseña en el primer ingreso.
- No usar el CI directamente como contraseña.

