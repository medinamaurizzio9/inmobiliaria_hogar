# CLIENT_CONFIG.md

## 1. Identificación

- Código del cliente:
- Nombre comercial:
- Razón social:
- NIT:
- País:
- Departamento:
- Ciudad:
- Dirección:
- Teléfono:
- WhatsApp:
- Correo:
- Sitio web:

---

## 2. Estado del cliente

- Estado: `development`
- Fecha de inicio:
- Responsable:
- Versión del CORE utilizada:
- Dominio producción:
- Dominio staging:

Estados permitidos:

- `planning`
- `development`
- `testing`
- `production`
- `suspended`

---

## 3. Tipo de implementación

Este cliente utiliza el CORE del sistema de terrenos.

No crear un fork del CORE por diferencias de:

- nombre comercial;
- logo;
- colores;
- textos;
- moneda;
- datos de contacto;
- módulos opcionales;
- configuraciones comerciales.

Las diferencias deben resolverse mediante configuración.

### Modalidad

- [ ] Venta de terrenos
- [ ] Venta al contado
- [ ] Venta a crédito
- [ ] Reservas
- [ ] Cobranza de cuotas
- [ ] Comisiones
- [ ] Aplicación móvil
- [ ] Portal público
- [ ] Portal del cliente
- [ ] Otro:

---

## 4. Moneda

- Moneda principal: `BOB`
- Símbolo: `Bs`
- Decimales: `2`
- Separador decimal: `.`
- Separador de miles: `,`

No almacenar importes monetarios como FLOAT.

Usar DECIMAL según la definición del CORE.

---

## 5. Zona horaria y localización

- Zona horaria: `America/La_Paz`
- Idioma: `es`
- País: `BO`
- Formato fecha: `DD/MM/YYYY`
- Formato hora: `24h`

Las fechas deben almacenarse siguiendo la política definida por el CORE.

---

## 6. Datos comerciales

### Reservas

- Reservas habilitadas: `true`
- Duración predeterminada:
- Monto mínimo:
- Permitir extensión: `true`
- Máximo de extensiones:
- Requiere aprobación:

### Ventas

- Venta contado: `true`
- Venta crédito: `true`
- Cuota inicial obligatoria:
- Porcentaje mínimo inicial:
- Número máximo de cuotas:
- Permitir descuentos:
- Descuento requiere autorización:

### Pagos

Métodos habilitados:

- [ ] Efectivo
- [ ] Transferencia
- [ ] QR
- [ ] Depósito bancario
- [ ] Tarjeta
- [ ] Otro

---

## 7. Urbanizaciones

Un cliente puede administrar una o varias urbanizaciones.

No colocar urbanizaciones específicas dentro del código fuente.

Las urbanizaciones deben administrarse desde la base de datos.

Configuraciones especiales del cliente deberán documentarse en:

`BUSINESS_OVERRIDES.md`

---

## 8. Módulos

La habilitación de módulos se define en:

`MODULES.md`

No eliminar código CORE porque un cliente no utilice determinado módulo.

Los módulos deben habilitarse/deshabilitarse mediante configuración cuando corresponda.

---

## 9. Branding

Toda personalización visual debe definirse en:

`BRANDING.md`

Incluye:

- logo;
- favicon;
- colores;
- nombre del sistema;
- nombre comercial;
- imágenes;
- textos institucionales.

No hardcodear branding del cliente dentro del CORE.

---

## 10. Reglas particulares

Las reglas comerciales que difieran del CORE deben documentarse en:

`BUSINESS_OVERRIDES.md`

Una excepción de cliente NO modifica automáticamente una regla global.

Antes de cambiar el CORE se debe determinar si la necesidad:

1. corresponde únicamente al cliente;
2. debe convertirse en configuración;
3. realmente constituye una mejora global del CORE.

---

## 11. Usuarios iniciales

### Superadministrador

- Crear: `true`
- Nombre:
- Email:
- Rol: `super_admin`

No guardar contraseñas reales en este archivo.

Las credenciales deben definirse mediante variables de entorno o un mecanismo seguro de instalación.

---

## 12. Aplicación móvil

- Habilitada: `false`
- Android: `false`
- iOS: `false`
- API habilitada: `true`
- Funcionamiento offline:
- Sincronización incremental:
- Reservas offline:
- Cobros offline:

La aplicación móvil debe consumir la API del CORE.

No debe conectarse directamente a la base de datos.

---

## 13. Infraestructura

### Desarrollo

- Sistema operativo:
- Servidor local:
- PHP:
- MySQL/MariaDB:
- Node:
- Composer:

### Producción

- Proveedor:
- Tipo:
- Sistema operativo:
- Web server:
- PHP:
- Base de datos:
- SSL:
- Backups:
- Cron:
- Queue worker:

No almacenar:

- contraseñas;
- claves SSH;
- API keys;
- tokens;
- secretos.

Usar `.env`.

---

## 14. Integraciones

Marcar las utilizadas:

- [ ] WhatsApp
- [ ] Email
- [ ] SMS
- [ ] Google Maps
- [ ] OpenStreetMap
- [ ] Pasarela de pagos
- [ ] QR bancario
- [ ] Facturación
- [ ] Inteligencia artificial
- [ ] Almacenamiento externo
- [ ] Otra:

Las credenciales nunca deben almacenarse en este MD.

---

## 15. Archivos relacionados

Codex debe consultar, cuando corresponda:

- `/AGENTS.md`
- `/docs/01-BASE_MASTER.md`
- `/docs/03-BUSINESS_RULES.md`
- `/docs/04-ROLES_PERMISSIONS.md`
- `/docs/05-DATABASE_BASE.md`
- `/docs/06-ARCHITECTURE.md`
- `/docs/07-API.md`
- `/docs/08-CURRENT_STATUS.md`
- `./BRANDING.md`
- `./BUSINESS_OVERRIDES.md`
- `./MODULES.md`
- `./IMPLEMENTATION_STATUS.md`

---

## 16. Regla para Codex

Antes de implementar una funcionalidad específica de este cliente:

1. Leer `AGENTS.md`.
2. Consultar `docs/08-CURRENT_STATUS.md`.
3. Leer este archivo.
4. Revisar `MODULES.md`.
5. Revisar `BUSINESS_OVERRIDES.md`.
6. Consultar únicamente los documentos CORE necesarios para la tarea.
7. Verificar el código existente antes de crear código nuevo.
8. No duplicar funcionalidad existente.
9. No modificar el CORE por una necesidad exclusiva del cliente.
10. Actualizar `IMPLEMENTATION_STATUS.md` después de completar una fase.

---

## 17. Principio fundamental

**CORE primero, configuración después, personalización al final.**

Un nuevo cliente no debe convertirse en un nuevo sistema.

Debe convertirse en una nueva configuración del mismo sistema.