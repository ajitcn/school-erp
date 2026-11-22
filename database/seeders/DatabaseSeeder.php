<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\Guardian;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app(TenantContext::class)->set(Tenant::where('email', 'main@school.test')->first());
        $this->call(AcademicFoundationSeeder::class);
        $branch = Branch::where('code', 'MAIN')->firstOrFail();
        $year = AcademicYear::where('branch_id', $branch->id)->where('name', '2026-2027')->firstOrFail();
        $permissions = [
            'students.view' => 'View students',
            'students.create' => 'Create students',
            'students.edit' => 'Edit students',
            'students.status' => 'Change student status',
            'students.enroll' => 'Manage student enrollments',
            'students.import' => 'Import students',
            'admissions.view' => 'View admissions',
            'admissions.manage' => 'Review admissions and manage documents',
            'attendance.view' => 'View attendance',
            'attendance.mark' => 'Mark attendance',
            'attendance.edit' => 'Edit attendance and request corrections',
            'attendance.approve' => 'Approve attendance corrections',
            'expenses.view' => 'View expenses',
            'expenses.create' => 'Create expenses',
            'expenses.edit' => 'Edit expenses',
            'expenses.categories.manage' => 'Manage expense categories',
            'expenses.approve' => 'Approve expenses',
            'payments.view' => 'View payments and student dues',
            'payments.collect' => 'Collect payments',
            'payments.edit' => 'Request payment corrections',
            'payments.refund' => 'Request refunds',
            'payments.cancel_receipt' => 'Request receipt cancellations',
            'payments.approve_adjustments' => 'Approve payment adjustments',
            'payments.approve_refunds' => 'Approve refunds',
            'payments.approve_receipts' => 'Approve receipt cancellations',
            'reconciliation.view' => 'View reconciliation',
            'reconciliation.reconcile' => 'Reconcile collections',
            'reconciliation.close' => 'Close cashier sessions',
            'fees.view' => 'View fee plans and assignments',
            'fees.assign' => 'Assign and update student fees',
            'fees.plan.create' => 'Create fee plans',
            'fees.plan.edit' => 'Edit and deactivate fee plans',
            'academic.view' => 'View academic records',
            'academic.create' => 'Create academic records',
            'academic.edit' => 'Edit academic records',
            'academic.delete' => 'Delete academic records',
            'results.view' => 'View student results',
            'results.record' => 'Record student results',
            'results.edit' => 'Edit student results',
            'results.delete' => 'Delete student results',
            'examinations.view' => 'View examinations',
            'examinations.create' => 'Create examinations',
            'examinations.edit' => 'Edit examinations',
            'examinations.publish' => 'Publish examinations and linked results',
            'timetable.view' => 'View timetables',
            'timetable.create' => 'Create timetable periods',
            'timetable.edit' => 'Edit timetable periods',
            'setup.view' => 'View institution setup',
            'setup.manage' => 'Manage institution setup',
            'users.view' => 'View tenant users',
            'users.manage' => 'Create and manage tenant users',
            'roles.view' => 'View roles and permissions',
            'roles.manage' => 'Create and manage roles',
            'notices.view' => 'View announcements',
            'notices.manage' => 'Create announcements',
            'notices.publish' => 'Publish announcements',
            'notifications.view' => 'View notification history and templates',
            'notifications.send' => 'Send notifications',
            'notifications.templates.manage' => 'Manage notification templates',
            'services.view' => 'View student services',
            'services.manage' => 'Manage student services',
            'integrations.view' => 'View integrations and API access',
            'integrations.manage' => 'Manage integrations and API access',
            'workflow.view' => 'View approval queue',
            'reports.view' => 'View reports',
            'reports.export' => 'Export reports',
            'portal.view' => 'View the student or parent portal',
        ];
        foreach ($permissions as $name => $label) {
            Permission::updateOrCreate(['name' => $name], ['label' => $label]);
        }
        $roleNames = [
            'super_admin' => 'Super Admin',
            'administrator' => 'School/College Administrator',
            'staff' => 'Staff',
            'teacher' => 'Teacher',
            'fee_staff' => 'Fee Collection Staff',
            'student' => 'Student',
            'parent' => 'Parent',
        ];
        foreach ($roleNames as $name => $label) {
            Role::updateOrCreate(['tenant_id' => null, 'name' => $name], ['label' => $label]);
        }
        $adminRole = Role::whereNull('tenant_id')->where('name', 'super_admin')->first();
        $adminRole->permissions()->sync(Permission::pluck('id'));
        $this->seedBuiltInRolePermissions($permissions);
        $admin = User::updateOrCreate(['email' => 'admin@school.test'], ['name' => 'Demo Super Admin', 'password' => Hash::make('ChangeMe!2026'), 'branch_id' => $branch->id, 'is_active' => true]);
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        $tenant = Tenant::where('email', 'main@school.test')->firstOrFail();
        $tenant->users()->syncWithoutDetaching([$admin->id => ['role' => 'owner', 'primary_branch_id' => $branch->id, 'is_active' => true, 'joined_at' => now()]]);
        DB::table('user_branch_access')->updateOrInsert(['user_id' => $admin->id, 'branch_id' => $branch->id], ['tenant_id' => $tenant->id]);
        if (Schema::hasColumn('users', 'is_platform_super_admin')) {
            $admin->forceFill(['is_platform_super_admin' => true])->save();
        }
        foreach (['Salaries', 'Electricity', 'Stationery', 'Maintenance', 'Transport', 'Rent', 'Supplies', 'Other'] as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name]);
        }
        $student = Student::updateOrCreate(['student_id' => 'MAIN-00001'], ['branch_id' => $branch->id, 'admission_number' => 'MAIN/2026/00001', 'first_name' => 'Demo', 'last_name' => 'Student', 'status' => 'active', 'phone' => '9999999999']);
        $class = SchoolClass::where('branch_id', $branch->id)->where('academic_year_id', $year->id)->first();
        $section = Section::where('school_class_id', $class->id)->first();
        StudentEnrollment::updateOrCreate(['student_id' => $student->id, 'academic_year_id' => $year
            ->id], ['branch_id' => $branch
            ->id, 'school_class_id' => $class
            ->id, 'section_id' => $section
            ?->id, 'admission_date' => '2026-06-01']);
        $guardian = Guardian::updateOrCreate(['branch_id' => $branch->id, 'phone' => '8888888888'], ['first_name' => 'Demo Parent', 'relation' => 'Parent']);
        $student->guardians()->syncWithoutDetaching([$guardian->id => ['is_primary' => true]]);
    }

    /** Apply least-privilege defaults to the standard tenant roles. */
    private function seedBuiltInRolePermissions(array $permissions): void
    {
        $allPermissions = array_keys($permissions);
        $rolePermissions = [
            'administrator' => $allPermissions,
            'staff' => [
                'students.view',
                'students.create',
                'students.edit',
                'students.status',
                'students.enroll',
                'admissions.view',
                'admissions.manage',
                'attendance.view',
                'academic.view',
                'fees.view',
                'notices.view',
                'services.view',
            ],
            'teacher' => [
                'students.view',
                'attendance.view',
                'attendance.mark',
                'attendance.edit',
                'academic.view',
                'results.view',
                'results.record',
                'results.edit',
                'examinations.view',
                'timetable.view',
                'notices.view',
            ],
            'fee_staff' => [
                'students.view',
                'fees.view',
                'fees.assign',
                'payments.view',
                'payments.collect',
                'payments.edit',
                'payments.refund',
                'payments.cancel_receipt',
                'reconciliation.view',
                'reconciliation.reconcile',
                'reconciliation.close',
                'reports.view',
                'reports.export',
            ],
            'student' => ['portal.view'],
            'parent' => ['portal.view'],
        ];

        foreach ($rolePermissions as $roleName => $permissionNames) {
            $role = Role::query()
                ->whereNull('tenant_id')
                ->where('name', $roleName)
                ->first();

            if (! $role) {
                continue;
            }

            $role->permissions()->sync(
                Permission::query()
                    ->whereIn('name', $permissionNames)
                    ->pluck('id')
                    ->all(),
            );
        }
    }
}
