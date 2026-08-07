<?php

namespace App\Http\Middleware;

use App\Services\ContactFormAvailabilityService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnsureContactFormIsEnabled
{
    public function __construct(
        private readonly ContactFormAvailabilityService $availability
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        if (! $this->availability->isEnabled()) {
            return new JsonResponse([
                'message' => 'El formulario de contacto no está disponible.',
                'data' => null,
            ], 503);
        }

        return $next($request);
    }
}
