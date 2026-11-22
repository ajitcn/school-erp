<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Allow each cashier to submit a separate closing for each branch and date. */
    public function up(): void
    {
        Schema::table('cashier_closings', function (Blueprint $table) {
            $table->index('user_id', 'cashier_closings_user_id_lookup_index');
            $table->dropUnique('cashier_closings_user_id_closing_date_unique');
            $table->unique(
                ['branch_id', 'user_id', 'closing_date'],
                'cashier_close_branch_user_date_unique',
            );
        });
    }

    /** Restore the original one-closing-per-cashier-and-date key. */
    public function down(): void
    {
        Schema::table('cashier_closings', function (Blueprint $table) {
            $table->dropUnique('cashier_close_branch_user_date_unique');
            $table->unique(['user_id', 'closing_date'], 'cashier_closings_user_id_closing_date_unique');
            $table->dropIndex('cashier_closings_user_id_lookup_index');
        });
    }
};
