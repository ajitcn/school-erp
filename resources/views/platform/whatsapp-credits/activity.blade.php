@extends('layouts.app')

@section('content')
    <div class="platform-whatsapp-page">
        <x-admin.page-header
            title="WhatsApp credit activity"
            description="Track credit purchases, message charges, and refunds across all tenants."
        />

        @include('platform.whatsapp-credits._navigation')
        @include('platform.whatsapp-credits._summary')

        <section class="admin-card platform-section wa-credit-section" aria-labelledby="wa-activity-title">
            <div class="platform-section-heading wa-credit-section-heading">
                <div>
                    <h2 id="wa-activity-title">Credit activity</h2>
                    <p>A ledger entry is recorded for each purchase, message use, and delivery refund.</p>
                </div>
                <span class="platform-count">{{ number_format($ledger->total()) }} entries</span>
            </div>

            <details class="wa-activity-filter {{ $tenantFilter || $typeFilter ? 'is-active' : '' }}" @if ($tenantFilter || $typeFilter) open @endif>
                <summary class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>
                    {{ $tenantFilter || $typeFilter ? 'Filters applied' : 'Filter activity' }}
                </summary>
                <form method="get" class="wa-activity-filter-form" aria-label="Filter credit activity">
                    <div class="form-group">
                        <label for="activity-tenant">Tenant</label>
                        <select id="activity-tenant" class="form-control" name="tenant_id">
                            <option value="">All tenants</option>
                            @foreach ($tenantOptions as $tenantOption)
                                <option value="{{ $tenantOption->id }}" @selected($tenantFilter === $tenantOption->id)>{{ $tenantOption->institution_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="activity-type">Activity type</label>
                        <select id="activity-type" class="form-control" name="type">
                            <option value="">All activity</option>
                            <option value="purchase" @selected($typeFilter === 'purchase')>Purchases</option>
                            <option value="usage" @selected($typeFilter === 'usage')>Message usage</option>
                            <option value="refund" @selected($typeFilter === 'refund')>Refunds</option>
                        </select>
                    </div>
                    <div class="wa-filter-actions">
                        <button class="btn btn-primary" type="submit">Apply filters</button>
                        @if ($tenantFilter || $typeFilter)
                            <a class="btn btn-outline-secondary" href="{{ route('platform.whatsapp-credits.activity') }}">Clear</a>
                        @endif
                    </div>
                </form>
            </details>

            <div class="table-responsive platform-table-wrap">
                <table class="table wa-ledger-table mb-0">
                    <thead>
                        <tr>
                            <th>Date and time</th>
                            <th>Tenant</th>
                            <th>Activity</th>
                            <th>Details</th>
                            <th class="text-right">Credits</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ledger as $entry)
                            <tr>
                                <td>{{ $entry->created_at->format('d M Y') }}<small>{{ $entry->created_at->format('h:i A') }}</small></td>
                                <td>{{ $entry->tenant?->institution_name ?? 'Tenant removed' }}</td>
                                <td>
                                    <span class="wa-activity-type is-{{ $entry->type }}">
                                        {{ ucfirst($entry->type) }}
                                    </span>
                                </td>
                                <td>
                                    {{ $entry->description }}
                                    @if ($entry->notificationLog)
                                        <small>{{ $entry->notificationLog->event }} · {{ ucfirst($entry->notificationLog->status) }}</small>
                                    @endif
                                </td>
                                <td class="text-right">
                                    <span class="wa-credit-change {{ $entry->credits < 0 ? 'is-negative' : 'is-positive' }}">
                                        {{ $entry->credits > 0 ? '+' : '' }}{{ number_format($entry->credits) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5"><div class="wa-table-empty">No credit activity matches these filters.</div></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="platform-pagination"><x-admin.pagination :paginator="$ledger" /></div>
        </section>
    </div>
@endsection
