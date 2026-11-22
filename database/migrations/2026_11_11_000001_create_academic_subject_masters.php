<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Create class and course subject masters, then connect results to them. */
    public function up(): void
    {
        Schema::create('academic_subjects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('code', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'branch_id', 'school_class_id', 'name'],
                'academic_subjects_class_name_unique',
            );
            $table->unique(
                ['tenant_id', 'branch_id', 'course_id', 'name'],
                'academic_subjects_course_name_unique',
            );
        });

        Schema::table('results', function (Blueprint $table): void {
            $table->foreignId('subject_id')->nullable()->after('subject');
            $table->foreign('subject_id', 'results_subject_master_fk')
                ->references('id')
                ->on('academic_subjects')
                ->nullOnDelete();
        });
    }

    /** Remove the results link and subject master table. */
    public function down(): void
    {
        Schema::table('results', function (Blueprint $table): void {
            $table->dropForeign('results_subject_master_fk');
            $table->dropColumn('subject_id');
        });

        Schema::dropIfExists('academic_subjects');
    }
};
