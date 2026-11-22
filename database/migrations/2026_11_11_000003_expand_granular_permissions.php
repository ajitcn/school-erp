<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class () extends Migration {
    /** Add action-level permissions and carry existing role access forward. */
    public function up(): void
    {
        $permissions = $this->permissions();
        $now = now();

        foreach ($permissions as $name => $label) {
            if (! DB::table('permissions')->where('name', $name)->exists()) {
                DB::table('permissions')->insert([
                    'name' => $name,
                    'label' => $label,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $legacyGrants = $this->legacyGrants();
        $permissionIds = DB::table('permissions')->pluck('id', 'name');

        foreach ($legacyGrants as $legacyName => $newNames) {
            $legacyPermissionId = $permissionIds->get($legacyName);

            if (! $legacyPermissionId) {
                continue;
            }

            $roleIds = DB::table('role_permission')
                ->where('permission_id', $legacyPermissionId)
                ->pluck('role_id');

            foreach ($roleIds as $roleId) {
                $assignments = collect($newNames)
                    ->map(fn (string $name): ?array => $permissionIds->get($name)
                        ? [
                            'role_id' => $roleId,
                            'permission_id' => $permissionIds->get($name),
                        ]
                        : null)
                    ->filter()
                    ->values()
                    ->all();

                if ($assignments) {
                    DB::table('role_permission')->insertOrIgnore($assignments);
                }
            }
        }

        DB::table('permissions')
            ->whereIn('name', ['students.manage', 'expenses.manage', 'settings.manage'])
            ->delete();
    }

    /** Restore legacy permission grants without widening roles that were customized after migration. */
    public function down(): void
    {
        $legacyGrants = $this->legacyGrants();
        $now = now();

        foreach (array_keys($legacyGrants) as $legacyName) {
            if (! DB::table('permissions')->where('name', $legacyName)->exists()) {
                DB::table('permissions')->insert([
                    'name' => $legacyName,
                    'label' => str($legacyName)->replace('.', ' ')->headline()->toString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        $permissionIds = DB::table('permissions')->pluck('id', 'name');

        foreach ($legacyGrants as $legacyName => $newNames) {
            $newIds = collect($newNames)->map(fn (string $name) => $permissionIds->get($name))->filter()->values();
            $legacyId = $permissionIds->get($legacyName);

            if ($newIds->isEmpty() || ! $legacyId) {
                continue;
            }

            $fullyAssignedRoles = DB::table('role_permission')
                ->whereIn('permission_id', $newIds)
                ->groupBy('role_id')
                ->havingRaw('COUNT(DISTINCT permission_id) = ?', [$newIds->count()])
                ->pluck('role_id');
            $legacyAssignments = $fullyAssignedRoles
                ->map(fn (int $roleId): array => ['role_id' => $roleId, 'permission_id' => $legacyId])
                ->all();

            if ($legacyAssignments) {
                DB::table('role_permission')->insertOrIgnore($legacyAssignments);
            }
        }
    }

    /** Return the application permissions that can be assigned to tenant roles. */
    private function permissions(): array
    {
        return [
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
    }

    /** Expand old broad permissions into equivalent action-level grants. */
    private function legacyGrants(): array
    {
        return [
            'students.manage' => [
                'students.view', 'students.create', 'students.edit', 'students.status', 'students.enroll',
                'students.import', 'admissions.view', 'admissions.manage',
            ],
            'attendance.mark' => ['attendance.view', 'attendance.edit'],
            'payments.collect' => [
                'payments.view', 'payments.edit', 'payments.refund', 'payments.cancel_receipt',
                'reconciliation.view', 'reconciliation.reconcile', 'reconciliation.close',
            ],
            'expenses.manage' => [
                'expenses.view', 'expenses.create', 'expenses.edit', 'expenses.categories.manage',
            ],
            'reports.view' => ['reports.export'],
            'settings.manage' => [
                'attendance.approve', 'expenses.approve', 'payments.approve_adjustments', 'payments.approve_refunds',
                'payments.approve_receipts', 'fees.view', 'fees.assign', 'fees.plan.create', 'fees.plan.edit',
                'users.view', 'users.manage', 'roles.view', 'roles.manage', 'notices.view', 'notices.manage',
                'notices.publish', 'notifications.view', 'notifications.send', 'notifications.templates.manage',
                'setup.view', 'setup.manage', 'academic.view', 'academic.create', 'academic.edit', 'academic.delete',
                'results.view', 'results.record', 'results.edit', 'results.delete', 'examinations.view',
                'examinations.create', 'examinations.edit', 'examinations.publish', 'timetable.view',
                'timetable.create', 'timetable.edit', 'services.view', 'services.manage', 'integrations.view',
                'integrations.manage', 'workflow.view',
            ],
        ];
    }
};
