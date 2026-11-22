<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/** Signs student and parent portal records in without creating users-table accounts. */
class PortalPasswordAuthController extends Controller
{
    /** Show the student and parent portal password sign-in form. */
    public function create(): View
    {
        return view('auth.portal-login');
    }

    /** Authenticate the selected student or parent record using its stored password hash. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_type' => ['required', 'in:student,parent'],
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($data['identifier']);
        $records = $data['account_type'] === 'student'
            ? Student::withoutGlobalScope('tenant')
                ->where('status', 'active')
                ->whereNotNull('password')
                ->where(fn ($query) => $query->where('student_id', $identifier)->orWhere('email', $identifier))
                ->get()
            : Guardian::withoutGlobalScope('tenant')
                ->whereNotNull('password')
                ->where(fn ($query) => $query->where('email', $identifier)->orWhere('phone', $identifier))
                ->get();

        $matches = $records->filter(fn (Student|Guardian $record): bool => Hash::check($data['password'], $record->password));

        if ($matches->count() !== 1) {
            return back()
                ->withErrors(['identifier' => 'The portal ID or contact and password do not match.'])
                ->onlyInput('account_type', 'identifier');
        }

        $account = $matches->first();
        $tenant = Tenant::query()->find($account->tenant_id);

        if (! $tenant || ! $tenant->isPortalAccessAllowed()) {
            return back()
                ->withErrors(['identifier' => 'This institution portal is currently unavailable. Contact the school or college.'])
                ->onlyInput('account_type', 'identifier');
        }

        $request->session()->regenerate();
        $request->session()->put([
            'portal_account_type' => $data['account_type'],
            'portal_account_id' => $account->id,
            'tenant_id' => $tenant->id,
        ]);

        return redirect()->intended(route('portal.dashboard'));
    }

    /** End a student or parent portal session without touching a staff session. */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(['portal_account_type', 'portal_account_id', 'tenant_id']);
        $request->session()->regenerateToken();

        return redirect()->route('portal.password.login')->with('status', 'You have been signed out.');
    }
}
