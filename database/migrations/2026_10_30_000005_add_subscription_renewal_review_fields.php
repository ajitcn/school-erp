<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('billing_interval', 12)->nullable()->after('renewal_date');
            $table->decimal('amount_due', 12, 2)->nullable()->after('billing_interval');
            $table->foreignId('requested_by')->nullable()->after('amount_due')->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->after('requested_by')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['billing_interval', 'amount_due', 'approved_at']);
        });
    }
};
