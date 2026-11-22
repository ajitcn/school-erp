<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Link result rows to examinations and preserve marks from the legacy entry form. */
    public function up(): void
    {
        if (! Schema::hasColumn('results', 'examination_id')) {
            Schema::table('results', function (Blueprint $table): void {
                $table->foreignId('examination_id')
                    ->nullable()
                    ->after('academic_year_id')
                    ->constrained('examinations')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasIndex('results', 'results_exam_student_idx')) {
            Schema::table('results', function (Blueprint $table): void {
                $table->index(['tenant_id', 'examination_id', 'student_id'], 'results_exam_student_idx');
            });
        }

        if (! Schema::hasTable('examination_marks') || ! Schema::hasTable('student_enrollments')) {
            return;
        }

        $legacyMarks = DB::table('examination_marks as old_marks')
            ->join('examinations', 'examinations.id', '=', 'old_marks.examination_id')
            ->leftJoin('student_enrollments', function ($join): void {
                $join->on('student_enrollments.student_id', '=', 'old_marks.student_id')
                    ->on('student_enrollments.branch_id', '=', 'examinations.branch_id')
                    ->on('student_enrollments.academic_year_id', '=', 'examinations.academic_year_id');
            })
            ->whereColumn('old_marks.tenant_id', 'examinations.tenant_id');

        if (Schema::hasTable('academic_subjects')) {
            $legacyMarks->leftJoin('academic_subjects', function ($join): void {
                $join->on('academic_subjects.tenant_id', '=', 'examinations.tenant_id')
                    ->on('academic_subjects.branch_id', '=', 'examinations.branch_id')
                    ->on('academic_subjects.name', '=', 'old_marks.subject')
                    ->where(function ($groupJoin): void {
                        $groupJoin->where(function ($classJoin): void {
                            $classJoin->whereNotNull('student_enrollments.school_class_id')
                                ->whereColumn('academic_subjects.school_class_id', 'student_enrollments.school_class_id');
                        })->orWhere(function ($courseJoin): void {
                            $courseJoin->whereNull('student_enrollments.school_class_id')
                                ->whereNotNull('student_enrollments.course_id')
                                ->whereColumn('academic_subjects.course_id', 'student_enrollments.course_id');
                        });
                    });
            });
        }

        $legacyMarks->whereNotExists(function ($query): void {
            $query->selectRaw('1')
                ->from('results as current_results')
                ->whereColumn('current_results.examination_id', 'old_marks.examination_id')
                ->whereColumn('current_results.student_id', 'old_marks.student_id')
                ->whereColumn('current_results.subject', 'old_marks.subject');
        });

        $legacyMarks->select([
            'old_marks.tenant_id',
            'old_marks.examination_id',
            'old_marks.student_id',
            'examinations.academic_year_id',
            'student_enrollments.school_class_id',
            'student_enrollments.section_id',
            'student_enrollments.course_id',
            'student_enrollments.semester_id',
            'old_marks.subject',
            'old_marks.maximum_marks as marks',
            'old_marks.marks as marks_obtained',
            'old_marks.grade',
            DB::raw("CASE WHEN examinations.status = 'published' AND old_marks.status = 'published' THEN 'published' ELSE 'draft' END as status"),
            'old_marks.created_at',
            'old_marks.updated_at',
        ]);

        if (Schema::hasTable('academic_subjects')) {
            $legacyMarks->addSelect('academic_subjects.id as subject_id');
        }

        $legacyMarks = $legacyMarks->orderBy('old_marks.id')->cursor();

        foreach ($legacyMarks as $legacyMark) {
            $result = (array) $legacyMark;
            $result['subject_id'] = $result['subject_id'] ?? null;

            DB::table('results')->insert($result);
        }
    }

    /** Remove the optional examination relation while retaining the result rows. */
    public function down(): void
    {
        if (! Schema::hasColumn('results', 'examination_id')) {
            return;
        }

        Schema::table('results', function (Blueprint $table): void {
            $table->dropForeign(['examination_id']);
            $table->dropIndex('results_exam_student_idx');
            $table->dropColumn('examination_id');
        });
    }
};
