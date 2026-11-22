<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class FinancialYear extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'is_current' => 'boolean'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
