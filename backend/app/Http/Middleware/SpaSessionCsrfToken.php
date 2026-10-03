<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

/**
 * CSRF de la sesión SPA: siempre se exige (también bajo PHPUnit) y no emite la
 * cookie XSRF-TOKEN, porque la cookie de sesión es host-only en la API y React
 * recibe el token por JSON.
 */
class SpaSessionCsrfToken extends VerifyCsrfToken
{
    protected $addHttpCookie = false;

    protected function runningUnitTests()
    {
        return false;
    }
}
