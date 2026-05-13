<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
   public function handle(Request $request, Closure $next)
{
    $origin = $request->headers->get('Origin');
    $allowedOrigin = 'http://192.168.2.221:8192';

    // 1. If there's no origin (direct request), just proceed
    if (!$origin) {
        return $next($request);
    }

    // 2. If the origin isn't allowed, block it
    if ($origin !== $allowedOrigin) {
        return response()->json(['message' => 'CORS policy: Origin not allowed.'], 403);
    }

    // 3. Define the headers for the allowed origin
    $headers = [
        'Access-Control-Allow-Origin'      => $allowedOrigin,
        'Access-Control-Allow-Methods'     => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
        'Access-Control-Allow-Headers'     => 'X-Requested-With, Content-Type, X-CSRF-TOKEN, Authorization, Accept, X-Inertia, X-Inertia-Version',
        'Access-Control-Allow-Credentials' => 'true',
    ];

    // 4. Handle Preflight
    if ($request->isMethod('OPTIONS')) {
        return response('', 200, $headers);
    }

    // 5. Proceed and ATTACH the headers to the final response
    $response = $next($request);
    
    foreach ($headers as $key => $value) {
        $response->headers->set($key, $value);
    }

    return $response;
}
}
