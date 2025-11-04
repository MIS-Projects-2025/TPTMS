<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ProgrammerMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $empData = session('emp_data');

        // Check if user is logged in
        if (!$empData) {
            abort(403, 'Unauthorized: Please log in to access this page.');
        }

        // Check if user is a programmer
        if (($empData['emp_system_role'] ?? null) !== 'Programmer') {
            abort(403, 'Unauthorized: Only programmers can access task management.');
        }

        return $next($request);
    }
}
