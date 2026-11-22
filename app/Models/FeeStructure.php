<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FeeStructure extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['default_amount' => 'decimal:2'];

    public function components()
    {
        return $this->hasMany(FeeComponent::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }
}
