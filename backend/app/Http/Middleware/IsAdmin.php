<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Enums\UserRole;

class IsAdmin
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && $request->user()->role === UserRole::ADMIN->value) {
            return $next($request);
        }

        abort(403, 'Unauthorized action.');
    }
}
