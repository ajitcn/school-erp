@extends('layouts.app')

@section('content')
    @php
        $labels = [
            'classes' => 'Class',
            'sections' => 'Section',
            'departments' => 'Department',
            'courses' => 'Course',
            'semesters' => 'Semester',
        ];

        $label = $labels[$type];
    @endphp

    <x-admin.page-header
        :title="ucfirst($type)"
        description="Set up and maintain the academic records used by your institution."
    />

    <section class="admin-card academic-manager" aria-labelledby="academic-manager-title">
        <div class="academic-manager-heading">
            <div>
                <h3 id="academic-manager-title">Manage {{ strtolower($label) }}s</h3>
                <p>Create a record, then review or update it in the list below.</p>
            </div>
            <x-admin.badge tone="primary">{{ $items->total() }} total</x-admin.badge>
        </div>

        <div class="academic-manager-section">
            <h4>Add {{ strtolower($label) }}</h4>
            <form method="post" action="{{ route('academic.store', $type) }}">
                @csrf

                <div class="form-row">
                    <div class="col-md-4 form-group">
                        <label for="name">Name</label>
                        <input id="name" class="form-control" name="name" value="{{ old('name') }}" required>
                    </div>

                    @if (in_array($type, ['classes', 'departments', 'courses'], true))
                        <div class="col-md-4 form-group">
                            <label for="code">{{ $type === 'courses' ? 'Course code' : 'Code' }}</label>
                            <input id="code" class="form-control" name="code" value="{{ old('code') }}" @required($type === 'courses')>
                        </div>
                    @endif

                    @if (in_array($type, ['classes', 'sections', 'departments', 'courses'], true))
                        <div class="col-md-4 form-group">
                            <label for="branch_id">Branch</label>
                            <select id="branch_id" class="form-control" name="branch_id" required>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(old('branch_id', auth()->user()->currentBranchId()) == $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($type === 'classes')
                        <div class="col-md-4 form-group">
                            <label for="academic_year_id">Academic year</label>
                            <select id="academic_year_id" class="form-control" name="academic_year_id" required>
                                @foreach ($years as $year)
                                    <option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>
                                        {{ $year->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="level_type">Type</label>
                            <select id="level_type" class="form-control" name="level_type">
                                <option value="school" @selected(old('level_type', 'school') === 'school')>School</option>
                                <option value="college" @selected(old('level_type') === 'college')>College</option>
                            </select>
                        </div>
                    @elseif ($type === 'sections')
                        <div class="col-12">
                            <p class="form-text">Create each section once for this branch. Assign sections to classes during enrollment, attendance, and timetable setup.</p>
                        </div>
                    @elseif ($type === 'courses')
                        <div class="col-md-4 form-group">
                            <label for="department_id">Department</label>
                            <select id="department_id" class="form-control" name="department_id">
                                <option value="">No department</option>
                                @foreach ($departments as $department)
                                    <option value="{{ $department->id }}" @selected(old('department_id') == $department->id)>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="duration_years">Duration (years)</label>
                            <input id="duration_years" class="form-control" type="number" min="1" name="duration_years" value="{{ old('duration_years') }}">
                        </div>
                    @elseif ($type === 'semesters')
                        <div class="col-md-4 form-group">
                            <label for="course_id">Course</label>
                            <select id="course_id" class="form-control" name="course_id" required>
                                <option value="">Select a course</option>
                                @foreach ($courses as $course)
                                    <option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>
                                        {{ $course->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label for="semester_number">Semester number</label>
                            <input id="semester_number" class="form-control" type="number" min="1" name="semester_number" value="{{ old('semester_number') }}" required>
                        </div>
                    @endif
                </div>

                @error('master')
                    <div class="alert alert-warning" role="alert">{{ $message }}</div>
                @enderror

                <button class="btn btn-primary" type="submit">Save {{ strtolower($label) }}</button>
            </form>
        </div>

        <hr class="academic-manager-divider">

        <div class="academic-manager-section">
            <div class="academic-list-heading">
                <div>
                    <h4>{{ $label }} list</h4>
                    <p>Search, edit, deactivate, or remove existing records.</p>
                </div>

                <form class="academic-search js-filter-form" method="get" action="{{ route('academic.index', $type) }}">
                    <label class="sr-only" for="academic-search">Search {{ strtolower($label) }}s</label>
                    <input id="academic-search" class="form-control" name="q" value="{{ request('q') }}" placeholder="Search by name">
                    <button class="btn btn-outline-primary" type="submit">Search</button>
                    @if (request()->filled('q'))
                        <a class="btn btn-outline-secondary" href="{{ route('academic.index', $type) }}">Clear</a>
                    @endif
                </form>
            </div>

            <x-admin.table>
                <thead>
                    @if ($type === 'sections')
                        <tr><th>Section</th><th>Branch</th><th>Status</th><th class="text-right">Actions</th></tr>
                    @else
                        <tr><th>Name</th><th>Code</th><th>Status</th><th class="text-right">Actions</th></tr>
                    @endif
                </thead>
                <tbody>
                    @forelse ($items as $item)
                        <tr>
                            @if ($type === 'sections')
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->branch?->name ?? '—' }}</td>
                            @else
                                <td>{{ $item->name }}</td>
                                <td>{{ $item->code ?? '—' }}</td>
                            @endif
                            <td>
                                <x-admin.badge :tone="$item->is_active ? 'success' : 'slate'">
                                    {{ $item->is_active ? 'Active' : 'Inactive' }}
                                </x-admin.badge>
                            </td>
                            <td class="text-right">
                                <div class="academic-row-actions">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ route('academic.edit', [$type, $item->id]) }}">Edit</a>
                                    <form method="post" action="{{ route('academic.toggle', [$type, $item->id]) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">
                                            {{ $item->is_active ? 'Deactivate' : 'Activate' }}
                                        </button>
                                    </form>
                                    <form method="post" action="{{ route('academic.destroy', [$type, $item->id]) }}" onsubmit="return confirm('Delete this {{ strtolower($label) }} if it has no linked history?');">
                                        @csrf
                                        @method('delete')
                                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-admin.empty-state
                                    title="No {{ strtolower($label) }}s found"
                                    description="Add a {{ strtolower($label) }} above or adjust your search."
                                />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-admin.table>

            <div class="academic-pagination">
                <x-admin.pagination :paginator="$items" />
            </div>
        </div>
    </section>
@endsection
