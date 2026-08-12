# DEPLOYMENT.md

## Checklist específico — Hogar Inmobiliaria

Requisitos confirmados para esta implementación:

- PHP 8.3 o versión compatible con `composer.lock`, con PDO MySQL, Mbstring,
  XML, Ctype, JSON, Fileinfo, OpenSSL y GD; Composer instalado.
- Node.js solo es necesario para construir assets cuando cambie el frontend:
  `npm ci && npm run build`.
- Crear `.env` desde `.env.example`: `APP_ENV=production`, `APP_DEBUG=false`,
  `APP_URL=https://dominio`, credenciales MySQL limitadas, drivers de sesión,
  cache y cola definidos. Con HTTPS usar `SESSION_SECURE_COOKIE=true`.
- No ejecutar `DatabaseSeeder` en producción: contiene usuarios y datos demo
  conocidos. Crear usuarios reales mediante un procedimiento administrativo
  seguro; usar seeders de permisos individualmente solo tras revisarlos.
- Antes de migrar: backup verificado y `php artisan migrate:status`; después,
  `php artisan migrate --force`. Nunca `migrate:fresh` ni `db:wipe`.
- Ejecutar `php artisan storage:link` para logos y QR institucionales públicos.
  `storage/` y `bootstrap/cache/` deben ser escribibles por el usuario web, sin
  usar permisos globales `777`.
- Secuencia de caches validada: `php artisan optimize:clear`, `config:cache`,
  `route:cache` y `view:cache`. En desarrollo limpiar después con
  `route:clear`, `config:clear` y `view:clear`.
- No hay jobs programados ni workers específicos obligatorios actualmente.
  No desplegar Supervisor/cron por suposición; configurarlos si se activa cola
  o scheduler en una fase posterior.
- El backup manual `php artisan impacto:backup` escribe en
  `storage/app/backups`, fuera de `public`; los dumps SQL están ignorados por
  Git. Definir copia externa, retención y prueba periódica de restauración.
- Detrás de proxy inverso verificar detección HTTPS antes de ajustar proxies
  confiables. No confiar indiscriminadamente en todos los proxies.
- Smoke test posterior: login y cambio obligatorio; dashboard; clientes;
  ventas; `/cobranza`; caja; `/reportes/gerencia`; configuración financiera en
  lectura/escritura según rol; portal cliente, PDF y recibos propios.
- Completar `clients/hogar-inmobiliaria/ACCEPTANCE_CHECKLIST.md` en staging.

# DESPLIEGUE BASE — PLATAFORMA TERRENOS

## 1. OBJETIVO

Este documento define el procedimiento estándar para desplegar Terrenos y sus versiones personalizadas en servidores Linux.

Debe permitir despliegues:

* Repetibles
* Seguros
* Auditables
* Reversibles
* Consistentes entre clientes

El procedimiento base está pensado para:

* Ubuntu Server
* Nginx
* PHP
* Laravel
* MySQL
* Git
* Supervisor o systemd
* HTTPS

---

# 2. PRINCIPIO

Nunca desplegar cambios manualmente sin saber:

* Qué versión se está publicando.
* Qué migraciones incluye.
* Qué variables de entorno necesita.
* Qué servicios deben reiniciarse.
* Cómo volver a la versión anterior.

Todo despliegue debe partir de un estado conocido de Git.

---

# 3. ENTORNOS

Mantener separados:

```text
LOCAL
STAGING
PRODUCTION
```

## LOCAL

Desarrollo.

Puede utilizar:

* Laragon
* Docker
* PHP local
* MySQL local

## STAGING

Entorno opcional de pruebas antes de producción.

Debe parecerse lo máximo posible a producción.

## PRODUCTION

Entorno utilizado por clientes reales.

No realizar experimentos directamente aquí.

---

# 4. SERVIDOR RECOMENDADO

Base:

```text
Ubuntu 24.04 LTS
Nginx
PHP compatible con la versión Laravel
MySQL 8+
Composer
Node.js
Git
Supervisor
Certbot o SSL administrado externamente
```

Las versiones exactas deben verificarse contra el proyecto real antes de instalar.

---

# 5. USUARIO DEL SERVIDOR

Evitar trabajar permanentemente como:

```text
root
```

Crear o utilizar un usuario administrativo.

Ejemplo:

```text
deploy
```

o:

```text
ubuntu
```

Utilizar `sudo` únicamente cuando sea necesario.

---

# 6. UBICACIÓN DEL PROYECTO

Ruta recomendada:

```text
/var/www/terrenos
```

Para múltiples clientes:

```text
/var/www/cliente-a
/var/www/cliente-b
```

o nombres específicos del proyecto.

---

# 7. REPOSITORIO

Cada implementación debe tener un repositorio Git claramente identificado.

Ejemplo:

```text
github.com/empresa/terrenos-cliente-a
```

El servidor debe desplegar desde una rama conocida.

Producción normalmente:

```text
main
```

No desplegar ramas experimentales directamente en producción.

---

# 8. CLONACIÓN INICIAL

Ejemplo:

```bash
cd /var/www
git clone REPOSITORY_URL terrenos
cd terrenos
```

Comprobar:

```bash
git status
git branch
git log --oneline -5
```

---

# 9. VARIABLES DE ENTORNO

Crear:

```text
.env
```

a partir de:

```text
.env.example
```

Nunca subir `.env` al repositorio.

Variables mínimas:

```text
APP_NAME=
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=

LOG_CHANNEL=

DB_CONNECTION=mysql
DB_HOST=
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

CACHE_STORE=
SESSION_DRIVER=
QUEUE_CONNECTION=
```

---

# 10. APP_DEBUG

Producción siempre:

```text
APP_DEBUG=false
```

No mostrar stack traces ni secretos al usuario final.

---

# 11. APP_KEY

Si no existe:

```bash
php artisan key:generate
```

IMPORTANTE:

No regenerar `APP_KEY` de una aplicación existente sin evaluar consecuencias.

Puede afectar:

* sesiones,
* datos cifrados,
* cookies,
* información protegida.

---

# 12. BASE DE DATOS

Crear:

```text
database
user
password
```

con privilegios limitados a la base correspondiente.

No utilizar `root` de MySQL como usuario normal de Laravel.

---

# 13. INSTALACIÓN PHP

Instalar versión compatible.

Extensiones típicas:

```text
php-cli
php-fpm
php-mysql
php-mbstring
php-xml
php-curl
php-zip
php-bcmath
php-intl
php-gd
```

Verificar:

```bash
php -v
php -m
```

---

# 14. COMPOSER

Instalar dependencias:

```bash
composer install \
  --no-dev \
  --prefer-dist \
  --optimize-autoloader \
  --no-interaction
```

No ejecutar:

```bash
composer update
```

automáticamente en producción.

Producción debe respetar:

```text
composer.lock
```

---

# 15. NODE

Si el proyecto compila frontend:

```bash
npm ci
npm run build
```

Preferir:

```bash
npm ci
```

sobre:

```bash
npm install
```

cuando existe `package-lock.json`.

---

# 16. PERMISOS DE ARCHIVOS

Laravel necesita escritura normalmente en:

```text
storage/
bootstrap/cache/
```

Ejemplo:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

No utilizar:

```bash
chmod -R 777
```

como solución permanente.

---

# 17. STORAGE LINK

Cuando sea necesario:

```bash
php artisan storage:link
```

Verificar que los documentos privados no dependan de este enlace público.

---

# 18. MIGRACIONES

Antes de ejecutar:

```bash
php artisan migrate --force
```

revisar:

```bash
php artisan migrate:status
```

y las migraciones nuevas.

Nunca modificar migraciones ya ejecutadas en producción.

---

# 19. BACKUP ANTES DE MIGRACIONES IMPORTANTES

Antes de cambios destructivos:

Crear backup de:

* Base de datos.
* Archivos importantes.
* Configuración relevante.

Ejemplo conceptual:

```bash
mysqldump ...
```

Guardar el backup fuera del directorio público.

---

# 20. MODO MANTENIMIENTO

Para despliegues que puedan dejar temporalmente código y base incompatibles:

```bash
php artisan down
```

Después:

```bash
php artisan up
```

No todos los despliegues necesitan mantenimiento.

---

# 21. SECUENCIA BASE DE DESPLIEGUE

Proceso recomendado:

```text
1. Revisar estado actual.
2. Crear backup si corresponde.
3. Activar mantenimiento si corresponde.
4. Obtener nueva versión.
5. Instalar dependencias.
6. Compilar frontend.
7. Ejecutar migraciones.
8. Limpiar/generar caches.
9. Reiniciar workers.
10. Verificar aplicación.
11. Desactivar mantenimiento.
```

---

# 22. ACTUALIZACIÓN DESDE GIT

Antes:

```bash
cd /var/www/terrenos
git status
```

No hacer `git pull` si existen cambios locales no identificados.

Comprobar rama:

```bash
git branch --show-current
```

Actualizar:

```bash
git pull --ff-only origin main
```

Preferir `--ff-only` para evitar merges inesperados en producción.

---

# 23. NO SOBRESCRIBIR CAMBIOS LOCALES

Si:

```bash
git status
```

muestra modificaciones:

DETENER EL DESPLIEGUE.

Investigar primero.

No utilizar automáticamente:

```bash
git reset --hard
git clean -fd
```

---

# 24. CACHES LARAVEL

Después de actualizar:

```bash
php artisan optimize:clear
```

Después puede utilizarse:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

o:

```bash
php artisan optimize
```

según versión de Laravel.

---

# 25. CONFIG CACHE

Cuando se utiliza:

```bash
php artisan config:cache
```

el código no debe depender de llamar `env()` directamente fuera de archivos de configuración.

---

# 26. QUEUES

Si utiliza colas:

```text
QUEUE_CONNECTION=database
```

o Redis.

Workers deben mantenerse funcionando.

Ejemplo:

```bash
php artisan queue:work
```

En producción utilizar un administrador de procesos.

---

# 27. SUPERVISOR

Ejemplo conceptual:

```ini
[program:terrenos-worker]
command=php /var/www/terrenos/artisan queue:work --sleep=3 --tries=3
directory=/var/www/terrenos
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/terrenos/storage/logs/worker.log
```

La configuración real debe adaptarse al servidor.

---

# 28. REINICIAR WORKERS

Después del despliegue:

```bash
php artisan queue:restart
```

Esto permite que los workers carguen el nuevo código.

---

# 29. SCHEDULER

Configurar:

```bash
php artisan schedule:run
```

cada minuto mediante cron.

Ejemplo:

```text
* * * * * cd /var/www/terrenos && php artisan schedule:run >> /dev/null 2>&1
```

El usuario del cron debe tener permisos adecuados.

---

# 30. RESERVAS AUTOMÁTICAS

El Scheduler podrá utilizarse para:

* Vencer reservas.
* Liberar lotes.
* Actualizar cuotas vencidas.
* Generar notificaciones.
* Ejecutar mantenimientos.

Estas operaciones deben ser idempotentes.

---

# 31. NGINX

Configuración conceptual:

```nginx
server {
    listen 80;
    server_name ejemplo.com www.ejemplo.com;

    root /var/www/terrenos/public;

    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php-fpm.sock;

        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

La ruta real del socket PHP depende de la versión instalada.

---

# 32. VALIDAR NGINX

Antes de reiniciar:

```bash
sudo nginx -t
```

Solo si responde correctamente:

```bash
sudo systemctl reload nginx
```

Preferir `reload` sobre `restart` cuando sea suficiente.

---

# 33. HTTPS

Producción debe utilizar HTTPS.

Opciones:

* Let's Encrypt.
* Cloudflare.
* Certificado del proveedor.
* Load Balancer AWS.

No transmitir:

* login,
* tokens,
* documentos,
* pagos

por HTTP sin cifrar.

---

# 34. LET'S ENCRYPT

Si se utiliza Certbot:

```bash
sudo certbot --nginx -d ejemplo.com -d www.ejemplo.com
```

Verificar renovación automática.

---

# 35. CLOUDFLARE

Si se utiliza Cloudflare:

Evitar configuraciones de SSL incompatibles.

Preferir:

```text
Full (strict)
```

cuando el servidor tiene certificado válido.

No utilizar Flexible SSL para una aplicación Laravel sensible salvo una razón excepcional.

---

# 36. TRUSTED PROXIES

Si Laravel está detrás de:

* Cloudflare,
* Load Balancer,
* proxy inverso,

configurar correctamente proxies confiables.

Esto afecta:

* HTTPS detection.
* IP del cliente.
* generación de URLs.

---

# 37. APP_URL

Debe coincidir con producción:

```text
APP_URL=https://ejemplo.com
```

Especialmente importante para:

* links,
* emails,
* archivos,
* redirects.

---

# 38. BASE URL DE API

Para móvil:

```text
https://ejemplo.com/api/v1/
```

No publicar la app Android con una IP temporal si existe un dominio definitivo.

---

# 39. FIREWALL

Abrir únicamente servicios necesarios.

Normalmente:

```text
22 SSH
80 HTTP
443 HTTPS
```

MySQL no debe estar expuesto públicamente salvo necesidad justificada.

---

# 40. SSH

Utilizar preferentemente:

* claves SSH,
* usuarios limitados,
* bloqueo de acceso root directo cuando sea viable.

No compartir una misma llave privada entre múltiples personas sin control.

---

# 41. MYSQL

Recomendado:

MySQL accesible solo desde:

```text
localhost
```

cuando Laravel y MySQL están en el mismo servidor.

---

# 42. ARCHIVOS PRIVADOS

Documentos como:

* CI,
* contratos,
* comprobantes,
* documentos legales,

no deben exponerse directamente desde `/public`.

Utilizar almacenamiento privado.

---

# 43. S3

Si una versión utiliza AWS S3:

Configurar mediante:

```text
FILESYSTEM_DISK=s3
```

Credenciales únicamente en `.env` o mecanismos seguros de AWS.

Preferir IAM Roles en servidores AWS cuando sea posible.

---

# 44. LOGS

Laravel:

```text
storage/logs/
```

Servidor:

```text
/var/log/nginx/
```

PHP-FPM tendrá sus propios logs según distribución.

En caso de error revisar:

1. Laravel.
2. Nginx.
3. PHP-FPM.
4. Queue worker.

---

# 45. LOG ROTATION

Evitar que logs crezcan indefinidamente.

Configurar:

* Laravel daily logs,
* logrotate,
* retención adecuada.

---

# 46. MONITOREO

En producción debería comprobarse:

* CPU.
* RAM.
* Disco.
* Base de datos.
* HTTP.
* Workers.
* Cron/Scheduler.
* Certificado SSL.
* Espacio de backups.

---

# 47. ESPACIO EN DISCO

Prestar especial atención a:

```text
storage/
logs/
backups/
uploads/
```

Los comprobantes e imágenes pueden aumentar rápidamente el almacenamiento.

---

# 48. BACKUPS

Debe existir backup periódico de:

## Base de datos

Como mínimo diario para sistemas activos.

## Documentos

Según criticidad.

## Configuración

Guardar copia segura de configuraciones necesarias.

---

# 49. RETENCIÓN

Ejemplo:

```text
7 backups diarios
4 semanales
3 mensuales
```

La política real debe ajustarse al cliente.

---

# 50. BACKUP NO ES BACKUP SI NO SE PRUEBA

Periódicamente verificar que pueda restaurarse.

No asumir que un archivo `.sql` válido garantiza una restauración completa.

---

# 51. DEPLOYMENT CHECKLIST

Antes:

```text
[ ] Rama correcta
[ ] Git limpio
[ ] Backup si corresponde
[ ] Migraciones revisadas
[ ] Variables nuevas identificadas
[ ] Dependencias revisadas
[ ] Tests aprobados
```

Durante:

```text
[ ] Código actualizado
[ ] Composer instalado
[ ] Frontend compilado
[ ] Migraciones ejecutadas
[ ] Caches actualizadas
[ ] Workers reiniciados
```

Después:

```text
[ ] Login funciona
[ ] Dashboard funciona
[ ] Lotes cargan
[ ] Reserva funciona
[ ] Venta funciona
[ ] Pagos funcionan
[ ] API responde
[ ] Logs sin errores nuevos
```

---

# 52. SMOKE TEST

Después de cada despliegue probar mínimo:

```text
GET /
GET /login
```

Luego:

* autenticación,
* dashboard,
* módulo principal.

Si existe API:

```text
GET /api/v1/...
```

o endpoint de health check.

---

# 53. HEALTH CHECK

Recomendado crear:

```text
GET /health
```

o:

```text
GET /api/health
```

Debe comprobar como mínimo que Laravel responde.

Puede ampliarse posteriormente para:

* DB,
* cache,
* queue.

No exponer información sensible.

---

# 54. ROLLBACK DE CÓDIGO

Antes de despliegue registrar:

```bash
git rev-parse HEAD
```

Ejemplo:

```text
OLD_COMMIT=abc123
```

Si el nuevo código falla y no existen cambios irreversibles de base:

volver a commit conocido.

---

# 55. CUIDADO CON ROLLBACK DE MIGRACIONES

No ejecutar automáticamente:

```bash
php artisan migrate:rollback
```

en producción.

Una migración puede haber transformado datos.

El rollback debe analizarse individualmente.

---

# 56. ESTRATEGIA DE ROLLBACK

Separar:

## Código

Puede volver a commit anterior.

## Base de datos

Puede requerir:

* migración correctiva,
* restauración backup,
* intervención manual.

No asumir que ambos pueden revertirse con un único comando.

---

# 57. MIGRACIONES DESTRUCTIVAS

Evitar en un solo despliegue:

```text
renombrar/eliminar columna
+
publicar código que depende inmediatamente del cambio
```

Para cambios delicados utilizar despliegues por etapas.

Ejemplo:

1. Agregar nueva columna.
2. Publicar código compatible.
3. Migrar información.
4. Comprobar.
5. Eliminar columna antigua en despliegue posterior.

---

# 58. ZERO-DOWNTIME

No es obligatorio inicialmente.

Si el sistema crece puede utilizarse:

* Laravel Envoyer.
* Releases con symlink.
* Blue/Green deployment.
* Containers.

No añadir esta complejidad antes de necesitarla.

---

# 59. MULTIEMPRESA

Si varias empresas comparten instalación:

Un despliegue puede afectar a todas.

Por ello:

* Tests de aislamiento obligatorios.
* Migraciones compatibles.
* Feature flags cuando corresponda.
* No publicar personalización que modifique CORE para todos accidentalmente.

---

# 60. INSTALACIONES INDEPENDIENTES

Si cada cliente tiene servidor independiente:

Ventajas:

* aislamiento,
* personalización,
* menor riesgo cruzado.

Desventajas:

* más servidores,
* más mantenimiento,
* múltiples despliegues.

Esta decisión se tomará según modelo comercial.

---

# 61. CONFIGURACIÓN DE CLIENTE

Después del despliegue inicial:

Configurar:

```text
Empresa
Logo
Colores
Dominio
Moneda
Reservas
Descuentos
Cuotas
Interés
Comisiones
Métodos de pago
```

No editar código para cambios que pertenecen a configuración.

---

# 62. SEEDERS

Ejecutar seeders de producción únicamente si están diseñados para ello.

Ejemplo:

```bash
php artisan db:seed --class=RolePermissionSeeder --force
```

No ejecutar un `DemoSeeder` en producción.

---

# 63. SUPER ADMIN

Después del primer despliegue:

Crear usuario administrativo mediante mecanismo seguro.

No dejar credenciales demo como:

```text
admin@example.com
admin123
```

---

# 64. CONTRASEÑAS

No documentar contraseñas reales dentro de:

```text
DEPLOYMENT.md
README.md
GitHub
```

Utilizar gestor de secretos apropiado.

---

# 65. API KEYS

Las integraciones como:

* WhatsApp,
* Google Maps,
* IA,
* AWS,

deben tener credenciales independientes por ambiente cuando sea posible.

---

# 66. ACTUALIZACIONES DE PHP

No actualizar la versión principal de PHP durante un despliegue normal.

Tratarlo como cambio de infraestructura separado.

Probar compatibilidad previamente.

---

# 67. ACTUALIZACIONES DE LARAVEL

No mezclar:

```text
actualización mayor de Laravel
+
nueva funcionalidad
+
migraciones financieras
```

en un solo despliegue.

Separar riesgos.

---

# 68. AWS EC2

Si se utiliza AWS EC2:

Documentar por cliente:

```text
Region
Instance ID
Instance Type
Elastic IP
Security Group
EBS Volume
Domain
Backup Strategy
```

No guardar secretos AWS en este documento.

---

# 69. ELASTIC IP

En servidores productivos que necesiten IP fija:

Utilizar Elastic IP o dominio apuntando al mecanismo estable correspondiente.

Evitar depender de IP pública dinámica.

---

# 70. EBS

El volumen debe dimensionarse considerando:

* proyecto,
* sistema operativo,
* logs,
* uploads,
* backups locales.

Los documentos deberían migrarse a S3 si el volumen crece demasiado.

---

# 71. SECURITY GROUP AWS

Permitir solo lo necesario.

Ejemplo:

```text
22 -> IP administrativa cuando sea posible
80 -> público
443 -> público
3306 -> NO público
```

---

# 72. REBOOT DEL SERVIDOR

Después de reiniciar verificar automáticamente o manualmente:

* Nginx.
* PHP-FPM.
* MySQL.
* Supervisor.
* Workers.
* Scheduler.

Todos deberían configurarse para iniciar con el sistema cuando corresponda.

---

# 73. CHECK POST-REBOOT

```bash
sudo systemctl status nginx
sudo systemctl status php*-fpm
sudo systemctl status mysql
sudo supervisorctl status
```

Adaptar nombres a la distribución.

---

# 74. GIT DEPLOYMENT RULE

El servidor de producción no debe convertirse en entorno de desarrollo.

No editar archivos PHP manualmente en producción salvo emergencia extrema.

La corrección normal debe seguir:

```text
LOCAL
→ GIT
→ TEST
→ PRODUCTION
```

---

# 75. HOTFIX

En emergencia:

1. Identificar problema.
2. Corregir en rama hotfix.
3. Probar.
4. Commit.
5. Push.
6. Desplegar commit específico.
7. Verificar.
8. Registrar en CURRENT_STATUS.

No corregir únicamente en el servidor y olvidar llevarlo a Git.

---

# 76. CURRENT_STATUS

Después de un despliegue importante actualizar:

```text
docs/CURRENT_STATUS.md
```

Registrar:

```text
Fecha
Versión
Commit
Servidor
Migraciones
Resultado
Incidencias
```

No registrar contraseñas ni secretos.

---

# 77. VERSIONADO

Puede utilizarse:

```text
v1.0.0
v1.1.0
v1.1.1
```

Recomendado cuando existan múltiples clientes y releases.

Ejemplo:

```text
v1.0.0
Base comercial inicial

v1.1.0
Nuevo módulo de comisiones

v1.1.1
Corrección de pagos
```

---

# 78. TAGS GIT

Los releases estables pueden marcarse:

```bash
git tag v1.1.0
```

Esto facilita identificar exactamente qué versión está instalada.

---

# 79. REGLA PARA AGENTES DE IA

Antes de tocar producción:

1. Leer `AGENTS.md`.
2. Leer `CURRENT_STATUS.md`.
3. Leer `DEPLOYMENT.md`.
4. Identificar servidor y entorno.
5. Verificar rama.
6. Verificar Git.
7. No eliminar cambios locales.
8. Revisar migraciones.
9. Revisar variables `.env` nuevas.
10. Crear backup cuando corresponda.
11. Desplegar cambios mínimos.
12. Ejecutar smoke tests.
13. Revisar logs.
14. Actualizar `CURRENT_STATUS.md`.

---

# 80. PROHIBICIONES PARA AGENTES

Nunca ejecutar automáticamente en producción:

```bash
git reset --hard
git clean -fd
rm -rf
DROP DATABASE
php artisan migrate:fresh
php artisan db:wipe
```

sin instrucción explícita y evaluación del impacto.

No reemplazar `.env` completo durante una actualización rutinaria.

---

# 81. AHORRO DE TOKENS EN DEPLOYMENT

Para una actualización normal, el agente debe leer:

```text
AGENTS.md
CURRENT_STATUS.md
DEPLOYMENT.md
```

Después únicamente:

* cambios Git involucrados,
* migraciones nuevas,
* configuración relacionada.

No necesita analizar nuevamente todo Laravel para desplegar un commit ya probado.

---

# 82. FICHA DE SERVIDOR POR CLIENTE

Cada implementación debe registrar información no sensible:

```text
Cliente:
Proyecto:
Dominio:
Servidor:
Proveedor:
Sistema operativo:
Ruta aplicación:
Rama producción:
PHP:
MySQL:
Queue:
Storage:
SSL:
Backup:
```

Nunca incluir:

* passwords,
* private keys,
* tokens,
* API secrets.

---

# 83. PLANTILLA DE DESPLIEGUE

## Release

Fecha:

Cliente:

Versión:

Commit:

Responsable:

### Cambios

*

### Migraciones

*

### Variables nuevas

*

### Backup

Sí / No

### Tests previos

*

### Resultado

*

### Problemas

*

### Rollback necesario

Sí / No

### Próximo paso

*

---

# 84. PRINCIPIO FINAL

Un despliegue correcto no significa solamente:

**“la página abrió”.**

Debe mantenerse:

```text
CÓDIGO
+
BASE DE DATOS
+
WORKERS
+
SCHEDULER
+
CONFIGURACIÓN
+
SEGURIDAD
+
BACKUPS
+
TRAZABILIDAD
```

El objetivo es poder desplegar Terrenos para una nueva empresa de forma repetible sin depender de recordar manualmente cómo se configuró el cliente anterior.
