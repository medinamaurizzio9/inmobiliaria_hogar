# Importación masiva de clientes

## Alcance

Importa exclusivamente identidades de personas en `clientes`. No crea ventas, reservas, lotes, créditos, cuotas, pagos, saldos ni movimientos de caja.

## Formatos y límites

- CSV con delimitador coma o punto y coma y XLSX.
- Máximo 5 MB y 5.000 filas por archivo.
- El archivo se analiza completamente antes de confirmar y no se conserva en almacenamiento público ni permanente.
- La vista previa y su detalle temporal caducan después de 24 horas si no se confirman.

## Columnas

| Columna de plantilla | Obligatoria | Destino |
|---|---:|---|
| `nombre` | Sí | `clientes.nombre` |
| `ci` | Sí | `clientes.documento` |
| `telefono` | No | `clientes.telefono` |
| `email` | No | `clientes.email` |
| `direccion` | No | `clientes.direccion` |
| `asesor_email` | No | Responsable comercial |

Se aceptan alias explícitos: nombre/nombres/nombre completo; CI/C.I./documento/carnet; teléfono/celular/WhatsApp; email/e-mail/correo/correo electrónico; dirección/domicilio; asesor/asesor email/email asesor/correo asesor. Se normalizan BOM, acentos, espacios y mayúsculas.

## Identidad y coincidencias

`ClientMatchingService` normaliza CI, teléfono y email. El CI global identifica a una persona:

- CI existente: `YA EXISTE`, se omite sin sobrescribir datos.
- CI repetido compatible dentro del archivo: se usa una fila canónica y se omiten las repeticiones.
- CI repetido con datos contradictorios: `ERROR`; no se importa esa identidad.
- Teléfono o email coincidente con CI diferente: `COINCIDENCIA`; requiere confirmación explícita por fila.

## Asesor y protección

El asesor solo se resuelve por email exacto de un usuario con rol `vendedor`. No se crean usuarios desde la planilla. Si está vacío, el usuario importador queda como responsable, igual que en el alta manual. `created_by` siempre identifica al importador.

Todo cliente nuevo pasa por `ClientRegistrationService`: recibe CI/teléfono/email normalizados, protección comercial configurada, control de concurrencia y unicidad global.

## Flujo y trazabilidad

1. Descargar plantilla XLSX.
2. Subir CSV/XLSX.
3. Revisar vista previa: LISTO, YA EXISTE, COINCIDENCIA o ERROR.
4. Aceptar explícitamente coincidencias seguras y confirmar.

`client_import_batches` conserva importador, fecha, nombre original, SHA-256 y resumen. `client_import_rows` conserva el resultado por fila. Repetir un archivo no duplica personas por la restricción global de CI. El reporte final se descarga en CSV.

## Seguridad

El permiso `importar clientes` se concede a super administrador, administrador y gerente. Supervisor y vendedor no están habilitados. El backend aplica el permiso en todas las rutas.

## Futuro

La futura importación de cartera deberá relacionar ventas/créditos con clientes globales existentes. No debe duplicar identidades por urbanización ni mezclarse con este flujo.
