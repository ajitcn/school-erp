@extends('layouts.app')

@section('content')
    <x-admin.page-header title="Users" description="Manage tenant staff and branch access.">
        <x-slot:actions>
            <a class="btn btn-primary" href="{{ route('admin.users.create') }}">Add user</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="admin-card">
        <x-admin.table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Tenant role</th>
                    <th>Branches</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td class="font-weight-bold">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->tenant_role_label }}</td>
                        <td>{{ $user->branches->pluck('name')->join(', ') ?: 'All permitted branches' }}</td>
                        <td>
                            <x-admin.badge :tone="$user->tenantMembership()?->is_active ? 'success' : 'slate'">
                                {{ $user->tenantMembership()?->is_active ? 'Active' : 'Inactive' }}
                            </x-admin.badge>
                        </td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary mr-1" href="{{ route('admin.users.edit', $user) }}">
                                Edit
                            </a>
                            @if ((int) $user->id === (int) auth()->id())
                                <span class="small text-muted" aria-label="Your own account cannot be deactivated">
                                    Current account
                                </span>
                            @else
                                <form class="d-inline" method="post" action="{{ route('admin.users.toggle', $user) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">
                                        {{ $user->tenantMembership()?->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <x-admin.empty-state
                                title="No users found"
                                description="Add staff to begin managing the tenant."
                            />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-admin.table>

        <div class="p-3">
            <x-admin.pagination :paginator="$users" />
        </div>
    </div>
@endsection
