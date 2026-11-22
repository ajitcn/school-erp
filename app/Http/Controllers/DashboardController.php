<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Builds the active tenant dashboard and its summary metrics.
 */
class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();

        if ($user->hasRole(['student', 'parent'])) {
            return redirect()->route('portal.dashboard');
        }

        $canManageStudents = $user->hasPermission('students.view');
        $canCollectPayments = $user->hasPermission('payments.collect');
        $canManageExpenses = $user->hasPermission('expenses.view');
        $canViewReports = $user->hasPermission('reports.view');
        $canManageSettings = $user->hasPermission('setup.manage');
        $canReviewApprovals = $user->hasPermission('workflow.view')
            || $user->hasPermission('attendance.approve')
            || $user->hasPermission('expenses.approve')
            || $user->hasPermission('payments.approve_adjustments')
            || $user->hasPermission('payments.approve_refunds')
            || $user->hasPermission('payments.approve_receipts');

        $canViewStudents = $canManageStudents;
        $canViewAttendance = $user->hasPermission('attendance.view') || $canViewReports;
        $canViewFinance = $user->hasPermission('payments.view') || $canCollectPayments || $canViewReports;
        $canViewExpenses = $canManageExpenses || $canViewReports;
        $branchIds = $this->authorizedBranchIds($user);
        $tenantId = app(TenantContext::class)->id();
        $pendingApprovals = $canReviewApprovals
            ? $this->recentPendingApprovals($branchIds, $tenantId)
            : collect();
        $today = today();
        $now = now();
        $monthStart = $today->copy()->startOfMonth();
        $weekStart = $today->copy()->subDays(6);
        $enrollmentTrendStart = $today->copy()->startOfMonth()->subMonths(5);

        $students = $this->scopeBranches(Student::query(), $branchIds);
        $payments = $this->scopeBranches(Payment::query(), $branchIds);
        $expenses = $this->scopeBranches(Expense::query(), $branchIds);
        $attendance = $this->scopeBranches(Attendance::query(), $branchIds);

        $activeStudents = 0;
        $studentsAddedThisMonth = 0;
        $studentStatusCounts = collect();
        $recentStudents = collect();
        $monthlyEnrollmentCounts = collect();

        if ($canViewStudents) {
            $activeStudents = (clone $students)
                ->where('status', 'active')
                ->count();

            $studentsAddedThisMonth = (clone $students)
                ->whereBetween('created_at', [$monthStart, $now])
                ->count();

            $studentStatusCounts = (clone $students)
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            $recentStudents = (clone $students)
                ->with('branch')
                ->latest()
                ->limit(6)
                ->get();

            $monthlyEnrollmentCounts = (clone $students)
                ->whereBetween('created_at', [$enrollmentTrendStart, $now])
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as report_month, COUNT(*) as total")
                ->groupByRaw("DATE_FORMAT(created_at, '%Y-%m')")
                ->pluck('total', 'report_month');
        }

        $enrollmentTrend = collect(range(5, 0))
            ->map(function (int $monthsAgo) use ($today, $monthlyEnrollmentCounts): array {
                $month = $today->copy()->startOfMonth()->subMonths($monthsAgo);

                return [
                    'label' => $month->format('M y'),
                    'date' => $month->format('F Y'),
                    'count' => (int) $monthlyEnrollmentCounts->get($month->format('Y-m'), 0),
                ];
            });

        $teacherCount = 0;
        $activeBranchCount = 0;

        if ($canManageSettings && $tenantId) {
            $teachers = User::query()
                ->where('is_active', true)
                ->whereHas('tenants', function (Builder $query) use ($tenantId) {
                    $query->where('tenants.id', $tenantId)
                        ->where('tenant_user.is_active', true);
                })
                ->whereHas('roles', fn (Builder $query) => $query->where('name', 'teacher'));

            $teacherCount = $this->scopeBranches($teachers, $branchIds, 'branch_id')->count();

            $activeBranches = Branch::query()->where('is_active', true);
            $activeBranchCount = $this->scopeBranches($activeBranches, $branchIds)->count();
        }

        $overdueAmount = 0;

        if ($canViewFinance) {
            $assignmentPayments = DB::table('payment_allocations')
                ->select('student_fee_assignment_id')
                ->selectRaw('SUM(amount) as paid_amount')
                ->groupBy('student_fee_assignment_id');

            $overdueBalances = DB::table('student_fee_assignments as assignments')
                ->join('fee_components as components', 'components.id', '=', 'assignments.fee_component_id')
                ->join('students', 'students.id', '=', 'assignments.student_id')
                ->leftJoinSub($assignmentPayments, 'assignment_payments', function ($join) {
                    $join->on('assignment_payments.student_fee_assignment_id', '=', 'assignments.id');
                })
                ->whereIn('assignments.status', ['open', 'partial'])
                ->whereNotNull('components.due_date')
                ->whereDate('components.due_date', '<', $today)
                ->when($tenantId, fn ($query) => $query->where('assignments.tenant_id', $tenantId))
                ->when($branchIds !== null, fn ($query) => $query->whereIn('students.branch_id', $branchIds))
                ->selectRaw('assignments.amount - assignments.discount + assignments.late_fee - COALESCE(assignment_payments.paid_amount, 0) as balance')
                ->get();

            $overdueAmount = $overdueBalances->sum(fn (object $assignment): float => max(0, (float) $assignment->balance));
        }

        $paymentTodayCount = 0;
        $paymentsTodayAmount = 0;
        $paymentsMonthAmount = 0;
        $recentPayments = collect();
        $dailyCollections = collect();

        if ($canViewFinance) {
            $completedPayments = (clone $payments)->where('status', 'completed');

            $paymentTodayCount = (clone $completedPayments)
                ->whereDate('paid_at', $today)
                ->count();

            $paymentsTodayAmount = (clone $completedPayments)
                ->whereDate('paid_at', $today)
                ->sum('amount');

            $paymentsMonthAmount = (clone $completedPayments)
                ->whereBetween('paid_at', [$monthStart, $now])
                ->sum('amount');

            $recentPayments = (clone $completedPayments)
                ->with('student')
                ->latest('paid_at')
                ->limit(6)
                ->get();

            $dailyCollections = (clone $completedPayments)
                ->whereDate('paid_at', '>=', $weekStart)
                ->whereDate('paid_at', '<=', $today)
                ->selectRaw('DATE(paid_at) as report_date, SUM(amount) as total')
                ->groupByRaw('DATE(paid_at)')
                ->pluck('total', 'report_date');
        }

        $expensesTodayAmount = 0;
        $expensesTodayCount = 0;
        $expensesMonthAmount = 0;
        $pendingExpenses = collect();
        $pendingExpenseCount = 0;
        $pendingExpenseAmount = 0;
        $recentExpenses = collect();
        $dailyExpenses = collect();

        if ($canViewExpenses) {
            $expensesTodayAmount = (clone $expenses)
                ->whereDate('expense_date', $today)
                ->sum('amount');

            $expensesTodayCount = (clone $expenses)
                ->whereDate('expense_date', $today)
                ->count();

            $expensesMonthAmount = (clone $expenses)
                ->whereBetween('expense_date', [$monthStart, $today])
                ->sum('amount');

            $pendingExpensesQuery = (clone $expenses)->where('status', 'pending');
            $pendingExpenseCount = (clone $pendingExpensesQuery)->count();
            $pendingExpenseAmount = (clone $pendingExpensesQuery)->sum('amount');

            if ($canManageExpenses) {
                $pendingExpenses = (clone $pendingExpensesQuery)
                    ->with('category')
                    ->latest('expense_date')
                    ->limit(5)
                    ->get();
            }

            $recentExpenses = (clone $expenses)
                ->with('category')
                ->latest('expense_date')
                ->limit(6)
                ->get();

            $dailyExpenses = (clone $expenses)
                ->whereDate('expense_date', '>=', $weekStart)
                ->whereDate('expense_date', '<=', $today)
                ->selectRaw('DATE(expense_date) as report_date, SUM(amount) as total')
                ->groupByRaw('DATE(expense_date)')
                ->pluck('total', 'report_date');
        }

        $attendanceCounts = collect();
        $recentAbsences = collect();
        $attendanceMarkedCount = 0;
        $attendanceRate = 0;
        $dailyAttendanceCounts = collect();

        if ($canViewAttendance) {
            $todayAttendance = (clone $attendance)
                ->whereDate('attendance_date', $today)
                ->where('session', 'full_day');

            $attendanceCounts = (clone $todayAttendance)
                ->select('status', DB::raw('COUNT(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status');

            $attendanceMarkedCount = (int) $attendanceCounts->sum();
            $presentCount = (int) $attendanceCounts->get('present', 0);

            if ($attendanceMarkedCount > 0) {
                $attendanceRate = (int) round(($presentCount / $attendanceMarkedCount) * 100);
            }

            if ($canManageStudents) {
                $recentAbsences = (clone $todayAttendance)
                    ->where('status', 'absent')
                    ->with('student.branch')
                    ->latest()
                    ->limit(6)
                    ->get();
            }

            $dailyAttendanceCounts = (clone $attendance)
                ->whereDate('attendance_date', '>=', $weekStart)
                ->whereDate('attendance_date', '<=', $today)
                ->where('session', 'full_day')
                ->selectRaw("DATE(attendance_date) as report_date, SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count, COUNT(*) as marked_count")
                ->groupByRaw('DATE(attendance_date)')
                ->get()
                ->keyBy('report_date');
        }

        $attendanceTrend = collect(range(6, 0))
            ->map(function (int $daysAgo) use ($today, $dailyAttendanceCounts): array {
                $date = $today->copy()->subDays($daysAgo);
                $counts = $dailyAttendanceCounts->get($date->toDateString());
                $markedCount = (int) ($counts->marked_count ?? 0);
                $presentCount = (int) ($counts->present_count ?? 0);

                return [
                    'label' => $date->format('D'),
                    'date' => $date->format('d M'),
                    'rate' => $markedCount > 0 ? (int) round(($presentCount / $markedCount) * 100) : null,
                    'present' => $presentCount,
                    'marked' => $markedCount,
                ];
            });

        $collectionTrend = collect(range(6, 0))
            ->map(function (int $daysAgo) use ($today, $dailyCollections, $dailyExpenses) {
                $date = $today->copy()->subDays($daysAgo);
                $dateKey = $date->toDateString();

                return [
                    'label' => $date->format('D'),
                    'date' => $date->format('d M'),
                    'collections' => (float) $dailyCollections->get($dateKey, 0),
                    'expenses' => (float) $dailyExpenses->get($dateKey, 0),
                ];
            });

        return view('dashboard', [
            'activeStudents' => $activeStudents,
            'studentsAddedThisMonth' => $studentsAddedThisMonth,
            'studentStatusCounts' => $studentStatusCounts,
            'recentStudents' => $recentStudents,
            'teacherCount' => $teacherCount,
            'activeBranchCount' => $activeBranchCount,
            'overdueAmount' => $overdueAmount,
            'paymentTodayCount' => $paymentTodayCount,
            'paymentsTodayAmount' => $paymentsTodayAmount,
            'paymentsMonthAmount' => $paymentsMonthAmount,
            'recentPayments' => $recentPayments,
            'expensesTodayAmount' => $expensesTodayAmount,
            'expensesTodayCount' => $expensesTodayCount,
            'expensesMonthAmount' => $expensesMonthAmount,
            'pendingExpenseCount' => $pendingExpenseCount,
            'pendingExpenseAmount' => $pendingExpenseAmount,
            'pendingExpenses' => $pendingExpenses,
            'recentExpenses' => $recentExpenses,
            'attendanceCounts' => $attendanceCounts,
            'attendanceMarkedCount' => $attendanceMarkedCount,
            'attendanceRate' => $attendanceRate,
            'recentAbsences' => $recentAbsences,
            'collectionTrend' => $collectionTrend,
            'enrollmentTrend' => $enrollmentTrend,
            'attendanceTrend' => $attendanceTrend,
            'dashboardDate' => $today,
            'canManageStudents' => $canManageStudents,
            'canViewStudents' => $canViewStudents,
            'canViewAttendance' => $canViewAttendance,
            'canViewFinance' => $canViewFinance,
            'canViewExpenses' => $canViewExpenses,
            'canManageExpenses' => $canManageExpenses,
            'canManageSettings' => $canManageSettings,
            'canViewReports' => $canViewReports,
            'canCollectPayments' => $canCollectPayments,
            'canReviewApprovals' => $canReviewApprovals,
            'pendingApprovals' => $pendingApprovals,
            'quickActions' => $this->quickActions(
                $user->hasPermission('students.create'),
                $canCollectPayments,
                $user->hasPermission('reconciliation.view'),
                $canManageExpenses,
                $canViewReports,
                $canManageSettings
            ),
        ]);
    }

    /** Collect recent requests from each approval workflow within the user's branch scope. */
    private function recentPendingApprovals(?Collection $branchIds, ?int $tenantId): Collection
    {
        $scope = fn ($query, string $tenantColumn, string $branchColumn) => $query
            ->when($tenantId, fn ($query) => $query->where($tenantColumn, $tenantId))
            ->when($branchIds !== null, fn ($query) => $query->whereIn($branchColumn, $branchIds));

        $expenseRequests = $scope(
            DB::table('expense_approvals as requests')
                ->join('expenses', 'expenses.id', '=', 'requests.expense_id')
                ->where('requests.status', 'pending'),
            'requests.tenant_id',
            'expenses.branch_id'
        )->get([
            DB::raw("'Expense' as kind"),
            'requests.id',
            'expenses.voucher_number as reference',
            'expenses.description as summary',
            'expenses.amount',
            'requests.created_at',
            DB::raw('(select users.name from users where users.id = expenses.entered_by) as requester_name'),
        ]);

        $attendanceRequests = $scope(
            DB::table('attendance_corrections as requests')
                ->join('attendance', 'attendance.id', '=', 'requests.attendance_id')
                ->leftJoin('users as requesters', 'requesters.id', '=', 'requests.requested_by')
                ->where('requests.status', 'pending'),
            'requests.tenant_id',
            'attendance.branch_id'
        )->get([
            DB::raw("'Attendance' as kind"),
            'requests.id',
            'attendance.attendance_date as reference',
            'requests.reason as summary',
            DB::raw('NULL as amount'),
            'requests.created_at',
            'requesters.name as requester_name',
        ]);

        $refundRequests = $scope(
            DB::table('refund_requests as requests')
                ->join('payments', 'payments.id', '=', 'requests.payment_id')
                ->leftJoin('users as requesters', 'requesters.id', '=', 'requests.requested_by')
                ->where('requests.status', 'pending'),
            'requests.tenant_id',
            'payments.branch_id'
        )->get([
            DB::raw("'Refund' as kind"),
            'requests.id',
            'payments.transaction_reference as reference',
            'requests.reason as summary',
            'requests.amount',
            'requests.created_at',
            'requesters.name as requester_name',
        ]);

        $paymentRequests = $scope(
            DB::table('payment_corrections as requests')
                ->join('payments', 'payments.id', '=', 'requests.payment_id')
                ->leftJoin('users as requesters', 'requesters.id', '=', 'requests.requested_by')
                ->where('requests.status', 'pending'),
            'requests.tenant_id',
            'payments.branch_id'
        )->get([
            DB::raw("'Payment correction' as kind"),
            'requests.id',
            'payments.transaction_reference as reference',
            'requests.reason as summary',
            DB::raw("JSON_UNQUOTE(JSON_EXTRACT(requests.proposed_changes, '$.amount')) as amount"),
            'requests.created_at',
            'requesters.name as requester_name',
        ]);

        $receiptRequests = $scope(
            DB::table('receipt_cancellations as requests')
                ->join('receipts', 'receipts.id', '=', 'requests.receipt_id')
                ->join('payments', 'payments.id', '=', 'receipts.payment_id')
                ->leftJoin('users as requesters', 'requesters.id', '=', 'requests.requested_by')
                ->where('requests.status', 'pending'),
            'requests.tenant_id',
            'payments.branch_id'
        )->get([
            DB::raw("'Receipt cancellation' as kind"),
            'requests.id',
            'receipts.receipt_number as reference',
            'requests.reason as summary',
            'payments.amount',
            'requests.created_at',
            'requesters.name as requester_name',
        ]);

        return $expenseRequests
            ->concat($attendanceRequests)
            ->concat($refundRequests)
            ->concat($paymentRequests)
            ->concat($receiptRequests)
            ->sortByDesc('created_at')
            ->take(10)
            ->values();
    }

    private function authorizedBranchIds(User $user): ?Collection
    {
        return $user->accessibleBranchIds();
    }

    private function scopeBranches(
        Builder $query,
        ?Collection $branchIds,
        string $column = 'branch_id'
    ): Builder {
        if ($branchIds === null) {
            return $query;
        }

        if ($branchIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($column, $branchIds);
    }

    private function quickActions(
        bool $canCreateStudents,
        bool $canCollectPayments,
        bool $canViewReconciliation,
        bool $canManageExpenses,
        bool $canViewReports,
        bool $canManageSettings
    ): array {
        $actions = [];

        if ($canCreateStudents && Route::has('students.create')) {
            $actions[] = [
                'label' => 'Add student',
                'description' => 'Create a student record',
                'href' => route('students.create'),
                'icon' => 'fa-user-plus',
                'tone' => 'primary',
            ];
        }

        if ($canViewReconciliation && Route::has('reconciliation.index')) {
            $actions[] = [
                'label' => 'Review collections',
                'description' => 'Check cashier closings',
                'href' => route('reconciliation.index'),
                'icon' => 'fa-money-bill-transfer',
                'tone' => 'success',
            ];
        }

        if ($canManageExpenses && Route::has('expenses.index')) {
            $actions[] = [
                'label' => 'View expenses',
                'description' => 'Review recorded expenses',
                'href' => route('expenses.index'),
                'icon' => 'fa-wallet',
                'tone' => 'warning',
            ];
        }

        if ($canViewReports && Route::has('reports.financial')) {
            $actions[] = [
                'label' => 'Financial report',
                'description' => 'Review collections and expenses',
                'href' => route('reports.financial'),
                'icon' => 'fa-chart-column',
                'tone' => 'info',
            ];
        }

        if ($canViewReports && Route::has('reports.attendance')) {
            $actions[] = [
                'label' => 'Attendance report',
                'description' => 'Review attendance records',
                'href' => route('reports.attendance'),
                'icon' => 'fa-calendar-check',
                'tone' => 'purple',
            ];
        }

        if ($canManageSettings && Route::has('setup.index')) {
            $actions[] = [
                'label' => 'Manage setup',
                'description' => 'Branches and academic years',
                'href' => route('setup.index'),
                'icon' => 'fa-gears',
                'tone' => 'slate',
            ];
        }

        return $actions;
    }
}
