@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Payment adjustment history"
        description="Follow each adjustment request, decision, and resulting ledger entry."
    >
        <x-slot name="actions">
            <a class="btn btn-outline-secondary" href="{{ route('payments.receipt', $payment) }}">Back to receipt</a>
        </x-slot>
    </x-admin.page-header>

    <x-admin.card class="mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <div class="small text-muted">Student</div>
                <strong>{{ $payment->student?->first_name }} {{ $payment->student?->last_name }}</strong>
            </div>
            <div>
                <div class="small text-muted">Transaction</div>
                <strong>{{ $payment->transaction_reference }}</strong>
            </div>
            <div>
                <div class="small text-muted">Current payment amount</div>
                <strong>₹{{ number_format((float) $payment->amount, 2) }}</strong>
            </div>
            <div>
                <div class="small text-muted">Payment status</div>
                <span class="badge badge-{{ $payment->status === 'completed' ? 'success' : 'secondary' }}">
                    {{ ucfirst($payment->status) }}
                </span>
            </div>
        </div>
    </x-admin.card>

    <x-admin.card padding="false">
        <div class="card-header">
            <h2 class="h5 mb-0">Request and ledger timeline</h2>
        </div>
        <x-admin.table>
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Reason and details</th>
                    <th class="text-right">Amount</th>
                    <th>Status</th>
                    <th>Requested / recorded by</th>
                    <th>Reviewed by</th>
                    <th>Dates</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($history as $event)
                    @php
                        $statusClass = match ($event->status) {
                            'approved', 'processed', 'recorded' => 'success',
                            'rejected' => 'danger',
                            'pending' => 'warning',
                            default => 'secondary',
                        };
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $event->kind }}</strong>
                            <div class="small text-muted">{{ $event->summary }}</div>
                        </td>
                        <td>
                            {{ $event->reason ?: '—' }}
                            @if ($event->review_note)
                                <div class="small mt-1"><strong>Review note:</strong> {{ $event->review_note }}</div>
                            @endif
                        </td>
                        <td class="text-right text-nowrap">
                            @if (is_numeric($event->amount))
                                @if ($event->kind === 'Fee ledger reversal')
                                    −₹{{ number_format(abs((float) $event->amount), 2) }}
                                @else
                                    ₹{{ number_format((float) $event->amount, 2) }}
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td><span class="badge badge-{{ $statusClass }}">{{ ucfirst($event->status) }}</span></td>
                        <td>{{ $event->requester_name ?: '—' }}</td>
                        <td>{{ $event->reviewer_name ?: '—' }}</td>
                        <td class="text-nowrap">
                            <div>{{ \Illuminate\Support\Carbon::parse($event->submitted_at)->format('d M Y, h:i A') }}</div>
                            @if ($event->decided_at)
                                <div class="small text-muted">Decision: {{ \Illuminate\Support\Carbon::parse($event->decided_at)->format('d M Y, h:i A') }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-admin.empty-state
                                title="No adjustment history"
                                description="Correction, refund, or receipt cancellation requests will appear here with their decisions and ledger entries."
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>
    </x-admin.card>
@endsection
