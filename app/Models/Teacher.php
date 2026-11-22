<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Teacher extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function assignments()
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}
