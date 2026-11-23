@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Notifications"
        description="Review account alerts and delivery updates in one place."
    />

    <div class="row mb-4 notification-summary-grid">
        <div class="col-md-4 mb-3 mb-md-0">
            <x-admin.stat-card label="All notifications" :value="number_format($totalCount)" detail="Alerts for your account" icon="fa-bell" />
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <x-admin.stat-card label="Unread" :value="number_format($unreadCount)" detail="Waiting for your review" tone="info" icon="fa-envelope-open" />
        </div>
        <div class="col-md-4">
            <x-admin.stat-card label="Delivery issues" :value="number_format($failedCount)" detail="Notifications that failed to send" tone="danger" icon="fa-circle-exclamation" />
        </div>
    </div>

    <x-admin.card padding="false">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <h2 class="h5 mb-1">Your notifications</h2>
                <p class="text-muted small mb-0">Unread items stay here until you mark them as read.</p>
            </div>
            @if ($unreadCount > 0)
                <form method="post" action="{{ route('notifications.read-all') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-primary" type="submit">Mark all as read</button>
                </form>
            @endif
        </div>

        <div class="px-3 pt-3">
            <details class="mb-3">
                <summary class="btn btn-sm btn-outline-secondary d-inline-flex align-items-center">Filter notifications</summary>
                <form method="get" class="row align-items-end mt-3">
                    <div class="col-sm-5 col-md-3">
                        <label for="notification-status" class="small font-weight-bold">Read status</label>
                        <select id="notification-status" name="status" class="form-control form-control-sm">
                            <option value="all" @selected($filter === 'all')>All notifications</option>
                            <option value="unread" @selected($filter === 'unread')>Unread</option>
                            <option value="read" @selected($filter === 'read')>Read</option>
                        </select>
                    </div>
                    <div class="col-auto mt-3 mt-sm-0">
                        <button class="btn btn-sm btn-primary" type="submit">Apply</button>
                        <a class="btn btn-sm btn-link" href="{{ route('notifications.index') }}">Clear</a>
                    </div>
                </form>
            </details>
        </div>

        <div class="table-responsive">
            <x-admin.table>
                <thead>
                    <tr>
                        <th>Notification</th>
                        <th>Channel</th>
                        <th>Delivery</th>
                        <th>Received</th>
                        <th class="text-right">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notifications as $notification)
                        <tr class="{{ $notification->read_at ? '' : 'font-weight-bold' }}">
                            <td>
                                <span class="d-block">{{ data_get($notification->payload, 'subject', str($notification->event)->replace('.', ' ')->title()) }}</span>
                                <small class="d-block text-muted font-weight-normal">{{ data_get($notification->payload, 'message', 'There is an update for your account.') }}</small>
                                @if ($notification->error)
                                    <small class="d-block text-danger font-weight-normal">{{ $notification->error }}</small>
                                @endif
                            </td>
                            <td>{{ ucfirst($notification->channel) }}</td>
                            <td>
                                <x-admin.badge :tone="$notification->status === 'sent' ? 'success' : ($notification->status === 'failed' ? 'danger' : 'warning')">
                                    {{ ucfirst($notification->status) }}
                                </x-admin.badge>
                            </td>
                            <td>{{ $notification->created_at?->format('d M Y, h:i A') }}</td>
                            <td class="text-right">
                                @if (! $notification->read_at)
                                    <form method="post" action="{{ route('notifications.read', $notification) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary" type="submit">Mark read</button>
                                    </form>
                                @else
                                    <span class="text-muted small">Read</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4">
                                <x-admin.empty-state title="No notifications found" description="New account alerts and delivery updates will appear here." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-admin.table>
        </div>

        <div class="p-3">
            <x-admin.pagination :paginator="$notifications" />
        </div>
    </x-admin.card>
@endsection
