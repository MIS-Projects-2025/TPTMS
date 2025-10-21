<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AuthenticationController extends Controller
{
    public function setSession(Request $request) {}

    public function logout(Request $request)
    {
        session()->forget('emp_data');
        session()->flush();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
