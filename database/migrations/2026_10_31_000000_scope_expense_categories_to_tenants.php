<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Replace the global category name constraint with tenant-local uniqueness. */
    public function up(): void
    {
        if (! Schema::hasTable('expense_categories') || ! Schema::hasColumn('expense_categories', 'tenant_id')) {
            return;
        }

        $indexes = collect(Schema::getIndexes('expense_categories'));
        if ($indexes->contains(fn (array $index): bool => $index['name'] === 'expense_categories_name_unique')) {
            Schema::table('expense_categories', function (Blueprint $table): void {
                $table->dropUnique('expense_categories_name_unique');
            });
        }

        if (! $indexes->contains(fn (array $index): bool => $index['name'] === 'expense_cat_tenant_name_unq')) {
            Schema::table('expense_categories', function (Blueprint $table): void {
                $table->unique(['tenant_id', 'name'], 'expense_cat_tenant_name_unq');
            });
        }
    }

    /** Restore global name uniqueness only when existing tenant data permits it. */
    public function down(): void
    {
        if (! Schema::hasTable('expense_categories')) {
            return;
        }

        $hasDuplicateNames = DB::table('expense_categories')
            ->select('name')
            ->groupBy('name')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateNames) {
            throw new RuntimeException('Cannot restore global expense category names while multiple tenants use the same category names.');
        }

        $indexes = collect(Schema::getIndexes('expense_categories'));
        if ($indexes->contains(fn (array $index): bool => $index['name'] === 'expense_cat_tenant_name_unq')) {
            Schema::table('expense_categories', function (Blueprint $table): void {
                $table->dropUnique('expense_cat_tenant_name_unq');
            });
        }

        Schema::table('expense_categories', function (Blueprint $table): void {
            $table->unique('name', 'expense_categories_name_unique');
        });
    }
};
