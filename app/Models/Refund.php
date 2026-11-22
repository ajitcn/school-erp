<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2'];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
