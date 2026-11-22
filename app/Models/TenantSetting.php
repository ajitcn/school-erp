<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantSetting extends Model
{
    protected $guarded = [];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
