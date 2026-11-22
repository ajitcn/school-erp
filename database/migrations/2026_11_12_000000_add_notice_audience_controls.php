<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Add role and student targeting to tenant announcements. */
    public function up(): void
    {
        Schema::table('notices', function (Blueprint $table): void {
            $table->string('audience_type', 20)->default('all')->after('body');
            $table->string('student_scope', 20)->default('all')->after('audience_type');
        });

        Schema::create('notice_students', function (Blueprint $table): void {
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('notice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->primary(['notice_id', 'student_id']);
            $table->index(['tenant_id', 'student_id']);
        });
    }

    /** Remove audience targeting data when rolling back this migration. */
    public function down(): void
    {
        Schema::dropIfExists('notice_students');

        Schema::table('notices', function (Blueprint $table): void {
            $table->dropColumn(['audience_type', 'student_scope']);
        });
    }
};
