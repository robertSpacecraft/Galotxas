<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sesión de primera parte para la SPA (transición 5.7-J)
    |--------------------------------------------------------------------------
    |
    | Capacidad apagada por defecto. Con `false` la API se comporta como el
    | contrato Bearer vigente. El origen permitido es siempre FRONTEND_URL.
    |
    */

    'enabled' => (bool) env('SPA_SESSION_AUTH_ENABLED', false),

    /*
    | Cookie propia de la SPA, distinta de `session.cookie` (Blade). Siempre
    | host-only: no existe un dominio configurable para ella.
    */

    'cookie' => env('SPA_SESSION_COOKIE', 'galotxas-spa-session'),

    /*
    | Señal explícita de modo. Sólo el valor exacto activa la sesión; nunca se
    | deduce del Origin ni del Referer.
    */

    'mode_header' => 'X-Galotxas-Auth-Mode',

    'mode_value' => 'session',

];
