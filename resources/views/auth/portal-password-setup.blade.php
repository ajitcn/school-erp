<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#18375f">
    <title>Set portal password · {{ $tenant->institution_name }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/auth.css?v=4') }}">
</head>
<body class="auth-page portal-password-setup-page">
    <main class="portal-password-setup-shell">
        <section class="portal-password-setup-card" aria-labelledby="setup-title">
            <header class="portal-password-setup-brand">
                @if ($tenant->logo)
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
                    <strong>{{ $tenant->institution_name }}</strong>
                    <small>{{ ucfirst($tenant->institution_type) }} portal</small>
                </span>
            </header>

            <div class="portal-password-setup-content">
                <span class="portal-password-setup-kicker">Secure account setup</span>
                <h1 id="setup-title">Create your portal password</h1>
                <p class="portal-password-setup-intro">
                    Set a password to sign in to {{ $tenant->institution_name }}’s student and parent portal.
                </p>

                @if ($errors->any())
                    <div class="auth-form-alert auth-form-alert-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <div class="portal-password-setup-account">
                    <span class="portal-password-setup-account-icon" aria-hidden="true">
                        <i class="fa-solid {{ $accountType === 'student' ? 'fa-user-graduate' : 'fa-user-group' }}"></i>
                    </span>
                    <span>
                        <small>{{ ucfirst($accountType) }} account</small>
                        <strong>{{ $accountName }}</strong>
                        <span class="portal-password-setup-login-id">Login ID: {{ $loginIdentifier ?: 'Contact your institution office' }}</span>
                    </span>
                </div>

                <form method="post" action="{{ route('portal.password.setup.store', $token) }}" class="portal-password-setup-form">
                    @csrf
                    <div class="form-group">
                        <label for="password">New password</label>
                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="form-control"
                            minlength="10"
                            autocomplete="new-password"
                            required
                            autofocus
                        >
                        <small class="form-text">Use at least 10 characters.</small>
                    </div>
                    <div class="form-group">
                        <label for="password_confirmation">Confirm password</label>
                        <input
                            id="password_confirmation"
                            name="password_confirmation"
                            type="password"
                            class="form-control"
                            minlength="10"
                            autocomplete="new-password"
                            required
                        >
                    </div>
                    <button class="btn portal-password-setup-submit" type="submit">
                        Save password
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </button>
                </form>

                <div class="portal-password-setup-security">
                    <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <span>This link can be used once and expires {{ \Illuminate\Support\Carbon::parse($expiresAt)->format('d M Y, g:i A') }}.</span>
                </div>
            </div>

            <footer class="portal-password-setup-footer">
                <span>Need help? Contact {{ $tenant->institution_name }}.</span>
                <span>Secure portal · Powered by School ERP</span>
            </footer>
        </section>
    </main>
</body>
</html>
