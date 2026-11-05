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

El orden importa: cada ciudad pertenece a un país, y los tres últimos comandos trabajan sobre aeropuertos y ciudades que ya existen. `tests/Feature/CatalogDataImportTest.php` comprueba que estos archivos crean el catálogo completo: 251 países, más de 3000 ciudades y 3244 aeropuertos con coordenadas y zona horaria, todos con ciudad salvo los 6 que no la tienen en el CSV.

Los cinco archivos se generan con los scripts de [`tools/`](tools/README.md).

| Archivo | Contenido | Origen |
|---|---|---|
| `countries.csv` | Países y continentes | [OurAirports](https://ourairports.com/data/) `countries.csv`, sin cambios (idéntico byte a byte al publicado el 05-11-2026) |
| `countries_overrides.csv` | Dos territorios que faltan en `countries.csv` (AX, SJ) | `iso3166.tab` de la [base de datos tz de IANA](https://www.iana.org/time-zones) (código y nombre tal cual) |
| `airports.csv` | Coordenadas de los aeropuertos | [OurAirports](https://ourairports.com/data/) `airports.csv` del 05-11-2026, reducido a los 3244 códigos IATA del mapeo; las filas no se modifican |
| `airport_timezones.csv` | Zona horaria IANA de cada aeropuerto | Nombre, IATA, ICAO y coordenadas de OurAirports; zona derivada de la base de datos tz y de Natural Earth (ver abajo). `id` es el número de fila, en el orden del mapeo |
| `vyamap_airport_city_mapping.csv` | Aeropuertos y la ciudad a la que sirven | Aeropuerto (IATA, ICAO, nombre y país) de OurAirports; ciudad servida y sus coordenadas de Wikidata, o de OurAirports cuando Wikidata no la da (ver abajo). La lista de aeropuertos y su orden es una selección de VyaMap; `id_original` es el número de fila |

No contienen datos personales.

### Fuentes y licencias

| Fuente | Versión | Licencia |
|---|---|---|
| [OurAirports](https://ourairports.com/data/) (`airports.csv`, `countries.csv`) | Descarga del 05-11-2026 | Dominio público («All data is released to the Public Domain») |
| [Base de datos tz de IANA](https://www.iana.org/time-zones) (`zone.tab`, `iso3166.tab` y comentarios de los archivos fuente) | 2026f | Dominio público (indicado en la cabecera de cada archivo) |
| [Natural Earth](https://www.naturalearthdata.com/downloads/10m-cultural-vectors/timezones/), husos horarios 1:10m | 5.0.0 | Dominio público (donados por International Mapping Associates; atribución opcional) |
| [Wikidata](https://www.wikidata.org/) (ciudad servida P931, tipos, nombres, alias, coordenadas y país de las ciudades) | Consulta del 05-11-2026 | [CC0 1.0](https://creativecommons.org/publicdomain/zero/1.0/) («All structured data in the main, property and lexeme namespaces is made available under the Creative Commons CC0 License») |

Ninguna exige atribución ni impone condiciones a la redistribución, así que los cinco archivos pueden publicarse junto al código bajo cualquier licencia.

### Ciudad de cada aeropuerto (`vyamap_airport_city_mapping.csv`)

Regenerado el 05-11-2026. Es la ciudad a la que sirve el aeropuerto (NRT → Tokyo, BVA → Paris), no necesariamente la localidad donde está. Para cada aeropuerto (detalle en `tools/cities.py`):

1. **Ciudad servida de Wikidata** (P931) del elemento con su IATA (y su ICAO, si hay varios): solo si es una ciudad (ni isla, región, país, organización, condado, distrito ni un nombre administrativo como «Zumpango Municipality» o «Yiwu Shi»), del mismo país y a 100 km o menos. Si hay varias, la citada en el nombre del aeropuerto o, si no, la más enlazada desde Wikipedia.
2. **Regla de estabilidad:** si Wikidata respalda la ciudad anterior (entre sus ciudades servidas, como nombre o alias de la elegida o como lugar con ese nombre a 50 km o menos), se conserva su nombre; las coordenadas son siempre las de Wikidata. El archivo anterior solo elige entre opciones de Wikidata.
3. **Nombre comercial:** si los nombres alternativos del aeropuerto en Wikidata citan una ciudad mayor del mismo país a 100 km o menos y su nombre en OurAirports no la cita, se usa esa, para que el buscador lo encuentre por ella (BVA, «aéroport de Paris-Beauvais» → Paris).
4. **Sin ciudad utilizable:** el `municipality` de OurAirports, con coordenadas de Wikidata (lugar con ese nombre a 50 km o menos) o, si no las hay, las del aeropuerto. Seis aeropuertos de atolones de la Polinesia Francesa (FAV, MAU, MVT, TKP, TKX, XMH) no tienen `municipality` ni lugar en Wikidata y quedan **sin ciudad**.
5. **Excepciones revisadas a mano** (`tools/build_rules.py`): KEF → Reykjavík, MRU → Port Louis y CCE → Cairo, con coordenadas de Wikidata (Wikidata solo da la localidad del aeropuerto y su nombre no cita la ciudad); y KDM, GXH, DCY y DDR, cuya ciudad servida en Wikidata es un atolón o una prefectura, conservan la ciudad anterior con las coordenadas del aeropuerto (Wikidata no tiene un lugar con ese nombre cerca).
6. **País:** el del aeropuerto en OurAirports, salvo Åland (AX) y Svalbard (SJ), que tienen código propio en ISO 3166, y una ciudad de otro país citada en el nombre del aeropuerto (BSL → Basel, CH). Los aeropuertos del Sáhara Occidental (EUN, SMW, VIL) van en EH, como en ISO 3166 y en OurAirports.

Comparado con el archivo anterior (de origen sin acreditar): 3073 ciudades iguales (mismo nombre a 25 km o menos; las coordenadas se mueven una mediana de 0,7 km); 102 con otro nombre a 25 km o menos (a menudo la localidad del aeropuerto, como BEN Benghazi → Benina); 40 asignadas a otra ciudad (por ejemplo, AVP Scranton → Wilkes-Barre, RFD Chicago → Rockford o CRL Brussels → Charleroi, que «Brussels» sigue encontrando por su nombre); 19 con el mismo nombre y coordenadas a más de 25 km; 4 con otro país (los tres del Sáhara Occidental y YAM, Sault Ste. Marie, en Canadá), y 6 sin ciudad. 76 aeropuertos dejan de aparecer al buscar su ciudad anterior; siguen apareciendo por su nombre, su código y su nueva ciudad. Además, 4 aeropuertos cambian de nombre (MBD, EFL, ZTH, CJJ: OurAirports actual) y 42 países usan el nombre de `countries.csv` (antes había nombres de otro estilo, como «St. Lucia»).

Ninguno de los aeropuertos con vuelos ni ninguna de las ciudades con visitas de las bases de datos de desarrollo y de pruebas manuales cambia. En cualquier caso, `cities:import` nunca modifica una ciudad existente y `airports:map-cities` nunca reasigna un aeropuerto que ya tiene ciudad: en una base de datos existente, el cambio solo se vería al importar desde cero.

### Cálculo de las zonas horarias (`timezone_status=calculated`)

Regenerado el 05-11-2026. Para cada aeropuerto, a partir de su país, su región ISO 3166-2 (`iso_region` de OurAirports) y sus coordenadas:

1. **Excepción por aeropuerto** (38), cuando la base de datos tz nombra el lugar en `zone.tab` o en los comentarios de sus archivos fuente: Coral Harbour y Pickle Lake (`America/Atikokan`), la Baja Costa Norte de Quebec «al este de Natashquan» (`America/Blanc-Sablon`), la frontera de Coahuila y Tamaulipas con EE. UU. (`America/Matamoros`), el oeste de Pará al otro lado del Xingu (`America/Santarem`), el este de Yakutia (`Asia/Srednekolymsk`), Ercan (`Asia/Famagusta`), Broken Hill, las islas Whitsunday, Åland, Svalbard, y las ciudades que dan nombre a su zona (Juneau, Sitka, Nome, Yakutat, Louisville, Inuvik, Dawson…).
2. **Regla por región** (258), según las provincias, estados o sujetos federales que `zone.tab` asigna a cada zona (Argentina, Australia, Brasil, Canadá, EE. UU., Indonesia, Kazajistán, México, Rusia…). Xinjiang (CN-65) usa `Asia/Shanghai`: según la base de datos tz, aviones y trenes funcionan allí con la hora de Pekín.
3. **País con una sola zona** en `zone.tab` (o en la región o país por defecto: España, México, Indonesia): esa zona. Kosovo, que no figura en `zone.tab`, usa `Europe/Belgrade`.
4. **Región con varias zonas**: las candidatas cuyo desfase estándar coincide con el del polígono de Natural Earth en las coordenadas del aeropuerto; entre ellas, la principal de la región o, si no está, la de punto representativo (`zone.tab`) más cercano.

Comparado con el archivo anterior (de origen sin acreditar): 3199 zonas iguales; 26 con otro nombre pero las mismas horas locales entre 1990 y 2035 (por ejemplo, el norte de Vietnam pasa de `Asia/Bangkok` a `Asia/Ho_Chi_Minh` y parte de Alaska de `America/Nome` a `America/Anchorage`), y 19 corregidas: los 17 aeropuertos de Xinjiang (`Asia/Urumqi` → `Asia/Shanghai`), Natashquan (`America/Blanc-Sablon` → `America/Toronto`) y Gold Coast (`Australia/Sydney` → `Australia/Brisbane`: el aeropuerto opera con la hora de Queensland). Ninguno de los aeropuertos con vuelos en las bases de datos de desarrollo y de pruebas manuales cambia de IATA, coordenadas ni zona; en cualquier caso, `airports:import-timezones` nunca cambia la zona de un aeropuerto que tiene vuelos.

Se valoró Wikidata (CC0), pero sus aeropuertos no enlazan zonas IANA (la propiedad P421 apunta a husos como «UTC+01:00»), así que no se usa.
