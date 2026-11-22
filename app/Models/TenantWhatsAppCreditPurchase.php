<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Records an attempted or completed purchase of a WhatsApp credit package. */
class TenantWhatsAppCreditPurchase extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_whatsapp_credit_purchases';

    protected $guarded = [];

    protected $casts = [
        'credits' => 'integer',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /** Get the package definition when it remains available. */
    public function pack()
    {
        return $this->belongsTo(WhatsAppCreditPack::class, 'pack_id');
    }

    /** Get the tenant member who started checkout. */
    public function purchaser()
    {
        return $this->belongsTo(User::class, 'purchased_by');
    }
}
