@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Overdue fee report"
        description="Review unpaid installments that have passed their due date."
    >
        <x-slot name="actions">
            <button
                id="fee-report-filter-toggle"
                class="btn btn-outline-secondary"
                type="button"
                aria-controls="fee-report-filters"
                aria-expanded="false"
            >
                <i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>
                Filters
            </button>
            @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
                <a
                    class="btn btn-outline-primary"
                    href="{{ route('reports.export', ['type' => 'fees', 'format' => $format] + request()->except('page')) }}"
                >{{ $label }}</a>
            @endforeach
            <button class="btn btn-outline-secondary" type="button" onclick="window.print()">Print</button>
        </x-slot>
    </x-admin.page-header>

    <section id="fee-report-filters" class="admin-card p-3 p-md-4 mb-4" hidden aria-label="Overdue fee filters">
        <form method="get" action="{{ route('reports.fees') }}" class="form-row align-items-end">
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
                <label for="academic_year_id">Academic year</label>
                <select id="academic_year_id" name="academic_year_id" class="form-control">
                    <option value="">All academic years</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected($academicYearId == $year->id)>
                            {{ $year->name }} · {{ $year->branch?->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6 form-group">
                <label for="from_date">Due from</label>
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
                <label for="to_date">Due to</label>
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
            <div class="col-lg-2 col-md-6 form-group">
                <label for="search">Find student</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    class="form-control"
                    value="{{ $data['search'] ?? '' }}"
                    placeholder="Name or ID"
                >
            </div>
            <div class="col-12 d-flex flex-wrap align-items-center">
                <button class="btn btn-primary mr-2" type="submit">Apply filters</button>
                <a class="btn btn-outline-secondary" href="{{ route('reports.fees') }}">Reset</a>
                <span class="small text-muted ml-md-3 mt-2 mt-md-0">Only installments due today or earlier are included.</span>
            </div>
        </form>
    </section>

    <section class="row fee-report-summary mb-4" aria-label="Overdue fee summary">
        <div class="col-xl-4 col-md-6 mb-3">
            <x-admin.stat-card
                label="Outstanding balance"
                :value="'₹'.number_format($totalBalance, 2)"
                detail="Unpaid amount across overdue installments"
                tone="danger"
                icon="fa-indian-rupee-sign"
            />
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <x-admin.stat-card
                label="Overdue installments"
                :value="number_format($installmentCount)"
                detail="Fee items with an unpaid balance"
                tone="warning"
                icon="fa-file-invoice-dollar"
            />
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <x-admin.stat-card
                label="Students with dues"
                :value="number_format($studentCount)"
                detail="Students with at least one overdue item"
                tone="primary"
                icon="fa-user-graduate"
            />
        </div>
    </section>

    <x-admin.card padding="false">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h2 class="h5 mb-1">Overdue installments</h2>
                <p class="small text-muted mb-0">Oldest due items appear first · {{ number_format($assignments->total()) }} records</p>
            </div>
            <strong class="text-nowrap">Outstanding: ₹{{ number_format($totalBalance, 2) }}</strong>
        </div>
        <x-admin.table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Branch</th>
                    <th>Academic year</th>
                    <th>Fee item</th>
                    <th>Due date</th>
                    <th class="text-right">Amount</th>
                    <th class="text-right">Paid</th>
                    <th class="text-right">Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assignments as $assignment)
                    @php
                        $balance = $assignment->balanceDue();
                        $paid = $assignment->totalDue() - $balance;
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $assignment->student?->first_name }} {{ $assignment->student?->last_name }}</strong>
                            <small class="d-block text-muted">{{ $assignment->student?->student_id ?? '—' }}</small>
                        </td>
                        <td>{{ $assignment->student?->branch?->name ?? '—' }}</td>
                        <td>{{ $assignment->component?->academicYear?->name ?? $assignment->component?->structure?->academicYear?->name ?? '—' }}</td>
                        <td>{{ $assignment->component?->name ?? '—' }}</td>
                        <td class="text-nowrap">{{ $assignment->component?->due_date?->format('d M Y') ?? '—' }}</td>
                        <td class="text-right text-nowrap">₹{{ number_format($assignment->totalDue(), 2) }}</td>
                        <td class="text-right text-nowrap">₹{{ number_format($paid, 2) }}</td>
                        <td class="text-right text-nowrap">
                            <strong class="text-danger">₹{{ number_format($balance, 2) }}</strong>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            <x-admin.empty-state
                                title="No overdue fee balances"
                                description="There are no unpaid fee items matching these filters."
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>
        @if ($assignments->hasPages())
            <div class="p-3">{{ $assignments->links() }}</div>
        @endif
    </x-admin.card>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('fee-report-filter-toggle');
            const filters = document.getElementById('fee-report-filters');

            toggle.addEventListener('click', function () {
                const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!isExpanded));
                filters.hidden = isExpanded;
            });
        });
    </script>
@endsection
