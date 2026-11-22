@extends('layouts.app')

@section('content')
    <div class="teacher-management-page">
        <x-admin.page-header
            title="Teacher management"
            description="Create teacher profiles first, then assign their campus teaching load."
        />

        @if (! auth()->user()->currentBranchId() && ! $branchId)
            <x-admin.card class="teacher-campus-filter">
                <form method="get" action="{{ route('academic.teachers') }}" class="form-row align-items-end">
                    <div class="form-group col-12 col-md-6 mb-md-0">
                        <label for="branch_id">Campus</label>
                        <select id="branch_id" name="branch_id" class="form-control" required>
                            <option value="">Choose a campus to focus this page</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>
                                    {{ $branch->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-12 col-md-auto mb-md-0">
                        <button class="btn btn-outline-primary" type="submit">Show campus</button>
                        @if (request()->filled('branch_id'))
                            <a class="btn btn-link" href="{{ route('academic.teachers') }}">Show all campuses</a>
                        @endif
                    </div>
                </form>
            </x-admin.card>
        @elseif ($branchId)
            <div class="teacher-campus-context mb-4">
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <span>Showing teacher records for <strong>{{ $branches->firstWhere('id', $branchId)?->name ?? 'your selected campus' }}</strong></span>
                @if (! auth()->user()->currentBranchId())
                    <a href="{{ route('academic.teachers') }}">Change campus</a>
                @endif
            </div>
        @endif

        <section class="teacher-summary-grid" aria-label="Teacher summary">
            <x-admin.stat-card
                label="Teacher profiles"
                :value="number_format($teacherCount)"
                detail="Across the selected campus scope"
                tone="primary"
                icon="fa-chalkboard-user"
            />
            <x-admin.stat-card
                label="Active teachers"
                :value="number_format($activeTeacherCount)"
                detail="Available for assignments"
                tone="success"
                icon="fa-user-check"
            />
            <x-admin.stat-card
                label="Teaching assignments"
                :value="number_format($assignmentCount)"
                detail="Across all academic years"
                tone="info"
                icon="fa-book-open-reader"
            />
        </section>

        @php
            $assignmentHasErrors = $errors->has('teacher_id')
                || $errors->has('academic_year_id')
                || $errors->has('school_class_id')
                || $errors->has('section_id')
                || $errors->has('course_id')
                || $errors->has('is_class_teacher')
                || $errors->has('teaching_group');
            $profileHasErrors = $errors->has('branch_id')
                || $errors->has('user_id')
                || $errors->has('employee_number')
                || $errors->has('phone');
            $requestedTeacherTab = old('return_tab', request('tab', 'profiles'));
            $activeTeacherTab = $assignmentHasErrors
                ? 'assignments'
                : (in_array($requestedTeacherTab, ['profiles', 'assignments'], true) ? $requestedTeacherTab : 'profiles');
            $profileFormOpen = $profileHasErrors;
            $assignmentFormOpen = $assignmentHasErrors;
        @endphp

        <div class="teacher-tabs">
            <div class="teacher-tab-list" role="tablist" aria-label="Teacher management">
                <button
                    id="teacher-profiles-tab"
                    class="teacher-tab-button {{ $activeTeacherTab === 'profiles' ? 'is-active' : '' }}"
                    type="button"
                    role="tab"
                    aria-controls="teacher-profiles-panel"
                    aria-selected="{{ $activeTeacherTab === 'profiles' ? 'true' : 'false' }}"
                    tabindex="{{ $activeTeacherTab === 'profiles' ? '0' : '-1' }}"
                    data-teacher-tab="profiles"
                >
                    <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                    <span>Teacher profiles</span>
                    <span class="teacher-tab-count">{{ number_format($teacherCount) }}</span>
                </button>
                <button
                    id="teacher-assignments-tab"
                    class="teacher-tab-button {{ $activeTeacherTab === 'assignments' ? 'is-active' : '' }}"
                    type="button"
                    role="tab"
                    aria-controls="teacher-assignments-panel"
                    aria-selected="{{ $activeTeacherTab === 'assignments' ? 'true' : 'false' }}"
                    tabindex="{{ $activeTeacherTab === 'assignments' ? '0' : '-1' }}"
                    data-teacher-tab="assignments"
                >
                    <i class="fa-solid fa-book-open" aria-hidden="true"></i>
                    <span>Teaching assignments</span>
                    <span class="teacher-tab-count">{{ number_format($assignmentCount) }}</span>
                </button>
            </div>

            <section
                id="teacher-profiles-panel"
                class="teacher-workflow teacher-tab-panel"
                role="tabpanel"
                aria-labelledby="teacher-profiles-tab"
                data-teacher-panel="profiles"
                @if ($activeTeacherTab !== 'profiles') hidden @endif
            >
            <x-admin.card class="teacher-workflow-card">
                <div class="teacher-section-toolbar">
                    <div class="teacher-section-heading">
                        <span class="teacher-section-icon teacher-section-icon-blue" aria-hidden="true">
                            <i class="fa-solid fa-user-plus"></i>
                        </span>
                        <div>
                            <span class="teacher-step-number">01</span>
                            <h2>Teacher profiles</h2>
                            <p>Connect an existing teacher account to a campus.</p>
                        </div>
                    </div>
                    <button
                        class="btn btn-primary teacher-create-button"
                        type="button"
                        data-form-toggle="profiles"
                        aria-controls="teacher-profile-form"
                        aria-expanded="{{ $profileFormOpen ? 'true' : 'false' }}"
                    >
                        <i class="fa-solid fa-plus mr-2" aria-hidden="true"></i>
                        <span data-create-label>{{ $profileFormOpen ? 'Hide form' : 'Create teacher profile' }}</span>
                    </button>
                </div>

                <div id="teacher-profile-form" class="teacher-inline-form-panel" data-create-panel="profiles" @if (! $profileFormOpen) hidden @endif>
                    @if ($availableUsers->isEmpty())
                        <x-admin.empty-state
                            title="No eligible teacher accounts"
                            description="Add a user to this institution and assign them the Teacher role before creating a campus profile."
                        />
                    @else
                        <form method="post" action="{{ route('academic.teachers.store') }}" class="teacher-form">
                            @csrf
                            <input type="hidden" name="return_tab" value="profiles">
                            <div class="form-row">
                                <div class="form-group col-12 col-md-6">
                                    <label for="teacher_branch">Campus <span class="text-danger">*</span></label>
                                    <select id="teacher_branch" name="branch_id" class="form-control" required>
                                        <option value="">Choose campus</option>
                                        @foreach ($branches as $branch)
                                            <option value="{{ $branch->id }}" @selected(old('branch_id', $branchId) == $branch->id)>
                                                {{ $branch->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('branch_id') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-12 col-md-6">
                                    <label for="user_id">Teacher account <span class="text-danger">*</span></label>
                                    <select id="user_id" name="user_id" class="form-control" required>
                                        <option value="">Choose teacher account</option>
                                        @foreach ($availableUsers as $user)
                                            <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                                                {{ $user->name }} · {{ $user->email }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('user_id') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-12 col-md-6">
                                    <label for="employee_number">Employee number</label>
                                    <input
                                        id="employee_number"
                                        name="employee_number"
                                        class="form-control"
                                        value="{{ old('employee_number') }}"
                                        maxlength="50"
                                        autocomplete="off"
                                        placeholder="Generated automatically if blank"
                                    >
                                    @error('employee_number') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                                <div class="form-group col-12 col-md-6">
                                    <label for="teacher_phone">Phone</label>
                                    <input
                                        id="teacher_phone"
                                        name="phone"
                                        type="tel"
                                        class="form-control"
                                        value="{{ old('phone') }}"
                                        maxlength="30"
                                        autocomplete="tel"
                                        placeholder="Optional contact number"
                                    >
                                    @error('phone') <small class="text-danger">{{ $message }}</small> @enderror
                                </div>
                            </div>
                            <div class="teacher-form-actions">
                                <button class="btn btn-outline-secondary" type="button" data-form-close="profiles">Cancel</button>
                                <button class="btn btn-primary" type="submit">Create profile</button>
                            </div>
                        </form>
                    @endif
                </div>

                <div class="teacher-data-heading">
                    <div>
                        <h3>Current teacher profiles</h3>
                        <p>Campus contact details and assignment totals.</p>
                    </div>
                    <span class="teacher-count-pill">{{ number_format($teacherCount) }} {{ \Illuminate\Support\Str::plural('teacher', $teacherCount) }}</span>
                </div>
                <div class="table-responsive">
                    <x-admin.table>
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Employee number</th>
                                <th>Campus</th>
                                <th>Phone</th>
                                <th>Assignments</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($teachers as $teacher)
                                <tr>
                                    <td>
                                        <strong>{{ $teacher->user?->name ?? 'Account removed' }}</strong>
                                        <small class="d-block text-muted">{{ $teacher->user?->email ?? '—' }}</small>
                                    </td>
                                    <td>{{ $teacher->employee_number }}</td>
                                    <td>{{ $teacher->branch?->name ?? '—' }}</td>
                                    <td>{{ $teacher->phone ?: '—' }}</td>
                                    <td><span class="teacher-assignment-count">{{ $teacher->assignments_count }}</span></td>
                                    <td>
                                        @if ($teacher->is_active)
                                            <x-admin.badge tone="success">Active</x-admin.badge>
                                        @else
                                            <x-admin.badge tone="slate">Inactive</x-admin.badge>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="py-4">
                                        <x-admin.empty-state title="No teacher profiles yet" description="Create a profile above to start assigning teaching loads." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-admin.table>
                </div>
            </x-admin.card>
            </section>

            <section
                id="teacher-assignments-panel"
                class="teacher-workflow teacher-tab-panel"
                role="tabpanel"
                aria-labelledby="teacher-assignments-tab"
                data-teacher-panel="assignments"
                @if ($activeTeacherTab !== 'assignments') hidden @endif
            >
            <x-admin.card class="teacher-workflow-card">
                <div class="teacher-section-toolbar">
                    <div class="teacher-section-heading">
                        <span class="teacher-section-icon teacher-section-icon-green" aria-hidden="true">
                            <i class="fa-solid fa-book-open"></i>
                        </span>
                        <div>
                            <span class="teacher-step-number">02</span>
                            <h2>Teaching assignments</h2>
                            <p>Choose a teacher, year, then a class or course.</p>
                        </div>
                    </div>
                    <button
                        class="btn btn-primary teacher-create-button"
                        type="button"
                        data-form-toggle="assignments"
                        aria-controls="teacher-assignment-form"
                        aria-expanded="{{ $assignmentFormOpen ? 'true' : 'false' }}"
                    >
                        <i class="fa-solid fa-plus mr-2" aria-hidden="true"></i>
                        <span data-create-label>{{ $assignmentFormOpen ? 'Hide form' : 'Add teaching assignment' }}</span>
                    </button>
                </div>

                <div id="teacher-assignment-form" class="teacher-inline-form-panel" data-create-panel="assignments" @if (! $assignmentFormOpen) hidden @endif>
                @if ($teachers->isEmpty())
                    <x-admin.empty-state
                        title="Create a teacher profile first"
                        description="Teacher assignments are campus-specific, so a profile is needed before assigning a class or course."
                    />
                @elseif ($years->isEmpty())
                    <x-admin.empty-state
                        title="No academic years available"
                        description="Create an academic year for this campus before adding teaching assignments."
                    />
                @else
                    <form method="post" action="{{ route('academic.teacher-assignments.store') }}" class="teacher-form" data-teacher-assignment-form>
                        @csrf
                        <input type="hidden" name="return_tab" value="assignments">
                        <div class="form-row">
                            <div class="form-group col-12 col-md-6">
                                <label for="teacher_id">Teacher <span class="text-danger">*</span></label>
                                <select id="teacher_id" name="teacher_id" class="form-control" required data-assignment-teacher>
                                    <option value="">Choose teacher</option>
                                    @foreach ($teachers as $teacher)
                                        <option value="{{ $teacher->id }}" data-branch="{{ $teacher->branch_id }}" @selected(old('teacher_id') == $teacher->id)>
                                            {{ $teacher->user?->name ?? 'Account removed' }} · {{ $teacher->branch?->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('teacher_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-12 col-md-6">
                                <label for="academic_year_id">Academic year <span class="text-danger">*</span></label>
                                <select id="academic_year_id" name="academic_year_id" class="form-control" required data-assignment-year>
                                    <option value="">Choose academic year</option>
                                    @foreach ($years as $year)
                                        <option value="{{ $year->id }}" data-branch="{{ $year->branch_id }}" @selected(old('academic_year_id') == $year->id)>
                                            {{ $year->branch?->name }} · {{ $year->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('academic_year_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-12 col-md-6">
                                <label for="school_class_id">Class <span class="text-muted font-weight-normal">(optional)</span></label>
                                <select id="school_class_id" name="school_class_id" class="form-control" data-assignment-class>
                                    <option value="">No class</option>
                                    @foreach ($classes as $class)
                                        <option value="{{ $class->id }}" data-branch="{{ $class->branch_id }}" data-year="{{ $class->academic_year_id }}" @selected(old('school_class_id') == $class->id)>
                                            {{ $class->name }} · {{ $class->academicYear?->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('school_class_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-12 col-md-6">
                                <label for="course_id">Course <span class="text-muted font-weight-normal">(optional)</span></label>
                                <select id="course_id" name="course_id" class="form-control" data-assignment-course>
                                    <option value="">No course</option>
                                    @foreach ($courses as $course)
                                        <option value="{{ $course->id }}" data-branch="{{ $course->branch_id }}" @selected(old('course_id') == $course->id)>
                                            {{ $course->name }} · {{ $course->branch?->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('course_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-12 mb-2">
                                <p class="teacher-group-guidance mb-0">Choose a class, a course, or both. You can leave Class blank when assigning by course.</p>
                                <p class="teacher-group-error d-none" data-teaching-group-error role="alert" aria-live="polite"></p>
                                @error('teaching_group') <small class="text-danger d-block">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-12 col-md-6">
                                <label for="section_id">Section <span class="text-muted font-weight-normal">(optional)</span></label>
                                <select id="section_id" name="section_id" class="form-control" data-assignment-section>
                                    <option value="">No section</option>
                                    @foreach ($sections as $section)
                                        <option value="{{ $section->id }}" data-branch="{{ $section->branch_id }}" @selected(old('section_id') == $section->id)>
                                            Section {{ $section->name }} · {{ $section->branch?->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('section_id') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                            <div class="form-group col-12 col-md-6 d-flex align-items-center mb-md-0">
                                <div class="custom-control custom-checkbox teacher-class-teacher-control">
                                    <input id="is_class_teacher" name="is_class_teacher" type="checkbox" value="1" class="custom-control-input" @checked(old('is_class_teacher'))>
                                    <label class="custom-control-label" for="is_class_teacher">Assign as class teacher</label>
                                    <small class="d-block text-muted">Requires both a class and a section.</small>
                                </div>
                                @error('is_class_teacher') <small class="text-danger">{{ $message }}</small> @enderror
                            </div>
                        </div>
                        <div class="teacher-form-actions">
                            <button class="btn btn-outline-secondary" type="button" data-form-close="assignments">Cancel</button>
                            <button class="btn btn-primary" type="submit">
                                <i class="fa-solid fa-link mr-2" aria-hidden="true"></i>Save assignment
                            </button>
                        </div>
                    </form>
                @endif
                </div>

                <div class="teacher-data-heading">
                    <div>
                        <h3>Current teaching assignments</h3>
                        <p>Review each teacher’s academic year and teaching group.</p>
                    </div>
                    <span class="teacher-count-pill">{{ number_format($assignmentCount) }} {{ \Illuminate\Support\Str::plural('assignment', $assignmentCount) }}</span>
                </div>
                <div class="table-responsive">
                    <x-admin.table>
                        <thead>
                            <tr>
                                <th>Teacher</th>
                                <th>Academic year</th>
                                <th>Class / section</th>
                                <th>Course</th>
                                <th>Assignment role</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($assignments as $assignment)
                                <tr>
                                    <td>
                                        <strong>{{ $assignment->teacher?->user?->name ?? 'Account removed' }}</strong>
                                        <small class="d-block text-muted">{{ $assignment->teacher?->branch?->name ?? '—' }}</small>
                                    </td>
                                    <td>{{ $assignment->academicYear?->name ?? '—' }}</td>
                                    <td>
                                        @if ($assignment->schoolClass)
                                            <strong>{{ $assignment->schoolClass->name }}</strong>
                                            @if ($assignment->section)
                                                <span class="teacher-section-chip">Section {{ $assignment->section->name }}</span>
                                            @endif
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $assignment->course?->name ?? '—' }}</td>
                                    <td>
                                        @if ($assignment->is_class_teacher)
                                            <x-admin.badge tone="info">Class teacher</x-admin.badge>
                                        @else
                                            <span class="text-muted">Teacher</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4">
                                        <x-admin.empty-state title="No teaching assignments yet" description="Add an assignment above to link a teacher to a class or course." />
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-admin.table>
                </div>
                @if ($assignments->hasPages())
                    <div class="p-3">
                        <x-admin.pagination :paginator="$assignments" />
                    </div>
                @endif
            </x-admin.card>
            </section>
        </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const tabButtons = Array.from(document.querySelectorAll('[data-teacher-tab]'));
            const tabPanels = Array.from(document.querySelectorAll('[data-teacher-panel]'));

            const activateTeacherTab = function (tabName, moveFocus = false) {
                tabButtons.forEach(function (button) {
                    const isActive = button.dataset.teacherTab === tabName;
                    button.classList.toggle('is-active', isActive);
                    button.setAttribute('aria-selected', String(isActive));
                    button.setAttribute('tabindex', isActive ? '0' : '-1');

                    if (isActive && moveFocus) {
                        button.focus();
                    }
                });

                tabPanels.forEach(function (panel) {
                    panel.hidden = panel.dataset.teacherPanel !== tabName;
                });
            };

            tabButtons.forEach(function (button, index) {
                button.addEventListener('click', function () {
                    activateTeacherTab(button.dataset.teacherTab);
                });

                button.addEventListener('keydown', function (event) {
                    if (! ['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) {
                        return;
                    }

                    event.preventDefault();
                    const nextIndex = event.key === 'Home'
                        ? 0
                        : event.key === 'End'
                            ? tabButtons.length - 1
                            : (index + (event.key === 'ArrowRight' ? 1 : tabButtons.length - 1)) % tabButtons.length;

                    activateTeacherTab(tabButtons[nextIndex].dataset.teacherTab, true);
                });
            });

            const formPanels = Array.from(document.querySelectorAll('[data-create-panel]'));
            const formToggleButtons = Array.from(document.querySelectorAll('[data-form-toggle]'));
            const formCloseButtons = Array.from(document.querySelectorAll('[data-form-close]'));

            const setCreateFormVisibility = function (formName, shouldOpen) {
                const panel = formPanels.find((item) => item.dataset.createPanel === formName);
                const button = formToggleButtons.find((item) => item.dataset.formToggle === formName);

                if (! panel || ! button) {
                    return;
                }

                panel.hidden = ! shouldOpen;
                button.setAttribute('aria-expanded', String(shouldOpen));
                button.querySelector('[data-create-label]').textContent = shouldOpen
                    ? 'Hide form'
                    : (formName === 'profiles' ? 'Create teacher profile' : 'Add teaching assignment');

                if (shouldOpen) {
                    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    panel.querySelector('input:not([type="hidden"]), select, textarea')?.focus({ preventScroll: true });
                }
            };

            formToggleButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    const panel = formPanels.find((item) => item.dataset.createPanel === button.dataset.formToggle);
                    setCreateFormVisibility(button.dataset.formToggle, panel?.hidden ?? false);
                });
            });

            formCloseButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    setCreateFormVisibility(button.dataset.formClose, false);
                });
            });

            const form = document.querySelector('[data-teacher-assignment-form]');

            if (! form) {
                return;
            }

            const teacher = form.querySelector('[data-assignment-teacher]');
            const year = form.querySelector('[data-assignment-year]');
            const schoolClass = form.querySelector('[data-assignment-class]');
            const section = form.querySelector('[data-assignment-section]');
            const course = form.querySelector('[data-assignment-course]');
            const groupError = form.querySelector('[data-teaching-group-error]');

            const filterOptions = function (select, predicate) {
                Array.from(select.options).forEach(function (option) {
                    option.hidden = option.value !== '' && ! predicate(option);

                    if (option.hidden && option.selected) {
                        select.value = '';
                    }
                });
            };

            const updateAcademicOptions = function () {
                const teacherBranch = teacher.selectedOptions[0]?.dataset.branch || '';
                filterOptions(year, (option) => ! teacherBranch || option.dataset.branch === teacherBranch);

                const selectedYear = year.value;
                filterOptions(schoolClass, (option) => (! teacherBranch || option.dataset.branch === teacherBranch)
                    && (! selectedYear || option.dataset.year === selectedYear));
                filterOptions(section, (option) => ! teacherBranch || option.dataset.branch === teacherBranch);
                filterOptions(course, (option) => ! teacherBranch || option.dataset.branch === teacherBranch);
            };

            const classTeacher = form.querySelector('#is_class_teacher');
            const updateAssignmentRequirements = function () {
                schoolClass.required = classTeacher.checked;
                section.required = classTeacher.checked;

                if (schoolClass.value || course.value) {
                    groupError.textContent = '';
                    groupError.classList.add('d-none');
                }
            };

            teacher.addEventListener('change', updateAcademicOptions);
            year.addEventListener('change', updateAcademicOptions);
            classTeacher.addEventListener('change', updateAssignmentRequirements);
            schoolClass.addEventListener('change', updateAssignmentRequirements);
            course.addEventListener('change', updateAssignmentRequirements);
            form.addEventListener('submit', function (event) {
                updateAssignmentRequirements();

                if (! schoolClass.value && ! course.value) {
                    groupError.textContent = 'Choose at least one teaching group: a class or a course.';
                    groupError.classList.remove('d-none');
                    event.preventDefault();
                }
            });
            updateAcademicOptions();
            updateAssignmentRequirements();
        });
    </script>
@endsection
