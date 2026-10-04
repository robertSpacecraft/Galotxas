<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Retirada controlada del Bearer legacy (transición 5.7-J, J4)
    |--------------------------------------------------------------------------
    |
    | Ambos controles valen `true` por defecto: el comportamiento Bearer vigente
    | no cambia. Estados válidos (emisión / aceptación):
    |
    |   true  / true   compatibilidad inicial
    |   false / true   sin emisión nueva; los PAT existentes siguen funcionando
    |   false / false  retirada final
    |
    | true / false es inválido (se emitirían credenciales que se rechazan al
    | instante) y lo bloquea `deploy:check`. Sólo el valor booleano `false`
    | desactiva un control; `deploy:check` exige además valores booleanos.
    |
    */

    'issuance_enabled' => env('LEGACY_BEARER_ISSUANCE_ENABLED', true),

    'acceptance_enabled' => env('LEGACY_BEARER_ACCEPTANCE_ENABLED', true),

];
