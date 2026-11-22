<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates API requests with tenant-scoped, revocable read-only tokens.
 */
class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, string $ability = 'read'): Response
    {
        $plain = $request->bearerToken();
        abort_unless($plain, 401, 'A bearer token is required.');
        $token = DB::table('api_tokens')->where('token_hash', hash('sha256', $plain))
            ->whereNull('revoked_at')
            ->where(fn ($q) => $q
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->first();
        abort_unless($token, 401, 'The API token is invalid or expired.');
        $user = User::query()->find($token->user_id);
        $tenant = Tenant::query()->whereKey($token->tenant_id)->whereHas('users', fn ($q) => $q->where('users.id', $token->user_id)->where('tenant_user.is_active', true))->first();
        abort_unless($user && $user->is_active && $tenant, 401, 'The token owner or tenant is inactive.');
        abort_if($user->is_platform_super_admin, 403, 'Platform administrators cannot use school API tokens.');
        abort_unless($tenant->status === 'active' || $tenant->status === 'trial', 403, 'The tenant account is not active.');
        abort_unless($tenant->hasValidSubscription(), 402, 'The tenant subscription has expired.');
        $abilities = json_decode($token->abilities ?: '[]', true) ?: [];
        abort_unless(in_array('*', $abilities, true) || in_array($ability, $abilities, true), 403, 'This token does not have the required ability.');
        app(TenantContext::class)->set($tenant);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('api_token', $token);
        DB::table('api_tokens')->where('id', $token->id)->update(['last_used_at' => now()]);

        return $next($request);
    }
}
