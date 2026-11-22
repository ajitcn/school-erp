<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Add explicit tenant-aware composite keys to the academic master tables.
     *
     * Branch ownership remains part of branch-level keys, so separate campuses
     * in one tenant may maintain their own academic setup while values from
     * different tenants remain isolated at the database boundary.
     */
    public function up(): void
    {
        $branchScopedTables = [
            'academic_years',
            'school_classes',
            'sections',
            'departments',
            'courses',
        ];

        foreach ($branchScopedTables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            DB::table($tableName)
                ->whereNull('tenant_id')
                ->orderBy('id')
                ->chunkById(500, function ($records) use ($tableName): void {
                    foreach ($records as $record) {
                        $tenantId = DB::table('branches')
                            ->where('id', $record->branch_id)
                            ->value('tenant_id');

                        if ($tenantId) {
                            DB::table($tableName)
                                ->where('id', $record->id)
                                ->update(['tenant_id' => $tenantId]);
                        }
                    }
                });
        }

        if (Schema::hasTable('semesters') && Schema::hasColumn('semesters', 'tenant_id')) {
            DB::table('semesters')
                ->whereNull('tenant_id')
                ->orderBy('id')
                ->chunkById(500, function ($records): void {
                    foreach ($records as $record) {
                        $tenantId = DB::table('courses')
                            ->where('id', $record->course_id)
                            ->value('tenant_id');

                        if ($tenantId) {
                            DB::table('semesters')
                                ->where('id', $record->id)
                                ->update(['tenant_id' => $tenantId]);
                        }
                    }
                });
        }

        foreach ([...$branchScopedTables, 'semesters'] as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }

            if (DB::table($tableName)->whereNull('tenant_id')->exists()) {
                throw new RuntimeException("Cannot scope {$tableName}: one or more records are not linked to a tenant.");
            }

            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        }

        $this->addUniqueIndex('academic_years', 'academic_years_tenant_branch_name_unique', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'branch_id', 'name'], 'academic_years_tenant_branch_name_unique');
        });
        $this->addUniqueIndex('school_classes', 'school_classes_tenant_branch_year_name_unique', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'branch_id', 'academic_year_id', 'name'], 'school_classes_tenant_branch_year_name_unique');
        });
        $this->addUniqueIndex('sections', 'sections_tenant_branch_name_unique', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'branch_id', 'name'], 'sections_tenant_branch_name_unique');
        });
        $this->addUniqueIndex('departments', 'departments_tenant_branch_name_unique', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'branch_id', 'name'], 'departments_tenant_branch_name_unique');
        });
        $this->addUniqueIndex('courses', 'courses_tenant_branch_code_unique', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'branch_id', 'code'], 'courses_tenant_branch_code_unique');
        });
        $this->addUniqueIndex('semesters', 'semesters_tenant_course_number_unique', function (Blueprint $table): void {
            $table->unique(['tenant_id', 'course_id', 'semester_number'], 'semesters_tenant_course_number_unique');
        });
    }

    /** Add a tenant-aware key while retaining legacy keys used by existing foreign keys. */
    private function addUniqueIndex(string $tableName, string $indexName, Closure $addIndex): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($addIndex): void {
            $addIndex($table);
        });
    }

    /** Restore the original database keys when rolling this change back. */
    public function down(): void
    {
        $this->dropUniqueIndex('academic_years', 'academic_years_tenant_branch_name_unique');
        $this->dropUniqueIndex('school_classes', 'school_classes_tenant_branch_year_name_unique');
        $this->dropUniqueIndex('sections', 'sections_tenant_branch_name_unique');
        $this->dropUniqueIndex('departments', 'departments_tenant_branch_name_unique');
        $this->dropUniqueIndex('courses', 'courses_tenant_branch_code_unique');
        $this->dropUniqueIndex('semesters', 'semesters_tenant_course_number_unique');
    }

    /** Remove only the key introduced by this migration during rollback. */
    private function dropUniqueIndex(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || ! Schema::hasIndex($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName): void {
            $table->dropUnique($indexName);
        });
    }
};
