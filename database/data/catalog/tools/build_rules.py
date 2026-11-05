"""Reglas de zona horaria por región, por país y por aeropuerto.

Las usa assign.py. Cada regla sale de zone.tab o de los comentarios de los
archivos fuente de la base de datos tz (northamerica, southamerica, asia,
australasia); el comentario de cada línea indica cuál.

- REGIONS: región ISO 3166-2 (iso_region de OurAirports) -> zona, o lista de
  zonas posibles con la principal primero.
- COUNTRY_DEFAULTS: zona de las regiones sin regla en países con varias zonas.
- AIRPORTS: excepciones por código IATA.

Ejecutado directamente, comprueba que cada zona existe en zone.tab para su
país (hace falta haber descargado los datos con gen.py).
"""

REGIONS = {}


def put(zone, *regions):
    for region in regions:
        REGIONS[region] = zone


# Argentina (zone.tab: provincias por zona)
put('America/Argentina/Buenos_Aires', 'AR-B', 'AR-C')
put('America/Argentina/Cordoba', 'AR-X', 'AR-W', 'AR-H', 'AR-E', 'AR-P', 'AR-N', 'AR-G', 'AR-S')
put('America/Argentina/Salta', 'AR-A', 'AR-L', 'AR-Q', 'AR-R')
put('America/Argentina/Jujuy', 'AR-Y')
put('America/Argentina/Tucuman', 'AR-T')
put('America/Argentina/Catamarca', 'AR-K', 'AR-U')
put('America/Argentina/La_Rioja', 'AR-F')
put('America/Argentina/San_Juan', 'AR-J')
put('America/Argentina/Mendoza', 'AR-M')
put('America/Argentina/San_Luis', 'AR-D')
put('America/Argentina/Rio_Gallegos', 'AR-Z')
put('America/Argentina/Ushuaia', 'AR-V')

# Australia
put(['Australia/Sydney', 'Australia/Broken_Hill', 'Australia/Lord_Howe'], 'AU-NSW')
put('Australia/Sydney', 'AU-ACT')
put(['Australia/Brisbane', 'Australia/Lindeman'], 'AU-QLD')
put('Australia/Darwin', 'AU-NT')
put('Australia/Adelaide', 'AU-SA')
put('Australia/Hobart', 'AU-TAS')
put('Australia/Melbourne', 'AU-VIC')
put(['Australia/Perth', 'Australia/Eucla'], 'AU-WA')

# Brasil (zone.tab: estados por zona)
put('America/Rio_Branco', 'BR-AC')
put('America/Maceio', 'BR-AL', 'BR-SE')
put('America/Belem', 'BR-AP')
put(['America/Manaus', 'America/Eirunepe'], 'BR-AM')
put('America/Bahia', 'BR-BA')
put('America/Fortaleza', 'BR-CE', 'BR-MA', 'BR-PI', 'BR-RN', 'BR-PB')
put(['America/Recife', 'America/Noronha'], 'BR-PE')
put('America/Araguaina', 'BR-TO')
put('America/Sao_Paulo', 'BR-GO', 'BR-DF', 'BR-MG', 'BR-ES', 'BR-RJ', 'BR-SP', 'BR-PR', 'BR-SC', 'BR-RS')
put('America/Campo_Grande', 'BR-MS')
put('America/Cuiaba', 'BR-MT')
put(['America/Belem', 'America/Santarem'], 'BR-PA')
put('America/Porto_Velho', 'BR-RO')
put('America/Boa_Vista', 'BR-RR')

# Canadá
put('America/Edmonton', 'CA-AB')
put(['America/Vancouver', 'America/Edmonton', 'America/Creston', 'America/Dawson_Creek', 'America/Fort_Nelson'], 'CA-BC')
put('America/Winnipeg', 'CA-MB')
put('America/Moncton', 'CA-NB')
put(['America/St_Johns', 'America/Goose_Bay'], 'CA-NL')
put(['America/Halifax', 'America/Glace_Bay'], 'CA-NS')
put('America/Halifax', 'CA-PE')
put(['America/Edmonton', 'America/Inuvik'], 'CA-NT')
put(['America/Iqaluit', 'America/Rankin_Inlet', 'America/Resolute', 'America/Cambridge_Bay', 'America/Atikokan'], 'CA-NU')
put(['America/Toronto', 'America/Winnipeg', 'America/Atikokan'], 'CA-ON')
put(['America/Toronto', 'America/Blanc-Sablon', 'America/Halifax'], 'CA-QC')
put(['America/Regina', 'America/Swift_Current', 'America/Edmonton'], 'CA-SK')
put(['America/Whitehorse', 'America/Dawson'], 'CA-YT')

# Chile, China, España, Kazajistán, Islas Marshall, Papúa Nueva Guinea, Uzbekistán
put('America/Coyhaique', 'CL-AI')
put('America/Punta_Arenas', 'CL-MA')
put('Asia/Shanghai', 'CN-65')  # asia: aviones y trenes de Xinjiang usan la hora de Pekín
put('Africa/Ceuta', 'ES-CE', 'ES-ML')
put('Atlantic/Canary', 'ES-CN')
put('Asia/Aqtobe', 'KZ-AKT')
put('Asia/Atyrau', 'KZ-ATY')
put('Asia/Qyzylorda', 'KZ-KZY', 'KZ-BAY')
put('Asia/Qostanay', 'KZ-KUS')
put('Asia/Aqtau', 'KZ-MAN')
put('Asia/Oral', 'KZ-ZAP')
put('Pacific/Kwajalein', 'MH-KWA')
put('Pacific/Bougainville', 'PG-NSB')
put('Asia/Tashkent', 'UZ-JI')

# México (zone.tab): Central Mexico salvo los estados citados
put(['America/Chihuahua', 'America/Ciudad_Juarez', 'America/Ojinaga'], 'MX-CHH')
put(['America/Monterrey', 'America/Matamoros'], 'MX-COA', 'MX-TAM', 'MX-NLE')
put('America/Monterrey', 'MX-DUR')
put('America/Cancun', 'MX-ROO')
put('America/Hermosillo', 'MX-SON')
put('America/Merida', 'MX-CAM', 'MX-YUC')
put('America/Mazatlan', 'MX-BCS', 'MX-SIN', 'MX-NAY')
put('America/Tijuana', 'MX-BCN')

# Indonesia (zone.tab): Java y Sumatra por defecto; Borneo oeste y centro;
# Borneo este y sur, Sulawesi, Bali y Nusa Tenggara; Molucas y Nueva Guinea
put('Asia/Pontianak', 'ID-KB', 'ID-KT')
put('Asia/Makassar', *['ID-' + code for code in 'BA NB NT KS KI KU SA ST SN SG SR GO'.split()])
put('Asia/Jayapura', *['ID-' + code for code in 'MA MU PA PB PP PS PT PD PE'.split()])

# Rusia (zone.tab: MSK+n por sujeto federal)
put('Europe/Kaliningrad', 'RU-KGD')
put('Europe/Moscow', *['RU-' + code for code in (
    'AD ARK BEL BRY CE CU DA IN IVA KB KC KDA KL KLU KO KOS KR KRS LEN LIP ME MO MOS MOW '
    'MUR NEN NGR NIZ ORL PNZ PSK RYA ROS SE SMO SPE STA TAM TA TUL TVE VLA VLG VOR YAR'
).split()])
put('Europe/Volgograd', 'RU-VGG')
put('Europe/Kirov', 'RU-KIR')
put('Europe/Samara', 'RU-SAM', 'RU-UD')
put('Europe/Astrakhan', 'RU-AST')
put('Europe/Saratov', 'RU-SAR')
put('Europe/Ulyanovsk', 'RU-ULY')
put('Asia/Yekaterinburg', *['RU-' + code for code in 'BA CHE KGN KHM ORE PER SVE TYU YAN'.split()])
put('Asia/Omsk', 'RU-OMS')
put('Asia/Novosibirsk', 'RU-NVS')
put('Asia/Barnaul', 'RU-ALT', 'RU-AL')
put('Asia/Tomsk', 'RU-TOM')
put('Asia/Novokuznetsk', 'RU-KEM')
put('Asia/Krasnoyarsk', 'RU-KYA', 'RU-KK', 'RU-TY')
put('Asia/Irkutsk', 'RU-IRK', 'RU-BU')
put('Asia/Chita', 'RU-ZAB')
put('Asia/Yakutsk', 'RU-AMU', 'RU-SA')  # Sakha: río Lena; el resto, en AIRPORTS
put('Asia/Vladivostok', 'RU-PRI', 'RU-KHA', 'RU-YEV')
put('Asia/Magadan', 'RU-MAG')
put('Asia/Sakhalin', 'RU-SAK')
put('Asia/Kamchatka', 'RU-KAM')
put('Asia/Anadyr', 'RU-CHU')

# Estados Unidos
E, C, M, P = 'America/New_York', 'America/Chicago', 'America/Denver', 'America/Los_Angeles'
put(E, *['US-' + state for state in 'CT DE DC GA ME MD MA NH NJ NY NC OH PA RI SC VT VA WV'.split()])
put(C, *['US-' + state for state in 'AL AR IL IA LA MN MS MO OK WI'.split()])
put(M, *['US-' + state for state in 'CO MT NM UT WY'.split()])
put(P, 'US-CA', 'US-NV', 'US-WA')
put('America/Phoenix', 'US-AZ')
put('Pacific/Honolulu', 'US-HI')
put([E, C], 'US-FL', 'US-TN')
put([E, C, 'America/Kentucky/Louisville'], 'US-KY')
put(['America/Indiana/Indianapolis', C], 'US-IN')
put([C, M], 'US-TX', 'US-ND', 'US-SD', 'US-NE', 'US-KS')
put(['America/Boise', P], 'US-ID', 'US-OR')
put(['America/Detroit', 'America/Menominee'], 'US-MI')
put(['America/Anchorage', 'America/Juneau', 'America/Sitka', 'America/Yakutat', 'America/Nome',
     'America/Adak', 'America/Metlakatla'], 'US-AK')

COUNTRY_DEFAULTS = {'ES': 'Europe/Madrid', 'MX': 'America/Mexico_City', 'ID': 'Asia/Jakarta'}

AIRPORTS = {
    'CNP': 'America/Scoresbysund',                                  # Scoresbysund/Ittoqqortoormiit
    'YBX': 'America/Blanc-Sablon', 'YIF': 'America/Blanc-Sablon',   # Quebec al este de Natashquan
    'YGR': 'America/Halifax',                                       # Îles de la Madeleine
    'YPL': 'America/Atikokan',                                      # Pickle Lake: EST todo el año
    'YZS': 'America/Atikokan',                                      # NU (Coral H)
    'YXC': 'America/Edmonton',                                      # BC (E)
    'YYE': 'America/Fort_Nelson',                                   # BC (Ft Nelson)
    'YQY': 'America/Glace_Bay',                                     # Atlantic - NS (Cape Breton)
    'YEV': 'America/Inuvik', 'YDA': 'America/Dawson',               # ciudades que dan nombre a su zona
    'ECN': 'Asia/Famagusta',                                        # Northern Cyprus
    'CUU': 'America/Chihuahua',                                     # Chihuahua (most areas)
    'MAM': 'America/Matamoros', 'NLD': 'America/Matamoros',
    'REX': 'America/Matamoros', 'PDS': 'America/Matamoros',         # US border
    'GMR': 'Pacific/Gambier',                                       # Gambier Islands
    'TBT': 'America/Eirunepe',                                      # Amazonas (west); en la línea divisoria
    'ATM': 'America/Santarem', 'ITB': 'America/Santarem',
    'STM': 'America/Santarem', 'TMT': 'America/Santarem',           # Pará al oeste del Xingu
    'USR': 'Asia/Ust-Nera',                                         # Oymyakonsky
    'MQJ': 'Asia/Srednekolymsk', 'SEK': 'Asia/Srednekolymsk', 'CKH': 'Asia/Srednekolymsk',
    'CYX': 'Asia/Srednekolymsk', 'ZKP': 'Asia/Srednekolymsk',       # Sakha (E)
    'BHQ': 'Australia/Broken_Hill',                                 # New South Wales (Yancowinna)
    'HTI': 'Australia/Lindeman',                                    # Queensland (Whitsunday Islands)
    'SDF': 'America/Kentucky/Louisville',                           # Eastern - KY (Louisville area)
    'JNU': 'America/Juneau', 'SIT': 'America/Sitka',
    'OME': 'America/Nome', 'YAK': 'America/Yakutat',                # ciudades que dan nombre a su zona
    'MHQ': 'Europe/Mariehamn', 'LYR': 'Arctic/Longyearbyen',        # zone.tab AX y SJ (countries_overrides.csv)
}


# Ciudad servida por aeropuerto (cities.py). Excepciones revisadas a mano el
# 05-11-2026; las coordenadas salen siempre de la ciudad en Wikidata.
#
# CITY_OVERRIDES: Wikidata solo da la localidad del aeropuerto y su nombre no
# cita la ciudad a la que sirve, así que el buscador no lo encontraría por ella.
CITY_OVERRIDES = {
    'KEF': 'Reykjavík',    # Wikidata: Keflavík
    'MRU': 'Port Louis',   # Wikidata: Plaine Magnien
    'CCE': 'Cairo',        # Wikidata: The New Capital
}

# ADMINISTRATIVE_P931: su ciudad servida en Wikidata es un atolón o una
# prefectura, unidades administrativas como los condados; se tratan como sin
# ciudad utilizable y conservan la actual (regla de estabilidad).
ADMINISTRATIVE_P931 = {
    'KDM',   # Huvadhu Atoll
    'GXH',   # Gannan (prefectura)
    'DCY',   # Garzê (prefectura)
    'DDR',   # Xigazê (prefectura)
}


if __name__ == '__main__':
    import sys
    from pathlib import Path
    from tzlib import load_zonetab

    zonetab = Path(__file__).resolve().parent / 'downloads' / 'zone.tab'
    if not zonetab.is_file():
        sys.exit(f'Falta {zonetab}: ejecuta antes gen.py (descarga los datos).')

    known = {entry['zone'] for entries in load_zonetab(zonetab).values() for entry in entries}
    used = set(COUNTRY_DEFAULTS.values()) | set(AIRPORTS.values())
    for rule in REGIONS.values():
        used.update([rule] if isinstance(rule, str) else rule)

    unknown = sorted(used - known)
    print(f'{len(REGIONS)} reglas de región, {len(COUNTRY_DEFAULTS)} países por defecto, {len(AIRPORTS)} excepciones por aeropuerto')
    if unknown:
        sys.exit('Zonas que no están en zone.tab: ' + ', '.join(unknown))
    print('Todas las zonas existen en zone.tab.')
