@extends('layouts.portal')

@section('content')
    <x-admin.page-header
        title="Payment receipt"
        description="Payment record for {{ $student->first_name }} {{ $student->last_name }} · {{ $student->student_id }}"
    >
        <x-slot:actions>
            <button class="btn btn-primary" type="button" onclick="window.print()">
                <i class="fa-solid fa-print mr-1" aria-hidden="true"></i>Print receipt
            </button>
        </x-slot:actions>
    </x-admin.page-header>

    <article class="admin-card portal-receipt-card">
        <header class="portal-receipt-header">
            <div class="portal-receipt-institution">
                @if ($tenant->logo)
                    <img src="{{ asset('storage/'.$tenant->logo) }}" alt="{{ $tenant->institution_name }} logo">
                @else
                    <span class="portal-brand-mark" aria-hidden="true"><i class="fa-solid fa-school"></i></span>
                @endif
                <div>
                    <h2>{{ $tenant->institution_name }}</h2>
                    <p>{{ ucfirst($tenant->institution_type) }} · {{ $student->branch?->name }}</p>
                    @if ($tenant->phone || $tenant->email)
                        <small>{{ collect([$tenant->phone, $tenant->email])->filter()->implode(' · ') }}</small>
                    @endif
                </div>
            </div>
            <div class="portal-receipt-number">
                <span>Receipt</span>
                <strong>{{ $payment->receipt?->receipt_number ?? $payment->transaction_reference }}</strong>
                <x-admin.badge :tone="$payment->receipt?->status === 'cancelled' ? 'warning' : 'success'">
                    {{ $payment->receipt?->status === 'cancelled' ? 'Cancelled' : 'Paid' }}
                </x-admin.badge>
            </div>
        </header>

        <div class="portal-receipt-body">
            <dl class="portal-receipt-details">
                <div><dt>Student</dt><dd>{{ $student->first_name }} {{ $student->last_name }}</dd></div>
                <div><dt>Student ID</dt><dd>{{ $student->student_id }}</dd></div>
                <div><dt>Payment date</dt><dd>{{ $payment->paid_at?->format('d M Y, h:i A') ?? '—' }}</dd></div>
                <div><dt>Academic year</dt><dd>{{ $payment->academicYear?->name ?? '—' }}</dd></div>
                <div><dt>Transaction reference</dt><dd>{{ $payment->transaction_reference }}</dd></div>
                <div>
                    <dt>Payment method</dt>
                    <dd>
                        @forelse ($payment->paymentTenders as $tender)
                            {{ ucfirst(str_replace('_', ' ', $tender->payment_mode)) }}{{ ! $loop->last ? ', ' : '' }}
                        @empty
                            {{ ucfirst(str_replace('_', ' ', $payment->payment_mode)) }}
                        @endforelse
                    </dd>
                </div>
            </dl>

            <h3>Payment details</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>Fee item</th><th class="text-right">Paid amount</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($payment->allocations as $allocation)
                            @php
                                $component = $allocation->studentFeeAssignment?->component;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $component?->structure?->name ?? $component?->name ?? 'Student fee' }}</strong>
                                    @if ($component && $component->structure && $component->name !== $component->structure->name)
                                        <small class="d-block text-muted">{{ $component->name }}</small>
                                    @endif
                                    @if ($component?->academicYear?->name)
                                        <small class="d-block text-muted">{{ $component->academicYear->name }}</small>
                                    @endif
                                </td>
                                <td class="text-right">₹{{ number_format((float) $allocation->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td>Payment received</td>
                                <td class="text-right">₹{{ number_format((float) $payment->amount, 2) }}</td>
                            </tr>
                        @endforelse
                        <tr class="portal-receipt-total">
                            <td>Total paid</td>
                            <td class="text-right">₹{{ number_format((float) $payment->amount, 2) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <p class="portal-receipt-note">
                @if ($payment->receipt?->status === 'cancelled')
                    This receipt has been cancelled and is no longer valid as proof of payment.
                @else
                    Thank you. Please keep this receipt for your records.
                @endif
            </p>
        </div>
    </article>
@endsection
