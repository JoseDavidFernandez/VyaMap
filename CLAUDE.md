# CLAUDE.md — VyaMap

Instrucciones permanentes para Claude Code en este repositorio. VyaMap es una aplicación web personal (Laravel 13 + Vue 3 con Inertia) para registrar, visualizar y explorar el historial de viajes: viajes, ciudades visitadas, vuelos y fotos, con mapas y estadísticas. Demo pública con cuenta de solo lectura en `https://vyamap.josedavidfernandezcomino.com`.

## Documentos de referencia (fuente de verdad)

Consulta el documento correspondiente antes de actuar. No dupliques su contenido aquí ni entre documentos: enlaza.

| Documento | Es la fuente de… |
|---|---|
| [README.md](README.md) | Funcionalidades, stack, instalación local, catálogo, demo y pruebas. |
| [docs/architecture.md](docs/architecture.md) | Arquitectura, reglas del dominio, seguridad, dependencias y entorno de testing. |
| [docs/frontend.md](docs/frontend.md) | Frontend. |
| [docs/VyaMap_Map_System.md](docs/VyaMap_Map_System.md) y [docs/VyaMap_ADR-001_Separate_Map_Contexts.md](docs/VyaMap_ADR-001_Separate_Map_Contexts.md) | Sistema de mapas. |
| [docs/roadmap.md](docs/roadmap.md) | Fases (F0–F6), estado real de cada una y pendientes. |
| [docs/manual-testing.md](docs/manual-testing.md) | Pruebas manuales por prioridad. |
| [docs/deployment.md](docs/deployment.md) | Despliegue y operación en producción (Plesk). |
| [DESIGN.md](DESIGN.md) | Diseño visual. |
| [database/data/catalog/README.md](database/data/catalog/README.md) | Origen, licencia y regeneración de los CSV del catálogo. |

Si dos documentos se contradicen, detente y pregunta.

## Idioma y convenciones

- Conversación y documentación: español.
- Interfaz: inglés, como la actual («Try the demo», «Mark as planned»…).
- Código, identificadores, tablas, columnas y mensajes de commit: inglés.
- Commits con prefijo de tipo, como en el historial: `feat:`, `fix:`, `docs:`, `perf:`, `data:`, `style:`, `test:`, `chore:` (`chore(deps):` para dependencias). Ramas con el mismo prefijo: `perf/bundle-y-globo`, `data/polinesia-islas`, `docs/one-click-deploy`.

## Protocolo de trabajo

### 1. Investigar
- Lee solo los documentos que afecten a la tarea.
- Inspecciona el código existente y `git status` antes de modificar nada.
- Para Laravel, Fortify, Inertia, Vue, Tailwind, Leaflet, three-globe, PHPUnit, Vitest u otras librerías, consulta documentación actual (Context7 o fuentes oficiales). **No inventes versiones ni APIs.** Las versiones reales son las de `composer.lock` y `package-lock.json`.

### 2. Planificar
- Para tareas de más de un archivo o con decisiones de diseño, presenta un plan breve: archivos afectados, reglas implicadas, tests previstos.
- Si una decisión no está en los documentos, **no la tomes por tu cuenta**: proponla y pregunta.
- Trabaja dentro de la fase actual del roadmap. No adelantes fases ni implementes lo que está aplazado o fuera de V1.

### 3. Implementar
- Cambios pequeños y enfocados. Sin refactors no solicitados.
- Respeta el estilo del código circundante. La lógica de negocio va en `app/Services` y `app/Support`, no en controladores ni componentes Vue; las estadísticas salen solo de `App\Services\TravelStatistics`.
- Fechas de viaje y horas de vuelo: reutiliza las utilidades de `resources/js/utils` (`tripDates`, `flightTime`); las horas se muestran en la zona del aeropuerto, nunca en la del navegador.

### 4. Verificar
- Comprobación completa: `php artisan test`, `npm run test:js`, `npx vue-tsc --noEmit` y `npm run build`; añade `npm audit` si cambian dependencias. Comunica el resultado real, incluidos fallos.
- Todo comportamiento nuevo o corregido queda cubierto por un test.
- Revisa `git status` y `git diff` al terminar: solo cambios esperados.

## Protección de datos y secretos

- Nunca muestres, copies, registres ni subas a Git valores de `.env`, `.env.testing`, `.env.manual`, contraseñas, tokens o claves.
- `.env*` con secretos nunca se versiona; `.env.example` y `.env.testing.example` solo con valores de ejemplo.
- No asumas contraseñas existentes de MySQL/MAMP; pídelas o pide al usuario que las configure.
- No envíes datos del proyecto ni del usuario a servicios externos sin autorización.
- Sin datos personales reales en seeders, factories, tests ni en los datos de la demo.
- La suite automatizada (PHPUnit) usa siempre `vyamap_testing` y nunca la base de desarrollo (`vyamap`) ni la de pruebas manuales (`vyamap_manual`). `phpunit.xml` la fija y `Tests\Support\TestDatabaseGuard` aborta si se carga otra base.

## Protección del trabajo existente

- No sobrescribas ni elimines archivos sin revisarlos y, si no es parte evidente de la tarea, sin preguntar.
- Sin `git reset --hard`, `git checkout -- .`, `git clean`, `push --force` ni reescritura de historial sin autorización explícita.
- Sin commits ni push salvo petición expresa; si se piden y estás en `main`, crea una rama.
- No modifiques migraciones ya ejecutadas en entornos compartidos; crea una nueva.

## Operaciones que requieren autorización explícita

- **Destructivas:** borrar archivos o directorios, `migrate:fresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`, `DROP`, `TRUNCATE`, borrar ramas.
- **Instalaciones:** `composer require/update/install`, `npm install/update`, herramientas globales, Laravel Boost, cambios de versión de PHP o Node.
- **Base de datos:** crear bases o usuarios en MAMP, ejecutar migraciones, seeders o comandos que escriban (importadores del catálogo con `--apply` o sin `--dry-run`, `demo:reset`, `cities:reconcile`, `users:create`…) contra `vyamap` o `vyamap_manual`.
- **Configuración:** `.env`, MAMP, permisos, hooks, skills, agentes, plugins o settings de Claude Code.
- **Despliegues y servicios externos:** despliegues, recursos en la nube, CI, webhooks, correo real, llamadas reales a Geoapify.

Una autorización vale para la operación concreta, no para operaciones futuras similares.

## Skills y subagentes

- Úsalos solo cuando aporten valor claro; por defecto, trabaja directamente.
- Subagentes: búsquedas amplias o revisiones independientes que no necesiten el contexto de la conversación.
- Skills: cuando la tarea coincide con una disponible (revisión de código, revisión de seguridad antes de publicar).
- Resume al usuario lo relevante de lo que devuelvan.

---

## Sobre VyaMap

### Stack (versiones instaladas según los lockfiles)

| Capa | Tecnologías |
|---|---|
| Backend | PHP ^8.3, Laravel 13.32, Laravel Fortify 1.39, Laravel Sanctum 4.3, Inertia Laravel 3.3, Intervention Image 4.3 (GD) |
| Base de datos | MySQL 8 en local (MAMP); MariaDB 10.5 en producción |
| Frontend | Vue 3.5, TypeScript 5.9, Inertia Vue 3.7, Tailwind CSS 4.3, Vite 6.4 (`laravel-vite-plugin` 1.x) |
| Mapas | Leaflet 1.9 con teselas de OpenStreetMap; three 0.186 + three-globe 2.45 para el globo del perfil; polígonos de países en `public/data/world.topo.json` |
| Pruebas | PHPUnit 12.5, Vitest 4.1 con @vue/test-utils y happy-dom, vue-tsc 3.3, Pint 1.32 |
| Servicios externos | Geoapify (ciudades fuera del catálogo y `cities:enrich-regions`), Have I Been Pwned (contraseñas), Brevo (correo) |

### Estructura

- `app/Console/Commands`: comandos de Artisan propios (catálogo, cuentas, demo, fotos).
- `app/Services`: lógica de dominio (`TravelStatistics`, `CityPersistenceService`, `CityReconciliationService`, `CitySearchService`, fotos y EXIF).
- `app/Support`: reglas compartidas (`DemoAccount`, `Registration`, `SovereignCountries`, `CityName`, `FlightPresenter`…).
- `app/Policies`: autorización por propietario de viajes, vuelos y fotos.
- `config/`: además de lo estándar, `demo.php`, `uploads.php`, `continents.php`, `countries.php`, `cities.php`, `city_aliases.php`.
- `resources/js`: `pages` (Inertia, cargadas bajo demanda), `components`, `utils`, `data`, `vendor-stubs` (sustitutos de partes de three/h3-js que no se usan).
- `database/data/catalog`: los cinco CSV del catálogo versionados y sus herramientas de regeneración (`tools/`).
- `tests/Feature`, `tests/Unit` (PHPUnit), `tests/js` (Vitest).

### Particularidades

- **Catálogo:** los importadores leen los CSV de `storage/app/data/`, que Git ignora, no los de `database/data/catalog/`. Hay que copiarlos a mano, también en producción; un despliegue no actualiza el catálogo.
- **Simulación por defecto, con dos convenciones:** `countries:import`, `airports:map-cities`, `airports:enrich-coordinates`, `airports:import-timezones` y `cities:enrich-regions` simulan sin `--apply`; `cities:import` y `app:import-airports` **aplican** salvo que se pase `--dry-run`. Lanza siempre primero la simulación.
- **Cuenta demo de solo lectura:** la identifica `DEMO_USER_EMAIL` (`config/demo.php`); el middleware `DemoReadOnly` bloquea cualquier escritura y no puede recuperar su contraseña. «Try the demo» inicia la sesión desde el servidor.
- **Registro:** cerrado fuera de `local` salvo `REGISTRATION_ENABLED=true`, con un máximo de `REGISTRATION_MAX_USERS` cuentas (100 en producción); `users:create` no está sujeto al límite.
- **Subidas de fotos y avatares desactivadas** (`UPLOADS_ENABLED=false` por defecto); el procesado en cola sigue en el código. Las fotos existentes son privadas y solo las ve su propietario.
- **Tres bases locales:** `vyamap` (desarrollo), `vyamap_manual` (pruebas manuales, `.env.manual`, con `--env=manual`) y `vyamap_testing` (solo la suite). **`php artisan test` vacía y recrea `vyamap_testing`.**
- Caché y sesiones en `file`: no hay tablas `sessions` ni `cache`, y Laravel lee `CACHE_DRIVER`, no `CACHE_STORE`.
- Política de `npm audit` y riesgos aceptados: [docs/architecture.md](docs/architecture.md) («Dependencias de npm»). Nunca `npm audit fix` en el servidor.

## Comandos habituales

### Instalar y desarrollo local

```bash
composer install
npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm run dev                                   # Vite
php artisan serve                             # o el servidor web local
php artisan serve --env=manual --port=8010    # contra vyamap_manual
composer run queue                            # worker, solo si QUEUE_CONNECTION=database
```

La suite PHP necesita el MySQL de MAMP en el puerto 3306 (`/Applications/MAMP/bin/startMysql.sh`).

### Tests y build

```bash
php artisan test         # PHPUnit sobre vyamap_testing (.env.testing, plantilla .env.testing.example)
npm run test:js          # Vitest
npx vue-tsc --noEmit     # tipos
npm run build            # assets de producción
```

### Comandos de Artisan propios

| Comando | Qué hace |
|---|---|
| `countries:import [--apply]` | Importa países (`countries.csv`, `countries_overrides.csv`) |
| `cities:import [--dry-run]` | Importa ciudades (`vyamap_airport_city_mapping.csv`) |
| `app:import-airports [--dry-run]` | Importa aeropuertos (`vyamap_airport_city_mapping.csv`) |
| `airports:map-cities [--apply]` | Asocia aeropuertos a ciudades |
| `airports:enrich-coordinates [--apply]` | Coordenadas de aeropuertos (`airports.csv`) |
| `cities:enrich-regions [--apply]` | Códigos de región de ciudades homónimas (consulta Geoapify) |
| `airports:import-timezones [--apply]` | Zonas horarias de aeropuertos (`airport_timezones.csv`) |
| `cities:audit-aliases [--delete]` | Lista (o borra, con confirmación) alias de ciudades inválidos |
| `cities:reconcile {duplicate} --into=` | Fusión reversible de ciudades duplicadas (simula por defecto) |
| `cities:test-region-matching` | Prueba el emparejamiento de regiones con Geoapify |
| `users:create "Nombre" correo` | Crea una cuenta; pide la contraseña de forma interactiva |
| `users:list [--limit=50]` | Lista cuentas y estado del registro (solo lectura) |
| `users:verify correo [--force]` | Marca un correo como verificado |
| `demo:reset correo [--force]` | Sustituye viajes, visitas y vuelos de esa cuenta por datos ficticios; se niega si tiene fotos |
| `photos:move-to-private` | Migración verificable y reversible de fotos al disco privado |

Los importadores se ejecutan en el orden de la tabla (el de [docs/deployment.md §5.5](docs/deployment.md)) y son seguros de repetir: nunca duplican.

## Despliegue

Resumen; el procedimiento completo y las comprobaciones están en [docs/deployment.md](docs/deployment.md).

- **Dónde:** VPS de IONOS con Plesk, suscripción propia, raíz web `httpdocs/public`. PHP 8.3 (FPM), MariaDB local, Node solo para compilar los assets. Sin cron ni worker (`QUEUE_CONNECTION=sync`).
- **Cómo:** Plesk Git (modo *Manual*, deploy key de solo lectura) + **Laravel Toolkit → Deployment**: push a `main` en GitHub y pulsar *Deploy*. El script ejecuta `composer install`, `npm install`, `npm run build`, `php artisan migrate --force` y `php artisan optimize` en modo mantenimiento.
- **Antes de desplegar una migración:** comprobar la última copia de Backup Manager o exportar un volcado (§9.4).
- **Después:** verificar las fechas de `public/build/manifest.json` y `bootstrap/cache/config.php` y que `migrate:status` no tiene pendientes (§6.3); comprobaciones de seguridad de §10.
- **Recordar:**
  - Si cambia un CSV del catálogo, copiarlo a mano a `storage/app/data/` (con copia `.bak`) y lanzar sus importadores, primero simulando (§6.4).
  - Cualquier cambio en el `.env` de producción necesita `php artisan optimize`.
  - `users:create` y `users:verify` piden datos interactivos: van por SSH, no por el Toolkit.
  - Composer siempre *Install*, nunca *Update*; nunca `npm audit fix` en el servidor.
  - Si el Toolkit falla, plan B manual en §11 (y `php artisan up` si queda en mantenimiento).
- Desplegar o ejecutar cualquier comando en el servidor requiere autorización explícita del usuario.
