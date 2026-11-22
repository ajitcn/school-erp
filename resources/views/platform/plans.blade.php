@extends('layouts.app')

@section('content')
    <div class="platform-tenants-page">
        <x-admin.page-header title="Subscription plans" description="Manage plan pricing, included capacity, and tenant adoption." />
        <section class="platform-summary" aria-label="Plan overview">
            <x-admin.stat-card label="All plans" :value="number_format($summary['plans'])" detail="Available catalog entries" icon="fa-layer-group" />
            <x-admin.stat-card label="Active plans" :value="number_format($summary['active'])" detail="Available for assignment" tone="success" icon="fa-circle-check" />
            <x-admin.stat-card label="Inactive plans" :value="number_format($summary['inactive'])" detail="Not offered to new tenants" tone="warning" icon="fa-circle-pause" />
            <x-admin.stat-card label="Subscribed tenants" :value="number_format($summary['subscribed_tenants'])" detail="Currently assigned a plan" tone="info" icon="fa-building" />
        </section>
        <section class="platform-plans-section">
            <div class="platform-section-heading platform-plans-heading"><div><h2>Plan catalog</h2><p>Compare plan limits and monthly or annual pricing.</p></div><span class="platform-count">{{ number_format($plans->total()) }} results</span></div>
            <form method="get" class="platform-filters js-filter-form" aria-label="Filter plans">
                <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Search plan name">
                <select class="form-control" name="state" aria-label="Plan availability"><option value="">All plans</option><option value="active" @selected(request('state') === 'active')>Active</option><option value="inactive" @selected(request('state') === 'inactive')>Inactive</option></select>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>Filter</button>
                @if (request()->hasAny(['q', 'state']))<a class="btn btn-outline-secondary" href="{{ route('platform.plans') }}">Clear</a>@endif
            </form>
            <div class="platform-plans-grid">
                @forelse ($plans as $plan)
                    <article class="admin-card platform-plan-card">
                        <div class="platform-plan-heading"><span class="platform-plan-icon"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span><span class="platform-status-badge {{ $plan->is_active ? 'is-success' : 'is-secondary' }}">{{ $plan->is_active ? 'Active' : 'Inactive' }}</span></div>
                        <h3>{{ $plan->name }}</h3>
                        <div class="platform-plan-prices"><span><strong>₹{{ number_format($plan->monthly_price, 0) }}</strong><small>/ month</small></span><span><strong>₹{{ number_format($plan->yearly_price, 0) }}</strong><small>/ year</small></span></div>
                        <div class="platform-plan-limits"><span><i class="fa-solid fa-code-branch" aria-hidden="true"></i>{{ $plan->max_branches ?? 'Unlimited' }} branches</span><span><i class="fa-solid fa-users" aria-hidden="true"></i>{{ $plan->max_users ?? 'Unlimited' }} users</span><span><i class="fa-solid fa-user-graduate" aria-hidden="true"></i>{{ $plan->max_students ?? 'Unlimited' }} students</span></div>
                        <p class="platform-plan-adoption"><strong>{{ number_format($plan->tenants_count) }}</strong> {{ str('tenant')->plural($plan->tenants_count) }} assigned</p>
                        <details class="platform-plan-edit">
                            <summary class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-pen-to-square mr-1" aria-hidden="true"></i>Edit plan</summary>
                            <form method="post" action="{{ route('platform.plans.update', $plan) }}" class="platform-plan-form">@csrf @method('put')
                                <div><label for="plan-name-{{ $plan->id }}">Plan name</label><input id="plan-name-{{ $plan->id }}" name="name" class="form-control" value="{{ $plan->name }}" required></div>
                                <div class="platform-plan-price-fields"><div><label for="plan-monthly-{{ $plan->id }}">Monthly price</label><input id="plan-monthly-{{ $plan->id }}" name="monthly_price" type="number" min="0" step="0.01" class="form-control" value="{{ $plan->monthly_price }}" required></div><div><label for="plan-yearly-{{ $plan->id }}">Annual price</label><input id="plan-yearly-{{ $plan->id }}" name="yearly_price" type="number" min="0" step="0.01" class="form-control" value="{{ $plan->yearly_price }}" required></div></div>
                                <div class="platform-plan-limit-fields"><div><label for="plan-branches-{{ $plan->id }}">Branch limit</label><input id="plan-branches-{{ $plan->id }}" name="max_branches" type="number" min="1" class="form-control" value="{{ $plan->max_branches }}" placeholder="Unlimited"></div><div><label for="plan-users-{{ $plan->id }}">User limit</label><input id="plan-users-{{ $plan->id }}" name="max_users" type="number" min="1" class="form-control" value="{{ $plan->max_users }}" placeholder="Unlimited"></div><div><label for="plan-students-{{ $plan->id }}">Student limit</label><input id="plan-students-{{ $plan->id }}" name="max_students" type="number" min="1" class="form-control" value="{{ $plan->max_students }}" placeholder="Unlimited"></div></div>
                                <div><label for="plan-active-{{ $plan->id }}">Availability</label><select id="plan-active-{{ $plan->id }}" name="is_active" class="form-control" required><option value="1" @selected($plan->is_active)>Active</option><option value="0" @selected(! $plan->is_active)>Inactive</option></select></div>
                                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk mr-1" aria-hidden="true"></i>Save changes</button>
                            </form>
                        </details>
                    </article>
                @empty
                    <div class="admin-card platform-plan-empty">No plans match these filters.</div>
                @endforelse
                <article class="admin-card platform-plan-create">
                    <div class="platform-section-heading"><div><h3><i class="fa-solid fa-plus mr-2" aria-hidden="true"></i>Create a plan</h3><p>Set pricing and optional usage limits.</p></div></div>
                    <form method="post" action="{{ route('platform.plans.store') }}" class="platform-plan-form">@csrf
                        <div><label for="plan-name">Plan name</label><input id="plan-name" name="name" class="form-control" placeholder="e.g. Standard" required></div>
                        <div class="platform-plan-price-fields"><div><label for="plan-monthly">Monthly price</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">₹</span></div><input id="plan-monthly" name="monthly_price" type="number" min="0" step="0.01" class="form-control" required></div></div><div><label for="plan-yearly">Annual price</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">₹</span></div><input id="plan-yearly" name="yearly_price" type="number" min="0" step="0.01" class="form-control" required></div></div></div>
                        <div class="platform-plan-limit-fields"><div><label for="plan-branches">Branch limit</label><input id="plan-branches" name="max_branches" type="number" min="1" class="form-control" placeholder="Unlimited"></div><div><label for="plan-users">User limit</label><input id="plan-users" name="max_users" type="number" min="1" class="form-control" placeholder="Unlimited"></div><div><label for="plan-students">Student limit</label><input id="plan-students" name="max_students" type="number" min="1" class="form-control" placeholder="Unlimited"></div></div>
                        <button class="btn btn-primary" type="submit"><i class="fa-solid fa-plus mr-1" aria-hidden="true"></i>Create subscription plan</button>
                    </form>
                </article>
            </div>
            <div class="platform-pagination"><x-admin.pagination :paginator="$plans" /></div>
        </section>
    </div>
@endsection
