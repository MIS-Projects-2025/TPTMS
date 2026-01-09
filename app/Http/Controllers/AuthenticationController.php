<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthenticationController extends Controller
{
    public function setSession(Request $request) {}

    public function logout(Request $request)
    {
        $token = $request->cookie('sso_token')
            ?? session('emp_data.token');

        // Clear local Laravel session
        session()->forget('emp_data');
        session()->flush();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Build redirect back after SSO logout
        $redirectUrl = urlencode(route('dashboard'));

        return redirect(
            "http://192.168.1.27:8080/authify/public/logout?token={$token}&redirect={$redirectUrl}"
        );
    }
}
