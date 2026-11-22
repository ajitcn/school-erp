<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Allows an authenticated user to change their own account password. */
class AccountPasswordController extends Controller
{
    /** Show the password change form. */
    public function edit(): View
    {
        return view('account.password');
    }

    /** Validate the current credential before saving a replacement password. */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:10', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => $data['password'],
            'remember_token' => Str::random(60),
        ])->save();

        return redirect()
            ->route('account.password.edit')
            ->with('success', 'Your password has been updated.');
    }
}
