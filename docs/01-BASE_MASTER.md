# TERRENOS — BASE MAESTRA DEL PROYECTO

## 1. Objetivo

Este proyecto es la base reutilizable para sistemas inmobiliarios orientados a la administración y comercialización de urbanizaciones, manzanos y lotes.

Debe servir como punto de partida para nuevas implementaciones destinadas a distintas empresas, evitando reconstruir desde cero los módulos comunes.

Cada nueva empresa podrá personalizar:

* Nombre comercial
* Logo
* Colores
* Tipografías
* Imágenes
* Información institucional
* Datos de contacto
* Dominio
* Textos comerciales
* Módulos adicionales

El núcleo funcional descrito en este documento debe mantenerse estable salvo que exista una necesidad empresarial específica.

---

# 2. PRINCIPIO DE ARQUITECTURA

El sistema se divide conceptualmente en dos capas:

## CORE

Contiene las funcionalidades comunes a todas las empresas.

No debe duplicarse ni modificarse innecesariamente para cada cliente.

Incluye:

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

## PERSONALIZACIÓN

Contiene configuraciones y funcionalidades particulares de cada empresa.

Ejemplos:

* Branding
* Logo
* Colores
* Nombre empresarial
* Configuración comercial
* Módulos adicionales
* Integraciones externas
* Flujos particulares

Siempre que sea posible, las personalizaciones deben hacerse mediante configuración y no modificando directamente el CORE.

---

# 3. STACK BASE

Backend:

Laravel

Base de datos:

MySQL

Frontend:

Blade / tecnología definida por la versión actual del proyecto.

API:

REST API cuando sea necesaria para aplicaciones móviles o integraciones externas.

Autenticación:

Sistema de autenticación de Laravel.

Para APIs móviles podrá utilizarse Laravel Sanctum.

Control de versiones:

Git.

Repositorio:

Cada implementación podrá utilizar su propio repositorio derivado del proyecto base.

---

# 4. MÓDULOS OBLIGATORIOS

## 4.1 Urbanizaciones

Debe permitir:

* Crear urbanizaciones
* Editar urbanizaciones
* Visualizar información
* Activar o desactivar urbanizaciones
* Registrar ubicación
* Registrar superficie
* Registrar propietario
* Registrar descripción
* Adjuntar plano
* Adjuntar imágenes
* Consultar disponibilidad

Cada urbanización contiene uno o varios manzanos.

---

# 4.2 Manzanos

Cada manzano pertenece a una urbanización.

Debe permitir:

* Crear manzanos
* Editar manzanos
* Asignarlos a una urbanización
* Definir código o nombre
* Consultar sus lotes
* Consultar disponibilidad

Relación principal:

Urbanización
→ Manzano
→ Lote

---

# 4.3 Lotes

Cada lote pertenece a un manzano.

Debe permitir como mínimo:

* Código de lote
* Superficie
* Precio
* Estado
* Ubicación
* Coordenadas cuando existan
* Observaciones

Estados sugeridos:

* Disponible
* Reservado
* Vendido
* Bloqueado

Los estados deben manejarse mediante reglas del sistema y no únicamente mediante cambios manuales.

---

# 5. CLIENTES

Debe existir un módulo central de clientes.

Información sugerida:

* Nombre
* Apellido
* Documento de identidad
* Teléfono
* WhatsApp
* Correo electrónico
* Dirección
* Fecha de nacimiento
* Observaciones

Un cliente puede tener:

* Reservas
* Compras
* Pagos
* Cuotas
* Historial comercial

No se debe duplicar un cliente innecesariamente.

---

# 6. RESERVAS

Una reserva debe relacionar como mínimo:

Cliente
+
Lote
+
Asesor
+
Fecha
+
Monto de reserva
+
Estado

Estados sugeridos:

* Pendiente
* Activa
* Vencida
* Cancelada
* Convertida en venta

El sistema debe evitar que un lote activo sea reservado simultáneamente para múltiples clientes salvo que exista una regla empresarial explícita que lo permita.

Al reservar un lote:

Disponible
→ Reservado

Al cancelar o vencer una reserva:

Reservado
→ Disponible

Si se concreta la venta:

Reservado
→ Vendido

---

# 7. VENTAS

La venta debe registrar como mínimo:

* Cliente
* Lote
* Asesor
* Precio del lote
* Precio acordado
* Descuento
* Forma de pago
* Fecha
* Estado

Tipos de venta posibles:

* Contado
* Crédito
* Plan de cuotas

Una venta puede generar automáticamente un plan de pagos.

---

# 8. PAGOS

El sistema debe registrar cada pago de forma independiente.

Información mínima:

* Cliente
* Venta
* Monto
* Fecha
* Método de pago
* Número de comprobante
* Usuario que registró el pago
* Observaciones

Métodos posibles:

* Efectivo
* Transferencia
* QR
* Depósito bancario
* Otros

Debe mantenerse historial completo.

Los pagos no deben eliminarse físicamente sin una razón administrativa y auditoría correspondiente.

---

# 9. CUOTAS

Cuando una venta sea financiada, el sistema debe poder generar un cronograma.

Cada cuota puede contener:

* Número
* Fecha de vencimiento
* Capital
* Interés si corresponde
* Monto total
* Saldo
* Estado

Estados:

* Pendiente
* Parcial
* Pagada
* Vencida

El sistema deberá calcular el saldo pendiente de la venta.

---

# 10. REPORTES

Todos los proyectos derivados deben incluir reportes básicos.

Como mínimo:

* Lotes disponibles
* Lotes reservados
* Lotes vendidos
* Reservas
* Ventas
* Ventas por asesor
* Ventas por urbanización
* Pagos
* Saldos pendientes
* Cuotas vencidas
* Clientes
* Ingresos

Los reportes avanzados podrán ser módulos adicionales dependiendo de cada empresa.

---

# 11. USUARIOS

El sistema debe permitir administrar usuarios internos.

Información mínima:

* Nombre
* Email
* Contraseña
* Estado
* Rol

Los usuarios no deben compartir credenciales.

Cada operación relevante debe poder identificar al usuario responsable.

---

# 12. ROLES

Roles base sugeridos:

## Super Administrador

Control completo del sistema.

Puede:

* Administrar empresas/configuración
* Administrar usuarios
* Administrar roles
* Administrar permisos
* Acceder a todos los módulos
* Visualizar toda la información

## Administrador

Gestiona operaciones administrativas y comerciales.

## Supervisor

Supervisa asesores, operaciones y reportes.

## Asesor

Gestiona:

* Clientes
* Lotes asignados
* Reservas
* Ventas autorizadas

Otros roles podrán agregarse según cada empresa.

---

# 13. PERMISOS

Los permisos deben manejarse por módulo y acción.

Ejemplo:

urbanizaciones.ver

urbanizaciones.crear

urbanizaciones.editar

urbanizaciones.eliminar

lotes.ver

lotes.crear

lotes.editar

clientes.ver

clientes.crear

clientes.editar

reservas.crear

reservas.cancelar

ventas.crear

pagos.registrar

reportes.ver

usuarios.administrar

roles.administrar

Nunca se deben controlar permisos únicamente ocultando botones en la interfaz.

El backend debe validar siempre las autorizaciones.

---

# 14. PERSONALIZACIÓN POR EMPRESA

Cada nueva empresa debe disponer de configuración propia.

## Identidad

* Nombre
* Nombre comercial
* Logo principal
* Logo secundario
* Favicon

## Colores

* Color primario
* Color secundario
* Color de acento
* Color de fondo

Los colores no deberían quedar repetidos directamente en múltiples vistas.

Deben administrarse preferentemente mediante variables CSS, configuración o tema.

## Información institucional

* Dirección
* Teléfono
* WhatsApp
* Email
* Sitio web
* Redes sociales

---

# 15. MÓDULOS ADICIONALES

Una empresa podrá solicitar funcionalidades que no pertenecen al CORE.

Ejemplos:

* Aplicación Android
* Aplicación iOS
* Mapa interactivo
* Google Maps
* WhatsApp
* Chatbot IA
* Firma digital
* Contratos automáticos
* Comisiones
* Caja
* Facturación
* Contabilidad
* Inventario
* CRM avanzado
* Campañas
* Integración bancaria
* Cobros mediante QR
* Portal público
* Portal de clientes

Estos módulos deben implementarse sin romper el núcleo existente.

---

# 16. REGLAS PARA IA Y AGENTES DE PROGRAMACIÓN

Antes de realizar cualquier modificación:

1. Leer este documento.

2. Identificar si la solicitud corresponde a:

CORE

o

PERSONALIZACIÓN.

3. No modificar módulos estables que no estén relacionados con la tarea.

4. Inspeccionar únicamente los archivos necesarios.

5. No reconstruir la arquitectura desde cero si ya existe.

6. Reutilizar servicios, modelos, componentes y funciones existentes.

7. No duplicar lógica.

8. Mantener compatibilidad con funcionalidades existentes.

9. Ejecutar pruebas relacionadas después de cada cambio.

10. Registrar las modificaciones importantes.

---

# 17. REGLA DE AHORRO DE CONTEXTO PARA IA

No analizar automáticamente todo el repositorio para cada tarea.

Proceso recomendado:

Primero leer:

AGENTS.md

Después leer únicamente el documento relacionado con la tarea.

Ejemplo:

Base de datos:

docs/DATABASE.md

API:

docs/API.md

Arquitectura:

docs/ARCHITECTURE.md

Estado:

docs/CURRENT_STATUS.md

Reglas comerciales:

docs/BUSINESS_RULES.md

Después inspeccionar solamente los archivos de código involucrados.

---

# 18. DOCUMENTACIÓN RECOMENDADA

La estructura ideal será:

```text
/
├── AGENTS.md
│
├── docs/
│   ├── ARCHITECTURE.md
│   ├── DATABASE.md
│   ├── BUSINESS_RULES.md
│   ├── API.md
│   ├── ROLES_PERMISSIONS.md
│   ├── DEPLOYMENT.md
│   └── CURRENT_STATUS.md
│
├── app/
├── database/
├── resources/
├── routes/
└── tests/
```

---

# 19. CREACIÓN DE UNA NUEVA EMPRESA

Cuando se cree una nueva versión del sistema:

NO iniciar un proyecto completamente nuevo.

Proceso recomendado:

1. Clonar la base Terrenos.

2. Crear nueva rama o repositorio.

3. Configurar nombre de empresa.

4. Configurar logo.

5. Configurar colores.

6. Configurar información institucional.

7. Mantener módulos CORE.

8. Definir requerimientos adicionales.

9. Crear módulos personalizados.

10. Ejecutar pruebas del CORE.

11. Ejecutar pruebas de las personalizaciones.

12. Preparar despliegue.

---

# 20. ARCHIVO DE PERSONALIZACIÓN

Cada implementación deberá tener un documento:

```text
docs/CLIENT_CONFIG.md
```

Ejemplo:

```md
# Cliente

Empresa:
INMOBILIARIA EJEMPLO

## Branding

Logo:
logo-cliente.png

Color primario:
#XXXXXX

Color secundario:
#XXXXXX

## Módulos CORE

Urbanizaciones: activo
Manzanos: activo
Lotes: activo
Clientes: activo
Reservas: activo
Ventas: activo
Pagos: activo
Cuotas: activo
Reportes: activo

## Personalizaciones

- módulo adicional X
- integración X
- regla comercial X

## Restricciones

- regla particular del cliente

## Estado

En desarrollo
```

---

# 21. OBJETIVO FINAL

Terrenos debe evolucionar como una plataforma base reutilizable.

La filosofía debe ser:

CORE estable

*

configuración empresarial

*

módulos adicionales

=

nueva implementación

Esto permitirá crear nuevas versiones del sistema con menor tiempo de desarrollo, menor riesgo de errores y menor consumo de recursos de IA.
