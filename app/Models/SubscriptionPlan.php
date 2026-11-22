<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $guarded = [];

    protected $casts = ['enabled_modules' => 'array', 'is_active' => 'boolean'];

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }
}
