<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use BelongsToTenant;

    protected $table = 'attendance';

    protected $guarded = [];

    protected $casts = ['attendance_date' => 'date'];

    public function student()
    {
        return $this->belongsTo(Student::class);
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

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function marker()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
