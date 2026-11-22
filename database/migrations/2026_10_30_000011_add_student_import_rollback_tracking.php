<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('import_histories', function (Blueprint $table) {
            $table->json('imported_student_ids')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('import_histories', fn (Blueprint $table) => $table->dropColumn(['imported_student_ids', 'rolled_back_at']));
    }
};
