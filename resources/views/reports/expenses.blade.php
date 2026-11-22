@extends('layouts.app')

@section('content')
    @php
        $periodLabels = [
            'day' => 'Today',
            'week' => 'This week',
            'month' => 'This month',
            'quarter' => 'This quarter',
            'year' => 'This year',
            'financial_year' => 'Financial year',
        ];
        $period = $data['period'] ?? null;
        $periodLabel = $period
            ? ($periodLabels[$period] ?? 'Selected period')
            : 'Custom date range';
        $dateRange = ! empty($data['from_date']) && ! empty($data['to_date'])
            ? \Illuminate\Support\Carbon::parse($data['from_date'])->format('d M Y').' – '.\Illuminate\Support\Carbon::parse($data['to_date'])->format('d M Y')
            : 'All dates';
    @endphp

    <x-admin.page-header
        title="Expense report"
        description="{{ $branchName }} · {{ $periodLabel }} · {{ $dateRange }}"
    >
        <x-slot name="actions">
            <button
                id="expense-filter-toggle"
                class="btn btn-outline-secondary"
                type="button"
                aria-controls="expense-filters"
                aria-expanded="false"
            >
                <i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>
                Filters
            </button>
            @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
                <a
                    class="btn btn-outline-primary"
                    href="{{ route('reports.export', ['type' => 'expenses', 'format' => $format] + request()->except('page')) }}"
                >{{ $label }}</a>
            @endforeach
            <button class="btn btn-outline-secondary" type="button" onclick="window.print()">Print</button>
        </x-slot>
    </x-admin.page-header>

    <section id="expense-filters" class="admin-card p-3 p-md-4 mb-4" hidden aria-label="Expense report filters">
        <form method="get" action="{{ route('reports.expenses') }}" class="form-row align-items-end">
            <div class="col-lg-3 col-md-6 form-group">
                <label for="branch_id">Branch</label>
                <select id="branch_id" name="branch_id" class="form-control">
                    <option value="">All permitted branches</option>
                    @foreach ($branchOptions as $branch)
                        <option value="{{ $branch->id }}" @selected($branchId == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-6 form-group">
                <label for="period">Reporting period</label>
                <select id="period" name="period" class="form-control">
                    <option value="">Custom dates</option>
                    @foreach ($periodLabels as $key => $label)
                        <option value="{{ $key }}" @selected(($data['period'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6 form-group">
                <label for="from_date">From date</label>
                <input
                    id="from_date"
                    name="from_date"
                    type="text"
                    class="form-control js-datepicker"
                    value="{{ $data['from_date'] ?? '' }}"
                    autocomplete="off"
                    placeholder="DD/MM/YYYY"
                >
            </div>
            <div class="col-lg-2 col-md-6 form-group">
                <label for="to_date">To date</label>
                <input
                    id="to_date"
                    name="to_date"
                    type="text"
                    class="form-control js-datepicker"
                    value="{{ $data['to_date'] ?? '' }}"
                    autocomplete="off"
                    placeholder="DD/MM/YYYY"
                >
            </div>
            <div class="col-lg-2 col-md-6 form-group d-flex align-items-end">
                <button class="btn btn-primary mr-2" type="submit">Apply</button>
                <a class="btn btn-outline-secondary" href="{{ route('reports.expenses') }}">Reset</a>
            </div>
        </form>
    </section>

    <section class="row expense-report-summary mb-4" aria-label="Expense summary">
        <div class="col-xl-4 col-md-6 mb-3">
            <x-admin.stat-card
                label="Approved spending"
                :value="'₹'.number_format($total, 2)"
                detail="Total approved expenses in this period"
                tone="warning"
                icon="fa-receipt"
            />
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <x-admin.stat-card
                label="Expense records"
                :value="number_format($expenseCount)"
                detail="Approved items in the selected period"
                tone="primary"
                icon="fa-file-invoice"
            />
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <x-admin.stat-card
                label="Average expense"
                :value="'₹'.number_format($averageExpense, 2)"
                detail="Average approved amount per record"
                tone="slate"
                icon="fa-chart-column"
            />
        </div>
    </section>

    <div class="row mb-4">
        <div class="col-12">
            <x-admin.card>
                <div class="d-flex flex-wrap justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h5 mb-1">Spending by category</h2>
                        <p class="small text-muted mb-0">Approved expenses grouped by category.</p>
                    </div>
                    <strong class="text-muted">{{ $byCategory->count() }} {{ \Illuminate\Support\Str::plural('category', $byCategory->count()) }}</strong>
                </div>
                @forelse ($byCategory as $item)
                    @php($share = $total > 0 ? min(100, ((float) $item->total / $total) * 100) : 0)
                    <div class="expense-category-row">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <div>
                                <strong>{{ $item->category?->name ?? 'Other' }}</strong>
                                <span class="small text-muted ml-2">{{ number_format((int) $item->expense_count) }} {{ \Illuminate\Support\Str::plural('record', (int) $item->expense_count) }}</span>
                            </div>
                            <div class="text-right">
                                <strong>₹{{ number_format((float) $item->total, 2) }}</strong>
                                <span class="small text-muted ml-2">{{ number_format($share, 1) }}%</span>
                            </div>
                        </div>
                        <div class="progress" style="height: 7px" aria-label="{{ number_format($share, 1) }} percent of approved spending">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $share }}%" aria-valuenow="{{ round($share) }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                @empty
                    <x-admin.empty-state
                        title="No approved expenses in this period"
                        description="Change the reporting period or branch to view expense activity."
                    />
                @endforelse
            </x-admin.card>
        </div>
    </div>

    <x-admin.card padding="false">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h2 class="h5 mb-1">Expense ledger</h2>
                <p class="small text-muted mb-0">{{ number_format($expenses->total()) }} approved expense records</p>
            </div>
            <strong class="text-nowrap">Total: ₹{{ number_format($total, 2) }}</strong>
        </div>
        <x-admin.table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Voucher</th>
                    <th>Branch</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Vendor</th>
                    <th class="text-right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($expenses as $expense)
                    <tr>
                        <td class="text-nowrap">{{ $expense->expense_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $expense->voucher_number ?: '—' }}</td>
                        <td>{{ $expense->branch?->name ?? '—' }}</td>
                        <td>{{ $expense->category?->name ?? 'Other' }}</td>
                        <td>{{ $expense->description ?: '—' }}</td>
                        <td>{{ $expense->vendor_name ?: '—' }}</td>
                        <td class="text-right font-weight-bold text-nowrap">₹{{ number_format((float) $expense->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-admin.empty-state
                                title="No approved expenses found"
                                description="Try another period or branch."
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>
        @if ($expenses->hasPages())
            <div class="p-3">{{ $expenses->links() }}</div>
        @endif
    </x-admin.card>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('expense-filter-toggle');
            const filters = document.getElementById('expense-filters');
            const period = document.getElementById('period');
            const fromDate = document.getElementById('from_date');
            const toDate = document.getElementById('to_date');

            toggle.addEventListener('click', function () {
                const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!isExpanded));
                filters.hidden = isExpanded;
            });

            period.addEventListener('change', function () {
                if (period.value) {
                    fromDate.value = '';
                    toDate.value = '';
                }
            });
        });
    </script>
@endsection
