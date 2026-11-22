<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use Tests\TestCase;

#[RequiresPhpExtension('pdo_sqlite')]
class CriticalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_login_and_reach_dashboard(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password'), 'is_active' => true]);
        $tenant = $this->createApprovedTrialTenant($user, 'owner');

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertSame($tenant->id, (int) session('tenant_id'));
    }

    public function test_student_portal_cannot_read_unlinked_student(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $branch = Branch::create(['name' => 'Test', 'code' => 'TEST', 'is_active' => true]);
        $student = Student::create(['branch_id' => $branch->id, 'student_id' => 'TEST-00001', 'admission_number' => 'TEST/00001', 'first_name' => 'Unlinked']);
        $this->actingAs($user)->get(route('portal.attendance', $student))->assertForbidden();
    }

    public function test_report_export_requires_permission(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $this->createApprovedTrialTenant($user, 'staff');

        $this->actingAs($user)->get('/reports/financial/export')->assertForbidden();
    }

    private function createApprovedTrialTenant(User $user, string $role): Tenant
    {
        $tenant = Tenant::create([
            'institution_name' => 'Approved test school',
            'institution_type' => 'school',
            'email' => 'tenant-'.$user->id.'@example.test',
            'phone' => '9999999999',
            'status' => 'trial',
            'registration_status' => 'approved',
            'trial_ends_at' => now()->addDays(14),
        ]);
        $tenant->users()->attach($user->id, [
            'role' => $role,
            'is_active' => true,
            'joined_at' => now(),
        ]);

        return $tenant;
    }
}
