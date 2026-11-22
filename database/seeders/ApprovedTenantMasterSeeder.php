<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Department;
use App\Models\ExpenseCategory;
use App\Models\FinancialYear;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Creates editable starter masters for a newly approved institution.
 */
class ApprovedTenantMasterSeeder extends Seeder
{
    /**
     * Seed only the supplied tenant and restore the previous request context afterwards.
     */
    public function seedFor(Tenant $tenant): void
    {
        $context = app(TenantContext::class);
        $previousTenant = $context->get();
        $context->set($tenant);

        try {
            DB::transaction(function () use ($tenant): void {
                $branch = $tenant->branches()->first()
                    ?? Branch::query()->firstOrCreate(
                        ['tenant_id' => $tenant->id, 'code' => 'MAIN-'.$tenant->id],
                        ['name' => 'Main Campus', 'phone' => $tenant->phone, 'is_active' => true],
                    );

                $academicYear = $this->createAcademicYear($branch);
                $this->createFinancialYear($branch);
                $this->createExpenseCategories();

                if ($tenant->institution_type === 'college') {
                    $this->createCollegeMasters($branch);
                } else {
                    $this->createSchoolMasters($branch, $academicYear);
                    $this->createSectionMasters($branch);
                }
            });
        } finally {
            $context->set($previousTenant);
        }
    }

    /** Create a current April-to-March academic year if the campus has none yet. */
    private function createAcademicYear(Branch $branch): AcademicYear
    {
        $startYear = now()->month < 4 ? now()->year - 1 : now()->year;
        $name = $startYear.'-'.($startYear + 1);
        $academicYear = AcademicYear::query()->firstOrCreate(
            ['branch_id' => $branch->id, 'name' => $name],
            ['start_date' => $startYear.'-04-01', 'end_date' => ($startYear + 1).'-03-31', 'is_current' => false],
        );

        if (! AcademicYear::query()->where('branch_id', $branch->id)->where('is_current', true)->exists()) {
            $academicYear->update(['is_current' => true]);
        }

        return $academicYear;
    }

    /** Create the current financial year if the campus has none yet. */
    private function createFinancialYear(Branch $branch): void
    {
        $startYear = now()->month < 4 ? now()->year - 1 : now()->year;
        $name = $startYear.'-'.($startYear + 1);
        $financialYear = FinancialYear::query()->firstOrCreate(
            ['branch_id' => $branch->id, 'name' => $name],
            ['start_date' => $startYear.'-04-01', 'end_date' => ($startYear + 1).'-03-31', 'is_current' => false],
        );

        if (! FinancialYear::query()->where('branch_id', $branch->id)->where('is_current', true)->exists()) {
            $financialYear->update(['is_current' => true]);
        }
    }

    /** Add common expense categories without restoring categories a tenant has deactivated. */
    private function createExpenseCategories(): void
    {
        foreach ([
            'Salaries',
            'Utilities',
            'Rent',
            'Teaching supplies',
            'Transport',
            'Maintenance',
            'Technology',
            'Events and activities',
            'Other',
        ] as $name) {
            ExpenseCategory::query()->firstOrCreate(['name' => $name]);
        }
    }

    /** Add common school grade levels and editable section placeholders. */
    private function createSchoolMasters(Branch $branch, AcademicYear $academicYear): void
    {
        $levels = ['Nursery', 'LKG', 'UKG'];

        for ($grade = 1; $grade <= 12; $grade++) {
            $levels[] = 'Class '.$grade;
        }

        foreach ($levels as $index => $name) {
            $code = match ($name) {
                'Nursery' => 'NUR',
                'LKG' => 'LKG',
                'UKG' => 'UKG',
                default => 'CLS-'.str_pad((string) ($index - 2), 2, '0', STR_PAD_LEFT),
            };
            SchoolClass::query()->firstOrCreate(
                ['branch_id' => $branch->id, 'academic_year_id' => $academicYear->id, 'name' => $name],
                ['code' => $code, 'level_type' => 'school', 'sort_order' => $index + 1, 'is_active' => true],
            );
        }
    }

    /** Add reusable section labels once per branch, independent of class records. */
    private function createSectionMasters(Branch $branch): void
    {
        foreach (['A', 'B', 'C'] as $name) {
            Section::query()->firstOrCreate(
                ['branch_id' => $branch->id, 'name' => $name],
                ['is_active' => true],
            );
        }
    }

    /** Add common college departments, undergraduate courses, and semester placeholders. */
    private function createCollegeMasters(Branch $branch): void
    {
        $programs = [
            ['department' => 'Arts', 'department_code' => 'ART', 'course' => 'Bachelor of Arts', 'course_code' => 'BA'],
            ['department' => 'Science', 'department_code' => 'SCI', 'course' => 'Bachelor of Science', 'course_code' => 'BSC'],
            ['department' => 'Commerce', 'department_code' => 'COM', 'course' => 'Bachelor of Commerce', 'course_code' => 'BCOM'],
            ['department' => 'Management', 'department_code' => 'MGT', 'course' => 'Bachelor of Business Administration', 'course_code' => 'BBA'],
            ['department' => 'Computer Applications', 'department_code' => 'CA', 'course' => 'Bachelor of Computer Applications', 'course_code' => 'BCA'],
        ];

        foreach ($programs as $program) {
            $department = Department::query()->firstOrCreate(
                ['branch_id' => $branch->id, 'name' => $program['department']],
                ['code' => $program['department_code'], 'is_active' => true],
            );
            $course = Course::query()->firstOrCreate(
                ['branch_id' => $branch->id, 'code' => $program['course_code']],
                ['department_id' => $department->id, 'name' => $program['course'], 'duration_years' => 3, 'is_active' => true],
            );

            for ($semesterNumber = 1; $semesterNumber <= 6; $semesterNumber++) {
                Semester::query()->firstOrCreate(
                    ['course_id' => $course->id, 'semester_number' => $semesterNumber],
                    ['name' => 'Semester '.$semesterNumber, 'is_active' => true],
                );
            }
        }
    }

    /** Keep the standard seeder command useful for an explicitly selected tenant context. */
    public function run(): void
    {
        $tenant = app(TenantContext::class)->get();

        if ($tenant) {
            $this->seedFor($tenant);
        }
    }
}
