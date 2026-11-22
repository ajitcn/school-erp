<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AcademicFoundationSeeder extends Seeder
{
    public function run(): void
    {
        $tenantId = DB::table('tenants')
            ->where('email', 'main@school.test')
            ->value('id');

        if (! $tenantId) {
            return;
        }

        DB::table('branches')->updateOrInsert(
            ['tenant_id' => $tenantId, 'code' => 'MAIN'],
            [
                'tenant_id' => $tenantId,
                'name' => 'Main Campus',
                'address' => 'Main Campus Address',
                'phone' => '0000000000',
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $branchId = DB::table('branches')
            ->where('code', 'MAIN')
            ->where('tenant_id', $tenantId)
            ->value('id');

        DB::table('academic_years')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'name' => '2026-2027',
            ],
            [
                'start_date' => '2026-06-01',
                'end_date' => '2027-05-31',
                'is_current' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $academicYearId = DB::table('academic_years')
            ->where('branch_id', $branchId)
            ->where('name', '2026-2027')
            ->value('id');

        DB::table('school_classes')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'academic_year_id' => $academicYearId,
                'name' => 'Class 5',
            ],
            [
                'code' => 'CLS5',
                'level_type' => 'school',
                'sort_order' => 5,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $classId = DB::table('school_classes')
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $academicYearId)
            ->where('name', 'Class 5')
            ->value('id');

        DB::table('sections')->updateOrInsert(
            ['tenant_id' => $tenantId, 'branch_id' => $branchId, 'name' => 'A'],
            [
                'tenant_id' => $tenantId,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        DB::table('departments')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'name' => 'Computer Science',
            ],
            [
                'code' => 'CS',
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $departmentId = DB::table('departments')
            ->where('branch_id', $branchId)
            ->where('tenant_id', $tenantId)
            ->where('name', 'Computer Science')
            ->value('id');

        DB::table('courses')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'code' => 'BCA',
            ],
            [
                'department_id' => $departmentId,
                'name' => 'Bachelor of Computer Applications',
                'duration_years' => 3,
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $courseId = DB::table('courses')
            ->where('branch_id', $branchId)
            ->where('code', 'BCA')
            ->value('id');

        DB::table('semesters')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'course_id' => $courseId,
                'semester_number' => 1,
            ],
            [
                'name' => 'Semester 1',
                'is_active' => true,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }
}
