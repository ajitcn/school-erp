<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Restore the tenant membership's preferred branch when a database is missing the column.
     *
     * The original tenant-role migration adds this field. This guard also repairs databases
     * where that migration was recorded as complete while the schema change was not applied.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tenant_user') || Schema::hasColumn('tenant_user', 'primary_branch_id')) {
            return;
        }

        Schema::table('tenant_user', function (Blueprint $table): void {
            $table->foreignId('primary_branch_id')
                ->nullable()
                ->after('role')
                ->constrained('branches')
                ->nullOnDelete();
        });
    }

    /**
     * Remove the repaired field when rolling this migration back.
     */
    public function down(): void
    {
        if (Schema::hasTable('tenant_user') && Schema::hasColumn('tenant_user', 'primary_branch_id')) {
            Schema::table('tenant_user', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('primary_branch_id');
            });
        }
    }
};
