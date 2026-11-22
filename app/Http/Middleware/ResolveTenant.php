<?php

namespace App\Http\Middleware;

use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;

/** Resolves the active tenant from membership and enforces its subscription state. */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): mixed
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->is_platform_super_admin) {
            app(TenantContext::class)->clear();

            if ($request->routeIs('logout')) {
                return $next($request);
            }

            return redirect()->route('platform.tenants');
        }

        $tenantId = (int) $request->session()->get('tenant_id');
        $tenant = $user->tenants()
            ->where('tenants.id', $tenantId)
            ->where('tenants.registration_status', 'approved')
            ->wherePivot('is_active', true)
            ->first();
        $tenant ??= $user->tenants()
            ->where('tenants.registration_status', 'approved')
            ->wherePivot('is_active', true)
            ->first();
        abort_unless($tenant, 403, 'No active tenant is assigned to this account.');
        app(TenantContext::class)->set($tenant);
        $request->session()->put('tenant_id', $tenant->id);
        $tenant->subscriptions()
            ->whereIn('status', ['trial', 'active'])
            ->whereIn('payment_status', ['not_required', 'paid'])
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', today())
            ->update(['status' => 'expired', 'updated_at' => now()]);
        $billingAccessible = $request->routeIs('billing.*', 'tenant.switch', 'logout');
        if (($tenant->status === 'suspended' || $tenant->status === 'cancelled') && ! $billingAccessible) {
            return redirect()->route('billing.index')->with('subscription_blocked', ucfirst($tenant->status).' tenant. Contact your billing administrator.');
        }
        if (! $tenant->hasValidSubscription() && ! $billingAccessible) {
            return redirect()->route('billing.index')->with('subscription_expired', true);
        }

        return $next($request);
    }
}
