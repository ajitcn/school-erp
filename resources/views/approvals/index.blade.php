@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Approval queue"
        description="Review operational and financial requests. Decisions are recorded with the request history."
    />

    <x-admin.card padding="false">
        <x-admin.table>
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Reference</th>
                    <th>Request and reason</th>
                    <th>Amount</th>
                    <th>Requested by</th>
                    <th>Submitted</th>
                    <th>Decision</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $approval)
                    @php
                        $routeName = match ($approval->kind) {
                            'expense' => 'workflow.expense',
                            'attendance' => 'workflow.attendance',
                            'refund' => 'workflow.refund',
                            'payment' => 'workflow.payment',
                            'receipt' => 'workflow.receipt',
                        };
                        $amount = $approval->amount;
                        if ($approval->kind === 'payment') {
                            $changes = json_decode($amount, true) ?? [];
                            $amount = $changes['amount'] ?? null;
                        }
                        $financialAdjustment = in_array($approval->kind, ['refund', 'payment', 'receipt'], true);
                    @endphp
                    <tr>
                        <td>{{ ucfirst($approval->kind) }}</td>
                        <td class="font-weight-bold">{{ $approval->reference }}</td>
                        <td>
                            <div>{{ $approval->summary }}</div>
                            @if ($approval->kind === 'expense' && filled($approval->attachment_path))
                                <a
                                    class="btn btn-sm btn-outline-primary"
                                    style="margin-top: .5rem"
                                    href="{{ route('expenses.bill.preview', $approval->expense_id) }}"
                                    target="_blank"
                                    rel="noopener"
                                    aria-label="Preview attached bill for {{ $approval->reference }}"
                                >
                                    <i class="fa-solid fa-eye mr-1" aria-hidden="true"></i>
                                    Preview bill
                                </a>
                            @else
                                @if ($approval->kind === 'expense')
                                    <span class="small text-muted">No attachment</span>
                                @endif
                            @endif
                        </td>
                        <td>{{ is_numeric($amount) ? '₹'.number_format((float) $amount, 2) : '—' }}</td>
                        <td>{{ $approval->requester_name ?? '—' }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($approval->created_at)->format('d M Y, h:i A') }}</td>
                        <td>
                            <div class="d-flex flex-column flex-xl-row">
                                <form method="post" action="{{ route($routeName, $approval->id) }}" class="mr-xl-2 mb-2 mb-xl-0">
                                    @csrf
                                    <input type="hidden" name="approve" value="1">
                                    <button class="btn btn-sm btn-primary" type="submit">Approve</button>
                                </form>
                                <form method="post" action="{{ route($routeName, $approval->id) }}">
                                    @csrf
                                    <input type="hidden" name="approve" value="0">
                                    @if ($financialAdjustment)
                                        <label class="sr-only" for="decision-note-{{ $approval->kind }}-{{ $approval->id }}">Rejection reason</label>
                                        <input
                                            id="decision-note-{{ $approval->kind }}-{{ $approval->id }}"
                                            name="decision_note"
                                            class="form-control form-control-sm mb-2"
                                            placeholder="Reason required to reject"
                                            required
                                        >
                                    @endif
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            <x-admin.empty-state
                                title="No pending approvals"
                                description="Submitted requests will appear here for review."
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>
    </x-admin.card>
@endsection
