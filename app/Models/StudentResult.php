<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentResult extends Model
{
    use BelongsToTenant;

    protected $table = 'results';

    protected $guarded = [];

    protected $casts = [
        'marks' => 'decimal:2',
        'marks_obtained' => 'decimal:2',
    ];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** Return the optional scheduled examination linked to this result. */
    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
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

    public function subjectMaster()
    {
        return $this->belongsTo(Subject::class, 'subject_id');
    }
}
