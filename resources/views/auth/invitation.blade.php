<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Join {{ $invitation->tenant->institution_name }} · School ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="{{ asset('css/bootstrap-theme.css') }}">
</head>
<body class="auth-page">
    <main class="container py-5">
        <div class="row justify-content-center">
            <div class="col-12 col-sm-10 col-md-7 col-lg-5">
                <div class="card shadow-lg border-0">
                    <div class="card-body p-4 p-md-5">
                        <h1 class="h3">Join {{ $invitation->tenant->institution_name }}</h1>
                        <p class="text-muted">
                            You were invited as a {{ str($invitation->role)->replace('_', ' ')->title() }} using
                            <strong>{{ $invitation->email }}</strong>. This invitation expires
                            {{ $invitation->expires_at->diffForHumans() }}.
                        </p>

                        @if ($errors->any())
                            <div class="alert alert-danger">{{ $errors->first() }}</div>
                        @endif

                        @auth
                            @if (strcasecmp(auth()->user()->email, $invitation->email) === 0)
                                <form method="post" action="{{ route('tenant.invitation.accept', $invitation->token) }}">
                                    @csrf
                                    <button class="btn btn-primary">Accept invitation</button>
                                </form>
                            @else
                                <p class="text-danger">You are signed in with a different email address.</p>
                                <form method="post" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="btn btn-outline-secondary">Sign out</button>
                                </form>
                            @endif
                        @else
                            <form method="post" action="{{ route('tenant.invitation.accept', $invitation->token) }}">
                                @csrf
                                <div class="form-group">
                                    <label for="name">Your name</label>
                                    <input id="name" name="name" class="form-control" required maxlength="150">
                                </div>
                                <div class="form-group">
                                    <label for="password">Create password</label>
                                    <input
                                        id="password"
                                        name="password"
                                        type="password"
                                        class="form-control"
                                        required
                                        minlength="10"
                                        autocomplete="new-password"
                                    >
                                </div>
                                <div class="form-group">
                                    <label for="password_confirmation">Confirm password</label>
                                    <input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        type="password"
                                        class="form-control"
                                        required
                                        minlength="10"
                                        autocomplete="new-password"
                                    >
                                </div>
                                <button class="btn btn-primary">Create account and accept</button>
                            </form>
                            <p class="small text-muted mt-3">
                                Already have an account with this email?
                                <a href="{{ route('login') }}">Sign in first</a>, then reopen this invitation.
                            </p>
                        @endauth
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
