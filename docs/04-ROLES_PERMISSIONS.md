# ROLES_PERMISSIONS.md

# ROLES Y PERMISOS — PLATAFORMA TERRENOS

## 1. OBJETIVO

Este documento define el modelo base de acceso para todas las implementaciones derivadas de Terrenos.

Principio:

USUARIO
→ ROL
→ PERMISOS
→ ACCESO A FUNCIONES

Los permisos deben validarse siempre en backend.

Ocultar una opción del menú NO constituye una medida de seguridad.

---

# 2. ROLES CORE

Roles iniciales:

1. Super Administrador
2. Administrador
3. Supervisor
4. Asesor

Roles opcionales:

5. Cajero
6. Contabilidad
7. Gerencia
8. Auditor

Cada empresa podrá crear roles adicionales combinando permisos existentes.

---

# 3. SUPER ADMINISTRADOR

Es el nivel máximo del sistema.

Tiene acceso completo a:

* Configuración
* Urbanizaciones
* Manzanos
* Lotes
* Clientes
* Reservas
* Ventas
* Pagos
* Cuotas
* Reportes
* Usuarios
* Roles
* Permisos
* Auditoría

Puede realizar operaciones administrativas extraordinarias.

Debe existir un número reducido de usuarios con este rol.

---

# 4. ADMINISTRADOR

Administra la operación de la empresa.

Puede normalmente:

* Gestionar urbanizaciones
* Gestionar manzanos
* Gestionar lotes
* Gestionar clientes
* Supervisar reservas
* Gestionar ventas
* Confirmar determinados pagos
* Consultar cuotas
* Consultar reportes
* Gestionar asesores
* Consultar actividad

No debe tener automáticamente acceso a funciones reservadas al Super Administrador.

---

# 5. SUPERVISOR

Supervisa principalmente la operación comercial.

Puede:

* Consultar urbanizaciones
* Consultar manzanos
* Consultar lotes
* Gestionar clientes de su ámbito
* Revisar reservas
* Supervisar asesores
* Revisar ventas
* Aprobar determinadas excepciones
* Consultar reportes comerciales

Puede tener límites de autorización configurables.

Ejemplo:

Asesor:
descuento máximo 2 %

Supervisor:
descuento máximo 5 %

Administrador:
descuento máximo 10 %

Los porcentajes NO son valores CORE.

Son configuraciones del cliente.

---

# 6. ASESOR

Es el usuario comercial.

Puede normalmente:

* Consultar urbanizaciones asignadas
* Consultar manzanos
* Consultar lotes
* Consultar disponibilidad
* Registrar clientes
* Editar clientes permitidos
* Crear reservas
* Consultar sus reservas
* Crear ventas cuando tenga autorización
* Consultar sus ventas
* Consultar sus indicadores

No debe acceder automáticamente a:

* Usuarios
* Roles
* Permisos
* Configuración global
* Auditoría
* Información financiera global
* Ventas de otros asesores
* Comisiones de otros asesores

---

# 7. CAJERO

Rol opcional.

Orientado al registro y validación de cobros.

Puede:

* Buscar clientes
* Consultar ventas
* Consultar cuotas
* Registrar pagos
* Adjuntar comprobantes
* Emitir recibos
* Consultar caja

No debe poder:

* Modificar precio de lotes
* Crear urbanizaciones
* Cambiar permisos
* Modificar ventas arbitrariamente

---

# 8. CONTABILIDAD

Rol opcional.

Puede:

* Consultar pagos
* Consultar cuotas
* Consultar ventas
* Consultar movimientos financieros
* Exportar reportes
* Consultar comprobantes
* Consultar caja

Los permisos de modificación deberán definirse por empresa.

---

# 9. GERENCIA

Rol opcional de consulta ejecutiva.

Puede tener acceso a:

* Dashboard general
* Ventas
* Cobros
* Saldos
* Mora
* Comisiones
* Rendimiento de asesores
* Disponibilidad
* Reportes

Puede configurarse principalmente como rol de lectura.

---

# 10. AUDITOR

Rol opcional.

Acceso principalmente de lectura.

Puede consultar:

* Auditoría
* Ventas
* Reservas
* Pagos
* Anulaciones
* Cambios de precios
* Descuentos
* Usuarios responsables

No debería modificar operaciones comerciales.

---

# 11. NOMENCLATURA DE PERMISOS

Utilizar preferentemente:

`modulo.accion`

Ejemplos:

`lotes.ver`

`lotes.crear`

`lotes.editar`

`lotes.eliminar`

`ventas.ver`

`ventas.crear`

`ventas.anular`

---

# 12. URBANIZACIONES

Permisos:

`urbanizaciones.ver`

`urbanizaciones.crear`

`urbanizaciones.editar`

`urbanizaciones.archivar`

`urbanizaciones.eliminar`

`urbanizaciones.documentos`

`urbanizaciones.planos`

---

# 13. MANZANOS

`manzanos.ver`

`manzanos.crear`

`manzanos.editar`

`manzanos.archivar`

`manzanos.eliminar`

---

# 14. LOTES

`lotes.ver`

`lotes.crear`

`lotes.editar`

`lotes.archivar`

`lotes.eliminar`

`lotes.cambiar_estado`

`lotes.cambiar_precio`

`lotes.bloquear`

`lotes.desbloquear`

`lotes.ver_costos`

---

# 15. CLIENTES

`clientes.ver`

`clientes.crear`

`clientes.editar`

`clientes.archivar`

`clientes.eliminar`

`clientes.ver_documentos`

`clientes.subir_documentos`

`clientes.exportar`

---

# 16. RESERVAS

`reservas.ver`

`reservas.crear`

`reservas.editar`

`reservas.extender`

`reservas.cancelar`

`reservas.convertir_venta`

`reservas.ver_todas`

`reservas.ver_propias`

---

# 17. VENTAS

`ventas.ver`

`ventas.crear`

`ventas.editar`

`ventas.confirmar`

`ventas.anular`

`ventas.ver_todas`

`ventas.ver_propias`

`ventas.aplicar_descuento`

`ventas.aprobar_descuento`

`ventas.generar_contrato`

`ventas.ver_contrato`

---

# 18. PAGOS

`pagos.ver`

`pagos.registrar`

`pagos.confirmar`

`pagos.rechazar`

`pagos.anular`

`pagos.ver_todos`

`pagos.ver_propios`

`pagos.ver_comprobantes`

`pagos.subir_comprobantes`

`pagos.emitir_recibo`

---

# 19. CUOTAS

`cuotas.ver`

`cuotas.crear`

`cuotas.reprogramar`

`cuotas.aplicar_pago`

`cuotas.aplicar_mora`

`cuotas.condonar_mora`

`cuotas.ver_todas`

---

# 20. REPORTES

`reportes.ver`

`reportes.comerciales`

`reportes.financieros`

`reportes.clientes`

`reportes.asesores`

`reportes.cobranzas`

`reportes.mora`

`reportes.comisiones`

`reportes.exportar`

---

# 21. USUARIOS

`usuarios.ver`

`usuarios.crear`

`usuarios.editar`

`usuarios.activar`

`usuarios.desactivar`

`usuarios.asignar_rol`

`usuarios.resetear_password`

---

# 22. ROLES

`roles.ver`

`roles.crear`

`roles.editar`

`roles.eliminar`

`roles.asignar_permisos`

---

# 23. CONFIGURACIÓN

`configuracion.ver`

`configuracion.editar`

`configuracion.branding`

`configuracion.empresa`

`configuracion.comercial`

`configuracion.financiera`

`configuracion.integraciones`

---

# 24. AUDITORÍA

`auditoria.ver`

`auditoria.exportar`

La auditoría no debe poder modificarse desde operaciones normales del sistema.

---

# 25. COMISIONES

`comisiones.ver`

`comisiones.ver_propias`

`comisiones.ver_todas`

`comisiones.calcular`

`comisiones.aprobar`

`comisiones.pagar`

`comisiones.anular`

---

# 26. DASHBOARD

Permisos:

`dashboard.general`

`dashboard.comercial`

`dashboard.financiero`

`dashboard.asesor`

Cada usuario debe ver solamente indicadores para los que tenga autorización.

---

# 27. ALCANCE DE INFORMACIÓN

Los permisos determinan QUÉ puede hacer el usuario.

El alcance determina SOBRE QUÉ información puede hacerlo.

Ejemplo:

Un asesor puede tener:

`ventas.ver`

pero solamente sobre sus propias ventas.

Un supervisor puede tener:

`ventas.ver`

sobre las ventas de su equipo.

Un administrador puede tener:

`ventas.ver`

sobre toda la empresa.

---

# 28. NIVELES DE ALCANCE

Cuando sea necesario, utilizar:

PROPIO

EQUIPO

URBANIZACIONES ASIGNADAS

SUCURSAL

EMPRESA

GLOBAL

Ejemplo:

Asesor
→ PROPIO / URBANIZACIONES ASIGNADAS

Supervisor
→ EQUIPO

Administrador
→ EMPRESA

Super Administrador
→ GLOBAL

---

# 29. URBANIZACIONES ASIGNADAS

Un usuario puede tener acceso únicamente a determinadas urbanizaciones.

Ejemplo:

Asesor Juan:

Urbanización Norte
Urbanización Paraíso

No debería consultar datos privados de:

Urbanización Sur

si no está asignado.

Esta restricción debe aplicarse en backend.

---

# 30. SUCURSALES

Si una empresa tiene sucursales:

Los usuarios podrán pertenecer a una o varias sucursales según la implementación.

Los permisos podrán limitarse a:

Sucursal actual

o

Toda la empresa.

---

# 31. DESCUENTOS

No utilizar solamente roles para controlar descuentos.

Utilizar:

Permiso

*

Límite configurable

Ejemplo:

`ventas.aplicar_descuento`

Máximo:
2 %

Para superar ese valor:

`ventas.aprobar_descuento`

---

# 32. OPERACIONES CRÍTICAS

Considerar críticas:

* Anular venta
* Anular pago
* Cambiar precio
* Aplicar descuento excepcional
* Liberar lote vendido
* Cambiar comprador
* Reprogramar deuda
* Modificar permisos
* Eliminar información
* Cambiar configuración financiera

Estas acciones requieren permisos específicos.

---

# 33. CONFIRMACIÓN DE OPERACIONES CRÍTICAS

Las operaciones críticas deberían solicitar confirmación.

Ejemplo:

"Está a punto de anular un pago confirmado."

Cuando corresponda debe solicitarse:

* Motivo
* Observación
* Usuario responsable

---

# 34. AUDITORÍA DE PERMISOS

Registrar:

* Usuario
* Acción
* Fecha
* Registro afectado
* Valor anterior
* Valor nuevo

Especialmente para:

* Precios
* Descuentos
* Ventas
* Pagos
* Estados de lotes
* Roles
* Permisos

---

# 35. SEGURIDAD DEL BACKEND

Nunca confiar exclusivamente en:

* Menús ocultos
* Botones ocultos
* JavaScript
* Frontend

Ejemplo:

Aunque el botón "Anular pago" no aparezca, el servidor debe rechazar una petición directa de un usuario sin:

`pagos.anular`

---

# 36. PRINCIPIO DE MENOR PRIVILEGIO

Cada usuario debe recibir solamente los permisos necesarios para realizar su trabajo.

No utilizar Administrador como solución para usuarios que simplemente necesitan una función adicional.

Crear o asignar el permiso específico.

---

# 37. MATRIZ BASE DE ROLES

Leyenda:

✓ = permitido normalmente

LIMITADO = depende del alcance/configuración

— = no permitido por defecto

| Función              | Super Admin | Admin    | Supervisor | Asesor        |
| -------------------- | ----------- | -------- | ---------- | ------------- |
| Configuración global | ✓           | LIMITADO | —          | —             |
| Urbanizaciones       | ✓           | ✓        | Ver        | Ver asignadas |
| Manzanos             | ✓           | ✓        | Ver        | Ver asignados |
| Lotes                | ✓           | ✓        | LIMITADO   | Ver asignados |
| Cambiar precio       | ✓           | ✓        | LIMITADO   | —             |
| Clientes             | ✓           | ✓        | ✓          | LIMITADO      |
| Crear reserva        | ✓           | ✓        | ✓          | ✓             |
| Ver todas reservas   | ✓           | ✓        | EQUIPO     | —             |
| Crear venta          | ✓           | ✓        | ✓          | LIMITADO      |
| Ver todas ventas     | ✓           | ✓        | EQUIPO     | —             |
| Descuentos           | ✓           | ✓        | LIMITADO   | LIMITADO      |
| Confirmar pagos      | ✓           | ✓        | LIMITADO   | —             |
| Anular pagos         | ✓           | LIMITADO | —          | —             |
| Cuotas               | ✓           | ✓        | Ver        | LIMITADO      |
| Reportes generales   | ✓           | ✓        | LIMITADO   | —             |
| Reportes propios     | ✓           | ✓        | ✓          | ✓             |
| Usuarios             | ✓           | ✓        | LIMITADO   | —             |
| Roles                | ✓           | LIMITADO | —          | —             |
| Permisos             | ✓           | —        | —          | —             |
| Auditoría            | ✓           | LIMITADO | —          | —             |

Esta matriz es una configuración inicial, no una regla rígida.

Cada implementación debe definir su matriz definitiva.

---

# 38. ROLES PERSONALIZADOS

Una empresa puede solicitar:

Gerente Comercial

Cobrador

Jefe de Ventas

Abogado

Recepción

Marketing

Caja

Contabilidad

No modificar el CORE para cada nuevo cargo.

Crear el rol y asignarle permisos existentes.

Si realmente aparece una capacidad nueva:

Crear un nuevo permiso.

---

# 39. REGLA PARA NUEVAS FUNCIONES

Todo nuevo módulo debe definir:

1. Permiso para visualizar.
2. Permiso para crear si corresponde.
3. Permiso para editar.
4. Permiso para operaciones especiales.
5. Alcance de datos.
6. Operaciones que requieren auditoría.

No lanzar un nuevo módulo sin definir autorización.

---

# 40. USUARIOS DESACTIVADOS

Un usuario desactivado:

* No puede iniciar sesión.
* No pierde su historial.
* No deben eliminarse sus operaciones anteriores.

Sus ventas, reservas y pagos registrados deben continuar mostrando quién realizó la operación.

---

# 41. ELIMINACIÓN DE USUARIOS

Preferir:

DESACTIVAR

en lugar de:

ELIMINAR

especialmente cuando el usuario tenga operaciones relacionadas.

---

# 42. SUPER ADMINISTRADOR

El Super Administrador debe utilizarse únicamente para administración de alto nivel.

No debería utilizarse como cuenta cotidiana de asesor o cajero.

---

# 43. PERSONALIZACIÓN POR EMPRESA

En `CLIENT_TEMPLATE.md` deberá definirse:

Roles utilizados:

Permisos especiales:

Límites de descuentos:

Alcance de asesores:

Alcance de supervisores:

Quién confirma pagos:

Quién anula pagos:

Quién modifica precios:

Quién aprueba excepciones:

Quién accede a reportes financieros:

---

# 44. REGLA PARA AGENTES DE IA

Antes de modificar autenticación, roles o permisos:

1. Leer `BASE_MASTER.md`.
2. Leer `BUSINESS_RULES.md`.
3. Leer este archivo.
4. Leer `CLIENT_TEMPLATE.md`.
5. Identificar el permiso involucrado.
6. Identificar el alcance de datos.
7. Revisar backend y frontend.
8. No solucionar seguridad solamente ocultando elementos visuales.
9. Crear o actualizar pruebas.
10. Verificar que otro cliente no resulte afectado.

---

# 45. PRUEBAS MÍNIMAS DE AUTORIZACIÓN

Debe comprobarse como mínimo:

* Asesor no accede a configuración.
* Asesor no modifica permisos.
* Asesor no ve urbanizaciones no asignadas.
* Asesor no anula pagos sin permiso.
* Supervisor ve información de su ámbito.
* Usuario desactivado no inicia sesión.
* Usuario sin permiso recibe acceso denegado.
* Cambiar URL manualmente no evita autorización.
* API aplica las mismas restricciones.
* Descuentos respetan límites.
* Operaciones críticas quedan auditadas.

---

# 46. PRINCIPIO FINAL

ROL
≠
PERMISO
≠
ALCANCE

El rol agrupa capacidades.

El permiso define qué acción puede realizarse.

El alcance determina sobre qué información puede realizarse.

Ejemplo:

ASESOR

*

`ventas.ver`

*

URBANIZACIONES ASIGNADAS

=

Puede consultar ventas únicamente dentro del ámbito autorizado.

Este principio debe mantenerse en todas las versiones de Terrenos.
