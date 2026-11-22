@extends('layouts.portal')

@section('content')
    @php
        $placement = $enrollment?->schoolClass?->name ?? $enrollment?->course?->name ?? 'Placement pending';
        $placementDetail = $enrollment?->section?->name ?? $enrollment?->semester?->name;
        $pageDetails = [
            'profile' => ['title' => 'Student profile', 'description' => 'Student identity, enrollment, and family contact details.'],
            'subjects' => ['title' => 'Subjects & teachers', 'description' => 'Current subjects and teaching staff for this placement.'],
            'examinations' => ['title' => 'Examination schedule', 'description' => 'Published upcoming examinations for the current academic year.'],
            'results' => ['title' => 'Results', 'description' => 'Published subject results and examination scores.'],
            'timetable' => ['title' => 'Timetable', 'description' => 'Weekly class schedule and assigned teachers.'],
            'fees' => ['title' => 'Fees & installments', 'description' => 'Fee items, due dates, amounts paid, and remaining balances.'],
            'payments' => ['title' => 'Payment history', 'description' => 'Recorded payments and available receipts.'],
            'notices' => ['title' => 'Notices', 'description' => 'Announcements published for this student.'],
        ];
        $pageDetail = $pageDetails[$page];
    @endphp

    <header class="portal-page-hero portal-section-page-hero">
        <div class="portal-page-hero-copy">
            <span class="portal-eyebrow">{{ ($isParentPortal ?? false) ? 'Family portal' : 'Student portal' }} · {{ $student->branch?->name ?? 'Campus' }}</span>
            <h1>{{ $pageDetail['title'] }}</h1>
            <p>{{ $student->first_name }} {{ $student->last_name }} · {{ $student->student_id }}{{ $placement !== 'Placement pending' ? ' · '.$placement.($placementDetail ? ' · '.$placementDetail : '') : '' }}</p>
        </div>
        <a class="portal-page-home-link" href="{{ route('portal.dashboard') }}"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i><span>Overview</span></a>
    </header>

    @switch($page)
        @case('profile')
            <x-admin.card class="portal-detail-card">
                <span class="portal-section-kicker">STUDENT INFORMATION</span>
                <h2>Student profile</h2>
                <dl class="portal-profile-details">
                    <div><dt>Full name</dt><dd>{{ trim($student->first_name.' '.$student->last_name) }}</dd></div>
                    <div><dt>Student ID</dt><dd>{{ $student->student_id }}</dd></div>
                    <div><dt>Admission number</dt><dd>{{ $student->admission_number ?: '—' }}</dd></div>
                    <div><dt>Date of birth</dt><dd>{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt>Gender</dt><dd>{{ $student->gender ? ucfirst($student->gender) : '—' }}</dd></div>
                    <div><dt>Campus</dt><dd>{{ $student->branch?->name ?? '—' }}</dd></div>
                    <div><dt>Academic year</dt><dd>{{ $enrollment?->academicYear?->name ?? '—' }}</dd></div>
                    <div><dt>Class / course</dt><dd>{{ $placement }}{{ $placementDetail ? ' · '.$placementDetail : '' }}</dd></div>
                    <div><dt>Student email</dt><dd>{{ $student->email ?: '—' }}</dd></div>
                    <div><dt>Student phone</dt><dd>{{ $student->phone ?: '—' }}</dd></div>
                </dl>
                @if ($student->guardians->isNotEmpty())
                    <div class="portal-student-contact portal-student-guardians">
                        <span class="portal-section-kicker">PARENT / GUARDIAN CONTACTS</span>
                        <div class="portal-guardian-list">
                            @foreach ($student->guardians as $guardian)
                                <article>
                                    <strong>{{ trim($guardian->first_name.' '.$guardian->last_name) }}</strong>
                                    <small>{{ $guardian->relation ? ucfirst($guardian->relation) : 'Guardian' }}</small>
                                    @if ($guardian->phone)<a href="tel:{{ $guardian->phone }}"><i class="fa-solid fa-phone" aria-hidden="true"></i>{{ $guardian->phone }}</a>@endif
                                    @if ($guardian->email)<a href="mailto:{{ $guardian->email }}"><i class="fa-regular fa-envelope" aria-hidden="true"></i>{{ $guardian->email }}</a>@endif
                                </article>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-admin.card>
            @break

        @case('subjects')
            <div class="portal-page-grid">
                <x-admin.card class="portal-detail-card">
                    <span class="portal-section-kicker">CURRENT PLACEMENT</span>
                    <h2>Subjects</h2>
                    <p class="portal-detail-subtitle">{{ $placement }}{{ $placementDetail ? ' · '.$placementDetail : '' }}{{ $enrollment?->academicYear?->name ? ' · '.$enrollment->academicYear->name : '' }}</p>
                    @forelse ($subjects as $subject)
                        <article class="portal-record-row">
                            <span class="portal-record-icon portal-record-icon--violet"><i class="fa-solid fa-book-open" aria-hidden="true"></i></span>
                            <div class="portal-record-main"><strong>{{ $subject->name }}</strong><span>{{ $subject->code ?: 'Subject' }}</span></div>
                        </article>
                    @empty
                        <div class="portal-detail-empty">No active subjects have been published for this placement.</div>
                    @endforelse
                </x-admin.card>
                <x-admin.card class="portal-detail-card">
                    <span class="portal-section-kicker">TEACHING STAFF</span>
                    <h2>Teachers</h2>
                    @php
                        $teacherNames = $timetable->pluck('teacher.user.name')->filter()->unique()->values();
                    @endphp
                    @forelse ($teacherNames as $teacherName)
                        <article class="portal-record-row"><span class="portal-record-icon portal-record-icon--blue"><i class="fa-solid fa-user-tie" aria-hidden="true"></i></span><div class="portal-record-main"><strong>{{ $teacherName }}</strong><span>{{ $placement }}</span></div></article>
                    @empty
                        <div class="portal-detail-empty">Teaching staff will appear here when the timetable is published.</div>
                    @endforelse
                </x-admin.card>
            </div>
            @break

        @case('examinations')
            <x-admin.card padding="false" class="portal-list-card">
                <div class="card-header"><h2>Upcoming examinations</h2><span class="portal-section-count">{{ $upcomingExaminations->count() }} scheduled</span></div>
                @forelse ($upcomingExaminations as $examination)
                    <article class="portal-record-row">
                        <span class="portal-record-icon portal-record-icon--violet"><i class="fa-regular fa-rectangle-list" aria-hidden="true"></i></span>
                        <div class="portal-record-main"><strong>{{ $examination->name }}</strong><span>{{ ucfirst($examination->exam_type) }} · {{ $examination->academicYear?->name }}</span></div>
                        <strong class="portal-record-value">{{ $examination->starts_on?->format('d M Y') ?? 'Date pending' }}</strong>
                    </article>
                @empty
                    <div class="portal-empty-inline"><span class="portal-empty-icon"><i class="fa-regular fa-calendar" aria-hidden="true"></i></span><div><strong>No published examinations</strong><p>Upcoming examination schedules will appear here.</p></div></div>
                @endforelse
            </x-admin.card>
            @break

        @case('results')
            <x-admin.card padding="false" class="portal-list-card">
                <div class="card-header"><h2>Published results</h2><span class="portal-section-count">{{ $results->count() }} results</span></div>
                <x-admin.table class="portal-mobile-table">
                    <thead><tr><th>Examination</th><th>Academic year</th><th>Subject</th><th>Marks</th><th>Grade</th></tr></thead>
                    <tbody>
                        @forelse ($results as $result)
                            <tr><td data-label="Examination">{{ $result->examination?->name ?? 'General result' }}</td><td data-label="Academic year">{{ $result->academicYear?->name ?? '—' }}</td><td data-label="Subject">{{ $result->subject }}</td><td data-label="Marks">{{ $result->marks_obtained ?? '—' }} / {{ $result->marks ?? '—' }}</td><td data-label="Grade">{{ $result->grade ?? '—' }}</td></tr>
                        @empty
                            <tr><td colspan="5"><x-admin.empty-state title="No results published" description="Published examination results will appear here." /></td></tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
                @if ($results->hasPages())<div class="p-3">{{ $results->links() }}</div>@endif
            </x-admin.card>
            @break

        @case('timetable')
            <x-admin.card padding="false" class="portal-list-card">
                <div class="card-header"><h2>Weekly timetable</h2><span class="portal-section-count">{{ $timetable->count() }} periods</span></div>
                <x-admin.table class="portal-mobile-table">
                    <thead><tr><th>Day</th><th>Time</th><th>Subject</th><th>Teacher</th></tr></thead>
                    <tbody>
                        @forelse ($timetable as $period)
                            <tr><td data-label="Day">{{ $period->weekday }}</td><td data-label="Time">{{ substr($period->starts_at, 0, 5) }}–{{ substr($period->ends_at, 0, 5) }}</td><td data-label="Subject">{{ $period->subject ?? '—' }}</td><td data-label="Teacher">{{ $period->teacher?->user?->name ?? '—' }}</td></tr>
                        @empty
                            <tr><td colspan="4"><x-admin.empty-state title="No timetable published" description="Your class schedule will appear here when it is available." /></td></tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
            </x-admin.card>
            @break

        @case('fees')
            @php
                $dueAssignments = $assignments->filter(fn ($item) => $item->balanceDue() > 0)->sortBy(fn ($item) => $item->component?->due_date?->timestamp ?? PHP_INT_MAX);
                $outstanding = $assignments->sum(fn ($item) => $item->balanceDue());
            @endphp
            <div class="portal-summary-grid portal-summary-grid--single">
                <x-admin.card class="portal-summary-card"><span class="portal-summary-icon" aria-hidden="true"><i class="fa-solid fa-wallet"></i></span><p class="portal-summary-label">Total outstanding</p><strong class="portal-summary-value">₹{{ number_format($outstanding, 2) }}</strong><div class="small">{{ $dueAssignments->count() }} unpaid {{ \Illuminate\Support\Str::plural('item', $dueAssignments->count()) }}</div></x-admin.card>
            </div>
            <x-admin.card padding="false" class="portal-list-card">
                <div class="card-header"><h2>Fee items &amp; installments</h2><span class="portal-section-count">Oldest due first</span></div>
                <x-admin.table class="portal-mobile-table">
                    <thead><tr><th>Fee plan / installment</th><th>Due date</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($assignments->sortBy(fn ($item) => $item->component?->due_date?->timestamp ?? PHP_INT_MAX) as $assignment)
                            @php
                                $balance = $assignment->balanceDue();
                            @endphp
                            <tr><td data-label="Fee item">{{ $assignment->component?->name ?? $assignment->component?->structure?->name ?? 'Fee item' }}</td><td data-label="Due date">{{ $assignment->component?->due_date?->format('d M Y') ?? '—' }}</td><td data-label="Amount">₹{{ number_format($assignment->totalDue(), 2) }}</td><td data-label="Paid">₹{{ number_format($assignment->totalDue() - $balance, 2) }}</td><td data-label="Balance">₹{{ number_format($balance, 2) }}</td><td data-label="Status">{{ $balance <= 0 ? 'Paid' : ($assignment->component?->due_date?->isPast() ? 'Overdue' : 'Due') }}</td></tr>
                        @empty
                            <tr><td colspan="6"><x-admin.empty-state title="No fee items assigned" description="Assigned fees and installment schedules will appear here." /></td></tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
            </x-admin.card>
            @break

        @case('payments')
            <x-admin.card padding="false" class="portal-list-card">
                <div class="card-header"><h2>Payment history</h2><span class="portal-section-count">{{ $payments->count() }} recent records</span></div>
                @forelse ($payments as $payment)
                    <article class="portal-record-row">
                        <span class="portal-record-icon portal-record-icon--green"><i class="fa-solid fa-arrow-down" aria-hidden="true"></i></span>
                        <div class="portal-record-main"><strong>{{ $payment->receipt?->receipt_number ?? $payment->transaction_reference ?? 'Payment received' }}</strong><span>{{ $payment->paid_at?->format('d M Y, h:i A') }} · {{ ucfirst(str_replace('_', ' ', $payment->payment_mode)) }}</span></div>
                        <strong class="portal-record-value">₹{{ number_format($payment->amount, 2) }}</strong>
                        @if ($payment->receipt)<a class="portal-record-action" href="{{ route('portal.receipt', $payment) }}" aria-label="Open receipt {{ $payment->receipt->receipt_number }}"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i></a>@endif
                    </article>
                @empty
                    <div class="portal-empty-inline"><span class="portal-empty-icon"><i class="fa-regular fa-credit-card" aria-hidden="true"></i></span><div><strong>No payments yet</strong><p>Payment receipts will appear here after a payment is recorded.</p></div></div>
                @endforelse
                @if ($payments->hasPages())<div class="p-3">{{ $payments->links() }}</div>@endif
            </x-admin.card>
            @break

        @case('notices')
            <x-admin.card class="portal-detail-card">
                <span class="portal-section-kicker">SCHOOL UPDATES</span>
                <h2>Notices for {{ $student->first_name }}</h2>
                @forelse ($notices as $notice)
                    <article class="portal-notice-row"><span class="portal-notice-dot" aria-hidden="true"><i class="fa-regular fa-message"></i></span><div><h3>{{ $notice->title }}</h3><p>{{ $notice->body }}</p><small>{{ $notice->published_at?->format('d M Y') }}</small></div></article>
                @empty
                    <div class="portal-empty-inline"><span class="portal-empty-icon"><i class="fa-regular fa-message" aria-hidden="true"></i></span><div><strong>No notices</strong><p>Announcements for this student will appear here.</p></div></div>
                @endforelse
                @if ($notices->hasPages())<div class="p-3">{{ $notices->links() }}</div>@endif
            </x-admin.card>
            @break
    @endswitch
@endsection
