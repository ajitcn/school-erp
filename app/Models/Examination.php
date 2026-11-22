<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Represent a scheduled assessment belonging to one tenant and branch. */
class Examination extends Model
{
    use BelongsToTenant;

    protected $table = 'examinations';

    protected $guarded = [];

    protected $casts = [
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    /** Return the campus that owns this examination. */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** Return the academic year in which this examination is scheduled. */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** Return result rows entered from the Academic Results module. */
    public function results(): HasMany
    {
        return $this->hasMany(StudentResult::class);
    }
}
