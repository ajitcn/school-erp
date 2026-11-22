<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Add a review state to tenant applications while preserving existing tenants.
     */
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        if (! Schema::hasColumn('tenants', 'registration_status')) {
            Schema::table('tenants', function (Blueprint $table): void {
                // Existing institutions stay approved; only new registrations wait for review.
                $table->string('registration_status', 20)->default('approved');
            });
        }

        if (! Schema::hasColumn('tenants', 'registration_reviewed_by')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->foreignId('registration_reviewed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('tenants', 'registration_reviewed_at')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->timestamp('registration_reviewed_at')->nullable();
            });
        }

        if (! Schema::hasIndex('tenants', 'tenants_registration_status_index')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->index('registration_status', 'tenants_registration_status_index');
            });
        }
    }

    /**
     * Remove the review metadata added by this migration.
     */
    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        if (Schema::hasIndex('tenants', 'tenants_registration_status_index')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropIndex('tenants_registration_status_index');
            });
        }

        if (Schema::hasColumn('tenants', 'registration_reviewed_by')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('registration_reviewed_by');
            });
        }

        $columnsToDrop = array_values(array_filter(
            ['registration_status', 'registration_reviewed_at'],
            fn (string $column): bool => Schema::hasColumn('tenants', $column),
        ));

        if ($columnsToDrop !== []) {
            Schema::table('tenants', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }
    }
};
