@extends('layouts.app')

@section('content')
    <div class="platform-tenants-page">
        <x-admin.page-header title="Subscriptions & billing" description="Track tenant subscription periods and verify submitted renewal payments." />
        <section class="platform-summary platform-billing-summary" aria-label="Billing overview">
            <x-admin.stat-card label="Subscriptions" :value="number_format($summary['subscriptions'])" detail="All billing records" icon="fa-receipt" />
            <x-admin.stat-card label="Active" :value="number_format($summary['active'])" detail="Currently paid subscriptions" tone="success" icon="fa-circle-check" />
            <x-admin.stat-card label="Trials" :value="number_format($summary['trials'])" detail="Trial subscriptions" tone="info" icon="fa-hourglass-half" />
            <x-admin.stat-card label="Payment reviews" :value="number_format($summary['payment_reviews'])" detail="Renewals awaiting verification" tone="warning" icon="fa-file-circle-check" />
            <x-admin.stat-card label="Recorded payments" :value="'₹'.number_format($summary['paid_amount'], 2)" detail="Approved payment amounts" icon="fa-indian-rupee-sign" />
        </section>
        <section class="admin-card platform-section" aria-labelledby="billing-list-title">
            <div class="platform-section-heading"><div><h2 id="billing-list-title">Billing activity</h2><p>Use filters to find a tenant, payment state, or billing term.</p></div><span class="platform-count">{{ number_format($subscriptions->total()) }} results</span></div>
            <form method="get" class="platform-filters js-filter-form" aria-label="Filter subscriptions">
                <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Search tenant">
                <select class="form-control" name="status" aria-label="Subscription status"><option value="">All subscription states</option>@foreach (['trial', 'active', 'expired', 'suspended', 'cancelled'] as $value)<option value="{{ $value }}" @selected(request('status') === $value)>{{ ucfirst($value) }}</option>@endforeach</select>
                <select class="form-control" name="payment_status" aria-label="Payment status"><option value="">All payment states</option>@foreach (['renewal_requested' => 'Awaiting review', 'paid' => 'Paid', 'not_required' => 'Not required', 'pending' => 'Pending', 'rejected' => 'Rejected'] as $value => $label)<option value="{{ $value }}" @selected(request('payment_status') === $value)>{{ $label }}</option>@endforeach</select>
                <select class="form-control" name="billing_interval" aria-label="Billing interval"><option value="">All billing terms</option><option value="monthly" @selected(request('billing_interval') === 'monthly')>Monthly</option><option value="yearly" @selected(request('billing_interval') === 'yearly')>Annual</option></select>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>Filter</button>
                @if (request()->hasAny(['q', 'status', 'payment_status', 'billing_interval']))<a class="btn btn-outline-secondary" href="{{ route('platform.subscriptions') }}">Clear</a>@endif
            </form>
            <div class="table-responsive platform-table-wrap"><table class="table platform-tenant-table mb-0"><thead><tr><th>Tenant</th><th>Plan / term</th><th>Period</th><th>Amount</th><th>Payment</th><th>Review</th></tr></thead><tbody>
                @forelse ($subscriptions as $subscription)
                    @php
                        $paymentTone = match ($subscription->payment_status) { 'paid' => 'success', 'renewal_requested' => 'warning', 'rejected' => 'secondary', default => 'info' };
                        $subTone = match ($subscription->status) { 'active' => 'success', 'trial' => 'info', default => 'secondary' };
                    @endphp
                    <tr>
                        <td><div class="platform-institution"><span class="platform-institution-logo platform-institution-placeholder"><i class="fa-solid fa-building" aria-hidden="true"></i></span><span class="platform-institution-copy"><strong>{{ $subscription->tenant?->institution_name ?? 'Tenant unavailable' }}</strong><small>{{ $subscription->tenant?->email }}</small><small>Subscription #{{ $subscription->id }}</small></span></div></td>
                        <td><strong class="platform-plan-name">{{ $subscription->plan?->name ?? 'Plan unavailable' }}</strong><span class="platform-expiry">{{ ucfirst($subscription->billing_interval ?? 'Trial') }}</span><br><span class="platform-status-badge is-{{ $subTone }}">{{ ucfirst($subscription->status) }}</span></td>
                        <td>{{ $subscription->start_date?->format('d M Y') ?? '—' }}<small class="platform-review-date">through {{ $subscription->end_date?->format('d M Y') ?? 'No expiry' }}</small></td>
                        <td><strong class="platform-renewal-amount">₹{{ number_format($subscription->amount_due ?? 0, 2) }}</strong></td>
                        <td><span class="platform-status-badge is-{{ $paymentTone }}">{{ str($subscription->payment_status)->replace('_', ' ')->title() }}</span>@if ($subscription->payment_reference)<small class="platform-review-date">Ref: <span class="platform-reference">{{ $subscription->payment_reference }}</span></small>@endif</td>
                        <td>
                            @if ($subscription->payment_status === 'renewal_requested' && $subscription->tenant)
                                <div class="platform-renewal-actions">
                                    <form method="post" action="{{ route('platform.subscriptions.review', [$subscription->tenant, $subscription]) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success" type="submit"><i class="fa-solid fa-check mr-1" aria-hidden="true"></i>Verify</button></form>
                                    <form method="post" action="{{ route('platform.subscriptions.review', [$subscription->tenant, $subscription]) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-sm btn-outline-danger" type="submit">Reject</button></form>
                                </div>
                            @else
                                <small>{{ $subscription->approver?->name ? 'Reviewed by '.$subscription->approver->name : ($subscription->requester?->name ? 'Submitted by '.$subscription->requester->name : '—') }}</small>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-admin.empty-state title="No billing records found" description="Try changing the search or filters." /></td></tr>
                @endforelse
            </tbody></table></div>
            <div class="platform-pagination"><x-admin.pagination :paginator="$subscriptions" /></div>
        </section>
    </div>
@endsection
