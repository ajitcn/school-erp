<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Validation\ValidationException;

/** Generates independent, branch-scoped R.NO and GR.NO sequences. */
class StudentRegisterNumberService
{
    /** Return the next roll number while preserving existing numeric padding. */
    public function nextRollNumber(Branch $branch, array $group): string
    {
        $values = $this->rollQuery($branch, $group)->whereNotNull('roll_number')->pluck('roll_number')
            ->filter(fn ($value): bool => preg_match('/^\d+$/D', (string) $value) === 1);
        $maximum = $values->map(fn ($value): int => (int) $value)->max() ?? 0;
        $padding = $values->max(fn ($value): int => strlen((string) $value)) ?? 1;

        return str_pad((string) ($maximum + 1), $padding, '0', STR_PAD_LEFT);
    }

    /** Check a number inside the enrollment group while the branch is locked. */
    public function assertRollAvailable(Branch $branch, array $group, string $number, ?int $exceptEnrollment = null): void
    {
        if ($this->rollQuery($branch, $group)->where('roll_number', $number)
            ->when($exceptEnrollment, fn ($query) => $query->where('id', '!=', $exceptEnrollment))->exists()) {
            throw ValidationException::withMessages(['roll_number' => 'This roll number is already used in the selected academic group.']);
        }
    }

    private function rollQuery(Branch $branch, array $group): \Illuminate\Database\Eloquent\Builder
    {
        $query = StudentEnrollment::query()->where('branch_id', $branch->id)->where('academic_year_id', $group['academic_year_id']);
        foreach (['school_class_id', 'section_id', 'course_id', 'semester_id'] as $field) {
            $query->where($field, $group[$field] ?? null);
        }

        return $query;
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
