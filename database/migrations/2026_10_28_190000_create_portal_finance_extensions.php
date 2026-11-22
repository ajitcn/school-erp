<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('financial_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });
        Schema::create('collection_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['branch_id', 'name']);
        });
        Schema::create('user_counter', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('collection_counter_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'collection_counter_id']);
        });
        Schema::create('student_user', function (Blueprint $table) {
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['student_id', 'user_id']);
        });
        Schema::create('guardian_user', function (Blueprint $table) {
            $table->foreignId('guardian_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['guardian_id', 'user_id']);
        });
        Schema::create('timetables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->nullOnDelete();
            $table->string('weekday');
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('subject')->nullable();
            $table->timestamps();
        });
        Schema::create('results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->string('subject');
            $table->decimal('marks', 8, 2)->nullable();
            $table->string('grade')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
        });
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('event')->unique();
            $table->string('channel');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('opening_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->date('balance_date');
            $table->string('account');
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['opening_balances', 'notification_templates', 'results', 'timetables', 'guardian_user', 'student_user', 'user_counter', 'collection_counters', 'financial_years'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
