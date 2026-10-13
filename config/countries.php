<?php

/*
|--------------------------------------------------------------------------
| Clasificación de países para las estadísticas
|--------------------------------------------------------------------------
|
| El catálogo `countries` mezcla Estados soberanos, territorios dependientes
| y códigos especiales. El porcentaje de "países visitados" solo cuenta
| Estados soberanos, tanto en el numerador como en el denominador.
|
| Criterio: Estados miembros de las Naciones Unidas (193), según la lista
| oficial de la ONU (https://www.un.org/en/about-us/member-states),
| identificados por su código ISO 3166-1 alfa-2. No se borra ni se modifica
| ningún registro del catálogo: la clasificación solo afecta al cálculo.
|
| Clasificación del resto de registros:
|
| - additional_sovereign: Estados que se cuentan como soberanos aunque no
|   sean miembros de la ONU. Vacío hasta que se decida.
| - pending_decision: estatus discutido o sin una fuente única; NO se
|   cuentan hasta que se decida.
| - special_codes: códigos que no representan un país (desconocido,
|   territorio disputado sin Estado).
| - Cualquier otro código (p. ej. PR, HK, GL, RE, AQ) es un territorio
|   dependiente o una zona sin Estado propio y no se cuenta.
|
*/

return [

    'un_member_states' => [
        'AD', 'AE', 'AF', 'AG', 'AL', 'AM', 'AO', 'AR', 'AT', 'AU',
        'AZ', 'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ',
        'BN', 'BO', 'BR', 'BS', 'BT', 'BW', 'BY', 'BZ', 'CA', 'CD',
        'CF', 'CG', 'CH', 'CI', 'CL', 'CM', 'CN', 'CO', 'CR', 'CU',
        'CV', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC',
        'EE', 'EG', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FM', 'FR', 'GA',
        'GB', 'GD', 'GE', 'GH', 'GM', 'GN', 'GQ', 'GR', 'GT', 'GW',
        'GY', 'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IN', 'IQ',
        'IR', 'IS', 'IT', 'JM', 'JO', 'JP', 'KE', 'KG', 'KH', 'KI',
        'KM', 'KN', 'KP', 'KR', 'KW', 'KZ', 'LA', 'LB', 'LC', 'LI',
        'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD',
        'ME', 'MG', 'MH', 'MK', 'ML', 'MM', 'MN', 'MR', 'MT', 'MU',
        'MV', 'MW', 'MX', 'MY', 'MZ', 'NA', 'NE', 'NG', 'NI', 'NL',
        'NO', 'NP', 'NR', 'NZ', 'OM', 'PA', 'PE', 'PG', 'PH', 'PK',
        'PL', 'PT', 'PW', 'PY', 'QA', 'RO', 'RS', 'RU', 'RW', 'SA',
        'SB', 'SC', 'SD', 'SE', 'SG', 'SI', 'SK', 'SL', 'SM', 'SN',
        'SO', 'SR', 'SS', 'ST', 'SV', 'SY', 'SZ', 'TD', 'TG', 'TH',
        'TJ', 'TL', 'TM', 'TN', 'TO', 'TR', 'TT', 'TV', 'TZ', 'UA',
        'UG', 'US', 'UY', 'UZ', 'VC', 'VE', 'VN', 'VU', 'WS', 'YE',
        'ZA', 'ZM', 'ZW',
    ],

    'additional_sovereign' => [],

    'pending_decision' => [
        'VA', // Santa Sede: Estado observador permanente de la ONU.
        'PS', // Palestina: Estado observador permanente de la ONU.
        'XK', // Kosovo: reconocimiento parcial; código no oficial ISO.
        'TW', // Taiwán: reconocimiento parcial.
        'EH', // Sáhara Occidental: territorio no autónomo en disputa.
        'CK', // Islas Cook: libre asociación con Nueva Zelanda.
        'NU', // Niue: libre asociación con Nueva Zelanda.
    ],

    'special_codes' => [
        'ZZ', // Desconocido o no asignado.
        'XP', // Islas Paracel: territorio disputado sin Estado propio.
    ],

];
