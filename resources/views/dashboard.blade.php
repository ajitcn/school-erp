@extends('layouts.app')

@section('content')
    @php
        $attendanceStatuses = [
            'present' => ['label' => 'Present', 'tone' => 'success'],
            'absent' => ['label' => 'Absent', 'tone' => 'danger'],
            'late' => ['label' => 'Late', 'tone' => 'warning'],
            'half_day' => ['label' => 'Half day', 'tone' => 'info'],
            'leave' => ['label' => 'Leave', 'tone' => 'slate'],
        ];

        $studentStatuses = [
            'active' => ['label' => 'Active', 'tone' => 'success'],
            'inactive' => ['label' => 'Inactive', 'tone' => 'slate'],
            'graduated' => ['label' => 'Graduated', 'tone' => 'info'],
            'transferred' => ['label' => 'Transferred', 'tone' => 'warning'],
            'withdrawn' => ['label' => 'Withdrawn', 'tone' => 'danger'],
        ];

        $totalStudents = (int) $studentStatusCounts->sum();
        $presentCount = (int) $attendanceCounts->get('present', 0);
        $enrollmentTrendMaximum = max(1, (int) $enrollmentTrend->max('count'));
        $hasAttendanceTrend = $attendanceTrend->contains(fn ($day) => $day['marked'] > 0);
        $weeklyCollections = (float) $collectionTrend->sum('collections');
        $weeklyExpenses = (float) $collectionTrend->sum('expenses');
        $trendMaximum = max(
            1,
            (float) $collectionTrend->max(fn ($day) => max($day['collections'], $day['expenses']))
        );
        $dashboardTenant = app(\App\Services\TenantContext::class)->get();
        $subscriptionDaysRemaining = $dashboardTenant?->subscriptionDaysRemaining();
        $subscriptionExpiryDate = $dashboardTenant?->currentSubscription()?->end_date ?? $dashboardTenant?->trial_ends_at;
        $subscriptionHasExpired = $subscriptionDaysRemaining !== null && $subscriptionDaysRemaining <= 0;
    @endphp

    <div class="dashboard-page">
        <header class="dashboard-welcome">
            <div>
                <p class="dashboard-eyebrow">School overview</p>
                <h1>Good morning, {{ auth()->user()->name }}!</h1>
                <p class="dashboard-intro">A clear view of today’s activity and the work that needs attention.</p>
            </div>

            <div class="dashboard-welcome-actions">
                <div class="dashboard-date">
                    <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                    <span>{{ $dashboardDate->format('l, d F Y') }}</span>
                </div>

                @if ($canViewStudents)
                    <a href="{{ route('students.index') }}" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-user-graduate mr-2" aria-hidden="true"></i>
                        View students
                    </a>
                @endif

                @if (auth()->user()->hasPermission('students.create') && Route::has('students.create'))
                    <a href="{{ route('students.create') }}" class="btn btn-primary">
                        <i class="fa-solid fa-user-plus mr-2" aria-hidden="true"></i>
                        Add student
                    </a>
                @endif
            </div>
        </header>

        @if ($dashboardTenant && $subscriptionDaysRemaining !== null && $subscriptionDaysRemaining <= 30)
            <div class="alert {{ $subscriptionHasExpired || $subscriptionDaysRemaining <= 7 ? 'alert-danger' : 'alert-warning' }} d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4" role="status">
                <span>
                    <i class="fa-solid fa-clock mr-2" aria-hidden="true"></i>
                    @if ($subscriptionHasExpired)
                        Subscription expired{{ $subscriptionExpiryDate ? ' on '.$subscriptionExpiryDate->format('d M Y') : '' }}.
                    @else
                        Subscription expires in {{ $subscriptionDaysRemaining }} day(s){{ $subscriptionExpiryDate ? ' on '.$subscriptionExpiryDate->format('d M Y') : '' }}.
                    @endif
                </span>
                <a href="{{ route('billing.index') }}" class="btn btn-sm btn-outline-dark">Review subscription</a>
            </div>
        @endif

        @if ($canReviewApprovals && $pendingApprovals->isNotEmpty())
            <section class="card dashboard-panel mb-4" aria-labelledby="pending-approvals-title">
                <div class="card-header dashboard-panel-header">
                    <div>
                        <p class="dashboard-panel-eyebrow">Needs review</p>
                        <h2 id="pending-approvals-title">Recent pending approvals</h2>
                        <p class="dashboard-panel-description">Latest requests across attendance, expenses, refunds, payment corrections, and receipts.</p>
                    </div>
                    <a href="{{ route('approvals.index') }}" class="btn btn-sm btn-outline-primary">Open approval queue</a>
                </div>
                <div class="table-responsive">
                    <table class="table dashboard-table mb-0">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Reference</th>
                                <th>Request</th>
                                <th>Amount</th>
                                <th>Requested by</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pendingApprovals as $approval)
                                <tr>
                                    <td><x-admin.badge tone="warning">{{ $approval->kind }}</x-admin.badge></td>
                                    <td><strong>{{ $approval->reference }}</strong></td>
                                    <td>{{ $approval->summary }}</td>
                                    <td>{{ is_numeric($approval->amount) ? '₹'.number_format((float) $approval->amount, 2) : '—' }}</td>
                                    <td>{{ $approval->requester_name ?? '—' }}</td>
                                    <td>{{ \Illuminate\Support\Carbon::parse($approval->created_at)->format('d M, h:i A') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="dashboard-metrics" aria-label="Key school metrics">
            @if ($canViewStudents)
                <x-admin.stat-card
                    label="Active students"
                    :value="number_format($activeStudents)"
                    :detail="number_format($studentsAddedThisMonth).' added this month'"
                    tone="primary"
                    icon="fa-user-graduate"
                />
            @endif

            @if ($canViewFinance)
                <x-admin.stat-card
                    label="Overdue amount"
                    :value="'₹'.number_format($overdueAmount, 2)"
                    detail="Fees past their due date"
                    tone="danger"
                    icon="fa-triangle-exclamation"
                />
            @endif

            @if ($canViewFinance)
                <x-admin.stat-card
                    label="Collected today"
                    :value="'₹'.number_format($paymentsTodayAmount, 2)"
                    :detail="number_format($paymentTodayCount).' completed payments · ₹'.number_format($paymentsMonthAmount, 2).' this month'"
                    tone="info"
                    icon="fa-indian-rupee-sign"
                />
            @endif

            @if ($canViewExpenses)
                <x-admin.stat-card
                    label="Expenses today"
                    :value="'₹'.number_format($expensesTodayAmount, 2)"
                    :detail="number_format($expensesTodayCount).' recorded · ₹'.number_format($expensesMonthAmount, 2).' this month'"
                    tone="warning"
                    icon="fa-wallet"
                />
            @endif
        </section>

        <div class="mb-4">
            <button class="btn btn-sm btn-outline-primary" type="button" id="copy-portal-login">
                <i class="fa-solid fa-link mr-1" aria-hidden="true"></i> Copy student and parent portal link
            </button>
            <small id="portal-copy-status" class="text-muted ml-2" role="status" aria-live="polite"></small>
            <input id="portal-login-link" class="form-control form-control-sm mt-2" type="text" value="{{ route('portal.password.login') }}" readonly aria-label="Student and parent portal login link" hidden>
        </div>

        <div class="dashboard-primary-grid">
            @if ($canViewFinance || $canViewExpenses)
                <section class="card dashboard-panel dashboard-finance-panel">
                    <div class="card-header dashboard-panel-header">
                        <div>
                            <p class="dashboard-panel-eyebrow">Financial activity</p>
                            <h2>Collections and expenses</h2>
                            <p class="dashboard-panel-description">Daily totals for the last seven days</p>
                        </div>

                        @if ($canViewReports && Route::has('reports.financial'))
                            <a href="{{ route('reports.financial') }}" class="btn btn-sm btn-outline-primary">
                                Financial report
                            </a>
                        @endif
                    </div>

                    <div class="card-body">
                        <div class="dashboard-chart-summary">
                            @if ($canViewFinance)
                                <div>
                                    <span class="dashboard-legend-dot dashboard-legend-collections"></span>
                                    <span>Collections</span>
                                    <strong>₹{{ number_format($weeklyCollections, 2) }}</strong>
                                </div>
                            @endif

                            @if ($canViewExpenses)
                                <div>
                                    <span class="dashboard-legend-dot dashboard-legend-expenses"></span>
                                    <span>Expenses</span>
                                    <strong>₹{{ number_format($weeklyExpenses, 2) }}</strong>
                                </div>
                            @endif

                            @if ($canViewExpenses && $canManageExpenses)
                                <div class="dashboard-pending-summary">
                                    <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                    <span>{{ number_format($pendingExpenseCount) }} pending</span>
                                </div>
                            @endif
                        </div>

                        @if ($weeklyCollections > 0 || $weeklyExpenses > 0)
                            <div class="dashboard-chart" role="img" aria-label="Daily collections and expenses over the last seven days">
                                @foreach ($collectionTrend as $day)
                                    @php
                                        $collectionHeight = $day['collections'] > 0
                                            ? max(4, ($day['collections'] / $trendMaximum) * 100)
                                            : 0;
                                        $expenseHeight = $day['expenses'] > 0
                                            ? max(4, ($day['expenses'] / $trendMaximum) * 100)
                                            : 0;
                                    @endphp

                                    <div class="dashboard-chart-day">
                                        <div class="dashboard-chart-bars">
                                            @if ($canViewFinance && $day['collections'] > 0)
                                                <span
                                                    class="dashboard-chart-bar dashboard-chart-bar-collections"
                                                    style="height: {{ $collectionHeight }}%"
                                                    title="{{ $day['date'] }} collections: ₹{{ number_format($day['collections'], 2) }}"
                                                ></span>
                                            @endif

                                            @if ($canViewExpenses && $day['expenses'] > 0)
                                                <span
                                                    class="dashboard-chart-bar dashboard-chart-bar-expenses"
                                                    style="height: {{ $expenseHeight }}%"
                                                    title="{{ $day['date'] }} expenses: ₹{{ number_format($day['expenses'], 2) }}"
                                                ></span>
                                            @endif
                                        </div>
                                        <span class="dashboard-chart-date">{{ $day['label'] }}</span>
                                        <span class="dashboard-chart-day-date">{{ $day['date'] }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="dashboard-chart-empty">
                                <span class="dashboard-empty-icon">
                                    <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                                </span>
                                <strong>No financial activity this week</strong>
                                <span>Collections and expenses will appear here when recorded.</span>
                            </div>
                        @endif
                    </div>
                </section>
            @endif

            @if ($canViewAttendance)
                <section class="card dashboard-panel dashboard-attendance-panel">
                    <div class="card-header dashboard-panel-header">
                        <div>
                            <p class="dashboard-panel-eyebrow">Today</p>
                            <h2>Attendance overview</h2>
                            <p class="dashboard-panel-description">
                                {{ number_format($attendanceMarkedCount) }} full-day records marked
                            </p>
                        </div>

                        @if (auth()->user()->hasPermission('attendance.mark') && Route::has('attendance.records'))
                            <a href="{{ route('attendance.records') }}" class="dashboard-icon-link" aria-label="Open attendance records" title="Attendance records">
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                            </a>
                        @elseif ($canViewReports && Route::has('reports.attendance'))
                            <a href="{{ route('reports.attendance') }}" class="dashboard-icon-link" aria-label="Open attendance report" title="Attendance report">
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>

                    <div class="card-body">
                        @if ($attendanceMarkedCount > 0)
                            <div class="dashboard-attendance-summary">
                                <div
                                    class="dashboard-attendance-ring"
                                    style="--attendance-rate: {{ $attendanceRate }}%"
                                    role="img"
                                    aria-label="{{ $attendanceRate }} percent of today's marked records are present"
                                >
                                    <div>
                                        <strong>{{ $attendanceRate }}%</strong>
                                        <span>present</span>
                                    </div>
                                </div>

                                <div class="dashboard-attendance-counts">
                                    @foreach ($attendanceStatuses as $status => $meta)
                                        @php
                                            $count = (int) $attendanceCounts->get($status, 0);
                                        @endphp
                                        <div class="dashboard-attendance-count">
                                            <span class="dashboard-status-dot dashboard-status-{{ $status }}"></span>
                                            <span>{{ $meta['label'] }}</span>
                                            <strong>{{ number_format($count) }}</strong>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="dashboard-chart-empty dashboard-attendance-empty">
                                <span class="dashboard-empty-icon">
                                    <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                                </span>
                                <strong>No attendance marked today</strong>
                                <span>The overview will update as attendance is submitted.</span>
                            </div>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        @if ($canViewStudents || $canViewAttendance)
            <div class="dashboard-insights-grid">
                @if ($canViewStudents)
                    <section class="card dashboard-panel dashboard-insight-panel" aria-labelledby="enrollment-trend-title">
                        <div class="card-header dashboard-panel-header">
                            <div>
                                <p class="dashboard-panel-eyebrow">Admissions</p>
                                <h2 id="enrollment-trend-title">Enrollment trend</h2>
                                <p class="dashboard-panel-description">New student records over the last six months</p>
                            </div>
                            <span class="dashboard-insight-total">
                                {{ number_format($enrollmentTrend->sum('count')) }}
                                <small>new students</small>
                            </span>
                        </div>

                        <div class="card-body">
                            @if ($enrollmentTrend->sum('count') > 0)
                                <div class="dashboard-insight-chart dashboard-insight-chart-six" role="img" aria-label="New student records by month for the last six months">
                                    @foreach ($enrollmentTrend as $month)
                                        @php
                                            $barHeight = $month['count'] > 0
                                                ? max(4, ($month['count'] / $enrollmentTrendMaximum) * 100)
                                                : 0;
                                        @endphp
                                        <div class="dashboard-insight-column" title="{{ $month['date'] }}: {{ number_format($month['count']) }} new students">
                                            <strong>{{ number_format($month['count']) }}</strong>
                                            <div class="dashboard-insight-track">
                                                <span class="dashboard-insight-bar dashboard-insight-bar-enrollment" style="height: {{ $barHeight }}%"></span>
                                            </div>
                                            <span class="dashboard-insight-label">{{ $month['label'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="dashboard-insight-empty">Enrollment activity will appear here when student records are added.</div>
                            @endif
                        </div>
                    </section>
                @endif

                @if ($canViewAttendance)
                    <section class="card dashboard-panel dashboard-insight-panel" aria-labelledby="attendance-trend-title">
                        <div class="card-header dashboard-panel-header">
                            <div>
                                <p class="dashboard-panel-eyebrow">Daily attendance</p>
                                <h2 id="attendance-trend-title">Present rate</h2>
                                <p class="dashboard-panel-description">Present students as a share of full-day records</p>
                            </div>
                            @if ($hasAttendanceTrend)
                                <span class="dashboard-insight-total">
                                    {{ (int) round($attendanceTrend->whereNotNull('rate')->avg('rate')) }}%
                                    <small>7-day average</small>
                                </span>
                            @endif
                        </div>

                        <div class="card-body">
                            @if ($hasAttendanceTrend)
                                <div class="dashboard-insight-chart" role="img" aria-label="Daily student attendance present rate for the last seven days">
                                    @foreach ($attendanceTrend as $day)
                                        <div class="dashboard-insight-column" title="{{ $day['date'] }}: {{ $day['rate'] === null ? 'No records' : $day['rate'].'% present ('.$day['present'].' of '.$day['marked'].')' }}">
                                            <strong>{{ $day['rate'] === null ? '—' : $day['rate'].'%' }}</strong>
                                            <div class="dashboard-insight-track">
                                                @if ($day['rate'] !== null)
                                                    <span class="dashboard-insight-bar dashboard-insight-bar-attendance" style="height: {{ max(4, $day['rate']) }}%"></span>
                                                @endif
                                            </div>
                                            <span class="dashboard-insight-label">{{ $day['label'] }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="dashboard-insight-empty">Attendance trends will appear after full-day attendance is recorded.</div>
                            @endif
                        </div>
                    </section>
                @endif
            </div>
        @endif

        <div class="dashboard-secondary-grid">
            @if ($canViewStudents)
                <section class="card dashboard-panel dashboard-status-panel">
                    <div class="card-header dashboard-panel-header">
                        <div>
                            <p class="dashboard-panel-eyebrow">Enrollment</p>
                            <h2>Student status</h2>
                        </div>
                        <span class="dashboard-panel-total">{{ number_format($totalStudents) }} total</span>
                    </div>

                    <div class="card-body dashboard-status-list">
                        @forelse ($studentStatuses as $status => $meta)
                            @php
                                $count = (int) $studentStatusCounts->get($status, 0);
                                $percentage = $totalStudents > 0 ? ($count / $totalStudents) * 100 : 0;
                            @endphp

                            <div class="dashboard-status-row">
                                <div class="dashboard-status-row-label">
                                    <span>{{ $meta['label'] }}</span>
                                    <strong>{{ number_format($count) }}</strong>
                                </div>
                                <div class="dashboard-status-track">
                                    <span class="dashboard-status-fill dashboard-status-fill-{{ $status }}" style="width: {{ $percentage }}%"></span>
                                </div>
                            </div>
                        @empty
                            <div class="dashboard-inline-empty">No student records are available.</div>
                        @endforelse
                    </div>
                </section>
            @endif

            @if ($canManageSettings)
                <section class="card dashboard-panel dashboard-snapshot-panel">
                    <div class="card-header dashboard-panel-header">
                        <div>
                            <p class="dashboard-panel-eyebrow">School setup</p>
                            <h2>Institution snapshot</h2>
                        </div>

                        @if (Route::has('setup.index'))
                            <a href="{{ route('setup.index') }}" class="dashboard-icon-link" aria-label="Open school setup" title="School setup">
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>

                    <div class="card-body dashboard-snapshot-stats">
                        <div>
                            <span class="dashboard-snapshot-icon dashboard-snapshot-icon-blue">
                                <i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i>
                            </span>
                            <span><small>Active teachers</small><strong>{{ number_format($teacherCount) }}</strong></span>
                        </div>
                        <div>
                            <span class="dashboard-snapshot-icon dashboard-snapshot-icon-green">
                                <i class="fa-solid fa-code-branch" aria-hidden="true"></i>
                            </span>
                            <span><small>Active branches</small><strong>{{ number_format($activeBranchCount) }}</strong></span>
                        </div>
                    </div>
                </section>
            @endif

            @if ($canManageExpenses)
                <section class="card dashboard-panel dashboard-pending-panel">
                    <div class="card-header dashboard-panel-header">
                        <div>
                            <p class="dashboard-panel-eyebrow">Expense tracking</p>
                            <h2>Pending expenses</h2>
                        </div>
                        @if (Route::has('expenses.index'))
                            <a href="{{ route('expenses.index') }}" class="dashboard-icon-link" aria-label="View expenses" title="View expenses">
                                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                            </a>
                        @endif
                    </div>

                    <div class="card-body dashboard-pending-summary-body">
                        <div class="dashboard-pending-total">
                            <span class="dashboard-pending-icon" aria-hidden="true">
                                <i class="fa-regular fa-clock"></i>
                            </span>
                            <strong>{{ number_format($pendingExpenseCount) }}</strong>
                            <span>awaiting review</span>
                        </div>
                        <b class="dashboard-pending-amount">₹{{ number_format($pendingExpenseAmount, 2) }}</b>

                        <ul class="dashboard-pending-list">
                            @forelse ($pendingExpenses as $expense)
                                <li>
                                    <span class="dashboard-pending-item-icon" aria-hidden="true">
                                        <i class="fa-solid fa-receipt"></i>
                                    </span>
                                    <span class="dashboard-pending-item-copy">
                                        <strong>{{ $expense->description }}</strong>
                                        <small>
                                            {{ $expense->category?->name ?? 'Uncategorized' }}
                                            · {{ $expense->expense_date?->format('d M Y') ?? '—' }}
                                        </small>
                                    </span>
                                    <strong class="dashboard-pending-item-amount">
                                        ₹{{ number_format((float) $expense->amount, 2) }}
                                    </strong>
                                </li>
                            @empty
                                <li class="dashboard-pending-empty">No expenses are waiting for review.</li>
                            @endforelse
                        </ul>
                    </div>
                </section>
            @endif
        </div>

        <div class="dashboard-lists-grid">
            @if ($canViewStudents)
                <section class="card dashboard-panel dashboard-list-panel">
                    <div class="card-header dashboard-panel-header">
                        <div>
                            <p class="dashboard-panel-eyebrow">Latest records</p>
                            <h2>Recent students</h2>
                        </div>
                        @if (Route::has('students.index'))
                            <a href="{{ route('students.index') }}" class="dashboard-panel-link">View all</a>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table dashboard-table mb-0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Status</th>
                                    <th>Branch</th>
                                    <th>Added</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentStudents as $student)
                                    <tr>
                                        <td>
                                            <strong class="dashboard-primary-cell">{{ $student->first_name }} {{ $student->last_name }}</strong>
                                            <small>{{ $student->student_id }}</small>
                                        </td>
                                        <td>
                                            @php
                                                $statusMeta = $studentStatuses[$student->status] ?? ['tone' => 'slate'];
                                            @endphp
                                            <x-admin.badge :tone="$statusMeta['tone']">{{ ucfirst($student->status) }}</x-admin.badge>
                                        </td>
                                        <td>{{ $student->branch?->name ?? '—' }}</td>
                                        <td>{{ $student->created_at?->format('d M Y') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="dashboard-table-empty">No students found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($canViewFinance)
                <section class="card dashboard-panel dashboard-list-panel">
                    <div class="card-header dashboard-panel-header">
                        <div>
                            <p class="dashboard-panel-eyebrow">Fee collection</p>
                            <h2>Recent payments</h2>
                        </div>
                        @if ($canViewReports && Route::has('reports.financial'))
                            <a href="{{ route('reports.financial') }}" class="dashboard-panel-link">View report</a>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table dashboard-table mb-0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Method</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentPayments as $payment)
                                    <tr>
                                        <td>
                                            <strong class="dashboard-primary-cell">
                                                {{ $payment->student?->first_name ?? 'Unknown' }} {{ $payment->student?->last_name }}
                                            </strong>
                                            <small>{{ $payment->transaction_reference }}</small>
                                        </td>
                                        <td>{{ ucfirst(str_replace('_', ' ', $payment->payment_mode)) }}</td>
                                        <td class="dashboard-amount">₹{{ number_format((float) $payment->amount, 2) }}</td>
                                        <td>{{ $payment->paid_at?->format('d M, h:i A') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="dashboard-table-empty">No completed payments found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($canViewExpenses)
                <section class="card dashboard-panel dashboard-list-panel">
                    <div class="card-header dashboard-panel-header">
                        <div>
                            <p class="dashboard-panel-eyebrow">Expense tracking</p>
                            <h2>Recent expenses</h2>
                        </div>
                        @if ($canManageExpenses && Route::has('expenses.index'))
                            <a href="{{ route('expenses.index') }}" class="dashboard-panel-link">View all</a>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table dashboard-table mb-0">
                            <thead>
                                <tr>
                                    <th>Expense</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentExpenses as $expense)
                                    <tr>
                                        <td>
                                            <strong class="dashboard-primary-cell">{{ $expense->description }}</strong>
                                            <small>{{ $expense->expense_date?->format('d M Y') ?? '—' }}</small>
                                        </td>
                                        <td>{{ $expense->category?->name ?? '—' }}</td>
                                        <td>
                                            @php
                                                $expenseTone = match ($expense->status) {
                                                    'approved' => 'success',
                                                    'rejected' => 'danger',
                                                    default => 'warning',
                                                };
                                            @endphp
                                            <x-admin.badge :tone="$expenseTone">{{ ucfirst($expense->status) }}</x-admin.badge>
                                        </td>
                                        <td class="dashboard-amount">₹{{ number_format((float) $expense->amount, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="dashboard-table-empty">No expense records found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if ($canViewAttendance && $canViewStudents)
                <section class="card dashboard-panel dashboard-list-panel">
                    <div class="card-header dashboard-panel-header">
                        <div>
                            <p class="dashboard-panel-eyebrow">Follow-up</p>
                            <h2>Students absent today</h2>
                        </div>
                        @if (auth()->user()->hasPermission('attendance.mark') && Route::has('attendance.records'))
                            <a href="{{ route('attendance.records') }}" class="dashboard-panel-link">Attendance records</a>
                        @elseif ($canViewReports && Route::has('reports.attendance'))
                            <a href="{{ route('reports.attendance') }}" class="dashboard-panel-link">Attendance report</a>
                        @endif
                    </div>

                    <div class="table-responsive">
                        <table class="table dashboard-table mb-0">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Branch</th>
                                    <th>Marked at</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentAbsences as $attendanceRecord)
                                    <tr>
                                        <td>
                                            <strong class="dashboard-primary-cell">
                                                {{ $attendanceRecord->student?->first_name }} {{ $attendanceRecord->student?->last_name }}
                                            </strong>
                                            <small>{{ $attendanceRecord->student?->student_id }}</small>
                                        </td>
                                        <td>{{ $attendanceRecord->student?->branch?->name ?? '—' }}</td>
                                        <td>{{ $attendanceRecord->created_at?->format('h:i A') ?? '—' }}</td>
                                        <td><x-admin.badge tone="danger">Absent</x-admin.badge></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="dashboard-table-empty">No students are marked absent today.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>

        @if (count($quickActions) > 0)
            <section class="dashboard-quick-actions">
                <div class="dashboard-quick-actions-heading">
                    <div>
                        <p class="dashboard-panel-eyebrow">Shortcuts</p>
                        <h2>Quick actions</h2>
                    </div>
                </div>

                <div class="dashboard-actions-grid">
                    @foreach ($quickActions as $action)
                        <a href="{{ $action['href'] }}" class="dashboard-action-card">
                            <span class="dashboard-action-icon dashboard-action-icon-{{ $action['tone'] }}">
                                <i class="fa-solid {{ $action['icon'] }}" aria-hidden="true"></i>
                            </span>
                            <span class="dashboard-action-copy">
                                <strong>{{ $action['label'] }}</strong>
                                <small>{{ $action['description'] }}</small>
                            </span>
                            <i class="fa-solid fa-arrow-right dashboard-action-arrow" aria-hidden="true"></i>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
    <script>
        document.getElementById('copy-portal-login').addEventListener('click', async function () {
            const link = document.getElementById('portal-login-link');
            const status = document.getElementById('portal-copy-status');

            try {
                if (! navigator.clipboard || ! window.isSecureContext) {
                    throw new Error('Clipboard unavailable');
                }
                await navigator.clipboard.writeText(link.value);
                status.textContent = 'Portal login link copied.';
            } catch (error) {
                link.hidden = false;
                link.focus();
                link.select();
                status.textContent = 'Select and copy the link above.';
            }
        });
    </script>
@endsection
