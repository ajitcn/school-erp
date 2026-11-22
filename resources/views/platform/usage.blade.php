@extends('layouts.app')

@section('content')
    <div class="platform-tenants-page">
        <x-admin.page-header title="Tenant usage" description="Monitor student, user, and branch capacity against each tenant’s enforced plan limits." />

        <section class="platform-summary" aria-label="Platform resource totals">
            <x-admin.stat-card label="Tenants" :value="number_format($summary['tenants'])" detail="Organizations monitored" icon="fa-building" />
            <x-admin.stat-card label="Branches" :value="number_format($summary['branches'])" detail="Across all tenants" tone="success" icon="fa-code-branch" />
            <x-admin.stat-card label="Users" :value="number_format($summary['users'])" detail="Tenant memberships" tone="info" icon="fa-users" />
            <x-admin.stat-card label="Students" :value="number_format($summary['students'])" detail="Across all institutions" tone="warning" icon="fa-user-graduate" />
        </section>

        <section class="admin-card platform-section" aria-labelledby="tenant-usage-title">
            <div class="platform-section-heading"><div><h2 id="tenant-usage-title">Capacity by tenant</h2><p>Review current usage and spot tenants approaching or exceeding a limit.</p></div><span class="platform-count">{{ number_format($tenants->total()) }} results</span></div>
            <form method="get" class="platform-filters js-filter-form" aria-label="Filter tenant usage">
                <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Search institution">
                <select class="form-control" name="plan_id" aria-label="Subscription plan"><option value="">All plans</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" @selected(request('plan_id') == $plan->id)>{{ $plan->name }}</option>@endforeach</select>
                <select class="form-control" name="institution_type" aria-label="Institution type"><option value="">All institution types</option><option value="school" @selected(request('institution_type') === 'school')>School</option><option value="college" @selected(request('institution_type') === 'college')>College</option></select>
                <select class="form-control" name="status" aria-label="Tenant status"><option value="">All access states</option>@foreach(['trial', 'active', 'suspended', 'cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>Filter</button>
                @if(request()->hasAny(['q', 'plan_id', 'institution_type', 'status']))<a class="btn btn-outline-secondary" href="{{ route('platform.usage') }}">Clear</a>@endif
            </form>

            <div class="table-responsive platform-table-wrap"><table class="table platform-usage-table mb-0"><thead><tr><th>Tenant</th><th>Plan</th><th>Branches</th><th>Users</th><th>Students</th><th>Access</th></tr></thead><tbody>
                @forelse($tenants as $tenant)
                    <tr>
                        <td><a href="{{ route('platform.tenants.show', $tenant) }}" class="platform-usage-tenant"><strong>{{ $tenant->institution_name }}</strong><small>{{ ucfirst($tenant->institution_type) }} · {{ $tenant->email }}</small></a></td>
                        <td>{{ $tenant->plan?->name ?? 'No plan' }}</td>
                        @foreach(['branches' => $tenant->branches_count, 'users' => $tenant->users_count, 'students' => $tenant->students_count] as $resource => $used)
                            @php($limit = data_get($tenant->subscription_limits, 'max_'.$resource) ?? $tenant->plan?->{'max_'.$resource})
                            @php($percentage = $limit === null ? null : ($limit > 0 ? min((int) round(($used / $limit) * 100), 100) : ($used > 0 ? 100 : 0)))
                            @php($overLimit = $limit !== null && $used > $limit)
                            @php($nearLimit = $limit !== null && ! $overLimit && $percentage >= 80)
                            <td><div class="platform-usage-cell"><div><strong>{{ number_format($used) }}</strong><span>{{ $limit === null ? 'Unlimited' : 'of '.number_format($limit) }}</span></div>@if($limit !== null)<div class="platform-progress-track"><span class="{{ $overLimit ? 'is-over' : ($nearLimit ? 'is-near' : '') }}" style="width: {{ $percentage }}%"></span></div>@endif</div></td>
                        @endforeach
                        <td><span class="platform-status-badge {{ $tenant->status === 'active' ? 'is-success' : ($tenant->status === 'trial' ? 'is-info' : 'is-secondary') }}">{{ ucfirst($tenant->status) }}</span></td>
                    </tr>
                @empty<tr><td colspan="6"><x-admin.empty-state title="No tenants match" description="Try adjusting your filters." /></td></tr>@endforelse
            </tbody></table></div>
            <div class="platform-pagination"><x-admin.pagination :paginator="$tenants" /></div>
        </section>
    </div>
@endsection
