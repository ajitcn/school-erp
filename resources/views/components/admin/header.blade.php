@props(['title' => null])

@php
    $tenantContext = app(App\Services\TenantContext::class);
    $activeTenant = $tenantContext->get();
    $user = auth()->user();
    $isPlatformAdmin = (bool) $user->is_platform_super_admin;
    $tenants = $isPlatformAdmin
        ? collect()
        : $user->tenants()
            ->wherePivot('is_active', true)
            ->where('tenants.registration_status', 'approved')
            ->get();
    $userInitial = strtoupper(substr($user->name, 0, 1));
@endphp

<header class="admin-navbar" aria-label="Application toolbar">
    <div class="admin-navbar-start">
        <button
            type="button"
            class="btn sidebar-toggle"
            data-sidebar-open
            aria-expanded="true"
            aria-controls="admin-sidebar"
            aria-label="Collapse sidebar"
            title="Toggle sidebar"
        >
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>

        <label class="admin-search" for="admin-menu-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input
                id="admin-menu-search"
                type="search"
                data-admin-menu-search
                placeholder="Search..."
                autocomplete="off"
                aria-label="Search navigation"
            >
            <kbd>/</kbd>
        </label>
    </div>

    <div class="admin-navbar-actions">
        @if ($isPlatformAdmin)
            <div class="platform-context-chip" aria-label="Platform administration">
                <span class="platform-context-icon" aria-hidden="true">
                    <i class="fa-solid fa-layer-group"></i>
                </span>
                <span>Platform administration</span>
            </div>
        @else
        <div class="dropdown tenant-navbar" data-dropdown>
            <button
                class="btn tenant-navbar-toggle"
                type="button"
                data-dropdown-toggle
                aria-expanded="false"
                aria-label="Active tenant"
                title="Active tenant"
            >
                <span class="tenant-navbar-icon" aria-hidden="true">
                    @if ($activeTenant?->logo)
                        <img src="{{ asset('storage/'.$activeTenant->logo) }}" alt="" class="tenant-navbar-logo">
                    @else
                        <i class="fa-solid fa-building"></i>
                    @endif
                </span>
                @if ($activeTenant)
                    <span class="tenant-navbar-copy"><strong>{{ $activeTenant->institution_name }}</strong><small>{{ ucfirst($activeTenant->institution_type) }}</small></span>
                @endif
                <i class="fa-solid fa-chevron-down tenant-navbar-chevron" aria-hidden="true"></i>
            </button>

            <div class="dropdown-menu dropdown-menu-right tenant-navbar-menu shadow" data-dropdown-panel hidden>
                <h6 class="dropdown-header">Active tenant</h6>

                @if ($activeTenant)
                    <div class="tenant-navbar-current">
                        <span class="tenant-navbar-current-identity">
                            @if ($activeTenant->logo)
                                <img src="{{ asset('storage/'.$activeTenant->logo) }}" alt="" class="tenant-navbar-current-logo">
                            @else
                                <span class="tenant-navbar-current-logo tenant-navbar-current-placeholder" aria-hidden="true"><i class="fa-solid fa-building"></i></span>
                            @endif
                            <strong>{{ $activeTenant->institution_name }}</strong>
                        </span>
                        <x-admin.badge :tone="$activeTenant->status === 'active' ? 'success' : 'warning'">
                            {{ ucfirst($activeTenant->status) }}
                        </x-admin.badge>
                    </div>
                @endif

                @if ($tenants->count() > 1)
                    <form method="post" action="{{ route('tenant.switch') }}" class="tenant-switch-form">
                        @csrf
                        <label for="navbar-tenant-switcher" class="small text-muted mb-1">Switch organization</label>

                        @if ($tenants->count() > 5)
                            <input
                                type="search"
                                class="form-control form-control-sm mb-2"
                                data-tenant-search
                                placeholder="Search tenants..."
                                aria-label="Search tenants"
                            >
                        @endif

                        <select
                            id="navbar-tenant-switcher"
                            name="tenant_id"
                            class="form-control form-control-sm js-tenant-switcher"
                            onchange="this.form.submit()"
                        >
                            @foreach ($tenants as $tenant)
                                <option value="{{ $tenant->id }}" @selected($tenant->id === $tenantContext->id())>
                                    {{ $tenant->institution_name }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                @else
                    <p class="dropdown-item-text small text-muted mb-0">You belong to one organization.</p>
                @endif
            </div>
        </div>
        @endif

        @unless ($isPlatformAdmin)
        <div class="dropdown" data-dropdown>
            <button
                class="btn nav-action position-relative"
                type="button"
                data-dropdown-toggle
                aria-expanded="false"
                aria-label="Notifications"
                title="Notifications"
            >
                <i class="fa-regular fa-bell" aria-hidden="true"></i>
                @if ($unreadNotificationCount > 0)
                    <span class="notification-dot" aria-label="{{ $unreadNotificationCount }} unread notifications">{{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}</span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-right admin-popover shadow" data-dropdown-panel hidden>
                <div class="d-flex align-items-center justify-content-between px-3 py-2">
                    <h6 class="mb-0">Notifications</h6>
                    <span class="text-muted small">{{ $unreadNotificationCount }} unread</span>
                </div>
                @forelse ($notifications as $notification)
                    <a class="dropdown-item text-wrap {{ $notification->read_at ? 'text-muted' : 'font-weight-bold' }}" href="{{ route('notifications.index') }}">
                        <span class="d-block">{{ data_get($notification->payload, 'subject', str($notification->event)->replace('.', ' ')->title()) }}</span>
                        <small>{{ data_get($notification->payload, 'message', ucfirst($notification->status)) }}</small>
                    </a>
                @empty
                    <p class="dropdown-item-text text-muted small mb-0">You're all caught up.</p>
                @endforelse
                <div class="border-top mt-2 pt-2 px-3 pb-1">
                    <a class="small" href="{{ route('notifications.index') }}">View all notifications</a>
                </div>
            </div>
        </div>

        <div class="dropdown" data-dropdown>
            <button
                class="btn nav-action position-relative"
                type="button"
                data-dropdown-toggle
                aria-expanded="false"
                aria-label="Messages"
                title="Messages"
            >
                <i class="fa-regular fa-message" aria-hidden="true"></i>
                @if ($unreadMessageCount > 0)
                    <span class="notification-dot" aria-label="{{ $unreadMessageCount }} unread messages">{{ $unreadMessageCount > 9 ? '9+' : $unreadMessageCount }}</span>
                @endif
            </button>
            <div class="dropdown-menu dropdown-menu-right admin-popover shadow" data-dropdown-panel hidden>
                <div class="d-flex align-items-center justify-content-between px-3 py-2">
                    <h6 class="mb-0">Messages</h6>
                    <span class="text-muted small">{{ $unreadMessageCount }} unread</span>
                </div>
                @forelse ($messages as $message)
                    <a class="dropdown-item text-wrap" href="{{ route('messages.index') }}">
                        <span class="d-block font-weight-bold">{{ $message->title }}</span>
                        <small>{{ \Illuminate\Support\Str::limit($message->body, 90) }}</small>
                    </a>
                @empty
                    <p class="dropdown-item-text text-muted small mb-0">No unread messages.</p>
                @endforelse
                <div class="border-top mt-2 pt-2 px-3 pb-1">
                    <a class="small" href="{{ route('messages.index') }}">View all messages</a>
                </div>
            </div>
        </div>
        @endunless

        <button
            class="btn nav-action"
            type="button"
            data-color-mode-toggle
            aria-label="Switch to dark mode"
            title="Switch color mode"
        >
            <i class="fa-regular fa-moon" aria-hidden="true"></i>
        </button>

        <button
            class="btn nav-action fullscreen-toggle"
            type="button"
            data-fullscreen-toggle
            aria-label="Enter fullscreen"
            title="Toggle fullscreen"
        >
            <i class="fa-solid fa-expand" aria-hidden="true"></i>
        </button>

        <div class="dropdown user-navbar" data-dropdown>
            <button class="btn user-trigger" type="button" data-dropdown-toggle aria-expanded="false">
                <span class="admin-avatar" aria-hidden="true">{{ $userInitial }}</span>
                <span class="user-trigger-copy">
                    <strong>{{ $user->name }}</strong>
                    <small>{{ $user->email }}</small>
                </span>
                <i class="fa-solid fa-chevron-down user-trigger-chevron" aria-hidden="true"></i>
            </button>
            <div class="dropdown-menu dropdown-menu-right shadow" data-dropdown-panel hidden>
                <h6 class="dropdown-header">Signed in as</h6>
                <span class="dropdown-item-text small text-truncate">{{ $user->email }}</span>
                <a class="dropdown-item" href="{{ route('account.password.edit') }}">
                    <i class="fa-solid fa-key mr-2" aria-hidden="true"></i>
                    Change password
                </a>
                <div class="dropdown-divider"></div>
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="dropdown-item text-danger" type="submit">
                        <i class="fa-solid fa-arrow-right-from-bracket mr-2" aria-hidden="true"></i>
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
</header>
