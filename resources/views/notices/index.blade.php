@extends('layouts.app')

@section('content')
    @php
        $selectedStudentIds = collect(old('student_ids', []))
            ->map(fn ($studentId) => (int) $studentId)
            ->all();
    @endphp

    <x-admin.page-header
        title="Announcements"
        description="Publish messages for your tenant users and linked student and parent portals."
    />

    <div class="alert alert-info d-flex align-items-start mb-4" role="note">
        <i class="fa-regular fa-message mr-3 mt-1" aria-hidden="true"></i>
        <div>
            <strong>Messages are published announcements.</strong>
            <div>Save an announcement as a draft, or publish it now. Published announcements appear in the Messages inbox and relevant student and parent portals.</div>
        </div>
    </div>

    @unless ($audienceSchemaReady)
        <div class="alert alert-warning" role="alert">
            Audience targeting needs a database update. Until it is applied, announcements can only be sent to everyone.
            Run <code>php artisan migrate --force</code> to enable staff, parent, and student targeting.
        </div>
    @endunless

    <x-admin.card>
        <h2 class="h5 mb-1">Create announcement</h2>
        <p class="text-muted mb-4">Choose who should receive the message, add its campus scope, and decide whether to publish now.</p>

        <form method="post" action="{{ route('notices.store') }}" data-notice-form data-default-branch-id="{{ $selectedBranchId }}">
            @csrf

            <div class="form-row">
                @if (! auth()->user()->currentBranchId())
                    <div class="form-group col-md-6">
                        <label for="branch_id">Campus scope</label>
                        <select id="branch_id" name="branch_id" class="form-control" data-notice-branch>
                            <option value="">All accessible campuses</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id', $selectedBranchId) == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                @endif

                <div class="form-group col-md-6">
                    <label for="audience">Audience</label>
                    <select id="audience" name="audience" class="form-control" required data-notice-audience>
                        <option value="all" @selected(old('audience', 'all') === 'all')>Everyone in the tenant</option>
                        <option value="staff" @selected(old('audience') === 'staff') @disabled(! $audienceSchemaReady)>Staff</option>
                        <option value="students" @selected(old('audience') === 'students') @disabled(! $audienceSchemaReady)>Students</option>
                        <option value="parents" @selected(old('audience') === 'parents') @disabled(! $audienceSchemaReady)>Parents</option>
                    </select>
                    @error('audience')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                    <small class="form-text text-muted">Campus scope is applied in addition to the selected audience.</small>
                </div>

                <div class="form-group col-md-6 d-none" data-student-scope-group>
                    <label for="student_scope">Student recipients</label>
                    <select id="student_scope" name="student_scope" class="form-control" data-student-scope disabled>
                        <option value="all" @selected(old('student_scope', 'all') === 'all')>All students in this campus scope</option>
                        <option value="selected" @selected(old('student_scope') === 'selected')>Select specific students</option>
                    </select>
                    @error('student_scope')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group col-12 d-none" data-student-recipients-group>
                    <label for="student_ids">Specific students</label>
                    <select
                        id="student_ids"
                        name="student_ids[]"
                        class="form-control bt-select"
                        multiple
                        data-live-search="true"
                        data-actions-box="true"
                        data-size="8"
                        data-width="100%"
                        title="Search and select students"
                        data-student-recipients
                        disabled
                    >
                        @foreach ($students as $student)
                            <option
                                value="{{ $student->id }}"
                                data-branch-id="{{ $student->branch_id }}"
                                @selected(in_array((int) $student->id, $selectedStudentIds, true))
                            >
                                {{ $student->first_name }} {{ $student->last_name }} · {{ $student->student_id }} · {{ $student->branch?->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('student_ids')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                    @error('student_ids.*')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                    <small class="form-text text-muted">Search by student name or ID. You can select up to 500 students.</small>
                </div>

                <div class="form-group col-md-6">
                    <label for="title">Message title</label>
                    <input id="title" name="title" class="form-control" maxlength="180" value="{{ old('title') }}" required>
                    @error('title')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group col-12">
                    <label for="body">Message</label>
                    <textarea id="body" name="body" class="form-control" rows="5" maxlength="10000" required>{{ old('body') }}</textarea>
                    @error('body')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group col-md-6">
                    <label for="expires_at">Expiry date <span class="text-muted">(optional)</span></label>
                    <input
                        id="expires_at"
                        name="expires_at"
                        type="text"
                        class="form-control js-datepicker"
                        autocomplete="off"
                        value="{{ old('expires_at') }}"
                        placeholder="DD/MM/YYYY"
                    >
                    @error('expires_at')
                        <small class="text-danger">{{ $message }}</small>
                    @enderror
                </div>

                <div class="form-group col-md-6 d-flex align-items-center">
                    <div class="custom-control custom-checkbox mt-md-4">
                        <input
                            id="publish_now"
                            name="publish_now"
                            type="checkbox"
                            value="1"
                            class="custom-control-input"
                            @checked(old('publish_now', true))
                        >
                        <label class="custom-control-label" for="publish_now">Publish immediately</label>
                        <small class="form-text text-muted">Uncheck to save this announcement as a draft.</small>
                    </div>
                </div>
            </div>

            <button class="btn btn-primary" type="submit">Save announcement</button>
        </form>
    </x-admin.card>

    <x-admin.card padding="false">
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <h2 class="h5 mb-1">Announcement history</h2>
                <p class="text-muted small mb-0">Publish or return an announcement to draft status at any time.</p>
            </div>
            <a href="{{ route('messages.index') }}" class="btn btn-sm btn-outline-primary mt-2 mt-md-0">Open Messages inbox</a>
        </div>

        <div class="table-responsive">
            <x-admin.table>
                <thead>
                    <tr>
                        <th>Announcement</th>
                        <th>Audience</th>
                        <th>Created</th>
                        <th>Visibility</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($notices as $notice)
                        @php
                            $audienceLabel = match ($notice->audience_type) {
                                'staff' => 'Staff',
                                'students' => $notice->student_scope === 'selected'
                                    ? 'Selected students ('.$notice->students_count.')'
                                    : 'All students',
                                'parents' => 'Parents',
                                default => 'Everyone',
                            };
                        @endphp
                        <tr>
                            <td>
                                <strong>{{ $notice->title }}</strong>
                                <small class="d-block text-muted">{{ \Illuminate\Support\Str::limit($notice->body, 120) }}</small>
                            </td>
                            <td>
                                <span class="d-block">{{ $notice->branch?->name ?? 'All accessible campuses' }}</span>
                                <small class="text-muted">{{ $audienceLabel }}</small>
                            </td>
                            <td>{{ $notice->created_at->format('d M Y, h:i A') }}</td>
                            <td>
                                @if ($notice->published_at)
                                    <x-admin.badge tone="success">Published</x-admin.badge>
                                    <small class="d-block text-muted mt-1">{{ $notice->published_at->format('d M Y') }}{{ $notice->expires_at ? ' · expires '.$notice->expires_at->format('d M Y') : '' }}</small>
                                @else
                                    <x-admin.badge tone="slate">Draft</x-admin.badge>
                                @endif
                            </td>
                            <td class="text-right">
                                <form method="post" action="{{ route('notices.publish', $notice) }}">
                                    @csrf
                                    <button class="btn btn-sm {{ $notice->published_at ? 'btn-outline-secondary' : 'btn-outline-primary' }}" type="submit">
                                        {{ $notice->published_at ? 'Unpublish' : 'Publish' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4">
                                <x-admin.empty-state title="No announcements yet" description="Create an announcement above to publish a message to your campus community." />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-admin.table>
        </div>

        <div class="p-3">
            <x-admin.pagination :paginator="$notices" />
        </div>
    </x-admin.card>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-notice-form]');
            const audience = form?.querySelector('[data-notice-audience]');
            const studentScope = form?.querySelector('[data-student-scope]');
            const scopeGroup = form?.querySelector('[data-student-scope-group]');
            const recipients = form?.querySelector('[data-student-recipients]');
            const recipientsGroup = form?.querySelector('[data-student-recipients-group]');
            const branchSelect = form?.querySelector('[data-notice-branch]');

            if (!form || !audience || !studentScope || !scopeGroup || !recipients || !recipientsGroup) {
                return;
            }

            const updateStudentControls = () => {
                const isStudentAudience = audience.value === 'students';
                const requiresSelection = isStudentAudience && studentScope.value === 'selected';
                const branchId = branchSelect?.value || form.dataset.defaultBranchId || '';

                scopeGroup.classList.toggle('d-none', !isStudentAudience);
                studentScope.disabled = !isStudentAudience;
                recipientsGroup.classList.toggle('d-none', !requiresSelection);
                recipients.disabled = !requiresSelection;
                recipients.required = requiresSelection;

                Array.from(recipients.options).forEach((option) => {
                    const isInScope = !branchId || option.dataset.branchId === branchId;

                    if (!isInScope && option.selected) {
                        option.selected = false;
                    }
                    option.hidden = !isInScope;
                    option.disabled = !isInScope;
                });

                if (window.jQuery && jQuery.fn.selectpicker) {
                    const recipientPicker = jQuery(recipients);

                    // Rebuild the picker after changing option visibility so stale menu rows cannot accumulate.
                    if (recipientPicker.data('selectpicker')) {
                        recipientPicker.selectpicker('destroy');
                    }

                    window.initBtSelect?.(recipients);
                }
            };

            audience.addEventListener('change', updateStudentControls);
            studentScope.addEventListener('change', updateStudentControls);
            branchSelect?.addEventListener('change', updateStudentControls);
            updateStudentControls();
        });
    </script>
@endsection
