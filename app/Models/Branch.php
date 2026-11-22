<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function academicYears()
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
