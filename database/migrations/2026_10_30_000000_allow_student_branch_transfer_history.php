<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // Keep standalone foreign-key indexes before removing the composite unique index.
        // MySQL may otherwise reject the index drop because it uses that index for a foreign key.
        foreach ([
            'student_id' => 'student_enrollments_student_id_index',
            'branch_id' => 'student_enrollments_branch_id_index',
            'academic_year_id' => 'student_enrollments_academic_year_id_index',
        ] as $column => $indexName) {
            if (! Schema::hasIndex('student_enrollments', [$column])) {
                Schema::table('student_enrollments', function (Blueprint $table) use ($column, $indexName): void {
                    $table->index($column, $indexName);
                });
            }
        }

        Schema::table('student_enrollments', function (Blueprint $table) {
            if (Schema::hasIndex('student_enrollments', 'student_enrollments_student_id_academic_year_id_unique')) {
                $table->dropUnique('student_enrollments_student_id_academic_year_id_unique');
            }

            if (! Schema::hasIndex('student_enrollments', 'student_enrollment_year_branch_unique')) {
                $table->unique(
                    ['student_id', 'academic_year_id', 'branch_id'],
                    'student_enrollment_year_branch_unique'
                );
            }
        });
    }

    public function down(): void
    {
        // Keep historical transfer rows intact; restoring the old one-campus-per-year unique key
        // could make a rollback fail once a student has valid cross-campus history.
        Schema::table('student_enrollments', function (Blueprint $table) {
            $table->dropUnique('student_enrollment_year_branch_unique');
        });
    }
};
