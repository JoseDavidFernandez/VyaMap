"""Ciudad a la que sirve cada aeropuerto (vyamap_airport_city_mapping.csv).

Fuentes: Wikidata (CC0, descargada con wikidata.py) y OurAirports (dominio
público). Para cada aeropuerto, en orden:

1. Elemento de Wikidata: el que tiene su IATA; si hay varios, el que además
   tiene su ICAO; si siguen siendo varios (aeropuerto y base aérea, antiguo y
   nuevo), el más enlazado desde Wikipedia.
2. Candidatas: sus ciudades servidas (P931) que son una ciudad (asentamiento
   o municipio; ni isla, región, país, organización, condado, distrito ni un
   nombre administrativo como «X Municipality» o «X Shi»), con nombre y
   coordenadas, a 100 km o menos del aeropuerto y del mismo país (o de su
   Estado soberano). Una ciudad de otro país solo vale si aparece en el nombre
   del aeropuerto (Basilea en EuroAirport Basel-Mulhouse-Freiburg).
3. Regla de estabilidad: si una candidata es la ciudad del archivo anterior
   (su nombre es un nombre o alias de ella y está a 25 km o menos), se queda
   esa, con el nombre anterior y las coordenadas de Wikidata.
4. Si no, la citada en el nombre del aeropuerto en OurAirports (la más
   cercana), después la citada en sus nombres alternativos de Wikidata y,
   después, la más enlazada desde Wikipedia.
5. Nombre comercial: si los nombres alternativos del aeropuerto citan otra
   ciudad del catálogo del mismo país, a 100 km o menos y más enlazada, y su
   nombre en OurAirports no la cita (el buscador no lo encontraría), se usa
   esa (aeropuerto de Paris-Beauvais -> Paris).
6. Nombre: el de la ciudad en inglés en Wikidata; el anterior si es la misma
   ciudad con otra grafía; el `municipality` de OurAirports si es una forma más
   corta del mismo nombre (New York / New York City).
7. Sin ciudad utilizable: la ciudad anterior si Wikidata tiene un lugar con ese
   nombre a 50 km o menos; si no, el `municipality` de OurAirports, con las
   coordenadas de esa ciudad del catálogo o de un lugar de Wikidata con ese
   nombre a 50 km o menos, o las del aeropuerto. Sin `municipality`, el
   aeropuerto queda sin ciudad.
8. Excepciones de build_rules.py: CITY_OVERRIDES (ciudad fijada, con
   coordenadas de Wikidata) y ADMINISTRATIVE_P931 (su P931 es un atolón o una
   prefectura: se tratan como sin ciudad utilizable).
9. Una misma ciudad lleva las mismas coordenadas en todas sus filas.

Sáhara Occidental: sus aeropuertos llevan siempre EH (AIRPORT_COUNTRY_ONLY).
"""
import math
import re
import unicodedata
from collections import defaultdict

import build_rules

MAX_CITY_KM = 100
# Distancia a la que cities:import considera que dos filas son la misma ciudad.
MATCH_KM = 25
MAX_FALLBACK_KM = 50
MIN_NAME_LENGTH = 4

# Nombres de entidades administrativas o de áreas («Zumpango Municipality»,
# «Yiwu Shi», «San Francisco Bay Area»): no son el nombre de una ciudad, se
# tratan como un condado o distrito.
ADMIN_SUFFIXES = (' municipality', ' district', ' county', ' regency', ' province', ' prefecture', ' governorate',
                  ' shi', ' xian', ' area')

# Territorios con su propio código ISO 3166 y su Estado soberano: una ciudad
# de Wikidata puede tener el país del Estado (Guernsey -> GB).
SOVEREIGN = {
    **{code: 'GB' for code in 'GG JE IM GI FK BM KY VG TC MS AI SH IO'.split()},
    **{code: 'FR' for code in 'NC PF RE GP MQ GF YT PM WF BL MF'.split()},
    **{code: 'NL' for code in 'AW CW SX BQ'.split()},
    **{code: 'US' for code in 'PR GU VI AS MP UM'.split()},
    **{code: 'DK' for code in 'GL FO'.split()},
    **{code: 'AU' for code in 'CX CC NF'.split()},
    **{code: 'NZ' for code in 'CK NU'.split()},
    **{code: 'CN' for code in 'HK MO'.split()},
    'AX': 'FI', 'SJ': 'NO',
}

# ISO 3166 da código propio a Åland y Svalbard; OurAirports los incluye en
# Finlandia y Noruega (ver countries_overrides.csv).
REGION_COUNTRY = {'FI-01': 'AX', 'NO-21': 'SJ', 'NO-22': 'SJ'}

# Territorios en los que la ciudad lleva siempre el país del aeropuerto, aunque
# Wikidata le asigne otro: Sáhara Occidental (EH en ISO 3166 y en OurAirports).
AIRPORT_COUNTRY_ONLY = {'EH'}

# Regla de estabilidad: la ciudad actual se conserva si Wikidata la respalda a
# esta distancia como máximo.
STABLE_KM = 25


def norm(text):
    text = unicodedata.normalize('NFKD', text or '').encode('ascii', 'ignore').decode().lower()
    return re.sub(r'[^a-z0-9]+', ' ', text).strip()


def dist(lat1, lon1, lat2, lon2):
    lat1, lon1, lat2, lon2 = map(math.radians, (lat1, lon1, lat2, lon2))
    h = math.sin((lat2 - lat1) / 2) ** 2 + math.cos(lat1) * math.cos(lat2) * math.sin((lon2 - lon1) / 2) ** 2
    return 6371 * 2 * math.asin(math.sqrt(h))


def point(wkt):
    match = re.match(r'Point\(([-\d.eE]+) ([-\d.eE]+)\)', wkt or '')
    return (float(match.group(2)), float(match.group(1))) if match else None


def mentions(name, text):
    """Si `name` aparece como palabra completa en `text` (ambos normalizados)."""
    return len(name) >= MIN_NAME_LENGTH and f' {name} ' in f' {text} '


def same_country(a, b):
    return a == b or SOVEREIGN.get(a) == b or SOVEREIGN.get(b) == a


def first_municipality(text):
    """«Southend-on-Sea, Essex» -> «Southend-on-Sea»; «Geelong/Melbourne» -> «Geelong»."""
    return re.split(r'[,/(]', text or '')[0].strip()


class Wikidata:
    def __init__(self, data):
        self.airports = data['airports']
        self.airport_links = data['airport_sitelinks']
        self.city_links = data['city_sitelinks']
        self.airport_aliases = defaultdict(set)
        for item, alias in data['airport_aliases']:
            self.airport_aliases[item].add(norm(alias))

        self.classes = defaultdict(set)
        for city, klass in data['city_classes']:
            self.classes[city].add(klass)
        self.labels = {}
        for city, language, label in sorted(data['city_labels'], key=lambda row: row[1] == 'en'):
            self.labels[city] = label  # el inglés, si existe, sobrescribe al multilingüe
        self.aliases = defaultdict(set)
        for city, alias in data['city_aliases']:
            self.aliases[city].add(norm(alias))
        self.coords = defaultdict(list)
        for city, wkt in data['city_coords']:
            if point(wkt):
                self.coords[city].append(point(wkt))
        self.countries = defaultdict(set)
        for city, code in data['city_countries']:
            self.countries[city].add(code)

        self.human_settlement = set(data['settlement_classes'])
        self.settlement = self.human_settlement | set(data['municipality_classes'])
        self.county_district = set(data['county_district_classes'])
        self.not_a_city = set(data['not_a_city_classes'])

        self.places = defaultdict(list)
        for name, _place, wkt in data['named_places']:
            if point(wkt):
                self.places[norm(name)].append(point(wkt))

        self.by_iata = defaultdict(set)
        for item, airport in self.airports.items():
            for code in airport['iata']:
                self.by_iata[code].add(item)

    def item_for(self, iata, icao):
        candidates = set(self.by_iata.get(iata, ()))
        if len(candidates) > 1 and icao:
            with_icao = {item for item in candidates if icao in self.airports[item]['icao']}
            candidates = with_icao or candidates
        if len(candidates) > 1:
            ranked = sorted(candidates, key=lambda item: -self.airport_links.get(item, 0))
            if self.airport_links.get(ranked[0], 0) > self.airport_links.get(ranked[1], 0):
                candidates = {ranked[0]}
        return next(iter(candidates)) if len(candidates) == 1 else None

    def is_city(self, city):
        """Asentamiento o municipio; un condado o distrito solo si también es un asentamiento."""
        classes = self.classes[city]
        if classes & self.county_district and not classes & self.human_settlement:
            return False
        return not classes or bool(classes & self.settlement) or not classes & self.not_a_city


class Assigner:
    def __init__(self, wikidata, country_names, overrides=None, administrative=None):
        self.wd = wikidata
        self.country_names = country_names
        self.overrides = build_rules.CITY_OVERRIDES if overrides is None else overrides
        self.administrative = build_rules.ADMINISTRATIVE_P931 if administrative is None else administrative

    def candidates(self, item, country, lat, lon, names, own_name):
        found = []
        for city in self.wd.airports[item]['cities']:
            label = self.wd.labels.get(city)
            if not label or not self.wd.coords[city] or not self.wd.is_city(city):
                continue
            if label.lower().endswith(ADMIN_SUFFIXES):
                continue
            coord = min(self.wd.coords[city], key=lambda c: dist(lat, lon, *c))
            if dist(lat, lon, *coord) > MAX_CITY_KM:
                continue
            forms = self.wd.aliases[city] | {norm(label)}
            named = any(mentions(alias, names) for alias in forms)
            in_own_name = any(mentions(alias, own_name) for alias in forms)
            codes = self.wd.countries[city]
            if country in AIRPORT_COUNTRY_ONLY:
                city_country = country
            elif codes and not any(same_country(code, country) for code in codes):
                if not named:
                    continue
                city_country = sorted(codes)[0]
            else:
                city_country = country
            found.append({'q': city, 'label': label, 'forms': forms, 'coord': coord, 'country': city_country, 'named': named,
                          'in_own_name': in_own_name, 'km': dist(lat, lon, *coord),
                          'links': self.wd.city_links.get(city, 0)})
        return found

    @staticmethod
    def rank(city):
        """Primero la citada en el nombre de OurAirports (la más cercana: «Milan
        Bergamo» -> Bergamo); después la citada en otros nombres; después la más enlazada."""
        if city['in_own_name']:
            return (2, -city['km'])
        return (1 if city['named'] else 0, city['links'])

    def assign_all(self, airports, previous=None):
        """airports: [{iata, icao, name, country, region, municipality, lat, lon}] -> resultados en el mismo orden.

        previous: {iata: (ciudad, lat, lon)} del archivo anterior, para la regla de
        estabilidad. Solo elige entre opciones de Wikidata; no aporta datos.
        """
        previous = previous or {}
        results = []
        for airport in airports:
            country = REGION_COUNTRY.get(airport['region'], airport['country'])
            item = self.wd.item_for(airport['iata'], airport['icao'])
            names = ' | '.join([norm(airport['name'])] + sorted(self.wd.airport_aliases[item] if item else []))
            found = self.candidates(item, country, airport['lat'], airport['lon'], names, norm(airport['name'])) if item else []
            if airport['iata'] in self.administrative or airport['iata'] in self.overrides:
                found = []
            method, keep_name = 'p931', None
            before = previous.get(airport['iata'])
            stable = [c for c in found if before and norm(before[0]) in c['forms']
                      and dist(before[1], before[2], *c['coord']) <= STABLE_KM]
            if stable:
                chosen, method, keep_name = max(stable, key=self.rank), 'stable', before[0]
            else:
                chosen = max(found, key=self.rank) if found else None
                # La misma ciudad con otra grafía (Hanover / Hannover): se conserva el nombre.
                if chosen and before and norm(before[0]) in chosen['forms'] \
                        and dist(before[1], before[2], *chosen['coord']) <= STABLE_KM:
                    method, keep_name = 'stable', before[0]
            results.append({'airport': airport, 'country': country, 'item': item, 'names': names,
                            'p931': bool(item and self.wd.airports[item]['cities']), 'chosen': chosen,
                            'method': method if chosen else None, 'keep_name': keep_name, 'before': before})

        # Catálogo de ciudades elegidas, para la regla del nombre comercial y el respaldo.
        pool = {}
        for result in results:
            if result['chosen']:
                pool[result['chosen']['q']] = result['chosen']

        for result in results:
            if result['method'] == 'stable' or result['airport']['iata'] in self.overrides.keys() | self.administrative:
                continue
            airport, chosen = result['airport'], result['chosen']
            own_name = norm(airport['name'])
            best = None
            for city in pool.values():
                if city['country'] != result['country'] or city['q'] == (chosen or {}).get('q'):
                    continue
                if not mentions(norm(city['label']), result['names']):
                    continue
                # Si el nombre del aeropuerto ya cita esa ciudad, el buscador lo
                # encuentra por él: se conserva la ciudad servida (BGY sigue en Bergamo).
                if mentions(norm(city['label']), own_name):
                    continue
                if dist(airport['lat'], airport['lon'], *city['coord']) > MAX_CITY_KM:
                    continue
                if chosen and city['links'] <= chosen['links']:
                    continue
                if best is None or city['links'] > best['links']:
                    best = city
            if best:
                result['chosen'], result['method'] = best, 'commercial'

        by_name = defaultdict(list)
        for result in results:
            if result['chosen']:
                by_name[(norm(self.name_for(result)), result['chosen']['country'])].append(result['chosen'])

        rows = []
        for result in results:
            airport = result['airport']
            if airport['iata'] in self.overrides:
                row = self.override(result, pool)
            elif result['chosen']:
                city = result['chosen']
                row = (self.name_for(result), city['country'], city['coord'])
            else:
                row = self.fallback(result, by_name)
            rows.append({**result, 'city': row})
        return rows

    def name_for(self, result):
        """Nombre en inglés de Wikidata; el `municipality` de OurAirports solo si es
        un alias y una forma más corta del mismo nombre («New York» / «New York City»)."""
        if result.get('keep_name'):
            return result['keep_name']
        city = result['chosen']
        label = norm(city['label'])
        municipality = first_municipality(result['airport']['municipality'])
        short = norm(municipality)
        if short and short != label and short in self.wd.aliases[city['q']] and label.startswith(short + ' '):
            return municipality
        return city['label']

    def override(self, result, pool):
        """Excepción de build_rules.CITY_OVERRIDES: esa ciudad, con sus coordenadas de
        Wikidata (del catálogo o de un lugar con ese nombre a 100 km o menos)."""
        airport = result['airport']
        name = self.overrides[airport['iata']]
        lat, lon = airport['lat'], airport['lon']
        result['method'] = 'override'
        known = [c['coord'] for c in pool.values()
                 if c['country'] == result['country'] and norm(name) in c['forms'] and dist(lat, lon, *c['coord']) <= MAX_CITY_KM]
        places = known or [c for c in self.wd.places.get(norm(name), []) if dist(lat, lon, *c) <= MAX_CITY_KM]
        if not places:
            raise ValueError(f"{airport['iata']}: no hay en Wikidata ninguna ciudad «{name}» a {MAX_CITY_KM} km o menos")
        return name, result['country'], min(places, key=lambda c: dist(lat, lon, *c))

    def fallback(self, result, by_name):
        airport = result['airport']
        lat, lon = airport['lat'], airport['lon']
        before = result.get('before')
        if before:
            # Regla de estabilidad: la ciudad actual, si Wikidata tiene un lugar con ese nombre cerca.
            places = [c for c in self.wd.places.get(norm(before[0]), []) if dist(lat, lon, *c) <= MAX_FALLBACK_KM]
            if places:
                result['method'] = 'stable+wikidata'
                return before[0], result['country'], min(places, key=lambda c: dist(lat, lon, *c))
            # build_rules.ADMINISTRATIVE_P931: conservan la ciudad actual aunque Wikidata
            # no tenga un lugar con ese nombre; coordenadas del aeropuerto (OurAirports).
            if airport['iata'] in self.administrative:
                result['method'] = 'stable+airport'
                return before[0], result['country'], (lat, lon)
        name = first_municipality(airport['municipality'])
        if not name:
            result['method'] = 'none'
            return None
        known = [c['coord'] for c in by_name.get((norm(name), result['country']), [])
                 if dist(lat, lon, *c['coord']) <= MAX_FALLBACK_KM]
        if known:
            result['method'] = 'municipality+catalog'
            return name, result['country'], min(known, key=lambda c: dist(lat, lon, *c))
        places = [c for c in self.wd.places.get(norm(name), []) if dist(lat, lon, *c) <= MAX_FALLBACK_KM]
        if places:
            result['method'] = 'municipality+wikidata'
            return name, result['country'], min(places, key=lambda c: dist(lat, lon, *c))
        result['method'] = 'municipality+airport'
        return name, result['country'], (lat, lon)

    def csv_rows(self, assigned):
        """Filas de vyamap_airport_city_mapping.csv, en el orden recibido.

        Una misma ciudad (nombre y país, a MATCH_KM o menos) lleva siempre las mismas
        coordenadas: cities:import la crea con la primera fila y airports:map-cities
        solo asigna aeropuertos a 5 km o menos de ella. Se usan las de la primera
        fila que las toma de la ciudad en Wikidata (si no hay, la primera).
        """
        from_city = ('p931', 'stable', 'commercial', 'override')
        canonical = defaultdict(list)
        for result in sorted(assigned, key=lambda r: r['method'] not in from_city):
            if result['city']:
                name, country, coord = result['city']
                group = canonical[(norm(name), country)]
                if not any(dist(*coord, *known) <= MATCH_KM for known in group):
                    group.append(coord)

        rows = []
        for number, result in enumerate(assigned, 1):
            airport, city = result['airport'], result['city']
            if city:
                name, country, coord = city
                group = canonical[(norm(name), country)]
                lat, lon = next(known for known in group if dist(*coord, *known) <= MATCH_KM)
                rows.append([number, airport['iata'], airport['icao'], airport['name'], name,
                             self.country_names.get(country, ''), country, f'{lat:.4f}', f'{lon:.4f}'])
            else:
                rows.append([number, airport['iata'], airport['icao'], airport['name'], '', '', '', '', ''])
        return rows
