<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $payment->receipt?->receipt_number ?? $payment->transaction_reference }}</title>
    <style>
        :root {
            color-scheme: light;
            font-family: Inter, "Segoe UI", Arial, sans-serif;
            color: #1f2937;
            background: #f2f5f9;
        }

        * { box-sizing: border-box; }
        body { margin: 0; padding: 36px 16px; }
        .page { max-width: 820px; margin: 0 auto; }
        .receipt, .management { background: #fff; border: 1px solid #e3e9f1; border-radius: 16px; box-shadow: 0 12px 34px rgba(31, 41, 55, .08); }
        .receipt { overflow: hidden; }
        .receipt-header { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; padding: 32px; border-bottom: 1px solid #e8edf4; }
        .institution { display: flex; align-items: center; gap: 16px; min-width: 0; }
        .logo { width: 64px; height: 64px; object-fit: contain; border: 1px solid #e8edf4; border-radius: 12px; padding: 5px; }
        .institution-name { margin: 0; font-size: 20px; font-weight: 700; }
        .institution-address { margin: 5px 0 0; color: #64748b; font-size: 13px; white-space: pre-line; }
        .receipt-mark { text-align: right; flex-shrink: 0; }
        .receipt-mark h1 { margin: 0; font-size: 24px; letter-spacing: -.03em; }
        .receipt-number { color: #64748b; font-size: 13px; margin-top: 6px; }
        .status { display: inline-flex; align-items: center; margin-top: 10px; border-radius: 999px; padding: 5px 10px; color: #166534; background: #dcfce7; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        .status-cancelled { color: #92400e; background: #fef3c7; }
        .receipt-body { padding: 28px 32px 32px; }
        .details { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 20px 32px; margin-bottom: 30px; }
        .detail-label { display: block; margin-bottom: 5px; color: #64748b; font-size: 12px; font-weight: 600; }
        .detail-value { overflow-wrap: anywhere; font-size: 14px; font-weight: 600; }
        .section-title { margin: 0 0 12px; font-size: 15px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 10px; border-bottom: 1px solid #e8edf4; text-align: left; vertical-align: top; }
        th { color: #64748b; background: #f8fafc; font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; }
        td { font-size: 13px; }
        .amount { text-align: right; white-space: nowrap; }
        .muted { margin-top: 4px; color: #64748b; font-size: 12px; }
        .total-row td { border-bottom: 0; padding-top: 18px; font-size: 14px; font-weight: 700; }
        .total { color: #1d4ed8; font-size: 21px !important; }
        .tenders { display: grid; gap: 5px; }
        .tender { display: flex; justify-content: space-between; gap: 12px; }
        .receipt-footer { margin-top: 24px; padding-top: 16px; border-top: 1px dashed #d5deea; color: #64748b; font-size: 12px; text-align: center; }
        .toolbar { display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 16px; }
        .button { display: inline-flex; align-items: center; justify-content: center; min-height: 40px; padding: 9px 14px; border: 1px solid #cbd5e1; border-radius: 9px; background: #fff; color: #334155; cursor: pointer; font: inherit; font-size: 13px; font-weight: 600; text-decoration: none; }
        .button-primary { border-color: #2563eb; background: #2563eb; color: #fff; }
        .management { margin-top: 20px; padding: 24px; }
        .management h2 { margin: 0; font-size: 17px; }
        .management-intro { margin: 6px 0 20px; color: #64748b; font-size: 13px; }
        .action-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
        .action-card { padding: 16px; border: 1px solid #e3e9f1; border-radius: 12px; }
        .action-card label { display: block; margin-bottom: 7px; font-size: 12px; font-weight: 600; }
        .action-card input, .action-card textarea { width: 100%; margin-bottom: 9px; padding: 9px 10px; border: 1px solid #cbd5e1; border-radius: 8px; font: inherit; font-size: 13px; }
        .action-card textarea { resize: vertical; }
        .action-card .button { width: 100%; }
        .button-warning { border-color: #d97706; color: #b45309; }
        .button-danger { border-color: #dc2626; color: #b91c1c; }

        @media (max-width: 600px) {
            body { padding: 16px 10px; }
            .receipt-header, .receipt-body { padding: 22px 18px; }
            .receipt-header { flex-direction: column; }
            .receipt-mark { text-align: left; }
            .details { grid-template-columns: 1fr; gap: 14px; }
            .action-grid { grid-template-columns: 1fr; }
            th, td { padding: 10px 6px; }
        }

        @media print {
            :root { background: #fff; }
            body { padding: 0; }
            .page { max-width: none; }
            .receipt, .management { border: 0; border-radius: 0; box-shadow: none; }
            .d-print-none { display: none !important; }
            .receipt-header, .receipt-body { padding: 22px 26px; }
            tr, .details { break-inside: avoid; }
        }
    </style>
</head>
<body>
    <main class="page">
        <nav class="toolbar d-print-none" aria-label="Receipt actions">
            @if (auth()->user()->hasPermission('payments.collect'))
                <a class="button" href="{{ route('payments.history', $payment) }}">Adjustment history</a>
            @endif
            <a class="button" href="{{ url()->previous() }}">Back</a>
            <button class="button button-primary" type="button" onclick="window.print()">Print receipt</button>
        </nav>

        @if (session('success'))
            <div class="management d-print-none" role="status">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="management d-print-none" role="alert">
                <strong>We couldn’t submit the request.</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <article class="receipt" aria-labelledby="receipt-title">
            <header class="receipt-header">
                <div class="institution">
                    @if ($tenant?->logo)
                        <img class="logo" src="{{ asset('storage/'.$tenant->logo) }}" alt="{{ $tenant->institution_name }} logo">
                    @endif
                    <div>
                        <p class="institution-name">{{ $tenant?->institution_name ?? $payment->student?->branch?->name ?? 'Institution' }}</p>
                        @if ($tenant?->address)
                            <p class="institution-address">{{ $tenant->address }}</p>
                        @elseif ($payment->student?->branch?->address)
                            <p class="institution-address">{{ $payment->student->branch->address }}</p>
                        @endif
                    </div>
                </div>
                <div class="receipt-mark">
                    <h1 id="receipt-title">Payment receipt</h1>
                    <div class="receipt-number">{{ $payment->receipt?->receipt_number ?? $payment->transaction_reference }}</div>
                    <span class="status {{ $payment->receipt?->status === 'cancelled' ? 'status-cancelled' : '' }}">
                        {{ $payment->receipt?->status === 'cancelled' ? 'Cancelled' : ucfirst($payment->status) }}
                    </span>
                </div>
            </header>

            <div class="receipt-body">
                <section class="details" aria-label="Payment details">
                    <div>
                        <span class="detail-label">Student</span>
                        <div class="detail-value">
                            {{ trim(($payment->student?->first_name ?? '').' '.($payment->student?->last_name ?? '')) ?: 'Student' }}
                            @if ($payment->student?->student_id)
                                <div class="muted">ID: {{ $payment->student->student_id }}</div>
                            @endif
                        </div>
                    </div>
                    <div>
                        <span class="detail-label">Branch</span>
                        <div class="detail-value">{{ $payment->student?->branch?->name ?? '—' }}</div>
                    </div>
                    <div>
                        <span class="detail-label">Transaction reference</span>
                        <div class="detail-value">{{ $payment->transaction_reference }}</div>
                    </div>
                    @if ($payment->payment_reference)
                        <div>
                            <span class="detail-label">Payment reference</span>
                            <div class="detail-value">{{ $payment->payment_reference }}</div>
                        </div>
                    @endif
                    <div>
                        <span class="detail-label">Payment method</span>
                        <div class="detail-value">
                            @forelse ($payment->paymentTenders as $tender)
                                <div class="tenders">
                                    <div class="tender">
                                        <span>{{ ucfirst(str_replace('_', ' ', $tender->payment_mode)) }}</span>
                                        <span>₹{{ number_format((float) $tender->amount, 2) }}</span>
                                    </div>
                                </div>
                            @empty
                                {{ ucfirst(str_replace('_', ' ', $payment->payment_mode)) }}
                            @endforelse
                        </div>
                    </div>
                    <div>
                        <span class="detail-label">Payment date</span>
                        <div class="detail-value">{{ $payment->paid_at?->format('d M Y, h:i A') ?? '—' }}</div>
                    </div>
                    @if ($payment->academic_year_id)
                        <div>
                            <span class="detail-label">Academic year</span>
                            <div class="detail-value">{{ $payment->academicYear?->name ?? '—' }}</div>
                        </div>
                    @endif
                </section>

                <section aria-labelledby="items-title">
                    <h2 class="section-title" id="items-title">Payment details</h2>
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Fee item</th>
                                <th scope="col" class="amount">Paid amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payment->allocations as $allocation)
                                @php
                                    $assignment = $allocation->studentFeeAssignment;
                                    $component = $assignment?->component;
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $component?->structure?->name ?? $component?->name ?? 'Student fee' }}</strong>
                                        @if ($component && $component->structure && $component->name !== $component->structure->name)
                                            <div class="muted">{{ $component->name }}</div>
                                        @endif
                                        @if ($component?->academicYear?->name)
                                            <div class="muted">{{ $component->academicYear->name }}</div>
                                        @endif
                                    </td>
                                    <td class="amount">₹{{ number_format((float) $allocation->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td>Payment received</td>
                                    <td class="amount">₹{{ number_format((float) $payment->amount, 2) }}</td>
                                </tr>
                            @endforelse
                            <tr class="total-row">
                                <td>Total paid</td>
                                <td class="amount total">₹{{ number_format((float) $payment->amount, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>

                @if ($payment->receipt?->status === 'cancelled')
                    <p class="receipt-footer">This receipt has been cancelled and is no longer valid as proof of payment.</p>
                @else
                    <p class="receipt-footer">Thank you. Please keep this receipt for your records.</p>
                @endif
            </div>
        </article>

        @if ($payment->status === 'completed' && auth()->user()->hasPermission('payments.collect'))
            <section class="management d-print-none" aria-labelledby="correction-title">
                <h2 id="correction-title">Payment adjustments</h2>
                <p class="management-intro">Submitting a request leaves the recorded payment unchanged. An authorized reviewer approves or rejects it; approved changes update the ledger. See each request, decision, and ledger entry in <a href="{{ route('payments.history', $payment) }}">adjustment history</a>.</p>
                <div class="action-grid">
                    <form class="action-card" method="post" action="{{ route('payments.corrections.store', $payment) }}">
                        @csrf
                        <label for="corrected_amount">Correct amount</label>
                        <input id="corrected_amount" name="amount" type="number" min="0.01" step="0.01" value="{{ $payment->amount }}" required>
                        <textarea name="reason" rows="3" placeholder="Reason for correction" required></textarea>
                        <button class="button" type="submit">Request correction</button>
                    </form>
                    <form class="action-card" method="post" action="{{ route('payments.refunds.store', $payment) }}">
                        @csrf
                        <label for="refund_amount">Refund amount</label>
                        <input id="refund_amount" name="amount" type="number" min="0.01" step="0.01" max="{{ $payment->amount }}" required>
                        <textarea name="reason" rows="3" placeholder="Reason for refund" required></textarea>
                        <button class="button button-warning" type="submit">Request refund</button>
                    </form>
                    @if ($payment->receipt?->status === 'issued')
                        <form class="action-card" method="post" action="{{ route('payments.receipt-cancellations.store', $payment) }}">
                            @csrf
                            <label for="cancel_reason">Cancel receipt</label>
                            <textarea id="cancel_reason" name="reason" rows="5" placeholder="Reason for cancellation" required></textarea>
                            <button class="button button-danger" type="submit">Request cancellation</button>
                        </form>
                    @endif
                </div>
            </section>
        @endif
    </main>
</body>
</html>
