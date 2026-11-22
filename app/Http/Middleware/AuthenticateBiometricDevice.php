<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates a registered device before accepting attendance events.
 */
class AuthenticateBiometricDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) $request->header('X-Device-Token');
        abort_unless($secret, 401, 'Device token required.');
        $hash = hash('sha256', $secret);
        $device = DB::table('biometric_devices')->where('api_token_hash', $hash)->where('is_active', true)->first();
        abort_unless($device, 401, 'Invalid or inactive device.');
        $tenant = Tenant::query()->findOrFail($device->tenant_id);
        abort_unless(in_array($tenant->status, ['active', 'trial'], true) && $tenant->hasValidSubscription(), 402, 'The tenant subscription is expired or inactive.');
        app(TenantContext::class)->set($tenant);
        $request->attributes->set('biometric_device', $device);

        return $next($request);
    }
}
