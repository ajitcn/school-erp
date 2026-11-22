<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** A tenant-owned subject attached to exactly one class or course. */
class Subject extends Model
{
    use BelongsToTenant;

    protected $table = 'academic_subjects';

    protected $guarded = [];

    protected $casts = ['is_active' => 'boolean'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function results()
    {
        return $this->hasMany(StudentResult::class, 'subject_id');
    }
}
