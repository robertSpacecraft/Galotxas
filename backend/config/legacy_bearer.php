<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Compuertas del Bearer legacy (5.7-J)
    |--------------------------------------------------------------------------
    |
    | La autenticación normal es la sesión SPA por cookie HttpOnly. Los
    | personal access tokens de Sanctum están retirados: por defecto ni se
    | emiten (`POST /auth/login|register` responden 403) ni se aceptan (todo
    | `Authorization: Bearer` recibe 401). Ambos controles son fail-closed.
    |
    | Estados (emisión / aceptación):
    |
    |   false / false  estado normal y final
    |   false / true   sin emisión nueva; los PAT existentes siguen valiendo
    |   true  / true   compatibilidad Bearer explícita (break-glass)
    |
    | true / false es inválido (se emitirían credenciales que se rechazan al
    | instante) y lo bloquea `deploy:check`, igual que los valores no
    | booleanos. Sólo el booleano `true` activa un control cuando se declara.
    |
    */

    'issuance_enabled' => env('LEGACY_BEARER_ISSUANCE_ENABLED', false),

    'acceptance_enabled' => env('LEGACY_BEARER_ACCEPTANCE_ENABLED', false),

];
