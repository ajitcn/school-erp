@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Expenses"
        description="Record operating costs, track approvals, and review supporting receipts."
    >
        <x-slot:actions>
            @if ($branchId || auth()->user()->currentBranchId())
            <button
                type="button"
                class="btn btn-primary"
                data-expense-form-toggle
                aria-controls="record-expense-form"
                aria-expanded="{{ $errors->any() && old('description') !== null ? 'true' : 'false' }}"
            >
                <i class="fa-solid {{ $errors->any() && old('description') !== null ? 'fa-xmark' : 'fa-plus' }} mr-1" data-expense-toggle-icon aria-hidden="true"></i>
                <span data-expense-toggle-label>{{ $errors->any() && old('description') !== null ? 'Close form' : 'Record expense' }}</span>
            </button>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.alert />
    <x-admin.validation-errors />

    <section class="expense-summary" aria-label="Expense summary">
        <article class="expense-summary-card expense-summary-card--pending">
            <span class="expense-summary-icon" aria-hidden="true"><i class="fa-solid fa-hourglass-half"></i></span>
            <div><span>Pending approval</span><strong>{{ number_format((int) $summary->pending_count) }}</strong><small>₹{{ number_format((float) $summary->pending_amount, 2) }}</small></div>
        </article>
        <article class="expense-summary-card expense-summary-card--approved">
            <span class="expense-summary-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
            <div><span>Approved</span><strong>{{ number_format((int) $summary->approved_count) }}</strong><small>₹{{ number_format((float) $summary->approved_amount, 2) }}</small></div>
        </article>
        <article class="expense-summary-card expense-summary-card--rejected">
            <span class="expense-summary-icon" aria-hidden="true"><i class="fa-solid fa-circle-xmark"></i></span>
            <div><span>Rejected</span><strong>{{ number_format((int) $summary->rejected_count) }}</strong><small>Needs review</small></div>
        </article>
        <article class="expense-summary-card expense-summary-card--total">
            <span class="expense-summary-icon" aria-hidden="true"><i class="fa-solid fa-indian-rupee-sign"></i></span>
            <div><span>Total recorded</span><strong>₹{{ number_format((float) $summary->total_amount, 2) }}</strong><small>{{ number_format((int) $summary->total_count) }} expenses</small></div>
        </article>
    </section>

    @if (! auth()->user()->currentBranchId() && ! $branchId)
        <div class="admin-card mb-4 p-3">
            <form method="get" action="{{ route('expenses.index') }}" class="form-row align-items-end">
                <div class="form-group col-md-5 mb-md-0">
                    <label for="branch_filter">Branch</label>
                    <select id="branch_filter" name="branch_id" class="form-control" required>
                        <option value="">Choose a branch</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($branchId == $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div><button class="btn btn-outline-primary">Load branch expenses</button></div>
            </form>
        </div>
    @endif

    <form method="get" action="{{ route('expenses.index') }}" class="js-filter-form admin-card p-3 mb-4" aria-label="Expense filters">
        @if (! auth()->user()->currentBranchId() && $branchId)
            <input type="hidden" name="branch_id" value="{{ $branchId }}">
        @endif
        <div class="form-row align-items-end">
            <div class="form-group col-md-3">
                <label for="filter_status">Status</label>
                <select id="filter_status" name="status" class="form-control">
                    <option value="">All statuses</option>
                    @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $status => $label)
                        <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-3">
                <label for="filter_category">Category</label>
                <select id="filter_category" name="expense_category_id" class="form-control">
                    <option value="">All categories</option>
                    @foreach ($categoryCatalog as $category)
                        <option value="{{ $category->id }}" @selected(($filters['expense_category_id'] ?? '') == $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2">
                <label for="date_from">From date</label>
                <input id="date_from" name="date_from" type="text" class="form-control js-datepicker" value="{{ $filters['date_from'] ?? '' }}" autocomplete="off" placeholder="DD/MM/YYYY">
            </div>
            <div class="form-group col-md-2">
                <label for="date_to">To date</label>
                <input id="date_to" name="date_to" type="text" class="form-control js-datepicker" value="{{ $filters['date_to'] ?? '' }}" autocomplete="off" placeholder="DD/MM/YYYY">
            </div>
            <div class="form-group col-md-2 d-flex" style="gap: .5rem">
                <button class="btn btn-primary flex-fill">Apply</button>
                <a class="btn btn-outline-secondary" href="{{ route('expenses.index', $branchId && ! auth()->user()->currentBranchId() ? ['branch_id' => $branchId] : []) }}">Reset</a>
            </div>
        </div>
    </form>

    @if ($branchId || auth()->user()->currentBranchId())
    <form id="record-expense-form" method="post" action="{{ route('expenses.store') }}" enctype="multipart/form-data" class="admin-card p-3 p-md-4 mb-4" @if (! $errors->any() || old('description') === null) hidden @endif>
        @csrf
        @if (!auth()->user()->currentBranchId())<input type="hidden" name="branch_id" value="{{ $branchId }}">@endif
        <div class="expense-form-heading">
            <div><h2 class="h5 mb-1">Record an expense</h2><p class="mb-0 small text-muted">Submit the amount, payment details, and receipt for approval.</p></div>
        </div>

        <div class="form-row">
            <div class="form-group col-lg-3 col-md-6">
                <label for="expense_category_id">Category</label>
                <select id="expense_category_id" name="expense_category_id" class="form-control @error('expense_category_id') is-invalid @enderror" required>
                    <option value="">Choose a category</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected(old('expense_category_id') == $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                @error('expense_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-group col-lg-3 col-md-6">
                <label for="expense_date">Expense date</label>
                <input id="expense_date" type="text" class="form-control js-datepicker @error('expense_date') is-invalid @enderror" name="expense_date" value="{{ old('expense_date', now()->format('d/m/Y')) }}" autocomplete="off" required>
                @error('expense_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-group col-lg-3 col-md-6">
                <label for="amount">Amount</label>
                <input id="amount" type="number" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror" name="amount" value="{{ old('amount') }}" placeholder="0.00" required>
                @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-group col-lg-3 col-md-6">
                <label for="payment_mode">Payment mode</label>
                <select id="payment_mode" name="payment_mode" class="form-control @error('payment_mode') is-invalid @enderror" required>
                    <option value="">Choose a mode</option>
                    @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank_transfer' => 'Bank transfer'] as $mode => $label)
                        <option value="{{ $mode }}" @selected(old('payment_mode') === $mode)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('payment_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-group col-lg-8">
                <label for="description">Description</label>
                <input id="description" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description') }}" placeholder="What was this expense for?" required>
                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-group col-lg-4">
                <label for="vendor_name">Vendor or person <span class="text-muted font-weight-normal">(optional)</span></label>
                <input id="vendor_name" name="vendor_name" class="form-control @error('vendor_name') is-invalid @enderror" value="{{ old('vendor_name') }}" placeholder="Vendor name">
                @error('vendor_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-group col-lg-6"><label for="bill">Bill or receipt <span class="text-muted font-weight-normal">(PDF/JPG/PNG, up to 10 MB)</span></label><input id="bill" type="file" name="bill" class="form-control-file" accept=".pdf,.jpg,.jpeg,.png">@error('bill')<div class="text-danger small">{{ $message }}</div>@enderror</div>
        </div>

        <div class="d-flex justify-content-end">
            <button class="btn btn-primary" type="submit">
                <i class="fa-solid fa-plus mr-1" aria-hidden="true"></i>
                Submit expense
            </button>
        </div>
    </form>
    @else
        <div class="alert alert-info">Choose a branch before recording an expense.</div>
    @endif

    <details class="admin-card mb-4">
        <summary class="p-3 font-weight-bold">Manage expense categories</summary>
        @error('category')<div class="alert alert-warning mx-3" role="alert">{{ $message }}</div>@enderror
        <form method="post" action="{{ route('expenses.categories.store') }}" class="form-row align-items-end px-3">
            @csrf
            <div class="form-group col-md-6">
                <label for="expense_category_name">New category name</label>
                <input id="expense_category_name" name="name" class="form-control" maxlength="100" required>
            </div>
            <div class="form-group col-md-auto"><button class="btn btn-primary">Add category</button></div>
        </form>
        <x-admin.table>
            <thead><tr><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
                @forelse ($categoryCatalog as $category)
                    <tr>
                        <td>
                            <form method="post" action="{{ route('expenses.categories.update', $category) }}" class="form-row align-items-center">
                                @csrf @method('put')
                                <div class="col-md-8"><label class="sr-only" for="expense-category-{{ $category->id }}">Category name</label><input id="expense-category-{{ $category->id }}" name="name" class="form-control" value="{{ $category->name }}" required></div>
                                <div class="col-md-auto"><button class="btn btn-sm btn-outline-primary">Save name</button></div>
                            </form>
                        </td>
                        <td>{{ $category->is_active ? 'Active' : 'Inactive' }}</td>
                        <td>
                            <div class="d-flex flex-wrap" style="gap: .5rem">
                                <form method="post" action="{{ route('expenses.categories.toggle', $category) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $category->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                                <form method="post" action="{{ route('expenses.categories.destroy', $category) }}" onsubmit="return confirm('Delete this category if it has no expense history?');">@csrf @method('delete')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="text-muted">No categories are set up yet.</td></tr>
                @endforelse
            </tbody>
        </x-admin.table>
    </details>

    <section class="admin-card" aria-labelledby="expense-history-title">
        <div class="card-header d-flex align-items-center justify-content-between">
            <div>
                <h2 id="expense-history-title">Expense history</h2>
                <p class="mb-0 mt-1 small text-muted">Recent expenses recorded for this school.</p>
            </div>
            <x-admin.badge tone="slate">{{ number_format($expenses->total()) }} records</x-admin.badge>
        </div>

        <x-admin.table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th class="text-right">Amount</th>
                    <th>Receipt</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $expense)
                    @php
                        $statusTone = match ($expense->status) {
                            'approved' => 'success',
                            'rejected' => 'danger',
                            default => 'warning',
                        };
                    @endphp
                    <tr>
                        <td>{{ $expense->expense_date?->format('d M Y') ?? '—' }}</td>
                        <td><span class="text-muted">{{ $expense->voucher_number }}</span></td>
                        <td>{{ $expense->category?->name ?? 'Uncategorised' }}</td>
                        <td>{{ $expense->description }}</td>
                        <td><x-admin.badge :tone="$statusTone">{{ ucfirst($expense->status) }}</x-admin.badge></td>
                        <td class="text-right font-weight-bold">₹{{ number_format((float) $expense->amount, 2) }}</td>
                        <td>@if ($expense->bill_path)<a class="btn btn-sm btn-outline-secondary" href="{{ route('expenses.bill', $expense) }}"><i class="fa-solid fa-paperclip mr-1" aria-hidden="true"></i>View bill</a>@else<span class="text-muted">—</span>@endif</td>
                        <td class="text-right">
                            @if ($expense->status === 'pending' && $expense->has_pending_review)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('expenses.edit', $expense) }}">
                                    <i class="fa-solid fa-pen-to-square mr-1" aria-hidden="true"></i>Edit
                                </a>
                            @else
                                <span class="text-muted small">Review started</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <x-admin.empty-state title="No expenses recorded" description="Submit an expense above to begin your expense history." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>

        @if ($expenses->hasPages())
            <div class="p-3 border-top">
                <x-admin.pagination :paginator="$expenses" />
            </div>
        @endif
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.querySelector('[data-expense-form-toggle]');
            const form = document.getElementById('record-expense-form');

            if (! toggle || ! form) {
                return;
            }

            const label = toggle.querySelector('[data-expense-toggle-label]');
            const icon = toggle.querySelector('[data-expense-toggle-icon]');

            toggle.addEventListener('click', () => {
                const willOpen = form.hidden;
                form.hidden = ! willOpen;
                toggle.setAttribute('aria-expanded', String(willOpen));
                label.textContent = willOpen ? 'Close form' : 'Record expense';
                icon.classList.toggle('fa-plus', ! willOpen);
                icon.classList.toggle('fa-xmark', willOpen);

                if (willOpen) {
                    form.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    form.querySelector('select, input:not([type="hidden"])')?.focus({ preventScroll: true });
                }
            });
        });
    </script>
@endsection
