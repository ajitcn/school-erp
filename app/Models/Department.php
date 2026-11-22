<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function courses()
    {
        return $this->hasMany(Course::class);
    }
}
