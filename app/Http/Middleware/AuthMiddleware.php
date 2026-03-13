<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use App\Models\NotificationUser;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // 🔹 Get token sources (priority: query → cookie → session)
        $tokenFromQuery   = $request->query('key');
        $tokenFromCookie  = $request->cookie('sso_token');
        $tokenFromSession = session('emp_data.token');

        $token = $tokenFromQuery ?? $tokenFromCookie ?? $tokenFromSession;

        Log::info('AuthMiddleware token check', [
            'query'   => $tokenFromQuery,
            'cookie'  => $tokenFromCookie,
            'session' => $tokenFromSession,
            'used'    => $token,
        ]);

        // 🔹 1️⃣ No token → redirect to login server
        if (!$token) {
            return $this->redirectToLogin($request);
        }

        // 🔹 2️⃣ Session exists and token matches → continue
        if (session()->has('emp_data') && session('emp_data.token') === $token) {
            return $next($request);
        }

        // 🔹 3️⃣ Fetch user info from login server DB
        $currentUser = DB::connection('authify')
            ->table('authify_sessions')
            ->where('token', $token)
            ->first();

        if (!$currentUser) {
            session()->forget('emp_data');
            return $this->redirectToLogin($request);
        }

        // 🔹 4️⃣ Access control (customize your rules)
        // Check if employee is allowed based on emp_from
        $isFromAllowed = $currentUser->emp_from === NULL;

        // Check role-based access
        $hasRoleAccess =
            $currentUser->emp_position >= 2 ||
            stripos($currentUser->emp_jobtitle, 'programmer') !== false ||
            stripos($currentUser->emp_jobtitle, 'MIS Senior Supervisor') !== false;

        // Check if user is project handler
        $isProjectHandler = DB::connection('projects')
            ->table('project_list')
            ->where('PROJ_HANDLER', $currentUser->emp_id)
            ->exists();

        // Final access decision
        $canAccess = $isFromAllowed && ($hasRoleAccess || $isProjectHandler);
        if (!$canAccess) {
            session()->forget('emp_data');
            session()->flush();
            $redirectUrl = urlencode(route('dashboard'));
            $authifyUrl = "http://192.168.1.27:8080/authify/public/logout?redirect={$redirectUrl}";
            return Inertia::render('Unauthorized', [
                'logoutUrl' => $authifyUrl,
                'message' => 'Access Restricted: You do not have permission to access this app.',
            ])->toResponse($request)->setStatusCode(403);
        }

            // ✅ Assign system role if Programmer
            $systemRole = null;
            if (
                stripos($currentUser->emp_jobtitle, 'programmer') !== false ||
                stripos($currentUser->emp_jobtitle, 'MIS Senior Supervisor') !== false
            ) {
                $systemRole = 'Programmer';
            }
        // 🔹 5️⃣ Set session once
        session(['emp_data' => [
            'token'         => $currentUser->token,
            'emp_id'        => $currentUser->emp_id,
            'emp_name'      => $currentUser->emp_name,
            'emp_firstname' => $currentUser->emp_firstname,
            'emp_jobtitle'  => $currentUser->emp_jobtitle,
            'emp_dept'      => $currentUser->emp_dept,
            'emp_prodline'  => $currentUser->emp_prodline,
            'emp_station'   => $currentUser->emp_station,
            'emp_position'  => $currentUser->emp_position,
              'emp_system_role' => $systemRole,
            'generated_at'  => $currentUser->generated_at,
        ]]);

        // ✅ Force Laravel to save the session immediately
        session()->save();

        // 🔹 6️⃣ Set sso_token cookie for future requests (7 days)
        $cookie = cookie('sso_token', $currentUser->token, 60 * 24 * 7);

        // 🔹 7️⃣ Remove ?key from URL after first login to prevent loops
        if ($tokenFromQuery) {
            $url = $request->url(); // base URL without query
            $query = $request->query();
            unset($query['key']);
            if (!empty($query)) {
                $url .= '?' . http_build_query($query);
            }
            return redirect($url)->withCookie($cookie);
        }

        // 🔹 8️⃣ Create or update notification user
        $user = NotificationUser::firstOrCreate(
            ['emp_id' => $currentUser->emp_id],
            ['emp_name' => $currentUser->emp_name, 'emp_dept' => $currentUser->emp_dept]
        );
        $request->setUserResolver(fn() => $user);

        // 🔹 9️⃣ Continue request and attach cookie
        $response = $next($request);
        return $response->withCookie($cookie);
    }

    private function redirectToLogin(Request $request)
    {
        $redirectUrl = urlencode($request->fullUrl());
        return redirect("http://192.168.1.27:8080/authify/public/login?redirect={$redirectUrl}");
    }
}
