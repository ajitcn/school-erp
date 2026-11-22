<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Add password hashes to portal records and create single-use setup tokens. */
    public function up(): void
    {
        foreach (['students', 'guardians'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'password')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->string('password')->nullable();
                });
            }
        }

        if (! Schema::hasTable('portal_password_setup_tokens')) {
            Schema::create('portal_password_setup_tokens', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('guardian_id')->nullable()->constrained()->cascadeOnDelete();
                $table->foreignId('generated_by')->constrained('users')->cascadeOnDelete();
                $table->char('token_hash', 64)->unique();
                $table->timestamp('expires_at')->index();
                $table->timestamp('used_at')->nullable();
                $table->timestamps();
                $table->index(['tenant_id', 'student_id', 'used_at'], 'portal_setup_student_idx');
                $table->index(['tenant_id', 'guardian_id', 'used_at'], 'portal_setup_guardian_idx');
            });
        }
    }

    /** Remove the portal password fields and setup token records. */
    public function down(): void
    {
        Schema::dropIfExists('portal_password_setup_tokens');

        foreach (['students', 'guardians'] as $tableName) {
            if (Schema::hasColumn($tableName, 'password')) {
                Schema::table($tableName, function (Blueprint $table): void {
                    $table->dropColumn('password');
                });
            }
        }
    }
};
