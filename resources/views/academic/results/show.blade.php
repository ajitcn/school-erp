@extends('layouts.app')

@section('content')
    @php
        $first = $records->first();
        $totalMarks = (float) $records->sum(fn ($record) => (float) $record->marks);
        $totalObtained = (float) $records->sum(fn ($record) => (float) $record->marks_obtained);
        $groupParams = [
            'school_class_id' => $first->school_class_id ?? 0,
            'section_id' => $first->section_id ?? 0,
            'course_id' => $first->course_id ?? 0,
            'semester_id' => $first->semester_id ?? 0,
            'examination_id' => $first->examination_id ?? 0,
            'group_status' => $first->status,
        ];
    @endphp

    <div class="academic-results-page">
        <x-admin.page-header
            title="Student result details"
            description="Review subject marks for this student's academic group."
        >
            <x-slot:actions>
                <a class="btn btn-outline-secondary" href="{{ route('academic.results') }}">Back to results</a>
                <a class="btn btn-primary" href="{{ route('academic.results.edit', ['studentId' => $first->student_id, 'academicYearId' => $first->academic_year_id] + $groupParams) }}">
                    <i class="fa-solid fa-pen mr-2" aria-hidden="true"></i>Edit results
                </a>
                <a class="btn btn-outline-success" href="{{ route('academic.results.download', ['student' => $first->student_id, 'academicYear' => $first->academic_year_id] + $groupParams) }}">
                    <i class="fa-solid fa-download mr-2" aria-hidden="true"></i>Download result
                </a>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.card class="mb-4">
            <div class="row">
                <div class="col-12 col-md-4 mb-3">
                    <small class="text-muted d-block">Student</small>
                    <strong>{{ trim(($first->student?->first_name ?? '').' '.($first->student?->last_name ?? '')) }}</strong>
                    <span class="text-muted">· {{ $first->student?->student_id ?? '—' }}</span>
                </div>
                <div class="col-6 col-md-4 mb-3">
                    <small class="text-muted d-block">Branch</small>
                    <strong>{{ $first->student?->branch?->name ?? '—' }}</strong>
                </div>
                <div class="col-6 col-md-4 mb-3">
                    <small class="text-muted d-block">Academic year</small>
                    <strong>{{ $first->academicYear?->name ?? '—' }}</strong>
                </div>
                <div class="col-6 col-md-4 mb-3">
                    <small class="text-muted d-block">Examination</small>
                    <strong>{{ $first->examination?->name ?? 'Not linked' }}</strong>
                </div>
                <div class="col-6 col-md-4 mb-3">
                    <small class="text-muted d-block">Class / section</small>
                    <strong>{{ $first->schoolClass?->name ?? '—' }}{{ $first->section ? ' · '.$first->section->name : '' }}</strong>
                </div>
                <div class="col-6 col-md-4 mb-3">
                    <small class="text-muted d-block">Course / semester</small>
                    <strong>{{ $first->course?->name ?? $first->semester?->course?->name ?? '—' }}{{ $first->semester ? ' · '.$first->semester->name : '' }}</strong>
                </div>
            </div>
            <hr>
            <div class="d-flex flex-wrap align-items-center justify-content-between">
                <div class="mb-2 mb-md-0">
                    <span class="text-muted mr-3">Total marks <strong class="text-dark">{{ number_format($totalMarks, 2) }}</strong></span>
                    <span class="text-muted">Total obtained <strong class="text-dark">{{ number_format($totalObtained, 2) }}</strong></span>
                </div>
                <x-admin.badge :tone="$first->status === 'published' ? 'success' : 'slate'">{{ ucfirst($first->status) }}</x-admin.badge>
            </div>
        </x-admin.card>

        <x-admin.card padding="false">
            <div class="table-responsive">
                <x-admin.table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Maximum marks</th>
                            <th>Marks obtained</th>
                            <th>Grade</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr>
                                <td><strong>{{ $record->subjectMaster?->name ?? $record->subject }}</strong></td>
                                <td>{{ $record->marks ?? '—' }}</td>
                                <td>{{ $record->marks_obtained ?? '—' }}</td>
                                <td>{{ $record->grade ?: '—' }}</td>
                                <td><x-admin.badge :tone="$record->status === 'published' ? 'success' : 'slate'">{{ ucfirst($record->status) }}</x-admin.badge></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-admin.table>
            </div>
        </x-admin.card>
    </div>
@endsection
