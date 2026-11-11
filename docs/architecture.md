# VyaMap — Architecture

## 1. Objetivo

Este documento define la arquitectura técnica de VyaMap y las decisiones estructurales que deben mantenerse durante el desarrollo.

VyaMap es una aplicación web personal para construir, visualizar y explorar el historial de viajes del usuario.

> My History → Map → Discover → New Trip → My History

La arquitectura debe permitir evolucionar el proyecto progresivamente sin introducir complejidad innecesaria antes de que exista una necesidad real.

---

## 2. Principios arquitectónicos

### 2.1. MVP antes que sobreingeniería

La aplicación se desarrollará por fases.

No se implementará una abstracción, servicio, integración o infraestructura únicamente porque pueda resultar útil en el futuro.

> MVP terminado > aplicación perfecta que nunca se termina.

La arquitectura debe permitir crecer, pero no debe obligar al MVP a cargar con necesidades futuras.

### 2.2. Separación de responsabilidades

Cada capa debe tener una responsabilidad clara:

- **Controller**: coordina la petición HTTP y devuelve la respuesta.
- **Form Request**: valida la entrada y las reglas asociadas a la petición.
- **Model / Eloquent**: representa entidades y relaciones de dominio.
- **Policy**: controla autorización sobre recursos.
- **Service**: encapsula lógica de negocio compleja o procesos que involucran varias operaciones.
- **Resource / DTO**: controla la representación de datos cuando sea necesario.
- **Vue**: representa la interfaz y gestiona la interacción del usuario.
- **Inertia**: conecta Laravel con Vue para la aplicación principal.
- **External API clients**: encapsulan integraciones con proveedores externos.

No se debe introducir lógica de negocio compleja directamente en las vistas ni en controladores.

### 2.3. Laravel como fuente de verdad

Laravel será responsable de las reglas de negocio y de la persistencia.

El frontend no debe ser considerado una capa de seguridad.

Toda operación recibida desde Vue debe volver a validarse y autorizarse en backend.

---

# 3. Stack tecnológico

## Backend

- PHP 8.3+
- Laravel 13
- Eloquent ORM
- MySQL
- Laravel Sanctum cuando sea necesario para autenticación/API

## Frontend

- Vue 3
- TypeScript
- Inertia.js
- Tailwind CSS
- Vite
- Leaflet (mapas) y three-globe (globo del perfil)

`shadcn/ui` figuraba en el diseño inicial (sección 21), pero no se ha instalado: los componentes son propios, con Tailwind.

## Desarrollo

- Git
- GitHub
- PHPUnit mediante la infraestructura de testing de Laravel
- Vitest, @vue/test-utils y happy-dom para componentes Vue (`npm run test:js`)

## Futuras tecnologías

- APIs externas para enriquecimiento de ciudades, aeropuertos, vuelos y lugares
- Claude API para funcionalidades de IA

Estas tecnologías futuras no forman parte necesariamente del MVP.

---

# 4. Arquitectura general

La aplicación principal seguirá una arquitectura monolítica modular basada en Laravel + Inertia + Vue.

```text
┌──────────────────────────────┐
│            Vue 3             │
│       TypeScript + UI        │
└──────────────┬───────────────┘
               │
            Inertia
               │
┌──────────────▼───────────────┐
│           Laravel            │
│                              │
│ Controllers                  │
│ Form Requests                │
│ Policies                     │
│ Services                     │
│ Models / Eloquent            │
└──────────────┬───────────────┘
               │
            MySQL
               │
┌──────────────▼───────────────┐
│        Persistent Data       │
│                              │
│ Users                        │
│ Countries                    │
│ Cities                       │
│ Airports                     │
│ Places                       │
│ Trips                        │
│ Visits                       │
│ Flights                      │
│ Journals                     │
│ Journal Days                 │
│ Journal Entries              │
│ Photos                       │
└──────────────────────────────┘
```

La aplicación principal no utilizará una API REST interna como mecanismo obligatorio entre Vue y Laravel.

Inertia será el mecanismo principal de comunicación entre frontend y backend.

---

# 5. Monolito modular

VyaMap comenzará como un monolito Laravel.

No se utilizarán microservicios.

La separación se realizará a nivel lógico mediante:

- Models
- Controllers
- Requests
- Policies
- Services
- recursos de frontend
- módulos funcionales

Esto permite desarrollar rápidamente y mantener una arquitectura preparada para crecer.

No se dividirá la aplicación en servicios independientes hasta que exista una razón técnica real.

---

# 6. Estructura backend

La estructura inicial seguirá aproximadamente:

```text
app/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
│
├── Models/
│
├── Policies/
│
└── Services/
```

## Controllers

Los Controllers deben ser delgados.

Responsabilidades:

1. recibir la petición;
2. utilizar el Form Request correspondiente;
3. invocar la lógica necesaria;
4. devolver una respuesta.

No deben contener grandes bloques de lógica de negocio.

## Form Requests

Las validaciones de entrada se realizarán mediante Form Requests.

Ejemplos actuales:

```text
StoreTripRequest / UpdateTripRequest
UpdateTripStatusRequest
StoreVisitRequest / UpdateVisitRequest
FlightRequest
StorePhotoRequest
StoreJournalEntryRequest
```

`FlightRequest` es la única validación de vuelos: la usan tanto las rutas anidadas en el viaje como las rutas generales `/flights`.

Los Form Requests pueden contener reglas relacionadas con el contexto de la petición, como fechas, existencia de recursos, ownership y coherencia entre entidades.

## Policies

Las Policies centralizan la autorización sobre recursos. Existen `TripPolicy`, `FlightPolicy` y `PhotoPolicy`.

## Services

Los Services se introducirán cuando una operación requiera lógica de negocio significativa o coordine varias operaciones.

No se crearán Services vacíos para cada Model.

> Service cuando aporta una responsabilidad real, no por obligación estructural.

---

# 7. Autorización y ownership

La autenticación y autorización son responsabilidades distintas.

La aplicación utilizará:

- middleware `auth` para proteger rutas;
- Policies para autorización de recursos cuando las operaciones CRUD se amplíen;
- comprobaciones de ownership donde sea necesario.

Regla fundamental:

> Nunca confiar en un `user_id` enviado por el frontend para determinar el propietario de un recurso.

El propietario debe obtenerse del usuario autenticado:

```php
$request->user()->id
```

Aunque actualmente VyaMap se utiliza como aplicación personal, la arquitectura debe mantener esta separación para permitir evolucionar correctamente hacia múltiples usuarios.

---

# 8. Modelos y dominio

Las entidades principales son:

```text
User
Country
City
Airport
Place

Trip
Visit
Flight

Journal
JournalDay
JournalEntry
Photo
Album
```

Relaciones principales:

```text
Country 1:N City
City 1:N Airport
City 1:N Place

User 1:N Trip
User 1:N Visit
User 1:N Flight
User 1:N Photo
User 1:N Album

Trip 1:N Visit
Trip 1:N Flight (opcional: un vuelo puede no pertenecer a ningún viaje)
Trip 1:N Photo
Trip 1:1 Journal

Visit N:1 City

Flight N:1 Airport (origin)
Flight N:1 Airport (destination)

Journal 1:N JournalDay
JournalDay 1:N JournalEntry

JournalEntry N:1 City
JournalEntry N:1 Place
JournalEntry 1:N Photo

Visit 1:N Photo
Place 1:N Photo
Album N:M Photo (album_photo)
```

Las relaciones y reglas de base de datos detalladas se encuentran en la documentación correspondiente del modelo de datos.

---

# 9. Reglas fundamentales del dominio

## Flights

Un Flight representa un movimiento aéreo.

Un vuelo no implica que el usuario haya visitado el origen o destino.

```text
Flight ≠ Visit
```

Los vuelos se relacionan con Airports.

Un vuelo con conexiones se representa mediante múltiples registros Flight, uno por segmento real.

### Datos de un vuelo

- `departure_date`: fecha local de salida en el aeropuerto de origen. Siempre obligatoria.
- `departure_at` / `arrival_at`: instantes en UTC, opcionales. El formulario recoge las horas locales de cada aeropuerto en formato de 24 horas (`HH:MM`) y `FlightRequest` las convierte con la zona horaria del aeropuerto. Una hora que no existe por el cambio de horario se rechaza.
- La hora de llegada solo se admite junto con la de salida; la llegada puede caer entre un día antes y dos días después de la fecha de salida.
- `flight_number` y `airline` son opcionales.
- `trip_id` es opcional. Si el vuelo pertenece a un viaje, su fecha local de salida debe estar entre las fechas del viaje, ambas incluidas.
- `confirmed_at`: confirmación explícita de que el vuelo se realizó.

### Estado de un vuelo

El estado no se guarda: se deduce en un único lugar, `Flight::status()`, y todas las páginas usan `Flight::withStatuses()`. Reglas, en orden:

1. `cancelled`: su viaje está cancelado.
2. `upcoming`: aún no ha salido. Con hora, cuando `departure_at` es posterior al momento actual; sin hora, durante su fecha local de salida y antes.
3. `flown`: ya salió y su viaje está completado, o tiene `confirmed_at`, o su viaje planificado ya empezó y se conoce la hora exacta de salida.
4. `pending`: el resto de vuelos que ya salieron (sin hora y sin confirmar, o independientes sin confirmar). Se pueden confirmar con `PATCH /flights/{flight}/confirmation`.

Las estadísticas de vuelos solo cuentan los `flown`. Editar un vuelo confirmado para moverlo al futuro elimina su confirmación.

## Visits

Un Visit representa una declaración explícita de que el usuario ha visitado una City.

```text
Visit → City
```

La existencia de un vuelo no crea automáticamente una visita.

Una ciudad se considera visitada únicamente cuando existe un Visit correspondiente.

### Alcance en V1: solo ciudades o localidades

En V1, una visita representa exclusivamente una ciudad o localidad (`City`) del catálogo. Los lugares de interés (castillos, monumentos, museos…) quedan fuera del alcance actual:

- El buscador del itinerario (`/cities/search`) solo busca ciudades, en el catálogo local y en Geoapify con `type=city`. Un lugar de interés se registra visitando su localidad (por ejemplo, Sinaia para el castillo de Peleș) y, si se quiere, mencionándolo en las notas de la visita.
- La entidad `Place` existe en el esquema (para el diario y las fotos de V2), pero no tiene relación con las visitas ni se usa en el itinerario, en las estadísticas ni en ninguna pantalla.
- No se plantean categorías de visita ni cambios en `visits`, `cities` o `places` para V1. Incorporar lugares de interés al itinerario podrá estudiarse en V2 (sección 20).

## Trips

Un Trip representa una experiencia de viaje concreta.

Puede contener:

- Visits
- Flights
- Photos
- Journal

Un Trip puede existir inicialmente sin Visits.

### Estado de un viaje

`trips.status` guarda uno de tres estados: `planned`, `completed` o `cancelled`. Al crear un viaje sin estado explícito, es `completed` si su fecha de fin ya pasó y `planned` en otro caso.

Transiciones manuales (`PATCH /trips/{trip}/status`, validadas por `UpdateTripStatusRequest`):

- `planned → completed`, solo si el viaje ya ha empezado. Un viaje nunca se completa automáticamente por la fecha.
- `planned → cancelled` y `cancelled → planned`.
- `completed → planned`.

Cualquier otra transición se rechaza.

Para un viaje `planned`, `Trip::phase()` deduce la fase según el calendario del usuario (`app.user_timezone`): `upcoming` (aún no ha empezado), `ongoing` (en curso) o `pending` (ya terminó y falta confirmarlo).

Solo los viajes `completed` cuentan como lugares visitados (`Visit::visitedBy`) en estadísticas y mapas. Los planificados se muestran aparte y los cancelados no cuentan.

Un Visit puede existir sin Trip.

Un Flight puede existir sin Trip.

## Journals

Un Trip puede tener un único Journal.

```text
Trip 1:1 Journal
```

El Journal representa el relato de un viaje realizado.

No es un planificador de viajes futuros.

Un Journal no crea automáticamente Visits, Photos, Places ni Albums.

## Photos

Una Photo puede estar asociada opcionalmente a:

- Trip
- Visit
- Place
- JournalEntry

Esto permite reutilizar una fotografía en diferentes contextos.

### Albums

`Album` es una entidad persistente (tabla `albums`): una colección de fotos creada por el usuario, con nombre, descripción opcional y una foto de portada opcional (`cover_photo_id`). Pertenece a un User y se relaciona con Photo mediante la tabla pivote `album_photo` (N:M), de modo que una foto puede estar en varios álbumes.

- Borrar un álbum no borra sus fotos: solo se eliminan las filas de `album_photo`.
- Borrar una foto la quita de los álbumes y deja sin portada al álbum que la usaba (`cover_photo_id` a `NULL`).
- `AlbumController` gestiona crear, editar y borrar álbumes, y añadir o quitar fotos (rutas `/albums`). Los álbumes se muestran en la página de fotos.

### Archivos y ciclo de vida

`App\Services\PhotoFileManager` centraliza las rutas y el borrado:

```text
Original pendiente   disco local    photo-processing/{photo_id}/original.{ext}
Imagen procesada     disco photos   photos/{photo_id}/*.webp (imagen y miniatura)   storage/app/private
Fotos anteriores     disco public   photos/{photo_id}/*.webp (misma ruta relativa)  hasta migrarlas
```

### Acceso a los archivos (privado)

Las fotos procesadas se guardan en el disco privado `photos` (`storage/app/private`), que no está bajo `public/storage`. El navegador nunca recibe rutas de disco: las props de Inertia llevan `url` y `thumbnail_url` (o `cover`, `cover_url`), generadas por `Photo::fileUrl()`:

```text
GET /photos/{photo}/{variant}?v={hash}      variant: full | thumb      (PhotoFileController)
```

1. `auth`: sin sesión redirige al login (401 en peticiones JSON).
2. `PhotoPolicy::view`: la foto es del usuario y también lo son su viaje, su visita, su entrada del diario y los álbumes que la contienen.
3. La foto está `ready` y la ruta guardada pertenece a `photos/{id}/` (sin `..`).
4. `PhotoFileManager::locate()` busca el archivo en el disco privado y, si no está, en el público (fotos anteriores sin migrar).
5. Respuesta con `Cache-Control: private, max-age=3600`, `Last-Modified` (permite 304), `X-Content-Type-Options: nosniff` y `Content-Disposition: inline; filename=photo-{id}-{variant}.webp`.

Cualquier fallo de los pasos 2 a 4 (foto ajena, inexistente, sin procesar, sin archivo o con una ruta manipulada) devuelve el mismo 404, para no revelar qué IDs existen. El parámetro `v` cambia si se regenera el archivo e invalida la caché del navegador.

### Migración de las fotos anteriores

Las fotos procesadas antes de este cambio siguen en el disco público y, mientras no se migren, también son accesibles por `/storage/photos/...` para quien conozca la URL. La migración solo mueve archivos: las rutas relativas no cambian, así que no hay cambios en la base de datos.

```bash
php artisan photos:move-to-private                   # 1. simulación (no cambia nada)
php artisan photos:move-to-private --copy            # 2. copia al disco privado, verifica SHA-256; no borra
php artisan photos:move-to-private --remove-public   # 3. borra las copias públicas idénticas (pide confirmación)
php artisan photos:move-to-private --revert=<manifiesto>   # deshace la fase 3 o la 2
```

- Cada fase escribe un manifiesto en `storage/app/photo-migration/`. Entre las fases 2 y 3 la aplicación ya sirve la copia privada y la pública sigue intacta.
- Los archivos públicos que no pertenecen a ninguna foto (huérfanos) solo se listan; su limpieza es una decisión aparte.
- Antes de la fase 3, hacer una copia de seguridad de `storage/app/public/photos`.

- La extensión del original se deduce del contenido, no del nombre enviado.
- La ausencia de EXIF (habitual en PNG y WebP) es válida: fecha y coordenadas quedan a `null`.
- Las imágenes WebP generadas no conservan EXIF; las coordenadas solo se guardan en la base de datos.
- Si el procesamiento falla, se borran las imágenes a medio generar y la foto queda en `failed`. Cuando la cola agota los reintentos, también se borra el original.
- Al borrar una foto o un viaje se borra primero el registro y después los archivos. Si el almacenamiento falla, quedan archivos huérfanos (se registra un aviso), pero nunca registros que apunten a archivos inexistentes.
- Si una foto se borra antes de procesarse, el trabajo de la cola solo elimina el original.
- El procesamiento usa la cola `database` (`QUEUE_CONNECTION=database`). Sin un worker en marcha las fotos se quedan en `pending`. En desarrollo:

```bash
composer run queue        # equivale a: php artisan queue:work --queue=default --sleep=2
```

- `ProcessPhoto` reintenta hasta 3 veces (esperas de 10 s y 60 s) con un tiempo máximo de 120 s por intento. Entre reintentos la foto sigue `pending`; al agotarlos queda `failed`, sin imágenes a medias y sin el original. Si el worker muere a mitad, el trabajo se libera tras `retry_after` (90 s) y cuenta como un intento.
- La página del viaje muestra cuántas fotos están en proceso y se actualiza sola cada 5 s mientras queden pendientes.
- Formatos admitidos: JPEG, PNG y WebP (HEIC no: GD no puede leerlo).

---

# 10. Frontend

La aplicación utilizará Vue 3 + TypeScript mediante Inertia.

Estructura prevista:

```text
resources/js/
├── components/
├── layouts/
├── pages/
├── types/
└── ...
```

## Pages

Representarán las páginas principales de la aplicación.

Ejemplos futuros:

```text
Dashboard
Trips/Index
Trips/Show
Map/...
Journal/...
Photos/...
Discover/...
```

## Components

Contendrán componentes reutilizables de interfaz.

No deben contener lógica de negocio que corresponda al backend.

## Types

Los tipos TypeScript deberán reflejar los datos que realmente consume el frontend.

No se duplicará innecesariamente la lógica de dominio de Laravel en TypeScript.

---

# 11. Inertia frente a API REST

La aplicación principal utilizará Inertia.

No se construirá una API REST completa para todas las entidades únicamente por seguir una arquitectura API-first.

Esto evita mantener simultáneamente:

```text
Vue → REST API → Laravel
```

cuando realmente necesitamos:

```text
Vue → Inertia → Laravel
```

La API REST se utilizará cuando exista una necesidad concreta, por ejemplo:

- integración externa;
- aplicación móvil futura;
- servicios independientes;
- integraciones con IA;
- endpoints específicos consumidos fuera de Inertia.

La existencia futura de una API no debe condicionar innecesariamente el MVP.

---

# 12. Integraciones externas

VyaMap podrá utilizar APIs externas para enriquecer información.

Principio:

> VyaMap owns its data.

Las APIs externas pueden proporcionar:

- información de ciudades;
- coordenadas;
- aeropuertos;
- información de vuelos;
- lugares;
- recomendaciones.

Pero los datos necesarios para representar el historial del usuario deben persistirse en VyaMap.

La aplicación no debe depender de que un proveedor externo esté disponible para mostrar el historial existente.

Arquitectura conceptual:

```text
External API
     ↓
Validation / Mapping
     ↓
VyaMap Database
     ↓
Application
```

No:

```text
Application
     ↓
External API
     ↓
Render every time
```

## Geoapify (ciudades)

- El catálogo de ciudades, países y alias es compartido por todos los usuarios, por lo que `POST /cities` no acepta datos geográficos del cliente: solo `external_id` y `search_query`.
- El servidor comprueba que ese `external_id` aparece en los resultados de Geoapify para esa búsqueda y guarda los datos devueltos por Geoapify. Los resultados se guardan en caché 10 minutos para no repetir la llamada.
- Un alias solo se aprende a partir de una búsqueda que Geoapify ha asociado realmente a esa ciudad.
- Las llamadas tienen timeout (3 s de conexión, 5 s en total). Si Geoapify falla o no hay clave, la búsqueda devuelve solo resultados locales y el alta de ciudades responde 503. Nunca se registra la URL, porque contiene la clave.
- Nombre de la ciudad: se toma el campo `city` del resultado y, si no es un nombre válido, `name`. No son válidos los valores vacíos, los que no tienen ninguna letra y las etiquetas de OSM `yes`, `no`, `true` y `false` (Geoapify devuelve `city = "yes"` para la Ciudad del Vaticano). Si ningún candidato es válido, el resultado se descarta: no se ofrece ni se puede guardar.
- La clave se lee de `GEOAPIFY_API_KEY` (`config('services.geoapify.key')`) en el archivo de entorno que se esté usando: `.env`, `.env.manual` (`--env=manual`) o `.env.testing` (`--env=testing`). Sin ella solo se encuentran las ciudades del catálogo local (las que tienen aeropuerto, más las añadidas antes). Los tests no la necesitan: `Tests\TestCase` fija una clave ficticia y bloquea las peticiones reales.
- Límites: 60 búsquedas de ciudades o aeropuertos por minuto y 20 altas de ciudades por minuto por usuario; la búsqueda de aeropuertos devuelve como máximo 20 resultados.
- Duplicados: al crear una ciudad desde Geoapify se reutiliza una ciudad no fusionada del mismo país, con el mismo nombre normalizado y a menos de `config('cities.same_city_max_distance_km')` (25 km). Si esa ciudad no tiene identidad externa, se le asigna. El par `(external_provider, external_id)` es único.
- Fusión de duplicados existentes: `php artisan cities:reconcile {duplicada} --into={canónica}`. Por defecto solo simula; `--execute` aplica en una transacción y pide confirmación. Comprueba país, nombre o alias, distancia y conflictos de visitas, y solo asigna el identificador externo si Geoapify lo confirma (o se omite con `--without-external-id`). La ciudad duplicada no se borra: queda con `merged_into_id` y desaparece de búsquedas y selecciones. Cada fusión queda en `city_reconciliations` y se revierte con `--revert={id} --execute`.

---

# 13. Mapas y datos geográficos

Los mapas se implementarán posteriormente con Leaflet.

Los datos geográficos de países no se almacenarán como geometrías completas en MySQL.

Para representar fronteras se utilizará un recurso estático GeoJSON/TopoJSON asociado al ISO code del país.

Ejemplo:

```text
ES → Spain geometry
FR → France geometry
IT → Italy geometry
```

La base de datos almacenará la información necesaria para identificar y localizar las entidades.

Las coordenadas de Cities, Airports y Places sí forman parte del modelo de datos.

---

# 14. IA

La IA será una capa posterior de VyaMap.

Principio fundamental:

> AI proposes. Laravel validates. User confirms.

La IA podrá:

- proponer destinos;
- crear borradores de viajes;
- sugerir rutas;
- analizar preferencias;
- generar recomendaciones.

Pero nunca debe modificar directamente el dominio persistente sin pasar por las reglas de Laravel y la confirmación del usuario cuando corresponda.

Flujo:

```text
User
 ↓
AI
 ↓
Proposal / Draft
 ↓
Laravel validation
 ↓
User confirmation
 ↓
Persistent domain data
```

---

# 15. Persistencia

MySQL es la fuente persistente principal.

Las relaciones críticas están protegidas mediante foreign keys.

Comportamientos de eliminación:

- User → Trips / Visits / Flights / Photos: CASCADE
- Trip → Visits / Photos / Journal / listas de Google Maps (tabla pivote): CASCADE
- Trip → Flights: SET NULL. Al borrar un viaje, `TripController@destroy` conserva sus vuelos como independientes en una transacción; si el viaje no estaba cancelado, los vuelos que ya contaban como realizados quedan confirmados (`confirmed_at`).
- Journal → JournalDays: CASCADE
- JournalDay → JournalEntries: CASCADE
- JournalEntry → Photos: SET NULL
- Visit → Photos: SET NULL
- Place → Photos: SET NULL
- Country / City / Airport / Place: restricciones para evitar eliminar entidades referenciadas accidentalmente

No se utilizarán Soft Deletes inicialmente.

Se introducirán únicamente si aparece una necesidad real de recuperación o auditoría.

---

# 16. Datos derivados

No se almacenarán como contadores persistentes los datos que puedan calcularse desde el dominio.

Ejemplos:

- número de países visitados;
- número de ciudades visitadas;
- número de viajes;
- estadísticas del historial.

Estos datos se derivarán de Visits, Trips y demás entidades relevantes.

La fuente de verdad será siempre el dominio persistente.

## Estadísticas de viaje

`App\Services\TravelStatistics` es la única fuente de las cifras derivadas. Los controladores (Home, Trips, Passport y Flight History) le pasan los datos ya cargados y las páginas solo eligen el periodo que muestran; no recalculan nada en Vue.

- **Qué cuenta:** solo los viajes completados y las visitas de esos viajes. Un vuelo nunca marca un país como visitado.
- **Días viajados:** días naturales, inicio y fin incluidos, como unión de los intervalos de los viajes completados. Los solapes no duplican días y un viaje que cruza de año reparte sus días entre los dos años.
- **Año natural:**
  - un viaje cuenta y se lista en el año en que empieza (regla previa de «viajes por año»), pero sus días se reparten por año natural;
  - una visita pertenece a cada año que toca, así que sus países cuentan en cada uno (`touchesYear` en `resources/js/utils/tripDates.ts` filtra las listas con la misma regla).
- **Primeras visitas:** el año de la visita fechada más antigua de cada país. Las visitas sin fechas cuentan en el total, pero no en ningún año, y se informan como `undated_visits`.
- **Continentes:** `config/continents.php`, generado a partir del CSV local de países y revisado a mano. Un código sin continente no suma a ninguno y se informa aparte.
- **Vuelos:** solo los `flown`. La distancia solo cuenta con coordenadas en ambos aeropuertos y el tiempo en el aire solo con las dos horas. Si falta el dato en todos los vuelos, la cifra es `null` (desconocida), nunca 0. Los aeropuertos se cuentan por identidad, no por código.
- **Ruta más repetida:** no se calcula. No hay una definición de negocio (¿importa el sentido del viaje?) y queda pendiente de decisión.

## Porcentaje de países visitados

El porcentaje ("% of the world") solo cuenta Estados soberanos, tanto en el numerador como en el denominador. El criterio y las listas están en `config/countries.php` y se aplican con `App\Support\SovereignCountries`:

- **Soberanos:** los 193 Estados miembros de la ONU, por su código ISO 3166-1 alfa-2. Se pueden añadir otros en `additional_sovereign`.
- **Pendientes de decisión** (no cuentan hasta que se decida): VA, PS, XK, TW, EH, CK, NU.
- **Códigos especiales** (no son países): ZZ, XP.
- **Resto** (PR, HK, GL, RE, AQ…): territorios dependientes o zonas sin Estado propio; no cuentan.

La clasificación no modifica el catálogo: los territorios siguen apareciendo en el mapa y en el contador de países visitados, pero no en el porcentaje.

Un vuelo o una escala no marcan una ciudad ni un país como visitados: solo cuentan las Visits de viajes completados.

---

# 17. Seguridad

Principios mínimos:

- autenticación mediante Laravel;
- rutas protegidas mediante middleware;
- autorización mediante ownership/Policies;
- validación backend;
- protección contra mass assignment;
- uso de Form Requests;
- foreign keys;
- no confiar en identificadores enviados por el frontend;
- no exponer información de otros usuarios;
- almacenamiento seguro de fotografías;
- configuración mediante variables de entorno;
- no almacenar secretos en Git.

Las funcionalidades de IA y APIs externas deberán utilizar credenciales almacenadas mediante `.env` y configuración segura.

---

# 18. Testing

Cada funcionalidad importante seguirá el ciclo:

```text
Requirement
    ↓
Design
    ↓
Implementation
    ↓
Test
    ↓
Review
    ↓
Commit
```

Los tests deben cubrir especialmente:

- reglas de negocio;
- autorización;
- ownership;
- validaciones;
- relaciones críticas;
- operaciones destructivas;
- integraciones cuando existan.

Estado actual (15-10-2026):

```text
PHPUnit: 539 tests, 3771 assertions, 0 failures
Vitest:   63 tests, 0 failures
```

Los tests de componentes Vue están en `tests/js` y se ejecutan con `npm run test:js`.

Reglas de aislamiento:

- Los tests solo usan la base de datos de `.env.testing`.
- Los tests de fotos usan `Storage::fake('local')` y `Storage::fake('public')`: nunca escriben en `storage/app`.
- `Tests\TestCase` activa `Http::preventStrayRequests()`: cualquier llamada externa sin `Http::fake()` hace fallar el test.
- Las imágenes de prueba con EXIF se generan con `Tests\Support\SyntheticExifJpeg` (`php tests/Fixtures/make-synthetic-exif.php`). Solo contienen una fecha y unas coordenadas ficticias. No se deben añadir fotos reales al repositorio.

La suite debe mantenerse pasando antes de avanzar a cambios estructurales importantes.

## URLs legibles

- **Viajes:** `/trips/{id}-{slug}` (`/trips/386-new-york`, `/trips/386-new-york/edit`, `/trips/386-new-york/photos`). El id identifica el viaje y el slug se deriva del nombre con `Str::slug`; no se guarda, así que renombrar un viaje no rompe enlaces (`Trip::getRouteKey` y `resolveRouteBinding`). Sin slug posible (un nombre solo con emojis), la clave es el id.
- **Países:** `/countries/{slug}` (`/countries/italy`), derivado del nombre. Si dos países dieran el mismo slug, ambos llevan su código ISO (`congo-cg`). El mapa de slugs se calcula una vez por petición y se invalida al guardar o borrar un país (`Country::slugMap`).
- **Compatibilidad:** las URL anteriores (`/trips/386`, `/countries/1147`) y los slugs desactualizados siguen funcionando: las páginas GET redirigen con 301 a la canónica, conservando la query (`Controller::canonicalRedirect`).
- **Autorización primero:** la redirección se hace después de `authorize`; un intruso recibe 403 sin `Location`, así que la URL canónica nunca revela el nombre de un viaje ajeno (`tests/Feature/ReadableUrlTest.php`).
- **Escrituras:** `PUT`, `PATCH`, `POST` y `DELETE` aceptan el id solo o la clave completa; el frontend usa el id, que no depende del nombre.
- **Frontend:** `resources/js/utils/routes.ts` (`tripPath`, `countryPath`). Su `slugify` coincide con `Str::slug` en nombres latinos; en otros alfabetos, la página redirige a la canónica. Los países usan el `slug` que envía el servidor.

## Seguridad

### Cuentas y contraseñas

- El registro público solo está activo en `local` (`auth.registration_enabled`, configurable con `REGISTRATION_ENABLED`). Con el registro desactivado, `/register` devuelve 404 y la pantalla de login oculta el enlace; las cuentas se crean con `php artisan users:create "Nombre" correo@example.com`, que pide la contraseña de forma oculta y aplica las mismas reglas que el formulario.
- `REGISTRATION_MAX_USERS` (`auth.registration_max_users`) limita el total de cuentas: al alcanzarlo, `App\Support\Registration::isOpen()` cierra el registro igual que si estuviera desactivado. `CreateNewUser` vuelve a contar dentro de una transacción con bloqueo, para que dos altas simultáneas no superen el máximo. `users:create` no está sujeto al límite. `php artisan users:list` muestra las cuentas (solo lectura, sin credenciales) y el estado del registro.
- `EMAIL_VERIFICATION` (`auth.email_verification`; por defecto activa salvo en `local`): `User` implementa `MustVerifyEmail` y todas las rutas privadas llevan `verified` (`App\Http\Middleware\EnsureEmailIsVerified`, que no exige nada si la opción está desactivada). Sin verificar, la cuenta solo ve `/email/verify` (reenviar el enlace o cerrar sesión), las páginas redirigen ahí y las peticiones JSON reciben 403. Fortify registra el enlace firmado (`verification.verify`, 60 minutos) y el reenvío (`verification.send`, limitado en `GuardAuthEndpoints` a 3 cada 15 minutos por cuenta y 6 por minuto por IP). `users:create` crea cuentas verificadas y `users:verify` verifica una cuenta concreta.
- `UPLOADS_ENABLED` (`uploads.enabled`; desactivado en todos los entornos salvo `UPLOADS_ENABLED=true` explícito): con `false`, `POST /photos` devuelve 403 (`StorePhotoRequest::authorize`) y el avatar se rechaza con `prohibited` en el registro y en el perfil. La prop compartida `features.uploads` solo oculta los campos en la interfaz; la restricción la aplica el servidor. Nadie puede subir fotos ni avatares, tampoco las cuentas que ya los tienen; lo subido antes se sigue viendo y se puede borrar. La prop `features.photos` indica si la cuenta tiene fotos: sin ellas, la navegación no muestra Photos. Los tests del código de subida (`PhotoTest`, `PhotoQueueTest`, `UploadSecurityTest`) lo activan solo dentro del test.
- Contraseñas (`Password::defaults()` en `AppServiceProvider`): mínimo 10 caracteres y no filtradas según Have I Been Pwned. Solo se envían los 5 primeros caracteres del hash SHA-1; si el servicio no responde, la contraseña no se bloquea. En los tests el verificador se sustituye por uno local (`Tests\TestCase`).
- Límites de frecuencia (`App\Http\Middleware\GuardAuthEndpoints`, aplicado a las rutas de Fortify):

| Ruta | Límite |
|---|---|
| `POST /login` | 5 por minuto por correo e IP (Fortify) |
| `POST /register` | 5 por minuto y 20 por hora por IP |
| `POST /forgot-password` | 5 por minuto por IP y 3 cada 15 minutos por correo |
| `POST /reset-password` | 5 por minuto por IP |

### Subidas de imágenes

- **Avatares** (`App\Services\AvatarImageService`): se recodifican a WebP de como mucho 512 px con Intervention, lo que elimina EXIF, GPS y XMP; el archivo original nunca se guarda. El avatar anterior se borra solo después de guardar el nuevo.
- **Dimensiones** (`App\Rules\ImageWithinPixelLimit`): GD necesita unos 4,5 bytes por píxel y esa memoria no cuenta en el `memory_limit` de PHP (medido: 48 MP → unos 220 MB). Las fotos admiten hasta 50 MP y 12 000 px por lado; los avatares, 25 MP. `ProcessPhoto` vuelve a comprobarlo antes de decodificar y, si se supera, marca la foto como `failed` sin reintentos.
- **Frecuencia**: `POST /photos` admite 30 subidas por minuto y usuario.

### Autorización y validación

- Toda ruta que recibe un ID comprueba la propiedad: `TripPolicy` (`view`, `update`, `delete`) para viajes y todo lo que cuelga de ellos (visitas, vuelos, listas de Google Maps), y comprobaciones directas de `user_id` en fotos y álbumes. Visitas y vuelos usan `scopeBindings()`: un ID de otro viaje da 404. `Tests\Feature\SecurityIsolationTest` recorre todas estas rutas como intruso y sin sesión.
- Las rutas generales de vuelos (`/flights`, `/flights/{flight}` y `/flights/{flight}/confirmation`) usan `FlightPolicy` y responden 403 a otro usuario. Un `trip_id` ajeno se rechaza en la validación. Están cubiertas por `Tests\Feature\FlightManagementTest`, no por `SecurityIsolationTest`.
- Las operaciones de escritura sobre listas de Google Maps exigen `update`, no `view`.
- Crear una entrada en el diario de otro usuario devuelve 403; un día inexistente, 422.
- Quitar de un álbum una foto que no está en él devuelve 404.
- Longitudes máximas: descripción del viaje y notas de la visita, 5000 caracteres (caben en `TEXT` incluso con caracteres de 4 bytes); contenido del diario, 50 000.
- Leaflet interpreta como HTML el texto de `bindTooltip`/`bindPopup`: todo dato que llegue del servidor (ciudades, códigos, números de vuelo) pasa por `escapeHtml` (`resources/js/utils/escapeHtml.ts`) antes de llegar a un tooltip. En el resto de la interfaz no se usa `v-html`.
- No hay API: se eliminó `GET /api/user`, que devolvía el modelo de usuario completo. `User` oculta `password`, `remember_token` y las columnas `two_factor_*` al serializarse.

### Herramientas de desarrollo

- **Inertia DevTools** graba las props de cada página (correo, viajes, rutas de fotos) en `storage/inertia-devtools` y, en `local`, sirve `/_inertia/devtools/entries` sin autenticación. `AppServiceProvider::register()` lo fuerza a desactivado en cualquier entorno distinto de `local`, aunque `INERTIA_DEVTOOLS_ENABLED=true`, y entonces sus rutas no se registran. En local, `.env.example` lo deja desactivado: activarlo solo mientras se use y vaciar después `storage/inertia-devtools` (el proyecto está en una carpeta sincronizada con iCloud).
- **Ignition** está en `require-dev`: no se instala con `composer install --no-dev` y sus soluciones ejecutables solo funcionan con `APP_DEBUG=true` en `local`.

### Dependencias de npm

`npm audit` se revisa en local, nunca con `npm audit fix` en el servidor (ver `deployment.md`). Antes de decidir, se comprueba si el paquete llega al bundle del navegador mirando los sourcemaps de un `vite build`, sin fiarse de si está en `dependencies` o en `devDependencies`. axios no llega: el frontend no lo importa (Inertia 3 usa su propio cliente XHR, que ya envía `X-XSRF-TOKEN`, y el código propio usa `fetch`). Sigue instalado porque es dependencia *peer* de `@inertiajs/core` y de `laravel-precognition`; Inertia solo lo cargaría, en un chunk aparte, si se configurase su adaptador de axios.

#### Globo del perfil

`ProfileTravelGlobe` usa three-globe solo con el globo, la atmósfera y los polígonos de países. three-globe importa además el renderer WebGPU (`three/webgpu` y `three/tsl`, para su capa de heatmaps) y `h3-js` (capas hexBin y hexPolygons), unos 260 KB con gzip que el globo no usa. `vite.config.js` los sustituye por los módulos de `resources/js/vendor-stubs/`, que exportan los mismos nombres y lanzan un error claro si se llaman. Si algún día se quieren esas capas, basta con quitar el alias correspondiente.

**Al actualizar three-globe (o three):** `tests/js/vendorStubs.test.ts` comprueba que los sustitutos exportan todo lo que three-globe importa de esos módulos y que no los usa de otra forma. Si falla, hay que revisar el uso nuevo antes de ampliar el sustituto. Después, abrir `/profile` con el build de producción y comprobar que el globo se dibuja sin errores en la consola.

**Riesgo aceptado (revisado el 2026-11-10): `minimatch` y `glob` vía `@vue/test-utils`.**

- **Cadena:** `@vue/test-utils` 2.5.1 → `js-beautify` 2.0.3 → `editorconfig` 3.0.2 → `minimatch` 10.2.x, y `js-beautify` → `glob` 13.0.x. Son 5 avisos moderados en `npm audit`: `minimatch` (DoS por amplificación de patrones, GHSA-g3hv-75wg-m249), `glob` (sigue recorriendo el disco tras un AbortSignal, GHSA-3g3m-2fjr-6pw4) y los heredados por `editorconfig`, `js-beautify` y `@vue/test-utils`.
- **Por qué no se puede explotar:** es una dependencia solo de los tests de Vitest y no llega al navegador ni al servidor. `@vue/test-utils` usa `js-beautify` para formatear cadenas en `wrapper.html()`. `editorconfig` y `glob` solo se usan para buscar ficheros `.editorconfig` en disco, y esa búsqueda no se ejecuta. Los patrones nunca vienen de fuera.
- **Por qué no se corrige:** no hay versión parcheada dentro de los rangos que declaran (`editorconfig` fija `minimatch ~10.2.4` y `js-beautify` pide `glob ^13`). `npm audit fix --force` propone bajar a `@vue/test-utils` 2.4.11, que es un retroceso y trae `glob` 10, también afectado. Los `overrides` forzarían versiones mayores que esos paquetes no declaran.
- **Cuándo revisarlo:** cuando salga una versión nueva de `editorconfig` o de `js-beautify` (o de `@vue/test-utils` que las actualice). Basta con `npm outdated` y `npm audit`.

### Lista para producción

- `APP_ENV=production`, `APP_DEBUG=false` y `APP_KEY` propia.
- `composer install --no-dev --optimize-autoloader`, `php artisan config:cache` y `php artisan route:cache`.
- HTTPS con `SESSION_SECURE_COOKIE=true`; `APP_URL` con `https://`.
- `REGISTRATION_ENABLED` sin definir (o `false`): las cuentas se crean con `php artisan users:create`. Si se abre el registro: `REGISTRATION_MAX_USERS` y, sin correo configurado, nada de verificación ni recuperación de contraseña (ver `deployment.md`).
- `UPLOADS_ENABLED` sin definir (desactivado): no se admiten fotos ni avatares nuevos.
- `INERTIA_DEVTOOLS_ENABLED` sin definir o `false`.
- Un worker de cola supervisado (Supervisor, systemd o el gestor del hosting) con `php artisan queue:work --queue=default --sleep=2` (los reintentos los fija `ProcessPhoto`), reiniciado con `php artisan queue:restart` en cada despliegue. Con unos 220 MB por foto de 50 MP, conviene un solo proceso por cada 512 MB libres.
- `TRUSTED_PROXIES` (ver `config/trustedproxy.php`) si hay balanceador o proxy inverso: `*` o las IP del proxy. Si no, Laravel generaría URLs `http://` detrás de un proxy HTTPS y los límites de frecuencia verían la IP del proxy. Laravel Cloud, Forge y Vapor se detectan automáticamente.
- Despliegue paso a paso, catálogo inicial y datos de demostración: [`deployment.md`](deployment.md).
- `LOG_LEVEL=warning` o superior; los logs no incluyen claves ni coordenadas.
- Fotos: las nuevas se guardan en el disco privado `photos` (`storage/app/private`), que debe estar fuera del document root y persistir entre despliegues. Las anteriores se migran con `php artisan photos:move-to-private` (ver «Migración de las fotos anteriores»).

## Fechas y zona horaria

`app.timezone` sigue en `UTC`: las fechas y horas se guardan en UTC. Lo que depende del calendario del usuario ("hoy", el próximo viaje y los días que faltan en el dashboard) se calcula en `app.user_timezone`, configurable con `APP_USER_TIMEZONE` (por defecto `Europe/Madrid`). Así, a las 00:30 en Madrid un viaje que empieza ese día ya cuenta como "hoy" aunque en UTC aún sea el día anterior.

## Configuración del entorno de testing

PHPUnit fija `APP_ENV=testing` en `phpunit.xml`, por lo que Laravel carga `.env.testing` en lugar de `.env`. Este archivo es local y está excluido de Git; nunca debe versionarse.

1. Copiar la plantilla:

```bash
cp .env.testing.example .env.testing
```

2. Configurar en `.env.testing` una base de datos exclusiva para pruebas, distinta de la de desarrollo y de la de producción (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). La base de datos debe existir antes de ejecutar los tests.

3. Generar una `APP_KEY` exclusiva para testing. La opción `--show` solo muestra una clave nueva y no modifica ningún archivo, por lo que `.env` queda intacto:

```bash
php artisan key:generate --show
```

Copiar manualmente el valor mostrado en `APP_KEY=` dentro de `.env.testing`.

4. Ejecutar la suite:

```bash
php artisan test
```

**Advertencia:** los tests que utilizan `RefreshDatabase` borran y reconstruyen todas las tablas de la base de datos configurada en `.env.testing`. Antes de ejecutarlos hay que comprobar que `DB_DATABASE` no apunta a una base de datos con datos reales.

Tampoco debe existir una caché de configuración (`bootstrap/cache/config.php`): si existe, Laravel ignora `.env.testing` y los tests usarían la configuración cacheada. Se elimina con `php artisan config:clear`.

---

# 19. Git y commits

Los cambios se realizarán en commits pequeños y coherentes.

Ejemplos:

```text
feat: add trip creation validation
feat: add visit ownership validation
test: cover flight date validation
docs: define application architecture
```

Evitar commits que mezclen cambios de arquitectura, visuales, migraciones y funcionalidades no relacionadas.

Cada commit debería representar una unidad comprensible del trabajo.

---

# 20. Evolución por fases

## V1 — My Travel History

Prioridad:

- Countries
- Cities
- Visits
- Trips
- Flights
- Map
- estadísticas derivadas

## V2 — My Travel Memories

Añadir:

- Photos
- Places
- Journals
- Journal Days
- Journal Entries
- timeline/memories

## V3 — Discovery

Añadir:

- recomendaciones;
- destinos;
- rutas;
- lugares;
- IA;
- Trip Drafts.

## V4 — Social

Funcionalidades sociales únicamente cuando exista una necesidad real:

- perfiles públicos;
- compartir viajes;
- interacción;
- seguimiento;
- contenido de otros usuarios.

La arquitectura actual no obliga a implementar esta fase.

---

# 21. Decisiones arquitectónicas clave

| Decisión | Elección |
|---|---|
| Arquitectura | Monolito modular Laravel |
| Backend | Laravel 13 |
| Base de datos | MySQL |
| ORM | Eloquent |
| Frontend | Vue 3 |
| Lenguaje frontend | TypeScript |
| Comunicación principal | Inertia |
| UI | Tailwind CSS (shadcn/ui figuraba en el diseño inicial, pero no se ha instalado) |
| API REST | Solo cuando exista una necesidad concreta |
| Autorización | Middleware + Policies/ownership |
| Validación | Form Requests |
| Lógica compleja | Services |
| Mapas | Leaflet |
| Geometría países | GeoJSON/TopoJSON estático |
| IA | Capa posterior |
| IA sobre dominio | Propuesta → validación → confirmación |
| Datos derivados | Calculados, no almacenados |
| Soft deletes | No inicialmente |
| Microservicios | No |
| Flight search engine | No |
| Social | Futuro |

---

# 22. Regla de evolución

Cualquier nueva funcionalidad debe responder primero a estas preguntas:

1. ¿Qué problema real resuelve?
2. ¿Pertenece al alcance de la fase actual?
3. ¿Qué entidad del dominio afecta?
4. ¿Qué regla de negocio introduce?
5. ¿Necesita persistencia?
6. ¿Necesita una integración externa?
7. ¿Qué autorización requiere?
8. ¿Cómo se probará?

Si una funcionalidad no tiene una necesidad clara, se pospone.

La arquitectura debe facilitar que VyaMap crezca sin convertirse en una acumulación de funcionalidades desconectadas.

---

## Estado del documento

**Proyecto:** VyaMap  
**Documento:** Architecture  
**Estado:** Aprobado para implementación  
**Stack:** Laravel 13 + MySQL + Vue 3 + TypeScript + Inertia + Tailwind
