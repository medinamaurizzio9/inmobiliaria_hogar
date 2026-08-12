# Checklist de aceptación — Hogar Inmobiliaria

Ejecutar en staging con datos de prueba y un usuario por rol. Registrar fecha,
responsable y evidencia; no usar datos personales reales.

- [ ] Login por roles: administrador, gerente, cajero, supervisor, vendedor y cliente ven únicamente su menú y reciben 403 al abrir una URL financiera no permitida.
- [ ] Venta contado: registrar venta, confirmar pago total, comprobar lote vendido, ausencia de cuotas y recibo.
- [ ] Venta semicontado: definir inicial, plazo y primer vencimiento; comprobar suma exacta y residuo en última cuota.
- [ ] Venta crédito: definir inicial y plan; comprobar deuda independiente del resto de terrenos del cliente.
- [ ] Descuento: vendedor no modifica precio; gerente/administrador dejan autorizador y fecha.
- [ ] Cobro: buscar cliente/terreno, previsualizar, confirmar efectivo y descargar recibo sin navegación adicional.
- [ ] QR: comprobar QR institucional activo; registrar pendiente, confirmar desde Cobranza y verificar aplicaciones.
- [ ] Transferencia: comprobar cuenta activa, banco y referencia obligatorios; confirmar y verificar recibo.
- [ ] Recibo: solo confirmado, número estable, cliente/terreno/monto/saldo correctos; acceso ajeno denegado.
- [ ] Portal cliente: primer acceso obliga cambio de contraseña; muestra únicamente terrenos, cuotas, pagos y documentos propios.
- [ ] Reestructuración: solo administrador; cuotas pagadas intactas, anteriores anuladas y snapshots antes/después.
- [ ] Rescisión: solo administrador; pagos/aplicaciones preservados, devolución/retención separadas y lote sincronizado.
- [ ] Reportes: bruto, devolución, neto, retenido y cartera coinciden con operaciones; filtros y CSV respetan urbanización.
- [ ] Responsive: validar en 360 px, tablet y desktop portal, cobranza y reportes; botones táctiles y tablas desplazables.
- [ ] Backup: ejecutar `php artisan impacto:backup`, comprobar archivo en `storage/app/backups` y realizar una restauración controlada fuera de producción.
- [ ] Producción: completar el checklist de `docs/09-DEPLOYMENT.md`, probar cachés, migraciones, HTTPS, storage y smoke test.

Resultado UAT:

- Fecha:
- Responsable:
- Versión/commit:
- Incidencias abiertas:
- Decisión: [ ] Aprobado [ ] Requiere correcciones
