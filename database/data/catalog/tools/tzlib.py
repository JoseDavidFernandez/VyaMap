"""Utilidades sobre la base de datos tz: zone.tab, distancias y equivalencia de zonas."""
import math
from collections import defaultdict
from datetime import datetime, timedelta, timezone
from zoneinfo import ZoneInfo


def parse_coord(text):
    """Coordenadas de zone.tab (±DDMM[SS]±DDDMM[SS]) en grados decimales."""
    split = max(text.rfind('+'), text.rfind('-'))

    def convert(part, degree_digits):
        sign = -1 if part[0] == '-' else 1
        part = part[1:]
        degrees = int(part[:degree_digits])
        minutes = int(part[degree_digits:degree_digits + 2])
        seconds = int(part[degree_digits + 2:degree_digits + 4] or 0)
        return sign * (degrees + minutes / 60 + seconds / 3600)

    return convert(text[:split], 2), convert(text[split:], 3)


def load_zonetab(path):
    """Zonas por país: {código: [{'zone', 'lat', 'lon', 'comment'}, ...]}."""
    zones = defaultdict(list)
    with open(path, encoding='utf-8') as handle:
        for line in handle:
            if line.startswith('#') or not line.strip():
                continue
            fields = line.rstrip('\n').split('\t')
            lat, lon = parse_coord(fields[1])
            zones[fields[0]].append({
                'zone': fields[2],
                'lat': lat,
                'lon': lon,
                'comment': fields[3] if len(fields) > 3 else '',
            })
    return zones


def load_iso3166(path):
    """Nombres de país de iso3166.tab: {código: nombre}."""
    names = {}
    with open(path, encoding='utf-8') as handle:
        for line in handle:
            if line.startswith('#') or not line.strip():
                continue
            code, name = line.rstrip('\n').split('\t')
            names[code] = name
    return names


def dist(lat1, lon1, lat2, lon2):
    """Distancia de círculo máximo en km."""
    lat1, lon1, lat2, lon2 = map(math.radians, (lat1, lon1, lat2, lon2))
    h = math.sin((lat2 - lat1) / 2) ** 2 + math.cos(lat1) * math.cos(lat2) * math.sin((lon2 - lon1) / 2) ** 2
    return 6371 * 2 * math.asin(math.sqrt(h))


_SAMPLES = [datetime(1990, 1, 1, tzinfo=timezone.utc) + timedelta(hours=6 * i) for i in range(int(46 * 365.25 * 4))]
_OFFSETS = {}


def _offsets(zone):
    if zone not in _OFFSETS:
        info = ZoneInfo(zone)
        _OFFSETS[zone] = tuple(moment.astimezone(info).utcoffset() for moment in _SAMPLES)
    return _OFFSETS[zone]


def equivalent(zone_a, zone_b):
    """Si dos zonas dan las mismas horas locales entre 1990 y 2035 (muestras cada 6 horas)."""
    return zone_a == zone_b or _offsets(zone_a) == _offsets(zone_b)
