@extends('layouts.portal')

@section('content')
    @php
        $placement = $enrollment?->schoolClass?->name ?? $enrollment?->course?->name ?? 'Placement pending';
        $placementDetail = $enrollment?->section?->name ?? $enrollment?->semester?->name;
        $dueAssignments = $assignments
            ->filter(fn ($item) => $item->balanceDue() > 0)
            ->sortBy(fn ($item) => $item->component?->due_date?->timestamp ?? PHP_INT_MAX)
            ->values();
        $totalOutstanding = $assignments->sum(fn ($item) => $item->balanceDue());
        $totalPaid = $assignments->sum(fn ($item) => $item->totalDue() - $item->balanceDue());
        $nextDueAssignment = $dueAssignments->first();
    @endphp

    <header class="portal-page-hero">
        <div class="portal-page-hero-copy">
            <span class="portal-eyebrow">{{ ($isParentPortal ?? false) ? 'Family portal' : 'Student portal' }} · {{ $enrollment?->academicYear?->name ?? 'Academic year not assigned' }}</span>
            <h1>Hi, {{ $student->first_name }} <span aria-hidden="true">👋</span></h1>
            <p>{{ $student->branch?->name ?? 'Campus not assigned' }} · {{ $placement }}{{ $placementDetail ? ' · '.$placementDetail : '' }}</p>
        </div>
        <div class="portal-page-hero-identity">
            <span class="portal-profile-badge" aria-hidden="true">{{ strtoupper(substr($student->first_name, 0, 1).substr($student->last_name ?? '', 0, 1)) }}</span>
            <span><small>Student ID</small><strong>{{ $student->student_id }}</strong></span>
        </div>
    </header>

    <section class="portal-summary-grid" aria-label="Student overview">
        <x-admin.card class="portal-summary-card">
            <span class="portal-summary-icon" aria-hidden="true"><i class="fa-regular fa-calendar-check"></i></span>
            <p class="portal-summary-label">Attendance</p>
            <strong class="portal-summary-value">{{ $attendanceRate === null ? '—' : $attendanceRate.'%' }}</strong>
            <div class="small">{{ $presentCount }} of {{ $attendanceCount }} sessions present</div>
            <span class="portal-overview-link">Open attendance <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
        </x-admin.card>

        <x-admin.card class="portal-summary-card">
            <span class="portal-summary-icon" aria-hidden="true"><i class="fa-solid fa-indian-rupee-sign"></i></span>
            <p class="portal-summary-label">Fee balance</p>
            <strong class="portal-summary-value">₹{{ number_format($totalOutstanding, 2) }}</strong>
            <div class="small">{{ $dueAssignments->count() }} unpaid {{ \Illuminate\Support\Str::plural('item', $dueAssignments->count()) }}</div>
            <span class="portal-overview-link">Open fees <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
        </x-admin.card>

        <x-admin.card class="portal-summary-card">
            <span class="portal-summary-icon" aria-hidden="true"><i class="fa-solid fa-chart-column"></i></span>
            <p class="portal-summary-label">Published results</p>
            <strong class="portal-summary-value">{{ $results->count() }}</strong>
            <div class="small">Recent published subject results</div>
            <span class="portal-overview-link">Open results <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
        </x-admin.card>

        <x-admin.card class="portal-summary-card">
            <span class="portal-summary-icon" aria-hidden="true"><i class="fa-regular fa-credit-card"></i></span>
            <p class="portal-summary-label">Fees paid</p>
            <strong class="portal-summary-value">₹{{ number_format($totalPaid, 2) }}</strong>
            <div class="small">Across assigned fee items</div>
            <span class="portal-overview-link">Payment history <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
        </x-admin.card>
    </section>

    <section class="portal-overview-next" aria-label="Next steps">
        <x-admin.card>
            <span class="portal-section-kicker">UP NEXT</span>
            <h2>Keep up with school</h2>
            <div class="portal-overview-shortcuts">
                <a href="{{ route('portal.page', 'timetable') }}"><span class="portal-overview-shortcut-icon"><i class="fa-regular fa-clock" aria-hidden="true"></i></span><span><strong>View timetable</strong><small>Check your weekly schedule</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                <a href="{{ route('portal.page', 'examinations') }}"><span class="portal-overview-shortcut-icon"><i class="fa-regular fa-rectangle-list" aria-hidden="true"></i></span><span><strong>Examination schedule</strong><small>{{ $upcomingExaminations->first()?->name ?? 'See published examination dates' }}</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                <a href="{{ route('portal.page', 'notices') }}"><span class="portal-overview-shortcut-icon"><i class="fa-regular fa-message" aria-hidden="true"></i></span><span><strong>School notices</strong><small>{{ $notices->count() }} current {{ \Illuminate\Support\Str::plural('announcement', $notices->count()) }}</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
                <a href="{{ route('portal.page', 'profile') }}"><span class="portal-overview-shortcut-icon"><i class="fa-regular fa-id-card" aria-hidden="true"></i></span><span><strong>Student profile</strong><small>Review personal and enrollment details</small></span><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></a>
            </div>
        </x-admin.card>

        <x-admin.card class="portal-next-due-card">
            <span class="portal-section-kicker">FEE REMINDER</span>
            <h2>{{ $nextDueAssignment ? 'Next fee due' : 'No upcoming fee due' }}</h2>
            @if ($nextDueAssignment)
                <strong class="portal-next-due-amount">₹{{ number_format($nextDueAssignment->balanceDue(), 2) }}</strong>
                <p>{{ $nextDueAssignment->component?->name ?? $nextDueAssignment->component?->structure?->name ?? 'Fee item' }}</p>
                <small>Due {{ $nextDueAssignment->component?->due_date?->format('d M Y') ?? 'date not set' }}</small>
                <a class="btn btn-primary btn-block mt-3" href="{{ route('portal.page', 'fees') }}">View fee details</a>
            @else
                <p class="text-muted mb-0">You’re all caught up. New fee items will appear here when they are assigned.</p>
            @endif
        </x-admin.card>
    </section>
@endsection
