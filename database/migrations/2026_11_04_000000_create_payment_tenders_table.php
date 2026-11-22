<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Store each tender portion so mixed-method collections remain reconcilable. */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_reference', 100)->nullable()->after('payment_mode');
        });

        Schema::create('payment_tenders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('payment_mode', 32);
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->index(['payment_mode', 'created_at']);
        });
    }

    /** Remove tender details when rolling the migration back. */
    public function down(): void
    {
        Schema::dropIfExists('payment_tenders');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('payment_reference');
        });
    }
};
