<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\TenantWhatsAppCreditLedger;
use App\Models\TenantWhatsAppCreditPurchase;
use App\Models\TenantWhatsAppCreditWallet;
use Illuminate\Support\Facades\DB;

/** Maintains tenant credit balances and an auditable WhatsApp usage ledger. */
class WhatsAppCreditService
{
    /** Ensure the tenant wallet exists and return its current balance. */
    public function balance(int $tenantId): int
    {
        return (int) $this->wallet($tenantId)->credits_balance;
    }

    /** Reserve one credit before a WhatsApp message is queued. */
    public function reserveForNotification(NotificationLog $log): bool
    {
        $tenantId = (int) $log->tenant_id;

        if ($tenantId < 1) {
            return false;
        }

        return DB::transaction(function () use ($log, $tenantId): bool {
            $wallet = $this->lockedWallet($tenantId);
            if ($wallet->credits_balance < 1) {
                return false;
            }

            DB::table('tenant_whatsapp_credit_wallets')
                ->where('id', $wallet->id)
                ->decrement('credits_balance');
            TenantWhatsAppCreditLedger::withoutGlobalScopes()->create([
                'tenant_id' => $tenantId,
                'wallet_id' => $wallet->id,
                'notification_log_id' => $log->id,
                'type' => 'usage',
                'credits' => -1,
                'description' => 'WhatsApp message queued',
                'metadata' => ['event' => $log->event, 'channel' => $log->channel],
            ]);

            return true;
        });
    }

    /** Add the purchased package credits once payment has been verified. */
    public function creditPurchase(TenantWhatsAppCreditPurchase $purchase): void
    {
        DB::transaction(function () use ($purchase): void {
            $lockedPurchase = TenantWhatsAppCreditPurchase::withoutGlobalScopes()
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->firstOrFail();
            $alreadyCredited = TenantWhatsAppCreditLedger::withoutGlobalScopes()
                ->where('tenant_id', $lockedPurchase->tenant_id)
                ->where('type', 'purchase')
                ->where('purchase_id', $lockedPurchase->id)
                ->exists();

            if ($alreadyCredited) {
                return;
            }

            $wallet = $this->lockedWallet((int) $lockedPurchase->tenant_id);
            DB::table('tenant_whatsapp_credit_wallets')
                ->where('id', $wallet->id)
                ->increment('credits_balance', (int) $lockedPurchase->credits);
            TenantWhatsAppCreditLedger::withoutGlobalScopes()->create([
                'tenant_id' => $lockedPurchase->tenant_id,
                'wallet_id' => $wallet->id,
                'purchase_id' => $lockedPurchase->id,
                'created_by' => $lockedPurchase->purchased_by,
                'type' => 'purchase',
                'credits' => $lockedPurchase->credits,
                'description' => 'Purchased '.$lockedPurchase->pack_name,
                'metadata' => [
                    'amount' => $lockedPurchase->amount,
                    'currency' => $lockedPurchase->currency,
                    'payment_id' => $lockedPurchase->gateway_payment_id,
                ],
            ]);
        });
    }

    /** Refund a reserved credit after all provider delivery retries fail. */
    public function refundFailedNotification(int $notificationLogId): void
    {
        $log = NotificationLog::withoutGlobalScopes()->find($notificationLogId);

        if (! $log || $log->channel !== 'whatsapp' || ! $log->tenant_id) {
            return;
        }

        DB::transaction(function () use ($log): void {
            $usage = TenantWhatsAppCreditLedger::withoutGlobalScopes()
                ->where('tenant_id', $log->tenant_id)
                ->where('type', 'usage')
                ->where('notification_log_id', $log->id)
                ->first();
            $alreadyRefunded = TenantWhatsAppCreditLedger::withoutGlobalScopes()
                ->where('tenant_id', $log->tenant_id)
                ->where('type', 'refund')
                ->where('notification_log_id', $log->id)
                ->exists();

            if (! $usage || $alreadyRefunded) {
                return;
            }

            $wallet = $this->lockedWallet((int) $log->tenant_id);
            DB::table('tenant_whatsapp_credit_wallets')
                ->where('id', $wallet->id)
                ->increment('credits_balance');
            TenantWhatsAppCreditLedger::withoutGlobalScopes()->create([
                'tenant_id' => $log->tenant_id,
                'wallet_id' => $wallet->id,
                'notification_log_id' => $log->id,
                'type' => 'refund',
                'credits' => 1,
                'description' => 'Credit returned after delivery failure',
                'metadata' => ['event' => $log->event],
            ]);
        });
    }

    /** Lock and return a tenant wallet, creating it safely when first needed. */
    private function lockedWallet(int $tenantId): TenantWhatsAppCreditWallet
    {
        DB::table('tenant_whatsapp_credit_wallets')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'credits_balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return TenantWhatsAppCreditWallet::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /** Create a wallet on first view and return it within the requested tenant. */
    private function wallet(int $tenantId): TenantWhatsAppCreditWallet
    {
        return DB::transaction(fn (): TenantWhatsAppCreditWallet => $this->lockedWallet($tenantId));
    }
}
