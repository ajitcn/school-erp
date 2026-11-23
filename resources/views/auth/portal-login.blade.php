<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student and Parent Sign In · School ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/auth.css?v=5') }}">
</head>
<body class="auth-page">
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-7 col-lg-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <img class="auth-brand-logo mx-auto mb-3" src="{{ asset('img/app-logo.png') }}" alt="TechyGIG">
                            <h1 class="h3">Student and Parent Portal</h1>
                            <p class="text-muted mb-0">Sign in with your portal ID or contact and password.</p>
                        </div>

                        <a class="portal-login-pin-action" href="{{ route('portal.password.pin') }}">
                            <span class="portal-login-pin-icon" aria-hidden="true"><i class="fa-solid fa-key"></i></span>
                            <span>
                                <strong>Set or reset your password</strong>
                                <small>Use the 6-digit PIN provided by your school or college.</small>
                            </span>
                            <i class="fa-solid fa-arrow-right portal-login-pin-arrow" aria-hidden="true"></i>
                        </a>

                        @if (session('status'))
                            <div class="alert alert-success" role="status">{{ session('status') }}</div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                        @endif

                        <form method="post" action="{{ route('portal.password.login.store') }}">
                            @csrf
                            <div class="form-group">
                                <label for="account_type">Account type</label>
                                <select id="account_type" name="account_type" class="form-control" required>
                                    <option value="student" @selected(old('account_type', 'student') === 'student')>Student</option>
                                    <option value="parent" @selected(old('account_type') === 'parent')>Parent</option>
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
                                <small class="form-text text-muted">Students can use their student ID. Parents can use the email or mobile number on their guardian record.</small>
                            </div>
                            <div class="form-group">
                                <label for="password">Password</label>
                                <input id="password" name="password" type="password" class="form-control" autocomplete="current-password" required>
                            </div>
                            <button class="btn btn-primary btn-block" type="submit">Sign in to portal</button>
                        </form>

                        <p class="small text-center text-muted mt-4 mb-0">
                            Need help accessing your portal? Contact your school or college office.<br>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
