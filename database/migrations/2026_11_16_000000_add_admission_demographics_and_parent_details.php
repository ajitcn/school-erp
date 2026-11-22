<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Add optional student admission details and allow guardian contact data to be incomplete. */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->string('roll_number', 50)->nullable();
            $table->string('gr_number', 50)->nullable();
            $table->string('place_of_birth', 150)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('mother_tongue', 100)->nullable();
            $table->string('religion', 80)->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->text('aadhaar_number')->nullable();
            $table->string('caste', 100)->nullable();
            $table->string('category', 50)->nullable();
        });

        foreach (DB::table('branches')->orderBy('id')->pluck('id') as $branchId) {
            $rollNumber = 0;
            $grNumber = 0;

            foreach (DB::table('students')->where('branch_id', $branchId)->orderBy('id')->get(['id', 'roll_number', 'gr_number']) as $student) {
                $values = [];

                if (blank($student->roll_number)) {
                    $values['roll_number'] = (string) ++$rollNumber;
                } elseif (preg_match('/^\d+$/D', $student->roll_number)) {
                    $rollNumber = max($rollNumber, (int) $student->roll_number);
                }

                if (blank($student->gr_number)) {
                    $values['gr_number'] = (string) ++$grNumber;
                } elseif (preg_match('/^\d+$/D', $student->gr_number)) {
                    $grNumber = max($grNumber, (int) $student->gr_number);
                }

                if ($values !== []) {
                    DB::table('students')->where('id', $student->id)->update($values);
                }
            }
        }

        Schema::table('students', function (Blueprint $table): void {
            $table->unique(['branch_id', 'roll_number'], 'students_branch_roll_number_unique');
            $table->unique(['branch_id', 'gr_number'], 'students_branch_gr_number_unique');
        });

        Schema::table('guardians', function (Blueprint $table): void {
            $table->string('phone')->nullable()->change();
        });
    }

    /** Remove the added fields and restore the previous guardian phone constraint. */
    public function down(): void
    {
        DB::table('guardians')->whereNull('phone')->update(['phone' => '']);

        Schema::table('guardians', function (Blueprint $table): void {
            $table->string('phone')->nullable(false)->change();
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->dropUnique('students_branch_roll_number_unique');
            $table->dropUnique('students_branch_gr_number_unique');
            $table->dropColumn([
                'roll_number',
                'gr_number',
                'place_of_birth',
                'state',
                'nationality',
                'mother_tongue',
                'religion',
                'blood_group',
                'aadhaar_number',
                'caste',
                'category',
            ]);
        });
    }
};
