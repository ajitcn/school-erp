<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Payment;
use App\Models\PaymentTender;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Reconciles branch cash drawers and external payment totals. */
class ReconciliationController extends Controller
{
    /** Show the reconciliation forms and branch-scoped submission history. */
    public function index(Request $request)
    {
        $user = $request->user();
        $currentBranchId = $user->currentBranchId();
        $branchId = $currentBranchId ?: ($request->filled('branch_id') ? $request->integer('branch_id') : null);
        $accessibleBranches = $user->accessibleBranchIds();

        if ($branchId) {
            abort_unless($user->canAccessBranch($branchId), 403);
        }

        $tenantId = app(TenantContext::class)->id();
        $branches = Branch::query()
            ->where('is_active', true)
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('id', $accessibleBranches))
            ->orderBy('name')
            ->get();

        $closings = DB::table('cashier_closings as closings')
            ->join('branches', 'branches.id', '=', 'closings.branch_id')
            ->join('users', 'users.id', '=', 'closings.user_id')
            ->when($tenantId, fn ($query) => $query->where('closings.tenant_id', $tenantId))
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('closings.branch_id', $accessibleBranches))
            ->when($branchId, fn ($query) => $query->where('closings.branch_id', $branchId))
            ->orderByDesc('closings.closing_date')
            ->orderByDesc('closings.updated_at')
            ->select([
                'closings.*',
                'branches.name as branch_name',
                'users.name as cashier_name',
            ])
            ->paginate(10)
            ->withQueryString();

        $reconciliations = DB::table('payment_reconciliations as reconciliations')
            ->join('branches', 'branches.id', '=', 'reconciliations.branch_id')
            ->join('users', 'users.id', '=', 'reconciliations.created_by')
            ->when($tenantId, fn ($query) => $query->where('reconciliations.tenant_id', $tenantId))
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('reconciliations.branch_id', $accessibleBranches))
            ->when($branchId, fn ($query) => $query->where('reconciliations.branch_id', $branchId))
            ->orderByDesc('reconciliations.created_at')
            ->select([
                'reconciliations.*',
                'branches.name as branch_name',
                'users.name as reconciler_name',
            ])
            ->paginate(10, ['*'], 'reconcile_page')
            ->withQueryString();

        $closingDate = today()->toDateString();
        $expectedCash = $branchId ? $this->expectedCashFor($branchId, $closingDate) : 0.0;
        $existingClosing = $branchId
            ? DB::table('cashier_closings')
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $branchId)
                ->where('user_id', $user->id)
                ->whereDate('closing_date', $closingDate)
                ->first()
            : null;

        return view('reconciliation.index', compact(
            'branchId',
            'branches',
            'closings',
            'reconciliations',
            'closingDate',
            'expectedCash',
            'existingClosing',
        ));
    }

    /** Return the expected cash total used to prepare a cashier closing. */
    public function expectedCash(Request $request): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => 'required|integer',
            'date' => 'required|date_format:Y-m-d|before_or_equal:today',
        ]);
        $branchId = $this->branchId($request, $data);
        $existingClosing = DB::table('cashier_closings')
            ->where('tenant_id', app(TenantContext::class)->id())
            ->where('branch_id', $branchId)
            ->where('user_id', $request->user()->id)
            ->whereDate('closing_date', $data['date'])
            ->first(['actual_cash', 'notes']);

        return response()->json([
            'expected_cash' => $this->expectedCashFor($branchId, $data['date']),
            'actual_cash' => $existingClosing?->actual_cash,
            'notes' => $existingClosing?->notes,
        ]);
    }

    /** Save or update one cashier's closing for a branch and date. */
    public function close(Request $request)
    {
        $data = $request->validate([
            'branch_id' => $request->user()->currentBranchId() ? 'nullable|integer' : 'required|integer',
            'closing_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'actual_cash' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);
        $branchId = $this->branchId($request, $data);
        $expected = $this->expectedCashFor($branchId, $data['closing_date']);
        $actual = round((float) $data['actual_cash'], 2);

        DB::transaction(function () use ($request, $data, $branchId, $expected, $actual) {
            $tenantId = app(TenantContext::class)->id();
            $closing = DB::table('cashier_closings')
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $branchId)
                ->where('user_id', $request->user()->id)
                ->whereDate('closing_date', $data['closing_date'])
                ->lockForUpdate()
                ->first();

            $values = [
                'expected_cash' => $expected,
                'actual_cash' => $actual,
                'variance' => round($actual - $expected, 2),
                'notes' => $data['notes'] ?? null,
                'status' => 'submitted',
                'updated_at' => now(),
            ];

            if ($closing) {
                DB::table('cashier_closings')->where('id', $closing->id)->update($values);

                return;
            }

            DB::table('cashier_closings')->insert($values + [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'user_id' => $request->user()->id,
                'closing_date' => $data['closing_date'],
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('reconciliation.index', ['branch_id' => $branchId])
            ->with('success', 'Cashier closing saved.');
    }

    /** Save an external total comparison for a branch, date range, and payment method. */
    public function reconcile(Request $request)
    {
        $data = $request->validate([
            'branch_id' => $request->user()->currentBranchId() ? 'nullable|integer' : 'required|integer',
            'from_date' => 'required|date_format:Y-m-d|before_or_equal:today',
            'to_date' => 'required|date_format:Y-m-d|after_or_equal:from_date|before_or_equal:today',
            'payment_mode' => 'required|in:cash,upi,card,bank_transfer,online',
            'bank_total' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);
        $branchId = $this->branchId($request, $data);
        $system = $this->systemTotalFor($branchId, $data['from_date'], $data['to_date'], $data['payment_mode']);
        $external = round((float) $data['bank_total'], 2);

        DB::table('payment_reconciliations')->insert([
            'tenant_id' => app(TenantContext::class)->id(),
            'branch_id' => $branchId,
            'created_by' => $request->user()->id,
            'from_date' => $data['from_date'],
            'to_date' => $data['to_date'],
            'payment_mode' => $data['payment_mode'],
            'system_total' => $system,
            'bank_total' => $external,
            'variance' => round($external - $system, 2),
            'status' => 'submitted',
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('reconciliation.index', ['branch_id' => $branchId])
            ->with('success', 'Payment reconciliation saved.');
    }

    /** Resolve and authorize the active branch before reading or writing its financial data. */
    private function branchId(Request $request, array $data): int
    {
        $branchId = (int) ($request->user()->currentBranchId() ?: ($data['branch_id'] ?? 0));
        abort_unless(
            $branchId > 0
                && $request->user()->canAccessBranch($branchId)
                && Branch::query()->whereKey($branchId)->exists(),
            403,
        );

        return $branchId;
    }

    /** Sum cash receipts, including mixed tender payments, without double-counting them. */
    private function expectedCashFor(int $branchId, string $date): float
    {
        $legacyCash = Payment::query()
            ->where('branch_id', $branchId)
            ->where('payment_mode', 'cash')
            ->whereDoesntHave('paymentTenders')
            ->whereDate('paid_at', $date)
            ->where('status', 'completed')
            ->sum('amount');

        $tenderCash = PaymentTender::query()
            ->where('payment_mode', 'cash')
            ->whereHas('payment', fn ($payment) => $payment
                ->where('branch_id', $branchId)
                ->whereDate('paid_at', $date)
                ->where('status', 'completed'))
            ->sum('amount');

        return round((float) $legacyCash + (float) $tenderCash, 2);
    }

    /** Sum legacy and split-tender collections for a selected method and date range. */
    private function systemTotalFor(int $branchId, string $fromDate, string $toDate, string $paymentMode): float
    {
        $legacyTotal = Payment::query()
            ->where('branch_id', $branchId)
            ->where('payment_mode', $paymentMode)
            ->whereDoesntHave('paymentTenders')
            ->whereBetween(DB::raw('DATE(paid_at)'), [$fromDate, $toDate])
            ->where('status', 'completed')
            ->sum('amount');

        $tenderTotal = PaymentTender::query()
            ->where('payment_mode', $paymentMode)
            ->whereHas('payment', fn ($payment) => $payment
                ->where('branch_id', $branchId)
                ->whereBetween(DB::raw('DATE(paid_at)'), [$fromDate, $toDate])
                ->where('status', 'completed'))
            ->sum('amount');

        return round((float) $legacyTotal + (float) $tenderTotal, 2);
    }
}
