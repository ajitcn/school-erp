@extends('layouts.app')

@section('content')
    <div class="examinations-page">
        <x-admin.page-header
            title="Examinations"
            description="Schedule assessments and track their publication. Record all student marks from Academic results."
        >
            <x-slot:actions>
                <button
                    class="btn btn-outline-primary"
                    type="button"
                    data-examination-form-toggle
                    data-collapsed-label="{{ $editingExam ? 'Edit examination' : 'Create examination' }}"
                    aria-controls="examination-create-form"
                    aria-expanded="{{ $createFormOpen ? 'true' : 'false' }}"
                >
                    <i class="fa-solid fa-calendar-plus mr-2" aria-hidden="true"></i>
                    <span data-examination-form-label>{{ $createFormOpen ? 'Hide form' : ($editingExam ? 'Edit examination' : 'Create examination') }}</span>
                </button>
                <a class="btn btn-primary" href="{{ route('academic.results') }}">
                    <i class="fa-solid fa-square-pen mr-2" aria-hidden="true"></i>Record results
                </a>
            </x-slot:actions>
        </x-admin.page-header>

        <section class="examination-summary-grid" aria-label="Examination summary">
            <x-admin.stat-card
                label="Examinations"
                :value="number_format($totalExamCount)"
                detail="Matching branch and year"
                tone="primary"
                icon="fa-calendar-days"
            />
            <x-admin.stat-card
                label="Drafts"
                :value="number_format($draftExamCount)"
                detail="Not yet visible in portals"
                tone="warning"
                icon="fa-pen-to-square"
            />
            <x-admin.stat-card
                label="Published"
                :value="number_format($publishedExamCount)"
                detail="Available to students and parents"
                tone="success"
                icon="fa-circle-check"
            />
            <x-admin.stat-card
                label="Linked subject results"
                :value="number_format($linkedResultCount)"
                detail="Recorded from Academic results"
                tone="info"
                icon="fa-list-check"
            />
        </section>

        @unless ($resultsLinked)
            <div class="alert alert-warning mb-0" role="alert">
                <strong>Result linking is not enabled yet.</strong>
                Run <code>php artisan migrate --force</code> to link examinations with results recorded in Academic results.
            </div>
        @endunless

        <x-admin.card class="examination-filter-card">
            <form method="get" action="{{ route('academic.examinations') }}" class="form-row align-items-end js-filter-form" aria-label="Examination filters">
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="examination_filter_branch">Branch</label>
                    <select id="examination_filter_branch" name="branch_id" class="form-control">
                        <option value="">All accessible branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) request('branch_id', $branchId) === (string) $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="examination_filter_year">Academic year</label>
                    <select id="examination_filter_year" name="academic_year_id" class="form-control">
                        <option value="">All academic years</option>
                        @foreach ($years as $year)
                            <option value="{{ $year->id }}" @selected(request('academic_year_id') == $year->id)>
                                {{ $year->branch?->name }} · {{ $year->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="examination_filter_status">Status</label>
                    <select id="examination_filter_status" name="status" class="form-control">
                        <option value="">All statuses</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                        <option value="published" @selected(request('status') === 'published')>Published</option>
                    </select>
                </div>
                <div class="form-group col-12 mb-0 d-flex justify-content-end">
                    <a class="btn btn-outline-secondary" href="{{ route('academic.examinations') }}">Clear filters</a>
                    <button class="btn btn-primary ml-2" type="submit">
                        <i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>Apply filters
                    </button>
                </div>
            </form>
        </x-admin.card>

        <section
            id="examination-create-form"
            class="admin-card p-3 p-md-4 examination-form-panel"
            @if (! $createFormOpen) hidden @endif
        >
            <div class="examination-section-heading">
                <div>
                    <h2>{{ $editingExam ? 'Edit examination' : 'Create an examination' }}</h2>
                    <p>Set the academic year, schedule, and publication status. Record student marks in Academic results.</p>
                </div>
            </div>

            <form method="post" action="{{ $editingExam ? route('academic.examinations.update', $editingExam->id) : route('academic.examinations.store') }}" class="form-row align-items-end">
                @csrf
                @if ($editingExam)
                    @method('PUT')
                @endif
                @if ($editingExam && $editingExamHasResults)
                    <input type="hidden" name="branch_id" value="{{ $editingExam->branch_id }}">
                @elseif (! $branchId || $editingExam)
                    <div class="form-group col-12 col-md-6 col-xl-3">
                        <label for="examination_branch_id">Branch <span class="text-danger">*</span></label>
                        <select id="examination_branch_id" name="branch_id" class="form-control @error('branch_id') is-invalid @enderror" required>
                            <option value="">Choose a branch</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected(old('branch_id', $editingExam?->branch_id ?? $branchId) == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                        @error('branch_id') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                @else
                    <input type="hidden" name="branch_id" value="{{ $branchId }}">
                @endif
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="examination_academic_year_id">Academic year <span class="text-danger">*</span></label>
                    @if ($editingExam && $editingExamHasResults)
                        <select id="examination_academic_year_id" class="form-control" disabled>
                            @foreach ($years as $year)
                                @if ($year->id === $editingExam->academic_year_id)
                                    <option value="{{ $year->id }}" selected>{{ $year->branch?->name }} · {{ $year->name }}</option>
                                @endif
                            @endforeach
                        </select>
                        <input type="hidden" name="academic_year_id" value="{{ $editingExam->academic_year_id }}">
                        <small class="text-muted">Branch and academic year are locked because results are linked.</small>
                    @else
                        <select id="examination_academic_year_id" name="academic_year_id" class="form-control @error('academic_year_id') is-invalid @enderror" required>
                            <option value="">Choose an academic year</option>
                            @foreach ($years as $year)
                                <option value="{{ $year->id }}" data-branch="{{ $year->branch_id }}" @selected(old('academic_year_id', $editingExam?->academic_year_id) == $year->id)>
                                    {{ $year->branch?->name }} · {{ $year->name }}
                                </option>
                            @endforeach
                        </select>
                    @endif
                    @error('academic_year_id') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="examination_name">Examination name <span class="text-danger">*</span></label>
                    <input id="examination_name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $editingExam?->name) }}" maxlength="150" placeholder="e.g. Mid-term assessment" required>
                    @error('name') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="examination_type">Assessment type <span class="text-danger">*</span></label>
                    <select id="examination_type" name="exam_type" class="form-control @error('exam_type') is-invalid @enderror" required>
                        @foreach (['term' => 'Term', 'midterm' => 'Mid-term', 'final' => 'Final', 'entrance' => 'Entrance', 'practical' => 'Practical'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('exam_type', $editingExam?->exam_type ?? 'term') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('exam_type') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="examination_starts_on">Starts on</label>
                    <input id="examination_starts_on" type="text" name="starts_on" class="form-control js-datepicker @error('starts_on') is-invalid @enderror" value="{{ old('starts_on', $editingExam?->starts_on ? \Illuminate\Support\Carbon::parse($editingExam->starts_on)->format('d/m/Y') : '') }}" placeholder="DD/MM/YYYY" autocomplete="off">
                    @error('starts_on') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="examination_ends_on">Ends on</label>
                    <input id="examination_ends_on" type="text" name="ends_on" class="form-control js-datepicker @error('ends_on') is-invalid @enderror" value="{{ old('ends_on', $editingExam?->ends_on ? \Illuminate\Support\Carbon::parse($editingExam->ends_on)->format('d/m/Y') : '') }}" placeholder="DD/MM/YYYY" autocomplete="off">
                    @error('ends_on') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="examination_status">Status <span class="text-danger">*</span></label>
                    <select id="examination_status" name="status" class="form-control @error('status') is-invalid @enderror" required>
                        <option value="draft" @selected(old('status', $editingExam?->status ?? 'draft') === 'draft')>Draft</option>
                        <option value="published" @selected(old('status', $editingExam?->status ?? 'draft') === 'published')>Published</option>
                    </select>
                    @error('status') <small class="text-danger">{{ $message }}</small> @enderror
                </div>
                <div class="form-group col-12 mb-0 d-flex justify-content-end">
                    @if ($editingExam)
                        <a class="btn btn-outline-secondary mr-2" href="{{ route('academic.examinations', ['branch_id' => $editingExam->branch_id]) }}">Cancel</a>
                    @endif
                    <button class="btn btn-primary" type="submit">
                        <i class="fa-solid fa-floppy-disk mr-2" aria-hidden="true"></i>{{ $editingExam ? 'Save changes' : 'Create examination' }}
                    </button>
                </div>
            </form>
        </section>

        <x-admin.card padding="false" class="examination-list-card">
            <div class="examination-section-heading px-3 px-md-4 pt-3">
                <div>
                    <h2>Examination list</h2>
                    <p>Edit assessment details or publish an examination. Record marks from Academic results.</p>
                </div>
                <span class="results-count-pill">{{ number_format($exams->total()) }} {{ \Illuminate\Support\Str::plural('examination', $exams->total()) }}</span>
            </div>
            <div class="table-responsive">
                <x-admin.table>
                    <thead>
                        <tr>
                            <th>Examination</th>
                            <th>Branch / academic year</th>
                            <th>Schedule</th>
                            <th>Subject results</th>
                            <th>Status</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($exams as $exam)
                            <tr>
                                <td>
                                    <strong>{{ $exam->name }}</strong>
                                    <small class="d-block text-muted">{{ \Illuminate\Support\Str::headline($exam->exam_type) }}</small>
                                </td>
                                <td>
                                    {{ $exam->branch_name }}
                                    <small class="d-block text-muted">{{ $exam->year_name }}</small>
                                </td>
                                <td>
                                    @if ($exam->starts_on || $exam->ends_on)
                                        {{ $exam->starts_on ? \Illuminate\Support\Carbon::parse($exam->starts_on)->format('d M Y') : '—' }}
                                        <small class="d-block text-muted">to {{ $exam->ends_on ? \Illuminate\Support\Carbon::parse($exam->ends_on)->format('d M Y') : '—' }}</small>
                                    @else
                                        <span class="text-muted">Not scheduled</span>
                                    @endif
                                </td>
                                <td>{{ number_format($exam->linked_result_count) }}</td>
                                <td>
                                    <x-admin.badge :tone="$exam->status === 'published' ? 'success' : 'warning'">{{ ucfirst($exam->status) }}</x-admin.badge>
                                </td>
                                <td class="text-right">
                                    <div class="examination-row-actions">
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('academic.examinations', ['branch_id' => $exam->branch_id, 'edit' => $exam->id]) }}">
                                            <i class="fa-solid fa-pen mr-1" aria-hidden="true"></i>Edit
                                        </a>
                                        @if ($exam->status !== 'published')
                                            <form method="post" action="{{ route('academic.examinations.publish', $exam->id) }}" onsubmit="return confirm('Publish this examination and its linked results?')">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-primary" type="submit">
                                                    <i class="fa-solid fa-paper-plane mr-1" aria-hidden="true"></i>Publish
                                                </button>
                                            </form>
                                        @else
                                            <x-admin.badge tone="success">Published</x-admin.badge>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-4">
                                    <x-admin.empty-state
                                        title="No examinations found"
                                        description="Create an examination or adjust the filters to view existing assessments."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
            </div>
            @if ($exams->hasPages())
                <div class="p-3">
                    <x-admin.pagination :paginator="$exams" />
                </div>
            @endif
        </x-admin.card>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const branchSelect = document.querySelector('#examination_branch_id');
            const yearSelect = document.querySelector('#examination_academic_year_id');

            const filterAcademicYears = function () {
                if (! branchSelect || ! yearSelect || yearSelect.disabled) {
                    return;
                }

                Array.from(yearSelect.options).forEach(function (option) {
                    option.hidden = option.value !== ''
                        && branchSelect.value !== ''
                        && option.dataset.branch !== branchSelect.value;

                    if (option.hidden && option.selected) {
                        yearSelect.value = '';
                    }
                });
            };

            branchSelect?.addEventListener('change', filterAcademicYears);
            filterAcademicYears();

            document.querySelectorAll('[data-examination-form-toggle]').forEach(function (toggle) {
                const panel = document.getElementById(toggle.getAttribute('aria-controls'));
                const label = toggle.querySelector('[data-examination-form-label]');
                const collapsedLabel = toggle.dataset.collapsedLabel || 'Create examination';

                toggle.addEventListener('click', function () {
                    const expanded = toggle.getAttribute('aria-expanded') === 'true';
                    toggle.setAttribute('aria-expanded', String(! expanded));
                    panel.hidden = expanded;
                    label.textContent = expanded ? collapsedLabel : 'Hide form';

                    if (! expanded) {
                        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                        panel.querySelector('input:not([type="hidden"]), select')?.focus({ preventScroll: true });
                    }
                });
            });
        });
    </script>
@endsection
