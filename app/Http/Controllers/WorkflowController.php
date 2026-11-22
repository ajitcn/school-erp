<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Receipt;
use App\Models\Refund;
use App\Models\StudentFeeAssignment;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Reviews protected corrections, refunds, expenses, and receipt actions.
 */
class WorkflowController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = app(TenantContext::class)->id();
        $branchIds = $request->user()->accessibleBranchIds();
        $requests = collect();
        $expenseRequests = DB::table('expense_approvals as a')->join('expenses as e', 'e.id', '=', 'a.expense_id')
            ->leftJoin('users as requester', 'requester.id', '=', 'e.entered_by')->where('a.status', 'pending')
            ->when($tenantId, fn ($q) => $q->where('a.tenant_id', $tenantId))->when($branchIds !== null, fn ($q) => $q->whereIn('e.branch_id', $branchIds))
            ->selectRaw("'expense' as kind, a.id, e.id as expense_id, e.bill_path as attachment_path, e.voucher_number as reference, e.description as summary, e.amount, a.created_at, requester.name as requester_name")->get();
        $requests = $requests->merge($expenseRequests);
        $attendanceRequests = DB::table('attendance_corrections as c')->join('attendance as a', 'a.id', '=', 'c.attendance_id')
            ->leftJoin('users as requester', 'requester.id', '=', 'c.requested_by')->where('c.status', 'pending')
            ->when($tenantId, fn ($q) => $q->where('c.tenant_id', $tenantId))->when($branchIds !== null, fn ($q) => $q->whereIn('a.branch_id', $branchIds))
            ->selectRaw("'attendance' as kind, c.id, a.attendance_date as reference, c.reason as summary, NULL as amount, c.created_at, c.old_status, c.new_status, requester.name as requester_name")->get()
            ->map(function ($row) {
                $row->summary = ucfirst($row->old_status).' → '.ucfirst($row->new_status).': '.$row->summary;

                return $row;
            });
        $requests = $requests->merge($attendanceRequests);
        $refundRequests = DB::table('refund_requests as r')->join('payments as p', 'p.id', '=', 'r.payment_id')
            ->leftJoin('users as requester', 'requester.id', '=', 'r.requested_by')->where('r.status', 'pending')
            ->when($tenantId, fn ($q) => $q->where('r.tenant_id', $tenantId))->when($branchIds !== null, fn ($q) => $q->whereIn('p.branch_id', $branchIds))
            ->selectRaw("'refund' as kind, r.id, p.id as payment_id, p.transaction_reference as reference, r.reason as summary, r.amount, r.created_at, requester.name as requester_name")->get();
        $requests = $requests->merge($refundRequests);
        $paymentRequests = DB::table('payment_corrections as c')->join('payments as p', 'p.id', '=', 'c.payment_id')
            ->leftJoin('users as requester', 'requester.id', '=', 'c.requested_by')->where('c.status', 'pending')
            ->when($tenantId, fn ($q) => $q->where('c.tenant_id', $tenantId))->when($branchIds !== null, fn ($q) => $q->whereIn('p.branch_id', $branchIds))
            ->selectRaw("'payment' as kind, c.id, p.id as payment_id, p.transaction_reference as reference, c.reason as summary, c.proposed_changes as amount, c.created_at, requester.name as requester_name")->get();
        $requests = $requests->merge($paymentRequests);
        $receiptRequests = DB::table('receipt_cancellations as c')->join('receipts as r', 'r.id', '=', 'c.receipt_id')
            ->join('payments as p', 'p.id', '=', 'r.payment_id')->leftJoin('users as requester', 'requester.id', '=', 'c.requested_by')
            ->where('c.status', 'pending')
            ->when($tenantId, fn ($q) => $q->where('c.tenant_id', $tenantId))->when($branchIds !== null, fn ($q) => $q->whereIn('p.branch_id', $branchIds))
            ->selectRaw("'receipt' as kind, c.id, p.id as payment_id, r.receipt_number as reference, c.reason as summary, p.amount, c.created_at, requester.name as requester_name")->get();
        $requests = $requests->merge($receiptRequests);

        return view('approvals.index', ['requests' => $requests->sortByDesc('created_at')->values()]);
    }

    public function approveAttendance(Request $request, int $id)
    {
        return $this->decide($request, 'attendance', $id);
    }

    public function approveExpense(Request $request, int $id)
    {
        return $this->decide($request, 'expense', $id);
    }

    public function approveRefund(Request $request, int $id)
    {
        return $this->decide($request, 'refund', $id);
    }

    public function cancelReceipt(Request $request, int $id)
    {
        return $this->decide($request, 'receipt', $id);
    }

    public function correctPayment(Request $request, int $id)
    {
        return $this->decide($request, 'payment', $id);
    }

    private function decide(Request $request, string $kind, int $id)
    {
        $approve = $request->boolean('approve');
        $data = $request->validate([
            'decision_note' => 'nullable|string|max:1000',
        ]);
        if (! $approve && in_array($kind, ['refund', 'payment', 'receipt'], true) && blank($data['decision_note'] ?? null)) {
            throw ValidationException::withMessages([
                'decision_note' => 'Add a short reason when rejecting a financial adjustment.',
            ]);
        }

        DB::transaction(function () use ($request, $kind, $id, $approve, $data) {
            $tenantId = app(TenantContext::class)->id();
            $table = match ($kind) {
                'attendance' => 'attendance_corrections', 'expense' => 'expense_approvals', 'refund' => 'refund_requests',
                'receipt' => 'receipt_cancellations', 'payment' => 'payment_corrections',
            };
            $pending = DB::table($table)->where('id', $id)->where('status', 'pending')->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))->lockForUpdate()->first();
            abort_unless($pending, 404);

            $record = match ($kind) {
                'attendance' => Attendance::query()->findOrFail($pending->attendance_id),
                'expense' => Expense::query()->findOrFail($pending->expense_id),
                'refund' => Payment::query()->whereKey($pending->payment_id)->lockForUpdate()->firstOrFail(),
                'payment' => Payment::query()->whereKey($pending->payment_id)->lockForUpdate()->firstOrFail(),
                'receipt' => Receipt::query()->with('payment')->findOrFail($pending->receipt_id),
            };
            if ($kind === 'receipt') {
                Payment::query()->whereKey($record->payment_id)->lockForUpdate()->firstOrFail();
            }
            $branchId = $kind === 'attendance' || $kind === 'expense' ? $record->branch_id : ($kind === 'receipt' ? $record->payment->branch_id : $record->branch_id);
            abort_unless($request->user()->canAccessBranch($branchId), 403);

            if ($approve) {
                match ($kind) {
                    'attendance' => $record->update(['status' => $pending->new_status]),
                    'expense' => $record->update(['status' => 'approved']),
                    'refund' => $this->processRefund($pending, $record, $request->user()->id),
                    'payment' => $this->applyPaymentCorrection($pending, $record),
                    'receipt' => $this->cancelIssuedReceipt($record, $request->user()->id),
                };
            } elseif ($kind === 'expense') {
                $record->update(['status' => 'rejected']);
            }

            $decision = [
                'status' => $approve ? 'approved' : 'rejected',
                'approved_by' => $request->user()->id,
                'updated_at' => now(),
            ];
            if (in_array($kind, ['refund', 'payment', 'receipt'], true)) {
                $decision['review_note'] = $data['decision_note'] ?? null;
                $decision['decided_at'] = now();
            } elseif ($kind === 'expense') {
                $decision['remarks'] = $data['decision_note'] ?? null;
            }
            DB::table($table)->where('id', $id)->update($decision);
        });

        return back()->with('success', $approve ? 'Request approved and applied.' : 'Request rejected.');
    }

    private function processRefund(object $request, Payment $payment, int $userId): void
    {
        if ($payment->status !== 'completed') {
            throw ValidationException::withMessages(['refund' => 'Only a completed payment can be refunded.']);
        }

        $amountCents = (int) round((float) $request->amount * 100);
        $alreadyRefundedCents = (int) round((float) Refund::query()->where('payment_id', $payment->id)->where('status', 'processed')->sum('amount') * 100);
        if ($amountCents < 1 || $alreadyRefundedCents + $amountCents > (int) round((float) $payment->amount * 100)) {
            throw ValidationException::withMessages(['refund' => 'The approved refund exceeds the payment’s remaining refundable amount.']);
        }
        $refund = Refund::create(['payment_id' => $payment->id, 'processed_by' => $userId, 'amount' => number_format($amountCents / 100, 2, '.', ''), 'reason' => $request
            ->reason, 'status' => 'processed']);

        $remaining = $amountCents;
        $allocations = PaymentAllocation::query()->where('payment_id', $payment->id)->orderByDesc('id')->get();
        foreach ($allocations->groupBy(fn (PaymentAllocation $allocation) => (string) ($allocation->student_fee_assignment_id ?? 'unassigned')) as $rows) {
            if ($remaining < 1) {
                break;
            }
            $assignmentId = $rows->first()->student_fee_assignment_id;
            $netCents = (int) round((float) $rows->sum('amount') * 100);
            $reverseCents = min(max(0, $netCents), $remaining);
            if ($reverseCents < 1) {
                continue;
            }
            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'student_fee_assignment_id' => $assignmentId,
                'amount' => number_format(-$reverseCents / 100, 2, '.', ''),
                'allocation_type' => 'refund_reversal',
            ]);
            $remaining -= $reverseCents;
            if ($assignmentId !== null) {
                $this->refreshAssignmentStatus((int) $assignmentId);
            }
        }
    }

    private function applyPaymentCorrection(object $request, Payment $payment): void
    {
        if ($payment->status !== 'completed') {
            throw ValidationException::withMessages(['payment' => 'Only a completed payment can be corrected.']);
        }

        $changes = is_array($request->proposed_changes) ? $request->proposed_changes : json_decode($request->proposed_changes, true, 512, JSON_THROW_ON_ERROR);
        if (! isset($changes['amount'])) {
            return;
        }
        $newCents = (int) round((float) $changes['amount'] * 100);
        if ($newCents < 1) {
            throw ValidationException::withMessages(['amount' => 'Corrected payment amount must be greater than zero.']);
        }
        $refundedCents = (int) round((float) Refund::query()->where('payment_id', $payment->id)->where('status', 'processed')->sum('amount') * 100);
        if ($newCents < $refundedCents) {
            throw ValidationException::withMessages(['amount' => 'Corrected amount cannot be lower than refunds already issued.']);
        }
        $currentAllocatedCents = (int) round((float) PaymentAllocation::query()->where('payment_id', $payment->id)->sum('amount') * 100);
        if ($newCents < $currentAllocatedCents) {
            $remaining = $currentAllocatedCents - $newCents;
            $allocations = PaymentAllocation::query()->where('payment_id', $payment->id)->orderByDesc('id')->get();
            foreach ($allocations->groupBy(fn (PaymentAllocation $allocation) => (string) ($allocation->student_fee_assignment_id ?? 'unassigned')) as $rows) {
                if ($remaining < 1) {
                    break;
                }
                $assignmentId = $rows->first()->student_fee_assignment_id;
                $netCents = (int) round((float) $rows->sum('amount') * 100);
                $reverseCents = min(max(0, $netCents), $remaining);
                if ($reverseCents < 1) {
                    continue;
                }
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'student_fee_assignment_id' => $assignmentId,
                    'amount' => number_format(-$reverseCents / 100, 2, '.', ''),
                    'allocation_type' => 'correction_reversal',
                ]);
                $remaining -= $reverseCents;
                if ($assignmentId !== null) {
                    $this->refreshAssignmentStatus((int) $assignmentId);
                }
            }
        }

        if ($newCents > $currentAllocatedCents) {
            PaymentAllocation::create([
                'payment_id' => $payment->id,
                'student_fee_assignment_id' => null,
                'amount' => number_format(($newCents - $currentAllocatedCents) / 100, 2, '.', ''),
                'allocation_type' => 'correction_adjustment',
            ]);
        }

        $payment->update(['amount' => number_format($newCents / 100, 2, '.', '')]);
    }

    private function cancelIssuedReceipt(Receipt $receipt, int $userId): void
    {
        if ($receipt->status !== 'issued') {
            throw ValidationException::withMessages(['receipt' => 'Only an issued receipt can be cancelled.']);
        }
        $receipt->update(['status' => 'cancelled', 'cancelled_by' => $userId, 'cancelled_at' => now()]);
        $payment = $receipt->payment;
        $payment->update(['status' => 'cancelled']);
        $allocations = PaymentAllocation::query()->where('payment_id', $payment->id)->get();
        foreach ($allocations->groupBy(fn (PaymentAllocation $allocation) => (string) ($allocation->student_fee_assignment_id ?? 'unassigned')) as $rows) {
            $assignmentId = $rows->first()->student_fee_assignment_id;
            $netCents = (int) round((float) $rows->sum('amount') * 100);
            if ($netCents > 0) {
                PaymentAllocation::create([
                    'payment_id' => $payment->id,
                    'student_fee_assignment_id' => $assignmentId,
                    'amount' => number_format(-$netCents / 100, 2, '.', ''),
                    'allocation_type' => 'receipt_reversal',
                ]);
            }
            if ($assignmentId !== null) {
                $this->refreshAssignmentStatus((int) $assignmentId);
            }
        }
    }

    private function refreshAssignmentStatus(int $assignmentId): void
    {
        $assignment = StudentFeeAssignment::query()->findOrFail($assignmentId);
        $paid = (float) $assignment->allocations()->sum('amount');
        $due = $assignment->totalDue();
        $assignment->update(['status' => $paid >= $due ? 'paid' : ($paid > 0 ? 'partial' : 'open')]);
    }
}
