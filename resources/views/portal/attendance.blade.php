@extends('layouts.portal')

@section('content')
    <x-admin.page-header
        title="{{ $student->first_name }} {{ $student->last_name }} · Attendance"
        description="Attendance records for {{ $student->student_id }}. Most recent dates are shown first."
    />

    @php
        $attendanceRate = $attendanceCount > 0
            ? (int) round(($presentCount / $attendanceCount) * 100)
            : null;
    @endphp

    <div class="portal-attendance-summary">
        <span><strong>{{ $attendanceCount }}</strong> recorded sessions</span>
        <span><strong>{{ $presentCount }}</strong> present</span>
        <span><strong>{{ $attendanceRate === null ? '—' : $attendanceRate.'%' }}</strong> attendance rate</span>
    </div>

    <x-admin.card padding="false">
        <div class="card-header"><h2>Attendance history</h2></div>
        <x-admin.table class="portal-mobile-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Session</th>
                    <th>Class / section or course</th>
                    <th>Status</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $record)
                    @php
                        $groupName = $record->schoolClass?->name ?? $record->course?->name;
                        $groupDetail = $record->section?->name ?? $record->semester?->name;
                        $statusTone = match ($record->status) {
                            'present' => 'success',
                            'absent' => 'danger',
                            'leave' => 'warning',
                            default => 'slate',
                        };
                    @endphp
                    <tr>
                        <td data-label="Date">{{ $record->attendance_date?->format('d M Y') ?? '—' }}</td>
                        <td data-label="Session">{{ $record->session ?: 'Full day' }}</td>
                        <td data-label="Class / course">
                            @if ($groupName)
                                {{ $groupName }}{{ $groupDetail ? ' · '.$groupDetail : '' }}
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="Status"><x-admin.badge :tone="$statusTone">{{ ucfirst(str_replace('_', ' ', $record->status)) }}</x-admin.badge></td>
                        <td data-label="Remarks">{{ $record->remarks ?: '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-4">
                            <x-admin.empty-state title="No attendance records" description="Attendance will appear here after the school or college records it." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>
        @if ($records->hasPages())
            <div class="p-3">{{ $records->links() }}</div>
        @endif
    </x-admin.card>
@endsection
