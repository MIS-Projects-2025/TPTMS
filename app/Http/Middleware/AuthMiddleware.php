<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // 1️⃣ Get token from URL or existing session
        $token = $request->query('key') ?? session('emp_data.token');

        // 2️⃣ If no token, redirect to SSO login
        if (!$token) {
            $redirectUrl = urlencode($request->fullUrl());
            return redirect("http://192.168.2.221/authify/public/login?redirect={$redirectUrl}");
        }

        // 3️⃣ If session is missing or URL has a key, fetch user info
        if (!session()->has('emp_data') || $request->query('key')) {
            $currentUser = DB::connection('authify')->table('authify.authify_sessions')
                ->where('token', $token)
                ->first();

            if (!$currentUser) {
                // Token invalid, redirect to SSO login
                $redirectUrl = urlencode($request->fullUrl());
                return redirect("http://192.168.2.221/authify/public/login?redirect={$redirectUrl}");
            }

            // 4️⃣ Set session
            session(['emp_data' => [
                'token' => $currentUser->token,
                'emp_id' => $currentUser->emp_id,
                'emp_name' => $currentUser->emp_name,
                'emp_firstname' => $currentUser->emp_firstname,
                'emp_jobtitle' => $currentUser->emp_jobtitle,
                'emp_dept' => $currentUser->emp_dept,
                'emp_prodline' => $currentUser->emp_prodline,
                'emp_station' => $currentUser->emp_station,
                'generated_at' => $currentUser->generated_at,
            ]]);
        }

        return $next($request);
    }
}
