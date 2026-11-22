@extends('layouts.app')

@section('content')
    @php
        $selectedYear = $academicYears->firstWhere('id', $academicYearId);
        $yearSummary = $selectedYear
            ? $selectedYear->name.' · '.$selectedYear->branch?->name
            : ($academicYears->isNotEmpty() ? 'Latest completed academic year for each branch' : 'No completed academic years found');
    @endphp

    <x-admin.page-header
        title="Previous-year dues"
        description="{{ $branchName }} · {{ $yearSummary }}"
    >
        <x-slot name="actions">
            <button
                id="previous-dues-filter-toggle"
                class="btn btn-outline-secondary"
                type="button"
                aria-controls="previous-dues-filters"
                aria-expanded="false"
            >
                <i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>
                Filters
            </button>
            @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
                <a
                    class="btn btn-outline-primary"
                    href="{{ route('reports.export', ['type' => 'previous_dues', 'format' => $format] + request()->except('page')) }}"
                >{{ $label }}</a>
            @endforeach
            <button class="btn btn-outline-secondary" type="button" onclick="window.print()">Print</button>
        </x-slot>
    </x-admin.page-header>

    <section id="previous-dues-filters" class="admin-card p-3 p-md-4 mb-4" hidden aria-label="Previous-year dues filters">
        <form method="get" action="{{ route('reports.previous-dues') }}" class="form-row align-items-end">
            <div class="col-lg-4 col-md-6 form-group">
                <label for="branch_id">Branch</label>
                <select id="branch_id" name="branch_id" class="form-control">
                    <option value="">All permitted branches</option>
                    @foreach ($branchOptions as $branch)
                        <option value="{{ $branch->id }}" @selected($branchId == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-4 col-md-6 form-group">
                <label for="academic_year_id">Completed academic year</label>
                <select id="academic_year_id" name="academic_year_id" class="form-control">
                    <option value="">Latest completed year per branch</option>
                    @foreach ($academicYears as $year)
                        <option value="{{ $year->id }}" @selected($academicYearId == $year->id)>
                            {{ $year->name }} · {{ $year->branch?->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-3 col-md-6 form-group">
                <label for="search">Find student</label>
                <input
                    id="search"
                    name="search"
                    type="search"
                    class="form-control"
                    value="{{ $data['search'] ?? '' }}"
                    placeholder="Name or student ID"
                >
            </div>
            <div class="col-lg-1 col-md-6 form-group d-flex align-items-end">
                <button class="btn btn-primary" type="submit">Apply</button>
            </div>
            <div class="col-12">
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('reports.previous-dues') }}">Reset filters</a>
            </div>
        </form>
    </section>

    <section class="row fee-report-summary mb-4" aria-label="Previous-year dues summary">
        <div class="col-xl-4 col-md-6 mb-3">
            <x-admin.stat-card
                label="Outstanding balance"
                :value="'₹'.number_format($totalBalance, 2)"
                detail="Unpaid amount from completed academic years"
                tone="danger"
                icon="fa-indian-rupee-sign"
            />
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <x-admin.stat-card
                label="Unpaid fee items"
                :value="number_format($installmentCount)"
                detail="Installments with a remaining balance"
                tone="warning"
                icon="fa-file-invoice-dollar"
            />
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <x-admin.stat-card
                label="Students with dues"
                :value="number_format($studentCount)"
                detail="Students with at least one previous-year balance"
                tone="primary"
                icon="fa-user-graduate"
            />
        </div>
    </section>

    <x-admin.card padding="false">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h2 class="h5 mb-1">Outstanding fee items</h2>
                <p class="small text-muted mb-0">Oldest due items first · {{ number_format($assignments->total()) }} records</p>
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
                    <th class="text-right">Outstanding</th>
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
                                title="No previous-year balances found"
                                description="No unpaid items match the selected branch, academic year, or student search."
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
            const toggle = document.getElementById('previous-dues-filter-toggle');
            const filters = document.getElementById('previous-dues-filters');

            toggle.addEventListener('click', function () {
                const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!isExpanded));
                filters.hidden = isExpanded;
            });
        });
    </script>
@endsection
