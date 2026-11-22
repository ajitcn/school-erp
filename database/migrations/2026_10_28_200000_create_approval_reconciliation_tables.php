<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('payment_corrections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->json('proposed_changes');
            $t->text('reason');
            $t->string('status')->default('pending');
            $t->timestamps();
        });
        Schema::create('refund_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('amount', 12, 2);
            $t->text('reason');
            $t->string('status')->default('pending');
            $t->timestamps();
        });
        Schema::create('receipt_cancellations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('receipt_id')->constrained()->cascadeOnDelete();
            $t->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->text('reason');
            $t->string('status')->default('pending');
            $t->timestamps();
        });
        Schema::create('cashier_closings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->date('closing_date');
            $t->decimal('expected_cash', 12, 2)->default(0);
            $t->decimal('actual_cash', 12, 2)->default(0);
            $t->decimal('variance', 12, 2)->default(0);
            $t->string('status')->default('submitted');
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'closing_date']);
        });
        Schema::create('payment_reconciliations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $t->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $t->date('from_date');
            $t->date('to_date');
            $t->string('payment_mode');
            $t->decimal('system_total', 12, 2);
            $t->decimal('bank_total', 12, 2)->nullable();
            $t->decimal('variance', 12, 2)->nullable();
            $t->string('status')->default('open');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payment_reconciliations', 'cashier_closings', 'receipt_cancellations', 'refund_requests', 'payment_corrections'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
