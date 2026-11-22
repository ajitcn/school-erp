<?php

namespace App\Http\Middleware;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Resolves a portal record from its session and applies the correct tenant scope. */
class EnsurePortalAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $accountType = $request->session()->get('portal_account_type');
        $accountId = $request->session()->get('portal_account_id');

        if (in_array($accountType, ['student', 'parent'], true) && $accountId) {
            $account = $accountType === 'student'
                ? Student::withoutGlobalScope('tenant')->where('status', 'active')->find($accountId)
                : Guardian::withoutGlobalScope('tenant')->find($accountId);

            if (! $account) {
                $request->session()->forget(['portal_account_type', 'portal_account_id', 'tenant_id']);

                return redirect()->route('portal.password.login');
            }

            $tenant = Tenant::query()->find($account->tenant_id);
            abort_unless($tenant && $tenant->isPortalAccessAllowed(), 403, 'This institution portal is currently unavailable.');
            abort_unless((int) $tenant->id === (int) $request->session()->get('tenant_id'), 403);

            app(TenantContext::class)->set($tenant);
            $request->setUserResolver(fn (): Student|Guardian => $account);

            return $next($request);
        }

        // Keep existing portal sessions working while new portal logins use student/guardian records.
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $membership = $user->tenantMembership();
        abort_unless(in_array($membership?->role, ['student', 'parent'], true), 403);

        $tenant = $user->tenants()
            ->where('tenants.registration_status', 'approved')
            ->wherePivot('is_active', true)
            ->first();
        abort_unless($tenant && $tenant->isPortalAccessAllowed(), 403);
        app(TenantContext::class)->set($tenant);

        return $next($request);
    }
}
