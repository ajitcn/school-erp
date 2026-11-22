<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Add a snapshot of the academic group to each attendance record. */
    public function up(): void
    {
        Schema::table('attendance', function (Blueprint $table): void {
            $table->foreignId('school_class_id')
                ->nullable()
                ->after('academic_year_id')
                ->constrained('school_classes')
                ->nullOnDelete();
            $table->foreignId('section_id')
                ->nullable()
                ->after('school_class_id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('course_id')
                ->nullable()
                ->after('section_id')
                ->constrained()
                ->nullOnDelete();
            $table->foreignId('semester_id')
                ->nullable()
                ->after('course_id')
                ->constrained()
                ->nullOnDelete();
            $table->index(
                ['attendance_date', 'branch_id', 'academic_year_id', 'school_class_id', 'section_id', 'course_id', 'semester_id'],
                'attendance_group_lookup_idx',
            );
        });

        // Existing attendance can be linked to its enrollment for its branch and academic year.
        DB::statement(<<<'SQL'
            UPDATE attendance AS attendance_records
            INNER JOIN student_enrollments AS enrollments
                ON enrollments.student_id = attendance_records.student_id
                AND enrollments.branch_id = attendance_records.branch_id
                AND enrollments.academic_year_id = attendance_records.academic_year_id
            SET attendance_records.school_class_id = enrollments.school_class_id,
                attendance_records.section_id = enrollments.section_id,
                attendance_records.course_id = enrollments.course_id,
                attendance_records.semester_id = enrollments.semester_id
        SQL);
    }

    /** Remove the academic group snapshot fields. */
    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table): void {
            $table->dropIndex('attendance_group_lookup_idx');
            $table->dropForeign(['school_class_id']);
            $table->dropForeign(['section_id']);
            $table->dropForeign(['course_id']);
            $table->dropForeign(['semester_id']);
            $table->dropColumn(['school_class_id', 'section_id', 'course_id', 'semester_id']);
        });
    }
};
