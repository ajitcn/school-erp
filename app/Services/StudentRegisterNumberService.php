<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Student;

/** Generates independent, branch-scoped R.NO and GR.NO sequences. */
class StudentRegisterNumberService
{
    /** Return the next roll number while preserving existing numeric padding. */
    public function nextRollNumber(Branch $branch): string
    {
        return $this->nextNumber($branch, 'roll_number');
    }

    /** Return the next general-register number while preserving existing numeric padding. */
    public function nextGrNumber(Branch $branch): string
    {
        return $this->nextNumber($branch, 'gr_number');
    }

    /** Find the next integer in a supported student register column. */
    private function nextNumber(Branch $branch, string $column): string
    {
        abort_unless(in_array($column, ['roll_number', 'gr_number'], true), 500);

        $numericValues = Student::query()
            ->where('branch_id', $branch->id)
            ->whereNotNull($column)
            ->pluck($column)
            ->filter(fn (mixed $value): bool => preg_match('/^\d+$/D', (string) $value) === 1)
            ->map(fn (mixed $value): string => (string) $value);

        $maximum = $numericValues->map(fn (string $value): int => (int) $value)->max() ?? 0;
        $padding = $numericValues->max(fn (string $value): int => strlen($value)) ?? 1;

        return str_pad((string) ($maximum + 1), max(1, $padding), '0', STR_PAD_LEFT);
    }
}
