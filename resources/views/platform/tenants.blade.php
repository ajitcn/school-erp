@extends('layouts.app')

@section('content')
    <div class="platform-tenants-page">
        <x-admin.page-header title="Tenants" description="Review institution registrations and manage tenant access." />

        <section class="platform-summary" aria-label="Tenant overview">
            <x-admin.stat-card label="All tenants" :value="number_format($summary['tenants'])" detail="Registered institutions" icon="fa-building" />
            <x-admin.stat-card label="Awaiting review" :value="number_format($summary['pending_registrations'])" detail="Registration applications" tone="warning" icon="fa-clipboard-check" />
            <x-admin.stat-card label="Active" :value="number_format($summary['active'])" detail="Approved and enabled" tone="success" icon="fa-circle-check" />
            <x-admin.stat-card label="In trial" :value="number_format($summary['trials'])" detail="Approved trial accounts" tone="info" icon="fa-hourglass-half" />
        </section>

        @if ($errors->has('registration'))
            <div class="alert alert-danger mb-0" role="alert">{{ $errors->first('registration') }}</div>
        @endif

        <section class="admin-card platform-section" aria-labelledby="tenant-list-title">
            <div class="platform-section-heading">
                <div><h2 id="tenant-list-title">Institution directory</h2><p>Search organizations, review registrations, and update access status.</p></div>
                <span class="platform-count">{{ number_format($tenants->total()) }} results</span>
            </div>
            <form method="get" class="platform-filters js-filter-form" aria-label="Filter tenants">
                <input class="form-control" type="search" name="q" value="{{ request('q') }}" placeholder="Search institution or email">
                <select class="form-control" name="registration_status" aria-label="Registration status">
                    <option value="">All registrations</option>
                    @foreach (['pending' => 'Awaiting review', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                        <option value="{{ $value }}" @selected(request('registration_status') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select class="form-control" name="status" aria-label="Tenant status">
                    <option value="">All access statuses</option>
                    @foreach (['trial', 'active', 'suspended', 'cancelled'] as $value)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ ucfirst($value) }}</option>
                    @endforeach
                </select>
                <select class="form-control" name="institution_type" aria-label="Institution type">
                    <option value="">All types</option><option value="school" @selected(request('institution_type') === 'school')>School</option><option value="college" @selected(request('institution_type') === 'college')>College</option>
                </select>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>Filter</button>
                @if (request()->hasAny(['q', 'registration_status', 'status', 'institution_type']))<a class="btn btn-outline-secondary" href="{{ route('platform.tenants') }}">Clear</a>@endif
            </form>
            <div class="table-responsive platform-table-wrap">
                <table class="table platform-tenant-table mb-0">
                    <thead><tr><th>Institution</th><th>Registration</th><th>Plan</th><th>Access</th><th>Change access</th></tr></thead>
                    <tbody>
                        @forelse ($tenants as $tenant)
                            @php
                                $tone = match ($tenant->status) { 'active' => 'success', 'trial' => 'info', 'suspended' => 'warning', default => 'secondary' };
                            @endphp
                            <tr>
                                <td><div class="platform-institution">
                                    @if ($tenant->logo)<img src="{{ asset('storage/'.$tenant->logo) }}" alt="" class="platform-institution-logo">@else<span class="platform-institution-logo platform-institution-placeholder"><i class="fa-solid fa-building" aria-hidden="true"></i></span>@endif
                                    <span class="platform-institution-copy"><strong><a href="{{ route('platform.tenants.show', $tenant) }}">{{ $tenant->institution_name }}</a></strong><small>{{ $tenant->email }}</small><small>{{ ucfirst($tenant->institution_type) }} · Joined {{ $tenant->created_at->format('d M Y') }}</small><a class="platform-detail-link" href="{{ route('platform.tenants.show', $tenant) }}">View tenant details <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a></span>
                                </div></td>
                                <td>
                                    @if ($tenant->registration_status === 'pending')
                                        <div class="platform-review-actions"><span class="platform-status-badge is-warning">Awaiting review</span><div>
                                            <form method="post" action="{{ route('platform.tenants.registration-review', $tenant) }}">@csrf<input type="hidden" name="decision" value="approve"><button class="btn btn-sm btn-success" type="submit">Approve</button></form>
                                            <form method="post" action="{{ route('platform.tenants.registration-review', $tenant) }}">@csrf<input type="hidden" name="decision" value="reject"><button class="btn btn-sm btn-outline-danger" type="submit">Reject</button></form>
                                        </div></div>
                                    @else
                                        <span class="platform-status-badge is-{{ $tenant->registration_status === 'approved' ? 'success' : 'secondary' }}">{{ ucfirst($tenant->registration_status) }}</span>
                                        @if ($tenant->registration_reviewed_at)<small class="platform-review-date">{{ $tenant->registration_reviewed_at->format('d M Y') }}</small>@endif
                                    @endif
                                </td>
                                <td><strong class="platform-plan-name">{{ $tenant->plan?->name ?? 'No plan assigned' }}</strong></td>
                                <td><span class="platform-status-badge is-{{ $tone }}">{{ ucfirst($tenant->status) }}</span></td>
                                <td><form method="post" action="{{ route('platform.tenants.status', $tenant) }}" class="platform-status-form">@csrf
                                    <label class="sr-only" for="tenant-status-{{ $tenant->id }}">Status for {{ $tenant->institution_name }}</label>
                                    <select id="tenant-status-{{ $tenant->id }}" class="form-control form-control-sm" name="status"><option value="trial" @selected($tenant->status === 'trial')>Trial</option><option value="active" @selected($tenant->status === 'active')>Active</option><option value="suspended" @selected($tenant->status === 'suspended')>Suspended</option><option value="cancelled" @selected($tenant->status === 'cancelled')>Cancelled</option></select>
                                    <button class="btn btn-sm btn-outline-primary" type="submit">Save</button>
                                </form></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-admin.empty-state title="No tenants found" description="Try changing the search or filters." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="platform-pagination"><x-admin.pagination :paginator="$tenants" /></div>
        </section>
    </div>
@endsection
