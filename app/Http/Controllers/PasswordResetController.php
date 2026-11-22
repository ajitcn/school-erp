<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Handles password reset requests and token validation.
 */
class PasswordResetController extends Controller
{
    public function request()
    {
        return view('auth.forgot-password');
    }

    public function email(Request $r)
    {
        $d = $r->validate(['email' => 'required|email']);
        Password::sendResetLink($d);

        return back()->with('success', 'If an account matches that email, password reset instructions will be sent.');
    }

    public function form(Request $r, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $r->email]);
    }

    public function reset(Request $r)
    {
        $d = $r->validate(['token' => 'required', 'email' => 'required|email', 'password' => 'required|min:10|confirmed']);
        $status = Password::reset($d, function ($user, $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        });

        return $status === Password::PASSWORD_RESET ? redirect('/login')->with('success', __($status)) : back()->withErrors(['email' => __($status)]);
    }
}
