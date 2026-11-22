@extends('layouts.app')

@section('content')
    @php
        $firstRecord = $records->first();
        $groupName = collect([
            $firstRecord?->schoolClass?->name,
            $firstRecord?->section?->name,
        ])->filter()->implode(' · ');

        if ($groupName === '') {
            $groupName = collect([
                $firstRecord?->course?->name,
                $firstRecord?->semester?->name,
            ])->filter()->implode(' · ');
        }
    @endphp

    <x-admin.page-header
        title="Attendance details"
        description="Student attendance recorded for this branch and academic year."
    >
        <x-slot:actions>
            <a class="btn btn-outline-secondary" href="{{ route('attendance.records') }}">
                <i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i>
                Back to records
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <section class="admin-card attendance-detail-context" aria-label="Attendance session details">
        <div class="attendance-detail-context-item">
            <span>Date</span>
            <strong>{{ $attendanceDate->format('d M Y') }}</strong>
        </div>
        <div class="attendance-detail-context-item">
            <span>Branch</span>
            <strong>{{ $branch->name }}</strong>
        </div>
        <div class="attendance-detail-context-item">
            <span>Academic year / group</span>
            <strong class="attendance-group-highlight">{{ $groupName ?: 'Group not set' }}</strong>
            <small>{{ $academicYear->name }}</small>
        </div>
        <div class="attendance-detail-context-item">
            <span>Students recorded</span>
            <strong>{{ number_format($records->count()) }}</strong>
        </div>
    </section>

    <section class="admin-card attendance-record-list" aria-labelledby="attendance-detail-list-title">
        <div class="attendance-record-list-heading">
            <div>
                <h3 id="attendance-detail-list-title">Student attendance</h3>
                <p>Recorded status and remarks for this session.</p>
            </div>
        </div>

        <x-admin.table class="attendance-records-table attendance-detail-table">
            <thead>
                <tr>
                    <th scope="col">Student</th>
                    <th scope="col">Student ID</th>
                    <th scope="col">Status</th>
                    <th scope="col">Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $statusTone = [
                            'present' => 'success',
                            'absent' => 'danger',
                            'late' => 'warning',
                            'half_day' => 'info',
                            'leave' => 'slate',
                        ][$record->status] ?? 'slate';
                    @endphp
                    <tr>
                        <td class="attendance-record-student">
                            {{ $record->student?->first_name }} {{ $record->student?->last_name }}
                        </td>
                        <td>{{ $record->student?->student_id ?? '—' }}</td>
                        <td>
                            <x-admin.badge :tone="$statusTone">
                                {{ ucfirst(str_replace('_', ' ', $record->status)) }}
                            </x-admin.badge>
                        </td>
                        <td>{{ $record->remarks ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="p-5 text-center text-muted">No attendance details are available for this session.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>
    </section>
@endsection
