<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\TenantContext;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'branch_id', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
/** Authenticated account with tenant, branch, role, and portal access helpers. */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    public function tenants()
    {
        return $this->belongsToMany(Tenant::class)->withPivot(['role', 'is_active', 'joined_at', 'primary_branch_id'])->withTimestamps();
    }

    public function tenantMembership(?int $tenantId = null)
    {
        return $this->tenants()->where('tenants.id', $tenantId ?: app(TenantContext::class)->id())->first()?->pivot;
    }

    public function currentBranchId(): ?int
    {
        $membership = $this->tenantMembership();
        if ($this->is_platform_super_admin || in_array($membership?->role, ['owner', 'administrator'], true)) {
            return null;
        }
        $branchId = $membership?->primary_branch_id;
        // A legacy user-level branch is only meaningful before tenant membership
        // exists. Never reuse it when switching between organizations.
        if (! $membership) {
            $branchId = $this->branch_id;
        }

        return $branchId ? (int) $branchId : null;
    }

    public function isTenantAdmin(?int $tenantId = null): bool
    {
        return in_array($this->tenantMembership($tenantId)?->role, ['owner', 'administrator'], true);
    }

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_role');
    }

    public function branches()
    {
        $relation = $this->belongsToMany(Branch::class, 'user_branch_access')->withPivot('tenant_id');
        $tenantId = app(TenantContext::class)->id();

        return $tenantId ? $relation->wherePivot('tenant_id', $tenantId) : $relation->wherePivotNull('tenant_id');
    }

    public function hasRole(string|array $roles): bool
    {
        $roles = (array) $roles;
        if ($this->is_platform_super_admin && in_array('super_admin', $roles, true)) {
            return true;
        }
        $membershipRole = $this->tenantMembership()?->role;
        $effectiveRoles = $membershipRole === 'owner' ? ['owner', 'administrator'] : [$membershipRole];

        return $membershipRole !== null && count(array_intersect($effectiveRoles, $roles)) > 0;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->is_platform_super_admin) {
            return true;
        }
        $membership = $this->tenantMembership();
        if (in_array($membership?->role, ['owner', 'administrator'], true)) {
            return true;
        }
        $membershipRole = $membership?->role;

        if ($membershipRole === null) {
            return false;
        }

        $tenantRole = Role::query()
            ->where('tenant_id', app(TenantContext::class)->id())
            ->where('name', $membershipRole)
            ->first();
        $roleQuery = Role::query()->where('name', $membershipRole);

        if ($tenantRole) {
            $roleQuery->whereKey($tenantRole->id);
        } else {
            $roleQuery->whereNull('tenant_id');
        }

        return $roleQuery
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->exists();
    }

    public function canAccessBranch(?int $branchId): bool
    {
        if ($branchId !== null && ! $this->is_platform_super_admin) {
            $tenantId = app(TenantContext::class)->id();
            if (! $tenantId || ! Branch::query()->where('tenant_id', $tenantId)->whereKey($branchId)->exists()) {
                return false;
            }
        }
        $membershipRole = $this->tenantMembership()?->role;
        if ($branchId === null || $this->is_platform_super_admin || in_array($membershipRole, ['owner', 'administrator'], true)) {
            return true;
        }
        $primaryBranchId = $this->currentBranchId();
        if ($primaryBranchId) {
            return $primaryBranchId === $branchId || $this->branches()->whereKey($branchId)->exists();
        }

        return $this->branches()->whereKey($branchId)->exists();
    }

    public function accessibleBranchIds(): ?Collection
    {
        $membershipRole = $this->tenantMembership()?->role;
        if ($this->is_platform_super_admin || in_array($membershipRole, ['owner', 'administrator'], true)) {
            return null;
        }
        $primaryBranchId = $this->currentBranchId();
        if ($primaryBranchId) {
            return $this->branches()->pluck('branches.id')->push($primaryBranchId)->unique()->values();
        }

        return $this->branches()->pluck('branches.id');
    }

    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_user');
    }

    public function guardians()
    {
        return $this->belongsToMany(Guardian::class, 'guardian_user');
    }

    public function studentAccounts()
    {
        return $this->students();
    }

    public function guardianAccounts()
    {
        return $this->guardians();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_super_admin' => 'boolean',
        ];
    }
}
