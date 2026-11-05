# Catálogo de países, ciudades y aeropuertos

Datos para crear el catálogo en una instalación nueva (desarrollo o producción). Los comandos de importación los leen de `storage/app/data/`, así que primero se copian allí:

```bash
mkdir -p storage/app/data
cp database/data/catalog/*.csv storage/app/data/

php artisan countries:import --apply
php artisan cities:import
php artisan app:import-airports
php artisan airports:map-cities --apply
php artisan airports:enrich-coordinates --apply
php artisan airports:import-timezones --apply
```

El orden importa: cada ciudad pertenece a un país, y los tres últimos comandos trabajan sobre aeropuertos y ciudades que ya existen. `tests/Feature/CatalogDataImportTest.php` comprueba que estos archivos crean el catálogo completo: 251 países, más de 3000 ciudades y 3244 aeropuertos con ciudad, coordenadas y zona horaria.

| Archivo | Contenido | Origen |
|---|---|---|
| Archivo | Contenido | Origen |
|---|---|---|
| `countries.csv` | Países y continentes | [OurAirports](https://ourairports.com/data/) `countries.csv`, sin cambios (idéntico byte a byte al publicado el 05-11-2026) |
| `countries_overrides.csv` | Dos territorios que faltan en `countries.csv` (AX, SJ) | `iso3166.tab` de la [base de datos tz de IANA](https://www.iana.org/time-zones) (código y nombre tal cual) |
| `airports.csv` | Coordenadas de los aeropuertos | [OurAirports](https://ourairports.com/data/) `airports.csv` del 05-11-2026, reducido a los 3244 códigos IATA del mapeo; las filas no se modifican |
| `airport_timezones.csv` | Zona horaria IANA de cada aeropuerto | Nombre, IATA, ICAO y coordenadas de OurAirports; zona derivada de la base de datos tz y de Natural Earth (ver abajo). `id` es el número de fila, en el orden del mapeo |
| `vyamap_airport_city_mapping.csv` | Aeropuertos y la ciudad a la que pertenecen | Preparado para VyaMap. **Procedencia sin verificar** (ver abajo) |

No contienen datos personales.

### Fuentes y licencias

| Fuente | Versión | Licencia |
|---|---|---|
| [OurAirports](https://ourairports.com/data/) (`airports.csv`, `countries.csv`) | Descarga del 05-11-2026 | Dominio público («All data is released to the Public Domain») |
| [Base de datos tz de IANA](https://www.iana.org/time-zones) (`zone.tab`, `iso3166.tab` y comentarios de los archivos fuente) | 2026f | Dominio público (indicado en la cabecera de cada archivo) |
| [Natural Earth](https://www.naturalearthdata.com/downloads/10m-cultural-vectors/timezones/), husos horarios 1:10m | 5.0.0 | Dominio público (donados por International Mapping Associates; atribución opcional) |

Ninguna exige atribución ni impone condiciones a la redistribución, así que estos cuatro archivos pueden publicarse junto al código bajo cualquier licencia.

### Cálculo de las zonas horarias (`timezone_status=calculated`)

Regenerado el 05-11-2026 con los scripts de [`tools/`](tools/README.md), que también generan `countries.csv`, `countries_overrides.csv` y `airports.csv`. Para cada aeropuerto, a partir de su país, su región ISO 3166-2 (`iso_region` de OurAirports) y sus coordenadas:

1. **Excepción por aeropuerto** (38), cuando la base de datos tz nombra el lugar en `zone.tab` o en los comentarios de sus archivos fuente: Coral Harbour y Pickle Lake (`America/Atikokan`), la Baja Costa Norte de Quebec «al este de Natashquan» (`America/Blanc-Sablon`), la frontera de Coahuila y Tamaulipas con EE. UU. (`America/Matamoros`), el oeste de Pará al otro lado del Xingu (`America/Santarem`), el este de Yakutia (`Asia/Srednekolymsk`), Ercan (`Asia/Famagusta`), Broken Hill, las islas Whitsunday, Åland, Svalbard, y las ciudades que dan nombre a su zona (Juneau, Sitka, Nome, Yakutat, Louisville, Inuvik, Dawson…).
2. **Regla por región** (258), según las provincias, estados o sujetos federales que `zone.tab` asigna a cada zona (Argentina, Australia, Brasil, Canadá, EE. UU., Indonesia, Kazajistán, México, Rusia…). Xinjiang (CN-65) usa `Asia/Shanghai`: según la base de datos tz, aviones y trenes funcionan allí con la hora de Pekín.
3. **País con una sola zona** en `zone.tab` (o en la región o país por defecto: España, México, Indonesia): esa zona. Kosovo, que no figura en `zone.tab`, usa `Europe/Belgrade`.
4. **Región con varias zonas**: las candidatas cuyo desfase estándar coincide con el del polígono de Natural Earth en las coordenadas del aeropuerto; entre ellas, la principal de la región o, si no está, la de punto representativo (`zone.tab`) más cercano.

Comparado con el archivo anterior (de origen sin acreditar): 3199 zonas iguales; 26 con otro nombre pero las mismas horas locales entre 1990 y 2035 (por ejemplo, el norte de Vietnam pasa de `Asia/Bangkok` a `Asia/Ho_Chi_Minh` y parte de Alaska de `America/Nome` a `America/Anchorage`), y 19 corregidas: los 17 aeropuertos de Xinjiang (`Asia/Urumqi` → `Asia/Shanghai`), Natashquan (`America/Blanc-Sablon` → `America/Toronto`) y Gold Coast (`Australia/Sydney` → `Australia/Brisbane`: el aeropuerto opera con la hora de Queensland). Ninguno de los aeropuertos con vuelos en las bases de datos de desarrollo y de pruebas manuales cambia de IATA, coordenadas ni zona; en cualquier caso, `airports:import-timezones` nunca cambia la zona de un aeropuerto que tiene vuelos.

Se valoró Wikidata (CC0), pero sus aeropuertos no enlazan zonas IANA (la propiedad P421 apunta a husos como «UTC+01:00»), así que no se usa.

### Pendiente: `vyamap_airport_city_mapping.csv`

No hay ningún script, documento ni commit que explique cómo se generó, así que su origen **no está acreditado**. Coinciden con OurAirports el nombre del aeropuerto, el ICAO y el país; **sin fuente identificada:** la ciudad asignada a cada aeropuerto (en 822 no coincide con el `municipality` de OurAirports: es la ciudad a la que sirve, como NRT → Tokyo o BVA → Paris) y las coordenadas de esas ciudades. Regenerarlo con el `municipality` de OurAirports empeora la búsqueda de aeropuertos por ciudad (Narita, Beauvais, Sepang…); la alternativa con licencia conocida es la «ciudad servida» de Wikidata (P931, CC0). Hasta resolverlo, no se puede declarar una licencia que cubra este archivo, ni la lista de aeropuertos que de él depende.
