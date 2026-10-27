<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Subida de archivos
    |--------------------------------------------------------------------------
    |
    | Fotos de viaje y avatares. Desactivado en todos los entornos: nadie puede
    | subir archivos, tampoco las cuentas existentes. El servidor rechaza
    | cualquier archivo nuevo (POST /photos, avatar en el registro y en el
    | perfil) y la interfaz oculta los campos; las fotos y avatares ya subidos
    | se siguen viendo. Solo UPLOADS_ENABLED=true, explícito, lo reactivaría.
    |
    */

    'enabled' => (bool) env('UPLOADS_ENABLED', false),

];
