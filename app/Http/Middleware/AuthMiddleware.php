<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\NotificationUser;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class AuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $cookieName = env('SSO_COOKIE_NAME', 'authify_suite_sso'); // ← fixed default
        $secure     = (bool) env('SESSION_SECURE_COOKIE', false);  // ← added, used consistently

        $tokenFromQuery   = $request->query('key');
        $tokenFromCookie  = $request->cookie($cookieName);
        $tokenFromSession = session('emp_data.token');

        $token = $tokenFromQuery ?? $tokenFromCookie ?? $tokenFromSession;

        if (!$token) {
            return $this->redirectToLogin($request);
        }

        // Session valid and token matches — skip decode entirely
        if (session()->has('emp_data') && session('emp_data.token') === $token) {
            if ($tokenFromQuery) {
                return redirect($request->fullUrlWithoutQuery(['key']))
                    ->withCookie($this->makeCookie($cookieName, $token, $secure));
            }
            return $next($request);
        }

        // Decode JWT
        try {
            $secret = env('JWT_SECRET');

            if (empty($secret)) {
                Log::error('JWT_SECRET missing in .env on ' . env('APP_NAME'));
                return $this->redirectToLogin($request);
            }

            $decoded = (array) JWT::decode(
                $token,
                new Key($secret, 'HS256')
            );
        } catch (\Firebase\JWT\ExpiredException $e) {
            Log::warning('JWT expired: ' . $e->getMessage());
            session()->forget('emp_data');
            return $this->redirectToLogin($request)
                ->withCookie($this->forgetCookie($cookieName, $secure));
        } catch (\Exception $e) {
            Log::warning('JWT decode failed: ' . $e->getMessage());
            session()->forget('emp_data');
            return $this->redirectToLogin($request)
                ->withCookie($this->forgetCookie($cookieName, $secure));
        }

        if (empty($decoded['emp_id'])) {
            session()->forget('emp_data');
            return $this->redirectToLogin($request)
                ->withCookie($this->forgetCookie($cookieName, $secure));
        }

        // Access control — MIS rules
        $empPosition = $decoded['emp_position'] ?? 0;
        $empJobTitle = $decoded['emp_jobtitle'] ?? '';
        $empFrom     = $decoded['emp_from'] ?? null;

        $isFromAllowed = $empFrom === null || $empFrom === 'Employee';

        $hasRoleAccess =
            $empPosition >= 2 ||
            stripos($empJobTitle, 'programmer') !== false ||
            stripos($empJobTitle, 'MIS Senior Supervisor') !== false;

        $isProjectHandler = DB::connection('projects')
            ->table('project_list')
            ->where('PROJ_HANDLER', $decoded['emp_id'])
            ->exists();

        $canAccess = $isFromAllowed && ($hasRoleAccess || $isProjectHandler);

        if (!$canAccess) {
            session()->forget('emp_data');
            $redirectUrl = urlencode(rtrim(env('APP_URL'), '/'));
            $authifyUrl  = env('AUTHIFY_URL') . '/logout?redirect=' . $redirectUrl;

            return Inertia::render('Unauthorized', [
                'logoutUrl' => $authifyUrl,
                'message'   => 'Access Restricted: You do not have permission to access this app.',
            ])->toResponse($request)->setStatusCode(403);
        }

        // Build system role
        $systemRole = null;
        if (
            stripos($empJobTitle, 'programmer') !== false ||
            stripos($empJobTitle, 'MIS Senior Supervisor') !== false
        ) {
            $systemRole = 'Programmer';
        }

        // Store in session
        session()->put('emp_data', [
            'token'           => $token,
            'emp_id'          => $decoded['emp_id'],
            'emp_name'        => $decoded['emp_name'],
            'emp_firstname'   => $decoded['emp_firstname'],
            'emp_jobtitle'    => $decoded['emp_jobtitle'],
            'emp_dept'        => $decoded['emp_dept'],
            'emp_prodline'    => $decoded['emp_prodline'],
            'emp_station'     => $decoded['emp_station'],
            'emp_position'    => $decoded['emp_position'],
            'emp_from'        => $decoded['emp_from'] ?? 'Employee',
            'emp_system_role' => $systemRole,
            'generated_at'    => date('Y-m-d H:i:s', $decoded['iat']),
        ]);

        session()->save();

        $cookie = $this->makeCookie($cookieName, $token, $secure);

        if ($tokenFromQuery) {
            return redirect($request->fullUrlWithoutQuery(['key']))
                ->withCookie($cookie);
        }

        // Create or update notification user
        $user = NotificationUser::firstOrCreate(
            ['emp_id' => $decoded['emp_id']],
            [
                'emp_name' => $decoded['emp_name'],
                'emp_dept' => $decoded['emp_dept'],
            ]
        );
        $request->setUserResolver(fn() => $user);

        return $next($request)->withCookie($cookie);
    }

    private function makeCookie(string $name, string $value, bool $secure): SymfonyCookie
    {
        return SymfonyCookie::create(
            $name,
            $value,
            now()->addDays(7),
            '/',
            null,
            $secure,
            true,   // httpOnly
            false,
            'lax'
        );
    }

    private function forgetCookie(string $name, bool $secure): SymfonyCookie
    {
        return SymfonyCookie::create(
            $name, '', 1, '/', null,
            $secure, // must match makeCookie exactly
            true, false, 'lax'
        );
    }

    private function redirectToLogin(Request $request)
    {
        $redirectUrl = urlencode($request->fullUrl());
        $loginUrl    = env('AUTHIFY_URL') . '/login?redirect=' . $redirectUrl;

        if ($request->header('X-Inertia')) {
            return response()->json(['message' => 'Unauthenticated'], 409)
                ->header('X-Inertia-Location', $loginUrl);
        }

        return redirect($loginUrl);
    }
}