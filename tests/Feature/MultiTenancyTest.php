<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_registration_creates_owner_trial_and_settings_without_signing_in(): void
    {
        $response = $this->post('/register', [
            'institution_name' => 'Tenant A', 'institution_type' => 'school', 'owner_name' => 'Owner A',
            'email' => 'owner-a@example.test', 'phone' => '9999999999', 'password' => 'password123',
            'password_confirmation' => 'password123', 'terms' => '1',
        ]);
        $tenant = Tenant::where('email', 'owner-a@example.test')->firstOrFail();
        $this->assertGuest();
        $this->assertDatabaseHas('tenant_user', ['tenant_id' => $tenant->id, 'role' => 'owner']);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'registration_status' => 'pending']);
        $this->assertDatabaseHas('subscriptions', ['tenant_id' => $tenant->id, 'status' => 'trial']);
        $this->assertSame(2, $tenant->settings()->count());
        $response->assertRedirect(route('login'));
    }

    public function test_tenant_context_isolates_branches(): void
    {
        $a = Tenant::create(['institution_name' => 'A', 'institution_type' => 'school', 'email' => 'a@test', 'status' => 'active']);
        $b = Tenant::create(['institution_name' => 'B', 'institution_type' => 'college', 'email' => 'b@test', 'status' => 'active']);
        app(TenantContext::class)->set($a);
        Branch::create(['name' => 'A branch', 'code' => 'A1']);
        app(TenantContext::class)->set($b);
        Branch::create(['name' => 'B branch', 'code' => 'B1']);
        app(TenantContext::class)->set($a);
        $this->assertSame(['A branch'], Branch::pluck('name')->all());
        $this->assertNull(Branch::find(2));
    }

    public function test_suspended_tenant_is_denied_protected_access(): void
    {
        $tenant = Tenant::create(['institution_name' => 'Suspended', 'institution_type' => 'school', 'email' => 's@test', 'status' => 'suspended']);
        $user = User::factory()->create(['is_active' => true]);
        $tenant->users()->attach($user, ['role' => 'owner', 'is_active' => true, 'joined_at' => now()]);
        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }
}
