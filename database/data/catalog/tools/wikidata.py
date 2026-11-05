"""Descarga de Wikidata (CC0) para asignar a cada aeropuerto la ciudad a la que sirve.

Guarda en un único JSON (tools/downloads/wikidata.json):

- aeropuertos con un IATA del catálogo: ICAO, ciudades servidas (P931),
  nombres y alias, y enlaces a Wikipedia (para elegir entre duplicados);
- de cada ciudad servida: clases (P31), nombres en inglés o multilingües,
  alias, coordenadas (P625), país (P17 -> P297) y enlaces a Wikipedia;
- qué clases son asentamientos, municipios, condados o distritos, o lugares
  que no son una ciudad (islas, regiones, países, organizaciones...);
- lugares con el nombre del `municipality` de OurAirports o de la ciudad del
  archivo anterior, y sus coordenadas (respaldo y regla de estabilidad para
  los aeropuertos sin una ciudad servida utilizable).

Las consultas van por lotes y se reintentan si el servicio responde 429 o 5xx.
"""
import csv
import hashlib
import http.client
import io
import json
import sys
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import date
from pathlib import Path

ENDPOINT = 'https://query.wikidata.org/sparql'
USER_AGENT = 'vyamap-catalog-tools/1.0'

SETTLEMENT = 'Q486972'      # asentamiento humano
MUNICIPALITY = 'Q15284'     # municipio
COUNTY_DISTRICT = ['Q28575', 'Q149621']   # condado, distrito
NOT_A_CITY = [
    'Q23442',      # isla
    'Q10864048',   # división administrativa de primer nivel
    'Q28575',      # condado
    'Q149621',     # distrito
    'Q82794',      # región geográfica
    'Q6256',       # país
    'Q43229',      # organización
    'Q1907114',    # área metropolitana
]
ALIAS_LANGUAGES = ('en', 'mul', 'fr', 'es', 'de', 'pt', 'it', 'nl')

# Caché de respuestas por consulta (gen.py la sitúa en downloads/wikidata-cache):
# si la descarga se corta, al relanzarla continúa donde se quedó.
CACHE_DIR = None


def query(sparql, attempts=6):
    cached = None
    if CACHE_DIR:
        cached = Path(CACHE_DIR) / (hashlib.sha256(sparql.encode()).hexdigest() + '.json')
        if cached.is_file():
            return json.loads(cached.read_text(encoding='utf-8'))
    rows = _query(sparql, attempts)
    if cached:
        cached.parent.mkdir(parents=True, exist_ok=True)
        cached.write_text(json.dumps(rows, ensure_ascii=False), encoding='utf-8')
    return rows


def _query(sparql, attempts):
    data = urllib.parse.urlencode({'query': sparql}).encode()
    for attempt in range(attempts):
        request = urllib.request.Request(ENDPOINT, data=data, headers={'User-Agent': USER_AGENT, 'Accept': 'text/csv'})
        try:
            with urllib.request.urlopen(request, timeout=120) as response:
                return list(csv.DictReader(io.StringIO(response.read().decode('utf-8'))))
        except urllib.error.HTTPError as error:
            if error.code not in (429, 500, 502, 503, 504) or attempt == attempts - 1:
                raise
            wait = int(error.headers.get('Retry-After') or 0) or 10 * (attempt + 1)
            print(f'  Wikidata {error.code}: reintento en {wait} s', file=sys.stderr)
            time.sleep(wait)
        except (OSError, http.client.HTTPException) as error:
            # Cortes de conexión y tiempos de espera (URLError, socket.timeout...): también se reintentan.
            if attempt == attempts - 1:
                raise
            wait = 10 * (attempt + 1)
            print(f'  Wikidata: {type(error).__name__}, reintento en {wait} s', file=sys.stderr)
            time.sleep(wait)


def batched(values, template, size=150, pause=2):
    rows, values = [], sorted(values)
    for start in range(0, len(values), size):
        chunk = ' '.join(values[start:start + size])
        rows += query(template.replace('{VALUES}', chunk))
        time.sleep(pause)
    return rows


def qid(uri):
    return uri.rsplit('/', 1)[1]


def entities(qids):
    return {f'wd:{q}' for q in qids}


def subclasses(classes, roots):
    roots = ' '.join(f'wd:{root}' for root in roots)
    template = 'SELECT DISTINCT ?class WHERE { VALUES ?class { {VALUES} } VALUES ?root { ' + roots + ' } ?class wdt:P279* ?root . }'
    return sorted({qid(row['class']) for row in batched(entities(classes), template, size=80)})


def named_places(names):
    """[(nombre, lugar, coordenadas)] de los elementos cuyo nombre en inglés es uno de `names`."""
    places, names = [], sorted(names)
    for start in range(0, len(names), 120):
        values = ' '.join('"%s"@en' % name.replace('\\', '').replace('"', '') for name in names[start:start + 120])
        places += query('SELECT ?name ?place ?coord WHERE { VALUES ?name { ' + values + ' } ?place rdfs:label ?name ; wdt:P625 ?coord . }')
        time.sleep(2)
    return sorted({(row['name'], qid(row['place']), row['coord']) for row in places})


def fetch(wanted_iata, place_names):
    """Todo lo necesario para cities.py, en un diccionario serializable.

    place_names: nombres de lugar que buscar (municipios de OurAirports y
    ciudades del archivo anterior).
    """
    print('Wikidata: aeropuertos con IATA...')
    rows = query('SELECT ?item ?iata ?icao ?city WHERE { ?item wdt:P238 ?iata . '
                 'OPTIONAL { ?item wdt:P239 ?icao } OPTIONAL { ?item wdt:P931 ?city } }')
    airports = {}
    for row in rows:
        if row['iata'] not in wanted_iata:
            continue
        item = airports.setdefault(qid(row['item']), {'iata': set(), 'icao': set(), 'cities': set()})
        item['iata'].add(row['iata'])
        if row['icao']:
            item['icao'].add(row['icao'])
        if row['city']:
            item['cities'].add(qid(row['city']))

    print(f'Wikidata: nombres y alias de {len(airports)} aeropuertos...')
    languages = ', '.join(f'"{language}"' for language in ALIAS_LANGUAGES)
    airport_aliases = batched(entities(airports), 'SELECT ?item ?alias WHERE { VALUES ?item { {VALUES} } '
                              '?item rdfs:label|skos:altLabel ?alias . FILTER(LANG(?alias) IN (' + languages + ')) }')
    airport_links = batched(entities(airports), 'SELECT ?item ?links WHERE { VALUES ?item { {VALUES} } ?item wikibase:sitelinks ?links . }', size=200)

    cities = set().union(*(item['cities'] for item in airports.values()))
    print(f'Wikidata: {len(cities)} ciudades servidas...')
    city_classes = batched(entities(cities), 'SELECT ?city ?class WHERE { VALUES ?city { {VALUES} } ?city wdt:P31 ?class . }')
    city_labels = batched(entities(cities), 'SELECT ?city ?lang ?label WHERE { VALUES ?city { {VALUES} } ?city rdfs:label ?label . '
                          'BIND(LANG(?label) AS ?lang) FILTER(?lang IN ("en", "mul")) }')
    city_aliases = batched(entities(cities), 'SELECT ?city ?alias WHERE { VALUES ?city { {VALUES} } '
                           '?city rdfs:label|skos:altLabel ?alias . FILTER(LANG(?alias) IN (' + languages + ')) }')
    city_coords = batched(entities(cities), 'SELECT ?city ?coord WHERE { VALUES ?city { {VALUES} } ?city wdt:P625 ?coord . }')
    city_countries = batched(entities(cities), 'SELECT ?city ?cc WHERE { VALUES ?city { {VALUES} } ?city wdt:P17 ?country . ?country wdt:P297 ?cc . }')
    city_links = batched(entities(cities), 'SELECT ?item ?links WHERE { VALUES ?item { {VALUES} } ?item wikibase:sitelinks ?links . }', size=200)

    classes = {qid(row['class']) for row in city_classes}
    print(f'Wikidata: tipos de {len(classes)} clases...')
    settlement = subclasses(classes, [SETTLEMENT])
    municipality = subclasses(classes, [MUNICIPALITY])
    county_district = subclasses(classes, COUNTY_DISTRICT)
    not_a_city = subclasses(classes, NOT_A_CITY)

    print(f'Wikidata: lugares con {len(place_names)} nombres...')
    places = named_places(place_names)

    return {
        'retrieved': date.today().isoformat(),
        'airports': {q: {key: sorted(value) for key, value in item.items()} for q, item in airports.items()},
        'airport_aliases': sorted({(qid(row['item']), row['alias']) for row in airport_aliases}),
        'airport_sitelinks': {qid(row['item']): int(row['links']) for row in airport_links},
        'city_classes': sorted({(qid(row['city']), qid(row['class'])) for row in city_classes}),
        'city_labels': sorted({(qid(row['city']), row['lang'], row['label']) for row in city_labels}),
        'city_aliases': sorted({(qid(row['city']), row['alias']) for row in city_aliases}),
        'city_coords': sorted({(qid(row['city']), row['coord']) for row in city_coords}),
        'city_countries': sorted({(qid(row['city']), row['cc']) for row in city_countries}),
        'city_sitelinks': {qid(row['item']): int(row['links']) for row in city_links},
        'settlement_classes': settlement,
        'municipality_classes': municipality,
        'county_district_classes': county_district,
        'not_a_city_classes': not_a_city,
        'named_places': places,
    }
