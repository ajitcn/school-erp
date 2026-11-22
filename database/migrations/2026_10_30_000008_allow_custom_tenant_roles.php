<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tenant_user', fn (Blueprint $table) => $table->string('role', 80)->default('staff')->change());
    }

    public function down(): void
    {
        // Custom role names cannot safely be converted back to the legacy enum.
        if (DB::table('tenant_user')->whereNotIn('role', ['owner', 'administrator', 'staff', 'teacher', 'fee_staff', 'student', 'parent'])->exists()) {
            throw new RuntimeException('Custom tenant roles exist; migrate them before rolling back.');
        }
        Schema::table('tenant_user', fn (Blueprint $table) => $table->enum('role', ['owner', 'administrator', 'staff', 'teacher', 'fee_staff', 'student', 'parent'])
            ->default('staff')
            ->change());
    }
};
