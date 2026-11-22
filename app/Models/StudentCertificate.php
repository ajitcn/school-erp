<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** An issued student certificate and its issue-time identity snapshot. */
class StudentCertificate extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = [
        'issued_on' => 'date',
        'effective_date' => 'date',
        'student_snapshot' => 'array',
    ];

    /** Get the live student record when it is still available. */
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /** Get the staff account that issued the certificate. */
    public function issuer()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
