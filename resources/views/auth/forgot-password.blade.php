<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Reset password - School ERP</title><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"><link rel="stylesheet" href="{{ asset('css/auth.css?v=5') }}"></head>
<body class="auth-page">
<main class="container py-5"><div class="row justify-content-center"><div class="col-12 col-sm-10 col-md-6 col-lg-4">
<div class="text-center text-white mb-4"><img class="auth-brand-logo mx-auto mb-3" src="{{ asset('img/app-logo.png') }}" alt="TechyGIG"><h1 class="h3">Reset password</h1></div>
<div class="card shadow-lg border-0"><div class="card-body p-4">
@if(session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif
<form method="post" action="{{ route('password.email') }}">@csrf<div class="form-group"><label for="email">Email address</label><input id="email" class="form-control" type="email" name="email" required autofocus></div><button class="btn btn-primary btn-block" type="submit">Email reset link</button></form>
<a class="d-block text-center small mt-3" href="{{ route('login') }}">Back to sign in</a>
</div></div></div></div></main>
</body>
</html>
