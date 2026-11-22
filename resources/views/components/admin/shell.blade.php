@props(['title' => null])
<div class="admin-app">
    <x-admin.sidebar />
    <div class="admin-main">
        <x-admin.header />
        <main class="admin-content container-fluid">
            <div class="admin-page-breadcrumb">
                <x-admin.breadcrumbs />
            </div>
            <x-admin.alert /><x-admin.validation-errors />{{ $slot }}
        </main>
    </div>
</div>
