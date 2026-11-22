<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class TenantLimitService
{
    public function enforce(Tenant $tenant, string $resource): void
    {
        $limit = data_get($tenant->subscription_limits, 'max_'.$resource) ?? data_get($tenant->plan, 'max_'.$resource);
        if ($limit === null) {
            return;
        }
        $count = match ($resource) {
            'branches' => Branch::count(), 'students' => Student::count(), 'users' => $tenant->users()->count(), default => 0
        };
        if ($count >= $limit) {
            throw ValidationException::withMessages([$resource => "This subscription allows a maximum of {$limit} {$resource}."]);
        }
    }
}
