<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->foreignId('academic_year_id')->nullable()->change();
        });

        Schema::table('fee_components', function (Blueprint $table) {
            $table->foreignId('student_id')->nullable()->after('fee_structure_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->after('student_id')->constrained()->nullOnDelete();
        });

        DB::table('fee_components')->join('fee_structures', 'fee_components.fee_structure_id', '=', 'fee_structures.id')
            ->whereNull('fee_components.academic_year_id')
            ->update(['fee_components.academic_year_id' => DB::raw('fee_structures.academic_year_id')]);
    }

    public function down(): void
    {
        Schema::table('fee_components', function (Blueprint $table) {
            $table->dropForeign(['student_id']);
            $table->dropForeign(['academic_year_id']);
            $table->dropColumn(['student_id', 'academic_year_id']);
        });

        // Keep the column nullable during rollback because reusable plans may not have a year.
    }
};
