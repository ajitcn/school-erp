<?php

namespace App\Http\Controllers;

use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Issues and revokes tenant-scoped read-only API tokens.
 */
class ApiTokenController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = app(TenantContext::class)->id();
        $tokens = DB::table('api_tokens')->where('tenant_id', $tenantId)->where('user_id', $request->user()->id)->latest()->get();

        return view('admin.api-tokens', compact('tokens'));
    }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'expires_in' => 'required|integer|in:30,90,180,365']);
        $plain = Str::random(64);
        DB::table('api_tokens')->insert(['tenant_id' => app(TenantContext::class)
            ->id(), 'user_id' => $request
            ->user()
            ->id, 'name' => $data['name'], 'token_hash' => hash('sha256', $plain), 'abilities' => json_encode(['read'], JSON_THROW_ON_ERROR), 'expires_at' => now()
            ->addDays((int) $data['expires_in']), 'created_at' => now(), 'updated_at' => now()]);

        return redirect()->route('admin.api-tokens')->with('new_api_token', $plain)->with('success', 'Read-only API token created. Copy it now; it will not be shown again.');
    }

    public function revoke(Request $request, int $token)
    {
        $row = DB::table('api_tokens')->where('id', $token)->where('tenant_id', app(TenantContext::class)->id())->where('user_id', $request->user()->id)->first();
        abort_unless($row, 404);
        DB::table('api_tokens')->where('id', $row->id)->update(['revoked_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'API token revoked.');
    }
}
