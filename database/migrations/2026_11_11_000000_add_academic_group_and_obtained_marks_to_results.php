<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Add optional academic-group context and obtained marks to student results. */
    public function up(): void
    {
        Schema::table('results', function (Blueprint $table): void {
            $table->foreignId('school_class_id')->nullable()->after('academic_year_id');
            $table->foreignId('section_id')->nullable()->after('school_class_id');
            $table->foreignId('course_id')->nullable()->after('section_id');
            $table->decimal('marks_obtained', 8, 2)->nullable()->after('marks');

            $table->foreign('school_class_id', 'results_school_class_fk')
                ->references('id')
                ->on('school_classes')
                ->nullOnDelete();
            $table->foreign('section_id', 'results_section_fk')
                ->references('id')
                ->on('sections')
                ->nullOnDelete();
            $table->foreign('course_id', 'results_course_fk')
                ->references('id')
                ->on('courses')
                ->nullOnDelete();
        });

        DB::table('results')
            ->whereNotNull('marks')
            ->update([
                'marks_obtained' => DB::raw('marks'),
                'marks' => null,
            ]);
    }

    /** Remove the optional academic-group context and obtained-mark column. */
    public function down(): void
    {
        DB::table('results')
            ->whereNotNull('marks_obtained')
            ->update(['marks' => DB::raw('marks_obtained')]);

        Schema::table('results', function (Blueprint $table): void {
            $table->dropForeign('results_school_class_fk');
            $table->dropForeign('results_section_fk');
            $table->dropForeign('results_course_fk');
            $table->dropColumn([
                'school_class_id',
                'section_id',
                'course_id',
                'marks_obtained',
            ]);
        });
    }
};
