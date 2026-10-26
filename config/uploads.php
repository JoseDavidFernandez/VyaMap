<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subida de archivos
    |--------------------------------------------------------------------------
    |
    | Fotos de viaje y avatares. Con UPLOADS_ENABLED=false el servidor rechaza
    | cualquier archivo nuevo (POST /photos, avatar en el registro y en el
    | perfil) y la interfaz oculta los campos; lo ya subido se sigue viendo.
    | Por defecto, desactivado en producción y activado en el resto.
    |
    */

    'enabled' => (bool) env('UPLOADS_ENABLED', env('APP_ENV', 'production') !== 'production'),

];
