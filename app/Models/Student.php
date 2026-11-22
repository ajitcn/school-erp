<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $hidden = ['password', 'aadhaar_number'];

    protected $casts = [
        'date_of_birth' => 'date',
        'password' => 'hashed',
        'aadhaar_number' => 'encrypted',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function guardians()
    {
        return $this->belongsToMany(Guardian::class)->withPivot('is_primary')->withTimestamps();
    }

    public function enrollments()
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function userAccounts()
    {
        return $this->belongsToMany(User::class, 'student_user');
    }

    public function feeAssignments()
    {
        return $this->hasMany(StudentFeeAssignment::class);
    }
}
