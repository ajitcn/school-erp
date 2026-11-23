<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#18375f">
        <title>Sign in - School ERP</title>
        <link
            rel="stylesheet"
            href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        >
        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        >
        <link rel="stylesheet" href="{{ asset('css/auth.css?v=5') }}">
    </head>
    <body class="auth-page auth-login-page">
        <main class="auth-login-layout">
            <aside class="auth-login-visual">
                <a class="auth-brand" href="{{ route('login') }}" aria-label="School ERP sign in, by TechyGIG">
                    <img class="auth-brand-logo" src="{{ asset('img/app-logo.png') }}" alt="TechyGIG">
                    <span class="auth-brand-product">School ERP</span>
                </a>

                <div class="auth-login-intro">
                    <span class="auth-kicker">Your institution, in sync</span>
                    <h1>Welcome to a clearer way to manage your school.</h1>
                    <p>
                        One secure place for the people and daily work that keep your institution
                        moving forward.
                    </p>
                </div>

                <div class="auth-product-preview" aria-hidden="true">
                    <div class="auth-preview-topbar">
                        <span class="auth-preview-brand-mark">S</span>
                        <span class="auth-preview-title"></span>
                        <span class="auth-preview-avatar">A</span>
                    </div>
                    <div class="auth-preview-content">
                        <span class="auth-preview-label"></span>
                        <span class="auth-preview-heading"></span>
                        <div class="auth-preview-metrics">
                            <span><i></i><b></b></span>
                            <span><i></i><b></b></span>
                            <span><i></i><b></b></span>
                        </div>
                        <div class="auth-preview-chart">
                            <i></i><i></i><i></i><i></i><i></i><i></i><i></i>
                        </div>
                    </div>
                </div>

                <div class="auth-login-benefits">
                    <span><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>Private institution workspace</span>
                    <span><i class="fa-solid fa-building-columns" aria-hidden="true"></i>Designed for schools and colleges</span>
                </div>
            </aside>

            <section class="auth-login-form-panel" aria-labelledby="login-title">
                <div class="auth-login-form-wrap">
                    <div class="auth-login-mobile-brand" aria-hidden="true">
                        <img class="app-brand-mark" src="{{ asset('img/app-logo.png') }}" alt="">
                    </div>

                    <header class="auth-login-heading">
                        <span class="auth-kicker">Secure sign in</span>
                        <h2 id="login-title">Welcome back</h2>
                        <p>Sign in with the email and password linked to your school or college account.</p>
                    </header>

                    @if (session('status'))
                        <div class="auth-form-alert auth-form-alert-status" role="status">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            <span>{{ session('status') }}</span>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="auth-form-alert auth-form-alert-error" role="alert">
                            <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                            <span>{{ $errors->first() }}</span>
                        </div>
                    @endif

                    <form method="post" action="{{ route('login.store') }}" class="auth-login-form">
                        @csrf

                        <div class="form-group">
                            <label for="email">Email address</label>
                            <span class="auth-login-input-wrap">
                                <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                                <input
                                    id="email"
                                    class="form-control"
                                    type="email"
                                    name="email"
                                    value="{{ old('email') }}"
                                    placeholder="name@institution.edu"
                                    autocomplete="username"
                                    required
                                    autofocus
                                >
                            </span>
                        </div>

                        <div class="form-group">
                            <div class="auth-login-password-heading">
                                <label for="password">Password</label>
                                <a href="{{ route('password.request') }}">Forgot password?</a>
                            </div>
                            <span class="auth-login-input-wrap">
                                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                                <input
                                    id="password"
                                    class="form-control"
                                    type="password"
                                    name="password"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                                >
                            </span>
                        </div>

                        <label class="auth-login-remember">
                            <input type="checkbox" name="remember" value="1">
                            <span>Keep me signed in</span>
                        </label>

                        <button class="btn auth-submit-button" type="submit">
                            Sign in to your workspace
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </button>
                    </form>

                    <div class="auth-login-divider"><span>New to School ERP?</span></div>

                    <a class="auth-register-link" href="{{ route('register') }}">
                        <span>
                            <strong>Register your institution</strong>
                            <small>Applications are reviewed before access is granted.</small>
                        </span>
                        <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                    </a>

                    <p class="text-center mt-3 mb-0">
                        <a href="{{ route('portal.password.login') }}">Student or parent portal sign in</a>
                    </p>

                    <p class="auth-login-footer">
                        Your account and institution data are protected by secure sign in.
                    </p>
                </div>
            </section>
        </main>
    </body>
</html>
