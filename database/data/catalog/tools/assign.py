"""Asignación de la zona horaria IANA de un aeropuerto.

Orden (el primero que se aplique):

1. Excepción por aeropuerto (build_rules.AIRPORTS).
2. Regla de región con una sola zona (build_rules.REGIONS).
3. Zona por defecto del país, si la región no tiene regla (build_rules.COUNTRY_DEFAULTS).
4. País con una sola zona en zone.tab (Kosovo, que no figura, usa Europe/Belgrade).
5. Varias candidatas (las del país o las de la regla de región): se quedan
   las de desfase estándar igual al del polígono de Natural Earth en el
   punto; entre ellas, la principal (la primera de la regla de región o, sin
   regla, la marcada «most» en zone.tab) y, si no está, la de punto
   representativo más cercano.
"""
from datetime import datetime
from zoneinfo import ZoneInfo

import build_rules
from ne import NE
from tzlib import dist, load_zonetab

NO_ZONETAB = {'XK': 'Europe/Belgrade'}  # Kosovo: no es ISO 3166; zone1970.tab lo incluye en Europe/Belgrade


def std_offset(zone):
    info = ZoneInfo(zone)
    return min(datetime(2026, month, 15, 12, tzinfo=info).utcoffset().total_seconds() / 3600 for month in (1, 7))


class Assigner:
    def __init__(self, zonetab, ne_base, region_rules=None, airport_rules=None, country_defaults=None):
        self.zones = load_zonetab(zonetab)
        self.ne = NE(ne_base)
        self.region_rules = build_rules.REGIONS if region_rules is None else region_rules
        self.airport_rules = build_rules.AIRPORTS if airport_rules is None else airport_rules
        self.country_defaults = build_rules.COUNTRY_DEFAULTS if country_defaults is None else country_defaults
        self.std = {}

    def assign(self, iata, country, region, lat, lon):
        """(zona, cómo se ha decidido)."""
        if iata in self.airport_rules:
            return self.airport_rules[iata], 'airport_rule'

        rule = self.region_rules.get(region)
        if isinstance(rule, str):
            return rule, 'region_rule'
        if not rule and country in self.country_defaults:
            return self.country_defaults[country], 'country_default'
        if country in NO_ZONETAB:
            return NO_ZONETAB[country], 'country'

        candidates = self.zones[country]
        if rule:
            candidates = [c for c in candidates if c['zone'] in rule]
            assert len(candidates) == len(rule), f'{region}: zonas de la regla que no están en zone.tab ({rule})'
        if len(candidates) == 1:
            return candidates[0]['zone'], 'country'

        offsets = self.ne.offsets_at(lat, lon)
        for candidate in candidates:
            if candidate['zone'] not in self.std:
                self.std[candidate['zone']] = std_offset(candidate['zone'])
        matching = [c for c in candidates if self.std[c['zone']] in offsets] if offsets else []
        pool = matching or candidates

        if rule:
            principal = [c for c in pool if c['zone'] == rule[0]]
        else:
            principal = [c for c in pool if 'most' in c['comment'].lower()]
        pool = principal or pool

        zone = min(pool, key=lambda c: dist(lat, lon, c['lat'], c['lon']))['zone']
        return zone, 'offset+nearest' if matching else 'nearest'
