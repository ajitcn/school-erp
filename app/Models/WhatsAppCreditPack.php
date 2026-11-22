<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A platform-priced credit bundle available to tenant administrators. */
class WhatsAppCreditPack extends Model
{
    protected $table = 'whatsapp_credit_packs';

    protected $guarded = [];

    protected $casts = [
        'credits' => 'integer',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
