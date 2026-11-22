@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Attendance records"
        description="Browse saved attendance by date, branch, and academic year. Open a record to see each student’s status."
    >
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('attendance.index') }}">
                <i class="fa-solid fa-clipboard-check mr-1" aria-hidden="true"></i>
                Take attendance
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="admin-card attendance-record-filter-card">
        <form method="get" action="{{ route('attendance.records') }}" class="js-filter-form" aria-label="Attendance record filters">
            <div class="form-row">
                <div class="form-group col-md-6 col-xl-3">
                    <label for="attendance-record-from">From date</label>
                    <input
                        class="form-control js-datepicker"
                        type="text"
                        id="attendance-record-from"
                        name="from_date"
                        value="{{ isset($filters['from_date']) ? \Carbon\Carbon::parse($filters['from_date'])->format('d/m/Y') : '' }}"
                        placeholder="DD/MM/YYYY"
                        autocomplete="off"
                    >
                </div>
                <div class="form-group col-md-6 col-xl-3">
                    <label for="attendance-record-to">To date</label>
                    <input
                        class="form-control js-datepicker"
                        type="text"
                        id="attendance-record-to"
                        name="to_date"
                        value="{{ isset($filters['to_date']) ? \Carbon\Carbon::parse($filters['to_date'])->format('d/m/Y') : '' }}"
                        placeholder="DD/MM/YYYY"
                        autocomplete="off"
                    >
                </div>
                <div class="form-group col-md-6 col-xl-3">
                    <label for="attendance-record-branch">Branch</label>
                    <select class="form-control" id="attendance-record-branch" name="branch_id">
                        <option value="">All accessible branches</option>
                        @foreach ($branchOptions as $branch)
                            <option value="{{ $branch->id }}" @selected(($filters['branch_id'] ?? null) == $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-6 col-xl-3">
                    <label for="attendance-record-year">Academic year</label>
                    <select class="form-control" id="attendance-record-year" name="academic_year_id">
                        <option value="">All academic years</option>
                        @foreach ($academicYearOptions as $year)
                            <option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? null) == $year->id)>
                                {{ $year->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                @if ($institutionType === 'college')
                    <div class="form-group col-md-6 col-xl-3">
                        <label for="attendance-record-course">Course</label>
                        <select class="form-control" id="attendance-record-course" name="course_id">
                            <option value="">All courses</option>
                            @foreach ($courseOptions as $course)
                                <option value="{{ $course->id }}" @selected(($filters['course_id'] ?? null) == $course->id)>
                                    {{ $course->name }}{{ $course->code ? ' · '.$course->code : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6 col-xl-3">
                        <label for="attendance-record-semester">Semester</label>
                        <select class="form-control" id="attendance-record-semester" name="semester_id">
                            <option value="">All semesters</option>
                            @foreach ($semesterOptions as $semester)
                                <option value="{{ $semester->id }}" @selected(($filters['semester_id'] ?? null) == $semester->id)>
                                    {{ $semester->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @else
                    <div class="form-group col-md-6 col-xl-3">
                        <label for="attendance-record-class">Class</label>
                        <select class="form-control" id="attendance-record-class" name="school_class_id">
                            <option value="">All classes</option>
                            @foreach ($classOptions as $class)
                                <option value="{{ $class->id }}" @selected(($filters['school_class_id'] ?? null) == $class->id)>
                                    {{ $class->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group col-md-6 col-xl-3">
                        <label for="attendance-record-section">Section</label>
                        <select class="form-control" id="attendance-record-section" name="section_id">
                            <option value="">All sections</option>
                            @foreach ($sectionOptions as $section)
                                <option value="{{ $section->id }}" @selected(($filters['section_id'] ?? null) == $section->id)>
                                    {{ $section->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>

            <div class="attendance-record-filter-actions">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>
                    Apply filters
                </button>
                <a class="btn btn-outline-secondary" href="{{ route('attendance.records') }}">Clear filters</a>
            </div>
        </form>
    </div>

    <section class="admin-card attendance-record-list" aria-labelledby="attendance-record-list-title">
        <div class="attendance-record-list-heading">
            <div>
                <h3 id="attendance-record-list-title">Saved attendance</h3>
                <p>{{ number_format($summaries->total()) }} attendance group(s) match the selected filters</p>
            </div>
        </div>

        <x-admin.table class="attendance-records-table">
            <thead>
                <tr>
                    <th scope="col">Date</th>
                    <th scope="col">Branch</th>
                    <th scope="col">Academic year / group</th>
                    <th scope="col">Students</th>
                    <th scope="col">Present</th>
                    <th scope="col">Absent</th>
                    <th scope="col">Late</th>
                    <th scope="col">Other</th>
                    <th scope="col"><span class="sr-only">Details</span></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summaries as $summary)
                    @php
                        $groupName = collect([
                            $summary->schoolClass?->name,
                            $summary->section?->name,
                        ])->filter()->implode(' · ');

                        if ($groupName === '') {
                            $groupName = collect([
                                $summary->course?->name,
                                $summary->semester?->name,
                            ])->filter()->implode(' · ');
                        }
                    @endphp
                    <tr>
                        <td class="attendance-record-date">
                            {{ \Carbon\Carbon::parse($summary->attendance_date)->format('d M Y') }}
                        </td>
                        <td>{{ $summary->branch?->name ?? '—' }}</td>
                        <td>
                            <span class="attendance-record-group-primary">{{ $groupName ?: 'Group not set' }}</span>
                            <span class="attendance-record-group-secondary">{{ $summary->academicYear?->name ?? '—' }}</span>
                        </td>
                        <td><span class="attendance-summary-count">{{ number_format($summary->student_count) }}</span></td>
                        <td><span class="attendance-summary-count is-present">{{ number_format($summary->present_count) }}</span></td>
                        <td><span class="attendance-summary-count is-absent">{{ number_format($summary->absent_count) }}</span></td>
                        <td><span class="attendance-summary-count is-late">{{ number_format($summary->late_count) }}</span></td>
                        <td><span class="attendance-summary-count">{{ number_format($summary->other_count) }}</span></td>
                        <td class="text-right">
                            <a
                                class="btn btn-sm btn-outline-primary attendance-details-button"
                                href="{{ route('attendance.records.show', [
                                    \Carbon\Carbon::parse($summary->attendance_date)->format('Y-m-d'),
                                    $summary->branch_id,
                                    $summary->academic_year_id,
                                    'school_class_id' => $summary->school_class_id ?? 0,
                                    'section_id' => $summary->section_id ?? 0,
                                    'course_id' => $summary->course_id ?? 0,
                                    'semester_id' => $summary->semester_id ?? 0,
                                ]) }}"
                            >
                                View details
                                <i class="fa-solid fa-arrow-right ml-1" aria-hidden="true"></i>
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9">
                            <div class="empty-state">
                                <span class="empty-state-mark"><i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i></span>
                                <h3>No attendance recorded yet</h3>
                                <p>Saved attendance sessions will appear here.</p>
                                <a class="btn btn-primary btn-sm" href="{{ route('attendance.index') }}">Take attendance</a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>

        @if ($summaries->hasPages())
            <div class="attendance-record-pagination">{{ $summaries->links() }}</div>
        @endif
    </section>
@endsection
