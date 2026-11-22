@extends('layouts.app')

@section('content')
    @php
        $toOptions = fn ($items, $label) => $items->map(fn ($item) => [
            'value' => $item->id,
            'label' => $label($item),
        ])->values()->all();

        $statusOptions = collect(['active', 'inactive', 'graduated', 'transferred', 'withdrawn'])
            ->map(fn ($status) => ['value' => $status, 'label' => ucfirst($status)])
            ->all();
    @endphp

    <x-admin.page-header
        title="Students"
        description="Manage tenant-scoped admissions, enrollment, and student status."
    >
        <x-slot:actions>
            <a class="btn btn-outline-primary mr-2" href="{{ route('admissions.index') }}">
                Review admissions
            </a>
            <a class="btn btn-primary" href="{{ route('students.create') }}">Add admission</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="admin-card mb-4 p-3">
        <form method="get" action="{{ route('students.index') }}" class="js-filter-form">
            <div class="form-row">
                <div class="form-group col-lg-4">
                    <label for="student-search">Search students</label>
                    <input
                        id="student-search"
                        class="form-control"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Name, student ID, admission number"
                    >
                </div>

                @if (! auth()->user()->currentBranchId())
                    <div class="col-lg-2">
                        <x-admin.searchable-select
                            name="branch_id"
                            label="Branch"
                            :options="$toOptions($branches, fn ($item) => $item->name)"
                            :selected="request('branch_id')"
                        />
                    </div>
                @endif

                <div class="col-lg-2">
                    <x-admin.searchable-select
                        name="academic_year_id"
                        label="Academic year"
                        :options="$toOptions($years, fn ($item) => $item->name)"
                        :selected="request('academic_year_id')"
                    />
                </div>
                <div class="col-lg-2">
                    <x-admin.searchable-select
                        name="school_class_id"
                        label="Class"
                        :options="$toOptions($classes, fn ($item) => $item->name)"
                        :selected="request('school_class_id')"
                    />
                </div>
                <div class="col-lg-2">
                    <x-admin.searchable-select
                        name="status"
                        label="Status"
                        :options="$statusOptions"
                        :selected="request('status')"
                    />
                </div>
            </div>

            <div class="d-flex align-items-center">
                <button class="btn btn-primary mr-2" type="submit">Apply filters</button>
                <a class="btn btn-outline-secondary" href="{{ route('students.index') }}">Clear</a>
                <span class="ml-auto text-muted small">{{ $students->total() }} result(s)</span>
            </div>
        </form>
    </div>

    <div class="admin-card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h3 class="h5 mb-1">Student directory</h3>
                <p class="small text-muted mb-0">Tenant-scoped records and enrollment details.</p>
            </div>
            <x-admin.badge tone="primary">{{ $students->count() }} on page</x-admin.badge>
        </div>

        <x-admin.table>
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Branch</th>
                    <th>Enrollment</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($students as $student)
                    @php($enrollment = $student->enrollments->first())
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <span class="admin-avatar mr-2">
                                    {{ strtoupper(substr($student->first_name, 0, 1)) }}
                                </span>
                                <div>
                                    <a class="font-weight-bold" href="{{ route('students.show', $student) }}">
                                        {{ $student->first_name }} {{ $student->last_name }}
                                    </a>
                                    <small class="d-block text-muted">
                                        {{ $student->student_id }} · {{ $student->admission_number }}
                                    </small>
                                    <small class="d-block text-muted">
                                        R.NO {{ $student->roll_number }} · GR.NO {{ $student->gr_number }}
                                    </small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $student->branch?->name ?? '—' }}</td>
                        <td>
                            <strong>
                                {{ $enrollment?->schoolClass?->name ?? 'Not assigned' }}
                                {{ $enrollment?->section?->name ? ' / '.$enrollment->section->name : '' }}
                            </strong>
                            <small class="d-block text-muted">
                                {{ $enrollment?->academicYear?->name ?? '—' }}
                            </small>
                        </td>
                        <td>
                            <x-admin.badge
                                :tone="$student->status === 'active' ? 'success' : ($student->status === 'withdrawn' ? 'danger' : 'slate')"
                            >
                                {{ ucfirst($student->status) }}
                            </x-admin.badge>
                        </td>
                        <td class="text-right">
                            <x-admin.dropdown label="Actions" :portal="true">
                                @if(auth()->user()->hasPermission('fees.assign'))
                                    <button class="dropdown-item" type="button" data-open-fee-modal="{{ route('students.fees.setup-modal', $student) }}"><i class="fa-solid fa-circle-plus" aria-hidden="true"></i><span>Add Fee</span></button>
                                @endif
                                @if(auth()->user()->hasPermission('payments.collect'))
                                    <button class="dropdown-item" type="button" data-open-fee-modal="{{ route('students.fees.collect-modal', $student) }}"><i class="fa-solid fa-wallet" aria-hidden="true"></i><span>Collect Fee</span></button>
                                @endif
                                @if(auth()->user()->hasPermission('fees.assign') || auth()->user()->hasPermission('payments.collect'))
                                    <div class="dropdown-divider"></div>
                                @endif
                                <a class="dropdown-item" href="{{ route('students.show', $student) }}"><i class="fa-regular fa-eye" aria-hidden="true"></i><span>View student</span></a>
                                <a class="dropdown-item" href="{{ route('students.edit', $student) }}"><i class="fa-regular fa-pen-to-square" aria-hidden="true"></i><span>Edit student</span></a>
                            </x-admin.dropdown>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-admin.empty-state
                                title="No students found"
                                description="Try changing your filters or add a new admission."
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>

        <div class="p-3">
            <x-admin.pagination :paginator="$students" />
        </div>
    </div>

    @include('students.partials.fee-modals')

@endsection
