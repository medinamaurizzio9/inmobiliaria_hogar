# AGENTS.md

# TERRENOS — INSTRUCCIONES PARA AGENTES DE IA

## 1. PROPÓSITO

Este repositorio pertenece a **Terrenos**, una plataforma inmobiliaria reutilizable para administrar y comercializar:

- Urbanizaciones
- Manzanos
- Lotes
- Clientes
- Reservas
- Ventas
- Pagos
- Cuotas
- Usuarios
- Roles y permisos
- Reportes

Principio:

```text
CORE
+
CONFIGURACIÓN DEL CLIENTE
+
BRANDING
+
REGLAS PARTICULARES
+
MÓDULOS
=
IMPLEMENTACIÓN DEL CLIENTE
```

No reconstruyas funcionalidades existentes sin una razón técnica comprobada.

---

## 2. REGLA DE CONTEXTO

**No analices todo el repositorio automáticamente.**

Antes de trabajar:

1. Lee este archivo.
2. Identifica si la tarea afecta al CORE o a un cliente.
3. Lee el archivo de estado correspondiente.
4. Lee únicamente la documentación relacionada.
5. Inspecciona únicamente el código necesario.
6. Amplía la exploración solo si existe una dependencia real.

Objetivo: reducir tiempo, contexto y consumo de tokens.

---

## 3. DOCUMENTACIÓN PRINCIPAL

```text
AGENTS.md

docs/
├── 01-BASE_MASTER.md
├── 02-CLIENT_TEMPLATE.md
├── 03-BUSINESS_RULES.md
├── 04-ROLES_PERMISSIONS.md
├── 05-DATABASE_BASE.md
├── 06-ARCHITECTURE.md
├── 07-API.md
├── 08-CURRENT_STATUS.md
└── 09-DEPLOYMENT.md

clients/
└── <cliente>/
    ├── CLIENT_CONFIG.md
    ├── BRANDING.md
    ├── BUSINESS_OVERRIDES.md
    ├── MODULES.md
    └── IMPLEMENTATION_STATUS.md
```

`docs/08-CURRENT_STATUS.md` representa el estado global del CORE.

`clients/<cliente>/IMPLEMENTATION_STATUS.md` representa el estado real de una implementación concreta.

No mezclar ambos niveles.

---

## 4. QUÉ LEER SEGÚN LA TAREA

### CORE global o duda arquitectónica

Leer:

```text
AGENTS.md
docs/08-CURRENT_STATUS.md
```

### Tarea de un cliente

Leer primero:

```text
AGENTS.md
clients/<cliente>/IMPLEMENTATION_STATUS.md
clients/<cliente>/CLIENT_CONFIG.md
```

Agregar solamente lo necesario.

### Reservas, ventas, pagos o cuotas

```text
docs/03-BUSINESS_RULES.md
clients/<cliente>/BUSINESS_OVERRIDES.md
```

### Roles, permisos o seguridad

```text
docs/04-ROLES_PERMISSIONS.md
```

### Tablas, relaciones o migraciones

```text
docs/05-DATABASE_BASE.md
```

### Controllers, Actions, Services, Jobs o estructura

```text
docs/06-ARCHITECTURE.md
```

### API o aplicación móvil

```text
docs/07-API.md
```

### Branding o interfaz visual de un cliente

```text
clients/<cliente>/BRANDING.md
```

### Módulos habilitados

```text
clients/<cliente>/MODULES.md
```

### Producción

```text
docs/09-DEPLOYMENT.md
```

No cargues configuraciones de otros clientes.

---

## 5. CORE VS CLIENTE

El CORE define:

- arquitectura;
- entidades;
- relaciones;
- reglas generales;
- seguridad;
- permisos;
- API;
- invariantes.

El cliente define:

- identidad;
- branding;
- módulos habilitados;
- parámetros;
- reglas particulares;
- estado de implementación.

Antes de modificar el CORE pregunta:

**¿Este cambio debe aplicarse realmente a todas las implementaciones?**

Si no, debe resolverse preferentemente mediante:

1. configuración;
2. feature flag;
3. override documentado;
4. código específico solo como último recurso.

No implementar lógica como:

```php
if ($company->name === 'Empresa X') {
    // comportamiento especial
}
```

ni:

```php
if ($companyId === 7) {
    // comportamiento especial
}
```

---

## 6. MULTI-CLIENTE Y MULTI-TENANT

La existencia de:

```text
clients/cliente-a/
clients/cliente-b/
```

no significa automáticamente que la plataforma sea multi-tenant.

Multi-cliente significa que un mismo CORE puede reutilizarse en varias implementaciones.

Multi-tenant significa que una misma instalación aloja varias empresas con aislamiento de datos.

No implementar multi-tenancy accidentalmente.

Si el proyecto desplegado utiliza `company_id`, toda consulta empresarial debe respetar el aislamiento por empresa y la empresa debe derivarse del contexto autenticado, nunca de un valor confiado directamente desde frontend.

---

## 7. AUTORIZACIÓN

Principio:

```text
ROL
+
PERMISO
+
ALCANCE
+
EMPRESA, CUANDO CORRESPONDA
=
ACCESO
```

No implementar seguridad únicamente ocultando botones.

Validar siempre en backend.

---

## 8. ARQUITECTURA

Flujo recomendado:

```text
Request
↓
Controller
↓
Authorization
↓
Action / Service
↓
Model
↓
Database
```

Reglas:

- Controladores pequeños.
- Form Requests para validaciones relevantes.
- Actions para operaciones empresariales concretas.
- Services para lógica realmente reutilizable.
- No crear capas vacías por estética.
- Reutilizar lógica entre Web y API.

Antes de crear una Action o Service comprobar si ya existe una equivalente.

---

## 9. ESTADOS

No utilizar strings inconsistentes para representar el mismo estado.

Preferir Enums o constantes centralizadas.

Ejemplo:

```text
LoteStatus::AVAILABLE
LoteStatus::RESERVED
LoteStatus::SOLD
LoteStatus::BLOCKED
```

No agregar estados nuevos sin justificar la necesidad y revisar impacto.

---

## 10. OPERACIONES CRÍTICAS

Considerar críticas:

- crear/cancelar reserva;
- crear/cancelar venta;
- confirmar/anular pago;
- modificar precio;
- aplicar descuento;
- cambiar estado de lote;
- modificar permisos.

Revisar según corresponda:

```text
Validación
Autorización
Transacción
Concurrencia
Auditoría
Tests
```

---

## 11. TRANSACCIONES Y CONCURRENCIA

Cuando una operación modifique varias entidades, usar transacciones.

Ejemplo:

```php
DB::transaction(...)
```

Para reservas y ventas, verificar nuevamente la disponibilidad dentro de la transacción.

Evaluar:

```php
lockForUpdate()
```

No confiar en que el estado visto anteriormente en pantalla sigue vigente.

---

## 12. DINERO E HISTORIAL

Usar `DECIMAL` para importes. No usar `FLOAT`.

Los registros financieros deben guardar moneda cuando corresponda.

Una venta histórica debe conservar como mínimo sus valores pactados, por ejemplo:

```text
precio_lista
descuento
precio_final
moneda
```

No recalcular operaciones antiguas usando precios actuales.

Un pago confirmado no debe eliminarse físicamente para corregir un error.

Usar anulación, reversión o ajuste trazable y recalcular saldos cuando corresponda.

No modificar silenciosamente:

- precio histórico;
- monto confirmado;
- saldo histórico;
- propietario lógico;
- moneda histórica.

---

## 13. LOTES

Relación base:

```text
Urbanización
↓
Manzano
↓
Lote
```

Estados CORE:

```text
Disponible
Reservado
Vendido
Bloqueado
```

La disponibilidad real debe validarse en servidor.

---

## 14. API Y MÓVIL

API base:

```text
/api/v1/
```

Autenticación recomendada cuando corresponda:

```text
Laravel Sanctum
```

API y Web deben reutilizar Actions y Services.

Arquitectura móvil:

```text
Android
↓
API
↓
Laravel
↓
Base de datos
```

Nunca conectar una app móvil directamente a MySQL.

Para operaciones móviles sensibles considerar idempotencia, por ejemplo mediante `Idempotency-Key`.

Un reintento no debe duplicar reservas, ventas o pagos.

---

## 15. BASE DE DATOS

Antes de crear una tabla, columna o migración:

1. buscar si ya existe;
2. revisar migraciones relacionadas;
3. revisar el modelo;
4. revisar relaciones;
5. revisar índices;
6. revisar foreign keys;
7. revisar compatibilidad con datos existentes;
8. determinar si pertenece al CORE o al cliente.

No modificar migraciones ya utilizadas en producción para cambiar silenciosamente el esquema.

Crear una nueva migración.

Nunca usar en producción como parte de una tarea normal:

```text
migrate:fresh
db:wipe
DROP DATABASE
```

---

## 16. ELIMINACIONES

Para entidades históricas preferir Soft Delete o estados cuando corresponda.

Especialmente:

- Clientes
- Reservas
- Ventas
- Usuarios

Para pagos, preferir anulación/reversión.

No borrar historial financiero.

---

## 17. ARCHIVOS Y SEGURIDAD

Usar Laravel Storage.

No almacenar archivos grandes directamente en MySQL.

Los documentos privados deben requerir autorización.

Nunca guardar en Git:

```text
Passwords
API Keys
Tokens
AWS Secrets
Database Passwords
WhatsApp Tokens
```

Usar `.env` o un mecanismo seguro equivalente.

No mostrar secretos en logs ni auditoría.

---

## 18. AUDITORÍA Y LOGS

Logs técnicos y auditoría empresarial son conceptos distintos.

Usar logs para:

- excepciones;
- integraciones;
- jobs;
- errores operativos.

Usar auditoría para:

- reservas;
- ventas;
- pagos;
- descuentos;
- cambios de estado;
- permisos;
- acciones administrativas sensibles.

No guardar secretos ni documentos completos en logs o auditoría.

---

## 19. TESTS

Antes de considerar una tarea terminada:

1. ejecutar tests relacionados;
2. ejecutar la suite completa cuando sea razonable;
3. no afirmar que algo funciona si no fue verificado.

Tests críticos recomendados:

```text
No reservar lote vendido.
No vender lote vendido.
No reservar lote bloqueado.
No duplicar venta.
Cancelación de reserva libera lote.
Venta marca lote como vendido.
Pago confirmado reduce saldo.
Pago anulado restaura saldo.
Usuario sin permiso recibe acceso denegado.
Aislamiento empresarial funciona cuando aplique.
```

---

## 20. PERFORMANCE

Evitar N+1 queries.

Usar eager loading cuando corresponda.

No cargar miles de registros sin paginación.

Revisar índices antes de agregar cache indiscriminadamente.

---

## 21. GIT

Antes de modificaciones importantes revisar:

```bash
git status
git branch --show-current
```

No realizar automáticamente:

```text
push
merge
rebase
force push
```

salvo que la tarea lo requiera.

Si existen cambios sin commit del usuario u otro agente:

- no eliminarlos;
- no sobrescribirlos;
- trabajar alrededor de ellos.

No usar sin autorización explícita:

```text
git reset --hard
git checkout .
git clean -fd
```

---

## 22. NUEVO CLIENTE

Una nueva implementación debe comenzar copiando:

```text
clients/_template/
```

a:

```text
clients/<cliente>/
```

Luego completar:

```text
CLIENT_CONFIG.md
BRANDING.md
BUSINESS_OVERRIDES.md
MODULES.md
IMPLEMENTATION_STATUS.md
```

Definir primero configuración, branding, módulos y reglas.

Solo después determinar qué funcionalidades requieren código nuevo.

No crear forks del CORE por diferencias visuales o configurables.

---

## 23. ALCANCE DE CADA TAREA

Antes de editar código establecer:

```text
Objetivo
Módulo
Cliente afectado
Archivos probables
Reglas involucradas
Tests necesarios
```

No ampliar el alcance por iniciativa propia salvo dependencia necesaria.

Si aparece una mejora no solicitada, documentarla como recomendación.

---

## 24. SI DOCUMENTACIÓN Y CÓDIGO DIFIEREN

No asumir automáticamente que uno es correcto.

Investigar si:

- la documentación está desactualizada;
- el código está desactualizado;
- existe una excepción legítima.

El código y esquema realmente desplegados, una vez verificados, describen el estado real de implementación.

Las reglas aprobadas e invariantes siguen teniendo prioridad sobre una implementación accidentalmente incorrecta.

No realizar cambios destructivos para forzar coincidencia con documentación desactualizada.

---

## 25. SI FALTA INFORMACIÓN

No inventar reglas empresariales.

Si una decisión afecta:

- dinero;
- contratos;
- propiedad de lotes;
- permisos;
- datos históricos;

no inventar el comportamiento.

Documentar el bloqueo o pedir la decisión cuando realmente sea necesaria.

Para detalles técnicos menores elegir la opción más simple compatible con la arquitectura.

---

## 26. ESTADOS Y DOCUMENTACIÓN

### Estado global del CORE

Actualizar:

```text
docs/08-CURRENT_STATUS.md
```

solo cuando exista un cambio global en:

- CORE;
- arquitectura;
- seguridad;
- API;
- módulo reutilizable;
- estrategia;
- documentación principal.

### Estado de un cliente

Actualizar:

```text
clients/<cliente>/IMPLEMENTATION_STATUS.md
```

después de tareas relevantes del cliente.

Como mínimo actualizar:

- última tarea completada;
- siguiente tarea;
- estado del módulo;
- pruebas;
- bloqueos;
- problemas conocidos;
- commit si corresponde.

No convertir los MD de estado en historiales infinitos. Git conserva el detalle.

---

## 27. CRITERIO DE TERMINADO

Una tarea no está terminada solo porque compile.

Verificar según corresponda:

```text
[ ] Requerimiento implementado
[ ] Validación aplicada
[ ] Autorización aplicada
[ ] Aislamiento verificado si aplica
[ ] Migraciones revisadas
[ ] Tests relevantes aprobados
[ ] Sin secretos añadidos
[ ] Compatibilidad revisada
[ ] Documentación actualizada cuando corresponde
```

Si algo no pudo comprobarse, indicarlo explícitamente.

---

## 28. RESPUESTA FINAL DEL AGENTE

Al finalizar una tarea informar brevemente:

1. qué se cambió;
2. archivos principales modificados;
3. migraciones creadas, si existen;
4. tests ejecutados y resultado;
5. riesgos o pendientes reales;
6. siguiente paso solo si es necesario.

No presentar como comprobado algo que no fue ejecutado.

---

## 29. PROMPT OPERATIVO DE CONTINUACIÓN

Para continuar una implementación:

```text
Lee AGENTS.md y
clients/<cliente>/IMPLEMENTATION_STATUS.md.

Continúa con NEXT_TASK.

Consulta únicamente la documentación adicional necesaria.
Inspecciona primero el código existente.
No dupliques funcionalidad.
Respeta las reglas CORE, MODULES.md y BUSINESS_OVERRIDES.md.
Ejecuta las pruebas relevantes.

Al terminar actualiza
clients/<cliente>/IMPLEMENTATION_STATUS.md
con el estado real.
```

---

## 30. PRINCIPIO FINAL

No intentes entender todo Terrenos para resolver cada tarea.

**Entiende únicamente lo necesario, pero entiende esa parte correctamente.**

Protege siempre:

```text
CORE
+
DATOS
+
REGLAS DE NEGOCIO
+
SEGURIDAD
+
AISLAMIENTO, CUANDO APLIQUE
+
COMPATIBILIDAD
+
TRAZABILIDAD
```

Las nuevas implementaciones deben construirse extendiendo y configurando Terrenos, no copiando y deformando el sistema base sin control.
