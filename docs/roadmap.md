# VyaMap — Roadmap

Plan de desarrollo de la versión V1 («My Travel History», ver la sección 20 de [`architecture.md`](architecture.md)).

El plan F0–F6 se acordó el 15-10-2026 en la auditoría del bloque 2 («estadísticas, viajes futuros y registro de vuelos»). Hasta ahora solo existía en esa conversación; este documento lo recoge tal como se aprobó, con el estado real de cada fase.

Leyenda: **Completado** (implementado y con pruebas) · **En curso** (implementado en parte) · **Cerrado** (sin trabajo propio pendiente) · **Pendiente** (aún no empezado) · **Aplazado** (fuera del plan actual).

## Resumen

| Fase | Prioridad | Contenido | Estado |
|---|---|---|---|
| Bloque 1 | — | Definiciones de métricas y coherencia de datos | Completado |
| F0 | P0 | Estado de los viajes | Completado |
| F1 | P1 | Vuelos independientes y gestión de vuelos | Completado |
| F2 | P1 | Formulario simplificado y vuelos sin horas | Cerrado: cubierto por F1, salvo «repetir ruta» (pasa a F6) |
| — | — | Corrección del formulario de horas | Completado |
| F3 | P1 | Estadísticas | Completado (15-10-2026; commits `1f8b079` y `237a3a4`) |
| F4 | P2 | Reorganización de páginas | En curso: estadísticas conectadas; falta la reorganización |
| F5 | P2 | Sugerencias | En curso: vuelos independientes en un viaje (sugerencias, búsqueda, asociar y quitar) |
| F6 | P3 | Estacionalidad, repeticiones, CSV, «repetir ruta» y sugerencia de aerolínea | Pendiente |
| Bloque B | — | Catálogo de aerolíneas | Aplazado |
| — | — | Lugares de interés en el itinerario | Fuera de V1 (posible estudio en V2) |

## Completado

### Bloque 1 — Definiciones

- Utilidades compartidas del frontend para fechas de viaje, horas de vuelo en la hora local del aeropuerto y estado de los países.
- Estados soberanos y territorios contados por separado (porcentaje sobre 193).
- Profile como página de cuenta; el globo se carga bajo demanda.

### F0 — Estado de los viajes

- `trips.status` (`planned`, `completed`, `cancelled`) con su migración y relleno.
- Transiciones manuales explícitas; un viaje nunca se completa solo por la fecha.
- «Visitado = viaje completado» en todas las estadísticas y mapas; lo planificado en violeta; lo cancelado no cuenta.

### F1 — Vuelos independientes y gestión de vuelos

- Rutas generales `/flights` junto a las anidadas en el viaje, `FlightPolicy`, validación única (`FlightRequest`) y gestión en Flight History y en la ficha del viaje.
- Al borrar un viaje, sus vuelos se conservan (`SET NULL`).
- `departure_date`, horas y número de vuelo opcionales, confirmación y estado único del vuelo (`Flight::status()`).

Reglas detalladas: secciones 9 y 15 de [`architecture.md`](architecture.md).

### Corrección del formulario de horas

Las horas de los vuelos pasan a campos de texto en formato de 24 horas (`HH:MM`), validados al salir del campo y al guardar, y con tests de componente (Vitest).

### F3 — Estadísticas

`App\Services\TravelStatistics`, sin cambios de base de datos. Reglas en la sección «Estadísticas de viaje» de [`architecture.md`](architecture.md).

- Días viajados: unión de los intervalos de los viajes completados, repartida por año natural.
- Países y territorios por año, primeras visitas por año y total histórico.
- Continentes con `config/continents.php` (los 193 Estados cubiertos; códigos especiales fuera).
- Vuelos realizados: número, km, tiempo en el aire con su cobertura, aeropuertos y ciudades distintos, vuelo más largo y aeropuertos más frecuentes.
- Pruebas: `tests/Unit/TravelDaysTest.php`, `tests/Feature/TravelStatisticsTest.php` y `tests/js/tripDates.test.ts`.

**Fuera de F3:** la ruta más repetida. No hay una definición de negocio (¿cuenta igual MAD → JFK que JFK → MAD?), así que queda pendiente de decisión.

## Cerrado

### F2 — Formulario simplificado

Alcance previsto: formulario simplificado, vuelos sin horas, «repetir ruta», vuelta y conexión; migración de `departure_date`, campos opcionales y relleno; pruebas de ordenación, años, duración nula y relleno.

F1 incorporó todo menos «repetir ruta»:
- la migración de `departure_date` con relleno;
- horas y número de vuelo opcionales;
- el formulario compartido con «Save & add return» y «Save & add connection»;
- el orden cronológico, los años por fecha local de salida y la duración nula sin horas.

**«Repetir ruta» pasa a F6.**

## En curso

### F4 — Reorganización de páginas (P2)

**Hecho:** las estadísticas de F3 están conectadas.
- **Home:** km volados y franja «This year» (días viajando y países nuevos).
- **Trips:** cabecera con días sin solapes, países, territorios y ciudades.
- **Passport:** cifras del periodo, días viajando, continentes y «Countries discovered by year».
- **Flight History:** cifras por año y por vínculo, vuelo más largo y aeropuertos más frecuentes, todo calculado en el servidor.
- Los filtros por año coinciden con el servidor: los viajes, por año de inicio; los días y las visitas, por año natural.

**Pendiente:** la reorganización completa según la distribución aprobada (abajo), por ejemplo las pestañas Upcoming / Past de Trips, la sección separada «Upcoming flights» o retirar de Passport los contadores que ya están en otras páginas.

Distribución por página aprobada en el plan (se mantienen el estilo y los mapas ya validados):

- **Home** («cómo voy y qué viene»):
  - tarjeta del viaje en curso o próximo y aviso de viajes pendientes de confirmar;
  - una sola fila de cifras;
  - «Your World»;
  - «Este año» (días viajando y países nuevos);
  - viajes recientes, fotos, y mapa de vuelos con los últimos 3 vuelos.
  - «Trips by year» pasa a Trips.
- **Passport** («progreso geográfico»):
  - sellos, mapa, progreso (países sobre 193, territorios, continentes), países descubiertos por año y primeras visitas recientes;
  - se retiran los contadores de viajes, vuelos y km, y «Trips by year».
- **Flight History** («todo sobre vuelos»):
  - cabecera con vuelos, km, vueltas al mundo, aeropuertos y horas en el aire;
  - mapa con filtro por año y por tipo;
  - destacados: ruta más repetida, vuelo más largo y aeropuertos más frecuentes;
  - lista con añadir, editar y borrar;
  - sección separada de «Upcoming flights».
- **Countries** («consulta»):
  - grupos de países y territorios;
  - en cada tarjeta: ciudades, viajes, primera y última visita, y «been here N times»;
  - los países solo planificados, en violeta y sin contar.
- **Detalle de país**: ciudades con las fechas de cada visita y enlace a cada viaje.
- **Trips** («historial y planes»):
  - pestañas Upcoming / Past, y Cancelled plegado;
  - cabecera con viajes, días viajando (unión de intervalos), países y ciudades;
  - «Trips by year» y estacionalidad.
- **Ficha del viaje**: etiqueta de estado y acciones, mapa e itinerario en violeta si está planificado, y aviso de confirmar tras la fecha de fin.

Algunas piezas ya existen por F0 y F1 (por ejemplo, las acciones de estado de la ficha del viaje y la gestión de vuelos en Flight History). F4 consiste en reorganizar las páginas según esta distribución.

## Pendiente

### F5 — Sugerencias (P2)

- Sugerir la visita de cada foto: la fecha de la foto cae dentro de la visita y su GPS está cerca de la ciudad. Siempre con confirmación, nunca automático; las coordenadas solo se comparan en el servidor.
- ~~Sugerir el viaje para un vuelo independiente cuyas fechas caen dentro de un viaje.~~ Hecho al revés, desde el viaje: al crearlo o editarlo se sugieren los vuelos independientes de sus fechas (±1 día), y la ficha permite buscar, asociar y quitar vuelos existentes (`TripFlightService`, [`architecture.md`](architecture.md) §9). La regla D3 (salida dentro de las fechas del viaje) pasa de bloqueo a aviso.
- «Añadir visita a [destino]» después de un vuelo, con fechas sugeridas.

### F6 — Carga y análisis (P3)

- Estacionalidad visible y repeticiones.
- Importar vuelos pegando un CSV (fecha, origen IATA, destino IATA, número opcional), con vista previa, confirmación y control de duplicados (misma ruta y fecha).
- **«Repetir ruta»**, trasladada desde F2.
- Sugerir la aerolínea (depende de la decisión sobre el bloque B).

**Propuesta para la importación CSV** (no implementada; necesita decisiones):
- **Formato:** `fecha,origen,destino[,numero]`, con fecha local `YYYY-MM-DD` y códigos IATA; horas opcionales en una versión posterior.
- **Flujo:** subir o pegar el CSV → validación completa sin guardar nada → vista previa con errores por línea y duplicados marcados → confirmación explícita → guardado en una transacción.
- **Validaciones:** aeropuertos existentes por IATA (un código desconocido o ambiguo es un error, nunca se adivina), origen distinto de destino y fecha válida.
- **Duplicados:** mismo usuario, misma ruta y misma fecha de salida, ya sea en la base de datos o dentro del propio archivo.
- **Decisiones pendientes:**
  - ¿Los vuelos importados se confirman como realizados (`confirmed_at`) o quedan pendientes?
  - ¿Se asocian a un viaje cuando la fecha cae dentro de uno, o siempre como independientes (F5 lo sugeriría)?
  - ¿Cuál es el tamaño máximo del archivo?

## Aplazado

### Bloque B — Catálogo de aerolíneas

Idea general aprobada:
- catálogo `airlines`, con `flights.airline_id` opcional y un selector con búsqueda;
- estadísticas agrupadas por ID;
- se conserva el texto actual de `airline` durante la migración;
- nunca se deduce la compañía del prefijo del número de vuelo.

Se retomará cuando se decida; no hay nada implementado.

### Lugares de interés en el itinerario

Decisión de V1: una visita representa una ciudad o localidad, nunca un castillo, monumento o museo (ver «Alcance en V1» en [`architecture.md`](architecture.md)). No hay nada implementado ni planificado; incorporar lugares de interés al itinerario podrá estudiarse en V2, junto a la entidad `Place` prevista para esa fase.
