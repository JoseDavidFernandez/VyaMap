<?php

return [

    /*
    | Distancia máxima (km) para considerar que dos registros con el mismo
    | nombre normalizado y el mismo país representan la misma ciudad. Se usa
    | al reconciliar duplicados y al evitar duplicados nuevos desde Geoapify.
    */

    'same_city_max_distance_km' => 25,

];
