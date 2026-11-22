<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AuditService
{
    public function record(string $action, ?Model $model = null, array $old = [], array $new = []): void
    {
        $request = app(Request::class);
        AuditLog::create(['user_id' => $request->user()
            ?->id, 'branch_id' => $request
            ->user()
            ?->branch_id, 'action' => $action, 'auditable_type' => $model
            ?->getMorphClass(), 'auditable_id' => $model
            ?->getKey(), 'ip_address' => $request
            ->ip(), 'user_agent' => $request
            ->userAgent(), 'old_values' => $old, 'new_values' => $new]);
    }
}
