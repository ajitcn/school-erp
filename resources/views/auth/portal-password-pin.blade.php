<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#18375f">
    <title>Set portal password with PIN · School ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/auth.css?v=5') }}">
</head>
<body class="auth-page portal-password-setup-page">
    <main class="portal-password-setup-shell">
        <section class="portal-password-setup-card" aria-labelledby="pin-setup-title">
            <header class="portal-password-setup-brand">
                <img class="portal-password-setup-logo portal-app-logo-mark" src="{{ asset('img/app-logo.png') }}" alt="TechyGIG">
                <span class="portal-password-setup-institution">
                    <strong>School ERP</strong>
                    <small>Student and parent portal</small>
                </span>
            </header>

            <div class="portal-password-setup-content">
                <span class="portal-password-setup-kicker">Secure account setup</span>
                <h1 id="pin-setup-title">Set or reset your password</h1>
                <p class="portal-password-setup-intro">
                    Enter your portal ID and the 6-digit PIN provided by your school or college.
                    The PIN expires 10 minutes after it is generated.
                </p>

                @if ($errors->any())
                    <div class="auth-form-alert auth-form-alert-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif

                <form method="post" action="{{ route('portal.password.pin.store') }}" class="portal-password-setup-form">
                    @csrf
                    <div class="form-group">
                        <label for="account_type">Account type</label>
                        <select id="account_type" name="account_type" class="form-control" required>
                            <option value="student" @selected(old('account_type', 'student') === 'student')>Student</option>
                            <option value="parent" @selected(old('account_type') === 'parent')>Parent / guardian</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="identifier">Student ID, email, or mobile</label>
                        <input
                            id="identifier"
                            name="identifier"
                            class="form-control"
                            value="{{ old('identifier') }}"
                            autocomplete="username"
                            required
                            autofocus
                        >
                        <small class="form-text">Students use their student ID. Parents use the email address or mobile number on their record.</small>
                    </div>
                    <div class="form-group">
                        <label for="setup_pin">6-digit setup PIN</label>
                        <input
                            id="setup_pin"
                            name="setup_pin"
                            type="text"
                            class="form-control portal-password-pin-input"
                            inputmode="numeric"
                            pattern="[0-9]{6}"
                            minlength="6"
                            maxlength="6"
                            autocomplete="one-time-code"
                            placeholder="000000"
                            required
                        >
                    </div>
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
                    <span>The PIN works once. If it expires or does not work, ask your institution for a new one.</span>
                </div>
            </div>

            <footer class="portal-password-setup-footer">
                <a href="{{ route('portal.password.login') }}">Back to portal sign in</a>
            </footer>
        </section>
    </main>
</body>
</html>
