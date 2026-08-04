<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnsureContactFormIsEnabled
{
    public function handle(Request $request, Closure $next): mixed
    {
        if (! config('contact.form_enabled')) {
            return new JsonResponse([
                'message' => 'El formulario de contacto no está disponible.',
                'data' => null,
            ], 503);
        }

        return $next($request);
    }
}
