@extends('layouts.app')

@section('content')
    <div class="role-management-page">
        <x-admin.page-header
            title="Roles and permissions"
            description="Choose exactly which application permissions each tenant role can use. Changes apply only to this tenant."
        />

        @php
            $permissionGroups = $permissionGroups->mapWithKeys(fn ($groupPermissions, $group) => [
                $group => $groupPermissions->values(),
            ]);
        @endphp

        <x-admin.card class="role-management-card">
            <details class="role-collapsible" @if ($errors->any()) open @endif>
                <summary class="role-management-heading role-create-summary">
                    <span>
                        <strong>Create a tenant role</strong>
                        <small>Expand to set a role name and choose its permissions.</small>
                    </span>
                    <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                </summary>

                <form method="post" action="{{ route('admin.roles.store') }}">
                @csrf
                <div class="form-row">
                    <div class="form-group col-12 col-md-6">
                        <label for="role_name">Role key</label>
                        <input
                            id="role_name"
                            class="form-control @error('name') is-invalid @enderror"
                            name="name"
                            value="{{ old('name') }}"
                            placeholder="e.g. librarian"
                            pattern="[a-z][a-z0-9_]*"
                            required
                        >
                        @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-group col-12 col-md-6">
                        <label for="role_label">Display name</label>
                        <input
                            id="role_label"
                            class="form-control @error('label') is-invalid @enderror"
                            name="label"
                            value="{{ old('label') }}"
                            placeholder="e.g. Librarian"
                            required
                        >
                        @error('label') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>

                <fieldset class="role-permission-fieldset">
                    <legend>Permissions</legend>
                    <p class="text-muted">Select permissions one by one. You can update them later.</p>

                    @if ($permissionGroups->isNotEmpty())
                        <div class="role-permission-grid">
                            @foreach ($permissionGroups as $group => $groupPermissions)
                                <section class="role-permission-group" aria-label="{{ $group }} permissions">
                                    <h3>{{ $group }}</h3>
                                    @foreach ($groupPermissions as $permission)
                                        <label class="role-permission-option" for="new_permission_{{ $permission->id }}">
                                            <input
                                                id="new_permission_{{ $permission->id }}"
                                                type="checkbox"
                                                name="permissions[]"
                                                value="{{ $permission->id }}"
                                                @checked(collect(old('permissions', []))->map(fn ($id) => (string) $id)->contains((string) $permission->id))
                                            >
                                            <span>
                                                <strong>{{ $permission->label }}</strong>
                                                <small>{{ $permission->name }}</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </section>
                            @endforeach
                        </div>
                    @else
                        <p class="alert alert-warning mb-0">No permissions are registered yet.</p>
                    @endif
                    @error('permissions') <small class="d-block text-danger mt-2">{{ $message }}</small> @enderror
                    @error('permissions.*') <small class="d-block text-danger mt-2">{{ $message }}</small> @enderror
                </fieldset>

                <div class="role-form-actions">
                    <button class="btn btn-primary" type="submit" @disabled($permissions->isEmpty())>
                        <i class="fa-solid fa-user-plus mr-2" aria-hidden="true"></i>Create role
                    </button>
                </div>
                </form>
            </details>
        </x-admin.card>

        <section class="role-list-section" aria-labelledby="role-list-title">
            <div class="role-management-heading">
                <div>
                    <h2 id="role-list-title">Available roles</h2>
                    <p>Built-in roles are shared defaults. Customize an eligible role to edit a tenant-only copy.</p>
                </div>
                <span class="results-count-pill">{{ number_format($roles->count()) }} {{ \Illuminate\Support\Str::plural('role', $roles->count()) }}</span>
            </div>

            @forelse ($roles as $role)
                @php
                    $isBuiltIn = $role->tenant_id === null;
                    $hasBuiltInDefinition = $builtInRoleNames->contains($role->name);
                    $isAccessProtected = in_array($role->name, ['owner', 'administrator', 'super_admin'], true);
                    $selectedPermissionIds = $role->permissions->pluck('id')->map(fn ($id) => (string) $id)->all();
                @endphp

                <x-admin.card class="role-permission-card">
                    <details class="role-collapsible">
                        <summary class="role-management-heading role-card-heading">
                            <span class="role-summary-title">
                                <strong>{{ $role->label }}</strong>
                                <small><code>{{ $role->name }}</code> · {{ $role->permissions->count() }} permissions</small>
                            </span>
                            <span class="role-summary-meta">
                                @if ($isBuiltIn)
                                    <span class="badge badge-secondary">Built-in</span>
                                @elseif ($hasBuiltInDefinition)
                                    <span class="badge badge-primary">Customized for this tenant</span>
                                @else
                                    <span class="badge badge-primary">Tenant role</span>
                                @endif
                                <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                            </span>
                        </summary>

                        <div class="role-collapsible-content">
                            @if ($isBuiltIn)
                                <div class="role-built-in-summary">
                                    <strong>{{ $role->permissions->count() }} assigned permissions</strong>
                                    @if ($role->permissions->isNotEmpty())
                                        <div class="role-permission-badges" aria-label="Assigned permissions">
                                            @foreach ($role->permissions->sortBy('name') as $permission)
                                                <span class="role-permission-badge" title="{{ $permission->name }}">
                                                    {{ $permission->label }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="role-permission-empty">No direct permissions assigned.</span>
                                    @endif
                                </div>

                                @if ($isAccessProtected)
                                    <p class="role-protected-note mb-0">This system role keeps its full-access boundary and cannot be customized from this page.</p>
                                @else
                                    <form method="post" action="{{ route('admin.roles.customize', $role) }}">
                                        @csrf
                                        <button class="btn btn-outline-primary" type="submit">
                                            <i class="fa-solid fa-sliders mr-2" aria-hidden="true"></i>Customize for this tenant
                                        </button>
                                    </form>
                                @endif
                            @else
                                <form method="post" action="{{ route('admin.roles.update', $role) }}">
                                    @csrf
                                    @method('put')

                            <div class="form-group">
                                <label for="role_label_{{ $role->id }}">Display name</label>
                                <input
                                    id="role_label_{{ $role->id }}"
                                    class="form-control"
                                    name="label"
                                    value="{{ $role->label }}"
                                    maxlength="120"
                                    required
                                >
                            </div>

                            <fieldset class="role-permission-fieldset">
                                <legend>Choose permissions</legend>
                                <div class="role-permission-grid">
                                    @foreach ($permissionGroups as $group => $groupPermissions)
                                        <section class="role-permission-group" aria-label="{{ $group }} permissions for {{ $role->label }}">
                                            <h4>{{ $group }}</h4>
                                            @foreach ($groupPermissions as $permission)
                                                <label class="role-permission-option" for="role_{{ $role->id }}_permission_{{ $permission->id }}">
                                                    <input
                                                        id="role_{{ $role->id }}_permission_{{ $permission->id }}"
                                                        type="checkbox"
                                                        name="permissions[]"
                                                        value="{{ $permission->id }}"
                                                        @checked(in_array((string) $permission->id, $selectedPermissionIds, true))
                                                    >
                                                    <span>
                                                        <strong>{{ $permission->label }}</strong>
                                                        <small>{{ $permission->name }}</small>
                                                    </span>
                                                </label>
                                            @endforeach
                                        </section>
                                    @endforeach
                                </div>
                            </fieldset>

                            <div class="role-form-actions">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fa-solid fa-floppy-disk mr-2" aria-hidden="true"></i>Save role permissions
                                </button>
                            </div>
                                </form>
                            @endif
                        </div>
                    </details>
                </x-admin.card>
            @empty
                <x-admin.card>
                    <x-admin.empty-state title="No roles available" description="Create a tenant role to start assigning permissions." />
                </x-admin.card>
            @endforelse
        </section>
    </div>
@endsection
