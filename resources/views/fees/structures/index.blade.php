@extends('layouts.app')

@section('content')
    <x-admin.page-header title="Fee structures" description="Create reusable fee plan masters. Set amounts and due dates for each student from their profile.">
        <x-slot:actions>
            <button
                type="button"
                class="btn btn-primary"
                data-fee-plan-toggle
                aria-controls="create-fee-plan-section"
                aria-expanded="{{ $errors->any() || old('name') !== null ? 'true' : 'false' }}"
            >
                <i class="fa-solid {{ $errors->any() || old('name') !== null ? 'fa-xmark' : 'fa-plus' }} mr-1" data-fee-plan-toggle-icon aria-hidden="true"></i>
                <span data-fee-plan-toggle-label>{{ $errors->any() || old('name') !== null ? 'Close form' : 'Create Fee' }}</span>
            </button>
            <a class="btn btn-outline-primary" href="{{ route('fees.assignments.index') }}">Fee Installments List</a>
        </x-slot:actions>
    </x-admin.page-header>

    <section
        id="create-fee-plan-section"
        class="admin-card"
        aria-labelledby="new-fee-plan"
        @if (! $errors->any() && old('name') === null) hidden @endif
    >
        <div class="card-header"><h2 id="new-fee-plan">Create fee plan</h2><p class="mb-0 text-muted">A plan is a reusable master. It does not contain installment amounts.</p></div>
        <form method="post" action="{{ route('fees.structures.store') }}" class="p-4">
            @csrf
            <div class="form-row">
                <div class="form-group col-md-6 col-xl-3"><label for="branch_id">Branch</label><select id="branch_id" name="branch_id" class="form-control" required><option value="">Choose branch</option>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id', $branchId ?: auth()->user()->currentBranchId()) == $branch->id)>{{ $branch->name }}</option>@endforeach</select>@error('branch_id')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-md-6 col-xl-3"><label for="academic_year_id">Academic year <span class="text-muted">(optional)</span></label><select id="academic_year_id" name="academic_year_id" class="form-control"><option value="">Reusable across years</option>@foreach($years as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->name }}</option>@endforeach</select>@error('academic_year_id')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-md-6 col-xl-3"><label for="name">Plan name</label><input id="name" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. Standard tuition" required>@error('name')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-md-6 col-xl-3"><label for="default_amount">Standard amount</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">₹</span></div><input id="default_amount" name="default_amount" type="number" min="0.01" step="0.01" class="form-control" value="{{ old('default_amount') }}" required></div><small class="form-text text-muted">Auto-filled when this plan is added to a student.</small>@error('default_amount')<small class="text-danger">{{ $message }}</small>@enderror</div>
                <div class="form-group col-md-6 col-xl-3"><label for="school_class_id">Class <span class="text-muted">(optional)</span></label><select id="school_class_id" name="school_class_id" class="form-control"><option value="">Any class</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected(old('school_class_id') == $class->id)>{{ $class->name }}</option>@endforeach</select></div>
                <div class="form-group col-md-6 col-xl-3"><label for="course_id">Course <span class="text-muted">(optional)</span></label><select id="course_id" name="course_id" class="form-control"><option value="">Any course</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->name }}</option>@endforeach</select></div>
                <div class="form-group col-md-6 col-xl-3"><label for="semester_id">Semester <span class="text-muted">(optional)</span></label><select id="semester_id" name="semester_id" class="form-control"><option value="">Any semester</option>@foreach($semesters as $semester)<option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id)>{{ $semester->course?->name }} · {{ $semester->name }}</option>@endforeach</select></div>
            </div>
            <button class="btn btn-primary" type="submit">Save fee plan</button>
        </form>
    </section>

    <section class="admin-card" aria-labelledby="fee-plans-list">
        <div class="card-header"><h2 id="fee-plans-list">Fee plan masters</h2><x-admin.badge tone="primary">{{ $structures->total() }} plans</x-admin.badge></div>
        <x-admin.table>
            <thead><tr><th>Plan</th><th>Standard amount</th><th>Branch</th><th>Academic year</th><th>Applies to</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
                @forelse($structures as $structure)
                    <tr>
                        <td><strong>{{ $structure->name }}</strong></td>
                        <td><strong>{{ $structure->default_amount ? '₹'.number_format($structure->default_amount, 2) : 'Needs amount' }}</strong></td>
                        <td>{{ $structure->branch?->name ?? '—' }}</td>
                        <td>{{ $structure->academicYear?->name ?? 'Any year' }}</td>
                        <td>{{ $structure->schoolClass?->name ?? 'Any class' }} · {{ $structure->course?->name ?? 'Any course' }}{{ $structure->semester?->name ? ' · '.$structure->semester->name : '' }}</td>
                        <td><x-admin.badge :tone="$structure->is_active ? 'success' : 'slate'">{{ $structure->is_active ? 'Active' : 'Inactive' }}</x-admin.badge></td>
                        <td class="text-right"><a class="btn btn-sm btn-outline-primary" href="{{ route('fees.structures.edit', $structure) }}">Edit plan</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-admin.empty-state title="No fee plans yet" description="Create a master plan to start setting up student fees." /></td></tr>
                @endforelse
            </tbody>
        </x-admin.table>
        <div class="p-3">{{ $structures->links() }}</div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const toggle = document.querySelector('[data-fee-plan-toggle]');
            const section = document.getElementById('create-fee-plan-section');
            const label = toggle?.querySelector('[data-fee-plan-toggle-label]');
            const icon = toggle?.querySelector('[data-fee-plan-toggle-icon]');

            if (! toggle || ! section) {
                return;
            }

            toggle.addEventListener('click', () => {
                const willOpen = section.hidden;
                section.hidden = ! willOpen;
                toggle.setAttribute('aria-expanded', String(willOpen));
                label.textContent = willOpen ? 'Close form' : 'Create Fee';
                icon.classList.toggle('fa-plus', ! willOpen);
                icon.classList.toggle('fa-xmark', willOpen);

                if (willOpen) {
                    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    section.querySelector('input:not([type="hidden"]), select')?.focus({ preventScroll: true });
                }
            });
        });
    </script>
@endsection
