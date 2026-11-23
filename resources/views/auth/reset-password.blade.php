<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Choose new password - School ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/auth.css?v=5') }}">
</head>
<body class="auth-page">
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-6 col-lg-4">
                <div class="text-center text-white mb-4">
                    <img class="auth-brand-logo mx-auto mb-3" src="{{ asset('img/app-logo.png') }}" alt="TechyGIG">
                    <h1 class="h3">Choose a new password</h1>
                    <p class="mb-0">Use at least 10 characters. This secure link can only be used once.</p>
                </div>

                <div class="card shadow-lg border-0">
                    <div class="card-body p-4">
                        @if ($errors->any())
                            <div class="alert alert-danger">{{ $errors->first() }}</div>
                        @endif

                        <form method="post" action="{{ route('password.update') }}">
                            @csrf
                            <input type="hidden" name="token" value="{{ $token }}">

                            <div class="form-group">
                                <label for="email">Email address</label>
                                <input
                                    id="email"
                                    class="form-control"
                                    type="email"
                                    name="email"
                                    value="{{ old('email', request('email')) }}"
                                    required
                                >
                            </div>
                            <div class="form-group">
                                <label for="password">New password</label>
                                <input id="password" class="form-control" type="password" name="password" minlength="10" autocomplete="new-password" required>
                            </div>
                            <div class="form-group">
                                <label for="password_confirmation">Confirm password</label>
                                <input
                                    id="password_confirmation"
                                    class="form-control"
                                    type="password"
                                    name="password_confirmation"
                                    minlength="10"
                                    autocomplete="new-password"
                                    required
                                >
                            </div>
                            <button class="btn btn-primary btn-block">Update password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
