<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class StudentFeeAssignment extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2', 'discount' => 'decimal:2', 'late_fee' => 'decimal:2'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function component()
    {
        return $this->belongsTo(FeeComponent::class, 'fee_component_id');
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function totalDue(): float
    {
        return max(0, (float) $this->amount - (float) $this->discount + (float) $this->late_fee);
    }

    public function balanceDue(): float
    {
        $paid = $this->relationLoaded('allocations')
            ? $this->allocations->sum(fn ($allocation) => (float) $allocation->amount)
            : $this->allocations()->sum('amount');

        return max(0, $this->totalDue() - (float) $paid);
    }
}
