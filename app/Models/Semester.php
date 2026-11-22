<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Semester extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}
