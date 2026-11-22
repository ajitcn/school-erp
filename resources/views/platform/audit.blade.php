@extends('layouts.app')

@section('content')
    <div class="platform-tenants-page">
        <x-admin.page-header title="Platform audit log" description="Review who changed tenant access, registrations, subscriptions, and plans." />

        <section class="platform-summary" aria-label="Audit activity overview">
            <x-admin.stat-card label="Platform actions" :value="number_format($summary['actions'])" detail="Recorded administrative events" icon="fa-shield-halved" />
            <x-admin.stat-card label="Today" :value="number_format($summary['today'])" detail="Actions recorded today" tone="info" icon="fa-calendar-day" />
            <x-admin.stat-card label="Platform admins" :value="number_format($summary['actors'])" detail="Admins with logged actions" tone="success" icon="fa-user-shield" />
            <x-admin.stat-card label="Tenant options" :value="number_format($tenants->count())" detail="Available tenant filters" tone="warning" icon="fa-building" />
        </section>

        <section class="admin-card platform-section" aria-labelledby="audit-log-title">
            <div class="platform-section-heading"><div><h2 id="audit-log-title">Administrative activity</h2><p>Filter by action, tenant, administrator, or date range.</p></div><span class="platform-count">{{ number_format($logs->total()) }} results</span></div>
            <form method="get" class="platform-filters platform-audit-filters js-filter-form" aria-label="Filter platform audit log">
                <select class="form-control" name="action" aria-label="Platform action"><option value="">All actions</option>@foreach($actions as $action)<option value="{{ $action }}" @selected(request('action') === $action)>{{ str($action)->after('platform.')->replace('.', ' / ')->headline() }}</option>@endforeach</select>
                <select class="form-control" name="tenant_id" aria-label="Tenant"><option value="">All tenants</option>@foreach($tenants as $tenant)<option value="{{ $tenant->id }}" @selected(request('tenant_id') == $tenant->id)>{{ $tenant->institution_name }}</option>@endforeach</select>
                <select class="form-control" name="actor_id" aria-label="Platform administrator"><option value="">All administrators</option>@foreach($actors as $actor)<option value="{{ $actor->id }}" @selected(request('actor_id') == $actor->id)>{{ $actor->name }}</option>@endforeach</select>
                <div class="platform-audit-date"><label for="audit-from-date">From</label><input id="audit-from-date" class="form-control js-datepicker" type="text" name="from_date" value="{{ request('from_date') }}" autocomplete="off"></div>
                <div class="platform-audit-date"><label for="audit-to-date">To</label><input id="audit-to-date" class="form-control js-datepicker" type="text" name="to_date" value="{{ request('to_date') }}" autocomplete="off"></div>
                <button class="btn btn-primary" type="submit"><i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>Filter</button>
                @if(request()->hasAny(['action', 'tenant_id', 'actor_id', 'from_date', 'to_date']))<a class="btn btn-outline-secondary" href="{{ route('platform.audit') }}">Clear</a>@endif
            </form>

            <div class="table-responsive platform-table-wrap"><table class="table platform-tenant-table mb-0"><thead><tr><th>When</th><th>Action</th><th>Administrator</th><th>Tenant / target</th><th>Changes</th></tr></thead><tbody>
                @forelse($logs as $log)
                    <tr>
                        <td>{{ $log->created_at->format('d M Y') }}<small class="platform-review-date">{{ $log->created_at->format('H:i:s') }}</small></td>
                        <td><span class="platform-status-badge is-info">{{ str($log->action)->after('platform.')->replace('.', ' / ')->headline() }}</span></td>
                        <td><strong>{{ $log->user?->name ?? 'Account removed' }}</strong><small class="platform-review-date">{{ $log->ip_address ?: 'IP unavailable' }}</small></td>
                        <td>@if($log->tenant)<a href="{{ route('platform.tenants.show', $log->tenant) }}">{{ $log->tenant->institution_name }}</a>@else<span class="text-muted">Platform wide</span>@endif<small class="platform-review-date">{{ $log->auditable ? class_basename($log->auditable).' #'.$log->auditable->getKey() : 'Target unavailable' }}</small></td>
                        <td><details class="platform-audit-changes"><summary>View changes</summary><div class="platform-audit-change-columns"><div><strong>Before</strong><pre>{{ json_encode($log->old_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div><div><strong>After</strong><pre>{{ json_encode($log->new_values ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></div></div></details></td>
                    </tr>
                @empty<tr><td colspan="5"><x-admin.empty-state title="No platform activity found" description="Actions will appear here as platform administrators manage tenants, plans, and renewals." /></td></tr>@endforelse
            </tbody></table></div>
            <div class="platform-pagination"><x-admin.pagination :paginator="$logs" /></div>
        </section>
    </div>
@endsection
