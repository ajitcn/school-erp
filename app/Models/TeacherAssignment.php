<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class TeacherAssignment extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['is_class_teacher' => 'boolean'];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function schoolClass()
    {
        return $this->belongsTo(SchoolClass::class);
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
