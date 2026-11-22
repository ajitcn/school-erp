<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#18375f">
        <title>Register your institution - School ERP</title>
        <link
            rel="stylesheet"
            href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        >
        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        >
        <link rel="stylesheet" href="{{ asset('css/auth.css?v=1') }}">
    </head>
    <body class="auth-page auth-registration-page">
        <main class="auth-registration-layout">
            <aside class="auth-registration-aside">
                <a class="auth-brand" href="{{ route('login') }}" aria-label="School ERP sign in">
                    <span class="auth-brand-mark">SE</span>
                    <span>School ERP</span>
                </a>

                <div class="auth-registration-intro">
                    <span class="auth-kicker">One workspace for your institution</span>
                    <h1>Build a better day at school.</h1>
                    <p>
                        Bring students, staff, academics, attendance, and fees together in one
                        secure workspace for your school or college.
                    </p>
                </div>

                <ol class="auth-review-steps">
                    <li>
                        <span>01</span>
                        <div>
                            <strong>Submit your institution details</strong>
                            <small>Tell us who will manage the workspace.</small>
                        </div>
                    </li>
                    <li>
                        <span>02</span>
                        <div>
                            <strong>Wait for platform review</strong>
                            <small>A super administrator reviews every application.</small>
                        </div>
                    </li>
                    <li>
                        <span>03</span>
                        <div>
                            <strong>Start your 14-day trial</strong>
                            <small>Your trial starts after your institution is approved.</small>
                        </div>
                    </li>
                </ol>

                <p class="auth-aside-footnote">
                    No payment is required to submit a registration. Subscription plans are
                    available after approval.
                </p>
            </aside>

            <section class="auth-registration-form-panel" aria-labelledby="registration-title">
                <header class="auth-form-heading">
                    <span class="auth-kicker">Institution registration</span>
                    <h2 id="registration-title">Create your workspace</h2>
                    <p>Complete the details below to send your application for review.</p>
                </header>

                @if ($errors->any())
                    <div class="auth-form-alert auth-form-alert-error" role="alert">
                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="post" action="{{ route('register.store') }}" class="auth-registration-form">
                    @csrf

                    <div class="auth-form-section-heading">
                        <span>01</span>
                        <div>
                            <h3>Institution details</h3>
                            <p>Choose the organization type that best describes you.</p>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="institution_name">Institution name</label>
                        <input
                            id="institution_name"
                            class="form-control"
                            name="institution_name"
                            value="{{ old('institution_name') }}"
                            placeholder="e.g. Green Valley School"
                            autocomplete="organization"
                            required
                        >
                    </div>

                    <fieldset class="auth-institution-type">
                        <legend>Institution type</legend>
                        <div class="auth-type-options">
                            <label class="auth-type-option">
                                <input
                                    type="radio"
                                    name="institution_type"
                                    value="school"
                                    @checked(old('institution_type', 'school') === 'school')
                                    required
                                >
                                <span class="auth-type-icon" aria-hidden="true">
                                    <i class="fa-solid fa-school"></i>
                                </span>
                                <span>
                                    <strong>School</strong>
                                    <small>Primary and secondary education</small>
                                </span>
                            </label>
                            <label class="auth-type-option">
                                <input
                                    type="radio"
                                    name="institution_type"
                                    value="college"
                                    @checked(old('institution_type') === 'college')
                                >
                                <span class="auth-type-icon" aria-hidden="true">
                                    <i class="fa-solid fa-building-columns"></i>
                                </span>
                                <span>
                                    <strong>College</strong>
                                    <small>Degree and higher education</small>
                                </span>
                            </label>
                        </div>
                    </fieldset>

                    <div class="auth-form-section-heading auth-form-section-heading-spaced">
                        <span>02</span>
                        <div>
                            <h3>Account administrator</h3>
                            <p>This person becomes the institution owner after approval.</p>
                        </div>
                    </div>

                    <div class="auth-form-grid">
                        <div class="form-group">
                            <label for="owner_name">Full name</label>
                            <input
                                id="owner_name"
                                class="form-control"
                                name="owner_name"
                                value="{{ old('owner_name') }}"
                                autocomplete="name"
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label for="email">Work email</label>
                            <input
                                id="email"
                                class="form-control"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                autocomplete="email"
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label for="phone">Phone number</label>
                            <input
                                id="phone"
                                class="form-control"
                                type="tel"
                                name="phone"
                                value="{{ old('phone') }}"
                                autocomplete="tel"
                                required
                            >
                        </div>
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input
                                id="password"
                                class="form-control"
                                type="password"
                                name="password"
                                minlength="10"
                                autocomplete="new-password"
                                required
                            >
                        </div>
                        <div class="form-group auth-password-confirmation">
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
                    </div>

                    <label class="auth-terms-check">
                        <input type="checkbox" name="terms" value="1" @checked(old('terms')) required>
                        <span>I accept the terms and privacy policy.</span>
                    </label>

                    <button class="btn auth-submit-button" type="submit">
                        Submit registration for review
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </button>

                    <p class="auth-sign-in-link">
                        Already have an account?
                        <a href="{{ route('login') }}">Sign in</a>
                    </p>
                </form>
            </section>
        </main>
    </body>
</html>
