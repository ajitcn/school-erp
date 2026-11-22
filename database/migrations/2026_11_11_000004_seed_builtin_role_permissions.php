<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /** Initialize shared built-in roles with their standard tenant permissions. */
    public function up(): void
    {
        $now = now();
        $roleLabels = [
            'super_admin' => 'Super Admin',
            'administrator' => 'School/College Administrator',
            'staff' => 'Staff',
            'teacher' => 'Teacher',
            'fee_staff' => 'Fee Collection Staff',
            'student' => 'Student',
            'parent' => 'Parent',
        ];

        foreach ($roleLabels as $name => $label) {
            $role = DB::table('roles')->whereNull('tenant_id')->where('name', $name)->first();

            if (! $role) {
                DB::table('roles')->insert([
                    'tenant_id' => null,
                    'name' => $name,
                    'label' => $label,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'name');
        $allPermissionIds = $permissionIds->values()->all();
        $rolePermissions = [
            'super_admin' => $allPermissionIds,
            'administrator' => $allPermissionIds,
            'staff' => $this->ids($permissionIds, [
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
            ]),
            'teacher' => $this->ids($permissionIds, [
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
            ]),
            'fee_staff' => $this->ids($permissionIds, [
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
            ]),
            'student' => $this->ids($permissionIds, ['portal.view']),
            'parent' => $this->ids($permissionIds, ['portal.view']),
        ];

        foreach ($rolePermissions as $roleName => $ids) {
            $roleId = DB::table('roles')
                ->whereNull('tenant_id')
                ->where('name', $roleName)
                ->value('id');

            DB::table('role_permission')->where('role_id', $roleId)->delete();

            if ($ids) {
                DB::table('role_permission')->insert(
                    collect($ids)
                        ->unique()
                        ->map(fn (int $permissionId): array => [
                            'role_id' => $roleId,
                            'permission_id' => $permissionId,
                        ])
                        ->all(),
                );
            }
        }
    }

    /** Keep the initial permission assignment when rolling back this data migration. */
    public function down(): void
    {
        // Built-in roles may have been assigned to users after this migration.
    }

    /** Resolve a role's permission names to ids while ignoring absent optional permissions. */
    private function ids($permissionIds, array $names): array
    {
        return collect($names)
            ->map(fn (string $name): ?int => $permissionIds->get($name))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }
};
