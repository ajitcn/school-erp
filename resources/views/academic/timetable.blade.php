@extends('layouts.app')

@section('content')
    @php
        $periodFormOpen = $errors->has('branch_id')
            || $errors->has('academic_year_id')
            || $errors->has('school_class_id')
            || $errors->has('section_id')
            || $errors->has('teacher_id')
            || $errors->has('weekday')
            || $errors->has('starts_at')
            || $errors->has('ends_at')
            || $errors->has('subject');
        $periodFormOpen = $periodFormOpen || isset($editingPeriod);
    @endphp

    <div class="timetable-page">
        <x-admin.page-header
            title="Class timetable"
            description="Plan weekly class periods, assign teachers, and check schedules for conflicts."
        >
            <x-slot:actions>
                <button
                    class="btn btn-primary"
                    type="button"
                    data-period-form-toggle
                    aria-controls="timetable-period-form"
                    aria-expanded="{{ $periodFormOpen ? 'true' : 'false' }}"
                >
                    <i class="fa-solid {{ $editingPeriod ? 'fa-pen-to-square' : 'fa-plus' }} mr-2" aria-hidden="true"></i>
                    <span data-period-form-label>{{ $periodFormOpen ? 'Hide form' : 'Add period' }}</span>
                </button>
            </x-slot:actions>
        </x-admin.page-header>

        <section class="timetable-summary-grid" aria-label="Timetable summary">
            <x-admin.stat-card
                label="Scheduled periods"
                :value="number_format($periodCount)"
                detail="Matching the current filters"
                tone="primary"
                icon="fa-calendar-week"
            />
            <x-admin.stat-card
                label="Classes scheduled"
                :value="number_format($scheduledClassCount)"
                detail="Unique classes in this view"
                tone="info"
                icon="fa-users"
            />
            <x-admin.stat-card
                label="Teachers assigned"
                :value="number_format($scheduledTeacherCount)"
                detail="Unique teachers in this view"
                tone="success"
                icon="fa-chalkboard-user"
            />
        </section>

        <x-admin.card class="timetable-filter-card">
            <form
                method="get"
                action="{{ route('academic.timetable') }}"
                class="form-row align-items-end js-filter-form"
                aria-label="Timetable filters"
            >
                <div class="form-group col-12 col-md-4 col-xl-3">
                    <label for="filter_branch_id">Campus</label>
                    <select id="filter_branch_id" name="branch_id" class="form-control">
                        <option value="">All accessible campuses</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected(request('branch_id') == $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-12 col-md-4 col-xl-3">
                    <label for="filter_academic_year_id">Academic year</label>
                    <select id="filter_academic_year_id" name="academic_year_id" class="form-control">
                        <option value="">All academic years</option>
                        @foreach ($years as $year)
                            <option value="{{ $year->id }}" data-branch="{{ $year->branch_id }}" @selected(request('academic_year_id') == $year->id)>
                                {{ $year->branch?->name }} · {{ $year->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-12 col-md-4 col-xl-2">
                    <label for="filter_school_class_id">Class</label>
                    <select id="filter_school_class_id" name="school_class_id" class="form-control">
                        <option value="">All classes</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" data-branch="{{ $class->branch_id }}" data-year="{{ $class->academic_year_id }}" @selected(request('school_class_id') == $class->id)>
                                {{ $class->branch?->name }} · {{ $class->name }} · {{ $class->academicYear?->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-12 col-md-4 col-xl-2">
                    <label for="filter_section_id">Section</label>
                    <select id="filter_section_id" name="section_id" class="form-control">
                        <option value="">All sections</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" data-branch="{{ $section->branch_id }}" @selected(request('section_id') == $section->id)>
                                {{ $section->branch?->name }} · {{ $section->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-12 col-md-4 col-xl-2">
                    <label for="filter_weekday">Weekday</label>
                    <select id="filter_weekday" name="weekday" class="form-control">
                        <option value="">All weekdays</option>
                        @foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)
                            <option value="{{ $day }}" @selected(request('weekday') === $day)>{{ $day }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-12 mb-0 d-flex justify-content-end">
                    <a href="{{ route('academic.timetable') }}" class="btn btn-outline-secondary">Clear filters</a>
                    <button type="submit" class="btn btn-primary ml-2">Apply filters</button>
                </div>
            </form>
        </x-admin.card>

        <div id="timetable-period-form" class="admin-card p-3 p-md-4 timetable-form-panel" @if (! $periodFormOpen) hidden @endif>
                <div class="timetable-section-heading">
                    <div>
                        <h2>{{ $editingPeriod ? 'Edit timetable period' : 'Add a timetable period' }}</h2>
                        <p>{{ $editingPeriod ? 'Update this period. Schedule conflicts are checked before saving.' : 'Set the campus, academic group, teacher, and time for one weekly class.' }}</p>
                    </div>
                </div>

                <form
                    method="post"
                    action="{{ $editingPeriod ? route('academic.timetable.update', $editingPeriod) : route('academic.timetable.store') }}"
                    class="timetable-form"
                    data-timetable-form
                >
                    @csrf
                    @if ($editingPeriod)
                        @method('put')
                    @endif
                    <div class="form-row">
                        <div class="form-group col-12 col-md-6 col-xl-4">
                            <label for="period_branch_id">Campus <span class="text-danger">*</span></label>
                            <select id="period_branch_id" name="branch_id" class="form-control" required data-timetable-branch>
                                <option value="">Choose campus</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(old('branch_id', $editingPeriod?->branch_id ?? $branchId) == $branch->id)>
                                        {{ $branch->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group col-12 col-md-6 col-xl-4">
                            <label for="period_academic_year_id">Academic year <span class="text-danger">*</span></label>
                            <select id="period_academic_year_id" name="academic_year_id" class="form-control" required data-timetable-year>
                                <option value="">Choose academic year</option>
                                @foreach ($years as $year)
                                    <option value="{{ $year->id }}" data-branch="{{ $year->branch_id }}" @selected(old('academic_year_id', $editingPeriod?->academic_year_id) == $year->id)>
                                        {{ $year->branch?->name }} · {{ $year->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('academic_year_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group col-12 col-md-6 col-xl-4">
                            <label for="period_school_class_id">Class <span class="text-danger">*</span></label>
                            <select id="period_school_class_id" name="school_class_id" class="form-control" required data-timetable-class>
                                <option value="">Choose class</option>
                                @foreach ($classes as $class)
                                    <option value="{{ $class->id }}" data-branch="{{ $class->branch_id }}" data-year="{{ $class->academic_year_id }}" @selected(old('school_class_id', $editingPeriod?->school_class_id) == $class->id)>
                                        {{ $class->branch?->name }} · {{ $class->name }} · {{ $class->academicYear?->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('school_class_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group col-12 col-md-6 col-xl-4">
                            <label for="period_section_id">Section <span class="text-muted font-weight-normal">(optional)</span></label>
                            <select id="period_section_id" name="section_id" class="form-control" data-timetable-section>
                                <option value="">All sections</option>
                                @foreach ($sections as $section)
                                    <option value="{{ $section->id }}" data-branch="{{ $section->branch_id }}" @selected(old('section_id', $editingPeriod?->section_id) == $section->id)>
                                        {{ $section->branch?->name }} · {{ $section->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('section_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group col-12 col-md-6 col-xl-4">
                            <label for="period_teacher_id">Teacher <span class="text-muted font-weight-normal">(optional)</span></label>
                            <select id="period_teacher_id" name="teacher_id" class="form-control" data-timetable-teacher>
                                <option value="">Not assigned</option>
                                @foreach ($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" data-branch="{{ $teacher->branch_id }}" @selected(old('teacher_id', $editingPeriod?->teacher_id) == $teacher->id)>
                                        {{ $teacher->user?->name ?? 'Teacher account unavailable' }} · {{ $teacher->branch?->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('teacher_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group col-12 col-md-6 col-xl-4">
                            <label for="period_weekday">Weekday <span class="text-danger">*</span></label>
                            <select id="period_weekday" name="weekday" class="form-control" required>
                                @foreach (['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as $day)
                                    <option value="{{ $day }}" @selected(old('weekday', $editingPeriod?->weekday ?? 'Monday') === $day)>{{ $day }}</option>
                                @endforeach
                            </select>
                            @error('weekday') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group col-12 col-md-6 col-xl-4">
                            <label for="period_starts_at">Starts at <span class="text-danger">*</span></label>
                            <input id="period_starts_at" name="starts_at" type="time" class="form-control" value="{{ old('starts_at', $editingPeriod ? substr($editingPeriod->starts_at, 0, 5) : '') }}" required>
                            @error('starts_at') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group col-12 col-md-6 col-xl-4">
                            <label for="period_ends_at">Ends at <span class="text-danger">*</span></label>
                            <input id="period_ends_at" name="ends_at" type="time" class="form-control" value="{{ old('ends_at', $editingPeriod ? substr($editingPeriod->ends_at, 0, 5) : '') }}" required>
                            @error('ends_at') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                        <div class="form-group col-12 col-md-6 col-xl-4">
                            <label for="period_subject">Subject or activity <span class="text-muted font-weight-normal">(optional)</span></label>
                            <input id="period_subject" name="subject" class="form-control" value="{{ old('subject', $editingPeriod?->subject) }}" maxlength="100" placeholder="e.g. Mathematics">
                            @error('subject') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    </div>

                    <div class="timetable-form-actions">
                        @if ($editingPeriod)
                            <a class="btn btn-outline-secondary" href="{{ route('academic.timetable', request()->except('edit')) }}">Cancel</a>
                        @else
                            <button class="btn btn-outline-secondary" type="button" data-period-form-close>Cancel</button>
                        @endif
                        <button class="btn btn-primary" type="submit">
                            <i class="fa-solid {{ $editingPeriod ? 'fa-floppy-disk' : 'fa-calendar-plus' }} mr-2" aria-hidden="true"></i>
                            {{ $editingPeriod ? 'Save changes' : 'Save period' }}
                        </button>
                    </div>
                </form>
        </div>

        <x-admin.card padding="false" class="timetable-list-card">
            <div class="timetable-list-heading">
                <div>
                    <h2>Weekly schedule</h2>
                    <p>Periods are shown by weekday and start time. Use Filters to narrow the list.</p>
                </div>
                <span class="timetable-count-pill">{{ number_format($items->total()) }} {{ \Illuminate\Support\Str::plural('period', $items->total()) }}</span>
            </div>

            <div class="table-responsive">
                <x-admin.table>
                    <thead>
                        <tr>
                            <th>Day and time</th>
                            <th>Campus</th>
                            <th>Class and section</th>
                            <th>Subject / activity</th>
                            <th>Teacher</th>
                            <th>Academic year</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->weekday }}</strong>
                                    <small class="d-block text-muted">{{ substr($item->starts_at, 0, 5) }}–{{ substr($item->ends_at, 0, 5) }}</small>
                                </td>
                                <td>{{ $item->branch?->name ?? '—' }}</td>
                                <td>
                                    <strong>{{ $item->schoolClass?->name ?? '—' }}</strong>
                                    @if ($item->section)
                                        <span class="timetable-section-chip">Section {{ $item->section->name }}</span>
                                    @else
                                        <small class="d-block text-muted">All sections</small>
                                    @endif
                                </td>
                                <td>{{ $item->subject ?: '—' }}</td>
                                <td>{{ $item->teacher?->user?->name ?? 'Not assigned' }}</td>
                                <td>{{ $item->academicYear?->name ?? '—' }}</td>
                                <td class="text-right">
                                    <a
                                        class="btn btn-sm btn-outline-primary timetable-edit-button"
                                        href="{{ route('academic.timetable', array_merge(request()->query(), ['edit' => $item->id])) }}"
                                        aria-label="Edit timetable period for {{ $item->schoolClass?->name ?? 'class' }} on {{ $item->weekday }}"
                                    >
                                        <i class="fa-solid fa-pen-to-square mr-1" aria-hidden="true"></i>Edit
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-4">
                                    <x-admin.empty-state
                                        title="No timetable periods found"
                                        description="Add a period or change the selected filters to see the weekly schedule."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
            </div>

            @if ($items->hasPages())
                <div class="p-3">
                    <x-admin.pagination :paginator="$items" />
                </div>
            @endif
        </x-admin.card>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('[data-timetable-form]');
            const formPanel = document.querySelector('#timetable-period-form');
            const formToggle = document.querySelector('[data-period-form-toggle]');
            const formLabel = document.querySelector('[data-period-form-label]');
            const formClose = document.querySelector('[data-period-form-close]');

            const setFormVisibility = function (isVisible) {
                if (! formPanel || ! formToggle || ! formLabel) {
                    return;
                }

                formPanel.hidden = ! isVisible;
                formToggle.setAttribute('aria-expanded', String(isVisible));
                formLabel.textContent = isVisible ? 'Hide form' : 'Add period';

                if (isVisible) {
                    formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    form.querySelector('select, input:not([type="hidden"])')?.focus({ preventScroll: true });
                }
            };

            formToggle?.addEventListener('click', function () {
                setFormVisibility(formPanel?.hidden ?? false);
            });
            formClose?.addEventListener('click', function () {
                setFormVisibility(false);
            });

            const branch = form?.querySelector('[data-timetable-branch]');
            const year = form?.querySelector('[data-timetable-year]');
            const schoolClass = form?.querySelector('[data-timetable-class]');
            const section = form?.querySelector('[data-timetable-section]');
            const teacher = form?.querySelector('[data-timetable-teacher]');

            if (branch && year && schoolClass && section && teacher) {
                const filterOptions = function (select, predicate) {
                    Array.from(select.options).forEach(function (option) {
                        option.hidden = option.value !== '' && ! predicate(option);

                        if (option.hidden && option.selected) {
                            select.value = '';
                        }
                    });
                };

                const updateAcademicChoices = function () {
                    const branchId = branch.value;
                    filterOptions(year, (option) => ! branchId || option.dataset.branch === branchId);
                    filterOptions(section, (option) => ! branchId || option.dataset.branch === branchId);
                    filterOptions(teacher, (option) => ! branchId || option.dataset.branch === branchId);

                    const academicYearId = year.value;
                    filterOptions(schoolClass, (option) => (! branchId || option.dataset.branch === branchId)
                        && (! academicYearId || option.dataset.year === academicYearId));
                };

                branch.addEventListener('change', updateAcademicChoices);
                year.addEventListener('change', updateAcademicChoices);
                updateAcademicChoices();
            }

            const filterBranch = document.querySelector('#filter_branch_id');
            const filterYear = document.querySelector('#filter_academic_year_id');
            const filterClass = document.querySelector('#filter_school_class_id');
            const filterSection = document.querySelector('#filter_section_id');

            if (filterBranch && filterYear && filterClass && filterSection) {
                const filterOptions = function (select, predicate) {
                    Array.from(select.options).forEach(function (option) {
                        option.hidden = option.value !== '' && ! predicate(option);

                        if (option.hidden && option.selected) {
                            select.value = '';
                        }
                    });
                };

                const updateFilterChoices = function () {
                    const branchId = filterBranch.value;
                    filterOptions(filterYear, (option) => ! branchId || option.dataset.branch === branchId);
                    filterOptions(filterSection, (option) => ! branchId || option.dataset.branch === branchId);

                    const academicYearId = filterYear.value;
                    filterOptions(filterClass, (option) => (! branchId || option.dataset.branch === branchId)
                        && (! academicYearId || option.dataset.year === academicYearId));
                };

                filterBranch.addEventListener('change', updateFilterChoices);
                filterYear.addEventListener('change', updateFilterChoices);
                updateFilterChoices();
            }
        });
    </script>
@endsection
