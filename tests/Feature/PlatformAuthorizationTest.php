<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class PlatformAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->create(['is_active' => false, 'password' => Hash::make('password')]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_permission_middleware_denies_missing_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->actingAs($user)->get('/students')->assertForbidden();
    }

    public function test_branch_access_is_scoped(): void
    {
        $a = Branch::create(['name' => 'A', 'code' => 'A', 'is_active' => true]);
        $b = Branch::create(['name' => 'B', 'code' => 'B', 'is_active' => true]);
        $user = User::factory()->create(['branch_id' => $a->id]);
        $this->assertTrue($user->canAccessBranch($a->id));
        $this->assertFalse($user->canAccessBranch($b->id));
    }
}
