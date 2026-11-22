<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('gateway_order_id')->nullable()->unique();
            $table->string('gateway_payment_id')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique(['gateway_order_id']);
            $table->dropUnique(['gateway_payment_id']);
            $table->dropColumn(['gateway_order_id', 'gateway_payment_id']);
        });
    }
};
