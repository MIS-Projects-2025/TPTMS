<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\NotificationUser;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // ✅ Skip auth for OPTIONS requests
        if ($request->isMethod('OPTIONS')) {
            return $next($request);
        }
        // dd(session('emp_data.token'));
        $token = session('emp_data.token') ?? $_COOKIE['sso_token'] ?? null;



        if (!$token) {
            $redirectUrl = urlencode($request->fullUrl());
            return redirect("https://192.168.2.221/authify/public/login?redirect={$redirectUrl}");
        }

        if (!session()->has('emp_data') || $request->query('key')) {
            $currentUser = DB::connection('authify')->table('authify.authify_sessions')
                ->where('token', $token)
                ->first();

            if (!$currentUser) {
                $redirectUrl = urlencode($request->fullUrl());
                return redirect("https://192.168.2.221/authify/public/login?redirect={$redirectUrl}");
            }

            // ✅ Access control
            $canAccess = $currentUser->emp_position > 2 ||
                stripos($currentUser->emp_jobtitle, 'programmer') !== false ||
                stripos($currentUser->emp_jobtitle, 'MIS Senior Supervisor') !== false ||
                DB::connection('projects')->table('project_list')
                ->where('PROJ_HANDLER', $currentUser->emp_id)
                ->exists();

            if (!$canAccess) {
                session()->forget('emp_data');
                session()->flush();
                $redirectUrl = urlencode(route('dashboard'));
                $authifyUrl = "https://192.168.2.221/authify/public/logout?redirect={$redirectUrl}";

                return Inertia::render('Unauthorized', [
                    'logoutUrl' => $authifyUrl,
                    'message' => 'Access Restricted: You do not have permission to access the TPTMS.',
                ]);
            }

            $systemRole = (stripos($currentUser->emp_jobtitle, 'programmer') !== false ||
                stripos($currentUser->emp_jobtitle, 'MIS Senior Supervisor') !== false)
                ? 'Programmer' : null;

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

            $user = NotificationUser::firstOrCreate(
                ['emp_id' => $currentUser->emp_id],
                ['emp_name' => $currentUser->emp_name, 'emp_dept' => $currentUser->emp_dept]
            );

            $request->setUserResolver(fn() => $user);
        }

        return $next($request);
    }
}
