<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Admission extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['admission_date' => 'date', 'documents' => 'array'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }
}
