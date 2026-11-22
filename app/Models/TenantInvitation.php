<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantInvitation extends Model
{
    protected $guarded = [];

    protected $casts = ['portal_links' => 'array', 'branch_ids' => 'array', 'expires_at' => 'datetime', 'accepted_at' => 'datetime'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
