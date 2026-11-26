<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Guardian;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\User;
use App\Services\TenantContext;
use App\Services\TenantLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UserAdminController extends Controller
{
    private function tenant()
    {
        return app(TenantContext::class)->get() ?? abort(403);
    }

    private function tenantUsers()
    {
        return $this->tenant()->users();
    }

    private function availableRoles()
    {
        $tenantId = $this->tenant()->id;
        $tenantRoleNames = DB::table('roles')->where('tenant_id', $tenantId)->select('name');

        return Role::query()
            ->whereNotIn('name', ['super_admin', 'student', 'parent'])
            ->when(! auth()->user()->isTenantAdmin(), fn ($query) => $query->whereNotIn('name', ['owner', 'administrator']))
            ->where(function ($query) use ($tenantId, $tenantRoleNames): void {
                $query->where('tenant_id', $tenantId)
                    ->orWhere(function ($builtInRoles) use ($tenantRoleNames): void {
                        $builtInRoles->whereNull('tenant_id')->whereNotIn('name', $tenantRoleNames);
                    });
            })
            ->orderBy('label')
            ->get();
    }

    private function availableBranches()
    {
        $ids = auth()->user()->accessibleBranchIds();

        return Branch::query()->where('is_active', true)->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))->orderBy('name')->get();
    }

    private function portalOptions(): array
    {
        $branchIds = auth()->user()->accessibleBranchIds();

        return [
            'students' => Student::query()->with('branch')->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))->orderBy('first_name')->get(),
            'guardians' => Guardian::query()->with('branch')->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))->orderBy('first_name')->get(),
        ];
    }

    private function selectedRole(?User $user): ?Role
    {
        $roleName = $user?->tenantMembership($this->tenant()->id)?->role;

        return $roleName
            ? Role::query()->where('name', $roleName)->orderByRaw('tenant_id is null')->first()
            : null;
    }

    private function validateRoleAndBranches(array $data): array
    {
        $role = Role::query()->findOrFail($data['role_id']);
        abort_if($role->name === 'super_admin', 403, 'Platform administrator access is assigned outside tenant user management.');
        abort_if(in_array($role->name, ['owner', 'administrator'], true) && ! auth()->user()->isTenantAdmin(), 403, 'Only a tenant administrator can assign an administrator role.');
        abort_if(in_array($role->name, ['student', 'parent'], true), 422, 'Student and parent portal access is managed on their records, not in staff users.');

        $branchIds = array_values(array_unique(array_map('intval', array_filter(array_merge(
            [$data['primary_branch_id'] ?? null],
            $data['branch_access'] ?? [],
        )))));
        $availableBranches = auth()->user()->accessibleBranchIds();

        if ($role->name === 'student') {
            if (empty($data['student_id'])) {
                throw ValidationException::withMessages(['student_id' => 'Select the student record for this portal account.']);
            }
            $student = Student::query()->findOrFail($data['student_id'] ?? 0);
            abort_unless(auth()->user()->canAccessBranch($student->branch_id), 403);
            $branchIds[] = (int) $student->branch_id;
        }

        if ($role->name === 'parent') {
            $guardianIds = $data['guardian_ids'] ?? [];
            if (! $guardianIds) {
                throw ValidationException::withMessages(['guardian_ids' => 'Select at least one guardian record for a parent account.']);
            }
            $guardians = Guardian::query()->whereIn('id', $guardianIds)->get();
            if ($guardians->count() !== count(array_unique($guardianIds))) {
                throw ValidationException::withMessages(['guardian_ids' => 'One or more selected guardian records are unavailable.']);
            }
            foreach ($guardians as $guardian) {
                abort_unless(auth()->user()->canAccessBranch($guardian->branch_id), 403);
            }
            $branchIds = array_merge($branchIds, $guardians->pluck('branch_id')->map(fn ($id) => (int) $id)->all());
        }
        $branchIds = array_values(array_unique($branchIds));

        if (! $branchIds && $availableBranches !== null) {
            throw ValidationException::withMessages(['branch_access' => 'Assign at least one branch to this user.']);
        }

        foreach ($branchIds as $branchId) {
            abort_unless(auth()->user()->canAccessBranch($branchId) && Branch::query()->whereKey($branchId)->exists(), 403);
        }

        return [$role, $branchIds];
    }

    private function syncPortalLinks(User $user, Role $role, array $data): void
    {
        $tenantStudentIds = Student::query()->pluck('id');
        $tenantGuardianIds = Guardian::query()->pluck('id');
        if ($tenantStudentIds->isNotEmpty()) {
            DB::table('student_user')->where('user_id', $user->id)->whereIn('student_id', $tenantStudentIds)->delete();
        }
        if ($tenantGuardianIds->isNotEmpty()) {
            DB::table('guardian_user')->where('user_id', $user->id)->whereIn('guardian_id', $tenantGuardianIds)->delete();
        }

        if ($role->name === 'student' && ! empty($data['student_id'])) {
            DB::table('student_user')->insert(['student_id' => $data['student_id'], 'user_id' => $user->id]);
        }

        if ($role->name === 'parent') {
            foreach (array_unique($data['guardian_ids'] ?? []) as $guardianId) {
                DB::table('guardian_user')->insert(['guardian_id' => $guardianId, 'user_id' => $user->id]);
            }
        }
    }

    private function syncTenantBranches(User $user, array $branchIds): void
    {
        $tenantId = $this->tenant()->id;
        DB::table('user_branch_access')->where('user_id', $user->id)->where('tenant_id', $tenantId)->delete();

        foreach ($branchIds as $branchId) {
            DB::table('user_branch_access')->insert([
                'user_id' => $user->id,
                'branch_id' => $branchId,
                'tenant_id' => $tenantId,
            ]);
        }
    }

    public function index()
    {
        $users = $this->tenantUsers()->with('branches')->latest()->paginate(20);
        $roleNames = $users->getCollection()->map(fn (User $user) => $user->tenantMembership($this->tenant()->id)?->role)->filter()->unique()->values();
        $labels = Role::query()->whereIn('name', $roleNames)->pluck('label', 'name');
        $users->getCollection()
            ->each(fn (User $user) => $user
                ->setAttribute('tenant_role_label', $labels[$user
                    ->tenantMembership($this
                        ->tenant()
                        ->id)
                    ?->role] ?? str($user
                    ->tenantMembership($this
                        ->tenant()
                        ->id)
                    ?->role ?? 'staff')
                    ->replace('_', ' ')
                    ->title()));

        return view('admin.users.index', ['users' => $users]);
    }

    public function create()
    {
        return view('admin.users.form', ['user' => null, 'roles' => $this->availableRoles(), 'branches' => $this
            ->availableBranches(), 'selectedRole' => null] + $this
            ->portalOptions());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:10|confirmed',
            'primary_branch_id' => 'nullable|integer',
            'role_id' => 'required|integer',
            'student_id' => 'nullable|integer',
            'guardian_ids' => 'array',
            'guardian_ids.*' => 'integer',
            'branch_access' => 'array',
            'branch_access.*' => 'integer',
        ]);
        [$role, $branchIds] = $this->validateRoleAndBranches($data);
        $tenant = $this->tenant();
        app(TenantLimitService::class)->enforce($tenant, 'users');

        DB::transaction(function () use ($data, $role, $branchIds, $tenant): void {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'is_active' => true]);
            $linkedPrimary = $role->name === 'student' ? Student::query()->findOrFail($data['student_id'])->branch_id : null;
            if ($role->name === 'parent') {
                $linkedPrimary = Guardian::query()->whereIn('id', $data['guardian_ids'] ?? [])->value('branch_id');
            }
            $tenant->users()->attach($user->id, [
                'role' => $role->name,
                'primary_branch_id' => $data['primary_branch_id'] ?? $linkedPrimary,
                'is_active' => true,
                'joined_at' => now(),
            ]);
            $this->syncTenantBranches($user, $branchIds);
            $this->syncPortalLinks($user, $role, $data);
        });

        return redirect()->route('admin.users.index')->with('success', 'User created.');
    }

    public function edit(User $user)
    {
        abort_unless($this->tenantUsers()->whereKey($user->id)->exists(), 404);
        $membership = $user->tenantMembership($this->tenant()->id);
        $this->authorizePrivilegedMembership($membership?->role);

        return view('admin.users.form', [
            'user' => $user->load('branches'),
            'roles' => $this->availableRoles(),
            'branches' => $this->availableBranches(),
            'selectedRole' => $this->selectedRole($user),
            'primaryBranchId' => $membership?->primary_branch_id,
            'linkedStudentId' => $user->students()->value('students.id'),
            'linkedGuardianIds' => $user->guardians()->pluck('guardians.id')->all(),
        ] + $this->portalOptions());
    }

    public function update(Request $request, User $user)
    {
        abort_unless($this->tenantUsers()->whereKey($user->id)->exists(), 404);
        $this->authorizePrivilegedMembership($user->tenantMembership($this->tenant()->id)?->role);
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'password' => 'nullable|string|min:10|confirmed',
            'primary_branch_id' => 'nullable|integer',
            'role_id' => 'required|integer',
            'student_id' => 'nullable|integer',
            'guardian_ids' => 'array',
            'guardian_ids.*' => 'integer',
            'branch_access' => 'array',
            'branch_access.*' => 'integer',
        ]);
        [$role, $branchIds] = $this->validateRoleAndBranches($data);
        $membership = $user->tenantMembership($this->tenant()->id);

        DB::transaction(function () use ($user, $data, $role, $branchIds, $membership): void {
            $user->update(array_filter([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => isset($data['password']) ? Hash::make($data['password']) : null,
            ], fn ($value) => $value !== null));
            $linkedPrimary = $role->name === 'student' ? Student::query()->findOrFail($data['student_id'])->branch_id : null;
            if ($role->name === 'parent') {
                $linkedPrimary = Guardian::query()->whereIn('id', $data['guardian_ids'] ?? [])->value('branch_id');
            }
            $membership->update(['role' => $role->name, 'primary_branch_id' => $data['primary_branch_id'] ?? $linkedPrimary]);
            $this->syncTenantBranches($user, $branchIds);
            $this->syncPortalLinks($user, $role, $data);
        });

        return back()->with('success', 'User updated.');
    }

    /**
     * Toggle a user's active membership in the current tenant.
     *
     * A signed-in user cannot deactivate their own tenant membership because
     * doing so would remove their access to the current tenant.
     */
    public function toggle(User $user)
    {
        abort_unless($this->tenantUsers()->whereKey($user->id)->exists(), 404);
        $this->authorizePrivilegedMembership($user->tenantMembership($this->tenant()->id)?->role);

        if ((int) $user->id === (int) auth()->id()) {
            throw ValidationException::withMessages([
                'user' => 'You cannot deactivate your own account.',
            ]);
        }

        $membership = $user->tenantMembership($this->tenant()->id);
        $membership->update(['is_active' => ! $membership->is_active]);

        return back()->with('success', 'User status updated for this tenant.');
    }

    /** Protect privileged accounts from users with delegated user management. */
    private function authorizePrivilegedMembership(?string $role): void
    {
        abort_if(in_array($role, ['owner', 'administrator'], true) && ! auth()->user()->isTenantAdmin(), 403);
    }

    public function roles()
    {
        $tenantId = $this->tenant()->id;
        $tenantRoleNames = DB::table('roles')->where('tenant_id', $tenantId)->select('name');
        $permissions = Permission::query()->orderBy('name')->get();

        return view('admin.roles.index', [
            'roles' => Role::query()
                ->with('permissions')
                ->where(function ($query) use ($tenantId, $tenantRoleNames): void {
                    $query->where('tenant_id', $tenantId)
                        ->orWhere(function ($builtInRoles) use ($tenantRoleNames): void {
                            $builtInRoles->whereNull('tenant_id')->whereNotIn('name', $tenantRoleNames);
                        });
                })
                ->orderBy('label')
                ->get(),
            'permissions' => $permissions,
            'permissionGroups' => $permissions->groupBy(fn (Permission $permission): string => Str::headline(Str::before($permission->name, '.'))),
            'builtInRoleNames' => Role::query()->whereNull('tenant_id')->pluck('name'),
        ]);
    }

    public function roleStore(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80|regex:/^[a-z][a-z0-9_]*$/',
            'label' => 'required|string|max:120',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id',
        ]);

        if (Role::query()->where('name', $data['name'])->exists()) {
            throw ValidationException::withMessages(['name' => 'That role key is already in use in this tenant or is reserved by the system.']);
        }

        $role = DB::transaction(function () use ($data): Role {
            $role = Role::create(['tenant_id' => $this->tenant()->id, 'name' => $data['name'], 'label' => $data['label']]);
            $role->permissions()->sync($data['permissions'] ?? []);

            return $role;
        });

        return back()->with('success', 'Tenant role created.');
    }

    public function roleUpdate(Request $request, Role $role)
    {
        abort_unless((int) $role->tenant_id === (int) $this->tenant()->id, 404, 'Built-in roles are shared and cannot be changed here. Create a tenant role to customize access.');
        $data = $request->validate(['label' => 'required|string|max:120', 'permissions' => 'array', 'permissions.*' => 'exists:permissions,id']);

        DB::transaction(function () use ($role, $data): void {
            $role->update(['label' => $data['label']]);
            $role->permissions()->sync($data['permissions'] ?? []);
        });

        return back()->with('success', 'Tenant role permissions updated.');
    }

    /** Create a tenant-specific permission override for an editable built-in role. */
    public function roleCustomize(Role $role)
    {
        abort_unless($role->tenant_id === null, 404);
        abort_if(in_array($role->name, ['owner', 'administrator', 'super_admin'], true), 403, 'This system role always retains its defined access.');

        $tenantId = $this->tenant()->id;
        $overrideExists = DB::table('roles')
            ->where('tenant_id', $tenantId)
            ->where('name', $role->name)
            ->exists();

        if ($overrideExists) {
            return back()->with('info', 'This role is already customized for your tenant.');
        }

        DB::transaction(function () use ($role, $tenantId): void {
            $tenantRole = Role::create([
                'tenant_id' => $tenantId,
                'name' => $role->name,
                'label' => $role->label,
            ]);
            $tenantRole->permissions()->sync($role->permissions()->pluck('permissions.id'));
        });

        return back()->with('success', "{$role->label} is now customized for this tenant.");
    }
}
