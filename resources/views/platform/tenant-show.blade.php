@extends('layouts.app')

@section('content')
    <div class="platform-tenants-page">
        <x-admin.page-header title="{{ $tenant->institution_name }}" description="Tenant profile, subscription history, and resource usage." />

        <div class="platform-detail-toolbar"><a href="{{ route('platform.tenants') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left mr-1" aria-hidden="true"></i>Back to tenants</a><a href="{{ route('platform.usage', ['q' => $tenant->institution_name]) }}" class="btn btn-outline-primary"><i class="fa-solid fa-chart-simple mr-1" aria-hidden="true"></i>View usage</a></div>

        <section class="platform-summary" aria-label="Tenant resource usage">
            <x-admin.stat-card label="Students" :value="number_format($counts['students'])" detail="Enrolled student records" tone="info" icon="fa-user-graduate" />
            <x-admin.stat-card label="Users" :value="number_format($counts['users'])" detail="Tenant memberships" icon="fa-users" />
            <x-admin.stat-card label="Branches" :value="number_format($counts['branches'])" detail="Institution campuses" tone="success" icon="fa-code-branch" />
            <x-admin.stat-card label="Subscriptions" :value="number_format($tenant->subscriptions->count())" detail="Billing history records" tone="warning" icon="fa-receipt" />
        </section>

        <div class="platform-detail-grid">
            <section class="admin-card platform-section" aria-labelledby="tenant-profile-title">
                <div class="platform-section-heading"><div><h2 id="tenant-profile-title">Institution profile</h2><p>Registration and account ownership details.</p></div><span class="platform-status-badge is-{{ $tenant->status === 'active' ? 'success' : ($tenant->status === 'trial' ? 'info' : 'warning') }}">{{ ucfirst($tenant->status) }}</span></div>
                <dl class="platform-detail-list">
                    <div><dt>Institution</dt><dd>{{ $tenant->institution_name }} <span class="text-muted">({{ ucfirst($tenant->institution_type) }})</span></dd></div>
                    <div><dt>Contact email</dt><dd><a href="mailto:{{ $tenant->email }}">{{ $tenant->email }}</a></dd></div>
                    <div><dt>Phone</dt><dd>{{ $tenant->phone ?: 'Not provided' }}</dd></div>
                    <div><dt>Owner account</dt><dd>{{ $tenant->owner?->name ?? 'Not assigned' }}{{ $tenant->owner?->email ? ' · '.$tenant->owner->email : '' }}</dd></div>
                    <div><dt>Registration</dt><dd><span class="platform-status-badge is-{{ $tenant->registration_status === 'approved' ? 'success' : ($tenant->registration_status === 'pending' ? 'warning' : 'secondary') }}">{{ str($tenant->registration_status)->replace('_', ' ')->title() }}</span>@if($tenant->registration_reviewed_at)<small class="platform-review-date">Reviewed by {{ $tenant->registrationReviewer?->name ?? 'Platform admin' }} · {{ $tenant->registration_reviewed_at->format('d M Y H:i') }}</small>@endif</dd></div>
                    <div><dt>Current plan</dt><dd>{{ $tenant->plan?->name ?? 'No plan assigned' }}</dd></div>
                    <div><dt>Joined</dt><dd>{{ $tenant->created_at->format('d M Y') }}</dd></div>
                </dl>
            </section>

            <section class="admin-card platform-section" aria-labelledby="tenant-limits-title">
                <div class="platform-section-heading"><div><h2 id="tenant-limits-title">Plan usage</h2><p>Current usage compared with enforced tenant limits.</p></div></div>
                <div class="platform-usage-metrics">
                    @foreach ($usage as $resource => $metric)
                        <div class="platform-usage-metric">
                            <div><strong>{{ ucfirst($resource) }}</strong><span>{{ number_format($metric['used']) }} / {{ $metric['limit'] === null ? 'Unlimited' : number_format($metric['limit']) }}</span></div>
                            @if ($metric['limit'] !== null)<div class="platform-progress-track"><span class="{{ $metric['is_over'] ? 'is-over' : ($metric['is_near'] ? 'is-near' : '') }}" style="width: {{ $metric['percentage'] }}%"></span></div>@endif
                            @if ($metric['is_over'])<small class="platform-usage-note is-over">Above plan limit</small>@elseif($metric['is_near'])<small class="platform-usage-note is-near">Near plan limit</small>@endif
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="admin-card platform-section" aria-labelledby="tenant-branches-title">
            <div class="platform-section-heading"><div><h2 id="tenant-branches-title">Branches</h2><p>Campus locations belonging to this tenant.</p></div><span class="platform-count">{{ number_format($counts['branches']) }}</span></div>
            <div class="table-responsive platform-table-wrap"><table class="table platform-tenant-table mb-0"><thead><tr><th>Branch</th><th>Code</th><th>Status</th></tr></thead><tbody>
                @forelse ($tenant->branches as $branch)<tr><td>{{ $branch->name }}</td><td>{{ $branch->code }}</td><td><span class="platform-status-badge {{ $branch->is_active ? 'is-success' : 'is-secondary' }}">{{ $branch->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                @empty<tr><td colspan="3">No branches have been created.</td></tr>@endforelse
            </tbody></table></div>
        </section>

        <div class="platform-detail-grid">
            <section class="admin-card platform-section" aria-labelledby="tenant-users-title">
                <div class="platform-section-heading"><div><h2 id="tenant-users-title">Recent users</h2><p>Most recently joined tenant memberships.</p></div><span class="platform-count">{{ number_format($counts['users']) }} total</span></div>
                <div class="table-responsive platform-table-wrap"><table class="table platform-tenant-table mb-0"><thead><tr><th>User</th><th>Role</th><th>Membership</th></tr></thead><tbody>
                    @forelse ($tenant->users as $user)<tr><td><strong>{{ $user->name }}</strong><small class="d-block text-muted">{{ $user->email }}</small></td><td>{{ str($user->pivot->role)->replace('_', ' ')->title() }}</td><td><span class="platform-status-badge {{ $user->pivot->is_active ? 'is-success' : 'is-secondary' }}">{{ $user->pivot->is_active ? 'Active' : 'Inactive' }}</span></td></tr>
                    @empty<tr><td colspan="3">No tenant users found.</td></tr>@endforelse
                </tbody></table></div>
            </section>

            <section class="admin-card platform-section" aria-labelledby="tenant-audit-title">
                <div class="platform-section-heading"><div><h2 id="tenant-audit-title">Platform activity</h2><p>Recent administrative actions for this tenant.</p></div><a class="platform-detail-link" href="{{ route('platform.audit', ['tenant_id' => $tenant->id]) }}">Open audit log <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
                <div class="platform-activity-list">
                    @forelse ($audit as $entry)<div><span class="platform-activity-icon"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i></span><span><strong>{{ str($entry->action)->after('platform.')->replace('.', ' / ')->headline() }}</strong><small>{{ $entry->user?->name ?? 'Platform admin' }} · {{ $entry->created_at->format('d M Y H:i') }}</small></span></div>
                    @empty<p class="platform-empty-copy">No platform actions have been logged for this tenant.</p>@endforelse
                </div>
            </section>
        </div>

        <section class="admin-card platform-section" aria-labelledby="tenant-subscriptions-title">
            <div class="platform-section-heading"><div><h2 id="tenant-subscriptions-title">Subscription history</h2><p>Trial and renewal periods, amounts, and payment states.</p></div><a class="platform-detail-link" href="{{ route('platform.subscriptions', ['q' => $tenant->institution_name]) }}">Open billing <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></div>
            <div class="table-responsive platform-table-wrap"><table class="table platform-tenant-table mb-0"><thead><tr><th>Plan</th><th>Period</th><th>Term</th><th>Amount</th><th>Subscription</th><th>Payment</th></tr></thead><tbody>
                @forelse ($tenant->subscriptions as $subscription)
                    <tr><td>{{ $subscription->plan?->name ?? 'Plan unavailable' }}</td><td>{{ $subscription->start_date?->format('d M Y') ?? '—' }} – {{ $subscription->end_date?->format('d M Y') ?? 'No expiry' }}</td><td>{{ ucfirst($subscription->billing_interval ?? 'Trial') }}</td><td>₹{{ number_format($subscription->amount_due ?? 0, 2) }}</td><td>{{ str($subscription->status)->replace('_', ' ')->title() }}</td><td>{{ str($subscription->payment_status)->replace('_', ' ')->title() }}@if($subscription->payment_reference)<small class="platform-review-date">Ref: {{ $subscription->payment_reference }}</small>@endif</td></tr>
                @empty<tr><td colspan="6">No subscription records found.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </div>
@endsection
