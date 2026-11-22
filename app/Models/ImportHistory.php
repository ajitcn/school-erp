<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ImportHistory extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['errors' => 'array', 'imported_student_ids' => 'array', 'rolled_back_at' => 'datetime'];

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
