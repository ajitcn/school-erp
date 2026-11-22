<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#193b68">
    <title>{{ $title ?? 'Portal' }} · {{ app(\App\Services\TenantContext::class)->get()?->institution_name ?? 'School ERP' }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin-kit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin-shell.css?v=8') }}">
    <link rel="stylesheet" href="{{ asset('css/application-ui.css?v=95') }}">
    <link rel="stylesheet" href="{{ asset('css/portal.css?v=6') }}">
</head>
<body class="portal-body">
    @php
        $portalAccount = request()->user();
        $portalTenant = app(\App\Services\TenantContext::class)->get();
        $portalChildren = $portalChildren ?? collect();
        $currentPortalStudentId = $selectedPortalStudentId ?? (isset($student) ? $student->id : null);
        $portalAccountName = $portalAccount instanceof \App\Models\User
            ? $portalAccount->name
            : trim(($portalAccount->first_name ?? '').' '.($portalAccount->last_name ?? ''));
        $portalAccountRole = $portalAccount instanceof \App\Models\Guardian
            || ($portalAccount instanceof \App\Models\User
                && ($portalAccount->hasRole('parent') || $portalAccount->tenantMembership()?->role === 'parent'))
            ? 'Parent portal'
            : 'Student portal';
    @endphp

    <header class="portal-header">
        <button
            class="portal-menu-toggle"
            type="button"
            aria-label="Open navigation menu"
            aria-controls="portal-navigation"
            aria-expanded="false"
            data-portal-menu-toggle
        >
            <i class="fa-solid fa-bars" aria-hidden="true"></i>
        </button>

        <a class="portal-brand" href="{{ route('portal.dashboard') }}" aria-label="{{ $portalTenant?->institution_name ?? 'School ERP' }} portal home">
            @if ($portalTenant?->logo)
                <img class="portal-brand-logo" src="{{ asset('storage/'.$portalTenant->logo) }}" alt="{{ $portalTenant->institution_name }} logo">
            @else
                <span class="portal-brand-mark" aria-hidden="true"><i class="fa-solid fa-school"></i></span>
            @endif
            <span class="portal-brand-copy">
                <strong>{{ $portalTenant?->institution_name ?? 'School ERP' }}</strong>
                <small>{{ ucfirst($portalTenant?->institution_type ?? 'education') }} portal</small>
            </span>
        </a>

        @if ($portalChildren->count() > 1)
            <form class="portal-child-switcher" method="post" action="{{ route('portal.children.select') }}">
                @csrf
                <label for="portal-child-select">Viewing student</label>
                <select
                    id="portal-child-select"
                    name="student_id"
                    class="form-control"
                    onchange="this.form.submit()"
                    aria-label="Choose a student to view"
                >
                    <option value="" @selected(! $currentPortalStudentId)>All students</option>
                    @foreach ($portalChildren as $portalChild)
                        <option value="{{ $portalChild->id }}" @selected((int) $currentPortalStudentId === (int) $portalChild->id)>
                            {{ $portalChild->first_name }} {{ $portalChild->last_name }} · {{ $portalChild->student_id }}
                        </option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-primary btn-sm" type="submit">View student</button></noscript>
            </form>
        @endif

        <div class="portal-user-tools">
            <span class="portal-user-copy">
                <strong>{{ $portalAccountName }}</strong>
                <small>{{ $portalAccountRole }}</small>
            </span>
            <span class="portal-user-avatar" aria-hidden="true">
                {{ strtoupper(substr($portalAccountName ?: 'P', 0, 1)) }}
            </span>
            <form method="post" action="{{ route('portal.logout') }}">
                @csrf
                <button class="btn portal-signout-button" type="submit">
                    <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
                    <span>Sign out</span>
                </button>
            </form>
        </div>
    </header>

    <div class="portal-app-content">
        <button class="portal-drawer-backdrop" type="button" aria-label="Close navigation menu" data-portal-menu-close tabindex="-1"></button>

        <nav class="portal-navigation" id="portal-navigation" aria-label="Portal navigation" aria-hidden="true" inert>
            <div class="portal-drawer-header">
                <div class="portal-drawer-profile">
                    <span class="portal-user-avatar" aria-hidden="true">
                        @if (isset($student))
                            {{ strtoupper(substr($student->first_name, 0, 1).substr($student->last_name ?? '', 0, 1)) }}
                        @else
                            {{ strtoupper(substr($portalAccountName ?: 'P', 0, 1)) }}
                        @endif
                    </span>
                    <span>
                        <strong>{{ isset($student) ? trim($student->first_name.' '.$student->last_name) : $portalAccountName }}</strong>
                        <small>{{ isset($student) ? 'Student ID · '.$student->student_id : $portalAccountRole }}</small>
                    </span>
                </div>
                <button class="portal-drawer-close" type="button" aria-label="Close navigation menu" data-portal-menu-close>
                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                </button>
            </div>

            <div class="portal-navigation-links">
                <div class="portal-nav-group">
                    <span class="portal-nav-group-label">Menu</span>
                    <a class="{{ request()->routeIs('portal.dashboard') || request()->routeIs('portal.student') ? 'is-active' : '' }}" href="{{ route('portal.dashboard') }}">
                        <i class="fa-solid fa-house" aria-hidden="true"></i><span>Overview</span>
                    </a>
                </div>

                @if (isset($student))
                    <div class="portal-nav-group">
                        <span class="portal-nav-group-label">Academics</span>
                        <a class="{{ request()->routeIs('portal.attendance') ? 'is-active' : '' }}" href="{{ route('portal.attendance', $student) }}">
                            <i class="fa-regular fa-calendar-check" aria-hidden="true"></i><span>Attendance</span>
                        </a>
                        <a class="{{ request()->route('page') === 'profile' ? 'is-active' : '' }}" href="{{ route('portal.page', 'profile') }}"><i class="fa-regular fa-id-card" aria-hidden="true"></i><span>Student profile</span></a>
                        <a class="{{ request()->route('page') === 'subjects' ? 'is-active' : '' }}" href="{{ route('portal.page', 'subjects') }}"><i class="fa-solid fa-book-open" aria-hidden="true"></i><span>Subjects &amp; teachers</span></a>
                        <a class="{{ request()->route('page') === 'examinations' ? 'is-active' : '' }}" href="{{ route('portal.page', 'examinations') }}"><i class="fa-regular fa-rectangle-list" aria-hidden="true"></i><span>Examination schedule</span></a>
                        <a class="{{ request()->route('page') === 'results' ? 'is-active' : '' }}" href="{{ route('portal.page', 'results') }}"><i class="fa-solid fa-chart-column" aria-hidden="true"></i><span>Results</span></a>
                        <a class="{{ request()->route('page') === 'timetable' ? 'is-active' : '' }}" href="{{ route('portal.page', 'timetable') }}"><i class="fa-regular fa-clock" aria-hidden="true"></i><span>Timetable</span></a>
                    </div>

                    <div class="portal-nav-group">
                        <span class="portal-nav-group-label">School</span>
                        <a class="{{ request()->route('page') === 'fees' ? 'is-active' : '' }}" href="{{ route('portal.page', 'fees') }}"><i class="fa-solid fa-wallet" aria-hidden="true"></i><span>Fees &amp; installments</span></a>
                        <a class="{{ request()->route('page') === 'payments' ? 'is-active' : '' }}" href="{{ route('portal.page', 'payments') }}"><i class="fa-regular fa-credit-card" aria-hidden="true"></i><span>Payment history</span></a>
                        <a class="{{ request()->route('page') === 'notices' ? 'is-active' : '' }}" href="{{ route('portal.page', 'notices') }}"><i class="fa-regular fa-message" aria-hidden="true"></i><span>Notices</span></a>
                    </div>
                @endif
            </div>

            <div class="portal-drawer-footer">
                <span class="portal-drawer-footer-mark"><i class="fa-solid fa-school" aria-hidden="true"></i></span>
                <span><strong>{{ $portalTenant?->institution_name ?? 'School ERP' }}</strong><small>Student &amp; parent portal</small></span>
            </div>
        </nav>

        <main class="portal-main" id="portal-main" tabindex="-1">
            <x-admin.alert />
            <x-admin.validation-errors />
            @yield('content')
        </main>
    </div>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const navigation = document.querySelector('.portal-navigation');
        const menuToggle = document.querySelector('[data-portal-menu-toggle]');
        const closeButtons = document.querySelectorAll('[data-portal-menu-close]');
        const desktopLayout = window.matchMedia('(min-width: 1024px)');

        if (!navigation) {
            return;
        }

        const navigationLinks = [...navigation.querySelectorAll('a')];
        const syncMenuAccessibility = () => {
            const isDesktop = desktopLayout.matches;
            const isOpen = isDesktop || document.body.classList.contains('portal-menu-open');

            navigation.inert = !isOpen;
            navigation.setAttribute('aria-hidden', String(!isOpen));

            if (isDesktop) {
                document.body.classList.remove('portal-menu-open');
                menuToggle?.setAttribute('aria-expanded', 'false');
                menuToggle?.setAttribute('aria-label', 'Open navigation menu');
            }
        };
        const setMenuOpen = (isOpen) => {
            if (desktopLayout.matches) {
                return;
            }

            document.body.classList.toggle('portal-menu-open', isOpen);
            menuToggle?.setAttribute('aria-expanded', String(isOpen));
            menuToggle?.setAttribute('aria-label', isOpen ? 'Close navigation menu' : 'Open navigation menu');
            syncMenuAccessibility();

            if (isOpen) {
                navigation.querySelector('[data-portal-menu-close]')?.focus();
            } else {
                menuToggle?.focus();
            }
        };
        const getLinkTarget = (link) => new URL(link.getAttribute('href'), window.location.href);
        const activateAnchor = (hash) => {
            navigationLinks.forEach((link) => {
                const target = getLinkTarget(link);
                link.classList.toggle('is-active', target.pathname === window.location.pathname && target.hash === hash);
            });
        };

        menuToggle?.addEventListener('click', () => setMenuOpen(true));
        closeButtons.forEach((button) => button.addEventListener('click', () => setMenuOpen(false)));
        desktopLayout.addEventListener('change', syncMenuAccessibility);
        syncMenuAccessibility();
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && document.body.classList.contains('portal-menu-open')) {
                setMenuOpen(false);
            }
        });
        navigation.addEventListener('keydown', (event) => {
            if (event.key !== 'Tab' || desktopLayout.matches || !document.body.classList.contains('portal-menu-open')) {
                return;
            }

            const focusableElements = [...navigation.querySelectorAll('button:not(:disabled), a[href]')];
            const firstElement = focusableElements[0];
            const lastElement = focusableElements[focusableElements.length - 1];

            if (event.shiftKey && document.activeElement === firstElement) {
                event.preventDefault();
                lastElement?.focus();
            } else if (!event.shiftKey && document.activeElement === lastElement) {
                event.preventDefault();
                firstElement?.focus();
            }
        });

        document.addEventListener('click', (event) => {
            const link = event.target.closest('a[href^="#"]');

            if (link && navigationLinks.some((navigationLink) => {
                const navigationTarget = getLinkTarget(navigationLink);
                const clickedTarget = getLinkTarget(link);

                return navigationTarget.pathname === clickedTarget.pathname
                    && navigationTarget.hash !== ''
                    && navigationTarget.hash === clickedTarget.hash;
            })) {
                activateAnchor(link.getAttribute('href'));
            }
        });

        navigation.addEventListener('click', (event) => {
            if (event.target.closest('a')) {
                setMenuOpen(false);
            }
        });

        window.addEventListener('hashchange', () => activateAnchor(window.location.hash));

        if (window.location.hash) {
            activateAnchor(window.location.hash);
        }
    });
</script>
</body>
</html>
