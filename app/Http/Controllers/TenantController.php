<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantInvitation;
use App\Models\User;
use App\Services\PlatformConfigurationService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Handles organization registration, membership invitations, and tenant switching.
 */
class TenantController extends Controller
{
    public function registration()
    {
        return view('auth.register');
    }

    public function invitations()
    {
        abort_unless(auth()->user()->isTenantAdmin(), 403);
        $tenantId = app(TenantContext::class)->id();
        $branchIds = auth()->user()->accessibleBranchIds();

        return view('admin.invitations.index', [
            'invitations' => TenantInvitation::where('tenant_id', $tenantId)->with('inviter')->latest()->paginate(15),
            'branches' => Branch::query()->where('is_active', true)->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds))->orderBy('name')->get(),
            'students' => Student::query()->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds))->orderBy('first_name')->get(),
            'guardians' => Guardian::query()->when($branchIds !== null, fn ($q) => $q->whereIn('branch_id', $branchIds))->orderBy('first_name')->get(),
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'institution_name' => 'required|string|max:255',
            'institution_type' => 'required|in:school,college',
            'owner_name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email|unique:tenants,email',
            'phone' => 'required|string|max:30',
            'password' => 'required|string|min:10|confirmed',
            'terms' => 'accepted',
        ]);
        DB::transaction(function () use ($data): void {
            $plan = SubscriptionPlan::where('is_active', true)->orderBy('id')->firstOrCreate(['name' => 'Trial'], [
                'monthly_price' => 0, 'yearly_price' => 0, 'max_branches' => 10, 'max_users' => 50, 'max_students' => 500,
                'enabled_modules' => ['*'], 'is_active' => true,
            ]);
            $now = now();
            $tenant = Tenant::create([
                'institution_name' => $data['institution_name'], 'institution_type' => $data['institution_type'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'status' => 'trial',
                'registration_status' => 'pending',
                'subscription_plan_id' => $plan->id,
                'subscription_limits' => ['max_branches' => $plan->max_branches, 'max_users' => $plan->max_users, 'max_students' => $plan->max_students],
            ]);
            $owner = User::create(['name' => $data['owner_name'], 'email' => $data['email'], 'password' => $data['password'], 'is_active' => true]);
            $tenant->update(['created_by' => $owner->id]);
            $tenant->users()->attach($owner->id, ['role' => 'owner', 'is_active' => true, 'joined_at' => $now]);
            foreach (['date_format' => 'dd/mm/yyyy', 'timezone' => config('app.timezone')] as $key => $value) {
                $tenant->settings()->create(['key' => $key, 'value' => $value]);
            }
            Branch::create(['tenant_id' => $tenant->id, 'name' => 'Main Campus', 'code' => 'MAIN-'.$tenant->id, 'phone' => $data['phone'], 'is_active' => true]);

        });

        return redirect()->route('login')->with(
            'status',
            'Your registration is under review. You can sign in after a super administrator approves your institution.'
        );
    }

    public function switch(Request $request)
    {
        $data = $request->validate(['tenant_id' => 'required|integer']);
        abort_unless(
            $request->user()->tenants()
                ->whereKey($data['tenant_id'])
                ->where('tenants.registration_status', 'approved')
                ->wherePivot('is_active', true)
                ->exists(),
            403
        );
        $request->session()->put('tenant_id', $data['tenant_id']);

        return back()->with('success', 'Active organization changed.');
    }

    public function invite(Request $request, PlatformConfigurationService $platformConfiguration)
    {
        abort_unless($request->user()->isTenantAdmin(), 403);
        $data = $request->validate([
            'email' => 'required|email|max:255', 'role' => 'required|in:administrator,staff,teacher,fee_staff',
            'student_ids' => 'required_if:role,student|array|min:1|max:1', 'student_ids.*' => 'integer',
            'guardian_ids' => 'required_if:role,parent|array|min:1', 'guardian_ids.*' => 'integer',
            'branch_ids' => 'array', 'branch_ids.*' => 'integer',
        ]);
        $tenantId = app(TenantContext::class)->id();
        $studentIds = collect($data['student_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $guardianIds = collect($data['guardian_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        $branchIds = collect($data['branch_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->values();
        if (in_array($data['role'], ['staff', 'teacher', 'fee_staff'], true) && $branchIds->isEmpty()) {
            throw ValidationException::withMessages(['branch_ids' => 'Select at least one branch for this role.']);
        }
        foreach ($branchIds as $branchId) {
            abort_unless($request->user()->canAccessBranch($branchId) && Branch::query()->whereKey($branchId)->exists(), 403);
        }
        $students = Student::whereIn('id', $studentIds)->get();
        $guardians = Guardian::whereIn('id', $guardianIds)->get();
        abort_unless($students->count() === $studentIds->count() && $guardians->count() === $guardianIds->count(), 422);
        foreach ($students as $student) {
            abort_unless($request->user()->canAccessBranch($student->branch_id), 403);
        }
        foreach ($guardians as $guardian) {
            abort_unless($request->user()->canAccessBranch($guardian->branch_id), 403);
        }
        $branchIds = $branchIds->merge($students->pluck('branch_id'))->merge($guardians->pluck('branch_id'))->map(fn ($id) => (int) $id)->unique()->values();
        $invitation = TenantInvitation::create([
            'tenant_id' => $tenantId, 'invited_by' => $request->user()->id, 'email' => $data['email'], 'role' => $data['role'],
            'portal_links' => ['student_ids' => $studentIds, 'guardian_ids' => $guardianIds], 'branch_ids' => $branchIds,
            'token' => Str::random(64), 'expires_at' => now()->addDays(7),
        ]);
        $url = route('tenant.invitation.show', $invitation->token);
        try {
            $platformConfiguration->applyMailConfiguration();
            Mail::raw("You have been invited to join {$invitation->tenant->institution_name}. Accept your invitation: {$url}\nThis link expires in 7 days.", fn ($mail) => $mail->to($invitation
                ->email)
                ->subject('School ERP invitation'));

            return back()->with('success', 'Invitation sent to '.$invitation->email.'.');
        } catch (\Throwable $exception) {
            Log::warning('Tenant invitation email could not be delivered', ['invitation_id' => $invitation->id, 'error' => $exception->getMessage()]);

            return back()->with('success', 'Invitation created. Share this one-time link with '.$invitation->email.': '.$url);
        }
    }

    public function showInvitation(string $token)
    {
        abort_if(auth()->user()?->is_platform_super_admin, 403, 'Platform administrators cannot join a tenant through an invitation.');

        $invitation = TenantInvitation::withoutGlobalScope('tenant')->with('tenant')
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return view('auth.invitation', compact('invitation'));
    }

    public function accept(Request $request, string $token)
    {
        abort_if($request->user()?->is_platform_super_admin, 403, 'Platform administrators cannot join a tenant through an invitation.');

        $invitation = TenantInvitation::withoutGlobalScope('tenant')->where('token', $token)->whereNull('accepted_at')->where('expires_at', '>', now())->firstOrFail();
        abort_if(in_array($invitation->role, ['student', 'parent'], true), 410, 'Student and parent portal access is now managed from the student record. Ask the institution to generate a password setup link.');
        $user = $request->user();
        if ($user) {
            abort_unless(strcasecmp($user->email, $invitation->email) === 0, 403, 'Sign in with the invited email address.');
        } else {
            $existing = User::where('email', $invitation->email)->first();
            if ($existing) {
                return redirect()->route('login')->with('status', 'Sign in with the invited email address, then open the invitation link again.');
            }
            $data = $request->validate(['name' => 'required|string|max:150', 'password' => 'required|string|min:10|confirmed']);
            $user = User::create(['name' => $data['name'], 'email' => $invitation->email, 'password' => $data['password'], 'is_active' => true]);
        }
        // Resolve linked portal records inside the invite tenant, even if another tenant
        // was active when an existing account opened the invitation.
        app(TenantContext::class)->set($invitation->tenant);
        $portalLinks = $invitation->portal_links ?? [];
        $branchIds = $invitation->branch_ids ?? [];
        $primaryBranch = $branchIds[0] ?? null;
        if ($invitation->role === 'student' && ! empty($portalLinks['student_ids'])) {
            $user->studentAccounts()->syncWithoutDetaching($portalLinks['student_ids']);
            $primaryBranch = Student::whereKey($portalLinks['student_ids'][0])->value('branch_id') ?: $primaryBranch;
        }
        if ($invitation->role === 'parent' && ! empty($portalLinks['guardian_ids'])) {
            $user->guardianAccounts()->syncWithoutDetaching($portalLinks['guardian_ids']);
            $primaryBranch = Guardian::whereKey($portalLinks['guardian_ids'][0])->value('branch_id') ?: $primaryBranch;
        }
        $invitation->tenant
            ->users()
            ->syncWithoutDetaching([$user
                ->id => ['role' => $invitation
                ->role, 'primary_branch_id' => $primaryBranch, 'is_active' => true, 'joined_at' => now()]]);
        foreach ($branchIds as $branchId) {
            DB::table('user_branch_access')->updateOrInsert(['tenant_id' => $invitation->tenant_id, 'user_id' => $user->id, 'branch_id' => $branchId], []);
        }
        $invitation->update(['accepted_at' => now()]);
        $request->session()->put('tenant_id', $invitation->tenant_id);
        auth()->login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Invitation accepted.');
    }
}
