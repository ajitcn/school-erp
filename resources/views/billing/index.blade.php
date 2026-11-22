@extends('layouts.app')

@section('content')
    @php
        $pending = $subscriptions->first(fn ($item) => in_array(
            $item->payment_status,
            ['renewal_requested', 'checkout_created'],
            true,
        ));
        $onlinePaymentEnabled = (bool) (config('services.razorpay.key') && config('services.razorpay.secret'));
        $hasValidSubscription = $tenant->hasValidSubscription();
        $plansHaveOptions = $plans->isNotEmpty();
        $selectedPlanId = $errors->has('subscription_plan_id')
            ? old('subscription_plan_id', $tenant->subscription_plan_id)
            : $tenant->subscription_plan_id;
        $selectedBillingInterval = $errors->has('billing_interval') || $errors->has('subscription_plan_id')
            ? old('billing_interval', 'yearly')
            : 'yearly';
        $planPrices = $plans->mapWithKeys(fn ($plan) => [
            $plan->id => [
                'monthly' => (float) $plan->monthly_price,
                'yearly' => (float) $plan->yearly_price,
            ],
        ]);
        $paymentStatusLabels = [
            'paid' => 'Paid',
            'renewal_requested' => 'Awaiting review',
            'checkout_created' => 'Payment in progress',
            'rejected' => 'Rejected',
            'not_required' => 'Not required',
        ];
    @endphp

    <div class="billing-page">
        <x-admin.page-header
            title="Subscription & billing"
            description="Manage your institution plan, renewal, and payment history."
        />

        @if (session('subscription_expired') || ! $hasValidSubscription)
            <div class="alert alert-warning billing-notice" role="status">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <div>
                    <strong>Subscription access has expired.</strong>
                    <p class="mb-0">Submit a renewal request to restore access after platform review.</p>
                </div>
            </div>
        @elseif (session('subscription_blocked'))
            <div class="alert alert-warning billing-notice" role="status">
                <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                <div><strong>{{ session('subscription_blocked') }}</strong></div>
            </div>
        @endif

        <section class="row billing-overview mb-4" aria-label="Subscription overview">
            <div class="col-xl-4 col-md-6 mb-3">
                <x-admin.stat-card
                    label="Current plan"
                    :value="$tenant->plan?->name ?? $current?->plan?->name ?? 'No active plan'"
                    detail="{{ $current ? ($current->status === 'trial' ? 'Trial access period' : ucfirst($current->billing_interval ?? 'monthly').' billing') : 'Institution subscription' }}"
                    tone="primary"
                    icon="fa-layer-group"
                />
            </div>
            <div class="col-xl-4 col-md-6 mb-3">
                <x-admin.stat-card
                    label="Subscription status"
                    :value="$pending ? 'Renewal pending' : ($hasValidSubscription ? 'Active' : 'Expired / inactive')"
                    detail="{{ $current?->status === 'trial' ? 'Trial subscription' : ($hasValidSubscription ? 'Paid subscription' : 'No active access') }}"
                    tone="info"
                    icon="fa-shield-halved"
                />
            </div>
            <div class="col-xl-4 col-md-6 mb-3">
                <x-admin.stat-card
                    label="Access through"
                    :value="$current?->end_date?->format('d M Y') ?? '—'"
                    :detail="$current ? $tenant->subscriptionDaysRemaining().' day(s) remaining' : 'No active expiry date'"
                    tone="warning"
                    icon="fa-calendar-check"
                />
            </div>
        </section>

        @if ($pending && $pending->payment_status === 'checkout_created' && $canManage)
            <section class="billing-pending mb-4" aria-label="Payment in progress">
                <div class="billing-pending-icon"><i class="fa-solid fa-lock" aria-hidden="true"></i></div>
                <div class="billing-pending-copy">
                    <h2 class="h5 mb-1">Online payment in progress</h2>
                    <p class="mb-0">
                        {{ $pending->plan?->name }} · {{ ucfirst($pending->billing_interval ?? 'monthly') }} ·
                        <strong>₹{{ number_format($pending->amount_due ?? 0, 2) }}</strong>
                    </p>
                </div>
                <a class="btn btn-primary" href="{{ route('billing.checkout.resume', $pending) }}">Resume secure checkout</a>
            </section>
        @elseif ($pending && $pending->payment_status === 'renewal_requested' && $canManage)
            <section class="billing-pending billing-pending--review mb-4" aria-label="Renewal awaiting review">
                <div class="billing-pending-icon"><i class="fa-solid fa-hourglass-half" aria-hidden="true"></i></div>
                <div class="billing-pending-copy">
                    <h2 class="h5 mb-1">Renewal awaiting review</h2>
                    <p class="mb-1">
                        {{ $pending->plan?->name }} · {{ ucfirst($pending->billing_interval ?? 'monthly') }} ·
                        <strong>₹{{ number_format($pending->amount_due ?? 0, 2) }}</strong>
                    </p>
                    <p class="small mb-0">
                        Reference: {{ $pending->payment_reference ?: '—' }} ·
                        Submitted {{ $pending->created_at?->format('d M Y, h:i A') }}
                    </p>
                </div>
            </section>
        @elseif ($pending)
            <div class="alert alert-info billing-notice" role="status">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <div><strong>A subscription renewal is in progress.</strong><p class="mb-0">Your renewal status will update here after it is reviewed.</p></div>
            </div>
        @endif

        @if ($canManage && ! $pending)
            <x-admin.card class="billing-renewal mb-4" aria-labelledby="billing-renewal-title">
                <div class="billing-section-heading">
                    <div>
                        <h2 id="billing-renewal-title" class="h5 mb-1">Renew your subscription</h2>
                        <p class="text-muted mb-0">Choose a plan and term. Your renewal will begin after the current expiry date, if applicable.</p>
                    </div>
                    <span class="billing-review-note"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Access updates after confirmation</span>
                </div>

                @if ($errors->has('subscription') || $errors->has('payment'))
                    <div class="alert alert-danger" role="alert">
                        {{ $errors->first('subscription') ?? $errors->first('payment') }}
                    </div>
                @endif

                @if (! $plansHaveOptions)
                    <div class="billing-empty-plans" role="status">
                        No active subscription plans are available. Contact the platform administrator.
                    </div>
                @else
                    <div class="billing-plan-selection">
                        <div class="row align-items-end">
                            <div class="col-lg-5 col-md-6 form-group">
                                <label for="billing-plan-select">Plan</label>
                                <select id="billing-plan-select" class="form-control" required>
                                    @foreach ($plans as $plan)
                                        <option value="{{ $plan->id }}" @selected($selectedPlanId == $plan->id)>
                                            {{ $plan->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('subscription_plan_id')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-lg-4 col-md-6 form-group">
                                <label for="billing-term-select">Billing term</label>
                                <select id="billing-term-select" class="form-control" required>
                                    <option value="yearly" @selected($selectedBillingInterval === 'yearly')>Annual</option>
                                    <option value="monthly" @selected($selectedBillingInterval === 'monthly')>Monthly</option>
                                </select>
                                @error('billing_interval')
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-lg-3 col-md-12 form-group">
                                <div class="billing-price-preview" aria-live="polite">
                                    <span>Amount due</span>
                                    <strong id="billing-price-display">—</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        @if ($onlinePaymentEnabled)
                            <div class="col-lg-6 mb-3 mb-lg-0">
                                <div class="billing-option-card billing-option-card--online">
                                    <div class="billing-option-heading">
                                        <span class="billing-option-icon"><i class="fa-solid fa-credit-card" aria-hidden="true"></i></span>
                                        <div>
                                            <h3 class="h6 mb-1">Pay online</h3>
                                            <p class="small text-muted mb-0">Secure checkout with card, UPI, or net banking.</p>
                                        </div>
                                    </div>
                                    <form method="post" action="{{ route('billing.checkout') }}" class="billing-renewal-submit">
                                        @csrf
                                        <input type="hidden" name="subscription_plan_id" data-selected-plan>
                                        <input type="hidden" name="billing_interval" data-selected-term>
                                        <p class="small text-muted mb-3">Your selected plan and term will be charged securely by Razorpay.</p>
                                        <button class="btn btn-primary btn-block" type="submit">Continue to secure payment</button>
                                    </form>
                                </div>
                            </div>
                        @endif

                        <div class="{{ $onlinePaymentEnabled ? 'col-lg-6' : 'col-12' }}">
                            <div class="{{ $onlinePaymentEnabled ? 'billing-option-card' : 'billing-manual-option' }}">
                                <div class="billing-option-heading">
                                    <span class="billing-option-icon billing-option-icon--manual"><i class="fa-solid fa-receipt" aria-hidden="true"></i></span>
                                    <div>
                                        <h3 class="h6 mb-1">Request manual payment review</h3>
                                        <p class="small text-muted mb-0">For payments made outside this portal, use the transaction reference from your receipt.</p>
                                    </div>
                                </div>
                                <form method="post" action="{{ route('billing.renewals.store') }}" class="billing-renewal-submit billing-renewal-submit--manual">
                                    @csrf
                                    <input type="hidden" name="subscription_plan_id" data-selected-plan>
                                    <input type="hidden" name="billing_interval" data-selected-term>
                                    <div class="form-group billing-reference-field">
                                        <label for="payment_reference">Payment reference</label>
                                        <input
                                            id="payment_reference"
                                            name="payment_reference"
                                            class="form-control @error('payment_reference') is-invalid @enderror"
                                            value="{{ old('payment_reference') }}"
                                            maxlength="150"
                                            autocomplete="off"
                                            placeholder="Transaction ID or receipt number"
                                            required
                                        >
                                        @error('payment_reference')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <button class="btn btn-outline-primary" type="submit">Submit for review</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endif
            </x-admin.card>
        @elseif (! $canManage)
            <section class="billing-contact-note mb-4">
                <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                <p class="mb-0">Ask your tenant owner or administrator to renew this subscription.</p>
            </section>
        @endif

        @if ($canManage)
            <x-admin.card padding="false" class="billing-history">
                <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h2 class="h5 mb-1">Subscription history</h2>
                        <p class="small text-muted mb-0">Renewal requests and confirmed subscription periods.</p>
                    </div>
                    <span class="badge badge-light">{{ $subscriptions->count() }} record(s)</span>
                </div>
                <x-admin.table>
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Term</th>
                            <th>Subscription period</th>
                            <th class="text-right">Amount</th>
                            <th>Payment status</th>
                            <th>Reference</th>
                            <th>Reviewed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscriptions as $subscription)
                            @php
                                $paymentStatus = $subscription->payment_status ?: 'not_required';
                                $badgeClass = match ($paymentStatus) {
                                    'paid' => 'badge-success',
                                    'renewal_requested' => 'badge-warning',
                                    'checkout_created' => 'badge-info',
                                    'rejected' => 'badge-danger',
                                    default => 'badge-secondary',
                                };
                            @endphp
                            <tr>
                                <td><strong>{{ $subscription->plan?->name ?? '—' }}</strong></td>
                                <td>{{ ucfirst($subscription->billing_interval ?? '—') }}</td>
                                <td class="text-nowrap">
                                    {{ $subscription->start_date?->format('d M Y') ?? '—' }} –
                                    {{ $subscription->end_date?->format('d M Y') ?? 'No expiry' }}
                                </td>
                                <td class="text-right text-nowrap">
                                    {{ $subscription->amount_due !== null ? '₹'.number_format((float) $subscription->amount_due, 2) : '—' }}
                                </td>
                                <td><span class="badge {{ $badgeClass }}">{{ $paymentStatusLabels[$paymentStatus] ?? str($paymentStatus)->replace('_', ' ')->title() }}</span></td>
                                <td>{{ $subscription->payment_reference ?: '—' }}</td>
                                <td>
                                    @if ($subscription->approver?->name)
                                        {{ $subscription->approver->name }}
                                        <small class="d-block text-muted">{{ $subscription->approved_at?->format('d M Y') ?? '' }}</small>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7">
                                    <x-admin.empty-state
                                        title="No subscription history yet"
                                        description="Confirmed plans and renewal requests will appear here."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
            </x-admin.card>
        @endif
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const prices = @json($planPrices);
            const planSelect = document.getElementById('billing-plan-select');
            const intervalSelect = document.getElementById('billing-term-select');
            const priceDisplay = document.getElementById('billing-price-display');
            const renewalForms = document.querySelectorAll('.billing-renewal-submit');
            if (!planSelect || !intervalSelect || !priceDisplay) {
                return;
            }

            function syncRenewalOptions() {
                const amount = prices[planSelect.value]?.[intervalSelect.value];
                priceDisplay.textContent = amount === undefined
                    ? '—'
                    : new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);

                renewalForms.forEach(function (form) {
                    form.querySelector('[data-selected-plan]').value = planSelect.value;
                    form.querySelector('[data-selected-term]').value = intervalSelect.value;
                });
            }

            planSelect.addEventListener('change', syncRenewalOptions);
            intervalSelect.addEventListener('change', syncRenewalOptions);
            syncRenewalOptions();
        });
    </script>
@endsection
