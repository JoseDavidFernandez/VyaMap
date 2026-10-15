<?php

/*
|--------------------------------------------------------------------------
| Continentes
|--------------------------------------------------------------------------
|
| Continente de cada país o territorio del catálogo, por código ISO 3166-1
| alfa-2. Fuente: columna "continent" de storage/app/data/countries.csv
| (OurAirports), la misma que alimenta countries:import. Se genera una vez y
| se revisa a mano; no se consulta ninguna fuente externa en ejecución.
|
| Revisión manual:
| - Códigos especiales (config/countries.php: ZZ, XP): sin continente; el
|   CSV les asigna uno, pero no son países.
| - AX (Åland) y SJ (Svalbard y Jan Mayen) vienen de countries_overrides.csv,
|   que no tiene continente: Europa.
| - Países transcontinentales: se mantiene la asignación del CSV (RU → EU;
|   TR, CY, GE, AM, AZ, KZ → AS; EG → AF; PA → NA; PG → OC; ID, TL → AS).
| - AN (Antártida): AQ y GS (Georgias del Sur), según el CSV; sin Estados soberanos.
|
| Un código sin continente (o desconocido) no suma a ningún continente y se
| informa aparte (App\Services\TravelStatistics).
|
*/

return [

    'names' => [
        'AF' => 'Africa',
        'AN' => 'Antarctica',
        'AS' => 'Asia',
        'EU' => 'Europe',
        'NA' => 'North America',
        'OC' => 'Oceania',
        'SA' => 'South America',
    ],

    'countries' => [
        // AF: 59
        'AO' => 'AF', 'BF' => 'AF', 'BI' => 'AF', 'BJ' => 'AF', 'BW' => 'AF', 'CD' => 'AF', 'CF' => 'AF', 'CG' => 'AF', 'CI' => 'AF', 'CM' => 'AF', 'CV' => 'AF', 'DJ' => 'AF', 'DZ' => 'AF', 'EG' => 'AF', 'EH' => 'AF', 'ER' => 'AF',
        'ET' => 'AF', 'GA' => 'AF', 'GH' => 'AF', 'GM' => 'AF', 'GN' => 'AF', 'GQ' => 'AF', 'GW' => 'AF', 'KE' => 'AF', 'KM' => 'AF', 'LR' => 'AF', 'LS' => 'AF', 'LY' => 'AF', 'MA' => 'AF', 'MG' => 'AF', 'ML' => 'AF', 'MR' => 'AF',
        'MU' => 'AF', 'MW' => 'AF', 'MZ' => 'AF', 'NA' => 'AF', 'NE' => 'AF', 'NG' => 'AF', 'RE' => 'AF', 'RW' => 'AF', 'SC' => 'AF', 'SD' => 'AF', 'SH' => 'AF', 'SL' => 'AF', 'SN' => 'AF', 'SO' => 'AF', 'SS' => 'AF', 'ST' => 'AF',
        'SZ' => 'AF', 'TD' => 'AF', 'TF' => 'AF', 'TG' => 'AF', 'TN' => 'AF', 'TZ' => 'AF', 'UG' => 'AF', 'YT' => 'AF', 'ZA' => 'AF', 'ZM' => 'AF', 'ZW' => 'AF',
        // AN: 2
        'AQ' => 'AN', 'GS' => 'AN',
        // AS: 54
        'AE' => 'AS', 'AF' => 'AS', 'AM' => 'AS', 'AZ' => 'AS', 'BD' => 'AS', 'BH' => 'AS', 'BN' => 'AS', 'BT' => 'AS', 'CC' => 'AS', 'CN' => 'AS', 'CX' => 'AS', 'CY' => 'AS', 'GE' => 'AS', 'HK' => 'AS', 'ID' => 'AS', 'IL' => 'AS',
        'IN' => 'AS', 'IO' => 'AS', 'IQ' => 'AS', 'IR' => 'AS', 'JO' => 'AS', 'JP' => 'AS', 'KG' => 'AS', 'KH' => 'AS', 'KP' => 'AS', 'KR' => 'AS', 'KW' => 'AS', 'KZ' => 'AS', 'LA' => 'AS', 'LB' => 'AS', 'LK' => 'AS', 'MM' => 'AS',
        'MN' => 'AS', 'MO' => 'AS', 'MV' => 'AS', 'MY' => 'AS', 'NP' => 'AS', 'OM' => 'AS', 'PH' => 'AS', 'PK' => 'AS', 'PS' => 'AS', 'QA' => 'AS', 'SA' => 'AS', 'SG' => 'AS', 'SY' => 'AS', 'TH' => 'AS', 'TJ' => 'AS', 'TL' => 'AS',
        'TM' => 'AS', 'TR' => 'AS', 'TW' => 'AS', 'UZ' => 'AS', 'VN' => 'AS', 'YE' => 'AS',
        // EU: 52
        'AD' => 'EU', 'AL' => 'EU', 'AT' => 'EU', 'AX' => 'EU', 'BA' => 'EU', 'BE' => 'EU', 'BG' => 'EU', 'BY' => 'EU', 'CH' => 'EU', 'CZ' => 'EU', 'DE' => 'EU', 'DK' => 'EU', 'EE' => 'EU', 'ES' => 'EU', 'FI' => 'EU', 'FO' => 'EU',
        'FR' => 'EU', 'GB' => 'EU', 'GG' => 'EU', 'GI' => 'EU', 'GR' => 'EU', 'HR' => 'EU', 'HU' => 'EU', 'IE' => 'EU', 'IM' => 'EU', 'IS' => 'EU', 'IT' => 'EU', 'JE' => 'EU', 'LI' => 'EU', 'LT' => 'EU', 'LU' => 'EU', 'LV' => 'EU',
        'MC' => 'EU', 'MD' => 'EU', 'ME' => 'EU', 'MK' => 'EU', 'MT' => 'EU', 'NL' => 'EU', 'NO' => 'EU', 'PL' => 'EU', 'PT' => 'EU', 'RO' => 'EU', 'RS' => 'EU', 'RU' => 'EU', 'SE' => 'EU', 'SI' => 'EU', 'SJ' => 'EU', 'SK' => 'EU',
        'SM' => 'EU', 'UA' => 'EU', 'VA' => 'EU', 'XK' => 'EU',
        // NA: 41
        'AG' => 'NA', 'AI' => 'NA', 'AW' => 'NA', 'BB' => 'NA', 'BL' => 'NA', 'BM' => 'NA', 'BQ' => 'NA', 'BS' => 'NA', 'BZ' => 'NA', 'CA' => 'NA', 'CR' => 'NA', 'CU' => 'NA', 'CW' => 'NA', 'DM' => 'NA', 'DO' => 'NA', 'GD' => 'NA',
        'GL' => 'NA', 'GP' => 'NA', 'GT' => 'NA', 'HN' => 'NA', 'HT' => 'NA', 'JM' => 'NA', 'KN' => 'NA', 'KY' => 'NA', 'LC' => 'NA', 'MF' => 'NA', 'MQ' => 'NA', 'MS' => 'NA', 'MX' => 'NA', 'NI' => 'NA', 'PA' => 'NA', 'PM' => 'NA',
        'PR' => 'NA', 'SV' => 'NA', 'SX' => 'NA', 'TC' => 'NA', 'TT' => 'NA', 'US' => 'NA', 'VC' => 'NA', 'VG' => 'NA', 'VI' => 'NA',
        // OC: 27
        'AS' => 'OC', 'AU' => 'OC', 'CK' => 'OC', 'FJ' => 'OC', 'FM' => 'OC', 'GU' => 'OC', 'HM' => 'OC', 'KI' => 'OC', 'MH' => 'OC', 'MP' => 'OC', 'NC' => 'OC', 'NF' => 'OC', 'NR' => 'OC', 'NU' => 'OC', 'NZ' => 'OC', 'PF' => 'OC',
        'PG' => 'OC', 'PN' => 'OC', 'PW' => 'OC', 'SB' => 'OC', 'TK' => 'OC', 'TO' => 'OC', 'TV' => 'OC', 'UM' => 'OC', 'VU' => 'OC', 'WF' => 'OC', 'WS' => 'OC',
        // SA: 14
        'AR' => 'SA', 'BO' => 'SA', 'BR' => 'SA', 'CL' => 'SA', 'CO' => 'SA', 'EC' => 'SA', 'FK' => 'SA', 'GF' => 'SA', 'GY' => 'SA', 'PE' => 'SA', 'PY' => 'SA', 'SR' => 'SA', 'UY' => 'SA', 'VE' => 'SA',
    ],

];
