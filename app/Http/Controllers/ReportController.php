<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PaymentTender;
use App\Models\Refund;
use App\Models\StudentFeeAssignment;
use App\Services\SpreadsheetWriter;
use App\Services\TabularPdfWriter;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Builds permission-scoped academic, attendance, fee, and finance reports.
 */
class ReportController extends Controller
{
    /** Build a branch-scoped finance summary with tender-aware collection totals. */
    public function financial(Request $request)
    {
        $data = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'payment_mode' => 'nullable|in:cash,upi,card,bank_transfer,online',
            'period' => 'nullable|in:day,week,month,quarter,year,financial_year',
            'branch_id' => 'nullable|integer|min:1',
        ]);
        if (empty($data['from_date']) && empty($data['to_date']) && empty($data['period'])) {
            $data['period'] = 'financial_year';
        }
        $data = $this->applyPeriod($data);
        $accessibleBranches = $request->user()->accessibleBranchIds();
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        if ($branchId) {
            abort_unless(
                $request->user()->canAccessBranch($branchId) && Branch::query()->whereKey($branchId)->exists(),
                403,
            );
            $data['branch_id'] = $branchId;
        }
        $branches = $branchId ? collect([$branchId]) : $accessibleBranches;
        $branchOptions = Branch::query()
            ->where('is_active', true)
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('id', $accessibleBranches))
            ->orderBy('name')
            ->get();
        $branchName = $branchId
            ? ($branchOptions->firstWhere('id', $branchId)?->name ?? 'Selected branch')
            : 'All permitted branches';
        $paymentBaseQuery = fn ($query) => $query
            ->where('status', 'completed')
            ->when($branches !== null, fn ($query) => $query->whereIn('branch_id', $branches))
            ->when($data['from_date'] ?? null, fn ($query, $date) => $query->whereDate('paid_at', '>=', $date))
            ->when($data['to_date'] ?? null, fn ($query, $date) => $query->whereDate('paid_at', '<=', $date));

        $paymentQuery = $paymentBaseQuery(Payment::query())
            ->when($data['payment_mode'] ?? null, fn ($query, $mode) => $query->where(function ($query) use ($mode) {
                $query->where(fn ($legacy) => $legacy->where('payment_mode', $mode)->whereDoesntHave('paymentTenders'))
                    ->orWhereHas('paymentTenders', fn ($tenders) => $tenders->where('payment_mode', $mode));
            }));

        $payments = (clone $paymentQuery)
            ->with(['student', 'paymentTenders', 'receipt'])
            ->latest('paid_at')
            ->paginate(25)
            ->through(function (Payment $payment) use ($data) {
                $mode = $data['payment_mode'] ?? null;
                $reportAmount = $mode && $payment->paymentTenders->isNotEmpty()
                    ? $payment->paymentTenders
                        ->where('payment_mode', $mode)
                        ->sum(fn (PaymentTender $tender) => (float) $tender->amount)
                    : (float) $payment->amount;

                $payment->setAttribute('report_amount', $reportAmount);

                return $payment;
            })
            ->withQueryString();
        $paymentCount = (clone $paymentQuery)->count();

        $mode = $data['payment_mode'] ?? null;
        if ($mode) {
            $tenderTotal = PaymentTender::query()
                ->where('payment_mode', $mode)
                ->whereHas('payment', fn ($query) => $paymentBaseQuery($query))
                ->sum('amount');
            $legacyTotal = $paymentBaseQuery(Payment::query())
                ->where('payment_mode', $mode)
                ->whereDoesntHave('paymentTenders')
                ->sum('amount');
            $totalCollected = (float) $tenderTotal + (float) $legacyTotal;
        } else {
            $totalCollected = (float) $paymentBaseQuery(Payment::query())->sum('amount');
        }

        $refundQuery = Refund::query()
            ->where('status', 'processed')
            ->when($data['from_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '>=', $date))
            ->when($data['to_date'] ?? null, fn ($query, $date) => $query->whereDate('created_at', '<=', $date))
            ->whereHas('payment', fn ($query) => $query
                ->when($branches !== null, fn ($query) => $query->whereIn('branch_id', $branches))
                ->when($mode, fn ($query) => $query->where(function ($query) use ($mode) {
                    $query->where(fn ($legacy) => $legacy->where('payment_mode', $mode)->whereDoesntHave('paymentTenders'))
                        ->orWhereHas('paymentTenders', fn ($tenders) => $tenders->where('payment_mode', $mode));
                })));
        $totalRefunded = (float) $refundQuery->sum('amount');

        $tenderModeTotals = PaymentTender::query()
            ->whereHas('payment', fn ($query) => $paymentBaseQuery($query))
            ->when($mode, fn ($query) => $query->where('payment_mode', $mode))
            ->selectRaw('payment_mode, SUM(amount) as total')
            ->groupBy('payment_mode')
            ->pluck('total', 'payment_mode');
        $legacyModeTotals = $paymentBaseQuery(Payment::query())
            ->whereDoesntHave('paymentTenders')
            ->when($mode, fn ($query) => $query->where('payment_mode', $mode))
            ->selectRaw('payment_mode, SUM(amount) as total')
            ->groupBy('payment_mode')
            ->pluck('total', 'payment_mode');
        $modeTotals = $tenderModeTotals;
        foreach ($legacyModeTotals as $paymentMode => $total) {
            $modeTotals[$paymentMode] = (float) ($modeTotals[$paymentMode] ?? 0) + (float) $total;
        }

        if ($mode) {
            $staffPayments = Payment::query()
                ->whereDoesntHave('paymentTenders')
                ->where('payment_mode', $mode)
                ->with('collector');
            $staffLegacy = $paymentBaseQuery($staffPayments)->get()
                ->groupBy('collected_by')
                ->map(fn ($items) => [
                    'name' => $items->first()->collector?->name ?? 'Unassigned',
                    'total' => $items->sum(fn (Payment $payment) => (float) $payment->amount),
                ]);
            $staffTenderRows = PaymentTender::query()
                ->where('payment_mode', $mode)
                ->whereHas('payment', fn ($query) => $paymentBaseQuery($query))
                ->with('payment.collector')
                ->get()
                ->groupBy(fn (PaymentTender $tender) => $tender->payment?->collected_by)
                ->map(fn ($items) => [
                    'name' => $items->first()->payment?->collector?->name ?? 'Unassigned',
                    'total' => $items->sum(fn (PaymentTender $tender) => (float) $tender->amount),
                ]);
            $staffTotals = $staffLegacy;
            foreach ($staffTenderRows as $staffId => $summary) {
                $staffTotals[$staffId] = [
                    'name' => $summary['name'],
                    'total' => (float) ($staffTotals[$staffId]['total'] ?? 0) + $summary['total'],
                ];
            }
        } else {
            $staffTotals = $paymentBaseQuery(Payment::query())
                ->with('collector')
                ->get()
                ->groupBy('collected_by')
                ->map(fn ($items) => [
                    'name' => $items->first()->collector?->name ?? 'Unassigned',
                    'total' => $items->sum(fn (Payment $payment) => (float) $payment->amount),
                ]);
        }

        $expenseQuery = Expense::query()
            ->where('status', 'approved')
            ->when($branches !== null, fn ($query) => $query->whereIn('branch_id', $branches))
            ->when($data['from_date'] ?? null, fn ($query, $date) => $query->whereDate('expense_date', '>=', $date))
            ->when($data['to_date'] ?? null, fn ($query, $date) => $query->whereDate('expense_date', '<=', $date));
        $expenses = (float) (clone $expenseQuery)->sum('amount');
        $expenseBreakdown = (clone $expenseQuery)
            ->with('category')
            ->select('expense_category_id')
            ->selectRaw('SUM(amount) as total')
            ->groupBy('expense_category_id')
            ->get()
            ->map(fn (Expense $expense) => [
                'category' => $expense->category?->name ?? 'Uncategorized',
                'total' => (float) $expense->total,
            ])
            ->sortByDesc('total')
            ->values();

        $pendingFees = (float) (StudentFeeAssignment::query()
            ->whereHas('student', fn ($query) => $query
                ->when($branches !== null, fn ($query) => $query->whereIn('branch_id', $branches)))
            ->selectRaw('SUM(GREATEST(amount - discount + late_fee - COALESCE((SELECT SUM(payment_allocations.amount) FROM payment_allocations WHERE payment_allocations.student_fee_assignment_id = student_fee_assignments.id), 0), 0)) as total_balance')
            ->value('total_balance') ?? 0);

        $completedPayments = fn ($query) => $query
            ->where('status', 'completed')
            ->when($branches !== null, fn ($query) => $query->whereIn('branch_id', $branches));
        $allTimeCollections = (float) $completedPayments(Payment::query())->sum('amount');
        $allTimeAllocated = (float) PaymentAllocation::query()
            ->whereHas('payment', fn ($query) => $completedPayments($query))
            ->sum('amount');
        $allTimeRefunded = (float) Refund::query()
            ->where('status', 'processed')
            ->whereHas('payment', fn ($query) => $completedPayments($query))
            ->sum('amount');
        $advanceBalance = max(0, $allTimeCollections - $allTimeAllocated - $allTimeRefunded);
        $netCollected = $totalCollected - $totalRefunded;

        return view('reports.financial', [
            'payments' => $payments,
            'expenses' => $expenses,
            'expenseBreakdown' => $expenseBreakdown,
            'paymentCount' => $paymentCount,
            'd' => $data,
            'branches' => $branchOptions,
            'branchId' => $branchId,
            'branchName' => $branchName,
            'grossCollected' => (float) $totalCollected,
            'collected' => $netCollected,
            'refunded' => $totalRefunded,
            'netAfterExpenses' => $netCollected - $expenses,
            'modeTotals' => $modeTotals,
            'staffTotals' => $staffTotals,
            'pendingFees' => $pendingFees,
            'advanceBalance' => $advanceBalance,
        ]);
    }

    public function attendance(Request $request)
    {
        $data = $request->validate(['from_date' => 'nullable|date', 'to_date' => 'nullable|date|after_or_equal:from_date', 'status' => 'nullable|in:present,absent,late,half_day,leave']);
        $branches = $request->user()->accessibleBranchIds();
        $records = Attendance::query()->with('student')->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
            ->when($data['from_date'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '>=', $date))
            ->when($data['to_date'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '<=', $date))
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->latest('attendance_date')->paginate(25)->withQueryString();

        return view('reports.attendance', ['records' => $records, 'd' => $data]);
    }

    /** Export a report using the same validated period, branch, and payment filters. */
    public function export(Request $request, string $type, SpreadsheetWriter $xlsx, TabularPdfWriter $pdf)
    {
        abort_unless(in_array($type, ['financial', 'attendance', 'fees', 'expenses', 'previous_dues'], true), 404);
        $data = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'payment_mode' => 'nullable|in:cash,upi,card,bank_transfer,online',
            'period' => 'nullable|in:day,week,month,quarter,year,financial_year',
            'branch_id' => 'nullable|integer|min:1',
            'academic_year_id' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|in:present,absent,late,half_day,leave',
            'format' => 'nullable|in:csv,xlsx,pdf',
        ]);
        if ($type === 'financial' && empty($data['from_date']) && empty($data['to_date']) && empty($data['period'])) {
            $data['period'] = 'financial_year';
        }
        $data = $this->applyPeriod($data);
        $format = $data['format'] ?? 'csv';
        $accessibleBranches = $request->user()->accessibleBranchIds();
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        if ($branchId) {
            abort_unless(
                $request->user()->canAccessBranch($branchId) && Branch::query()->whereKey($branchId)->exists(),
                403,
            );
        }
        $branches = $branchId ? collect([$branchId]) : $accessibleBranches;
        if (in_array($type, ['fees', 'previous_dues'], true) && ! empty($data['academic_year_id'])) {
            $allowedYearIds = $type === 'previous_dues'
                ? $this->previousAcademicYearIds($branches)
                : AcademicYear::query()
                    ->when($branches !== null, fn ($query) => $query->whereIn('branch_id', $branches))
                    ->pluck('id')
                    ->all();
            abort_unless(
                in_array((int) $data['academic_year_id'], array_map('intval', $allowedYearIds), true),
                403,
            );
        }
        if ($type === 'financial') {
            $rows = Payment::query()->with(['student', 'paymentTenders', 'refunds' => fn ($q) => $q
                ->where('status', 'processed')
                ->when($data['from_date'] ?? null, fn ($r, $date) => $r
                    ->whereDate('created_at', '>=', $date))
                ->when($data['to_date'] ?? null, fn ($r, $date) => $r
                    ->whereDate('created_at', '<=', $date))])
                ->where('status', 'completed')
                ->when($branches !== null, fn ($q) => $q
                    ->whereIn('branch_id', $branches))
                ->when($data['from_date'] ?? null, fn ($q, $date) => $q->whereDate('paid_at', '>=', $date))
                ->when($data['to_date'] ?? null, fn ($q, $date) => $q->whereDate('paid_at', '<=', $date))
                ->when($data['payment_mode'] ?? null, fn ($q, $mode) => $q->where(function ($query) use ($mode) {
                    $query->where(fn ($legacy) => $legacy->where('payment_mode', $mode)->whereDoesntHave('paymentTenders'))
                        ->orWhereHas('paymentTenders', fn ($tender) => $tender->where('payment_mode', $mode));
                }))->get()
                ->map(function (Payment $payment) use ($data) {
                    $mode = $data['payment_mode'] ?? null;
                    $collectedAmount = $mode && $payment->paymentTenders->isNotEmpty()
                        ? $payment->paymentTenders
                            ->where('payment_mode', $mode)
                            ->sum(fn (PaymentTender $tender) => (float) $tender->amount)
                        : (float) $payment->amount;
                    $paymentMethod = $payment->paymentTenders->isNotEmpty()
                        ? $payment->paymentTenders
                            ->when($mode, fn ($tenders) => $tenders->where('payment_mode', $mode))
                            ->map(fn (PaymentTender $tender) => ucfirst($tender->payment_mode).' ₹'.number_format((float) $tender->amount, 2))
                            ->implode(', ')
                        : $payment->payment_mode;

                    return [
                        $payment->paid_at?->format('Y-m-d H:i'),
                        $payment->student?->student_id,
                        $payment->transaction_reference,
                        $paymentMethod,
                        number_format($collectedAmount, 2, '.', ''),
                        number_format((float) $payment->refunds->sum('amount'), 2, '.', ''),
                    ];
                });
            $header = ['Date', 'Student ID', 'Reference', 'Payment Method', 'Collected Amount', 'Refunded Amount (Payment Level)'];
        } elseif ($type === 'attendance') {
            $rows = Attendance::query()->with('student')->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))
                ->when($data['from_date'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '>=', $date))
                ->when($data['to_date'] ?? null, fn ($q, $date) => $q->whereDate('attendance_date', '<=', $date))
                ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->get()
                ->map(fn ($attendance) => [$attendance->attendance_date->format('Y-m-d'), $attendance->student?->student_id, $attendance->status, $attendance->remarks]);
            $header = ['Date', 'Student ID', 'Status', 'Remarks'];
        } elseif ($type === 'fees' || $type === 'previous_dues') {
            $rows = StudentFeeAssignment::query()->with(['student', 'component.structure.academicYear', 'component.academicYear', 'allocations'])
                ->whereHas('student', fn ($q) => $q->when($branches !== null, fn ($s) => $s->whereIn('branch_id', $branches)))
                ->when($type === 'fees', function ($query) use ($data) {
                    $query->whereHas('component', function ($component) use ($data) {
                        $component->whereNotNull('due_date')
                            ->whereDate('due_date', '<=', today())
                            ->when($data['from_date'] ?? null, fn ($q, $date) => $q->whereDate('due_date', '>=', $date))
                            ->when($data['to_date'] ?? null, fn ($q, $date) => $q->whereDate('due_date', '<=', $date))
                            ->when($data['academic_year_id'] ?? null, fn ($q, $yearId) => $q->where(function ($year) use ($yearId) {
                                $year->where('academic_year_id', $yearId)
                                    ->orWhereHas('structure', fn ($structure) => $structure->where('academic_year_id', $yearId));
                            }));
                    })
                        ->whereRaw('(student_fee_assignments.amount - student_fee_assignments.discount + student_fee_assignments.late_fee) > COALESCE((SELECT SUM(pa.amount) FROM payment_allocations pa WHERE pa.student_fee_assignment_id = student_fee_assignments.id), 0)')
                        ->when($data['search'] ?? null, fn ($query, $search) => $query->whereHas('student', fn ($student) => $student->where(function ($name) use ($search) {
                            $name->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhere('student_id', 'like', '%'.$search.'%');
                        })));
                })
                ->when($type === 'previous_dues', function ($query) use ($branches, $data) {
                    $yearIds = ! empty($data['academic_year_id'])
                        ? [(int) $data['academic_year_id']]
                        : $this->previousAcademicYearIds($branches);

                    $query->whereHas('component', function ($component) use ($yearIds) {
                        $component->where(function ($year) use ($yearIds) {
                            $year->whereIn('academic_year_id', $yearIds)
                                ->orWhereHas('structure', fn ($structure) => $structure->whereIn('academic_year_id', $yearIds));
                        });
                    })
                        ->whereRaw('(student_fee_assignments.amount - student_fee_assignments.discount + student_fee_assignments.late_fee) > COALESCE((SELECT SUM(pa.amount) FROM payment_allocations pa WHERE pa.student_fee_assignment_id = student_fee_assignments.id), 0)')
                        ->when($data['search'] ?? null, fn ($query, $search) => $query->whereHas('student', fn ($student) => $student->where(function ($name) use ($search) {
                            $name->where('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%')
                                ->orWhere('student_id', 'like', '%'.$search.'%');
                        })));
                })
                ->get()->map(function ($assignment) {
                    return [$assignment->student
                        ?->student_id, $assignment
                        ->student
                        ?->first_name.' '.$assignment
                        ->student
                        ?->last_name, $assignment
                        ->component
                        ?->academicYear
                        ?->name ?? $assignment->component?->structure?->academicYear?->name, $assignment
                        ->component
                        ?->name, $assignment
                        ->component
                        ?->due_date
                        ?->format('Y-m-d'), $assignment
                        ->totalDue(), $assignment
                        ->totalDue() - $assignment
                        ->balanceDue(), $assignment
                        ->balanceDue()];
                });
            $header = ['Student ID', 'Student', 'Academic Year', 'Installment', 'Due Date', 'Amount', 'Paid', 'Balance'];
        } else {
            $rows = Expense::query()->with('category')
                ->where('status', 'approved')
                ->when($branches !== null, fn ($q) => $q
                    ->whereIn('branch_id', $branches))
                ->when($data['from_date'] ?? null, fn ($q, $date) => $q
                    ->whereDate('expense_date', '>=', $date))
                ->when($data['to_date'] ?? null, fn ($q, $date) => $q
                    ->whereDate('expense_date', '<=', $date))
                ->get()
                ->map(fn ($expense) => [$expense
                    ->expense_date
                    ?->format('Y-m-d'), $expense
                    ->voucher_number, $expense
                    ->category
                    ?->name, $expense
                    ->description, $expense
                    ->vendor_name, $expense
                    ->payment_mode, $expense
                    ->amount]);
            $header = ['Date', 'Voucher', 'Category', 'Description', 'Vendor', 'Payment mode', 'Amount'];
        }

        DB::table('report_exports')->insert(['tenant_id' => app(TenantContext::class)
            ->id(), 'user_id' => $request
            ->user()
            ->id, 'branch_id' => $branchId ?: $request
            ->user()
            ->currentBranchId(), 'report_type' => $type, 'format' => $format, 'filters' => json_encode($data, JSON_THROW_ON_ERROR), 'ip_address' => $request
            ->ip(), 'created_at' => now(), 'updated_at' => now()]);
        if ($format === 'xlsx') {
            return response()->download($xlsx
                ->write($header, $rows), $type.'-report-'.now()
                ->format('YmdHis').'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                ->deleteFileAfterSend(true);
        }
        if ($format === 'pdf') {
            return response()->download($pdf
                ->write(ucfirst($type).' report', $header, $rows), $type.'-report-'.now()
                ->format('YmdHis').'.pdf', ['Content-Type' => 'application/pdf'])
                ->deleteFileAfterSend(true);
        }

        return response()->streamDownload(function () use ($header, $rows) {
            $output = fopen('php://output', 'w');
            fputcsv($output, array_map(fn (mixed $value): mixed => $this->escapeCsvFormula($value), $header));
            foreach ($rows as $row) {
                fputcsv($output, array_map(fn (mixed $value): mixed => $this->escapeCsvFormula($value), $row));
            }
            fclose($output);
        }, $type.'-report-'.now()->format('YmdHis').'.csv', ['Content-Type' => 'text/csv']);
    }

    /** Prefix formula-leading text so spreadsheet applications keep it as a literal value. */
    private function escapeCsvFormula(mixed $value): mixed
    {
        if (! is_string($value) || ! preg_match('/^(?:\xEF\xBB\xBF)?[\x00-\x20]*[=+\-@]/', $value)) {
            return $value;
        }

        return "'".$value;
    }

    /** Summarize unpaid fee items from the last completed academic year per permitted branch. */
    public function previousYearDues(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1',
            'academic_year_id' => 'nullable|integer|min:1',
            'search' => 'nullable|string|max:100',
        ]);
        $accessibleBranches = $request->user()->accessibleBranchIds();
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        if ($branchId) {
            abort_unless(
                $request->user()->canAccessBranch($branchId) && Branch::query()->whereKey($branchId)->exists(),
                403,
            );
        }
        $branches = $branchId ? collect([$branchId]) : $accessibleBranches;
        $branchOptions = Branch::query()
            ->where('is_active', true)
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('id', $accessibleBranches))
            ->orderBy('name')
            ->get();
        $yearIds = $this->previousAcademicYearIds($branches);
        $academicYears = AcademicYear::query()
            ->with('branch')
            ->whereIn('id', $yearIds)
            ->orderByDesc('end_date')
            ->get();
        $academicYearId = isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null;
        if ($academicYearId) {
            abort_unless($academicYears->contains('id', $academicYearId), 403);
            $yearIds = [$academicYearId];
        }

        $query = StudentFeeAssignment::query()->with([
            'student.branch',
            'component.structure.academicYear',
            'component.academicYear',
            'allocations',
        ])
            ->whereHas('component', fn ($component) => $component->where(fn ($year) => $year
                ->whereIn('academic_year_id', $yearIds)
                ->orWhereHas('structure', fn ($structure) => $structure->whereIn('academic_year_id', $yearIds))))
            ->whereHas('student', fn ($student) => $student
                ->when($branches !== null, fn ($branchQuery) => $branchQuery->whereIn('branch_id', $branches)))
            ->whereRaw('(student_fee_assignments.amount - student_fee_assignments.discount + student_fee_assignments.late_fee) > COALESCE((SELECT SUM(pa.amount) FROM payment_allocations pa WHERE pa.student_fee_assignment_id = student_fee_assignments.id), 0)')
            ->when($data['search'] ?? null, fn ($query, $search) => $query->whereHas('student', fn ($student) => $student->where(function ($name) use ($search) {
                $name->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('student_id', 'like', '%'.$search.'%');
            })));
        $totalBalance = (float) (clone $query)->sum(DB::raw('student_fee_assignments.amount - student_fee_assignments.discount + student_fee_assignments.late_fee - COALESCE((SELECT SUM(pa.amount) FROM payment_allocations pa WHERE pa.student_fee_assignment_id = student_fee_assignments.id), 0)'));
        $installmentCount = (clone $query)->count();
        $studentCount = (clone $query)->distinct('student_id')->count('student_id');
        $assignments = $query
            ->orderByRaw('(SELECT due_date FROM fee_components WHERE fee_components.id = student_fee_assignments.fee_component_id) ASC')
            ->orderBy('student_id')
            ->paginate(30)
            ->withQueryString();
        $branchName = $branchId
            ? ($branchOptions->firstWhere('id', $branchId)?->name ?? 'Selected branch')
            : 'All permitted branches';

        return view('reports.previous-dues', compact(
            'assignments',
            'data',
            'branchOptions',
            'branchId',
            'academicYears',
            'academicYearId',
            'totalBalance',
            'installmentCount',
            'studentCount',
            'branchName',
        ));
    }

    private function previousAcademicYearIds($branches): array
    {
        return AcademicYear::query()->when($branches !== null, fn ($q) => $q
            ->whereIn('branch_id', $branches))
            ->where('is_current', false)
            ->where('end_date', '<', today())
            ->orderByDesc('end_date')
            ->get(['id', 'branch_id'])
            ->groupBy('branch_id')
            ->map(fn ($items) => (int) $items
                ->first()
                ->id)
            ->values()
            ->all();
    }

    /** Build an approved-expense summary and ledger within the user's permitted branches. */
    public function expenses(Request $request)
    {
        $data = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'period' => 'nullable|in:day,week,month,quarter,year,financial_year',
            'branch_id' => 'nullable|integer|min:1',
        ]);
        if (empty($data['from_date']) && empty($data['to_date']) && empty($data['period'])) {
            $data['period'] = 'financial_year';
        }
        $data = $this->applyPeriod($data);
        $accessibleBranches = $request->user()->accessibleBranchIds();
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        if ($branchId) {
            abort_unless(
                $request->user()->canAccessBranch($branchId) && Branch::query()->whereKey($branchId)->exists(),
                403,
            );
        }
        $branches = $branchId ? collect([$branchId]) : $accessibleBranches;
        $branchOptions = Branch::query()
            ->where('is_active', true)
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('id', $accessibleBranches))
            ->orderBy('name')
            ->get();
        $branchName = $branchId
            ? ($branchOptions->firstWhere('id', $branchId)?->name ?? 'Selected branch')
            : 'All permitted branches';
        $query = Expense::query()->with(['category', 'branch'])
            ->where('status', 'approved')
            ->when($branches !== null, fn ($q) => $q
                ->whereIn('branch_id', $branches))
            ->when($data['from_date'] ?? null, fn ($q, $date) => $q
                ->whereDate('expense_date', '>=', $date))
            ->when($data['to_date'] ?? null, fn ($q, $date) => $q
                ->whereDate('expense_date', '<=', $date));
        $total = (float) (clone $query)->sum('amount');
        $expenseCount = (clone $query)->count();
        $averageExpense = $expenseCount > 0 ? $total / $expenseCount : 0;
        $byCategory = (clone $query)
            ->selectRaw('expense_category_id, SUM(amount) as total, COUNT(*) as expense_count')
            ->groupBy('expense_category_id')
            ->with('category')
            ->orderByDesc('total')
            ->get();
        $expenses = (clone $query)->latest('expense_date')->paginate(30)->withQueryString();

        return view('reports.expenses', compact(
            'expenses',
            'data',
            'total',
            'expenseCount',
            'averageExpense',
            'byCategory',
            'branches',
            'branchOptions',
            'branchId',
            'branchName',
        ));
    }

    private function applyPeriod(array $data): array
    {
        if (empty($data['period']) || ! empty($data['from_date']) || ! empty($data['to_date'])) {
            return $data;
        }
        $now = now();
        $range = match ($data['period']) {
            'day' => [$now->copy()
                ->startOfDay(), $now
                ->copy()
                ->endOfDay()],'week' => [$now
                ->copy()
                ->startOfWeek(), $now
                ->copy()
                ->endOfWeek()],'month' => [$now
                ->copy()
                ->startOfMonth(), $now
                ->copy()
                ->endOfMonth()],'quarter' => [$now
                ->copy()
                ->startOfQuarter(), $now
                ->copy()
                ->endOfQuarter()],'year' => [$now
                ->copy()
                ->startOfYear(), $now
                ->copy()
                ->endOfYear()],'financial_year' => [$now
                ->month >= 4 ? $now
                ->copy()
                ->startOfYear()
                ->addMonths(3) : $now
                ->copy()
                ->subYear()
                ->startOfYear()
                ->addMonths(3), $now
                ->month >= 4 ? $now
                ->copy()
                ->addYear()
                ->startOfYear()
                ->addMonths(2)
                ->endOfMonth() : $now
                ->copy()
                ->startOfYear()
                ->addMonths(2)
                ->endOfMonth()]
        };
        $data['from_date'] = $range[0]->toDateString();
        $data['to_date'] = $range[1]->toDateString();

        return $data;
    }

    /** Show overdue fee balances limited to branches the signed-in user can access. */
    public function fees(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|min:1',
            'academic_year_id' => 'nullable|integer|min:1',
            'from_date' => 'nullable|date|before_or_equal:today',
            'to_date' => 'nullable|date|after_or_equal:from_date|before_or_equal:today',
            'search' => 'nullable|string|max:100',
        ]);
        $accessibleBranches = $request->user()->accessibleBranchIds();
        $branchId = isset($data['branch_id']) ? (int) $data['branch_id'] : null;
        if ($branchId) {
            abort_unless(
                $request->user()->canAccessBranch($branchId) && Branch::query()->whereKey($branchId)->exists(),
                403,
            );
        }
        $branches = $branchId ? collect([$branchId]) : $accessibleBranches;
        $branchOptions = Branch::query()
            ->where('is_active', true)
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('id', $accessibleBranches))
            ->orderBy('name')
            ->get();
        $academicYears = AcademicYear::query()
            ->with('branch')
            ->when($branches !== null, fn ($query) => $query->whereIn('branch_id', $branches))
            ->orderByDesc('start_date')
            ->get();
        $academicYearId = isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null;
        if ($academicYearId) {
            abort_unless($academicYears->contains('id', $academicYearId), 403);
        }

        $query = StudentFeeAssignment::query()->with(['student.branch', 'component.structure.academicYear', 'component.academicYear', 'allocations'])
            ->whereHas('student', fn ($q) => $q->when($branches !== null, fn ($s) => $s->whereIn('branch_id', $branches)))
            ->whereHas('component', function ($component) use ($data, $academicYearId) {
                $component->whereNotNull('due_date')
                    ->whereDate('due_date', '<=', today())
                    ->when($data['from_date'] ?? null, fn ($q, $date) => $q->whereDate('due_date', '>=', $date))
                    ->when($data['to_date'] ?? null, fn ($q, $date) => $q->whereDate('due_date', '<=', $date))
                    ->when($academicYearId, fn ($q) => $q->where(function ($year) use ($academicYearId) {
                        $year->where('academic_year_id', $academicYearId)
                            ->orWhereHas('structure', fn ($structure) => $structure->where('academic_year_id', $academicYearId));
                    }));
            })
            ->whereRaw('(student_fee_assignments.amount - student_fee_assignments.discount + student_fee_assignments.late_fee) > COALESCE((SELECT SUM(pa.amount) FROM payment_allocations pa WHERE pa.student_fee_assignment_id = student_fee_assignments.id), 0)')
            ->when($data['search'] ?? null, fn ($query, $search) => $query->whereHas('student', fn ($student) => $student->where(function ($name) use ($search) {
                $name->where('first_name', 'like', '%'.$search.'%')
                    ->orWhere('last_name', 'like', '%'.$search.'%')
                    ->orWhere('student_id', 'like', '%'.$search.'%');
            })));
        $totalBalance = (float) (clone $query)->sum(DB::raw('student_fee_assignments.amount - student_fee_assignments.discount + student_fee_assignments.late_fee - COALESCE((SELECT SUM(pa.amount) FROM payment_allocations pa WHERE pa.student_fee_assignment_id = student_fee_assignments.id), 0)'));
        $installmentCount = (clone $query)->count();
        $studentCount = (clone $query)->distinct('student_id')->count('student_id');
        $assignments = $query
            ->orderByRaw('(SELECT due_date FROM fee_components WHERE fee_components.id = student_fee_assignments.fee_component_id) ASC')
            ->orderBy('student_id')
            ->paginate(30)
            ->withQueryString();

        return view('reports.fees', compact(
            'assignments',
            'data',
            'branchOptions',
            'branchId',
            'academicYears',
            'academicYearId',
            'totalBalance',
            'installmentCount',
            'studentCount',
        ));
    }
}
