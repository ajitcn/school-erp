<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Records branch expenses and their approval workflow.
 */
class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $branchId = $user->currentBranchId() ?: ($request->filled('branch_id') ? $request->integer('branch_id') : null);
        if ($branchId) {
            abort_unless($user->canAccessBranch($branchId), 403);
        }
        $accessibleBranches = $user->accessibleBranchIds();
        $filters = $request->validate([
            'status' => 'nullable|in:pending,approved,rejected',
            'expense_category_id' => [
                'nullable',
                'integer',
                Rule::exists('expense_categories', 'id')
                    ->where('tenant_id', (int) app(TenantContext::class)->id()),
            ],
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ]);

        $expenseScope = Expense::query()
            ->when($accessibleBranches !== null, fn ($query) => $query
                ->whereIn('branch_id', $accessibleBranches))
            ->when($branchId, fn ($query) => $query
                ->where('branch_id', $branchId));

        $summary = (clone $expenseScope)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->selectRaw("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending_amount")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'approved' THEN amount ELSE 0 END), 0) as approved_amount")
            ->selectRaw("SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_count")
            ->first();

        $expenses = (clone $expenseScope)
            ->addSelect([
                'has_pending_review' => DB::table('expense_approvals')
                    ->selectRaw('1')
                    ->whereColumn('expense_approvals.expense_id', 'expenses.id')
                    ->where('expense_approvals.tenant_id', (int) app(TenantContext::class)->id())
                    ->where('expense_approvals.status', 'pending')
                    ->limit(1),
            ])
            ->with('category')
            ->when($filters['status'] ?? null, fn ($query, $status) => $query
                ->where('status', $status))
            ->when($filters['expense_category_id'] ?? null, fn ($query, $categoryId) => $query
                ->where('expense_category_id', $categoryId))
            ->when($filters['date_from'] ?? null, fn ($query, $date) => $query
                ->whereDate('expense_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn ($query, $date) => $query
                ->whereDate('expense_date', '<=', $date))
            ->latest('expense_date')
            ->paginate(15)
            ->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'summary' => $summary,
            'filters' => $filters,
            'categories' => ExpenseCategory::query()->where('is_active', true)->orderBy('name')->get(),
            'categoryCatalog' => ExpenseCategory::query()->orderBy('name')->get(),
            'branches' => Branch::query()->where('is_active', true)->when($accessibleBranches !== null, fn ($q) => $q->whereIn('id', $accessibleBranches))->orderBy('name')->get(),
            'branchId' => $branchId,
        ]);
    }

    /** Show the edit form only while the expense review is still pending. */
    public function edit(Request $request, Expense $expense)
    {
        $this->ensureExpenseCanBeEdited($request, $expense);

        return view('expenses.edit', [
            'expense' => $expense->load('category'),
            'categories' => ExpenseCategory::query()
                ->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->orWhereKey($expense->expense_category_id))
                ->orderBy('name')
                ->get(),
        ]);
    }

    /** Update a pending expense without changing its branch, voucher, or approval history. */
    public function update(Request $request, Expense $expense)
    {
        $this->ensureExpenseCanBeEdited($request, $expense);

        $tenantId = (int) app(TenantContext::class)->id();
        $data = $request->validate([
            'expense_category_id' => [
                'required',
                'integer',
                Rule::exists('expense_categories', 'id')
                    ->where('tenant_id', $tenantId),
            ],
            'expense_date' => 'required|date',
            'description' => 'required|string|max:255',
            'payment_mode' => 'required|in:cash,upi,card,bank_transfer',
            'amount' => 'required|numeric|min:0.01',
            'vendor_name' => 'nullable|string|max:150',
            'bill' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        $replacementBill = $request->file('bill');
        unset($data['bill']);

        $replacementPath = $replacementBill?->store('expense-bills/'.$expense->branch_id, 'local');
        $previousBillPath = $expense->bill_path;

        try {
            DB::transaction(function () use ($request, $expense, $data, $tenantId, $replacementPath) {
                $lockedExpense = Expense::query()
                    ->whereKey($expense->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                abort_unless($request->user()->canAccessBranch($lockedExpense->branch_id), 403);

                $pendingReview = DB::table('expense_approvals')
                    ->where('expense_id', $lockedExpense->id)
                    ->where('tenant_id', $tenantId)
                    ->where('status', 'pending')
                    ->lockForUpdate()
                    ->exists();

                if ($lockedExpense->status !== 'pending' || ! $pendingReview) {
                    throw ValidationException::withMessages([
                        'expense' => 'This expense can no longer be edited because its review has started or finished.',
                    ]);
                }

                $lockedExpense->fill($data);

                if ($replacementPath) {
                    $lockedExpense->bill_path = $replacementPath;
                }

                $lockedExpense->save();
            });
        } catch (Throwable $exception) {
            if ($replacementPath) {
                Storage::disk('local')->delete($replacementPath);
            }

            throw $exception;
        }

        if ($replacementPath && $previousBillPath) {
            Storage::disk('local')->delete($previousBillPath);
        }

        return redirect()->route('expenses.index', $request->user()->currentBranchId() ? [] : ['branch_id' => $expense->branch_id])
            ->with('success', 'Expense updated and remains pending approval.');
    }

    /** Check tenant, branch, and pending-review boundaries before showing or saving edits. */
    private function ensureExpenseCanBeEdited(Request $request, Expense $expense): void
    {
        abort_unless($request->user()->canAccessBranch($expense->branch_id), 404);

        $hasPendingReview = DB::table('expense_approvals')
            ->where('expense_id', $expense->id)
            ->where('tenant_id', (int) app(TenantContext::class)->id())
            ->where('status', 'pending')
            ->exists();

        abort_unless($expense->status === 'pending' && $hasPendingReview, 404);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => auth()->user()->currentBranchId() ? 'nullable' : 'required|integer',
            'expense_category_id' => 'required|integer',
            'expense_date' => 'required|date',
            'description' => 'required|string|max:255',
            'payment_mode' => 'required|in:cash,upi,card,bank_transfer',
            'amount' => 'required|numeric|min:0.01',
            'vendor_name' => 'nullable|string|max:150',
            'bill' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);
        $branchId = auth()->user()->currentBranchId() ?: (int) $data['branch_id'];
        abort_unless(auth()->user()->canAccessBranch($branchId) && Branch::query()->whereKey($branchId)->exists(), 403);
        if (! ExpenseCategory::query()->whereKey($data['expense_category_id'])->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['expense_category_id' => 'Choose an active expense category.']);
        }
        $bill = $request->file('bill');
        unset($data['branch_id'], $data['bill']);

        $billPath = $bill?->store('expense-bills/'.$branchId, 'local');
        DB::transaction(function () use ($data, $branchId, $billPath) {
            $expense = Expense::create($data + [
                'branch_id' => $branchId,
                'entered_by' => auth()->id(),
                'voucher_number' => 'VCH-'.now()->format('YmdHis').'-'.str()->upper(str()->random(4)),
                'status' => 'pending',
                'bill_path' => $billPath,
            ]);
            DB::table('expense_approvals')->insert([
                'expense_id' => $expense->id,
                'tenant_id' => app(TenantContext::class)->id(),
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return redirect()->route('expenses.index', auth()->user()->currentBranchId() ? [] : ['branch_id' => $branchId])->with('success', 'Expense submitted for approval.');
    }

    /** Create a tenant-wide expense category for future expense entries. */
    public function storeCategory(Request $request)
    {
        $tenantId = (int) app(TenantContext::class)->id();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->where('tenant_id', $tenantId)],
        ]);

        ExpenseCategory::query()->create($data + ['is_active' => true]);

        return back()->with('success', 'Expense category created.');
    }

    /** Rename a tenant category without affecting expenses that already use it. */
    public function updateCategory(Request $request, ExpenseCategory $category)
    {
        $tenantId = (int) app(TenantContext::class)->id();
        abort_unless((int) $category->tenant_id === $tenantId, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->where('tenant_id', $tenantId)->ignore($category->id)],
        ]);
        $category->update($data);

        return back()->with('success', 'Expense category updated.');
    }

    /** Toggle a category while retaining it for existing expense history. */
    public function toggleCategory(Request $request, ExpenseCategory $category)
    {
        abort_unless((int) $category->tenant_id === (int) app(TenantContext::class)->id(), 404);
        $category->update(['is_active' => ! $category->is_active]);

        return back()->with('success', 'Expense category status updated.');
    }

    /** Delete an unused category; categories with expense records must be deactivated instead. */
    public function destroyCategory(Request $request, ExpenseCategory $category)
    {
        abort_unless((int) $category->tenant_id === (int) app(TenantContext::class)->id(), 404);

        if ($category->expenses()->exists()) {
            return back()->withErrors(['category' => 'This category is linked to expense history. Deactivate it to keep existing records intact.']);
        }

        $category->delete();

        return back()->with('success', 'Unused expense category deleted.');
    }

    public function bill(Request $request, Expense $expense)
    {
        abort_unless($request->user()->canAccessBranch($expense->branch_id), 403);
        abort_unless($expense->bill_path && Storage::disk('local')->exists($expense->bill_path), 404);

        return Storage::disk('local')->download($expense->bill_path, basename($expense->bill_path));
    }

    /** Preview a stored expense bill for authorized users without exposing its storage path. */
    public function previewBill(Request $request, Expense $expense): BinaryFileResponse
    {
        abort_unless($request->user()->canAccessBranch($expense->branch_id), 403);
        abort_unless($expense->bill_path && Storage::disk('local')->exists($expense->bill_path), 404);

        $disk = Storage::disk('local');
        $mimeType = $disk->mimeType($expense->bill_path);
        abort_unless(in_array($mimeType, ['application/pdf', 'image/jpeg', 'image/png'], true), 404);

        return response()->file($disk->path($expense->bill_path), [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
