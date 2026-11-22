<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FeeComponent extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['due_date' => 'date', 'amount' => 'decimal:2'];

    public function structure()
    {
        return $this->belongsTo(FeeStructure::class, 'fee_structure_id');
    }

    /** Academic year associated with this student-specific charge, when set. */
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function studentFeeAssignments()
    {
        return $this->hasMany(StudentFeeAssignment::class, 'fee_component_id');
    }
}
