# Generación de los CSV del catálogo

Scripts que regeneran los cinco CSV de `database/data/catalog/` solo desde fuentes de dominio público o CC0. El método y la comparación con los datos anteriores están en el [README del catálogo](../README.md).

## Scripts

| Script | Qué hace |
|---|---|
| `gen.py` | Punto de entrada. Descarga las fuentes (si faltan) en `downloads/` y escribe `countries.csv`, `countries_overrides.csv`, `vyamap_airport_city_mapping.csv`, `airports.csv` y `airport_timezones.csv`. Muestra cuántas ciudades y zonas cambian respecto a los archivos anteriores. |
| `cities.py` | Decide la ciudad a la que sirve cada aeropuerto: ciudad servida de Wikidata (P931), regla del nombre comercial, regla de estabilidad y, si no hay ciudad utilizable, el `municipality` de OurAirports. |
| `wikidata.py` | Descarga de Wikidata, por lotes y con reintentos, lo que necesita `cities.py`: aeropuertos, ciudades servidas, sus tipos, nombres, alias, coordenadas, país y enlaces a Wikipedia. |
| `assign.py` | Decide la zona horaria IANA de cada aeropuerto: excepción por aeropuerto, regla de región, país con una sola zona y, si quedan varias candidatas, desfase de Natural Earth en el punto y cercanía. |
| `build_rules.py` | Las reglas a mano: para las zonas horarias, 258 por región, 3 zonas por defecto de país y 38 excepciones por aeropuerto, cada una con su justificación en la base de datos tz; para las ciudades, 3 excepciones (`CITY_OVERRIDES`) y 4 aeropuertos cuya ciudad servida en Wikidata es una unidad administrativa (`ADMINISTRATIVE_P931`). Ejecutado directamente, comprueba que todas las zonas existen en `zone.tab`. |
| `ne.py` | Lector mínimo, sin dependencias, de la capa de husos horarios de Natural Earth (shapefile y dbf). |
| `tzlib.py` | Lectura de `zone.tab` e `iso3166.tab`, distancias y comprobación de si dos zonas dan las mismas horas locales. |

**Entrada:** del `vyamap_airport_city_mapping.csv` existente, `gen.py` toma la lista de aeropuertos (IATA y orden), que es una selección de VyaMap, y la ciudad anterior de cada uno, que solo sirve para la regla de estabilidad: elige entre opciones de Wikidata y no aporta nombres ni coordenadas propios. Con el archivo ya regenerado, volver a ejecutar `gen.py` da el mismo resultado.

## Fuentes

| Fuente | Versión | Qué se usa | Licencia |
|---|---|---|---|
| [OurAirports](https://ourairports.com/data/) | Sin versiones: la del día de la descarga (la de los CSV actuales es del 05-11-2026) | `airports.csv`, `countries.csv` | Dominio público |
| [Wikidata](https://www.wikidata.org/) (servicio de consultas SPARQL) | Sin versiones: la del día de la descarga (05-11-2026), guardada en `downloads/wikidata.json` | Ciudad servida (P931), tipos (P31), nombres y alias, coordenadas (P625), país (P17/P297) y enlaces a Wikipedia | CC0 |
| [Base de datos tz de IANA](https://www.iana.org/time-zones) | 2026f (`tzdata2026f.tar.gz`, fija) | `zone.tab`, `iso3166.tab`, `version` | Dominio público |
| [Natural Earth](https://www.naturalearthdata.com/downloads/10m-cultural-vectors/timezones/), husos horarios 1:10m | 5.0.0 | `ne_10m_time_zones.shp`, `.dbf` y `.VERSION.txt` | Dominio público |

De los archivos comprimidos solo se extraen esos archivos, por nombre. `gen.py` se detiene si la versión de la base de datos tz o de Natural Earth no es la documentada (`--any-version` lo permite). En ese caso, actualiza `TZDATA_VERSION` o `NATURAL_EARTH_VERSION` en `gen.py` y este README.

OurAirports y Wikidata se editan continuamente, así que una descarga posterior puede cambiar algunas filas. `gen.py` muestra cuántas ciudades y zonas cambian, y con `--out` se puede generar en otra carpeta para comparar antes de sustituir nada. La descarga de Wikidata tarda unos 15 minutos: son unas 200 consultas, con pausas entre lotes y esperas cuando el servicio limita las peticiones (429). Mientras `downloads/wikidata.json` exista, no se repite (salvo con `--refresh`).

Las descargas van a `tools/downloads/`, que está en `.gitignore`: no se versionan.

## Requisitos

- **Python 3.9 o superior** (probado con 3.9.6). Solo usa la biblioteca estándar, sin paquetes externos.
- **Base de datos tz del sistema**, para `zoneinfo`: viene con macOS y Linux. En Windows, o si `gen.py` avisa de que la del sistema no es 2026f, instala el paquete `tzdata` (`pip install tzdata`) en su versión correspondiente. `zoneinfo` da el desfase estándar de cada zona candidata y la comparación de equivalencias; las candidatas salen siempre de la `zone.tab` descargada.
- **Conexión a internet** la primera vez (o con `--refresh`).

## Regenerar los CSV desde cero

Desde la raíz del proyecto:

```bash
rm -rf database/data/catalog/tools/downloads
python3 database/data/catalog/tools/gen.py
python3 database/data/catalog/tools/build_rules.py   # opcional: valida las reglas
```

Para generar en otra carpeta y comparar con los actuales:

```bash
python3 database/data/catalog/tools/gen.py --out /tmp/vyamap-catalog
diff -q /tmp/vyamap-catalog database/data/catalog
```

Después, para cargarlos en una base de datos, se siguen los pasos del README del catálogo (copiar a `storage/app/data/` y ejecutar los importadores). Los importadores nunca modifican ciudades existentes ni reasignan aeropuertos que ya tienen ciudad, y `airports:import-timezones` nunca cambia la zona de un aeropuerto que ya tiene vuelos.
