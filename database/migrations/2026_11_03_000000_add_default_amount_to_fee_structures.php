<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->decimal('default_amount', 12, 2)->nullable()->after('name');
        });

        $sharedPlanAmounts = DB::table('fee_components')
            ->whereNull('student_id')
            ->select('fee_structure_id', DB::raw('SUM(amount) as plan_amount'))
            ->groupBy('fee_structure_id')
            ->get()
            ->keyBy('fee_structure_id');

        $studentPlanAmounts = DB::table('fee_components')
            ->whereNotNull('student_id')
            ->select('fee_structure_id', 'student_id', 'academic_year_id', DB::raw('SUM(amount) as plan_amount'))
            ->groupBy('fee_structure_id', 'student_id', 'academic_year_id')
            ->orderByDesc('plan_amount')
            ->get()
            ->groupBy('fee_structure_id');

        DB::table('fee_structures')->orderBy('id')->get()->each(function ($structure) use ($sharedPlanAmounts, $studentPlanAmounts) {
            $amount = $sharedPlanAmounts->get($structure->id)?->plan_amount
                ?? $studentPlanAmounts->get($structure->id)?->first()?->plan_amount;

            if ($amount !== null) {
                DB::table('fee_structures')->where('id', $structure->id)->update(['default_amount' => $amount]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('fee_structures', function (Blueprint $table) {
            $table->dropColumn('default_amount');
        });
    }
};
