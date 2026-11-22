<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Add explicit review metadata while retaining the original request rows as history. */
    public function up(): void
    {
        foreach (['payment_corrections', 'refund_requests', 'receipt_cancellations'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->text('review_note')->nullable();
                $table->timestamp('decided_at')->nullable();
            });
        }
    }

    /** Remove review metadata when rolling the adjustment history extension back. */
    public function down(): void
    {
        foreach (['receipt_cancellations', 'refund_requests', 'payment_corrections'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn(['review_note', 'decided_at']);
            });
        }
    }
};
