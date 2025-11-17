<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // ✅ Only apply CORS if there's a different Origin
        $origin = $request->headers->get('Origin');
        $allowedOrigin = 'https://192.168.2.221:85';

        // Skip CORS for same-origin or no origin
        if (!$origin || $origin === $allowedOrigin) {
            return $next($request);
        }

        // Different origin - apply CORS
        $headers = [
            'Access-Control-Allow-Origin' => $allowedOrigin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => 'X-Requested-With, Content-Type, X-CSRF-TOKEN, Authorization, Accept, X-Inertia, X-Inertia-Version',
            'Access-Control-Allow-Credentials' => 'true',
        ];

        if ($request->isMethod('OPTIONS')) {
            return response('', 200, $headers);
        }

        // Block different origins
        return response()->json([
            'status' => 'error',
            'message' => 'CORS policy: Origin not allowed.',
        ], 403);
    }
}
