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
- shadcn/ui
- Vite

## Desarrollo

- Git
- GitHub
- PHPUnit / Pest mediante la infraestructura de testing de Laravel

## Futuras tecnologías

- Leaflet para mapas
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
StoreTripRequest
StoreVisitRequest
StoreFlightRequest
StorePhotoRequest
StoreJournalEntryRequest
```

Los Form Requests pueden contener reglas relacionadas con el contexto de la petición, como fechas, existencia de recursos, ownership y coherencia entre entidades.

## Policies

Las Policies se utilizarán para centralizar la autorización sobre recursos a medida que se implementen las operaciones CRUD completas.

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

Trip 1:N Visit
Trip 1:N Flight
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

## Visits

Un Visit representa una declaración explícita de que el usuario ha visitado una City.

```text
Visit → City
```

La existencia de un vuelo no crea automáticamente una visita.

Una ciudad se considera visitada únicamente cuando existe un Visit correspondiente.

## Trips

Un Trip representa una experiencia de viaje concreta.

Puede contener:

- Visits
- Flights
- Photos
- Journal

Un Trip puede existir inicialmente sin Visits.

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

No existe una entidad Album.

Un álbum será una vista de frontend construida a partir de las relaciones de Photo.

Una Photo puede estar asociada opcionalmente a:

- Trip
- Visit
- Place
- JournalEntry

Esto permite reutilizar una fotografía en diferentes contextos sin introducir una entidad Album innecesaria.

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
- Trip → Visits / Flights / Photos: SET NULL
- Trip → Journal: CASCADE
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

Estado actual:

```text
11 tests
30 assertions
0 failures
```

La suite debe mantenerse pasando antes de avanzar a cambios estructurales importantes.

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
| UI | Tailwind CSS + shadcn/ui |
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
**Stack:** Laravel 13 + MySQL + Vue 3 + TypeScript + Inertia + Tailwind + shadcn/ui
