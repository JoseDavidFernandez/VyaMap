# VyaMap — Despliegue en Plesk

Guía del despliegue real de VyaMap en **`https://vyamap.josedavidfernandezcomino.com`**: un VPS con **Plesk**, compartido con otros sitios, cada uno en su propia suscripción. Describe cómo se hizo la primera vez (noviembre de 2026) y cómo se actualiza.

El registro público está **cerrado**: las cuentas se crean con `users:create`. La subida de fotos y avatares está **desactivada**.

## 1. Qué necesita la aplicación

| Requisito | Detalle |
|---|---|
| PHP | 8.3, con `gd`, `exif`, `mbstring`, `pdo_mysql`, `fileinfo`, `intl`, `xml`, `curl`, `zip` y `bcmath` |
| Composer | Dependencias de producción (sin las de desarrollo, para no instalar Ignition) |
| Node.js | 20 o 22. Solo para compilar los assets con Vite; la aplicación no lo usa en tiempo de ejecución. |
| Base de datos | MariaDB 10.5 (la del servidor), en una base y un usuario exclusivos de VyaMap |
| Sesiones y caché | `file`. El proyecto no tiene tablas `sessions` ni `cache`, así que `database` no funciona. Laravel lee `CACHE_DRIVER`, no `CACHE_STORE`. Nunca `array` en producción: no guarda nada entre peticiones y los límites de intentos dejarían de funcionar. |
| Cola | `QUEUE_CONNECTION=sync`: no hace falta worker. |
| Tareas programadas | Ninguna. La aplicación no necesita cron. |
| Servicios externos | Teselas de OpenStreetMap (sin clave). Geoapify para buscar ciudades fuera del catálogo y para `cities:enrich-regions`. Have I Been Pwned al crear contraseñas (si no responde, no bloquea). Brevo para el correo transaccional. |

## 2. Arquitectura

```
Internet ──443──▶ nginx (Plesk) ──▶ Apache ──▶ PHP-FPM 8.3 (usuario de la suscripción) ──▶ MariaDB (solo local)
            80 ─▶ redirección 301 a HTTPS
```

- Todo lo gestiona Plesk: el servidor web, PHP, los certificados y la base de datos.
- La aplicación vive en `httpdocs/` de su suscripción, y la **raíz web es `httpdocs/public`**. Nada fuera de `public/` debe ser accesible desde Internet (sección 4.2).
- Cada suscripción tiene su propio usuario del sistema, así que VyaMap no puede leer ni escribir en los demás sitios del servidor.
- **SSH está en *Forbidden*** salvo cuando hace falta (sección 7).
- HTTPS lo termina el propio servidor, así que **`TRUSTED_PROXIES` no se define**. Comprobado: los enlaces de los correos salen con `https://`.

## 3. Antes de empezar

1. **Claves nuevas**, exclusivas de producción y que nunca se reutilizan de otro entorno ni de otro sitio:
   - la contraseña de la base de datos (la genera Plesk);
   - una clave SMTP de Brevo propia de VyaMap;
   - una clave de Geoapify propia de producción;
   - `APP_KEY`, que se genera en el servidor (sección 5.4).
2. Guárdalas en el gestor de contraseñas. **Nunca** en el repositorio ni en notas sin cifrar.

## 4. Dominio, suscripción y HTTPS

### 4.1 DNS

El DNS de `josedavidfernandezcomino.com` está en el panel del registrador, no en Plesk. Los nombres de host se escriben **relativos al dominio principal**:

| Tipo | Host | Valor |
|---|---|---|
| A | `vyamap` | IP del VPS |
| A | `www.vyamap` | IP del VPS |

- **Ningún registro AAAA**, salvo que el VPS tenga IPv6. Si el registrador crea registros por defecto que apuntan a su propio hosting (A o AAAA), hay que cambiarlos o borrarlos: si no, Plesk no deja crear la suscripción (*«Cannot use the domain name because it resolves to another server»*) y Let's Encrypt falla.
- Comprobación desde fuera del servidor:

  ```bash
  dig +short vyamap.josedavidfernandezcomino.com          # IP del VPS
  dig +short AAAA vyamap.josedavidfernandezcomino.com     # vacío
  dig +short @1.1.1.1 www.vyamap.josedavidfernandezcomino.com
  ```

- Si en tu equipo sigue saliendo la IP antigua, es la caché: en macOS, `sudo dscacheutil -flushcache; sudo killall -HUP mDNSResponder`.

### 4.2 Suscripción

1. **Subscriptions → Add Subscription**, con el dominio `vyamap.josedavidfernandezcomino.com` (sin `www`).
2. **Hosting & DNS → Hosting:**
   - **Document root:** `httpdocs/public`. Después de guardar, **vuelve a entrar y comprueba que se ha guardado**. Si se queda en `httpdocs`, el `.env` es descargable desde Internet (ver la nota de abajo).
   - **Redirección 301 de HTTP a HTTPS:** activada.
   - **SSH access:** *Forbidden*.
3. **PHP:** versión 8.3 (FPM). `memory_limit` 256M y **`display_errors` en Off**.
4. **Mail → Mail Settings:** correo de entrada desactivado. VyaMap solo envía, y lo hace por Brevo.
5. Borra el `index.html` por defecto que crea Plesk en `httpdocs`.

> **Incidente del primer despliegue.** El *Document root* no llegó a guardarse y la raíz web quedó en `httpdocs/`. A los pocos minutos de crear el `.env`, un bot lo descargó. Hubo que corregir la raíz y cambiar todas las claves. Por eso la sección 5.3 obliga a comprobar el `.env` justo después de crearlo.

### 4.3 Certificado

**SSL/TLS Certificates → Let's Encrypt**, marcando el dominio **y el `www`** (sin *webmail* ni *mail*). Si el `www` no resolvía al emitirlo, el certificado no lo incluye: hay que volver a emitirlo cuando resuelva.

```bash
curl -I https://vyamap.josedavidfernandezcomino.com        # sin errores de certificado
curl -I https://www.vyamap.josedavidfernandezcomino.com    # también
curl -I http://vyamap.josedavidfernandezcomino.com         # 301 a https://
```

### 4.4 Cabeceras de seguridad

**Apache & nginx Settings → Additional directives for HTTPS:**

```apache
<IfModule mod_headers.c>
	Header always set Strict-Transport-Security "max-age=31536000"
	Header always set X-Content-Type-Options "nosniff"
	Header always set X-Frame-Options "SAMEORIGIN"
	Header always set Referrer-Policy "strict-origin-when-cross-origin"
	Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"
	Header always unset X-Powered-By
	Header unset X-Powered-By
</IfModule>
```

HSTS va sin `includeSubDomains`, porque afectaría a todos los subdominios de `josedavidfernandezcomino.com`, y sin `preload`. La aplicación no usa la geolocalización del navegador, así que se puede bloquear.

### 4.5 Asistente DNS de Plesk

*Troubleshoot DNS* avisa de que el correo no llega a este servidor y de que el SPF no lo autoriza. **Es lo esperado:** el correo de entrada está desactivado y los envíos salen por Brevo, con su propio DKIM.

## 5. Primer despliegue

### 5.1 Base de datos

**Databases → Add Database**, en la suscripción de VyaMap:

- Base y usuario exclusivos de VyaMap, con una contraseña generada por Plesk.
- **Access control:** *Allow local connections only*. Para consultar la base desde tu equipo se usa phpMyAdmin en Plesk o un túnel SSH, nunca el acceso remoto.

### 5.2 Código, dependencias y assets

1. **Git → Add Repository**, con la URL **SSH** del repositorio (`git@github.com:…`):
   - Copia la clave pública que muestra Plesk y añádela en GitHub, en **Settings → Deploy keys**, **sin permiso de escritura**. Hazlo antes de pulsar *OK*.
   - **Deployment mode:** *Manual*.
   - **Deployment directory:** `/httpdocs`.
   - Pulsa *Deploy now* y comprueba en el File Manager que `httpdocs` tiene `artisan`, `app/`, `public/`, etc.
2. **PHP Composer** (en la página del dominio): *Scan*, **Mode: Production** e ***Install***. Nunca *Update*, que ignoraría el `composer.lock`.
3. **Laravel Toolkit → Scan.** Si devuelve *0 applications*, casi seguro que la raíz web no está en `httpdocs/public`.
4. **Laravel Toolkit → Node.js:** activa Node 20 o 22, `npm install` y `npm run build`. Comprueba que existe `public/build/manifest.json`.
   - `npm install` avisa de vulnerabilidades. **Nunca** ejecutes `npm audit fix` en el servidor: se revisan y corrigen en local, se prueban y se despliegan.

### 5.3 `.env` de producción

En el File Manager, copia `.env.example` a `.env` dentro de `httpdocs` y cambia lo primero:

```
APP_ENV=production
APP_DEBUG=false
```

**Comprueba inmediatamente** que no es accesible desde Internet:

```bash
curl -I https://vyamap.josedavidfernandezcomino.com/.env    # tiene que dar 404
```

Si da **200**, la raíz web está mal: corrígela (4.2) antes de escribir ninguna clave.

Después, el resto de valores (las claves, desde el gestor de contraseñas):

```
APP_URL=https://vyamap.josedavidfernandezcomino.com
LOG_LEVEL=warning

DB_HOST=127.0.0.1
DB_DATABASE=<base de 5.1>
DB_USERNAME=<usuario de 5.1>
DB_PASSWORD=<contraseña de 5.1>

CACHE_DRIVER=file
SESSION_DRIVER=file
SESSION_SECURE_COOKIE=true

MAIL_MAILER=smtp
MAIL_HOST=smtp-relay.brevo.com
MAIL_PORT=587
MAIL_USERNAME=<login SMTP de Brevo>
MAIL_PASSWORD=<clave SMTP de Brevo>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@vyamap.josedavidfernandezcomino.com"
MAIL_FROM_NAME="VyaMap"

DEMO_USER_EMAIL=demo@vyamap.josedavidfernandezcomino.com
REGISTRATION_MAX_USERS=100

GEOAPIFY_API_KEY=<clave de producción>
```

Notas:

- **`APP_URL`** tiene que ser exactamente esa, con `https` y sin barra final. Si no coincide, `TrustHosts` responde 400.
- **`REGISTRATION_ENABLED`**, **`EMAIL_VERIFICATION`** y **`UPLOADS_ENABLED`** se dejan comentadas: sus valores por defecto ya son los correctos (registro cerrado, verificación obligatoria y subidas desactivadas).
- **No definas** `TRUSTED_PROXIES` ni `INERTIA_DEVTOOLS_ENABLED`.
- **`DEMO_USER_EMAIL`** usa una dirección del propio dominio que no recibe correo. Nunca una de un dominio ajeno: si alguien pidiera recuperar su contraseña, el enlace llegaría a un buzón que no controlas.

### 5.4 `APP_KEY` y migraciones

**Laravel Toolkit → Artisan:**

```
key:generate
migrate --force
```

`--force` hace falta porque en producción Laravel pide confirmación, y el Toolkit no puede responder.

### 5.5 Catálogo

Los comandos del catálogo leen los CSV de `storage/app/data/`, que Git ignora (así los tests pueden aislarse en una carpeta temporal). En el servidor hay que copiarlos desde el repositorio. En el File Manager:

1. Crea `httpdocs/storage/app/data/`.
2. Copia ahí los cinco CSV de `httpdocs/database/data/catalog/`: `countries.csv`, `countries_overrides.csv`, `airports.csv`, `airport_timezones.csv` y `vyamap_airport_city_mapping.csv`.

Después, en **Artisan**, por este orden. **Cuidado:** unos comandos simulan si no se les pasa `--apply`, y otros aplican si no se les pasa `--dry-run`. Lanza siempre primero la simulación y revisa el resumen.

| # | Simulación | Aplicar | Resultado del primer despliegue |
|---|---|---|---|
| 1 | `countries:import` | `countries:import --apply` | 251 países |
| 2 | `cities:import --dry-run` | `cities:import` | 3139 ciudades |
| 3 | `app:import-airports --dry-run` | `app:import-airports` | 3244 aeropuertos |
| 4 | `airports:map-cities` | `airports:map-cities --apply` | 3238 asociados |
| 5 | `airports:enrich-coordinates` | `airports:enrich-coordinates --apply` | 3244 |
| 6 | `cities:enrich-regions` | `cities:enrich-regions --apply` | 41 regiones |
| 7 | `airports:import-timezones` | `airports:import-timezones --apply` | 3244 |

- **Seis aeródromos de la Polinesia Francesa** (FAV, XMH, TKP, MVT, TKX, MAU) no tienen ciudad en el CSV. Se importan sin ciudad y salen como avisos en los pasos 2 y 4.
- **`cities:enrich-regions`** consulta Geoapify, tanto al simular como al aplicar, pero solo para las ciudades homónimas (unas 25 parejas). Los homónimos de China a los que Geoapify no da código de región se quedan sin él.
- Si el Toolkit muestra **«undefined»**, normalmente ha caducado la sesión de Plesk: recarga la página y repite la simulación para ver qué se ha aplicado. Todos los comandos son seguros de repetir: nunca duplican.

### 5.6 Cuentas

`users:create` **pide la contraseña de forma interactiva** (así no queda en ningún historial ni registro), y el Toolkit no puede responder. Hace falta SSH:

1. **Hosting & DNS → Hosting → SSH access:** `/bin/bash`. El entorno *chrooted* no incluye PHP.
2. Abre **SSH Terminal** en la página del dominio:

   ```bash
   cd httpdocs
   php artisan users:create "Nombre" correo@ejemplo.com
   ```

3. **Cuenta demo:** con una contraseña larga y aleatoria (`openssl rand -base64 24`), porque nadie la escribe:

   ```bash
   php artisan users:create "Demo" demo@vyamap.josedavidfernandezcomino.com
   php artisan demo:reset demo@vyamap.josedavidfernandezcomino.com --force
   ```

4. **Vuelve a poner SSH en *Forbidden*.**

### 5.7 Cachés

```
optimize
```

Guarda en caché la configuración, los eventos, las rutas y las vistas. **A partir de aquí, cualquier cambio en el `.env` no se aplica hasta volver a ejecutar `optimize`.**

## 6. Actualizaciones

Mientras no esté automatizado (6.1), cada despliegue con cambios de código se hace a mano:

1. **Git:** *Pull* y *Deploy*.
2. Si ha cambiado `composer.lock`: **PHP Composer → Install**.
3. Si han cambiado el JS, el CSS o `package-lock.json`: **Node.js → `npm install`** y **`npm run build`**.
4. Si hay migraciones nuevas: **Artisan → `migrate --force`**. Antes de una migración que cambie datos, haz una copia de seguridad (sección 8).
5. Siempre: **Artisan → `optimize`**.

Si el despliegue solo cambia documentación, basta con *Pull* y *Deploy*.

### 6.1 Automatizar (pendiente)

**Laravel Toolkit → Deployment** permite ejecutar todo lo anterior al pulsar *Deploy*: modo mantenimiento, `composer install`, `npm install` y un script propio (`npm run build`, `migrate --force`, `optimize`). Para editar el script hace falta activar SSH en la suscripción.

## 7. Operación

| Acción | Cómo |
|---|---|
| Cambiar el `.env` | File Manager o Toolkit, y después **`optimize`** |
| Abrir el registro | `REGISTRATION_ENABLED=true` y `optimize`. Antes: política de privacidad y aviso legal. |
| Cerrar el registro | `REGISTRATION_ENABLED=false` y `optimize`. `/register` devuelve 404 y las cuentas creadas siguen funcionando. |
| Límite de cuentas | `REGISTRATION_MAX_USERS`. Al alcanzarlo, el registro se cierra solo. `users:create` no está sujeto al límite. |
| Revisar las altas | **Artisan → `users:list`** (o `--limit=200`). Solo lectura. |
| Crear una cuenta | Por SSH, sección 5.6 |
| Restablecer la demo | **Artisan → `demo:reset demo@vyamap.josedavidfernandezcomino.com --force`**. Solo sustituye los viajes, visitas y vuelos de esa cuenta. |
| Verificar una cuenta concreta | `users:verify <correo>` (pide confirmación, así que por SSH) |

**Límites de intentos** (requieren la caché en `file`):

- Registro: 5 por minuto y 20 por hora por IP.
- Reenvío de la verificación: 3 cada 15 minutos por cuenta y 6 por minuto por IP.
- Recuperación de contraseña: misma respuesta exista o no la cuenta, 5 por minuto por IP y 3 cada 15 minutos por dirección.
- «Try the demo»: 10 por minuto por IP.

## 8. Correo (Brevo)

1. **Senders, Domains & Dedicated IPs → Domains → Add a domain:** `vyamap.josedavidfernandezcomino.com`, autenticándolo manualmente.
2. Añade en el DNS del registrador los registros que indica Brevo. Como sus nombres son relativos al subdominio, en el registrador hay que **añadirles `.vyamap`**:

   | Brevo | Registrador |
   |---|---|
   | `@` (TXT `brevo-code:…`) | `vyamap` |
   | `brevo1._domainkey` (CNAME) | `brevo1._domainkey.vyamap` |
   | `brevo2._domainkey` (CNAME) | `brevo2._domainkey.vyamap` |
   | `_dmarc` (TXT) | `_dmarc.vyamap` |

3. *Authenticate* en Brevo. La marca de dominio (*branded subdomain*) no hace falta para correos transaccionales.
4. **Senders → Add a sender:** `VyaMap <no-reply@vyamap.josedavidfernandezcomino.com>`.
5. **SMTP & API → SMTP:** una clave propia de VyaMap. Si se filtra, se borra y se genera otra, sin afectar a los demás sitios.
6. Prueba con *¿Olvidaste tu contraseña?* desde la web: el correo debe llegar a la bandeja de entrada, con ese remitente y con enlaces `https://`.

El DMARC empieza con `p=none` (solo informa). Se puede endurecer cuando la entrega lleve un tiempo funcionando.

**Probar en local sin enviar correos:** con `MAIL_MAILER=log`, los correos se escriben en `storage/logs/laravel.log`. Los enlaces se extraen así:

```bash
grep -oE 'http[^"<> ]*/email/verify/[^"<> ]*' storage/logs/laravel.log | tail -1 | sed 's/&amp;/\&/g'
grep -oE 'http[^"<> ]*/reset-password/[^"<> ]*' storage/logs/laravel.log | tail -1 | sed 's/&amp;/\&/g'
```

## 9. Copias de seguridad

**Pendiente.** Qué hay que copiar: la base de datos y `httpdocs/storage/app`. El catálogo no hace falta, porque se regenera desde el repositorio (sección 5.5).

Se configurarán con las herramientas de copia de Plesk, guardando una copia fuera del servidor, y se probarán con al menos **una restauración** en una base temporal, comprobando que contiene usuarios y viajes.

## 10. Comprobaciones después del despliegue

1. `https://…/login` carga con certificado válido, también con `www`, y `http://` redirige a `https://`.
2. **Devuelven 404:** `/.env`, `/_ignition/health-check`, `/_inertia/devtools/entries`, `/storage/logs/laravel.log` e `/index.php/../.env`.
3. Las cabeceras de la sección 4.4 aparecen en `curl -I` y no aparece `X-Powered-By`.
4. Con un error forzado no se ven trazas ni datos de configuración (`APP_DEBUG=false`).
5. Con el registro cerrado, `/register` devuelve 404.
6. Tu cuenta entra sin pasar por `/email/verify`; Home, Trips y Profile cargan y los mapas se ven.
7. «Try the demo» entra sin credenciales, muestra la banda de cuenta de solo lectura y no deja crear ni editar nada.
8. *¿Olvidaste tu contraseña?* envía el correo, llega a la bandeja de entrada y el enlace funciona.
9. Con dos cuentas, la segunda no ve los datos de la primera, y las URL de los recursos de la primera le devuelven 403 o 404.
10. SSH vuelve a estar en *Forbidden*.