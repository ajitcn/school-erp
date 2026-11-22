@extends('layouts.app')

@section('content')
    <x-admin.page-header :title="$user ? 'Edit user' : 'Add user'" description="Manage tenant role, portal record links, and branch access." />

    <div class="admin-card p-4">
        <form method="post" action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}">
            @csrf
            @if($user) @method('put') @endif
            <div class="form-row">
                <div class="col-md-6 form-group"><label for="name">Name</label><input id="name" class="form-control" name="name" value="{{ old('name', $user?->name) }}" required></div>
                <div class="col-md-6 form-group"><label for="email">Email</label><input id="email" class="form-control" type="email" name="email" value="{{ old('email', $user?->email) }}" required></div>
                <div class="col-md-6 form-group"><label for="password">{{ $user ? 'New password (optional)' : 'Password' }}</label><input id="password" class="form-control" type="password" name="password" {{ $user ? '' : 'required' }}></div>
                <div class="col-md-6 form-group"><label for="password_confirmation">Confirm password</label><input id="password_confirmation" class="form-control" type="password" name="password_confirmation" {{ $user ? '' : 'required' }}></div>
                <div class="col-md-6 form-group">
                    <label for="role_id">Tenant role</label>
                    <select id="role_id" class="form-control" name="role_id" required data-portal-role>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" data-role-name="{{ $role->name }}" @selected($selectedRole?->id === $role->id)>{{ $role->label }}{{ $role->tenant_id ? ' · Custom' : ' · Built-in' }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">This role applies only inside the active tenant.</small>
                </div>
                <div class="col-md-6 form-group"><label for="primary_branch_id">Primary branch</label><select id="primary_branch_id" class="form-control" name="primary_branch_id"><option value="">All permitted branches</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected((int)old('primary_branch_id', $primaryBranchId ?? null) === $branch->id)>{{ $branch->name }}</option>@endforeach</select></div>

                <div class="col-12 form-group" data-portal-student>
                    <label for="student_id">Linked student record</label>
                    <select id="student_id" class="form-control" name="student_id">
                        <option value="">Select a student</option>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}" @selected((int)old('student_id', $linkedStudentId ?? null) === $student->id)>{{ $student->student_id }} · {{ $student->first_name }} {{ $student->last_name }} · {{ $student->branch?->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">Required for the student portal role.</small>
                </div>

                <div class="col-12 form-group" data-portal-guardians>
                    <label for="guardian_ids">Linked guardian records</label>
                    <select id="guardian_ids" class="form-control bt-select" name="guardian_ids[]" multiple data-live-search="true" data-actions-box="true" data-width="100%">
                        @foreach($guardians as $guardian)
                            <option value="{{ $guardian->id }}" @selected(in_array($guardian->id, old('guardian_ids', $linkedGuardianIds ?? [])))>{{ $guardian->first_name }} {{ $guardian->last_name }} · {{ $guardian->phone }} · {{ $guardian->branch?->name }}</option>
                        @endforeach
                    </select>
                    <small class="form-text text-muted">Select one or more guardian records for the parent portal. A linked guardian can access the students attached to that record.</small>
                </div>

                <div class="col-12 form-group"><label for="branch_access">Additional branch access</label><select id="branch_access" class="form-control bt-select" name="branch_access[]" multiple data-live-search="true" data-actions-box="true" data-width="100%">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(in_array($branch->id, old('branch_access', $user?->branches->pluck('id')->all() ?? [])))>{{ $branch->name }}</option>@endforeach</select><small class="form-text text-muted">Access grants are saved separately for this tenant. Parent and student links also grant portal access to their linked records.</small></div>
            </div>
            <button class="btn btn-primary">{{ $user ? 'Update user' : 'Create user' }}</button>
            <a class="btn btn-outline-secondary ml-2" href="{{ route('admin.users.index') }}">Cancel</a>
        </form>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const role = document.querySelector('[data-portal-role]');
            const studentPanel = document.querySelector('[data-portal-student]');
            const parentPanel = document.querySelector('[data-portal-guardians]');
            const studentSelect = document.getElementById('student_id');
            const guardianSelect = document.getElementById('guardian_ids');
            const updatePortalFields = () => {
                const selectedRole = role?.selectedOptions[0]?.dataset.roleName;
                studentPanel.hidden = selectedRole !== 'student';
                parentPanel.hidden = selectedRole !== 'parent';
                studentSelect.required = selectedRole === 'student';
                guardianSelect.required = selectedRole === 'parent';
                studentSelect.disabled = selectedRole !== 'student';
                guardianSelect.disabled = selectedRole !== 'parent';

                if (selectedRole === 'parent') {
                    setTimeout(() => window.refreshBtSelect?.(guardianSelect), 0);
                }
            };
            role?.addEventListener('change', updatePortalFields);
            updatePortalFields();
        });
    </script>
@endsection
