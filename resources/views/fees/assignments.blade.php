@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Fee Installments List"
        description="Track student charges, payments received, balances, and due dates."
    >
        <x-slot:actions>
            <a class="btn btn-outline-primary" href="{{ route('fees.structures.index') }}">
                <i class="fa-solid fa-layer-group mr-1" aria-hidden="true"></i>
                Fee plans
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <section class="fee-assignment-summary" aria-label="Installment summary">
        <x-admin.stat-card label="Installments" :value="number_format($summary['total'])" tone="primary" icon="fa-list-check" />
        <x-admin.stat-card label="Due" :value="number_format($summary['due'])" tone="warning" icon="fa-hourglass-half" />
        <x-admin.stat-card label="Part paid" :value="number_format($summary['partial'])" tone="info" icon="fa-coins" />
        <x-admin.stat-card label="Paid" :value="number_format($summary['paid'])" tone="success" icon="fa-circle-check" />
        <x-admin.stat-card label="Overdue" :value="number_format($summary['overdue'])" tone="danger" icon="fa-triangle-exclamation" />
    </section>

    <div class="admin-card fee-assignment-filter-card">
        <form method="get" action="{{ route('fees.assignments.index') }}" class="form-row align-items-end js-filter-form" aria-label="Fee installment filters">
            @if (! auth()->user()->currentBranchId())
                <div class="form-group col-md-6 col-xl-3">
                    <label for="assignment-branch">Branch</label>
                    <select id="assignment-branch" name="branch_id" class="form-control">
                        <option value="">All accessible branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? null) == $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="form-group col-md-6 col-xl-3">
                <label for="assignment-student">Student</label>
                <select id="assignment-student" name="student_id" class="form-control">
                    <option value="">All students</option>
                    @foreach ($students as $student)
                        <option value="{{ $student->id }}" @selected(($filters['student_id'] ?? null) == $student->id)>
                            {{ $student->first_name }} {{ $student->last_name }} · {{ $student->student_id }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group col-md-6 col-xl-3">
                <label for="assignment-year">Academic year</label>
                <select id="assignment-year" name="academic_year_id" class="form-control">
                    <option value="">All academic years</option>
                    @foreach ($years as $year)
                        <option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? null) == $year->id)>
                            {{ $year->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group col-md-6 col-xl-3">
                <label for="assignment-status">Payment status</label>
                <select id="assignment-status" name="status" class="form-control">
                    <option value="">All statuses</option>
                    @foreach (['open' => 'Due', 'partial' => 'Part paid', 'paid' => 'Paid', 'overdue' => 'Overdue'] as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? null) === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group col-md-6 col-xl-3">
                <label for="assignment-due-from">Due from</label>
                <input
                    class="form-control js-datepicker"
                    type="text"
                    id="assignment-due-from"
                    name="due_from"
                    value="{{ isset($filters['due_from']) ? \Carbon\Carbon::parse($filters['due_from'])->format('d/m/Y') : '' }}"
                    placeholder="DD/MM/YYYY"
                    autocomplete="off"
                >
            </div>

            <div class="form-group col-md-6 col-xl-3">
                <label for="assignment-due-to">Due to</label>
                <input
                    class="form-control js-datepicker"
                    type="text"
                    id="assignment-due-to"
                    name="due_to"
                    value="{{ isset($filters['due_to']) ? \Carbon\Carbon::parse($filters['due_to'])->format('d/m/Y') : '' }}"
                    placeholder="DD/MM/YYYY"
                    autocomplete="off"
                >
            </div>

            <div class="form-group col-md-6 col-xl-3 fee-assignment-filter-actions">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>
                    Apply filters
                </button>
                <a class="btn btn-outline-secondary" href="{{ route('fees.assignments.index') }}">Clear</a>
            </div>
        </form>
    </div>

    <section class="admin-card fee-assignment-ledger" aria-labelledby="fee-assignment-ledger-title">
        <div class="fee-assignment-ledger-heading">
            <div>
                <h3 id="fee-assignment-ledger-title">Installment ledger</h3>
                <p>Showing {{ $assignments->firstItem() ?? 0 }}–{{ $assignments->lastItem() ?? 0 }} of {{ number_format($assignments->total()) }} installments</p>
            </div>
        </div>

        <x-admin.table class="fee-assignment-table">
            <thead>
                <tr>
                    <th scope="col">Student</th>
                    <th scope="col">Fee plan / year</th>
                    <th scope="col">Installment</th>
                    <th scope="col">Due date</th>
                    <th scope="col">Amount</th>
                    <th scope="col">Paid</th>
                    <th scope="col">Balance</th>
                    <th scope="col">Status</th>
                    <th scope="col" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($assignments as $assignment)
                    @php
                        $balance = $assignment->balanceDue();
                        $paid = $assignment->totalDue() - $balance;
                        $dueDate = $assignment->component?->due_date;
                        $overdue = $balance > 0 && $dueDate && $dueDate->toDateString() < today()->toDateString();
                        $academicYearId = $assignment->component?->academic_year_id
                            ?? $assignment->component?->structure?->academic_year_id;
                    @endphp
                    <tr>
                        <td>
                            <a class="fee-ledger-student" href="{{ route('students.show', $assignment->student) }}">
                                {{ $assignment->student?->first_name }} {{ $assignment->student?->last_name }}
                            </a>
                            <span class="fee-ledger-meta">
                                {{ $assignment->student?->student_id }} · {{ $assignment->student?->branch?->name }}
                            </span>
                        </td>
                        <td>
                            <span class="fee-ledger-primary">{{ $assignment->component?->structure?->name ?? 'Fee plan' }}</span>
                            <span class="fee-ledger-meta">
                                {{ $assignment->component?->academicYear?->name ?? $assignment->component?->structure?->academicYear?->name ?? '—' }}
                            </span>
                        </td>
                        <td>
                            <span class="fee-ledger-primary">
                                {{ $assignment->component?->installment_number ? 'Installment '.$assignment->component->installment_number : 'Full payment' }}
                            </span>
                            <span class="fee-ledger-meta">{{ $assignment->component?->name }}</span>
                        </td>
                        <td class="fee-ledger-due-date">
                            {{ $dueDate?->format('d M Y') ?? '—' }}
                            @if ($overdue)
                                <span class="fee-ledger-overdue-label">Overdue</span>
                            @endif
                        </td>
                        <td>₹{{ number_format($assignment->totalDue(), 2) }}</td>
                        <td>₹{{ number_format($paid, 2) }}</td>
                        <td class="fee-ledger-balance">₹{{ number_format($balance, 2) }}</td>
                        <td>
                            <x-admin.badge :tone="$balance <= 0 ? 'success' : ($overdue ? 'danger' : ($paid > 0 ? 'warning' : 'slate'))">
                                {{ $balance <= 0 ? 'Paid' : ($overdue ? 'Overdue' : ($paid > 0 ? 'Part paid' : 'Due')) }}
                            </x-admin.badge>
                        </td>
                        <td class="fee-ledger-actions text-right">
                            @if ($balance > 0)
                                <a
                                    class="btn btn-sm btn-primary"
                                    href="{{ route('payments.create', ['student_id' => $assignment->student_id, 'academic_year_id' => $academicYearId, 'student_fee_assignment_id' => $assignment->id]) }}"
                                >
                                    Collect
                                </a>
                            @else
                                <span class="fee-ledger-settled">Settled</span>
                            @endif
                            <a class="btn btn-sm btn-outline-secondary" href="{{ route('students.fees.create', $assignment->student) }}">Manage</a>
                            <details class="fee-assignment-adjustment">
                                <summary>Adjust</summary>
                                <form method="post" action="{{ route('fees.assignments.update', $assignment) }}">
                                    @csrf
                                    @method('put')
                                    <label for="discount-{{ $assignment->id }}">Discount</label>
                                    <input
                                        id="discount-{{ $assignment->id }}"
                                        class="form-control form-control-sm"
                                        name="discount"
                                        type="number"
                                        min="0"
                                        max="{{ $assignment->amount }}"
                                        step="0.01"
                                        value="{{ $assignment->discount }}"
                                    >
                                    <label for="late-fee-{{ $assignment->id }}">Late fee</label>
                                    <input
                                        id="late-fee-{{ $assignment->id }}"
                                        class="form-control form-control-sm"
                                        name="late_fee"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        value="{{ $assignment->late_fee }}"
                                    >
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Save adjustment</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <span class="empty-state-mark"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
                                <h3>No installments found</h3>
                                <p>Set up a fee plan from a student profile to start tracking installments here.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>

        @if ($assignments->hasPages())
            <div class="fee-assignment-pagination"><x-admin.pagination :paginator="$assignments" /></div>
        @endif
    </section>
@endsection
