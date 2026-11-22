<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Consolidate per-class section labels into branch-level reusable section masters. */
    public function up(): void
    {
        if (! Schema::hasTable('sections') || ! Schema::hasColumn('sections', 'school_class_id')) {
            return;
        }

        $referenceTables = collect(Schema::getTables())
            ->pluck('name')
            ->filter(fn (string $table): bool => $table !== 'sections' && Schema::hasColumn($table, 'section_id'));
        $canonicalSections = [];

        foreach (DB::table('sections')->orderBy('id')->get(['id', 'branch_id', 'name']) as $section) {
            $key = $section->branch_id.'|'.mb_strtolower(trim($section->name));

            if (! isset($canonicalSections[$key])) {
                $canonicalSections[$key] = $section->id;

                continue;
            }

            foreach ($referenceTables as $table) {
                DB::table($table)->where('section_id', $section->id)
                    ->update(['section_id' => $canonicalSections[$key]]);
            }

            DB::table('sections')->where('id', $section->id)->delete();
        }

        Schema::table('sections', function (Blueprint $table): void {
            $table->dropForeign('sections_school_class_id_foreign');
            $table->dropUnique('sections_school_class_id_name_unique');
            $table->dropColumn('school_class_id');
            $table->unique(['branch_id', 'name'], 'sections_branch_id_name_unique');
        });
    }

    /** Restore the former class reference as nullable without discarding shared masters. */
    public function down(): void
    {
        if (! Schema::hasTable('sections') || Schema::hasColumn('sections', 'school_class_id')) {
            return;
        }

        Schema::table('sections', function (Blueprint $table): void {
            $table->dropUnique('sections_branch_id_name_unique');
            $table->foreignId('school_class_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->unique(['school_class_id', 'name'], 'sections_school_class_id_name_unique');
        });
    }
};
