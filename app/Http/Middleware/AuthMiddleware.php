<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia; // ✅ Add this line
use App\Models\NotificationUser;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->query('key') ?? session('emp_data.token');

        // 1️⃣ Redirect if no token
        if (!$token) {
            $redirectUrl = urlencode($request->fullUrl());
            return redirect("http://192.168.2.221/authify/public/login?redirect={$redirectUrl}");
        }

        // 2️⃣ Fetch user if no session or new key is passed
        if (!session()->has('emp_data') || $request->query('key')) {
            $currentUser = DB::connection('authify')->table('authify.authify_sessions')
                ->where('token', $token)
                ->first();

            if (!$currentUser) {
                $redirectUrl = urlencode($request->fullUrl());
                return redirect("http://192.168.2.221/authify/public/login?redirect={$redirectUrl}");
            }
            // ✅ Access control
            $canAccess = false;

            // 1. Allow if emp_position > 2
            if ($currentUser->emp_position > 2) {
                $canAccess = true;
            }
            // 2. Allow if emp_id is PROJ_HANDLER
            if (!$canAccess) {
                $isHandler = DB::connection('projects')->table('project_list') // Adjust table/connection if needed
                    ->where('PROJ_HANDLER', $currentUser->emp_id)
                    ->exists();

                if ($isHandler) {
                    $canAccess = true;
                }
            }
            // 3. Allow if job title contains "Programmer"
            if (
                stripos($currentUser->emp_jobtitle, 'programmer') !== false ||
                stripos($currentUser->emp_jobtitle, 'MIS Senior Supervisor') !== false
            ) {
                $canAccess = true;
            }

            // ❌ Deny if not allowed
            if (!$canAccess) {
                // Clear Laravel session
                session()->forget('emp_data');
                session()->flush();

                // Prepare logout link
                $redirectUrl = urlencode(route('dashboard'));
                $authifyUrl = "http://192.168.2.221/authify/public/logout?redirect={$redirectUrl}";

                // Show warning page before redirect
                return Inertia::render('Unauthorized', [
                    'logoutUrl' => $authifyUrl,
                    'message' => 'Access Restricted: You do not have permission to access the TPTMS.',
                ]);
            }

            // ✅ Assign system role if Programmer
            $systemRole = null;
            if (
                stripos($currentUser->emp_jobtitle, 'programmer') !== false ||
                stripos($currentUser->emp_jobtitle, 'MIS Senior Supervisor') !== false
            ) {
                $systemRole = 'Programmer';
            }

            // 3️⃣ Set session
            session(['emp_data' => [
                'token' => $currentUser->token,
                'emp_id' => $currentUser->emp_id,
                'emp_name' => $currentUser->emp_name,
                'emp_position' => $currentUser->emp_position,
                'emp_firstname' => $currentUser->emp_firstname,
                'emp_jobtitle' => $currentUser->emp_jobtitle,
                'emp_dept' => $currentUser->emp_dept,
                'emp_prodline' => $currentUser->emp_prodline,
                'emp_station' => $currentUser->emp_station,
                'generated_at' => $currentUser->generated_at,
                'emp_system_role' => $systemRole,
            ]]);

            // 4️⃣ Notification user record
            $user = NotificationUser::firstOrCreate(
                ['emp_id' => $currentUser->emp_id],
                [
                    'emp_name' => $currentUser->emp_name,
                    'emp_dept' => $currentUser->emp_dept,
                ]
            );

            // Set user for broadcasting
            $request->setUserResolver(function () use ($user) {
                return $user;
            });
        }

        return $next($request);
    }
}
