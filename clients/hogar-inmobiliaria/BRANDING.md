# BRANDING.md

# Branding del Cliente

Este documento define exclusivamente la identidad visual y los textos
institucionales del cliente.

La personalización visual NO debe modificar la lógica de negocio del CORE.

---

## 1. Identidad

- Nombre comercial:
- Nombre corto:
- Razón social:
- Eslogan:
- Descripción corta:
- Descripción institucional:

Ejemplo:

Nombre comercial: Inmolider  
Nombre corto: Inmolider  
Eslogan: Tu terreno, tu futuro.

---

## 2. Nombre del sistema

Nombre mostrado en:

- Login:
- Dashboard:
- Menú:
- Título navegador:
- Aplicación móvil:
- Portal público:
- Credenciales/documentos:

Nombre predeterminado:

`Sistema de Gestión Inmobiliaria`

No hardcodear el nombre del cliente en vistas, controladores o componentes.

---

## 3. Logos

### Logo principal

Archivo:

`branding/logo.png`

Uso:

- login;
- sidebar;
- navbar;
- documentos;
- reportes;
- portal público.

### Logo horizontal

Archivo:

`branding/logo-horizontal.png`

### Logo oscuro

Archivo:

`branding/logo-dark.png`

### Logo claro

Archivo:

`branding/logo-light.png`

### Isotipo

Archivo:

`branding/isotipo.png`

### Favicon

Archivo:

`branding/favicon.png`

---

## 4. Regla de almacenamiento

Los archivos de branding específicos del cliente deben mantenerse separados
del código CORE.

Ejemplo:

clients/
└── CLIENTE/
    └── branding/
        ├── logo.png
        ├── logo-horizontal.png
        ├── logo-dark.png
        ├── logo-light.png
        ├── isotipo.png
        ├── favicon.png
        ├── login-background.jpg
        └── default-project.jpg

No reemplazar imágenes globales del CORE directamente.

---

## 5. Paleta de colores

### Color primario

- Nombre:
- HEX:
- RGB:

Ejemplo:

`#0F2747`

### Color secundario

- Nombre:
- HEX:
- RGB:

### Color de acento

- Nombre:
- HEX:
- RGB:

### Fondo principal

- HEX:

### Fondo secundario

- HEX:

### Texto principal

- HEX:

### Texto secundario

- HEX:

### Bordes

- HEX:

---

## 6. Colores funcionales

Estos colores representan estados del sistema.

### Éxito

HEX:

### Advertencia

HEX:

### Error

HEX:

### Información

HEX:

Los colores funcionales deben conservar suficiente contraste y legibilidad.

No utilizar el color corporativo para reemplazar indiscriminadamente colores
de estados funcionales.

---

## 7. Estados de lotes

Definir los colores utilizados en:

- mapas;
- planos;
- tarjetas;
- tablas;
- leyendas.

### Disponible

Color:

### Reservado

Color:

### Vendido

Color:

### Bloqueado

Color:

### No disponible

Color:

IMPORTANTE:

Los estados son reglas del CORE.

Este archivo únicamente define su representación visual.

No crear estados nuevos desde BRANDING.md.

---

## 8. Tipografía

### Fuente principal

Nombre:

Fallback:

Ejemplo:

`Inter, Arial, sans-serif`

### Fuente secundaria

Nombre:

Fallback:

### Pesos permitidos

- 400 Regular
- 500 Medium
- 600 Semibold
- 700 Bold

Evitar cargar múltiples fuentes innecesarias.

Priorizar rendimiento.

---

## 9. Diseño general

Estilo visual:

- [ ] Corporativo
- [ ] Minimalista
- [ ] Moderno
- [ ] Premium
- [ ] Institucional
- [ ] Otro:

### Características

- Bordes:
- Radio de tarjetas:
- Sombras:
- Espaciado:
- Densidad:
- Tamaño sidebar:

---

## 10. Dashboard

### Encabezado

Mostrar:

- [ ] Logo
- [ ] Nombre empresa
- [ ] Usuario
- [ ] Rol
- [ ] Notificaciones
- [ ] Fecha
- [ ] Sucursal

### Tarjetas

Estilo:

- Fondo:
- Bordes:
- Radio:
- Iconos:

No cambiar los indicadores del dashboard desde este documento.

Los KPI pertenecen al CORE o a `MODULES.md`.

---

## 11. Sidebar

### Logo

- Mostrar logo: `true`
- Mostrar nombre empresa: `true`
- Mostrar versión sistema: `false`

### Comportamiento

- Colapsable: `true`
- Estado inicial desktop: `expanded`
- Estado inicial móvil: `collapsed`

### Apariencia

- Fondo:
- Texto:
- Iconos:
- Elemento activo:
- Hover:

---

## 12. Login

### Logo

Mostrar: `true`

### Imagen de fondo

Archivo:

`branding/login-background.jpg`

### Texto principal

Ejemplo:

`Bienvenido`

### Texto secundario

Ejemplo:

`Sistema de Gestión Inmobiliaria`

### Mostrar eslogan

`true`

### Mostrar nombre empresa

`true`

### Mostrar versión

`false`

---

## 13. Portal público

Habilitado:

`false`

Cuando esté habilitado podrá utilizar:

### Hero

Título:

Subtítulo:

Imagen:

CTA:

### Información de contacto

Mostrar:

- [ ] Teléfono
- [ ] WhatsApp
- [ ] Email
- [ ] Dirección
- [ ] Redes sociales

---

## 14. Urbanizaciones

Cada urbanización puede tener imágenes propias.

Ejemplo:

- portada;
- galería;
- plano;
- ubicación;
- renders;
- fotografías;
- videos.

Estas imágenes NO forman parte del branding global.

Deben pertenecer a la urbanización correspondiente.

---

## 15. Documentos

Aplicar identidad visual a:

- contratos;
- recibos;
- comprobantes;
- cotizaciones;
- estados de cuenta;
- reportes;
- reservas;
- ventas;
- documentos PDF.

### Encabezado

Mostrar:

- [ ] Logo
- [ ] Empresa
- [ ] NIT
- [ ] Dirección
- [ ] Teléfono
- [ ] WhatsApp

### Pie

Texto:

Mostrar numeración:

`true`

Mostrar fecha generación:

`true`

---

## 16. Recibos

Configuración visual:

- Logo: `true`
- Nombre empresa: `true`
- Número recibo: `true`
- QR verificación: `false`
- Firma: `true`
- Sello: `false`

Texto inferior:

`Gracias por su pago.`

Los datos financieros del recibo NO se definen aquí.

---

## 17. Aplicación móvil

Si el cliente utiliza aplicación móvil:

### Nombre APP

Nombre:

### Logo

Archivo:

`branding/mobile-logo.png`

### Icono

Archivo:

`branding/app-icon.png`

### Splash

Archivo:

`branding/splash-logo.png`

### Colores

Primary:

Secondary:

Background:

Surface:

La aplicación móvil debe obtener la configuración dinámica cuando la
arquitectura lo permita.

Evitar mantener colores duplicados manualmente entre Web y Android.

---

## 18. Modo oscuro

Habilitado:

`false`

Si está habilitado:

### Fondo

HEX:

### Surface

HEX:

### Texto

HEX:

### Sidebar

HEX:

### Bordes

HEX:

No invertir logos automáticamente si pierden legibilidad.

Usar `logo-dark` o `logo-light` según corresponda.

---

## 19. Responsive

El sistema debe funcionar correctamente en:

- Desktop
- Laptop
- Tablet
- Celular

Prioridades móviles:

1. navegación sencilla;
2. botones táctiles;
3. tablas adaptables;
4. formularios utilizables;
5. planos consultables;
6. cobros rápidos;
7. búsqueda rápida de clientes y lotes.

El branding nunca debe romper el responsive del CORE.

---

## 20. Accesibilidad

Mantener:

- contraste adecuado;
- textos legibles;
- botones identificables;
- estados distinguibles;
- formularios con labels;
- navegación clara.

No depender exclusivamente del color para representar un estado.

Ejemplo:

NO usar únicamente:

`VERDE = disponible`

Usar:

`● Disponible`

junto con el color correspondiente.

---

## 21. Redes sociales

### Facebook

URL:

### Instagram

URL:

### TikTok

URL:

### YouTube

URL:

### WhatsApp

Número:

No almacenar tokens o claves de APIs sociales.

---

## 22. Textos institucionales

### Copyright

Ejemplo:

`© {{YEAR}} {{COMPANY_NAME}}. Todos los derechos reservados.`

### Footer

Texto:

### Soporte

Texto:

### WhatsApp soporte

Número:

### Email soporte

Correo:

---

## 23. Variables recomendadas

El sistema debería resolver el branding mediante configuración.

Ejemplo conceptual:

COMPANY_NAME
COMPANY_SHORT_NAME
COMPANY_SLOGAN

BRAND_PRIMARY
BRAND_SECONDARY
BRAND_ACCENT

BRAND_LOGO
BRAND_LOGO_LIGHT
BRAND_LOGO_DARK

BRAND_FAVICON

SUPPORT_EMAIL
SUPPORT_PHONE
SUPPORT_WHATSAPP

No significa que todas estas variables deban estar necesariamente en `.env`.

Preferir configuración persistente en base de datos cuando el administrador
deba poder modificarlas desde el sistema.

---

## 24. Administración del branding

Cuando corresponda, el panel administrativo deberá permitir modificar:

- nombre comercial;
- logo;
- favicon;
- colores;
- datos de contacto;
- eslogan;
- textos institucionales.

Los cambios visuales deberían aplicarse sin:

- modificar código;
- recompilar el proyecto;
- editar vistas;
- editar CSS manualmente;
- realizar un nuevo deploy.

---

## 25. Seguridad

Validar archivos cargados.

Permitir únicamente formatos autorizados.

Ejemplo:

Imágenes:

- PNG
- JPG
- JPEG
- WEBP
- SVG únicamente si existe sanitización segura.

Definir:

- tamaño máximo;
- dimensiones recomendadas;
- MIME permitido.

Nunca confiar únicamente en la extensión del archivo.

---

## 26. Rendimiento

Optimizar imágenes antes de mostrarlas.

Preferir:

- WEBP cuando corresponda;
- lazy loading;
- thumbnails;
- dimensiones apropiadas.

No cargar imágenes originales de varios MB en dashboards o listados.

---

## 27. Prohibiciones

Codex NO debe:

- hardcodear colores del cliente en múltiples componentes;
- hardcodear logos;
- duplicar layouts;
- crear CSS independiente por cada cliente;
- modificar reglas de negocio desde branding;
- crear forks del CORE por cambios visuales;
- reemplazar archivos CORE para personalizar un cliente.

---

## 28. Regla de implementación

Antes de realizar cambios visuales:

1. Leer este archivo.
2. Revisar la configuración existente.
3. Identificar si el CORE ya soporta la personalización.
4. Reutilizar componentes existentes.
5. Evitar CSS duplicado.
6. Mantener responsive.
7. Mantener accesibilidad.
8. Verificar desktop y móvil.

---

## 29. Principio fundamental

**El cliente puede cambiar completamente la apariencia del sistema sin
cambiar su funcionamiento.**

BRANDING modifica identidad visual.

BRANDING no modifica reglas de negocio.

BRANDING no modifica permisos.

BRANDING no modifica estados.

BRANDING no modifica la base de datos CORE.