<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class LivenessController extends Controller
{
    public function __invoke(): Response
    {
        return response("OK\n", 200, [
            'Cache-Control' => 'no-store',
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
