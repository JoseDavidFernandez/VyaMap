# VyaMap — Despliegue en un VPS

Guía para publicar VyaMap en un VPS Linux autoadministrado (sin Plesk ni cPanel), en **`https://vyamap.josedavidfernandezcomino.com`**, con registro propio y limitado y sin subida de fotos.

**No describe un despliegue ya hecho:** todavía no hay VPS. Está escrita para **Ubuntu Server 24.04 LTS**; con otra distribución cambian los nombres de los paquetes y algunas rutas.

**El registro público no se abre hasta tener correo** (recuperación de contraseña y verificación del correo, sección 7.3): mientras tanto, `REGISTRATION_ENABLED=false` y las cuentas se crean con `users:create`.

## 1. Qué necesita la aplicación

| Requisito | Detalle |
|---|---|
| PHP | 8.3 o superior, con `gd`, `exif`, `mbstring`, `pdo_mysql`, `fileinfo`, `intl`, `xml`, `curl`, `zip` y `bcmath` |
| Composer | `composer install --no-dev --optimize-autoloader`. Sin `--no-dev` se instalaría Ignition. |
| Node.js | Solo para compilar los assets (`npm ci && npm run build`); en producción no se ejecuta. |
| MySQL | 8.x, en una base nueva y exclusiva. Nunca se importan `vyamap`, `vyamap_manual` ni copias de ellas. |
| Disco | `storage/app` guarda los avatares (`storage/app/public`, servidos con `php artisan storage:link`) y, si se activan, las fotos privadas (`storage/app/private`). Debe sobrevivir a los despliegues: ver la estructura de carpetas (4.1). |
| Sesiones y caché | `file`. El proyecto no tiene tablas `sessions` ni `cache`, así que `database` no funciona. Laravel lee `CACHE_DRIVER`, no `CACHE_STORE`. |
| HTTPS | Lo termina Nginx en el propio VPS, con Let's Encrypt. Como no hay otro proxy delante, **`TRUSTED_PROXIES` no se define**. Solo haría falta con un proxy o CDN delante (por ejemplo, Cloudflare en modo proxy), y entonces con sus rangos de IP, nunca `*`. |
| Cola | `QUEUE_CONNECTION=sync`: no hace falta worker. Si se activan las fotos, se procesan durante la subida. |
| Tareas programadas | La aplicación no necesita cron. El servidor sí, para las copias de seguridad (sección 9). |
| Servicios externos | Teselas de OpenStreetMap (sin clave). Geoapify para buscar ciudades fuera del catálogo (opcional, `GEOAPIFY_API_KEY`). Have I Been Pwned al crear contraseñas (si no responde, no bloquea). Correo transaccional para recuperar contraseñas y verificar correos: con `MAIL_MAILER=log` no se envía nada (ver 7.3). |

## 2. Arquitectura

```
Internet ──443──▶ Nginx ──socket unix──▶ PHP-FPM (pool «vyamap», usuario vyamap) ──127.0.0.1:3306──▶ MySQL
            80 ─▶ redirección a HTTPS         │
                                              └─▶ /var/www/vyamap/shared/storage (único sitio donde escribe PHP)
```

Hay tres usuarios del sistema y cada uno tiene el mínimo acceso necesario:

| Usuario | Para qué | Puede escribir en |
|---|---|---|
| `deploy` | Entrar por SSH, subir versiones y ejecutar Composer | El código (`releases/`) |
| `vyamap` | Ejecutar PHP-FPM y `php artisan` | `shared/storage` y el `bootstrap/cache` de cada versión |
| `www-data` | Nginx: servir los archivos estáticos | Nada de la aplicación |

Si alguien comprometiera PHP, no podría modificar el código ni leer las claves SSH de `deploy`.

MySQL solo escucha en `127.0.0.1`.

## 3. Antes de empezar (lo haces tú)

1. **Contratar el VPS** con Ubuntu Server 24.04 LTS. Basta con 1 vCPU, 2 GB de RAM y 20 GB de disco. Con fotos activadas harán falta más disco y más memoria.
2. **DNS** en el proveedor de `josedavidfernandezcomino.com`: un registro `A` de `vyamap` a la IP del VPS (y `AAAA` si tiene IPv6). Pon un TTL bajo (300 s) durante la puesta en marcha. Si el DNS pasa por Cloudflare, déjalo en «DNS only» (nube gris).
3. **Claves nuevas**, exclusivas de producción y que nunca se reutilizan:
   - `APP_KEY`, que se genera en el servidor (5.3);
   - la contraseña de MySQL;
   - opcionalmente, una clave de Geoapify.

## 4. Preparar el servidor

Como root, la primera vez, y después con `sudo` desde `deploy`.

### 4.1 Usuarios, SSH y cortafuegos

```bash
adduser deploy && usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh && cp ~/.ssh/authorized_keys /home/deploy/.ssh/   # tu clave pública
chown -R deploy:deploy /home/deploy/.ssh && chmod 700 /home/deploy/.ssh && chmod 600 /home/deploy/.ssh/authorized_keys

adduser --system --group --home /var/www/vyamap --no-create-home vyamap
```

En `/etc/ssh/sshd_config.d/10-hardening.conf`:

```
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
```

Antes de cerrar la sesión de root, comprueba que entras como `deploy` con tu clave. Después:

```bash
systemctl reload ssh

apt update && apt -y full-upgrade
apt -y install ufw fail2ban unattended-upgrades
ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw enable    # 'Nginx Full' existe después de instalar Nginx (4.2)
dpkg-reconfigure -plow unattended-upgrades                    # actualizaciones de seguridad automáticas
```

### 4.2 Paquetes

```bash
apt -y install nginx mysql-server composer unzip git \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-gd php8.3-intl php8.3-zip php8.3-bcmath
apt -y install certbot python3-certbot-nginx
php -m | grep -Ei 'exif|fileinfo|gd|pdo_mysql'   # exif y fileinfo vienen en php8.3-common
```

**Node.js**, dos opciones:

- **A (recomendada): compilar los assets en tu equipo** y subir `public/build`. El servidor no necesita Node y tiene menos software expuesto.
- **B: compilar en el servidor.** Hace falta Node 20 o 22 LTS, que se instala desde el repositorio de NodeSource. El `nodejs` de Ubuntu 24.04 es la versión 18, que ya no recibe soporte.

### 4.3 MySQL

```bash
mysql_secure_installation
mysql
```

```sql
CREATE DATABASE vyamap_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'vyamap_prod'@'localhost' IDENTIFIED BY '<contraseña nueva>';
GRANT ALL PRIVILEGES ON vyamap_prod.* TO 'vyamap_prod'@'localhost';

-- Usuario de solo lectura para las copias de seguridad (sección 9)
CREATE USER 'vyamap_backup'@'localhost' IDENTIFIED BY '<otra contraseña nueva>';
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON vyamap_prod.* TO 'vyamap_backup'@'localhost';
```

Comprueba que MySQL solo escucha en local: `ss -ltnp | grep 3306` debe mostrar `127.0.0.1:3306` (o `::1`), nunca `0.0.0.0`.

### 4.4 Estructura de carpetas

Cada despliegue se sube a una carpeta nueva en `releases/`. `current` apunta a la versión activa, y lo que debe sobrevivir entre versiones está en `shared/`.

```
/var/www/vyamap/
├── current -> releases/20261101-1830
├── releases/
│   └── 20261101-1830/      código de esa versión (deploy)
│       ├── .env -> ../../shared/.env
│       └── storage -> ../../shared/storage
└── shared/
    ├── .env                640 deploy:vyamap
    └── storage/            vyamap
```

```bash
mkdir -p /var/www/vyamap/{releases,shared}
mkdir -p /var/www/vyamap/shared/storage/{app/public,app/private,app/data,framework/{cache/data,sessions,views},logs}
chown deploy:deploy /var/www/vyamap /var/www/vyamap/releases /var/www/vyamap/shared
chown -R vyamap:vyamap /var/www/vyamap/shared/storage
chmod -R u=rwX,g=rX,o= /var/www/vyamap/shared/storage
chmod o+x /var/www/vyamap/shared/storage /var/www/vyamap/shared/storage/app
chmod -R o+rX /var/www/vyamap/shared/storage/app/public   # Nginx sirve los avatares
```

`storage/app/private` y el resto de `storage` quedan fuera del alcance de Nginx.

### 4.5 PHP-FPM: pool propio

`/etc/php/8.3/fpm/pool.d/vyamap.conf`:

```ini
[vyamap]
user = vyamap
group = vyamap
listen = /run/php/vyamap.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = ondemand
pm.max_children = 5
pm.process_idle_timeout = 30s

php_admin_flag[display_errors] = Off
php_admin_value[memory_limit] = 256M
php_admin_value[upload_max_filesize] = 6M
php_admin_value[post_max_size] = 8M
```

`upload_max_filesize` cubre el avatar (5 MB como máximo). Si se activan las fotos, hay que revisarlo junto a `client_max_body_size` (sección 6).

En `/etc/php/8.3/cli/php.ini` y `/etc/php/8.3/fpm/php.ini` deja `expose_php = Off`. Desactiva el pool por defecto si no lo usa nada: `mv www.conf www.conf.disabled`.

```bash
systemctl restart php8.3-fpm
```

## 5. Primer despliegue

### 5.1 Subir el código (desde tu equipo)

Mientras no se pueda usar GitHub (ticket #4853797), se sube desde la copia local con `git archive`. Así solo viaja lo que está en el commit: nunca `.env*`, `vendor`, `node_modules` ni `storage`.

```bash
RELEASE=$(date +%Y%m%d-%H%M)
HOST=deploy@vyamap.josedavidfernandezcomino.com

git status --short                 # debe estar vacío: lo que no esté en el commit no se sube
ssh $HOST "mkdir /var/www/vyamap/releases/$RELEASE"
git archive --format=tar HEAD | ssh $HOST "tar -x -C /var/www/vyamap/releases/$RELEASE"

# Opción A de Node: assets compilados en local
npm ci && npm run build
tar -c public/build | ssh $HOST "tar -x -C /var/www/vyamap/releases/$RELEASE"
```

### 5.2 Preparar la versión (en el servidor, como `deploy`)

```bash
cd /var/www/vyamap/releases/$RELEASE
rm -rf storage && ln -s ../../shared/storage storage
ln -s ../../shared/.env .env
ln -s ../storage/app/public public/storage        # lo que hace storage:link, con una ruta relativa
composer install --no-dev --optimize-autoloader --no-interaction
# Opción B de Node: npm ci && npm run build && rm -rf node_modules
test ! -d vendor/spatie/laravel-ignition && echo "sin dependencias de desarrollo"
sudo chown -R vyamap:vyamap bootstrap/cache
```

### 5.3 `.env` de producción (solo la primera vez)

Créalo en `/var/www/vyamap/shared/.env` (permisos `640`, `deploy:vyamap`). No se copia de ningún otro entorno.

```
APP_NAME=VyaMap
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://vyamap.josedavidfernandezcomino.com
APP_USER_TIMEZONE=Europe/Madrid

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vyamap_prod
DB_USERNAME=vyamap_prod
DB_PASSWORD=<la de 4.3>

SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true
CACHE_DRIVER=file
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
MAIL_MAILER=log

REGISTRATION_ENABLED=false      # true solo cuando el correo funcione (7.3)
REGISTRATION_MAX_USERS=100
UPLOADS_ENABLED=false
EMAIL_VERIFICATION=true         # es el valor por defecto fuera de local
# GEOAPIFY_API_KEY=              opcional, clave nueva
```

Notas:

- `APP_KEY` se genera en el propio servidor con `php artisan key:generate --show`, desde la carpeta de la versión, y se pega en el archivo. Se hace así porque `vyamap` solo puede leer `.env`, no escribirlo.
- **No definas** `TRUSTED_PROXIES` (sección 1) ni `INERTIA_DEVTOOLS_ENABLED`.
- Con `LOG_CHANNEL=daily`, los registros rotan solos y se guardan 14 días.

### 5.4 Base de datos y catálogo (solo la primera vez)

Todos los comandos `artisan` se ejecutan como `vyamap`, para que lo que creen en `storage` tenga el propietario correcto.

```bash
cd /var/www/vyamap/releases/$RELEASE
A="sudo -u vyamap php artisan"

$A migrate --force

sudo -u vyamap cp database/data/catalog/*.csv storage/app/data/
$A countries:import --apply
$A cities:import
$A app:import-airports
$A airports:map-cities --apply
$A airports:enrich-coordinates --apply
$A airports:import-timezones --apply
```

### 5.5 Activar la versión

```bash
A="sudo -u vyamap php artisan"
$A migrate --force                       # en las actualizaciones
$A config:cache && $A route:cache && $A view:cache
ln -sfn releases/$RELEASE /var/www/vyamap/current
sudo systemctl reload php8.3-fpm         # vacía OPcache
```

Las cachés se generan antes de cambiar `current`. Como `config:cache` guarda rutas absolutas, se generan desde la carpeta de la versión.

## 6. Nginx y HTTPS

`/etc/nginx/sites-available/vyamap`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name vyamap.josedavidfernandezcomino.com;

    root /var/www/vyamap/current/public;
    index index.php;
    charset utf-8;

    client_max_body_size 8m;
    server_tokens off;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Permissions-Policy "camera=(), microphone=(), geolocation=()" always;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /index.php {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/vyamap.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT $realpath_root;
    }

    # Ningún otro PHP es ejecutable, y nada que empiece por punto es accesible
    location ~ \.php$ { return 404; }
    location ~ /\.(?!well-known) { deny all; }

    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
}
```

`$realpath_root` resuelve el enlace `current`, de modo que cada versión nueva se sirve sin reiniciar Nginx.

Los `add_header` de `location /build/` sustituyen a los del bloque `server` (Nginx no los combina). Si quieres conservar las cabeceras de seguridad también en los assets, repítelas en ese bloque.

```bash
ln -s /etc/nginx/sites-available/vyamap /etc/nginx/sites-enabled/
rm /etc/nginx/sites-enabled/default
nginx -t && systemctl reload nginx

certbot --nginx -d vyamap.josedavidfernandezcomino.com --redirect
systemctl list-timers | grep certbot   # renovación automática
```

Certbot añade el bloque `listen 443 ssl` y la redirección de HTTP a HTTPS.

Nginx pasa `HTTPS=on` a PHP (`fastcgi_params`), así que Laravel genera las URLs con `https://` sin `TRUSTED_PROXIES`.

**HSTS:** cuando HTTPS funcione, añade en el bloque `443`:

```nginx
add_header Strict-Transport-Security "max-age=31536000" always;
```

Sin `includeSubDomains`, porque afectaría a todos los subdominios de `josedavidfernandezcomino.com`, y sin `preload`.

## 7. Registro limitado y cuentas

### 7.1 Modelo

- Cada visitante crea **su propia cuenta**. No hay cuenta compartida ni credenciales publicadas.
- Los datos de cada cuenta (viajes, visitas, vuelos, álbumes, fotos) solo los ve y modifica su propietario: policies y `scopeBindings`, comprobados en `tests/Feature/SecurityIsolationTest.php`.
- Lo único compartido es el catálogo: países, ciudades, aeropuertos y alias de búsqueda. Las ciudades nuevas solo se crean después de verificarlas con Geoapify en el servidor.
- **Tu cuenta personal no está en producción.** Tus datos reales siguen en tu equipo (`vyamap`). Si quieres una cuenta tuya en la demo, créala con un correo distinto del personal y rellénala con `php artisan demo:reset`.

### 7.2 Operación

| Acción | Cómo |
|---|---|
| Abrir el registro | `REGISTRATION_ENABLED=true` en `shared/.env` y después `sudo -u vyamap php artisan config:cache` |
| Cerrar el registro | `REGISTRATION_ENABLED=false` y `config:cache`. `/register` devuelve 404 y el enlace desaparece del login. Las cuentas ya creadas siguen funcionando. |
| Limitar el número de cuentas | `REGISTRATION_MAX_USERS=100` (se cambia en `shared/.env` y `config:cache`). Al llegar al límite, el registro se cierra solo, igual que con `REGISTRATION_ENABLED=false`; las altas simultáneas se vuelven a contar con bloqueo. `users:create` no está sujeto al límite. |
| Desactivar subidas (fotos y avatares) | `UPLOADS_ENABLED=false`, que también es el valor por defecto en producción. El servidor rechaza `POST /photos` (403) y el avatar en el registro y en el perfil; la interfaz oculta los campos. |
| Revisar las altas | `sudo -u vyamap php artisan users:list` (o `--limit=200`): id, nombre, correo, fecha de alta y número de viajes, vuelos y fotos. Solo lectura, sin credenciales. Muestra también cuántas cuentas hay y si el registro está abierto. |
| Frecuencia | Ya limitada en la aplicación: 5 registros por minuto y 20 por hora por IP |
| Crear una cuenta a mano | `sudo -u vyamap php artisan users:create "Nombre" correo@example.com` (pide la contraseña) |
| Rellenar una cuenta con datos de ejemplo | `sudo -u vyamap php artisan demo:reset correo@example.com`. Solo sustituye los viajes, visitas y vuelos de esa cuenta; se niega si tiene fotos. |

### 7.3 Correo: requisito para abrir el registro

**Lo que ya hace la aplicación** (`EMAIL_VERIFICATION`, activa por defecto fuera de `local`):

- Al registrarse se envía un enlace de verificación firmado, válido 60 minutos. Hasta abrirlo, la cuenta solo ve `/email/verify`, desde donde puede reenviar el enlace o cerrar sesión. Las demás páginas redirigen ahí, y las peticiones JSON reciben 403.
- Reenvío limitado a 3 cada 15 minutos por cuenta y a 6 por minuto por IP.
- Recuperación de contraseña con la misma respuesta exista o no la cuenta, limitada a 5 peticiones por minuto por IP y 3 cada 15 minutos por dirección.
- `users:create` crea cuentas ya verificadas. `users:verify <correo>` verifica una cuenta concreta (pide confirmación).

**Lo que falta para abrir el registro:** un proveedor de correo transaccional con el dominio verificado (registros SPF, DKIM y DMARC en el DNS de `josedavidfernandezcomino.com`) y un remitente como `no-reply@josedavidfernandezcomino.com`. Mientras no esté, `REGISTRATION_ENABLED=false`.

Opciones con plan gratuito (octubre de 2026; los límites cambian, revísalos al crear la cuenta):

| Proveedor | Gratis | Primer plan de pago | Notas |
|---|---|---|---|
| **Brevo** (preferido) | 300 correos al día | Desde unos 9 $/mes | Empresa europea (Francia); SMTP y API. Según reseñas, el plan gratuito añade su marca: hay que comprobar si también en los transaccionales. |
| Resend | 3000 al mes, máximo 100 al día, 3 dominios | Pro, 20 $/mes (50 000 correos) | SMTP y API; empresa de EE. UU. |
| MailerSend | 500 al mes, 1 dominio | Hobby, 7 $/mes (5000 correos) | SMTP y API |
| Postmark | 100 al mes | Basic, 15 $/mes (10 000 correos) | Muy buena entregabilidad; el plan gratuito solo sirve para pruebas |
| Amazon SES | Sin cuota gratuita fija (créditos de cuenta nueva) | 0,10 $ por 1000 | Empieza en modo *sandbox* |

La aplicación no depende de ninguno: se configura solo con el mailer `smtp` de Laravel, sin dependencias nuevas. Para Brevo (valores de su documentación; compruébalos en el panel, «SMTP & API»):

```
MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587                         # si el VPS bloquea el 587: 2525
MAIL_USERNAME=<login SMTP de Brevo>   # el login SMTP, no el correo de la cuenta ni una clave de API
MAIL_PASSWORD=<clave SMTP de Brevo>   # una «SMTP key», no una «API key»
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=no-reply@josedavidfernandezcomino.com
MAIL_FROM_NAME=VyaMap
```

Después: `sudo -u vyamap php artisan config:cache`, una prueba con una cuenta propia (registro, enlace, recuperación) y, solo entonces, `REGISTRATION_ENABLED=true`.

**Probar en local sin enviar correos:**

- **`MAIL_MAILER=log`** (sin instalar nada): los correos se escriben en `storage/logs/laravel.log`, con su destinatario y su HTML. Los enlaces se encuentran con:

  ```bash
  grep -oE 'http[^"<> ]*/email/verify/[^"<> ]*' storage/logs/laravel.log | tail -1 | sed 's/&amp;/\&/g'
  grep -oE 'http[^"<> ]*/reset-password/[^"<> ]*' storage/logs/laravel.log | tail -1 | sed 's/&amp;/\&/g'
  ```

  El enlace de verificación hay que abrirlo con la sesión de esa cuenta iniciada. `tests/Feature/MailLogLinksTest.php` comprueba que los dos enlaces aparecen así en el log y funcionan.
- **Mailpit** (opcional; un buzón falso local, `brew install mailpit` y luego `mailpit`): con `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025` y sin usuario ni cifrado, los correos se ven en `http://127.0.0.1:8025`. No sale nada a Internet.
- Los tests cubren el flujo completo sin enviar nada: `EmailVerificationTest`, `PasswordResetTest` y `AuthSecurityTest`.

## 8. Actualizaciones y vuelta atrás

**Actualizar:** repite 5.1, 5.2 y 5.5 con un `RELEASE` nuevo. El `.env`, `storage` y la base de datos se conservan.

Antes de una migración que cambie datos, haz una copia manual (sección 9).

**Volver atrás:** si la versión nueva no ha migrado la base de datos, basta con apuntar `current` a la anterior y recargar PHP-FPM.

```bash
ln -sfn releases/<anterior> /var/www/vyamap/current && sudo systemctl reload php8.3-fpm
```

**Limpiar:** borra las versiones antiguas y conserva las tres últimas.

```bash
cd /var/www/vyamap/releases && ls -1 | head -n -3 | xargs -r rm -rf
```

## 9. Copias de seguridad

Qué se copia: la base de datos y `shared/storage/app`. El catálogo no hace falta, porque se regenera desde el repositorio.

Credenciales del usuario de copias, en `/root/.my-vyamap-backup.cnf` (`600`, root):

```ini
[client]
user=vyamap_backup
password=<la de 4.3>
```

`/usr/local/sbin/vyamap-backup` (`700`, root):

```bash
#!/bin/sh
set -eu
umask 077
DIR=/var/backups/vyamap
STAMP=$(date +%Y%m%d-%H%M)
mkdir -p "$DIR"
mysqldump --defaults-extra-file=/root/.my-vyamap-backup.cnf --single-transaction --no-tablespaces \
  --routines --triggers vyamap_prod | gzip > "$DIR/db-$STAMP.sql.gz"
tar -czf "$DIR/storage-$STAMP.tar.gz" -C /var/www/vyamap/shared/storage app/public app/private
find "$DIR" -type f -mtime +14 -delete
chgrp -R deploy "$DIR" && chmod 750 "$DIR" && chmod 640 "$DIR"/*   # para traerlas desde tu equipo
```

`/etc/cron.d/vyamap-backup`:

```
30 3 * * * root /usr/local/sbin/vyamap-backup
```

**Copia fuera del servidor:** si el VPS desaparece, las copias locales se pierden con él.

- La opción más sencilla es **traerlas desde tu equipo** (`rsync -a deploy@…:/var/backups/vyamap/ <carpeta>`). Así el servidor no guarda credenciales de ningún almacenamiento externo. El script ya deja la carpeta legible para `deploy`.
- Esa carpeta no debe estar dentro de `~/Documents`, porque se sincroniza con iCloud. Si tiene que estarlo, cifra los archivos antes (por ejemplo, con `age`).
- Además, activa las instantáneas del proveedor del VPS si las ofrece.

**Prueba de restauración**, al menos una vez: importa una copia en una base temporal y comprueba que tiene usuarios y viajes.

```bash
mysql -e 'CREATE DATABASE vyamap_restore_test'
gunzip -c db-<fecha>.sql.gz | mysql vyamap_restore_test
mysql -e 'SELECT COUNT(*) FROM vyamap_restore_test.users; DROP DATABASE vyamap_restore_test'
```

## 10. Comprobaciones después del despliegue

1. `https://vyamap.josedavidfernandezcomino.com/login` carga por HTTPS, con certificado válido y sin avisos de contenido mixto. `http://` redirige a `https://`.
2. Las cookies de sesión llevan `Secure` y `HttpOnly`.
3. Con un error forzado no se ven trazas ni datos de configuración (`APP_DEBUG=false`).
4. Devuelven 404: `/_ignition/health-check`, `/_inertia/devtools/entries`, `/.env`, `/storage/logs/laravel.log` e `/index.php/../.env`.
5. Registro: con `REGISTRATION_ENABLED=false`, `/register` devuelve 404. Las cuentas creadas con `users:create` entran sin pasar por `/email/verify`. Cuando se abra (con correo): se puede crear una cuenta, y con `REGISTRATION_MAX_USERS` igual al número de cuentas el registro se cierra (`users:list` lo indica).
6. Aislamiento: con dos cuentas, la segunda no ve los viajes, vuelos ni estadísticas de la primera, y las URL de los recursos de la primera le devuelven 403 o 404.
7. Subidas desactivadas: no aparecen los botones de fotos ni de avatar, y `POST /photos` devuelve 403.
8. Con datos de `demo:reset`: Home, Trips, Passport y Flight History muestran viajes y estadísticas, y los mapas cargan.
9. `ss -ltnp` solo muestra 22, 80 y 443 hacia fuera; MySQL, en `127.0.0.1`.
10. La copia de la noche aparece en `/var/backups/vyamap` y se restaura (sección 9).
