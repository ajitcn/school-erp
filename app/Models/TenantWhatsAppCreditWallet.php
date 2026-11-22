<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/** Holds the current WhatsApp credit balance for one tenant. */
class TenantWhatsAppCreditWallet extends Model
{
    use BelongsToTenant;

    protected $table = 'tenant_whatsapp_credit_wallets';

    protected $guarded = [];

    protected $casts = ['credits_balance' => 'integer'];

    /** Get this wallet's auditable credit changes. */
    public function ledgerEntries()
    {
        return $this->hasMany(TenantWhatsAppCreditLedger::class, 'wallet_id');
    }
}
