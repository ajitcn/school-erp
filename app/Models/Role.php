<?php

namespace App\Models;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $guarded = [];

    protected static function booted(): void
    {
        static::addGlobalScope('tenant_roles', function (Builder $query): void {
            $tenantId = app(TenantContext::class)->id();
            $query->where(function (Builder $roles) use ($tenantId): void {
                $roles->whereNull('tenant_id');
                if ($tenantId) {
                    $roles->orWhere('tenant_id', $tenantId);
                }
            });
        });
    }

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_role');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
