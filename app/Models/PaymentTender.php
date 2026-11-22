<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One payment method and amount portion recorded against a payment receipt. */
class PaymentTender extends Model
{
    protected $guarded = [];

    protected $casts = ['amount' => 'decimal:2'];

    /** Return the payment this method portion belongs to. */
    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }
}
