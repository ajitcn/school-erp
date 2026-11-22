<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="theme-color" content="#193b68"><link rel="manifest" href="{{ asset('manifest.webmanifest') }}"><link rel="apple-touch-icon" href="{{ asset('icons/app-icon.svg') }}">
    <title>{{ $title ?? 'School ERP' }}</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot'))) @vite(['resources/css/app.css','resources/js/app.js']) @endif
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/css/bootstrap-select.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="{{ asset('css/admin-kit.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin-menu.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin-shell.css?v=8') }}">
    <link rel="stylesheet" href="{{ asset('css/dashboard.css?v=4') }}">
        <link rel="stylesheet" href="{{ asset('css/application-ui.css?v=110') }}">
        <link rel="stylesheet" href="{{ asset('css/platform.css?v=5') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/css/bootstrap-datepicker.min.css">
</head>
<body class="admin-body">
    <x-admin.shell :title="$title ?? null">
        {{ $slot ?? '' }} @yield('content')
    </x-admin.shell>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.14.0-beta3/dist/js/bootstrap-select.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.10.0/js/bootstrap-datepicker.min.js"></script>
    <script src="{{ asset('js/admin-kit.js?v=20261103') }}"></script>
    <script src="{{ asset('js/bt-select.js?v=7') }}"></script>
    <script src="{{ asset('js/filter-panels.js?v=3') }}"></script>
    <script src="{{ asset('js/admin-shell.js?v=20261029') }}"></script>
    <script>if('serviceWorker' in navigator && (location.protocol === 'https:' || ['localhost','127.0.0.1'].includes(location.hostname))) navigator.serviceWorker.register('{{ asset('service-worker.js') }}').catch(()=>{});</script>
</body>
</html>
