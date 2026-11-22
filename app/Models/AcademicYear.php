<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class AcademicYear extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'is_current' => 'boolean'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function enrollments()
    {
        return $this->hasMany(StudentEnrollment::class);
    }
}
