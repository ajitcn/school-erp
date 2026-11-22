@php
    $activeTenant = app(\App\Services\TenantContext::class)->get();
    $isPlatformAdmin = (bool) auth()->user()->is_platform_super_admin;
    $studentPortalAvailable = Route::has('portal.dashboard')
        && (auth()->user()->hasRole(['student', 'parent'])
            || in_array(auth()->user()->tenantMembership()?->role, ['student', 'parent'], true));
    $studentsOpen = request()->routeIs('students.*')
        || request()->routeIs('admissions.*')
        || request()->routeIs('attendance.*')
        || request()->routeIs('services.*')
        || request()->routeIs('portal.*');
    $academicOpen = request()->routeIs('academic.*');
    $financeOpen = request()->routeIs('fees.*')
        || request()->routeIs('expenses.*')
        || request()->routeIs('reconciliation.*')
        || request()->routeIs('reports.financial')
        || request()->routeIs('reports.expenses')
        || request()->routeIs('reports.fees')
        || request()->routeIs('reports.previous-dues')
        || request()->routeIs('payments.*');
    $adminOpen = request()->routeIs('admin.*')
        || request()->routeIs('imports.*')
        || request()->routeIs('approvals.*')
        || request()->routeIs('setup.*');
@endphp

<aside id="admin-sidebar" class="admin-sidebar" aria-label="Primary navigation">
    <div class="admin-brand">
        <a
            href="{{ $isPlatformAdmin ? route('platform.tenants') : route('dashboard') }}"
            aria-label="{{ $isPlatformAdmin ? 'Platform administration' : ($activeTenant?->institution_name ?? 'EduSchool').' dashboard' }}"
        >
            @if (! $isPlatformAdmin && $activeTenant?->logo)
                <img src="{{ asset('storage/'.$activeTenant->logo) }}" alt="{{ $activeTenant->institution_name }} logo" class="admin-logo tenant-brand-logo">
            @else
                <span class="admin-logo" aria-hidden="true">
                    <i class="fa-solid {{ $isPlatformAdmin ? 'fa-layer-group' : 'fa-school' }}"></i>
                </span>
            @endif
            <span>
                <strong>{{ $isPlatformAdmin ? 'Platform Admin' : ($activeTenant?->institution_name ?? 'EduSchool') }}</strong>
                <small>{{ $isPlatformAdmin ? 'Tenant & subscription management' : ($activeTenant?->institution_type === 'college' ? 'College Management' : 'School Management') }}</small>
            </span>
        </a>
        <button type="button" class="btn sidebar-close d-lg-none" data-sidebar-close aria-label="Close navigation">&times;</button>
    </div>

    <nav class="admin-menu" aria-label="Main menu">
        @if ($isPlatformAdmin)
            <div class="admin-menu-label">Platform</div>
            <x-admin.menu-item
                href="{{ route('platform.tenants') }}"
                label="Tenants"
                icon="T"
                :active="request()->routeIs('platform.tenants*')"
            />
            <x-admin.menu-item
                href="{{ route('platform.subscriptions') }}"
                label="Subscriptions & billing"
                icon="$"
                :active="request()->routeIs('platform.subscriptions')"
            />
            <x-admin.menu-item
                href="{{ route('platform.plans') }}"
                label="Subscription plans"
                icon="P"
                :active="request()->routeIs('platform.plans*')"
            />
            <x-admin.menu-item
                href="{{ route('platform.usage') }}"
                label="Tenant usage"
                icon="U"
                :active="request()->routeIs('platform.usage')"
            />
            <x-admin.menu-group
                id="platform-whatsapp-credits-menu"
                label="WhatsApp credits"
                icon="W"
                :open="request()->routeIs('platform.whatsapp-credits*')"
            >
                <x-admin.menu-item
                    href="{{ route('platform.whatsapp-credits') }}"
                    label="Credit packages"
                    icon="P"
                    :active="request()->routeIs('platform.whatsapp-credits')"
                />
                <x-admin.menu-item
                    href="{{ route('platform.whatsapp-credits.tenants') }}"
                    label="Tenant balances"
                    icon="T"
                    :active="request()->routeIs('platform.whatsapp-credits.tenants')"
                />
                <x-admin.menu-item
                    href="{{ route('platform.whatsapp-credits.activity') }}"
                    label="Credit activity"
                    icon="A"
                    :active="request()->routeIs('platform.whatsapp-credits.activity')"
                />
            </x-admin.menu-group>
            <x-admin.menu-item
                href="{{ route('platform.audit') }}"
                label="Platform audit log"
                icon="R"
                :active="request()->routeIs('platform.audit')"
            />
            <x-admin.menu-item
                href="{{ route('platform.guide') }}"
                label="System flow guide"
                icon="F"
                :active="request()->routeIs('platform.guide')"
            />
            <x-admin.menu-item
                href="{{ route('platform.settings') }}"
                label="Platform settings"
                icon="C"
                :active="request()->routeIs('platform.settings*')"
            />
        @else
        <div class="admin-menu-label">Daily work</div>

        <x-admin.menu-item href="{{ route('dashboard') }}" label="Dashboard" icon="D" :active="request()->routeIs('dashboard')" />

        @if (auth()->user()->hasPermission('students.view')
            || auth()->user()->hasPermission('attendance.view')
            || (auth()->user()->hasPermission('services.view') && Route::has('services.index'))
            || $studentPortalAvailable)
            <x-admin.menu-group id="students-menu" label="Student" icon="S" :open="$studentsOpen">
                @if (auth()->user()->hasPermission('students.view'))
                    <x-admin.menu-item href="{{ route('students.index') }}" label="Student list" icon="S" :active="request()->routeIs('students.*') && ! request()->routeIs('students.certificates.*')" />
                    @if (Route::has('students.certificates.index'))
                        <x-admin.menu-item href="{{ route('students.certificates.index') }}" label="Certificates" icon="C" :active="request()->routeIs('students.certificates.*')" />
                    @endif

                    @if (Route::has('students.create'))
                        <x-admin.menu-item href="{{ route('students.create') }}" label="Admissions" icon="A" :active="request()->routeIs('students.create')" />
                        <x-admin.menu-item href="{{ route('admissions.index') }}" label="Admission review" icon="R" :active="request()->routeIs('admissions.*')" />
                    @endif
                @endif

                @if (Route::has('attendance.index') && auth()->user()->hasPermission('attendance.view'))
                    <x-admin.menu-item href="{{ route('attendance.index') }}" label="Attendance" icon="T" :active="request()->routeIs('attendance.index')" />
                @endif

                @if (Route::has('attendance.records') && auth()->user()->hasPermission('attendance.view'))
                    <x-admin.menu-item href="{{ route('attendance.records') }}" label="Attendance records" icon="L" :active="request()->routeIs('attendance.records*')" />
                @endif

                @if ($studentPortalAvailable)
                    <x-admin.menu-item href="{{ route('portal.dashboard') }}" label="Student / Parent portals" icon="P" :active="request()->routeIs('portal.*')" />
                @endif
            </x-admin.menu-group>
        @endif

        @if (auth()->user()->hasPermission('payments.view') || auth()->user()->hasPermission('payments.collect') || auth()->user()->hasPermission('fees.view') || auth()->user()->hasPermission('fees.assign') || auth()->user()->hasPermission('expenses.view') || auth()->user()->hasPermission('reports.view'))
            <x-admin.menu-group id="finance-menu" label="Finance" icon="F" :open="$financeOpen">
                @if (Route::has('fees.structures.index') && auth()->user()->hasPermission('fees.view'))
                    <x-admin.menu-item href="{{ route('fees.structures.index') }}" label="Fee structures" icon="F" :active="request()->routeIs('fees.structures.*') || request()->routeIs('fees.installments.*')" />
                    <x-admin.menu-item href="{{ route('fees.assignments.index') }}" label="Fee Installments List" icon="S" :active="request()->routeIs('fees.assignments.*')" />
                @endif

                @if (auth()->user()->hasPermission('expenses.view'))
                    <x-admin.menu-item href="{{ route('expenses.index') }}" label="Expenses" icon="E" :active="request()->routeIs('expenses.*')" />
                @endif

                @if (auth()->user()->hasPermission('payments.view') || auth()->user()->hasPermission('payments.collect'))
                    <x-admin.menu-item href="{{ route('payments.create') }}" label="Collect payment" icon="P" :active="request()->routeIs('payments.*')" />
                    <x-admin.menu-item href="{{ route('reconciliation.index') }}" label="Reconciliation" icon="C" :active="request()->routeIs('reconciliation.*')" />
                @endif

                @if (auth()->user()->hasPermission('reports.view'))
                    <x-admin.menu-item href="{{ route('reports.financial') }}" label="Financial reports" icon="R" :active="request()->routeIs('reports.financial')" />
                    <x-admin.menu-item href="{{ route('reports.expenses') }}" label="Expense reports" icon="E" :active="request()->routeIs('reports.expenses')" />
                    <x-admin.menu-item href="{{ route('reports.fees') }}" label="Fee defaulters" icon="D" :active="request()->routeIs('reports.fees')" />
                    <x-admin.menu-item href="{{ route('reports.previous-dues') }}" label="Previous-year dues" icon="P" :active="request()->routeIs('reports.previous-dues')" />
                @endif

            </x-admin.menu-group>
        @endif

        @if (auth()->user()->hasPermission('users.manage') || auth()->user()->hasPermission('setup.manage'))
            <x-admin.menu-item href="{{ route('billing.index') }}" label="Subscription & billing" icon="B" :active="request()->routeIs('billing.*')" />
        @endif

        <x-admin.menu-item href="{{ route('notifications.index') }}" label="Notifications" icon="N" :active="request()->routeIs('notifications.*')" />
        <x-admin.menu-item href="{{ route('messages.index') }}" label="Messages" icon="M" :active="request()->routeIs('messages.*')" />
        @if (auth()->user()->isTenantAdmin())
            <x-admin.menu-item href="{{ route('whatsapp-credits.index') }}" label="WhatsApp credits" icon="W" :active="request()->routeIs('whatsapp-credits.*')" />
        @endif

        @if (auth()->user()->hasPermission('academic.view'))
            <x-admin.menu-group id="academic-menu" label="Academic management" icon="A" :open="$academicOpen">
                @foreach (['classes', 'sections', 'departments', 'courses', 'semesters'] as $academicType)
                    <x-admin.menu-item
                        href="{{ route('academic.index', $academicType) }}"
                        :label="ucfirst($academicType)"
                        icon="*"
                        :active="request()->routeIs('academic.*') && request()->route('type') === $academicType"
                    />
                @endforeach
                <x-admin.menu-item href="{{ route('academic.subjects') }}" label="Subjects" icon="S" :active="request()->routeIs('academic.subjects*')" />
                <x-admin.menu-item href="{{ route('academic.teachers') }}" label="Teacher assignments" icon="T" :active="request()->routeIs('academic.teachers*')" />
                <x-admin.menu-item href="{{ route('academic.timetable') }}" label="Timetable" icon="T" :active="request()->routeIs('academic.timetable*')" />
                <x-admin.menu-item href="{{ route('academic.results') }}" label="Results" icon="R" :active="request()->routeIs('academic.results*')" />
                <x-admin.menu-item href="{{ route('academic.examinations') }}" label="Examinations" icon="E" :active="request()->routeIs('academic.examinations*')" />
            </x-admin.menu-group>
        @endif

        @if (auth()->user()->hasPermission('users.view') || auth()->user()->hasPermission('roles.view') || auth()->user()->hasPermission('workflow.view') || auth()->user()->hasPermission('notices.view') || auth()->user()->hasPermission('notifications.send') || auth()->user()->hasPermission('students.import') || auth()->user()->hasPermission('integrations.view') || auth()->user()->hasPermission('setup.view'))
            <x-admin.menu-group id="administration-menu" label="Administration" icon="U" :open="$adminOpen">
                <x-admin.menu-item href="{{ route('admin.users.index') }}" label="Users" icon="U" :active="request()->routeIs('admin.users.*')" />
                <x-admin.menu-item href="{{ route('admin.invitations') }}" label="Invitations" icon="I" :active="request()->routeIs('admin.invitations')" />
                <x-admin.menu-item href="{{ route('admin.roles.index') }}" label="Roles & permissions" icon="R" :active="request()->routeIs('admin.roles.*')" />
                <x-admin.menu-item href="{{ route('approvals.index') }}" label="Approval queue" icon="A" :active="request()->routeIs('approvals.*')" />
                <x-admin.menu-item href="{{ route('notices.index') }}" label="Announcements" icon="Q" :active="request()->routeIs('notices.*')" />
                <x-admin.menu-item href="{{ route('admin.notifications.send') }}" label="Send notification" icon="N" :active="request()->routeIs('admin.notifications.send*')" />
                @if (Route::has('imports.students'))
                    <x-admin.menu-item href="{{ route('imports.students') }}" label="Imports" icon="I" :active="request()->routeIs('imports.*')" />
                @endif
                <x-admin.menu-item href="{{ route('admin.notification-templates') }}" label="Notification templates" icon="N" :active="request()->routeIs('admin.notification-templates*')" />
                <span class="admin-menu-item is-disabled" role="link" aria-disabled="true" aria-label="Biometric devices, disabled" title="Biometric devices is currently disabled">
                    <span class="admin-menu-icon" aria-hidden="true"><i class="fa-solid fa-fingerprint"></i></span>
                    <span>Biometric devices</span>
                    <span class="admin-menu-disabled-label">Disabled</span>
                </span>
                @if (Route::has('setup.index'))
                    <x-admin.menu-item
                        href="{{ route('setup.index', ['section' => 'campuses']) }}#campus-setup"
                        label="Institution setup"
                        icon="S"
                        :active="request()->routeIs('setup.*')"
                    />
                @endif
            </x-admin.menu-group>
        @endif

        @endif
    </nav>

</aside>

<div class="sidebar-backdrop d-none" data-sidebar-overlay></div>
