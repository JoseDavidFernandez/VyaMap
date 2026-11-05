<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cuenta de demostración
    |--------------------------------------------------------------------------
    |
    | Correo de la cuenta de solo lectura del portfolio. Esa cuenta puede verlo
    | todo pero no escribir nada (App\Http\Middleware\DemoReadOnly), ni pedir la
    | recuperación de su contraseña; el login muestra «Try the demo», que inicia
    | su sesión desde el servidor sin enviar credenciales al navegador.
    |
    | Se identifica por esta configuración, no por un dato editable desde la
    | aplicación. Vacío: no hay cuenta de demostración. Se rellena con
    | `php artisan demo:reset <correo>`.
    |
    */

    'email' => env('DEMO_USER_EMAIL') ?: null,

];
