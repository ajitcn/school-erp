@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Change password"
        description="Update your sign-in password to keep your account secure."
    />

    <section class="admin-card p-4" aria-labelledby="password-form-title">
        <div class="mb-4">
            <h2 id="password-form-title" class="h5 mb-1">Password details</h2>
            <p class="text-muted mb-0">Use at least 10 characters and choose a password you do not use elsewhere.</p>
        </div>

        <form method="post" action="{{ route('account.password.update') }}" class="account-password-form">
            @csrf
            @method('put')

            <div class="form-group">
                <label for="current_password">Current password</label>
                <input
                    id="current_password"
                    class="form-control @error('current_password') is-invalid @enderror"
                    type="password"
                    name="current_password"
                    autocomplete="current-password"
                    required
                    autofocus
                >
                @error('current_password')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>

            <div class="form-row">
                <div class="col-md-6 form-group">
                    <label for="password">New password</label>
                    <input
                        id="password"
                        class="form-control @error('password') is-invalid @enderror"
                        type="password"
                        name="password"
                        autocomplete="new-password"
                        minlength="10"
                        required
                    >
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-6 form-group">
                    <label for="password_confirmation">Confirm new password</label>
                    <input
                        id="password_confirmation"
                        class="form-control"
                        type="password"
                        name="password_confirmation"
                        autocomplete="new-password"
                        minlength="10"
                        required
                    >
                </div>
            </div>

            <div class="d-flex flex-wrap align-items-center mt-3">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-key mr-2" aria-hidden="true"></i>Update password
                </button>
                <a
                    class="btn btn-outline-secondary ml-2"
                    href="{{ auth()->user()->is_platform_super_admin ? route('platform.tenants') : route('dashboard') }}"
                >
                    Cancel
                </a>
            </div>
        </form>
    </section>
@endsection
