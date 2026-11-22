<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['paid_at' => 'datetime', 'amount' => 'decimal:2'];

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    /** Return the academic year the payment was recorded against, when available. */
    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function allocations()
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /** Return method-level tender amounts for this collection. */
    public function paymentTenders()
    {
        return $this->hasMany(PaymentTender::class);
    }

    public function receipt()
    {
        return $this->hasOne(Receipt::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }
}
