@extends('layouts.app')

@section('content')
    @php
        $periodLabels = [
            'day' => 'Today',
            'week' => 'This week',
            'month' => 'This month',
            'quarter' => 'This quarter',
            'year' => 'This year',
            'financial_year' => 'Current financial year',
        ];
        $periodLabel = $d['period']
            ? ($periodLabels[$d['period']] ?? 'Selected period')
            : 'Custom date range';
        $totalByMode = max(0.01, (float) $modeTotals->sum());
    @endphp

    <x-admin.page-header
        title="Financial report"
        description="{{ $branchName }} · {{ $periodLabel }}{{ !empty($d['from_date']) && !empty($d['to_date']) ? ' · '.\Illuminate\Support\Carbon::parse($d['from_date'])->format('d M Y').' – '.\Illuminate\Support\Carbon::parse($d['to_date'])->format('d M Y') : '' }}."
    >
        <x-slot name="actions">
            <button
                id="financial-filter-toggle"
                class="btn btn-outline-secondary"
                type="button"
                aria-controls="financial-filters"
                aria-expanded="false"
            >
                <i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>
                Filters
            </button>
            @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
                <a class="btn btn-outline-primary" href="{{ route('reports.export', ['type' => 'financial', 'format' => $format] + request()->except('page')) }}">
                    {{ $label }}
                </a>
            @endforeach
            <a class="btn btn-outline-secondary" href="{{ route('reports.previous-dues') }}">Previous-year dues</a>
            <button class="btn btn-outline-secondary" type="button" onclick="window.print()">Print</button>
        </x-slot>
    </x-admin.page-header>

    <section id="financial-filters" class="admin-card p-3 p-md-4 mb-4" hidden aria-label="Financial report filters">
        <form method="get" action="{{ route('reports.financial') }}" class="form-row align-items-end">
            <div class="col-lg-3 col-md-6 form-group">
                <label for="branch_id">Branch</label>
                <select id="branch_id" name="branch_id" class="form-control">
                    <option value="">All permitted branches</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}" @selected($branchId == $branch->id)>{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6 form-group">
                <label for="period">Reporting period</label>
                <select id="period" name="period" class="form-control">
                    <option value="">Custom range</option>
                    @foreach ($periodLabels as $key => $label)
                        <option value="{{ $key }}" @selected(($d['period'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-lg-2 col-md-6 form-group">
                <label for="from_date">From date</label>
                <input
                    id="from_date"
                    type="text"
                    name="from_date"
                    class="form-control js-datepicker"
                    value="{{ $d['from_date'] ?? '' }}"
                    autocomplete="off"
                    placeholder="DD/MM/YYYY"
                >
            </div>
            <div class="col-lg-2 col-md-6 form-group">
                <label for="to_date">To date</label>
                <input
                    id="to_date"
                    type="text"
                    name="to_date"
                    class="form-control js-datepicker"
                    value="{{ $d['to_date'] ?? '' }}"
                    autocomplete="off"
                    placeholder="DD/MM/YYYY"
                >
            </div>
            <div class="col-lg-3 col-md-6 form-group">
                <label for="payment_mode">Payment method</label>
                <select id="payment_mode" name="payment_mode" class="form-control">
                    <option value="">All methods</option>
                    @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank_transfer' => 'Bank transfer', 'online' => 'Online'] as $key => $label)
                        <option value="{{ $key }}" @selected(($d['payment_mode'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 d-flex flex-wrap align-items-center gap-2">
                <button class="btn btn-primary" type="submit">Apply filters</button>
                <a class="btn btn-outline-secondary" href="{{ route('reports.financial') }}">Reset</a>
                <span class="small text-muted">Refunds are grouped by the original payment. Split-tender refunds are not divided by method.</span>
            </div>
        </form>
    </section>

    <section class="row financial-report-summary mb-4" aria-label="Financial summary">
        <div class="col-xl-3 col-md-6 mb-3">
            <x-admin.stat-card label="Gross collections" :value="'₹'.number_format($grossCollected, 2)" :detail="number_format($paymentCount).' completed payments in this period'" tone="primary" icon="fa-money-bill-wave" />
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <x-admin.stat-card label="Refunds issued" :value="'₹'.number_format($refunded, 2)" detail="Refunds processed in this period" tone="danger" icon="fa-arrow-rotate-left" />
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <x-admin.stat-card label="Net collections" :value="'₹'.number_format($collected, 2)" detail="Gross collections less refunds" tone="success" icon="fa-chart-line" />
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <x-admin.stat-card label="Approved expenses" :value="'₹'.number_format($expenses, 2)" detail="Approved spending in this period" tone="warning" icon="fa-receipt" />
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <x-admin.stat-card label="Net after expenses" :value="'₹'.number_format($netAfterExpenses, 2)" detail="Net collections less approved expenses" tone="info" icon="fa-scale-balanced" />
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <x-admin.stat-card label="Fees outstanding" :value="'₹'.number_format($pendingFees, 2)" detail="Current unpaid student balances · all periods" tone="slate" icon="fa-file-invoice-dollar" />
        </div>
        <div class="col-xl-4 col-md-6 mb-3">
            <x-admin.stat-card label="Unallocated advances" :value="'₹'.number_format($advanceBalance, 2)" detail="Payments not yet applied to fee items · all periods" tone="purple" icon="fa-wallet" />
        </div>
    </section>

    <div class="row mb-4">
        <div class="col-xl-4 mb-3">
            <x-admin.card class="h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h5 mb-1">Collections by payment method</h2>
                        <p class="small text-muted mb-0">Tender amounts for the selected period.</p>
                    </div>
                </div>
                @forelse ($modeTotals->sortDesc() as $mode => $total)
                    @php($share = min(100, ((float) $total / $totalByMode) * 100))
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>{{ ucfirst(str_replace('_', ' ', $mode)) }}</span>
                            <strong>₹{{ number_format((float) $total, 2) }}</strong>
                        </div>
                        <div class="progress" style="height: 7px">
                            <div class="progress-bar" role="progressbar" style="width: {{ $share }}%" aria-valuenow="{{ round($share) }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No collections were recorded in this period.</p>
                @endforelse
            </x-admin.card>
        </div>

        <div class="col-xl-4 mb-3">
            <x-admin.card class="h-100">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h2 class="h5 mb-1">Collections by staff</h2>
                        <p class="small text-muted mb-0">Cashier totals use the selected method when filtered.</p>
                    </div>
                </div>
                @forelse ($staffTotals->sortByDesc('total') as $staff)
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <span>{{ $staff['name'] }}</span>
                        <strong>₹{{ number_format((float) $staff['total'], 2) }}</strong>
                    </div>
                @empty
                    <p class="text-muted mb-0">No staff collections were recorded in this period.</p>
                @endforelse
            </x-admin.card>
        </div>

        <div class="col-xl-4 mb-3">
            <x-admin.card class="h-100">
                <div class="mb-3">
                    <h2 class="h5 mb-1">Approved expenses by category</h2>
                    <p class="small text-muted mb-0">See where spending is concentrated for this period.</p>
                </div>
                @forelse ($expenseBreakdown as $expenseGroup)
                    @php($share = $expenses > 0 ? min(100, ($expenseGroup['total'] / $expenses) * 100) : 0)
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>{{ $expenseGroup['category'] }}</span>
                            <strong>₹{{ number_format($expenseGroup['total'], 2) }}</strong>
                        </div>
                        <div class="progress" style="height: 7px">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: {{ $share }}%" aria-valuenow="{{ round($share) }}" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">No approved expenses were recorded in this period.</p>
                @endforelse
            </x-admin.card>
        </div>
    </div>

    <x-admin.card padding="false">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h2 class="h5 mb-1">Completed payments</h2>
                <p class="small text-muted mb-0">
                    @if (!empty($d['payment_mode']))
                        Showing the {{ ucfirst(str_replace('_', ' ', $d['payment_mode'])) }} portion of split-tender payments.
                    @else
                        Amounts show the full payment collected.
                    @endif
                </p>
            </div>
            <a href="{{ route('reports.expenses', array_filter(['from_date' => $d['from_date'] ?? null, 'to_date' => $d['to_date'] ?? null, 'branch_id' => $branchId])) }}" class="btn btn-sm btn-outline-secondary">View expense details</a>
        </div>
        <x-admin.table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Student</th>
                    <th>Receipt / reference</th>
                    <th>Payment method</th>
                    <th class="text-right">Amount</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    <tr>
                        <td class="text-nowrap">{{ $payment->paid_at?->format('d M Y, h:i A') ?? '—' }}</td>
                        <td>
                            <strong>{{ $payment->student?->first_name }} {{ $payment->student?->last_name }}</strong>
                            <div class="small text-muted">{{ $payment->student?->student_id ?? '—' }}</div>
                        </td>
                        <td>{{ $payment->receipt?->receipt_number ?? $payment->transaction_reference }}</td>
                        <td>
                            @if ($payment->paymentTenders->isNotEmpty())
                                @foreach ($payment->paymentTenders as $tender)
                                    @if (empty($d['payment_mode']) || $d['payment_mode'] === $tender->payment_mode)
                                        <div>{{ ucfirst(str_replace('_', ' ', $tender->payment_mode)) }} <span class="text-muted">₹{{ number_format((float) $tender->amount, 2) }}</span></div>
                                    @endif
                                @endforeach
                            @else
                                {{ ucfirst(str_replace('_', ' ', $payment->payment_mode)) }}
                            @endif
                        </td>
                        <td class="text-right font-weight-bold text-nowrap">₹{{ number_format((float) $payment->report_amount, 2) }}</td>
                        <td class="text-right">
                            <a class="btn btn-sm btn-outline-primary" href="{{ route('payments.receipt', $payment) }}">Receipt</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-admin.empty-state
                                title="No completed payments found"
                                description="Try another reporting period or clear the payment method filter."
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>
        <div class="p-3">{{ $payments->links() }}</div>
    </x-admin.card>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const toggle = document.getElementById('financial-filter-toggle');
            const filters = document.getElementById('financial-filters');
            const period = document.getElementById('period');
            const fromDate = document.getElementById('from_date');
            const toDate = document.getElementById('to_date');

            toggle.addEventListener('click', function () {
                const isExpanded = toggle.getAttribute('aria-expanded') === 'true';
                toggle.setAttribute('aria-expanded', String(!isExpanded));
                filters.hidden = isExpanded;
            });

            period.addEventListener('change', function () {
                fromDate.value = '';
                toDate.value = '';
            });

            [fromDate, toDate].forEach(function (input) {
                input.addEventListener('change', function () {
                    if (input.value) period.value = '';
                });
            });
        });
    </script>
@endsection
