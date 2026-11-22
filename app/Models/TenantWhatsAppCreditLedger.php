<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Immutable audit entry for purchased, consumed, and refunded WhatsApp credits. */
class TenantWhatsAppCreditLedger extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_whatsapp_credit_ledger';

    protected $guarded = [];

    protected $casts = [
        'credits' => 'integer',
        'metadata' => 'array',
    ];

    /** Get the associated tenant wallet. */
    public function wallet()
    {
        return $this->belongsTo(TenantWhatsAppCreditWallet::class, 'wallet_id');
    }

    /** Get the related purchase when this entry funds the wallet. */
    public function purchase()
    {
        return $this->belongsTo(TenantWhatsAppCreditPurchase::class, 'purchase_id');
    }

    /** Get the attempted WhatsApp notification. */
    public function notificationLog()
    {
        return $this->belongsTo(NotificationLog::class);
    }

    /** Get the member who initiated a balance change. */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
