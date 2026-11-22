<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Create credit packages, tenant wallets, payment records, and usage ledger. */
    public function up(): void
    {
        Schema::create('whatsapp_credit_packs', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 120);
            $table->unsignedInteger('credits');
            $table->decimal('price', 12, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order'], 'wa_credit_pack_active_sort_idx');
        });

        Schema::create('tenant_whatsapp_credit_wallets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unique('tenant_id', 'tenant_wa_wallet_tenant_uq');
            $table->unsignedInteger('credits_balance')->default(0);
            $table->timestamps();
        });

        Schema::create('tenant_whatsapp_credit_purchases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pack_id')->nullable()->constrained('whatsapp_credit_packs')->nullOnDelete();
            $table->foreignId('purchased_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pack_name', 120);
            $table->unsignedInteger('credits');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 24)->default('checkout_created');
            $table->string('gateway_order_id', 100)->nullable()->unique('tenant_wa_purchase_order_uq');
            $table->string('gateway_payment_id', 100)->nullable()->unique('tenant_wa_purchase_payment_uq');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'created_at'], 'tenant_wa_purchase_state_idx');
        });

        Schema::create('tenant_whatsapp_credit_ledger', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained('tenant_whatsapp_credit_wallets')->cascadeOnDelete();
            $table->foreignId('purchase_id')->nullable()->constrained('tenant_whatsapp_credit_purchases')->nullOnDelete();
            $table->foreignId('notification_log_id')->nullable()->constrained('notification_logs')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 24);
            $table->integer('credits');
            $table->string('description', 180);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'type', 'purchase_id'], 'tenant_wa_ledger_purchase_uq');
            $table->unique(['tenant_id', 'type', 'notification_log_id'], 'tenant_wa_ledger_notice_uq');
            $table->index(['tenant_id', 'created_at'], 'tenant_wa_ledger_date_idx');
            $table->index(['tenant_id', 'type', 'created_at'], 'tenant_wa_ledger_type_date_idx');
        });
    }

    /** Remove WhatsApp credit accounting tables. */
    public function down(): void
    {
        Schema::dropIfExists('tenant_whatsapp_credit_ledger');
        Schema::dropIfExists('tenant_whatsapp_credit_purchases');
        Schema::dropIfExists('tenant_whatsapp_credit_wallets');
        Schema::dropIfExists('whatsapp_credit_packs');
    }
};
