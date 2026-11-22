<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Collects payments and manages receipts, refunds, and corrections.
 */
class PaymentController extends Controller
{
    public function create(Request $request)
    {
        $user = $request->user();
        $branchId = $user->currentBranchId() ?: $request->integer('branch_id') ?: null;
        if ($branchId) {
            abort_unless($user->canAccessBranch($branchId), 403);
        }
        $accessibleBranches = $user->accessibleBranchIds();
        $hasStudents = false;
        $selectedStudent = null;

        if ($branchId) {
            $studentsQuery = Student::query()
                ->where('status', 'active')
                ->where('branch_id', $branchId);

            if ($accessibleBranches !== null) {
                $studentsQuery->whereIn('branch_id', $accessibleBranches);
            }

            $hasStudents = $studentsQuery->exists();

            if ($request->filled('student_id')) {
                $selectedStudent = (clone $studentsQuery)
                    ->with(['enrollments' => fn ($query) => $query
                        ->where('status', 'active')
                        ->latest()
                        ->with('academicYear')])
                    ->whereKey($request->integer('student_id'))
                    ->first();
            }
        }

        return view('payments.create', [
            'branches' => Branch::query()->where('is_active', true)->when($accessibleBranches !== null, fn ($q) => $q->whereIn('id', $accessibleBranches))->orderBy('name')->get(),
            'hasStudents' => $hasStudents,
            'selectedStudent' => $selectedStudent,
            'years' => AcademicYear::query()->when($branchId, fn ($q) => $q
                ->where('branch_id', $branchId))
                ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q
                    ->whereIn('branch_id', $accessibleBranches))
                ->orderByDesc('start_date')
                ->get(),
            'branchId' => $branchId,
        ]);
    }

    /** Search active students in the user's permitted tenant and branch scope. */
    public function searchStudents(Request $request)
    {
        $data = $request->validate([
            'q' => 'required|string|min:2|max:100',
            'branch_id' => 'nullable|integer',
        ]);
        $user = $request->user();
        $branchId = $user->currentBranchId() ?: ($data['branch_id'] ?? null);
        $accessibleBranches = $user->accessibleBranchIds();

        if ($branchId) {
            abort_unless($user->canAccessBranch($branchId), 403);
        }

        $term = trim($data['q']);
        $students = Student::query()
            ->where('status', 'active')
            ->when($accessibleBranches !== null, fn ($query) => $query
                ->whereIn('branch_id', $accessibleBranches))
            ->when($branchId, fn ($query) => $query
                ->where('branch_id', $branchId))
            ->where(fn ($query) => $query
                ->where('first_name', 'like', '%'.$term.'%')
                ->orWhere('last_name', 'like', '%'.$term.'%')
                ->orWhere('student_id', 'like', '%'.$term.'%')
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%'.$term.'%']))
            ->with(['enrollments' => fn ($query) => $query
                ->where('status', 'active')
                ->latest()])
            ->orderBy('first_name')
            ->limit(20)
            ->get()
            ->map(function (Student $student) {
                $enrollment = $student->enrollments->first();

                return [
                    'id' => $student->id,
                    'label' => trim($student->first_name.' '.$student->last_name).' · '.$student->student_id,
                    'current_year_id' => $enrollment?->academic_year_id,
                ];
            });

        return response()->json(['students' => $students]);
    }

    /** Return a selected student's academic years and due items for live payment collection. */
    public function studentDues(Request $request, Student $student)
    {
        abort_unless($request->user()->canAccessBranch($student->branch_id), 404);
        abort_unless($student->status === 'active', 404);

        $enrollment = $student->enrollments()
            ->where('status', 'active')
            ->with('academicYear')
            ->latest()
            ->first();

        $years = AcademicYear::query()
            ->where('branch_id', $student->branch_id)
            ->orderByDesc('start_date')
            ->get(['id', 'name'])
            ->map(fn (AcademicYear $year) => [
                'id' => $year->id,
                'name' => $year->name,
            ]);

        $assignments = StudentFeeAssignment::query()
            ->with(['component.structure', 'component.academicYear', 'allocations'])
            ->where('student_fee_assignments.student_id', $student->id)
            ->leftJoin('fee_components as ordered_components', 'student_fee_assignments.fee_component_id', '=', 'ordered_components.id')
            ->select('student_fee_assignments.*')
            ->orderByRaw('CASE WHEN ordered_components.due_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('ordered_components.due_date')
            ->orderBy('ordered_components.installment_number')
            ->orderBy('student_fee_assignments.id')
            ->get()
            ->map(function (StudentFeeAssignment $assignment) {
                $balance = $assignment->balanceDue();

                return [
                    'id' => $assignment->id,
                    'year_id' => $assignment->component?->academic_year_id
                        ?? $assignment->component?->structure?->academic_year_id,
                    'plan_name' => $assignment->component?->structure?->name ?? 'Fee',
                    'installment_name' => $assignment->component?->name ?? 'Installment',
                    'balance' => number_format($balance, 2, '.', ''),
                    'due_date' => $assignment->component?->due_date?->format('d M Y') ?? 'Not set',
                ];
            })
            ->filter(fn (array $assignment) => (float) $assignment['balance'] > 0)
            ->values();

        return response()->json([
            'current_year_id' => $enrollment?->academic_year_id,
            'years' => $years,
            'assignments' => $assignments,
        ]);
    }

    /**
     * Record one payment and allocate it only to the selected due items.
     * Branch access is checked before writes; payment, receipt, and allocations commit atomically.
     */
    public function store(StorePaymentRequest $request, NotificationService $notifications)
    {
        $data = $request->validated();
        $student = Student::query()->findOrFail($data['student_id']);
        abort_unless($request->user()->canAccessBranch($student->branch_id), 403);
        $enrollment = $student->enrollments()->where('status', 'active')->latest()->first();
        $yearId = $data['academic_year_id'] ?? $enrollment?->academic_year_id;
        $selectedAssignmentIds = collect($data['student_fee_assignment_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->when(! empty($data['student_fee_assignment_id']), fn ($ids) => $ids->push((int) $data['student_fee_assignment_id']))
            ->unique()
            ->values();
        if (! empty($data['collect_selected_items']) && $selectedAssignmentIds->isEmpty()) {
            throw ValidationException::withMessages([
                'student_fee_assignment_ids' => 'Select at least one due item before collecting payment.',
            ]);
        }
        $hasPerItemAmounts = array_key_exists('paid_amounts', $data);
        $paidByAssignment = collect($data['paid_amounts'] ?? [])
            ->mapWithKeys(fn ($amount, $id) => [(int) $id => (int) round((float) $amount * 100)]);
        if ($hasPerItemAmounts && $paidByAssignment->keys()->diff($selectedAssignmentIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'paid_amounts' => 'Each paid amount must match a selected due item.',
            ]);
        }
        if ($hasPerItemAmounts && ($paidByAssignment->count() !== $selectedAssignmentIds->count()
            || $paidByAssignment->contains(fn ($amountCents) => $amountCents < 1))) {
            throw ValidationException::withMessages([
                'paid_amounts' => 'Enter a paid amount for every selected due item.',
            ]);
        }

        $amountCents = $hasPerItemAmounts
            ? $paidByAssignment->sum()
            : (int) round((float) ($data['amount'] ?? 0) * 100);
        if ($amountCents < 1) {
            throw ValidationException::withMessages(['amount' => 'Enter a paid amount for at least one selected due item.']);
        }
        if (isset($data['amount']) && (int) round((float) $data['amount'] * 100) !== $amountCents) {
            throw ValidationException::withMessages(['amount' => 'The total paid amount does not match the due item amounts.']);
        }

        $paymentModes = collect($data['payment_modes'] ?? [$data['payment_mode'] ?? null])
            ->filter()
            ->unique()
            ->values();
        if ($paymentModes->isEmpty()) {
            throw ValidationException::withMessages([
                'payment_modes' => 'Select at least one payment method.',
            ]);
        }

        $tenderAmounts = [];
        if ($paymentModes->count() === 1) {
            if (! empty($data['method_amounts'] ?? [])) {
                throw ValidationException::withMessages([
                    'method_amounts' => 'Payment method amount details are only accepted when multiple methods are selected.',
                ]);
            }

            $tenderAmounts[$paymentModes->first()] = $amountCents;
        } else {
            foreach ($paymentModes as $mode) {
                $methodAmount = (int) round((float) ($data['method_amounts'][$mode] ?? 0) * 100);
                if ($methodAmount < 1) {
                    throw ValidationException::withMessages([
                        'method_amounts.'.$mode => 'Enter an amount for each selected payment method.',
                    ]);
                }
                $tenderAmounts[$mode] = $methodAmount;
            }

            $unselectedMethods = array_diff(array_keys($data['method_amounts'] ?? []), $paymentModes->all());
            if ($unselectedMethods !== []) {
                throw ValidationException::withMessages(['method_amounts' => 'Remove amounts for unselected payment methods.']);
            }
            if (array_sum($tenderAmounts) !== $amountCents) {
                throw ValidationException::withMessages(['method_amounts' => 'Payment method amounts must add up to the total paid amount.']);
            }
        }
        if ($yearId) {
            if (! AcademicYear::query()->whereKey($yearId)->where('branch_id', $student->branch_id)->exists()) {
                throw ValidationException::withMessages(['academic_year_id' => 'Choose an academic year from the student’s branch.']);
            }
        }

        if ($selectedAssignmentIds->isNotEmpty()) {
            $selectedAssignments = StudentFeeAssignment::query()
                ->with('component.structure', 'component.academicYear')
                ->whereIn('id', $selectedAssignmentIds)
                ->where('student_fee_assignments.student_id', $student->id)
                ->get();

            if ($selectedAssignments->count() !== $selectedAssignmentIds->count()) {
                throw ValidationException::withMessages([
                    'student_fee_assignment_ids' => 'Choose due items assigned to this student.',
                ]);
            }

            foreach ($selectedAssignments as $selectedAssignment) {
                if ((int) $selectedAssignment->component?->structure?->branch_id !== (int) $student->branch_id) {
                    abort(403);
                }

                $assignmentYearId = $selectedAssignment->component?->academic_year_id
                    ?? $selectedAssignment->component?->structure?->academic_year_id;
                if ($yearId && (int) $assignmentYearId !== (int) $yearId) {
                    throw ValidationException::withMessages([
                        'student_fee_assignment_ids' => 'Choose due items from the selected academic year.',
                    ]);
                }
            }
        }

        $payment = DB::transaction(function () use ($data, $student, $yearId, $request, $amountCents, $selectedAssignmentIds, $paidByAssignment, $paymentModes, $tenderAmounts) {
            $assignmentsQuery = StudentFeeAssignment::query()
                ->with('component')
                ->where('student_fee_assignments.student_id', $student->id);
            if ($selectedAssignmentIds->isNotEmpty()) {
                $assignmentsQuery->whereIn('student_fee_assignments.id', $selectedAssignmentIds)
                    ->leftJoin('fee_components as ordered_components', 'student_fee_assignments.fee_component_id', '=', 'ordered_components.id')
                    ->select('student_fee_assignments.*')
                    ->orderByRaw('CASE WHEN ordered_components.due_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('ordered_components.due_date')
                    ->orderBy('ordered_components.installment_number')
                    ->orderBy('student_fee_assignments.id');
            } else {
                if ($yearId) {
                    $assignmentsQuery->whereHas('component', fn ($q) => $q->where(fn ($year) => $year
                        ->where('academic_year_id', $yearId)
                        ->orWhereHas('structure', fn ($structure) => $structure->where('academic_year_id', $yearId))));
                } else {
                    $assignmentsQuery->whereRaw('1 = 0');
                }
                $assignmentsQuery->leftJoin('fee_components as ordered_components', 'student_fee_assignments.fee_component_id', '=', 'ordered_components.id')
                    ->select('student_fee_assignments.*')
                    ->orderByRaw('CASE WHEN ordered_components.due_date IS NULL THEN 1 ELSE 0 END')
                    ->orderBy('ordered_components.due_date')->orderBy('ordered_components.installment_number')->orderBy('student_fee_assignments.id');
            }
            $assignments = $assignmentsQuery->lockForUpdate()->get();
            $dueByAssignment = [];
            $totalBalanceCents = 0;
            foreach ($assignments as $assignment) {
                $dueCents = (int) round($assignment->totalDue() * 100);
                $paidCents = (int) round((float) $assignment->allocations()->sum('amount') * 100);
                $balanceCents = max(0, $dueCents - $paidCents);
                $dueByAssignment[$assignment->id] = $balanceCents;
                $totalBalanceCents += $balanceCents;
            }

            if ($assignments->isEmpty()) {
                throw ValidationException::withMessages([
                    'student_id' => 'This student has no assigned installments for the selected academic year.',
                ]);
            }

            if ($amountCents > $totalBalanceCents) {
                throw ValidationException::withMessages(['amount' => 'The payment exceeds the remaining balance for the selected installment(s).']);
            }

            foreach ($paidByAssignment as $assignmentId => $paidCents) {
                if (! isset($dueByAssignment[$assignmentId]) || $paidCents > $dueByAssignment[$assignmentId]) {
                    throw ValidationException::withMessages([
                        'paid_amounts.'.$assignmentId => 'The paid amount cannot exceed this due item’s remaining balance.',
                    ]);
                }
            }

            $payment = Payment::create([
                'student_id' => $student->id,
                'branch_id' => $student->branch_id,
                'academic_year_id' => $yearId,
                'collected_by' => $request->user()->id,
                'transaction_reference' => 'TXN-'.now()->format('YmdHis').'-'.str()->upper(str()->random(6)),
                'amount' => number_format($amountCents / 100, 2, '.', ''),
                'payment_mode' => $paymentModes->count() === 1 ? $paymentModes->first() : 'multiple',
                'payment_reference' => $data['payment_reference'] ?? null,
                'paid_at' => now(),
            ]);
            Receipt::create(['payment_id' => $payment->id, 'receipt_number' => 'RCP-'.now()->format('YmdHis').'-'.str()->upper(str()->random(5))]);
            foreach ($tenderAmounts as $mode => $tenderCents) {
                $payment->paymentTenders()->create([
                    'payment_mode' => $mode,
                    'amount' => number_format($tenderCents / 100, 2, '.', ''),
                ]);
            }

            $remainingCents = $amountCents;
            foreach ($assignments as $assignment) {
                $allocatedCents = $paidByAssignment->has($assignment->id)
                    ? $paidByAssignment->get($assignment->id)
                    : min($remainingCents, $dueByAssignment[$assignment->id]);
                if ($allocatedCents < 1) {
                    continue;
                }
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'student_fee_assignment_id' => $assignment->id,
                    'amount' => number_format($allocatedCents / 100, 2, '.', ''),
                    'allocation_type' => 'installment',
                ]);
                $remainingCents = max(0, $remainingCents - $allocatedCents);
                $paidAfterCents = ((int) round($assignment->totalDue() * 100)) - $dueByAssignment[$assignment->id] + $allocatedCents;
                $assignment->update(['status' => $paidAfterCents >= (int) round($assignment->totalDue() * 100) ? 'paid' : 'partial']);
            }

            return $payment;
        });

        $student->loadMissing('userAccounts', 'guardians.userAccounts');
        foreach ($student->userAccounts->merge($student->guardians->flatMap->userAccounts)->unique('id') as $recipient) {
            $notifications->send($recipient
                ->id, 'payment.received', 'auto', ['student' => trim($student
                ->first_name.' '.$student
                ->last_name), 'amount' => number_format($amountCents / 100, 2), 'reference' => $payment
                ->transaction_reference]);
        }

        if ($request->expectsJson()) {
            $payment->load(['receipt', 'paymentTenders', 'allocations.studentFeeAssignment.component.structure']);

            return response()->json([
                'message' => 'Payment recorded successfully.',
                'receipt_url' => route('payments.receipt', $payment),
                'payment' => [
                    'amount' => number_format((float) $payment->amount, 2, '.', ''),
                    'paid_at' => $payment->paid_at?->format('d M Y, h:i A'),
                    'payment_method' => $payment->paymentTenders->map(fn ($tender) => ucfirst(str_replace('_', ' ', $tender->payment_mode))
                        .' ₹'.number_format((float) $tender->amount, 2))->implode(', '),
                    'payment_methods' => $payment->paymentTenders->map(fn ($tender) => [
                        'method' => ucfirst(str_replace('_', ' ', $tender->payment_mode)),
                        'amount' => number_format((float) $tender->amount, 2, '.', ''),
                    ])->values(),
                    'payment_reference' => $payment->payment_reference,
                    'transaction_reference' => $payment->transaction_reference,
                    'receipt_number' => $payment->receipt?->receipt_number,
                    'allocations' => $payment->allocations->map(function (PaymentAllocation $allocation) {
                        $component = $allocation->studentFeeAssignment?->component;

                        return [
                            'fee_name' => $component?->structure?->name ?? 'Student fee',
                            'installment_name' => $component?->name ?? 'Installment',
                            'amount' => number_format((float) $allocation->amount, 2, '.', ''),
                        ];
                    })->values(),
                ],
            ], 201);
        }

        return redirect()->route('payments.receipt', $payment)->with('success', 'Payment recorded successfully.');
    }

    public function receipt(Payment $payment)
    {
        $user = auth()->user();
        abort_unless($user->canAccessBranch($payment
            ->branch_id) || $user
            ->students()
            ->whereKey($payment
                ->student_id)
            ->exists() || $user
            ->guardians()
            ->whereHas('students', fn ($q) => $q
                ->whereKey($payment
                    ->student_id))
            ->exists(), 403);

        $payment->load([
            'student.branch',
            'academicYear',
            'receipt',
            'paymentTenders',
            'allocations.studentFeeAssignment.component.structure',
            'allocations.studentFeeAssignment.component.academicYear',
        ]);

        $tenant = Tenant::query()->find($payment->tenant_id);

        return view('payments.receipt', compact('payment', 'tenant'));
    }

    /** Display the request, decision, and ledger history for a payment. */
    public function history(Payment $payment)
    {
        abort_unless(auth()->user()->canAccessBranch($payment->branch_id), 403);

        $payment->load(['student', 'receipt']);
        $corrections = DB::table('payment_corrections as requests')
            ->leftJoin('users as requesters', 'requesters.id', '=', 'requests.requested_by')
            ->leftJoin('users as reviewers', 'reviewers.id', '=', 'requests.approved_by')
            ->where('requests.payment_id', $payment->id)
            ->orderBy('requests.created_at')
            ->get([
                'requests.id',
                'requests.proposed_changes',
                'requests.reason',
                'requests.status',
                'requests.review_note',
                'requests.created_at as submitted_at',
                'requests.decided_at',
                'requests.updated_at',
                'requesters.name as requester_name',
                'reviewers.name as reviewer_name',
            ])
            ->map(function ($request) {
                $changes = is_array($request->proposed_changes)
                    ? $request->proposed_changes
                    : json_decode($request->proposed_changes, true) ?? [];

                return (object) [
                    'kind' => 'Correction request',
                    'status' => $request->status,
                    'summary' => isset($changes['old_amount'])
                        ? 'Amount changed from ₹'.number_format((float) $changes['old_amount'], 2).' to ₹'.number_format((float) $changes['amount'], 2)
                        : 'Requested amount: ₹'.number_format((float) ($changes['amount'] ?? 0), 2),
                    'amount' => $changes['amount'] ?? null,
                    'reason' => $request->reason,
                    'requester_name' => $request->requester_name,
                    'reviewer_name' => $request->reviewer_name,
                    'submitted_at' => $request->submitted_at,
                    'decided_at' => $request->decided_at ?? ($request->status !== 'pending' ? $request->updated_at : null),
                    'review_note' => $request->review_note,
                ];
            });

        $refundRequests = DB::table('refund_requests as requests')
            ->leftJoin('users as requesters', 'requesters.id', '=', 'requests.requested_by')
            ->leftJoin('users as reviewers', 'reviewers.id', '=', 'requests.approved_by')
            ->where('requests.payment_id', $payment->id)
            ->orderBy('requests.created_at')
            ->get([
                'requests.amount',
                'requests.reason',
                'requests.status',
                'requests.review_note',
                'requests.created_at as submitted_at',
                'requests.decided_at',
                'requests.updated_at',
                'requesters.name as requester_name',
                'reviewers.name as reviewer_name',
            ])
            ->map(fn ($request) => (object) [
                'kind' => 'Refund request',
                'status' => $request->status,
                'summary' => 'Requested refund: ₹'.number_format((float) $request->amount, 2),
                'amount' => $request->amount,
                'reason' => $request->reason,
                'requester_name' => $request->requester_name,
                'reviewer_name' => $request->reviewer_name,
                'submitted_at' => $request->submitted_at,
                'decided_at' => $request->decided_at ?? ($request->status !== 'pending' ? $request->updated_at : null),
                'review_note' => $request->review_note,
            ]);

        $receiptRequests = DB::table('receipt_cancellations as requests')
            ->join('receipts', 'receipts.id', '=', 'requests.receipt_id')
            ->leftJoin('users as requesters', 'requesters.id', '=', 'requests.requested_by')
            ->leftJoin('users as reviewers', 'reviewers.id', '=', 'requests.approved_by')
            ->where('receipts.payment_id', $payment->id)
            ->orderBy('requests.created_at')
            ->get([
                'receipts.receipt_number',
                'requests.reason',
                'requests.status',
                'requests.review_note',
                'requests.created_at as submitted_at',
                'requests.decided_at',
                'requests.updated_at',
                'requesters.name as requester_name',
                'reviewers.name as reviewer_name',
            ])
            ->map(fn ($request) => (object) [
                'kind' => 'Receipt cancellation',
                'status' => $request->status,
                'summary' => 'Receipt '.$request->receipt_number,
                'amount' => null,
                'reason' => $request->reason,
                'requester_name' => $request->requester_name,
                'reviewer_name' => $request->reviewer_name,
                'submitted_at' => $request->submitted_at,
                'decided_at' => $request->decided_at ?? ($request->status !== 'pending' ? $request->updated_at : null),
                'review_note' => $request->review_note,
            ]);

        $refundEntries = DB::table('refunds')
            ->leftJoin('users as processors', 'processors.id', '=', 'refunds.processed_by')
            ->where('refunds.payment_id', $payment->id)
            ->orderBy('refunds.created_at')
            ->get([
                'refunds.amount',
                'refunds.reason',
                'refunds.status',
                'refunds.created_at as submitted_at',
                'processors.name as requester_name',
            ])
            ->map(fn ($refund) => (object) [
                'kind' => 'Refund ledger entry',
                'status' => $refund->status,
                'summary' => 'Refund issued: ₹'.number_format((float) $refund->amount, 2),
                'amount' => $refund->amount,
                'reason' => $refund->reason,
                'requester_name' => $refund->requester_name,
                'reviewer_name' => null,
                'submitted_at' => $refund->submitted_at,
                'decided_at' => $refund->submitted_at,
                'review_note' => null,
            ]);

        $ledgerEntries = DB::table('payment_allocations')
            ->leftJoin('student_fee_assignments', 'student_fee_assignments.id', '=', 'payment_allocations.student_fee_assignment_id')
            ->leftJoin('fee_components', 'fee_components.id', '=', 'student_fee_assignments.fee_component_id')
            ->where('payment_allocations.payment_id', $payment->id)
            ->whereIn('payment_allocations.allocation_type', ['refund_reversal', 'correction_reversal', 'receipt_reversal', 'correction_adjustment'])
            ->orderBy('payment_allocations.created_at')
            ->get([
                'payment_allocations.amount',
                'payment_allocations.allocation_type',
                'payment_allocations.created_at as submitted_at',
                'fee_components.name as fee_name',
            ])
            ->map(fn ($entry) => (object) [
                'kind' => $entry->allocation_type === 'correction_adjustment'
                    ? 'Unassigned correction amount'
                    : 'Fee ledger reversal',
                'status' => 'recorded',
                'summary' => ucfirst(str_replace('_', ' ', $entry->allocation_type)).($entry->fee_name ? ' · '.$entry->fee_name : ''),
                'amount' => $entry->amount,
                'reason' => null,
                'requester_name' => null,
                'reviewer_name' => null,
                'submitted_at' => $entry->submitted_at,
                'decided_at' => $entry->submitted_at,
                'review_note' => null,
            ]);

        $history = $corrections
            ->concat($refundRequests)
            ->concat($receiptRequests)
            ->concat($refundEntries)
            ->concat($ledgerEntries)
            ->sortBy('submitted_at')
            ->values();

        return view('payments.history', compact('payment', 'history'));
    }

    public function requestCorrection(Request $request, Payment $payment)
    {
        abort_unless($request->user()->canAccessBranch($payment->branch_id), 403);
        abort_unless($payment->status === 'completed', 422);
        $data = $request->validate([
            'amount' => 'required|numeric|gt:0',
            'reason' => 'required|string|max:1000',
        ]);
        DB::transaction(function () use ($payment, $request, $data) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedPayment->status === 'completed', 422);

            if (DB::table('payment_corrections')->where('payment_id', $lockedPayment->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['amount' => 'A correction request is already pending for this payment.']);
            }

            DB::table('payment_corrections')->insert([
                'payment_id' => $lockedPayment->id,
                'requested_by' => $request->user()->id,
                'tenant_id' => app(TenantContext::class)->id(),
                'proposed_changes' => json_encode([
                    'old_amount' => number_format((float) $lockedPayment->amount, 2, '.', ''),
                    'amount' => number_format((float) $data['amount'], 2, '.', ''),
                ], JSON_THROW_ON_ERROR),
                'reason' => $data['reason'],
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Payment correction sent for approval.');
    }

    public function requestRefund(Request $request, Payment $payment)
    {
        abort_unless($request->user()->canAccessBranch($payment->branch_id), 403);
        abort_unless($payment->status === 'completed', 422);
        $data = $request->validate(['amount' => 'required|numeric|gt:0', 'reason' => 'required|string|max:1000']);
        DB::transaction(function () use ($payment, $request, $data) {
            $lockedPayment = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedPayment->status === 'completed', 422);

            $alreadyRequested = (float) DB::table('refund_requests')
                ->where('payment_id', $lockedPayment->id)
                ->where('status', 'pending')
                ->sum('amount');
            $alreadyRefunded = (float) DB::table('refunds')
                ->where('payment_id', $lockedPayment->id)
                ->where('status', 'processed')
                ->sum('amount');

            if ((float) $data['amount'] + $alreadyRequested + $alreadyRefunded > (float) $lockedPayment->amount) {
                throw ValidationException::withMessages(['amount' => 'Refund amount exceeds the payment’s remaining refundable balance.']);
            }

            DB::table('refund_requests')->insert([
                'payment_id' => $lockedPayment->id,
                'requested_by' => $request->user()->id,
                'tenant_id' => app(TenantContext::class)->id(),
                'amount' => $data['amount'],
                'reason' => $data['reason'],
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Refund request sent for approval.');
    }

    public function requestReceiptCancellation(Request $request, Payment $payment)
    {
        abort_unless($request->user()->canAccessBranch($payment->branch_id), 403);
        abort_unless($payment->status === 'completed', 422);
        $data = $request->validate(['reason' => 'required|string|max:1000']);
        $receipt = $payment->receipt;
        abort_unless($receipt && $receipt->status === 'issued', 422);
        DB::transaction(function () use ($payment, $receipt, $request, $data) {
            Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
            $lockedReceipt = Receipt::query()->whereKey($receipt->id)->lockForUpdate()->firstOrFail();
            abort_unless($lockedReceipt->status === 'issued', 422);

            if (DB::table('receipt_cancellations')->where('receipt_id', $lockedReceipt->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['reason' => 'A receipt cancellation request is already pending.']);
            }

            DB::table('receipt_cancellations')->insert([
                'receipt_id' => $lockedReceipt->id,
                'requested_by' => $request->user()->id,
                'tenant_id' => app(TenantContext::class)->id(),
                'reason' => $data['reason'],
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Receipt cancellation sent for approval.');
    }
}
