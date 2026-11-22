<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#18375f">
    <meta name="referrer" content="no-referrer">
    <meta name="robots" content="noindex, nofollow">
    <title>Setup link expired · {{ $tenant?->institution_name ?? 'School ERP' }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/auth.css?v=5') }}">
</head>
<body class="auth-page portal-password-setup-page">
    <main class="portal-password-setup-shell">
        <section class="portal-password-setup-card" aria-labelledby="expired-title">
            <header class="portal-password-setup-brand">
                @if ($tenant?->logo)
                    <img
                        class="portal-password-setup-logo"
                        src="{{ asset('storage/'.$tenant->logo) }}"
                        alt="{{ $tenant->institution_name }} logo"
                    >
                @else
                    <span class="portal-password-setup-logo-fallback" aria-hidden="true">
                        <i class="fa-solid fa-school"></i>
                    </span>
                @endif
                <span class="portal-password-setup-institution">
                    <strong>{{ $tenant?->institution_name ?? 'School ERP' }}</strong>
                    <small>{{ ucfirst($tenant?->institution_type ?? 'student and parent') }} portal</small>
                </span>
            </header>

            <div class="portal-password-setup-content">
                <span class="portal-password-setup-kicker">Secure account setup</span>
                <div class="portal-password-link-state-icon" aria-hidden="true">
                    <i class="fa-solid fa-link-slash"></i>
                </div>
                <h1 id="expired-title">This setup link is no longer active</h1>
                <p class="portal-password-setup-intro">
                    This one-time link has expired or has already been used. For your security, each link works once and expires after 60 minutes.
                </p>

                <div class="portal-password-link-help" role="status">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    <p>
                        If you already set your password, you can sign in now. Otherwise, ask your school or college office to generate a new link or a 6-digit setup PIN.
                    </p>
                </div>

                <div class="portal-password-link-actions">
                    <a class="btn portal-password-setup-submit" href="{{ route('portal.password.pin') }}">
                        Continue with a setup PIN
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                    <a class="portal-password-link-signin" href="{{ route('portal.password.login') }}">
                        Back to portal sign in
                    </a>
                </div>
            </div>

            <footer class="portal-password-setup-footer">
                <span>Need help? Contact {{ $tenant?->institution_name ?? 'your institution' }}.</span>
                <span>Secure portal · Powered by School ERP</span>
            </footer>
        </section>
    </main>
</body>
</html>
