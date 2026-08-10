# MODULES.md

# Módulos habilitados para el cliente

Este documento define qué módulos del CORE están habilitados para esta
implementación.

Documentos relacionados:

- `/AGENTS.md`
- `/docs/01-BASE_MASTER.md`
- `/docs/03-BUSINESS_RULES.md`
- `/docs/04-ROLES_PERMISSIONS.md`
- `./CLIENT_CONFIG.md`
- `./BUSINESS_OVERRIDES.md`
- `./IMPLEMENTATION_STATUS.md`

---

# 1. Principio general

El CORE puede contener más funcionalidades de las que utiliza un cliente.

Un módulo deshabilitado:

- NO debe eliminarse del CORE;
- NO debe provocar errores;
- NO debe aparecer en navegación;
- NO debe permitir acceso mediante URL directa;
- NO debe exponer endpoints activos innecesarios;
- NO debe ejecutar procesos programados propios;
- NO debe generar notificaciones;
- NO debe afectar otros módulos.

La visibilidad del menú NO constituye control de seguridad.

Backend, rutas y permisos deben validar igualmente el acceso.

---

# 2. Estados permitidos

Cada módulo puede encontrarse en:

`enabled`

`disabled`

`development`

`testing`

`planned`

Ejemplo:

```text
Clientes: enabled
Comisiones: disabled
Portal Cliente: planned