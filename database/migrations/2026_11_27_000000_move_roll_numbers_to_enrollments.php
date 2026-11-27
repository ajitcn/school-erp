<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Preserve existing student numbers on their latest enrollment. */
    public function up(): void
    {
        if (! Schema::hasColumn('student_enrollments', 'roll_number')) {
            Schema::table('student_enrollments', fn (Blueprint $table) => $table->string('roll_number', 50)->nullable());
        }

        foreach (['roll_class_key' => 'school_class_id', 'roll_section_key' => 'section_id', 'roll_course_key' => 'course_id', 'roll_semester_key' => 'semester_id'] as $key => $column) {
            if (! Schema::hasColumn('student_enrollments', $key)) {
                Schema::table('student_enrollments', fn (Blueprint $table) => $table->unsignedBigInteger($key)->storedAs("coalesce({$column}, 0)"));
            }
        }

        if (! Schema::hasIndex('student_enrollments', 'enrollment_group_roll_unique')) {
            Schema::table('student_enrollments', fn (Blueprint $table) => $table->unique(['branch_id', 'academic_year_id', 'roll_class_key', 'roll_section_key', 'roll_course_key', 'roll_semester_key', 'roll_number'], 'enrollment_group_roll_unique'));
        }

        DB::table('student_enrollments')->orderBy('id')->chunkById(500, function ($enrollments): void {
            foreach ($enrollments as $enrollment) {
                $latestId = DB::table('student_enrollments')->where('student_id', $enrollment->student_id)->orderByDesc('id')->value('id');
                if ($enrollment->id !== $latestId) {
                    continue;
                }
                $rollNumber = DB::table('students')->where('id', $enrollment->student_id)->value('roll_number');
                DB::table('student_enrollments')->where('id', $enrollment->id)->whereNull('roll_number')->update(['roll_number' => $rollNumber]);
            }
        });

        foreach (Schema::getIndexes('students') as $index) {
            if ($index['unique'] && $index['columns'] === ['branch_id', 'roll_number']) {
                Schema::table('students', fn (Blueprint $table) => $table->dropUnique($index['name']));
            }
        }
    }

    /** Restore the former constraint only if existing data permits it. */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->unique(['branch_id', 'roll_number'], 'students_branch_roll_number_unique');
        });
        Schema::table('student_enrollments', function (Blueprint $table): void {
            $table->dropUnique('enrollment_group_roll_unique');
            $table->dropColumn(['roll_number', 'roll_class_key', 'roll_section_key', 'roll_course_key', 'roll_semester_key']);
        });
    }
};
