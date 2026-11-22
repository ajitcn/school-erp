@extends('layouts.app')

@section('content')
    @php
        $editing = $editingSubject !== null;
        $ownerType = old('owner_type', $editing
            ? ($editingSubject->school_class_id ? 'class' : 'course')
            : 'class');
    @endphp

    <div class="academic-results-page">
        <x-admin.page-header
            title="Subject master"
            description="Create reusable subjects for a class or course, then select them when recording marks."
        />

        @if ($subjectMastersReady)
            <x-admin.card class="subject-master-card">
            <div class="results-list-heading">
                <div>
                    <h2>{{ $editing ? 'Edit subject' : 'Add a subject' }}</h2>
                    <p>{{ $editing ? 'The subject stays linked to its existing academic group.' : 'Each subject belongs to one class or one course.' }}</p>
                </div>
            </div>

            <form
                method="post"
                action="{{ $editing ? route('academic.subjects.update', $editingSubject) : route('academic.subjects.store') }}"
                class="subject-master-form"
            >
                @csrf
                @if ($editing)
                    @method('put')
                @endif

                <div class="form-row align-items-end">
                    @if (! $branchId && ! $editing)
                        <div class="form-group col-12 col-md-6 col-xl-3">
                            <label for="subject_branch_id">Branch <span class="text-danger">*</span></label>
                            <select id="subject_branch_id" name="branch_id" class="form-control" required data-subject-branch>
                                <option value="">Choose a branch</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                            @error('branch_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    @endif

                    @if ($editing)
                        <div class="form-group col-12 col-md-6 col-xl-3">
                            <label>Academic group</label>
                            <div class="form-control subject-owner-summary" aria-readonly="true">
                                {{ $editingSubject->schoolClass?->name ?? $editingSubject->course?->name }}
                            </div>
                        </div>
                    @else
                        <div class="form-group col-12 col-md-6 col-xl-3">
                            <label for="subject_owner_type">Link subject to <span class="text-danger">*</span></label>
                            <select id="subject_owner_type" name="owner_type" class="form-control" required data-subject-owner-type>
                                <option value="class" @selected($ownerType === 'class')>Class</option>
                                <option value="course" @selected($ownerType === 'course')>Course</option>
                            </select>
                            @error('owner_type') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group col-12 col-md-6 col-xl-3" data-subject-class-field @if ($ownerType !== 'class') hidden @endif>
                            <label for="subject_school_class_id">Class <span class="text-danger">*</span></label>
                            <select
                                id="subject_school_class_id"
                                name="school_class_id"
                                class="form-control @error('school_class_id') is-invalid @enderror"
                                data-subject-class
                                @required($ownerType === 'class')
                                @disabled($ownerType !== 'class')
                            >
                                <option value="">Choose a class</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}" data-branch="{{ $class->branch_id }}" @selected(old('school_class_id') == $class->id)>
                                        {{ $class->branch?->name }} · {{ $class->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('school_class_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group col-12 col-md-6 col-xl-3" data-subject-course-field @if ($ownerType !== 'course') hidden @endif>
                            <label for="subject_course_id">Course <span class="text-danger">*</span></label>
                            <select
                                id="subject_course_id"
                                name="course_id"
                                class="form-control @error('course_id') is-invalid @enderror"
                                data-subject-course
                                @required($ownerType === 'course')
                                @disabled($ownerType !== 'course')
                            >
                                <option value="">Choose a course</option>
                                @foreach ($courses as $course)
                                    <option value="{{ $course->id }}" data-branch="{{ $course->branch_id }}" @selected(old('course_id') == $course->id)>
                                        {{ $course->branch?->name }} · {{ $course->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('course_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    @endif

                    <div class="form-group col-12 col-md-6 col-xl-3">
                        <label for="subject_name">Subject name <span class="text-danger">*</span></label>
                        <input
                            id="subject_name"
                            name="name"
                            class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $editingSubject?->name) }}"
                            maxlength="150"
                            required
                            placeholder="e.g. Mathematics"
                        >
                        @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-group col-12 col-md-6 col-xl-3">
                        <label for="subject_code">Subject code <span class="text-muted font-weight-normal">(optional)</span></label>
                        <input
                            id="subject_code"
                            name="code"
                            class="form-control @error('code') is-invalid @enderror"
                            value="{{ old('code', $editingSubject?->code) }}"
                            maxlength="30"
                            placeholder="e.g. MAT-01"
                        >
                        @error('code') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-group col-12 col-md-auto ml-md-auto">
                        <button class="btn btn-primary" type="submit">
                            <i class="fa-solid fa-floppy-disk mr-2" aria-hidden="true"></i>{{ $editing ? 'Update subject' : 'Save subject' }}
                        </button>
                        @if ($editing)
                            <a class="btn btn-outline-secondary ml-2" href="{{ route('academic.subjects') }}">Cancel</a>
                        @endif
                    </div>
                </div>

                @error('subject')
                    <div class="alert alert-warning mb-0" role="alert">{{ $message }}</div>
                @enderror
            </form>
            </x-admin.card>
        @else
            <div class="alert alert-warning mb-0" role="alert">
                <strong>Subject setup is pending.</strong>
                Run <code>php artisan migrate --force</code> from the project folder to create the subject master tables.
            </div>
        @endif

        <x-admin.card padding="false" class="results-list-card">
            <div class="results-list-heading subject-list-heading">
                <div>
                    <h2>Subject list</h2>
                    <p>Active subjects appear in result entry when their class or course is selected.</p>
                </div>
                <span class="results-count-pill">{{ number_format($subjects->total()) }} subjects</span>
            </div>

            <form method="get" action="{{ route('academic.subjects') }}" class="subject-filter-row js-filter-form">
                <div class="form-group mb-0">
                    <label class="sr-only" for="subject_search">Search subjects</label>
                    <input id="subject_search" name="q" class="form-control" value="{{ request('q') }}" placeholder="Search subject or code">
                </div>
                <div class="form-group mb-0">
                    <label class="sr-only" for="subject_status">Status</label>
                    <select id="subject_status" name="status" class="form-control">
                        <option value="">All statuses</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                    </select>
                </div>
                <button class="btn btn-outline-primary" type="submit">Apply</button>
                @if (request()->filled('q') || request()->filled('status'))
                    <a class="btn btn-outline-secondary" href="{{ route('academic.subjects') }}">Clear</a>
                @endif
            </form>

            <div class="table-responsive">
                <x-admin.table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Linked class / course</th>
                            <th>Branch</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subjects as $subject)
                            <tr>
                                <td>
                                    <strong>{{ $subject->name }}</strong>
                                    <small class="d-block text-muted">{{ $subject->code ?: 'No subject code' }}</small>
                                </td>
                                <td>{{ $subject->schoolClass?->name ?? $subject->course?->name ?? 'Group unavailable' }}</td>
                                <td>{{ $subject->branch?->name ?? '—' }}</td>
                                <td>
                                    <x-admin.badge :tone="$subject->is_active ? 'success' : 'slate'">
                                        {{ $subject->is_active ? 'Active' : 'Inactive' }}
                                    </x-admin.badge>
                                </td>
                                <td class="text-right">
                                    <div class="academic-row-actions">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('academic.subjects', ['edit' => $subject->id]) }}">Edit</a>
                                        <form method="post" action="{{ route('academic.subjects.toggle', $subject) }}">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-secondary" type="submit">
                                                {{ $subject->is_active ? 'Deactivate' : 'Activate' }}
                                            </button>
                                        </form>
                                        <form method="post" action="{{ route('academic.subjects.destroy', $subject) }}" onsubmit="return confirm('Delete this subject if it has no recorded results?');">
                                            @csrf
                                            @method('delete')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4">
                                    <x-admin.empty-state
                                        title="No subjects found"
                                        description="Add a subject for a class or course above to use it when recording marks."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
            </div>

            @if ($subjects->hasPages())
                <div class="p-3">
                    <x-admin.pagination :paginator="$subjects" />
                </div>
            @endif
        </x-admin.card>
    </div>

    @unless ($editing)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const ownerType = document.querySelector('[data-subject-owner-type]');
                const classField = document.querySelector('[data-subject-class-field]');
                const courseField = document.querySelector('[data-subject-course-field]');
                const classSelect = document.querySelector('[data-subject-class]');
                const courseSelect = document.querySelector('[data-subject-course]');
                const branchSelect = document.querySelector('[data-subject-branch]');

                const syncOwnerFields = function () {
                    const usesClass = ownerType?.value === 'class';
                    classField.hidden = ! usesClass;
                    courseField.hidden = usesClass;
                    classSelect.disabled = ! usesClass;
                    courseSelect.disabled = usesClass;
                    classSelect.required = usesClass;
                    courseSelect.required = ! usesClass;
                };

                const syncBranchOptions = function () {
                    const branchId = branchSelect?.value || '';

                    [classSelect, courseSelect].forEach(function (select) {
                        Array.from(select.options).forEach(function (option) {
                            option.hidden = option.value !== '' && branchId && option.dataset.branch !== branchId;
                            if (option.hidden && option.selected) {
                                select.value = '';
                            }
                        });
                    });
                };

                ownerType?.addEventListener('change', syncOwnerFields);
                branchSelect?.addEventListener('change', syncBranchOptions);
                syncOwnerFields();
                syncBranchOptions();
            });
        </script>
    @endunless
@endsection
