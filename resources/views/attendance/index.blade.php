@extends('layouts.app')

@section('content')
    <div class="attendance-page">
        <x-admin.page-header
            title="Attendance"
            description="Choose a class group, mark the register, and save attendance for the selected day."
        />

        <details
            class="admin-card attendance-selection"
            aria-labelledby="attendance-selection-title"
            @if (! ($canLoadStudents && $students->isNotEmpty())) open @endif
        >
            <summary class="attendance-selection-summary">
                <span class="attendance-step">1</span>
                <span class="attendance-selection-copy">
                    <strong id="attendance-selection-title">Choose attendance group</strong>
                    <small>Select the branch, academic year, and class group you want to take attendance for.</small>
                </span>
                <span class="attendance-filter-control">
                    <span class="attendance-filter-show"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Show filters</span>
                    <span class="attendance-filter-hide"><i class="fa-solid fa-chevron-up" aria-hidden="true"></i> Hide filters</span>
                </span>
            </summary>

            <form method="get" action="{{ route('attendance.index') }}" class="attendance-selection-form">
                <div class="form-row">
                    @if (auth()->user()->currentBranchId())
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                        <div class="form-group col-md-6 col-xl-3">
                            <label for="attendance-branch">Branch</label>
                            <input
                                id="attendance-branch"
                                class="form-control"
                                value="{{ $branches->firstWhere('id', $branchId)?->name ?? 'Current branch' }}"
                                readonly
                            >
                        </div>
                    @else
                        <div class="form-group col-md-6 col-xl-3">
                            <label for="branch_id">Branch</label>
                            <select class="form-control" name="branch_id" id="branch_id" required>
                                <option value="">Select a branch</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected($branchId == $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="form-group col-md-6 col-xl-3">
                        <label for="attendance_date">Attendance date</label>
                        <input
                            class="form-control"
                            type="text"
                            id="attendance_date"
                            value="{{ $date->format('d/m/Y') }}"
                            autocomplete="off"
                            readonly
                            aria-describedby="attendance-date-help"
                        >
                        <small id="attendance-date-help" class="form-text text-muted">Attendance is recorded for today.</small>
                    </div>

                    <div class="form-group col-md-6 col-xl-3">
                        <label for="academic_year_id">Academic year</label>
                        <select class="form-control" id="academic_year_id" name="academic_year_id" required>
                            <option value="">Select an academic year</option>
                            @foreach ($years as $year)
                                <option value="{{ $year->id }}" @selected($academicYearId == $year->id)>
                                    {{ $year->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if ($institutionType === 'college')
                        <div class="form-group col-md-6 col-xl-3">
                            <label for="course_id">Course</label>
                            <select class="form-control" id="course_id" name="course_id" required>
                                <option value="">Select a course</option>
                                @foreach ($courses as $course)
                                    <option value="{{ $course->id }}" @selected($courseId == $course->id)>
                                        {{ $course->name }}{{ $course->code ? ' · '.$course->code : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6 col-xl-3">
                            <label for="semester_id">Semester</label>
                            <select class="form-control" id="semester_id" name="semester_id" required @disabled(! $courseId)>
                                <option value="">Select a semester</option>
                                @foreach ($semesters as $semester)
                                    <option value="{{ $semester->id }}" @selected($semesterId == $semester->id)>
                                        {{ $semester->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @else
                        <div class="form-group col-md-6 col-xl-3">
                            <label for="school_class_id">Class</label>
                            <select class="form-control" id="school_class_id" name="school_class_id" required @disabled(! $academicYearId)>
                                <option value="">Select a class</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}" @selected($schoolClassId == $class->id)>
                                        {{ $class->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-6 col-xl-3">
                            <label for="section_id">Section</label>
                            <select class="form-control" id="section_id" name="section_id" required>
                                <option value="">Select a section</option>
                                @foreach ($sections as $section)
                                    <option value="{{ $section->id }}" @selected($sectionId == $section->id)>
                                        {{ $section->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>

                <div class="attendance-selection-actions">
                    <span><i class="fa-regular fa-circle-check" aria-hidden="true"></i> Attendance is saved for this group and date.</span>
                    <button class="btn btn-primary" type="submit">
                        <i class="fa-solid fa-users-viewfinder mr-1" aria-hidden="true"></i>
                        Show student list
                    </button>
                </div>
            </form>
        </details>

        @error('records')
            <div class="alert alert-danger" role="alert">{{ $message }}</div>
        @enderror

        @if (! $branchId)
            <div class="admin-card"><x-admin.empty-state title="Choose a branch" description="Select a branch to view its academic years and groups." /></div>
        @elseif (! $canLoadStudents)
            <div class="admin-card"><x-admin.empty-state title="Select an attendance group" description="Choose the branch, academic year, and required class and section or course and semester, then show the student list." /></div>
        @elseif ($students->isEmpty())
            <div class="admin-card"><x-admin.empty-state title="No active students in this group" description="Check that active students are enrolled in the selected group and academic year." /></div>
        @else
            <form method="post" action="{{ route('attendance.store') }}">
                @csrf
                <input type="hidden" name="attendance_date" value="{{ $date->format('Y-m-d') }}">
                <input type="hidden" name="branch_id" value="{{ $branchId }}">
                <input type="hidden" name="academic_year_id" value="{{ $academicYearId }}">
                @if ($institutionType === 'college')
                    <input type="hidden" name="course_id" value="{{ $courseId }}">
                    <input type="hidden" name="semester_id" value="{{ $semesterId }}">
                @else
                    <input type="hidden" name="school_class_id" value="{{ $schoolClassId }}">
                    <input type="hidden" name="section_id" value="{{ $sectionId }}">
                @endif

                <section class="admin-card attendance-roster" aria-labelledby="attendance-roster-title">
                    <div class="attendance-roster-heading">
                        <div class="attendance-section-heading">
                            <span class="attendance-step">2</span>
                            <div>
                                <h2 id="attendance-roster-title">Mark attendance</h2>
                                <p>{{ $selectedGroupName }} <span aria-hidden="true">·</span> {{ $date->format('d M Y') }}</p>
                            </div>
                        </div>
                        <div class="attendance-roster-summary">
                            <span class="attendance-student-total">
                                <i class="fa-solid fa-user-group" aria-hidden="true"></i>
                                {{ $students->count() }} {{ str('student')->plural($students->count()) }}
                            </span>
                            <button class="btn btn-primary" type="submit">
                                <i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i>
                                Save attendance
                            </button>
                        </div>
                    </div>

                    <div class="attendance-tools" data-attendance-tools>
                        <div class="attendance-tools-top">
                            <div class="attendance-bulk-actions" aria-label="Bulk attendance actions">
                                <span class="attendance-tool-label">Mark everyone</span>
                                <button class="btn btn-sm btn-outline-success" type="button" data-attendance-set="present">Present</button>
                                <button class="btn btn-sm btn-outline-danger" type="button" data-attendance-set="absent">Absent</button>
                                <button class="btn btn-sm btn-outline-warning" type="button" data-attendance-set="late">Late</button>
                            </div>

                            <div class="attendance-counts" aria-live="polite" aria-label="Attendance totals">
                                <span class="attendance-count attendance-count-present">Present <strong data-attendance-count="present">0</strong></span>
                                <span class="attendance-count attendance-count-absent">Absent <strong data-attendance-count="absent">0</strong></span>
                                <span class="attendance-count attendance-count-late">Late <strong data-attendance-count="late">0</strong></span>
                                <span class="attendance-count attendance-count-other">Other <strong data-attendance-count="other">0</strong></span>
                            </div>
                        </div>

                        <div class="attendance-search-wrap">
                            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                            <label for="attendance_student_search" class="sr-only">Find a student</label>
                            <input
                                id="attendance_student_search"
                                type="search"
                                class="form-control"
                                data-attendance-search
                                placeholder="Find by student name or ID"
                            >
                        </div>
                    </div>

                    <x-admin.table class="attendance-table">
                        <thead>
                            <tr>
                                <th scope="col">Student</th>
                                <th scope="col">{{ $institutionType === 'college' ? 'Course / semester' : 'Class / section' }}</th>
                                <th scope="col">Attendance status</th>
                                <th scope="col">Remarks <span class="font-weight-normal text-muted">(optional)</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($students as $student)
                                @php($rowIndex = $loop->index)
                                @php($enrollment = $enrollments->get($student->id))
                                @php($selectedStatus = old("records.{$rowIndex}.status", $existing->get($student->id)?->status ?? 'present'))
                                @php($groupLabel = $institutionType === 'college' ? ($enrollment?->course?->name.' · '.$enrollment?->semester?->name) : ($enrollment?->schoolClass?->name.' · '.$enrollment?->section?->name))
                                <tr data-attendance-row data-student-search="{{ strtolower($student->first_name.' '.$student->last_name.' '.$student->student_id.' '.$student->roll_number.' '.$student->gr_number) }}">
                                    <td>
                                        <div class="attendance-student-cell">
                                            <span class="attendance-student-avatar" aria-hidden="true">
                                                {{ strtoupper(mb_substr($student->first_name, 0, 1).mb_substr($student->last_name ?? '', 0, 1)) }}
                                            </span>
                                            <span>
                                                <strong>{{ $student->first_name }} {{ $student->last_name }}</strong>
                                                <small>{{ $student->student_id }}</small>
                                                <small>R.NO {{ $student->roll_number }} · GR.NO {{ $student->gr_number }}</small>
                                                <small class="attendance-mobile-group">{{ $groupLabel }}</small>
                                            </span>
                                        </div>
                                        <input type="hidden" name="records[{{ $rowIndex }}][student_id]" value="{{ $student->id }}">
                                    </td>
                                    <td class="attendance-group-cell">
                                        {{ $groupLabel }}
                                    </td>
                                    <td>
                                        <div class="attendance-status-controls">
                                            <div class="attendance-status-quick" role="group" aria-label="Quick attendance status for {{ $student->first_name }} {{ $student->last_name }}">
                                                <button
                                                    class="attendance-quick-status {{ $selectedStatus === 'present' ? 'is-selected is-present' : '' }}"
                                                    type="button"
                                                    data-attendance-choice="present"
                                                    aria-pressed="{{ $selectedStatus === 'present' ? 'true' : 'false' }}"
                                                >
                                                    Present
                                                </button>
                                                <button
                                                    class="attendance-quick-status {{ $selectedStatus === 'absent' ? 'is-selected is-absent' : '' }}"
                                                    type="button"
                                                    data-attendance-choice="absent"
                                                    aria-pressed="{{ $selectedStatus === 'absent' ? 'true' : 'false' }}"
                                                >
                                                    Absent
                                                </button>
                                            </div>
                                            <label class="sr-only" for="attendance-status-{{ $student->id }}">Attendance status for {{ $student->first_name }} {{ $student->last_name }}</label>
                                            <select
                                                class="form-control attendance-status-select"
                                                id="attendance-status-{{ $student->id }}"
                                                name="records[{{ $rowIndex }}][status]"
                                                data-attendance-status
                                                required
                                            >
                                                @foreach (['present' => 'Present', 'absent' => 'Absent', 'late' => 'Late', 'half_day' => 'Half day', 'leave' => 'Leave'] as $value => $label)
                                                    <option value="{{ $value }}" @selected($selectedStatus === $value)>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </td>
                                    <td>
                                        <label class="sr-only" for="attendance-remarks-{{ $student->id }}">Remarks for {{ $student->first_name }} {{ $student->last_name }}</label>
                                        <input
                                            class="form-control"
                                            id="attendance-remarks-{{ $student->id }}"
                                            name="records[{{ $rowIndex }}][remarks]"
                                            value="{{ old("records.{$rowIndex}.remarks", $existing->get($student->id)?->remarks) }}"
                                            placeholder="Add a note"
                                        >
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-admin.table>

                    <div class="attendance-footer-actions">
                        <span>Review the status totals before saving.</span>
                        <button class="btn btn-primary" type="submit">
                            <i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i>
                            Save attendance
                        </button>
                    </div>
                </section>
            </form>
        @endif
    </div>
@endsection
