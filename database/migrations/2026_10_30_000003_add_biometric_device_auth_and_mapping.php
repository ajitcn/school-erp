<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->char('api_token_hash', 64)->nullable()->unique();
        });
        Schema::create('biometric_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('biometric_device_id')->constrained()->cascadeOnDelete();
            $table->string('person_identifier');
            $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['biometric_device_id', 'person_identifier']);
        });
        Schema::create('teacher_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('session')->default('full_day');
            $table->string('status')->default('present');
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['teacher_id', 'attendance_date', 'session']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_attendance');
        Schema::dropIfExists('biometric_mappings');
        Schema::table('biometric_devices', function (Blueprint $table) {
            $table->dropUnique(['api_token_hash']);
            $table->dropColumn('api_token_hash');
        });
    }
};
