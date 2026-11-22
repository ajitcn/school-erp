@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Edit expense"
        description="Update this expense before its review is completed."
    >
        <x-slot:actions>
            <a
                class="btn btn-outline-secondary"
                href="{{ route('expenses.index', auth()->user()->currentBranchId() ? [] : ['branch_id' => $expense->branch_id]) }}"
            >
                Back to expenses
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.alert />
    <x-admin.validation-errors />

    <section class="admin-card p-3 p-md-4" aria-labelledby="expense-edit-heading">
        <div class="d-flex flex-wrap align-items-start justify-content-between mb-4" style="gap: 1rem">
            <div>
                <h2 id="expense-edit-heading" class="h5 mb-1">{{ $expense->voucher_number }}</h2>
                <p class="mb-0 small text-muted">Changes are allowed only while the expense is awaiting review.</p>
            </div>
            <x-admin.badge tone="warning">Pending review</x-admin.badge>
        </div>

        <form method="post" action="{{ route('expenses.update', $expense) }}" enctype="multipart/form-data">
            @csrf
            @method('put')

            <div class="form-row">
                <div class="form-group col-md-6 col-xl-3">
                    <label for="expense_category_id">Category</label>
                    <select id="expense_category_id" name="expense_category_id" class="form-control @error('expense_category_id') is-invalid @enderror" required>
                        <option value="">Choose a category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('expense_category_id', $expense->expense_category_id) == $category->id)>
                                {{ $category->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('expense_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-group col-md-6 col-xl-3">
                    <label for="expense_date">Expense date</label>
                    <input
                        id="expense_date"
                        name="expense_date"
                        type="text"
                        class="form-control js-datepicker @error('expense_date') is-invalid @enderror"
                        value="{{ old('expense_date', $expense->expense_date?->format('d/m/Y')) }}"
                        autocomplete="off"
                        required
                    >
                    @error('expense_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-group col-md-6 col-xl-3">
                    <label for="amount">Amount</label>
                    <div class="input-group">
                        <div class="input-group-prepend"><span class="input-group-text">₹</span></div>
                        <input id="amount" name="amount" type="number" min="0.01" step="0.01" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $expense->amount) }}" required>
                    </div>
                    @error('amount')<small class="text-danger">{{ $message }}</small>@enderror
                </div>

                <div class="form-group col-md-6 col-xl-3">
                    <label for="payment_mode">Payment mode</label>
                    <select id="payment_mode" name="payment_mode" class="form-control @error('payment_mode') is-invalid @enderror" required>
                        @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank_transfer' => 'Bank transfer'] as $mode => $label)
                            <option value="{{ $mode }}" @selected(old('payment_mode', $expense->payment_mode) === $mode)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('payment_mode')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-group col-lg-8">
                    <label for="description">Description</label>
                    <input id="description" name="description" class="form-control @error('description') is-invalid @enderror" value="{{ old('description', $expense->description) }}" required>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-group col-lg-4">
                    <label for="vendor_name">Vendor or person <span class="text-muted font-weight-normal">(optional)</span></label>
                    <input id="vendor_name" name="vendor_name" class="form-control @error('vendor_name') is-invalid @enderror" value="{{ old('vendor_name', $expense->vendor_name) }}">
                    @error('vendor_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="form-group col-12">
                    <label for="bill">Bill or receipt <span class="text-muted font-weight-normal">(PDF/JPG/PNG, up to 10 MB)</span></label>
                    @if ($expense->bill_path)
                        <p class="small mb-2"><a href="{{ route('expenses.bill', $expense) }}">View current bill</a><span class="text-muted"> · Upload a new file to replace it.</span></p>
                    @endif
                    <input id="bill" type="file" name="bill" class="form-control-file @error('bill') is-invalid @enderror" accept=".pdf,.jpg,.jpeg,.png">
                    @error('bill')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
            </div>

            <div class="d-flex flex-wrap justify-content-end" style="gap: .5rem">
                <a
                    class="btn btn-outline-secondary"
                    href="{{ route('expenses.index', auth()->user()->currentBranchId() ? [] : ['branch_id' => $expense->branch_id]) }}"
                >Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-check mr-1" aria-hidden="true"></i>Save changes
                </button>
            </div>
        </form>
    </section>
@endsection
