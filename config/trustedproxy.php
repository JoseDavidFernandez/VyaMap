<?php

/*
|--------------------------------------------------------------------------
| Proxies de confianza
|--------------------------------------------------------------------------
|
| Detrás de un balanceador o proxy inverso que termina HTTPS, Laravel solo
| usa las cabeceras X-Forwarded-* (protocolo, host, IP del cliente) de los
| proxies de confianza; sin ellos generaría URLs http:// y vería la IP del
| proxy en los límites de frecuencia.
|
| TRUSTED_PROXIES: "*" (el proxy que llama, típico de las plataformas
| gestionadas) o una lista de IP/CIDR separadas por comas. Sin definir no se
| confía en ninguno, salvo la detección automática del framework para
| Laravel Cloud, Forge y Vapor (Illuminate\Http\Middleware\TrustProxies).
|
*/

return [

    'proxies' => env('TRUSTED_PROXIES'),

];
