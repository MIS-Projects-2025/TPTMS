<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class AuthenticationController extends Controller
{
    public function logout(Request $request)
    {
        $cookieName     = env('SSO_COOKIE_NAME', 'authify_suite_sso');
        $sessionName    = env('SESSION_COOKIE', 'authify_suite_session');
        $secure         = (bool) env('SESSION_SECURE_COOKIE', false);

        $redirectUrl    = urlencode(rtrim(env('APP_URL'), '/'));
        $authifyUrl     = env('AUTHIFY_URL') . '/logout?redirect=' . $redirectUrl;

        // Fully destroy the server-side session
        session()->forget('emp_data');
        session()->flush();
        session()->invalidate();
        session()->regenerateToken();

        return redirect($authifyUrl)
            ->withCookie($this->forgetCookie($cookieName, $secure))
            ->withCookie($this->forgetCookie($sessionName, $secure))
            ->withCookie($this->forgetCookie('XSRF-TOKEN', false));
    }

    private function forgetCookie(string $name, bool $secure = false): SymfonyCookie
    {
        return SymfonyCookie::create(
            $name, '', 1, '/', null,
            $secure,
            false,  // httpOnly false so browser can clear it
            false,
            'lax'
        );
    }
}