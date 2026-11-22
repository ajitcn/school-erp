<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Rejects requests that lack the required permission in the active tenant. */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        if ($permission === 'granular') {
            $permission = $this->permissionForRoute($request);
        }

        abort_unless($request->user()?->hasPermission($permission), 403);

        return $next($request);
    }

    /** Resolve a route to the narrow capability required for its action. */
    private function permissionForRoute(Request $request): string
    {
        $routeName = (string) $request->route()?->getName();
        $method = $request->method();

        $rules = [
            'admin.invitations' => 'users.view',
            'tenant.invite' => 'users.manage',
            'admin.users.' => $routeName === 'admin.users.index' ? 'users.view' : 'users.manage',
            'admin.roles.' => $routeName === 'admin.roles.index' ? 'roles.view' : 'roles.manage',
            'students.' => match (true) {
                str_contains($routeName, 'status') => 'students.status',
                str_contains($routeName, 'enrollments') => 'students.enroll',
                $method === 'GET' && str_contains($routeName, '.create') => 'students.create',
                str_contains($routeName, '.edit') => 'students.edit',
                $method === 'POST' => 'students.create',
                in_array($method, ['PUT', 'PATCH'], true) => 'students.edit',
                default => 'students.view',
            },
            'students.certificates.' => $method === 'POST' ? 'students.edit' : 'students.view',
            'admissions.' => in_array($method, ['PUT', 'PATCH', 'POST'], true) ? 'admissions.manage' : 'admissions.view',
            'imports.' => 'students.import',
            'attendance.' => match (true) {
                str_contains($routeName, 'corrections') => 'attendance.edit',
                $method === 'POST' => 'attendance.mark',
                default => 'attendance.view',
            },
            'reconciliation.' => match (true) {
                str_contains($routeName, 'closing') => 'reconciliation.close',
                $method === 'POST' => 'reconciliation.reconcile',
                default => 'reconciliation.view',
            },
            'students.fees.' => match (true) {
                str_contains($routeName, 'collect-modal') => 'payments.collect',
                in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) => 'fees.assign',
                str_contains($routeName, 'create') || str_contains($routeName, 'setup-modal') => 'fees.assign',
                default => 'fees.view',
            },
            'fees.assignments.' => $method === 'GET' ? 'fees.view' : 'fees.assign',
            'fees.structures.' => match (true) {
                str_contains($routeName, 'store') => 'fees.plan.create',
                str_contains($routeName, 'edit') || str_contains($routeName, 'update') || str_contains($routeName, 'deactivate') => 'fees.plan.edit',
                default => 'fees.view',
            },
            'payments.' => match (true) {
                str_contains($routeName, 'corrections') => 'payments.edit',
                str_contains($routeName, 'refunds') => 'payments.refund',
                str_contains($routeName, 'receipt-cancellations') => 'payments.cancel_receipt',
                str_contains($routeName, 'receipt') => 'payments.view',
                $method === 'POST' => 'payments.collect',
                default => 'payments.view',
            },
            'expenses.' => match (true) {
                str_contains($routeName, 'categories') => in_array($method, ['GET'], true) ? 'expenses.view' : 'expenses.categories.manage',
                str_contains($routeName, '.edit') => 'expenses.edit',
                in_array($method, ['POST'], true) => 'expenses.create',
                in_array($method, ['PUT', 'PATCH'], true) => 'expenses.edit',
                default => 'expenses.view',
            },
            'notices.' => str_contains($routeName, 'publish') ? 'notices.publish' : ($method === 'POST' ? 'notices.manage' : 'notices.view'),
            'admin.notifications.send' => 'notifications.view',
            'admin.notifications.send.store' => 'notifications.send',
            'setup.' => $method === 'GET' ? 'setup.view' : 'setup.manage',
            'academic.results.' => match (true) {
                str_contains($routeName, 'import') => 'results.record',
                str_contains($routeName, 'destroy') => 'results.delete',
                str_contains($routeName, 'edit') || str_contains($routeName, 'update') => 'results.edit',
                str_contains($routeName, 'store') || str_contains($routeName, 'search') => 'results.record',
                default => 'results.view',
            },
            'academic.students.search' => 'results.record',
            'academic.examinations' => match (true) {
                str_contains($routeName, 'publish') => 'examinations.publish',
                str_contains($routeName, 'store') => 'examinations.create',
                str_contains($routeName, 'update') => 'examinations.edit',
                default => 'examinations.view',
            },
            'academic.timetable' => match (true) {
                str_contains($routeName, 'store') => 'timetable.create',
                str_contains($routeName, 'update') => 'timetable.edit',
                default => 'timetable.view',
            },
            'academic.teachers' => $method === 'POST' ? 'academic.create' : 'academic.view',
            'academic.subjects' => match (true) {
                str_contains($routeName, 'store') => 'academic.create',
                in_array($method, ['PUT', 'PATCH', 'DELETE'], true) || str_contains($routeName, 'toggle') => 'academic.edit',
                default => 'academic.view',
            },
            'academic.' => match (true) {
                str_contains($routeName, '.edit') => 'academic.edit',
                $method === 'POST' => 'academic.create',
                in_array($method, ['PUT', 'PATCH', 'DELETE'], true) => 'academic.edit',
                default => 'academic.view',
            },
            'services.' => $method === 'GET' ? 'services.view' : 'services.manage',
            'admin.api-tokens' => 'integrations.view',
            'admin.api-tokens.' => 'integrations.manage',
            'admin.notification-templates' => 'notifications.view',
            'admin.notification-templates.' => 'notifications.templates.manage',
            'admin.biometric-' => 'integrations.manage',
            'reports.' => str_contains($routeName, 'export') ? 'reports.export' : 'reports.view',
            'portal.' => 'portal.view',
            'approvals.index' => 'workflow.view',
            'workflow.attendance' => 'attendance.approve',
            'workflow.expense' => 'expenses.approve',
            'workflow.refund' => 'payments.approve_refunds',
            'workflow.receipt' => 'payments.approve_receipts',
            'workflow.payment' => 'payments.approve_adjustments',
        ];

        uksort($rules, fn (string $left, string $right): int => strlen($right) <=> strlen($left));

        foreach ($rules as $routePrefix => $requiredPermission) {
            if ($routeName === $routePrefix || str_starts_with($routeName, $routePrefix)) {
                return $requiredPermission;
            }
        }

        return '__unmapped_route__';
    }
}
