<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Handles user sign-in, session renewal, and sign-out.
 */
class AuthController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Invalid email or password.'])
                ->onlyInput('email');
        }

        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            return back()
                ->withErrors(['email' => 'This account is inactive. Contact your institution administrator.'])
                ->onlyInput('email');
        }

        $activeTenants = collect();
        $approvedTenant = null;

        if (! $user->is_platform_super_admin) {
            $activeTenants = $user->tenants()
                ->wherePivot('is_active', true)
                ->get();
            $approvedTenant = $activeTenants->first(
                fn ($tenant): bool => $tenant->registration_status === 'approved'
            );

            if (! $approvedTenant) {
                $hasPendingRegistration = $activeTenants->contains(
                    fn ($tenant): bool => $tenant->registration_status === 'pending'
                );
                $hasRejectedRegistration = $activeTenants->contains(
                    fn ($tenant): bool => $tenant->registration_status === 'rejected'
                );
                $message = match (true) {
                    $hasPendingRegistration => 'Your institution account is under review. A super administrator must approve it before you can sign in.',
                    $hasRejectedRegistration => 'Your institution registration was not approved. Contact the platform administrator for assistance.',
                    default => 'This account is not linked to an approved institution.',
                };

                Auth::logout();

                return back()
                    ->withErrors(['email' => $message])
                    ->onlyInput('email');
            }
        }

        $request->session()->regenerate();
        if ($approvedTenant) {
            $selectedTenant = $activeTenants->firstWhere(
                'id',
                (int) $request->session()->get('tenant_id')
            ) ?? $approvedTenant;

            if ($selectedTenant->registration_status !== 'approved') {
                $selectedTenant = $approvedTenant;
            }

            $request->session()->put('tenant_id', $selectedTenant->id);
        }

        $user->update(['last_login_at' => now()]);

        if ($user->is_platform_super_admin) {
            return redirect('/platform/tenants');
        }

        return redirect()->intended('/dashboard');
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
