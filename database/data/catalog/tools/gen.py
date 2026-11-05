"""Regenera los CSV del catálogo desde fuentes de dominio público o CC0.

    python3 gen.py              # descarga lo que falte y escribe en database/data/catalog/
    python3 gen.py --refresh    # vuelve a descargar todo (Wikidata tarda unos 15 minutos)
    python3 gen.py --out DIR    # escribe en otra carpeta (para comparar)

Genera countries.csv, countries_overrides.csv, vyamap_airport_city_mapping.csv,
airports.csv y airport_timezones.csv. Del vyamap_airport_city_mapping.csv del
catálogo solo toma la lista de aeropuertos (IATA y orden) y la ciudad anterior
de cada uno, para la regla de estabilidad de cities.py.

Las descargas se guardan en tools/downloads/ (fuera de Git).
"""
import argparse
import csv
import io
import json
import shutil
import sys
import tarfile
import urllib.request
import zipfile
import zoneinfo
from collections import Counter
from pathlib import Path

TOOLS = Path(__file__).resolve().parent
sys.path.insert(0, str(TOOLS))

import cities  # noqa: E402
import wikidata  # noqa: E402
from assign import Assigner  # noqa: E402
from tzlib import equivalent, load_iso3166  # noqa: E402

CATALOG = TOOLS.parent
DOWNLOADS = TOOLS / 'downloads'

TZDATA_VERSION = '2026f'
NATURAL_EARTH_VERSION = '5.0.0'

OURAIRPORTS_URL = 'https://davidmegginson.github.io/ourairports-data/{}'
TZDATA_URL = f'https://data.iana.org/time-zones/releases/tzdata{TZDATA_VERSION}.tar.gz'
NATURAL_EARTH_URL = 'https://naciscdn.org/naturalearth/10m/cultural/ne_10m_time_zones.zip'
NATURAL_EARTH_BASE = 'ne_10m_time_zones'

# Territorios de iso3166.tab que faltan en countries.csv de OurAirports.
OVERRIDE_CODES = ('AX', 'SJ')

USER_AGENT = 'vyamap-catalog-tools/1.0'


def fetch(url):
    request = urllib.request.Request(url, headers={'User-Agent': USER_AGENT})
    with urllib.request.urlopen(request, timeout=120) as response:
        return response.read()


def download(refresh):
    """Descarga solo los archivos necesarios; los comprimidos se extraen por nombre."""
    DOWNLOADS.mkdir(exist_ok=True)

    for name in ('airports.csv', 'countries.csv'):
        target = DOWNLOADS / name
        if refresh or not target.is_file():
            print(f'Descargando OurAirports {name}...')
            target.write_bytes(fetch(OURAIRPORTS_URL.format(name)))

    tz_files = ('zone.tab', 'iso3166.tab', 'version')
    if refresh or not all((DOWNLOADS / name).is_file() for name in tz_files):
        print(f'Descargando tzdata {TZDATA_VERSION}...')
        with tarfile.open(fileobj=io.BytesIO(fetch(TZDATA_URL)), mode='r:gz') as archive:
            for name in tz_files:
                member = archive.getmember(name)
                if not member.isfile():
                    sys.exit(f'tzdata: {name} no es un archivo normal')
                (DOWNLOADS / name).write_bytes(archive.extractfile(member).read())

    ne_files = (f'{NATURAL_EARTH_BASE}.shp', f'{NATURAL_EARTH_BASE}.dbf', f'{NATURAL_EARTH_BASE}.VERSION.txt')
    if refresh or not all((DOWNLOADS / name).is_file() for name in ne_files):
        print('Descargando Natural Earth (husos horarios 1:10m)...')
        with zipfile.ZipFile(io.BytesIO(fetch(NATURAL_EARTH_URL))) as archive:
            for name in ne_files:
                (DOWNLOADS / name).write_bytes(archive.read(name))


def download_wikidata(refresh, mapping):
    """Wikidata para cities.py, en downloads/wikidata.json."""
    target = DOWNLOADS / 'wikidata.json'
    if not refresh and target.is_file():
        return
    wanted = {entry['iata_code'] for entry in mapping}
    places = {cities.first_municipality(row['municipality']) for row in read_csv(DOWNLOADS / 'airports.csv')
              if row['iata_code'] in wanted}
    places |= {entry['city_name'] for entry in mapping}
    wikidata.CACHE_DIR = DOWNLOADS / 'wikidata-cache'
    if refresh:
        shutil.rmtree(wikidata.CACHE_DIR, ignore_errors=True)
    data = wikidata.fetch(wanted, places - {''})
    target.write_text(json.dumps(data, ensure_ascii=False), encoding='utf-8')


def check_versions(any_version):
    problems = []
    tzdata = (DOWNLOADS / 'version').read_text(encoding='utf-8').strip()
    if tzdata != TZDATA_VERSION:
        problems.append(f'tzdata {tzdata} (se esperaba {TZDATA_VERSION})')
    natural_earth = (DOWNLOADS / f'{NATURAL_EARTH_BASE}.VERSION.txt').read_text(encoding='utf-8').strip()
    if natural_earth != NATURAL_EARTH_VERSION:
        problems.append(f'Natural Earth {natural_earth} (se esperaba {NATURAL_EARTH_VERSION})')
    if problems and not any_version:
        sys.exit('Versiones distintas de las documentadas: ' + '; '.join(problems)
                 + '. Actualiza TZDATA_VERSION / NATURAL_EARTH_VERSION y el README, o usa --any-version.')
    for problem in problems:
        print(f'AVISO: {problem}')

    # zoneinfo (desfases estándar y equivalencias) usa la base tz del sistema
    # o el paquete tzdata; si es de otra versión, se avisa.
    system = None
    for directory in zoneinfo.TZPATH:
        version_file = Path(directory) / '+VERSION'
        if version_file.is_file():
            system = version_file.read_text(encoding='utf-8').strip()
            break
    if system is None:
        try:
            import tzdata
            system = tzdata.IANA_VERSION
        except ImportError:
            pass
    if system != TZDATA_VERSION:
        print(f'AVISO: zoneinfo usa la base tz {system or "desconocida"}, no {TZDATA_VERSION}.')


def read_csv(path):
    with open(path, newline='', encoding='utf-8') as handle:
        return list(csv.DictReader(handle))


def write_countries(out):
    shutil.copyfile(DOWNLOADS / 'countries.csv', out / 'countries.csv')

    names = load_iso3166(DOWNLOADS / 'iso3166.tab')
    lines = ['code,name'] + [f'{code},{names[code]}' for code in OVERRIDE_CODES]
    (out / 'countries_overrides.csv').write_text('\n'.join(lines), encoding='utf-8')


def country_names():
    names = {row['code']: row['name'] for row in read_csv(DOWNLOADS / 'countries.csv')}
    iso = load_iso3166(DOWNLOADS / 'iso3166.tab')
    names.update({code: iso[code] for code in OVERRIDE_CODES})
    return names


def write_cities(out, mapping):
    """vyamap_airport_city_mapping.csv: ciudad servida de cada aeropuerto (cities.py)."""
    airports = {row['iata_code']: row for row in read_csv(DOWNLOADS / 'airports.csv') if row['iata_code']}
    entries = []
    for entry in mapping:
        airport = airports[entry['iata_code']]
        entries.append({
            'iata': airport['iata_code'], 'icao': airport['icao_code'], 'name': airport['name'],
            'country': airport['iso_country'], 'region': airport['iso_region'], 'municipality': airport['municipality'],
            'lat': float(airport['latitude_deg']), 'lon': float(airport['longitude_deg']),
        })
    previous = {entry['iata_code']: (entry['city_name'], float(entry['city_latitude']), float(entry['city_longitude']))
                for entry in mapping if entry['city_name']}

    data = json.loads((DOWNLOADS / 'wikidata.json').read_text(encoding='utf-8'))
    assigner = cities.Assigner(cities.Wikidata(data), country_names())
    assigned = assigner.assign_all(entries, previous)
    rows = assigner.csv_rows(assigned)

    header = ['id_original', 'iata_code', 'icao_code', 'airport_name', 'city_name',
              'country_name', 'country_iso_code', 'city_latitude', 'city_longitude']
    with open(out / 'vyamap_airport_city_mapping.csv', 'w', newline='', encoding='utf-8') as handle:
        writer = csv.writer(handle, lineterminator='\n')
        writer.writerow(header)
        writer.writerows(rows)

    changed = sum(1 for entry, row in zip(mapping, rows) if (entry['city_name'], entry['country_iso_code']) != (row[4], row[6]))
    print(f"Ciudades: {len(rows)} aeropuertos, {changed} con otra ciudad o país que en el archivo anterior, "
          f"{sum(1 for row in rows if not row[4])} sin ciudad (Wikidata del {data['retrieved']})")
    print('  Método por aeropuerto:', dict(Counter(result['method'] for result in assigned)))


def write_airports(out, wanted):
    """Filas de OurAirports con un IATA del mapeo, con su texto original y en su orden."""
    text = (DOWNLOADS / 'airports.csv').read_text(encoding='utf-8')
    lines = text.splitlines(keepends=True)
    reader = csv.reader(io.StringIO(text))
    header = next(reader)
    iata_column = header.index('iata_code')

    kept, rows, start = [lines[0]], {}, reader.line_num
    for record in reader:
        end = reader.line_num
        if record[iata_column] in wanted:
            kept.append(''.join(lines[start:end]))
            rows.setdefault(record[iata_column], []).append(dict(zip(header, record)))
        start = end

    duplicated = sorted(code for code, found in rows.items() if len(found) > 1)
    missing = sorted(wanted - rows.keys())
    if duplicated or missing:
        sys.exit(f'OurAirports: IATA repetidos {duplicated}, sin fila {missing}')

    (out / 'airports.csv').write_text(''.join(kept), encoding='utf-8')
    return {code: found[0] for code, found in rows.items()}


def build_timezones(mapping, airports):
    assigner = Assigner(DOWNLOADS / 'zone.tab', str(DOWNLOADS / NATURAL_EARTH_BASE))
    rows, methods = [], Counter()
    for number, entry in enumerate(mapping, 1):
        airport = airports[entry['iata_code']]
        lat, lon = float(airport['latitude_deg']), float(airport['longitude_deg'])
        zone, method = assigner.assign(airport['iata_code'], airport['iso_country'], airport['iso_region'], lat, lon)
        methods[method] += 1
        rows.append([number, airport['name'], airport['iata_code'], airport['icao_code'],
                     f'{lat:.7f}', f'{lon:.7f}', zone, 'calculated'])
    return rows, methods


def compare(previous_path, rows):
    """Resumen de cambios de zona respecto al archivo anterior."""
    if not previous_path.is_file():
        return
    previous = {row['iata_code']: row['timezone'] for row in read_csv(previous_path)}
    summary, changed = Counter(), []
    for row in rows:
        iata, zone = row[2], row[6]
        before = previous.get(iata)
        if before is None:
            summary['nuevo'] += 1
        elif before == zone:
            summary['igual'] += 1
        elif equivalent(before, zone):
            summary['equivalente (mismas horas 1990-2035)'] += 1
        else:
            summary['distinta'] += 1
            changed.append(f'  {iata}: {before} -> {zone}')
    print('Zonas respecto al archivo anterior:', dict(summary))
    for line in changed:
        print(line)


def write_timezones(out, rows):
    with open(out / 'airport_timezones.csv', 'w', newline='', encoding='utf-8') as handle:
        writer = csv.writer(handle, lineterminator='\n')
        writer.writerow(['id', 'name', 'iata_code', 'icao_code', 'latitude', 'longitude', 'timezone', 'timezone_status'])
        writer.writerows(rows)


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument('--out', type=Path, default=CATALOG, help='carpeta de salida (por defecto, el catálogo)')
    parser.add_argument('--refresh', action='store_true', help='volver a descargar las fuentes')
    parser.add_argument('--any-version', action='store_true', help='aceptar versiones de tzdata o Natural Earth distintas de las documentadas')
    args = parser.parse_args()

    mapping = read_csv(CATALOG / 'vyamap_airport_city_mapping.csv')
    download(args.refresh)
    check_versions(args.any_version)
    download_wikidata(args.refresh, mapping)

    out = args.out.resolve()
    out.mkdir(parents=True, exist_ok=True)

    write_countries(out)
    write_cities(out, mapping)
    airports = write_airports(out, {entry['iata_code'] for entry in mapping})
    rows, methods = build_timezones(mapping, airports)
    compare(out / 'airport_timezones.csv', rows)
    write_timezones(out, rows)

    print(f'Escrito en {out}: countries.csv, countries_overrides.csv, vyamap_airport_city_mapping.csv, airports.csv ({len(airports)} filas), '
          f'airport_timezones.csv ({len(rows)} filas)')
    print('Método por aeropuerto:', dict(methods))


if __name__ == '__main__':
    main()
