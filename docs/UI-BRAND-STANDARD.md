# Estándar de identidad visual en interfaz

## Fuente única

El logo web se obtiene mediante `SystemSettingsService`. Las vistas no deben leer rutas de archivos ni construir URLs de almacenamiento por su cuenta. El componente Blade compartido es:

```blade
<x-brand-logo variant="public-header" />
```

El componente usa `logo_main` por defecto. En login se usa `logo_login` y se conserva `logo_main` como respaldo:

```blade
<x-brand-logo variant="login" :prefer-login="true" />
```

Si no existe una imagen válida en el disco público, se muestran las iniciales del nombre configurado con nombre accesible. Nunca se deja un espacio roto ni un icono de imagen ausente.

## Cómo cambiar el logo

El logo se cambia desde la configuración general del sistema, usando los campos de logo principal y logo de login ya existentes. Esa carga conserva el archivo bajo el disco público y guarda su ruta mediante el flujo actual; no se debe editar la base de datos ni reemplazar manualmente archivos en una vista.

Después del cambio, todas las ubicaciones que consumen el componente reciben automáticamente la nueva imagen. Para comprobarlo, revisar como mínimo sidebar, topbar móvil, login, portal público y página pública de una urbanización.

## Variantes admitidas

- `sidebar`: caja de 44 × 44 px.
- `topbar`: caja de 32 × 32 px dentro del botón móvil de 42 px.
- `login`: caja máxima de 180 × 82 px.
- `login-hero`: caja máxima de 180 × 100 px.
- `public-header`: caja de 132 × 52 px, reducida de forma controlada en móvil.
- `footer`: caja de 52 × 44 px.
- `pwa`: caja de 88 × 88 px.

Todas las imágenes usan `object-fit: contain`, conservan su proporción y quedan limitadas por ancho y alto. No se debe añadir `width`, `height`, `max-width`, `max-height` ni `style` inline en una vista consumidora.

## Uso con nombre y fuentes explícitas

Para mostrar el nombre junto al logo:

```blade
<x-brand-logo variant="footer" :show-name="true" />
```

`src` y `name` solo se usan cuando el contexto ya entrega una fuente segura, por ejemplo el icono estático de la página offline. No se debe pasar un nombre de archivo suministrado directamente por el usuario.

## Reglas para nuevas pantallas

1. Reutilizar `<x-brand-logo>`; no duplicar etiquetas `<img>` del branding.
2. Elegir la variante más cercana al contexto; no crear tamaños locales para corregir un caso aislado.
3. Mantener texto alternativo útil cuando la imagen comunica la marca. Si el nombre visible ya está al lado, el componente usa `alt=""` para evitar repetición.
4. Validar desktop y móvil con logos horizontales, verticales y de alta resolución.
5. No usar `!important` para encuadrar el logo.

## Reutilización en otro proyecto derivado

La integración reusable requiere conservar:

- `resources/views/components/brand-logo.blade.php`;
- el bloque «Identidad visual reutilizable» de `public/css/app.css`;
- `app/Services/SystemSettingsService.php` y la exposición global de `systemSettings` en `AppServiceProvider`;
- los campos de configuración `logo_main`, `logo_login`, `system_name` y `company_name`.

Al adaptar el branding solo se ajustan los valores de configuración y, si la UX lo exige, las medidas centralizadas de cada variante. Las vistas consumidoras y la lógica del componente no deben copiarse ni especializarse por nombre de cliente.

## Excepción PDF

Las plantillas PDF continúan usando la variable local `$logo`, normalmente una ruta local o un data URI preparado para DomPDF. No deben migrarse al componente web porque el motor PDF tiene una resolución de recursos diferente. Sus límites deben permanecer en el CSS específico del documento.
