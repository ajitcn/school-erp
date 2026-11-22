@extends('layouts.app')

@section('content')
    @php
        $isEditingResult = isset($editingRecords) && $editingRecords->isNotEmpty();
        $subjectValidationFailed = collect(array_keys($errors->getMessages()))
            ->contains(fn ($key) => str_starts_with($key, 'subjects.'));
        $resultFormOpen = $isEditingResult
            || $errors->has('student_id')
            || $errors->has('academic_year_id')
            || $errors->has('school_class_id')
            || $errors->has('section_id')
            || $errors->has('course_id')
            || $errors->has('semester_id')
            || $errors->has('examination_id')
            || $errors->has('subjects')
            || $subjectValidationFailed
            || $errors->has('status');
        $defaultSubjectRows = $isEditingResult
            ? $editingRecords->map(fn ($record) => ['subject_id' => $record->subject_id, 'marks' => $record->marks, 'marks_obtained' => $record->marks_obtained, 'grade' => $record->grade])->all()
            : [['subject_id' => '', 'marks' => '', 'marks_obtained' => '', 'grade' => '']];
        $subjectRows = collect(old('subjects', $defaultSubjectRows))
            ->map(fn ($row) => is_array($row) ? $row : [])
            ->values();

        if ($subjectRows->isEmpty()) {
            $subjectRows->push(['subject_id' => '', 'marks' => '', 'marks_obtained' => '', 'grade' => '']);
        }
    @endphp

    <div class="academic-results-page">
        <x-admin.page-header
            title="Examination results"
            description="Record marks and grades for one or more subjects, then publish results for students and parents to view."
        >
            <x-slot:actions>
                <button class="btn btn-outline-primary" type="button" data-toggle="collapse" data-target="#result-import-panel" aria-expanded="false" aria-controls="result-import-panel"><i class="fa-solid fa-file-import mr-2" aria-hidden="true"></i>Import results</button>
                @if ($subjectMastersReady)
                    <button
                        class="btn btn-primary"
                        type="button"
                        data-result-form-toggle
                        aria-controls="academic-result-form-panel"
                        aria-expanded="{{ $resultFormOpen ? 'true' : 'false' }}"
                    >
                        <i class="fa-solid fa-plus mr-2" aria-hidden="true"></i>
                        <span data-result-form-label>{{ $resultFormOpen ? 'Hide form' : 'Record result' }}</span>
                    </button>
                @endif
            </x-slot:actions>
        </x-admin.page-header>

        @unless ($subjectMastersReady)
            <div class="alert alert-warning mb-0" role="alert">
                <strong>Subject setup is pending.</strong>
                Run <code>php artisan migrate --force</code> from the project folder to create the subject master tables and enable result entry.
            </div>
        @endunless

        @if ($subjectMastersReady && ! $examinationLinkReady)
            <div class="alert alert-info mb-0" role="status">
                Results can be recorded now. Apply the pending migrations to link a result to an examination.
            </div>
        @endif

        <section id="result-import-panel" class="collapse {{ $errors->any() && old('_result_import') ? 'show' : '' }} mb-4">
            <x-admin.card>
                <h2 class="h5">Import subject results</h2>
                <p class="text-muted">Select the academic group and upload one row per student and subject. Results are saved as drafts. Edit existing results from the results list.</p>
                <p class="small text-muted">Choose the academic group, then download a sheet with student names and subjects already filled in. Enter total and obtained marks together, or enter a grade. Leave rows blank to skip them.</p>
                @if ($errors->any() && old('_result_import'))
                    <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
                @endif
                <form method="post" action="{{ route('academic.results.import.store') }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="_result_import" value="1">
                    <div class="form-row">
                        <div class="form-group col-12 col-md-4"><label for="import_year">Academic year <span class="text-danger">*</span></label><select class="form-control" id="import_year" name="academic_year_id" required><option value="">Choose academic year</option>@foreach ($years as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id)>{{ $year->branch?->name }} · {{ $year->name }}</option>@endforeach</select></div>
                        <div class="form-group col-12 col-md-4"><label for="import_exam">Examination (optional)</label><select class="form-control" id="import_exam" name="examination_id"><option value="">No examination</option>@foreach ($examinations as $exam)<option value="{{ $exam->id }}" @selected(old('examination_id') == $exam->id)>{{ $exam->branch_name }} · {{ $exam->name }} · {{ $exam->year_name }}</option>@endforeach</select></div>
                        <div class="form-group col-12 col-md-4"><label for="import_class">Class (optional)</label><select class="form-control" id="import_class" name="school_class_id"><option value="">Choose class</option>@foreach ($classes as $class)<option value="{{ $class->id }}" @selected(old('school_class_id') == $class->id)>{{ $class->branch?->name }} · {{ $class->name }}</option>@endforeach</select></div>
                        <div class="form-group col-12 col-md-4"><label for="import_section">Section (optional)</label><select class="form-control" id="import_section" name="section_id"><option value="">Choose section</option>@foreach ($sections as $section)<option value="{{ $section->id }}" @selected(old('section_id') == $section->id)>{{ $section->branch?->name }} · {{ $section->name }}</option>@endforeach</select></div>
                        <div class="form-group col-12 col-md-4"><label for="import_course">Course (optional)</label><select class="form-control" id="import_course" name="course_id"><option value="">Choose course</option>@foreach ($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id') == $course->id)>{{ $course->branch?->name }} · {{ $course->name }}</option>@endforeach</select></div>
                        <div class="form-group col-12 col-md-4"><label for="import_semester">Semester (optional)</label><select class="form-control" id="import_semester" name="semester_id"><option value="">Choose semester</option>@foreach ($semesters as $semester)<option value="{{ $semester->id }}" @selected(old('semester_id') == $semester->id)>{{ $semester->course?->name }} · {{ $semester->name }}</option>@endforeach</select></div>
                        <div class="form-group col-12"><label for="import_result_file">Result sheet <span class="text-danger">*</span></label><input class="form-control" type="file" id="import_result_file" name="file" accept=".csv,.txt,.xlsx" required></div>
                    </div>
                    <button class="btn btn-outline-primary mr-2" type="button" data-result-template-url="{{ route('academic.results.import.template') }}">Download student sheet</button>
                    <span class="text-danger small d-block mt-2" data-result-template-error role="alert" hidden></span>
                    <button class="btn btn-primary" type="submit">Import results</button>
                </form>
            </x-admin.card>
        </section>
        <script>
            document.querySelector('[data-result-template-url]')?.addEventListener('click', function () {
                const panel = this.closest('#result-import-panel');
                const error = panel.querySelector('[data-result-template-error]');
                const year = panel.querySelector('#import_year').value;
                const schoolClass = panel.querySelector('#import_class').value;
                const course = panel.querySelector('#import_course').value;
                if (!year || (!schoolClass && !course)) {
                    error.textContent = 'Choose an academic year and a class or course first.';
                    error.hidden = false;
                    return;
                }
                error.hidden = true;
                const url = new URL(this.dataset.resultTemplateUrl, window.location.origin);
                url.searchParams.set('_result_import', '1');
                ['academic_year_id', 'school_class_id', 'section_id', 'course_id', 'semester_id'].forEach(function (name) {
                    const value = panel.querySelector('[name="' + name + '"]').value;
                    if (value) url.searchParams.set(name, value);
                });
                window.location.assign(url.toString());
            });
        </script>
        <section class="results-summary-grid" aria-label="Result summary">
            <x-admin.stat-card
                label="Results"
                :value="number_format($resultCount)"
                detail="Matching the current filters"
                tone="primary"
                icon="fa-clipboard-list"
            />
            <x-admin.stat-card
                label="Published"
                :value="number_format($publishedResultCount)"
                detail="Visible to students and parents"
                tone="success"
                icon="fa-circle-check"
            />
            <x-admin.stat-card
                label="Drafts"
                :value="number_format($draftResultCount)"
                detail="Saved, but not yet published"
                tone="warning"
                icon="fa-pen-to-square"
            />
        </section>

        <section
            id="academic-results-filters"
            class="admin-card p-3 p-md-4 mb-4 results-filter-card"
            aria-label="Result filters"
            hidden
        >
            <form
                method="get"
                action="{{ route('academic.results') }}"
                class="form-row align-items-end results-filter-grid js-filter-form"
                aria-label="Result filters"
            >
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="result_filter_branch">Branch</label>
                    <select id="result_filter_branch" name="branch_id" class="form-control" data-result-filter-branch>
                        <option value="">All accessible branches</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected((string) request('branch_id', $branchId) === (string) $branch->id)>{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="result_filter_year">Academic year</label>
                    <select id="result_filter_year" name="academic_year_id" class="form-control" data-result-filter-year>
                        <option value="">All academic years</option>
                        @foreach ($years as $year)
                            <option value="{{ $year->id }}" data-branch="{{ $year->branch_id }}" @selected(request('academic_year_id') == $year->id)>
                                {{ $year->branch?->name }} · {{ $year->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if ($examinationLinkReady)
                    <div class="form-group col-12 col-md-6 col-xl-3">
                        <label for="result_filter_examination">Examination</label>
                        <select id="result_filter_examination" name="examination_id" class="form-control" data-result-filter-examination>
                            <option value="">All examinations</option>
                            @foreach ($examinations as $examination)
                                <option value="{{ $examination->id }}" data-branch="{{ $examination->branch_id }}" data-year="{{ $examination->academic_year_id }}" @selected(request('examination_id') == $examination->id)>
                                    {{ $examination->name }} · {{ $examination->year_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="result_filter_class">Class</label>
                    <select id="result_filter_class" name="school_class_id" class="form-control" data-result-filter-class>
                        <option value="">All classes</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" data-branch="{{ $class->branch_id }}" data-year="{{ $class->academic_year_id }}" @selected(request('school_class_id') == $class->id)>
                                {{ $class->branch?->name }} · {{ $class->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="result_filter_section">Section</label>
                    <select id="result_filter_section" name="section_id" class="form-control" data-result-filter-section>
                        <option value="">All sections</option>
                        @foreach ($sections as $section)
                            <option value="{{ $section->id }}" data-branch="{{ $section->branch_id }}" @selected(request('section_id') == $section->id)>
                                {{ $section->branch?->name }} · {{ $section->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="result_filter_course">Course</label>
                    <select id="result_filter_course" name="course_id" class="form-control" data-result-filter-course>
                        <option value="">All courses</option>
                        @foreach ($courses as $course)
                            <option value="{{ $course->id }}" data-branch="{{ $course->branch_id }}" @selected(request('course_id') == $course->id)>
                                {{ $course->branch?->name }} · {{ $course->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="result_filter_semester">Semester</label>
                    <select id="result_filter_semester" name="semester_id" class="form-control" data-result-filter-semester>
                        <option value="">All semesters</option>
                        @foreach ($semesters as $semester)
                            <option value="{{ $semester->id }}" data-branch="{{ $semester->course?->branch_id }}" data-course="{{ $semester->course_id }}" @selected(request('semester_id') == $semester->id)>
                                {{ $semester->course?->name }} · {{ $semester->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if ($subjectMastersReady)
                    <div class="form-group col-12 col-md-6 col-xl-3">
                        <label for="result_filter_subject">Subject</label>
                        <select id="result_filter_subject" name="subject_id" class="form-control" data-result-filter-subject>
                            <option value="">All subjects</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" data-branch="{{ $subject->branch_id }}" data-class="{{ $subject->school_class_id }}" data-course="{{ $subject->course_id }}" @selected(request('subject_id') == $subject->id)>
                                    {{ $subject->name }} · {{ $subject->schoolClass?->name ?? $subject->course?->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="result_filter_student">Student</label>
                    <input id="result_filter_student" name="student" type="search" class="form-control" value="{{ request('student') }}" maxlength="100" placeholder="Name or student ID">
                </div>
                <div class="form-group col-12 col-md-6 col-xl-3">
                    <label for="result_filter_status">Result status</label>
                    <select id="result_filter_status" name="status" class="form-control">
                        <option value="">All results</option>
                        <option value="published" @selected(request('status') === 'published')>Published</option>
                        <option value="draft" @selected(request('status') === 'draft')>Draft</option>
                    </select>
                </div>
                <div class="form-group col-12 mb-0 d-flex justify-content-end">
                    <a href="{{ route('academic.results') }}" class="btn btn-outline-secondary">Clear filters</a>
                    <button type="submit" class="btn btn-primary ml-2">
                        <i class="fa-solid fa-filter mr-1" aria-hidden="true"></i>Apply filters
                    </button>
                </div>
            </form>
        </section>

        <div id="academic-result-form-panel" class="admin-card p-3 p-md-4 result-form-panel" @if (! $resultFormOpen) hidden @endif>
            <div class="result-form-heading">
                <div>
                    <h2>{{ $isEditingResult ? 'Edit student results' : 'Record student results' }}</h2>
                    <p>Choose the academic year, class and section, then search for a student and enter subject results.</p>
                </div>
            </div>

            <form method="post" action="{{ $isEditingResult ? route('academic.results.update', ['student' => $editingRecords->first()->student_id, 'academicYear' => $editingRecords->first()->academic_year_id] + request()->query()) : route('academic.results.store') }}" class="result-entry-form" data-result-entry-form>
                @csrf
                @if ($isEditingResult)
                    @method('PUT')
                @endif
                <input type="hidden" id="result_student_id" name="student_id" value="{{ $selectedStudent?->id }}" data-selected-student-id>

                <div class="form-row">
                    <div class="form-group col-12 col-lg-6">
                        <label for="result_academic_year_id">Academic year <span class="text-danger">*</span></label>
                        <select id="result_academic_year_id" name="academic_year_id" class="form-control" required data-result-year>
                            <option value="">Choose academic year</option>
                            @foreach ($years as $year)
                                <option value="{{ $year->id }}" data-branch="{{ $year->branch_id }}" @selected(old('academic_year_id', $editingRecords->first()?->academic_year_id) == $year->id)>
                                    {{ $year->branch?->name }} · {{ $year->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('academic_year_id') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    @if ($examinationLinkReady)
                        <div class="form-group col-12 col-lg-6">
                            <label for="result_examination_id">Examination <span class="text-muted font-weight-normal">(optional)</span></label>
                            <select id="result_examination_id" name="examination_id" class="form-control" data-result-examination @disabled($isEditingResult)>
                                <option value="">No examination linked</option>
                                @foreach ($examinations as $examination)
                                    <option
                                        value="{{ $examination->id }}"
                                        data-branch="{{ $examination->branch_id }}"
                                        data-year="{{ $examination->academic_year_id }}"
                                        @selected(old('examination_id', $editingRecords->first()?->examination_id) == $examination->id)
                                    >
                                        {{ $examination->branch_name }} · {{ $examination->name }} · {{ $examination->year_name }} · {{ ucfirst($examination->status) }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($isEditingResult)
                                <input type="hidden" name="examination_id" value="{{ $editingRecords->first()->examination_id }}">
                            @endif
                            @error('examination_id') <small class="text-danger">{{ $message }}</small> @enderror
                        </div>
                    @endif
                    <div class="form-group col-12 col-md-6">
                        <label for="result_school_class_id">Class <span class="text-muted font-weight-normal">(optional)</span></label>
                        <select id="result_school_class_id" name="school_class_id" class="form-control" data-result-class>
                            <option value="">Choose a class</option>
                            @foreach ($classes as $class)
                                <option
                                    value="{{ $class->id }}"
                                    data-branch="{{ $class->branch_id }}"
                                    data-year="{{ $class->academic_year_id }}"
                                    @selected(old('school_class_id', $editingRecords->first()?->school_class_id) == $class->id)
                                >
                                    {{ $class->branch?->name }} · {{ $class->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('school_class_id') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-group col-12 col-md-6">
                        <label for="result_section_id">Section <span class="text-muted font-weight-normal">(optional)</span></label>
                        <select id="result_section_id" name="section_id" class="form-control" data-result-section>
                            <option value="">Choose a section</option>
                            @foreach ($sections as $section)
                                <option
                                    value="{{ $section->id }}"
                                    data-branch="{{ $section->branch_id }}"
                                    @selected(old('section_id', $editingRecords->first()?->section_id) == $section->id)
                                >
                                    {{ $section->branch?->name }} · {{ $section->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('section_id') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-group col-12 col-lg-6">
                        <label for="result_student_search">Student <span class="text-danger">*</span></label>
                        <div class="academic-student-picker" data-academic-student-picker>
                            <input
                                id="result_student_search"
                                type="search"
                                class="form-control @error('student_id') is-invalid @enderror"
                                value="{{ $selectedStudent ? trim($selectedStudent->first_name.' '.$selectedStudent->last_name).' · '.$selectedStudent->student_id : '' }}"
                                placeholder="Type a student name or ID"
                                autocomplete="off"
                                role="combobox"
                                aria-haspopup="listbox"
                                aria-autocomplete="list"
                                aria-expanded="false"
                                aria-controls="academic-student-search-results"
                                data-academic-student-search
                                data-search-url="{{ route('academic.students.search') }}"
                                data-branch-id="{{ $branchId }}"
                                data-selected-branch-id="{{ $selectedStudent?->branch_id }}"
                            >
                            <div
                                id="academic-student-search-results"
                                class="academic-student-results"
                                role="listbox"
                                aria-label="Matching students"
                                data-academic-student-results
                                hidden
                            ></div>
                        </div>
                        <small class="form-text text-muted" data-academic-student-status aria-live="polite">
                            @if ($selectedStudent)
                                Selected {{ trim($selectedStudent->first_name.' '.$selectedStudent->last_name) }} · {{ $selectedStudent->branch?->name }}
                            @else
                                Enter at least two characters to search active students.
                            @endif
                        </small>
                        @error('student_id') <small class="text-danger d-block">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-group col-12 col-md-6">
                        <label for="result_course_id">Course <span class="text-muted font-weight-normal">(optional)</span></label>
                        <select id="result_course_id" name="course_id" class="form-control" data-result-course>
                            <option value="">Choose a course</option>
                            @foreach ($courses as $course)
                                <option
                                    value="{{ $course->id }}"
                                    data-branch="{{ $course->branch_id }}"
                                    @selected(old('course_id', $editingRecords->first()?->course_id) == $course->id)
                                >
                                    {{ $course->branch?->name }} · {{ $course->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('course_id') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-group col-12 col-md-6">
                        <label for="result_semester_id">Semester <span class="text-muted font-weight-normal">(optional)</span></label>
                        <select id="result_semester_id" name="semester_id" class="form-control" data-result-semester>
                            <option value="">No semester</option>
                            @foreach ($semesters as $semester)
                                <option value="{{ $semester->id }}" data-branch="{{ $semester->course?->branch_id }}" data-course="{{ $semester->course_id }}" @selected(old('semester_id', $editingRecords->first()?->semester_id) == $semester->id)>
                                    {{ $semester->course?->name }} · {{ $semester->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('semester_id') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                    <div class="form-group col-12">
                        <div class="result-subject-list-heading">
                            <div>
                                <label class="mb-1">Subject results <span class="text-danger">*</span></label>
                                <small class="form-text text-muted mt-0">Choose a subject linked to the selected class or course.</small>
                            </div>
                            <button class="btn btn-outline-primary" type="button" data-add-result-subject>
                                <i class="fa-solid fa-plus mr-1" aria-hidden="true"></i>Add subject
                            </button>
                        </div>

                        <div class="result-subject-rows" data-result-subject-rows>
                            @foreach ($subjectRows as $index => $subjectRow)
                                <div class="result-subject-row" data-result-subject-row>
                                    <div class="form-group mb-0">
                                        <label for="result_subject_id_{{ $index }}">Subject <span class="text-danger">*</span></label>
                                        <select
                                            id="result_subject_id_{{ $index }}"
                                            name="subjects[{{ $index }}][subject_id]"
                                            class="form-control @if ($errors->has('subjects.'.$index.'.subject_id')) is-invalid @endif"
                                            required
                                            data-subject-field="subject_id"
                                        >
                                            <option value="">Choose a class or course first</option>
                                            @foreach ($subjects as $subject)
                                                <option
                                                    value="{{ $subject->id }}"
                                                    data-branch="{{ $subject->branch_id }}"
                                                    data-class="{{ $subject->school_class_id }}"
                                                    data-course="{{ $subject->course_id }}"
                                                    @selected(($subjectRow['subject_id'] ?? '') == $subject->id)
                                                >
                                                    {{ $subject->name }}
                                                    @if ($subject->code)
                                                        · {{ $subject->code }}
                                                    @endif
                                                    · {{ $subject->schoolClass?->name ?? $subject->course?->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if ($errors->has('subjects.'.$index.'.subject_id'))
                                            <small class="text-danger">{{ $errors->first('subjects.'.$index.'.subject_id') }}</small>
                                        @endif
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="result_marks_{{ $index }}">Total marks</label>
                                        <input
                                            id="result_marks_{{ $index }}"
                                            name="subjects[{{ $index }}][marks]"
                                            type="number"
                                            min="0"
                                            max="999999.99"
                                            step="0.01"
                                            class="form-control @if ($errors->has('subjects.'.$index.'.marks')) is-invalid @endif"
                                            value="{{ $subjectRow['marks'] ?? '' }}"
                                            placeholder="Optional"
                                            data-subject-field="marks"
                                        >
                                        @if ($errors->has('subjects.'.$index.'.marks'))
                                            <small class="text-danger">{{ $errors->first('subjects.'.$index.'.marks') }}</small>
                                        @endif
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="result_marks_obtained_{{ $index }}">Marks obtained</label>
                                        <input
                                            id="result_marks_obtained_{{ $index }}"
                                            name="subjects[{{ $index }}][marks_obtained]"
                                            type="number"
                                            min="0"
                                            max="999999.99"
                                            step="0.01"
                                            class="form-control @if ($errors->has('subjects.'.$index.'.marks_obtained')) is-invalid @endif"
                                            value="{{ $subjectRow['marks_obtained'] ?? '' }}"
                                            placeholder="Optional"
                                            data-subject-field="marks_obtained"
                                        >
                                        @if ($errors->has('subjects.'.$index.'.marks_obtained'))
                                            <small class="text-danger">{{ $errors->first('subjects.'.$index.'.marks_obtained') }}</small>
                                        @endif
                                    </div>
                                    <div class="form-group mb-0">
                                        <label for="result_grade_{{ $index }}">Grade</label>
                                        <input
                                            id="result_grade_{{ $index }}"
                                            name="subjects[{{ $index }}][grade]"
                                            class="form-control @if ($errors->has('subjects.'.$index.'.grade')) is-invalid @endif"
                                            value="{{ $subjectRow['grade'] ?? '' }}"
                                            maxlength="20"
                                            placeholder="Optional"
                                            data-subject-field="grade"
                                        >
                                        @if ($errors->has('subjects.'.$index.'.grade'))
                                            <small class="text-danger">{{ $errors->first('subjects.'.$index.'.grade') }}</small>
                                        @endif
                                    </div>
                                    <button
                                        class="btn btn-outline-danger result-subject-remove"
                                        type="button"
                                        aria-label="Remove subject row {{ $index + 1 }}"
                                        data-remove-result-subject
                                        @disabled($subjectRows->count() === 1)
                                    >
                                        <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        @if ($errors->has('subjects'))
                            <small class="text-danger d-block mt-2">{{ $errors->first('subjects') }}</small>
                        @endif
                    </div>
                    <div class="form-group col-12 col-md-6">
                        <label for="result_status">Visibility</label>
                        <select id="result_status" name="status" class="form-control" required>
                            <option value="draft" @selected(old('status', $editingRecords->first()?->status ?? 'draft') === 'draft')>Draft</option>
                            <option value="published" @selected(old('status', $editingRecords->first()?->status) === 'published')>Published</option>
                        </select>
                        @error('status') <small class="text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>

                <template id="result-subject-row-template">
                    <div class="result-subject-row" data-result-subject-row>
                        <div class="form-group mb-0">
                            <label for="result_subject_id___INDEX__">Subject <span class="text-danger">*</span></label>
                            <select
                                id="result_subject_id___INDEX__"
                                name="subjects[__INDEX__][subject_id]"
                                class="form-control"
                                required
                                data-subject-field="subject_id"
                            >
                                <option value="">Choose a class or course first</option>
                                @foreach ($subjects as $subject)
                                    <option
                                        value="{{ $subject->id }}"
                                        data-branch="{{ $subject->branch_id }}"
                                        data-class="{{ $subject->school_class_id }}"
                                        data-course="{{ $subject->course_id }}"
                                    >
                                        {{ $subject->name }}
                                        @if ($subject->code)
                                            · {{ $subject->code }}
                                        @endif
                                        · {{ $subject->schoolClass?->name ?? $subject->course?->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group mb-0">
                            <label for="result_marks___INDEX__">Total marks</label>
                            <input
                                id="result_marks___INDEX__"
                                name="subjects[__INDEX__][marks]"
                                type="number"
                                min="0"
                                max="999999.99"
                                step="0.01"
                                class="form-control"
                                placeholder="Optional"
                                data-subject-field="marks"
                            >
                        </div>
                        <div class="form-group mb-0">
                            <label for="result_marks_obtained___INDEX__">Marks obtained</label>
                            <input
                                id="result_marks_obtained___INDEX__"
                                name="subjects[__INDEX__][marks_obtained]"
                                type="number"
                                min="0"
                                max="999999.99"
                                step="0.01"
                                class="form-control"
                                placeholder="Optional"
                                data-subject-field="marks_obtained"
                            >
                        </div>
                        <div class="form-group mb-0">
                            <label for="result_grade___INDEX__">Grade</label>
                            <input
                                id="result_grade___INDEX__"
                                name="subjects[__INDEX__][grade]"
                                class="form-control"
                                maxlength="20"
                                placeholder="Optional"
                                data-subject-field="grade"
                            >
                        </div>
                        <button
                            class="btn btn-outline-danger result-subject-remove"
                            type="button"
                            aria-label="Remove subject row __INDEX__"
                            data-remove-result-subject
                        >
                            <i class="fa-solid fa-trash" aria-hidden="true"></i>
                        </button>
                    </div>
                </template>

                <div class="result-form-actions">
                    <button class="btn btn-outline-secondary" type="button" data-result-form-close>Cancel</button>
                    <button class="btn btn-primary" type="submit">
                        <i class="fa-solid fa-floppy-disk mr-2" aria-hidden="true"></i>Save results
                    </button>
                </div>
            </form>
        </div>

        <x-admin.card padding="false" class="results-list-card">
            <div class="results-list-heading">
                <div>
                    <h2>Student result summaries</h2>
                    <p>Each row shows a student's marks grouped by examination, class or course, and academic year.</p>
                </div>
                <span class="results-count-pill">{{ number_format($results->total()) }} {{ \Illuminate\Support\Str::plural('result', $results->total()) }}</span>
            </div>

            <div class="table-responsive">
                <x-admin.table>
                    <thead>
                        <tr>
                            <th>Student details</th>
                            <th>Academic year</th>
                            @if ($examinationLinkReady)
                                <th>Examination</th>
                            @endif
                            <th>Class / section</th>
                            <th>Course / semester</th>
                            <th>Subjects</th>
                            <th>Total marks</th>
                            <th>Total obtained</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($results as $result)
                            @php
                                $groupParams = [
                                    'school_class_id' => $result->school_class_id ?? 0,
                                    'section_id' => $result->section_id ?? 0,
                                    'course_id' => $result->course_id ?? 0,
                                    'semester_id' => $result->semester_id ?? 0,
                                    'examination_id' => $result->examination_id ?? 0,
                                    'group_status' => $result->status,
                                ];
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ trim(($result->student?->first_name ?? '').' '.($result->student?->last_name ?? '')) ?: 'Student unavailable' }}</strong>
                                    <small class="d-block text-muted">ID: {{ $result->student?->student_id ?? '—' }}</small>
                                    <small class="d-block text-muted">{{ $result->student?->branch?->name ?? '—' }}</small>
                                </td>
                                <td>{{ $result->academicYear?->name ?? '—' }}</td>
                                @if ($examinationLinkReady)
                                    <td>
                                        {{ $result->examination?->name ?? '—' }}
                                        @if ($result->examination)
                                            <small class="d-block text-muted">{{ \Illuminate\Support\Str::headline($result->examination->exam_type) }}</small>
                                        @endif
                                    </td>
                                @endif
                                <td>
                                    {{ $result->schoolClass?->name ?? '—' }}
                                    @if ($result->section)
                                        <small class="d-block text-muted">Section {{ $result->section->name }}</small>
                                    @endif
                                </td>
                                <td>
                                    {{ $result->course?->name ?? $result->semester?->course?->name ?? '—' }}
                                    @if ($result->semester)
                                        <small class="d-block text-muted">{{ $result->semester->name }}</small>
                                    @endif
                                </td>
                                <td>{{ number_format((int) $result->subject_count) }}</td>
                                <td>{{ number_format((float) $result->total_marks, 2) }}</td>
                                <td><strong>{{ number_format((float) $result->total_obtained, 2) }}</strong></td>
                                <td>
                                    <x-admin.badge :tone="$result->status === 'published' ? 'success' : 'slate'">
                                        {{ ucfirst($result->status) }}
                                    </x-admin.badge>
                                </td>
                                <td class="text-right results-actions-cell">
                                    <div class="results-row-actions" role="group" aria-label="Result actions for {{ $result->student?->student_id }}">
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('academic.results.show', ['student' => $result->student_id, 'academicYear' => $result->academic_year_id] + $groupParams) }}" title="View details" aria-label="View details">
                                            <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                        </a>
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('academic.results.edit', ['studentId' => $result->student_id, 'academicYearId' => $result->academic_year_id] + $groupParams) }}" title="Edit results" aria-label="Edit results">
                                            <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                        </a>
                                        <a class="btn btn-sm btn-outline-success" href="{{ route('academic.results.download', ['student' => $result->student_id, 'academicYear' => $result->academic_year_id] + $groupParams) }}" title="Download result" aria-label="Download result">
                                            <i class="fa-solid fa-download" aria-hidden="true"></i>
                                        </a>
                                        <form method="post" action="{{ route('academic.results.destroy', ['student' => $result->student_id, 'academicYear' => $result->academic_year_id] + $groupParams) }}" onsubmit="return confirm('Delete all subject results for this student and academic group?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit" title="Delete results" aria-label="Delete results">
                                                <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $examinationLinkReady ? 10 : 9 }}" class="py-4">
                                    <x-admin.empty-state
                                        title="No results found"
                                        description="Record a result or adjust the selected filters to see results."
                                    />
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
            </div>

            @if ($results->hasPages())
                <div class="p-3">
                    <x-admin.pagination :paginator="$results" />
                </div>
            @endif
        </x-admin.card>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const filterPanel = document.querySelector('#academic-results-filters');
            const filterBranch = filterPanel?.querySelector('[data-result-filter-branch]');
            const filterYear = filterPanel?.querySelector('[data-result-filter-year]');
            const filterExamination = filterPanel?.querySelector('[data-result-filter-examination]');
            const filterClass = filterPanel?.querySelector('[data-result-filter-class]');
            const filterSection = filterPanel?.querySelector('[data-result-filter-section]');
            const filterCourse = filterPanel?.querySelector('[data-result-filter-course]');
            const filterSemester = filterPanel?.querySelector('[data-result-filter-semester]');
            const filterSubject = filterPanel?.querySelector('[data-result-filter-subject]');

            const filterOptions = function (select, predicate) {
                if (! select) {
                    return;
                }

                Array.from(select.options).forEach(function (option) {
                    option.hidden = option.value !== '' && ! predicate(option);
                    if (option.hidden && option.selected) {
                        select.value = '';
                    }
                });
            };

            const updateResultFilterOptions = function () {
                const branchId = filterBranch?.value || '';

                filterOptions(filterYear, (option) => ! branchId || option.dataset.branch === branchId);
                const academicYearId = filterYear?.value || '';
                filterOptions(filterExamination, (option) =>
                    (! branchId || option.dataset.branch === branchId)
                        && (! academicYearId || option.dataset.year === academicYearId));
                filterOptions(filterClass, (option) =>
                    (! branchId || option.dataset.branch === branchId)
                        && (! academicYearId || option.dataset.year === academicYearId));
                filterOptions(filterSection, (option) => ! branchId || option.dataset.branch === branchId);
                filterOptions(filterCourse, (option) => ! branchId || option.dataset.branch === branchId);
                const classId = filterClass?.value || '';
                const courseId = filterCourse?.value || '';
                filterOptions(filterSemester, (option) =>
                    (! branchId || option.dataset.branch === branchId)
                        && (! courseId || option.dataset.course === courseId));
                filterOptions(filterSubject, (option) => {
                    const belongsToSelectedGroup = (! classId && ! courseId)
                        || (classId && option.dataset.class === classId)
                        || (courseId && option.dataset.course === courseId);

                    return (! branchId || option.dataset.branch === branchId) && belongsToSelectedGroup;
                });
            };

            [filterBranch, filterYear, filterClass, filterCourse].forEach((select) =>
                select?.addEventListener('change', updateResultFilterOptions));
            updateResultFilterOptions();

            const form = document.querySelector('[data-result-entry-form]');
            const formPanel = document.querySelector('#academic-result-form-panel');
            const formToggle = document.querySelector('[data-result-form-toggle]');
            const formLabel = document.querySelector('[data-result-form-label]');
            const formClose = document.querySelector('[data-result-form-close]');

            const setFormVisibility = function (isVisible) {
                if (! formPanel || ! formToggle || ! formLabel) {
                    return;
                }

                formPanel.hidden = ! isVisible;
                formToggle.setAttribute('aria-expanded', String(isVisible));
                formLabel.textContent = isVisible ? 'Hide form' : 'Record result';

                if (isVisible) {
                    formPanel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    form.querySelector('input:not([type="hidden"]), select')?.focus({ preventScroll: true });
                }
            };

            formToggle?.addEventListener('click', function () {
                setFormVisibility(formPanel?.hidden ?? false);
            });
            formClose?.addEventListener('click', function () {
                setFormVisibility(false);
            });

            const studentSearch = form?.querySelector('[data-academic-student-search]');
            const studentResults = form?.querySelector('[data-academic-student-results]');
            const studentStatus = form?.querySelector('[data-academic-student-status]');
            const studentId = form?.querySelector('[data-selected-student-id]');
            const year = form?.querySelector('[data-result-year]');
            const schoolClass = form?.querySelector('[data-result-class]');
            const section = form?.querySelector('[data-result-section]');
            const course = form?.querySelector('[data-result-course]');
            const semester = form?.querySelector('[data-result-semester]');
            const examination = form?.querySelector('[data-result-examination]');
            const subjectRows = form?.querySelector('[data-result-subject-rows]');
            const subjectTemplate = form?.querySelector('#result-subject-row-template');
            const addSubjectButton = form?.querySelector('[data-add-result-subject]');
            let studentSearchTimer = null;
            let studentSearchController = null;
            let selectedStudentLabel = studentSearch?.value || '';
            let selectedBranchId = studentSearch?.dataset.selectedBranchId
                || studentSearch?.dataset.branchId
                || '';

            if (studentSearch && studentResults && studentStatus && studentId && year && semester) {
                const syncSubjectRows = function () {
                    if (! subjectRows || ! addSubjectButton) {
                        return;
                    }

                    const rows = Array.from(subjectRows.querySelectorAll('[data-result-subject-row]'));
                    rows.forEach(function (row, index) {
                        row.querySelectorAll('[data-subject-field]').forEach(function (field) {
                            const fieldName = field.dataset.subjectField;
                            const label = row.querySelector('label[for="' + field.id + '"]');
                            const fieldId = 'result_' + fieldName + '_' + index;
                            field.id = fieldId;
                            field.name = 'subjects[' + index + '][' + fieldName + ']';
                            label?.setAttribute('for', fieldId);
                        });
                        row.querySelector('[data-remove-result-subject]')?.setAttribute('aria-label', 'Remove subject row ' + (index + 1));
                    });

                    rows.forEach(function (row) {
                        const removeButton = row.querySelector('[data-remove-result-subject]');
                        if (removeButton) {
                            removeButton.disabled = rows.length === 1;
                        }
                    });

                    addSubjectButton.disabled = rows.length >= 20;
                };

                addSubjectButton?.addEventListener('click', function () {
                    const rowCount = subjectRows?.querySelectorAll('[data-result-subject-row]').length ?? 0;
                    if (! subjectTemplate || ! subjectRows || rowCount >= 20) {
                        return;
                    }

                    subjectRows.insertAdjacentHTML('beforeend', subjectTemplate.innerHTML.replaceAll('__INDEX__', String(rowCount)));
                    syncSubjectRows();
                    updateAcademicOptions();
                    subjectRows.querySelector('[data-result-subject-row]:last-child [data-subject-field="subject_id"]')?.focus();
                });

                subjectRows?.addEventListener('click', function (event) {
                    const removeButton = event.target.closest('[data-remove-result-subject]');
                    if (! removeButton || subjectRows.querySelectorAll('[data-result-subject-row]').length <= 1) {
                        return;
                    }

                    removeButton.closest('[data-result-subject-row]')?.remove();
                    syncSubjectRows();
                });

                syncSubjectRows();

                const filterOptions = function (select, predicate) {
                    Array.from(select.options).forEach(function (option) {
                        option.hidden = option.value !== '' && ! predicate(option);

                        if (option.hidden && option.selected) {
                            select.value = '';
                        }
                    });
                };

                const updateSubjectOptions = function () {
                    if (! subjectRows) {
                        return;
                    }

                    const classId = schoolClass.value;
                    const courseId = course.value;
                    const hasAcademicGroup = Boolean(classId || courseId);

                    subjectRows.querySelectorAll('[data-subject-field="subject_id"]').forEach(function (select) {
                        Array.from(select.options).forEach(function (option) {
                            const ownerMatches = (classId && option.dataset.class === classId)
                                || (courseId && option.dataset.course === courseId);
                            const branchMatches = ! selectedBranchId || option.dataset.branch === selectedBranchId;
                            option.hidden = option.value !== '' && (! ownerMatches || ! branchMatches);

                            if (option.hidden && option.selected) {
                                select.value = '';
                            }
                        });

                        select.disabled = ! hasAcademicGroup;
                        select.options[0].textContent = hasAcademicGroup
                            ? 'Choose a subject'
                            : 'Choose a class or course first';
                    });
                };

                const updateAcademicOptions = function () {
                    filterOptions(year, (option) => ! selectedBranchId || option.dataset.branch === selectedBranchId);
                    filterOptions(schoolClass, (option) =>
                        (! selectedBranchId || option.dataset.branch === selectedBranchId)
                            && (! year.value || option.dataset.year === year.value));
                    filterOptions(section, (option) => ! selectedBranchId || option.dataset.branch === selectedBranchId);
                    filterOptions(course, (option) => ! selectedBranchId || option.dataset.branch === selectedBranchId);
                    filterOptions(semester, (option) =>
                        (! selectedBranchId || option.dataset.branch === selectedBranchId)
                            && (! course.value || option.dataset.course === course.value));
                    filterOptions(examination, (option) =>
                        (! selectedBranchId || option.dataset.branch === selectedBranchId)
                            && (! year.value || option.dataset.year === year.value));
                    updateSubjectOptions();
                };

                [course].forEach(function (select) {
                    select?.addEventListener('change', updateAcademicOptions);
                });
                semester.addEventListener('change', function () {
                    if (semester.value && ! course.value) {
                        course.value = semester.selectedOptions[0]?.dataset.course || '';
                    }

                    updateAcademicOptions();
                });
                schoolClass?.addEventListener('change', function () {
                    schoolClass.setCustomValidity('');
                    course.setCustomValidity('');
                    updateAcademicOptions();
                });
                course.addEventListener('change', function () {
                    schoolClass.setCustomValidity('');
                    course.setCustomValidity('');
                });
                year.addEventListener('change', function () {
                    updateAcademicOptions();
                });
                examination?.addEventListener('change', function () {
                    const selectedOption = examination.selectedOptions[0];
                    if (selectedOption?.dataset.year) {
                        year.value = selectedOption.dataset.year;
                    }
                    updateAcademicOptions();
                });

                [schoolClass, section, course, year].forEach(function (select) {
                    select?.addEventListener('change', function () {
                        studentSearchController?.abort();
                        window.clearTimeout(studentSearchTimer);
                        studentId.value = '';
                        studentSearch.value = '';
                        selectedStudentLabel = '';
                        studentResults.hidden = true;
                        studentStatus.textContent = 'Search students in the selected group.';
                    });
                });

                const renderStudentResults = function (students) {
                    studentResults.replaceChildren();

                    if (! students.length) {
                        const empty = document.createElement('div');
                        empty.className = 'academic-student-result-empty';
                        empty.textContent = 'No matching active students found.';
                        studentResults.append(empty);
                    } else {
                        students.forEach(function (student) {
                            const option = document.createElement('button');
                            option.type = 'button';
                            option.className = 'academic-student-result';
                            option.setAttribute('role', 'option');
                            option.textContent = student.label + (student.branch_name ? ' · ' + student.branch_name : '');
                            option.addEventListener('click', function () {
                                studentId.value = student.id;
                                selectedStudentLabel = student.label;
                                selectedBranchId = String(student.branch_id);
                                studentSearch.value = student.label;
                                studentResults.hidden = true;
                                studentResults.replaceChildren();
                                studentSearch.setAttribute('aria-expanded', 'false');
                                studentSearch.setCustomValidity('');
                                studentStatus.textContent = 'Selected ' + student.label + (student.branch_name ? ' · ' + student.branch_name : '') + '.';
                                updateAcademicOptions();
                            });
                            studentResults.append(option);
                        });
                    }

                    studentResults.hidden = false;
                    studentSearch.setAttribute('aria-expanded', 'true');
                    studentStatus.textContent = students.length
                        ? students.length + ' matching student' + (students.length === 1 ? '' : 's') + '.'
                        : 'Try another name or student ID.';
                };

                const searchStudents = async function (query) {
                    studentSearchController?.abort();
                    studentSearchController = new AbortController();
                    const url = new URL(studentSearch.dataset.searchUrl, window.location.origin);
                    url.searchParams.set('q', query);
                    if (schoolClass.value) url.searchParams.set('school_class_id', schoolClass.value);
                    if (section.value) url.searchParams.set('section_id', section.value);
                    if (course.value) url.searchParams.set('course_id', course.value);
                    if (year.value) url.searchParams.set('academic_year_id', year.value);

                    if (studentSearch.dataset.branchId) {
                        url.searchParams.set('branch_id', studentSearch.dataset.branchId);
                    }

                    studentStatus.textContent = 'Searching students…';

                    try {
                        const response = await fetch(url, {
                            headers: { 'Accept': 'application/json' },
                            signal: studentSearchController.signal,
                        });
                        const result = await response.json();

                        if (! response.ok) {
                            throw new Error(result.message || 'Student search failed.');
                        }

                        renderStudentResults(result.students || []);
                    } catch (error) {
                        if (error.name !== 'AbortError') {
                            studentStatus.textContent = error.message;
                            studentResults.hidden = true;
                            studentSearch.setAttribute('aria-expanded', 'false');
                        }
                    }
                };

                studentSearch.addEventListener('input', function () {
                    window.clearTimeout(studentSearchTimer);
                    studentResults.hidden = true;
                    studentResults.replaceChildren();
                    studentSearch.setAttribute('aria-expanded', 'false');
                    studentSearch.setCustomValidity('');

                    if (studentSearch.value.trim() === selectedStudentLabel && studentId.value) {
                        return;
                    }

                    studentId.value = '';
                    selectedStudentLabel = '';
                    selectedBranchId = '';
                    updateAcademicOptions();

                    const query = studentSearch.value.trim();
                    if (!schoolClass.value && !course.value) {
                        studentStatus.textContent = 'Choose a class or course before searching.';
                        return;
                    }

                    if (query.length < 2) {
                        studentStatus.textContent = 'Enter at least two characters to search active students.';
                        return;
                    }

                    studentSearchTimer = window.setTimeout(() => searchStudents(query), 250);
                });

                studentSearch.addEventListener('keydown', function (event) {
                    const options = Array.from(studentResults.querySelectorAll('[role="option"]'));

                    if (event.key === 'Escape') {
                        studentResults.hidden = true;
                        studentSearch.setAttribute('aria-expanded', 'false');
                    } else if (event.key === 'ArrowDown' && options.length) {
                        event.preventDefault();
                        options[0].focus();
                    } else if (event.key === 'Enter' && ! studentResults.hidden && options.length) {
                        event.preventDefault();
                        options[0].click();
                    }
                });

                studentResults.addEventListener('keydown', function (event) {
                    const options = Array.from(studentResults.querySelectorAll('[role="option"]'));
                    const currentIndex = options.indexOf(document.activeElement);

                    if (event.key === 'Escape') {
                        event.preventDefault();
                        studentResults.hidden = true;
                        studentSearch.setAttribute('aria-expanded', 'false');
                        studentSearch.focus();
                    } else if (event.key === 'ArrowDown' && options.length) {
                        event.preventDefault();
                        options[(currentIndex + 1) % options.length].focus();
                    } else if (event.key === 'ArrowUp' && options.length) {
                        event.preventDefault();

                        if (currentIndex <= 0) {
                            studentSearch.focus();
                        } else {
                            options[currentIndex - 1].focus();
                        }
                    }
                });

                document.addEventListener('click', function (event) {
                    if (! event.target.closest('[data-academic-student-picker]')) {
                        studentResults.hidden = true;
                        studentSearch.setAttribute('aria-expanded', 'false');
                    }
                });

                form.addEventListener('submit', function (event) {
                    if (! studentId.value) {
                        event.preventDefault();
                        studentSearch.setCustomValidity('Select a student from the search results.');
                        studentSearch.reportValidity();
                        return;
                    }

                    if (! schoolClass.value && ! course.value) {
                        event.preventDefault();
                        schoolClass.setCustomValidity('Choose the student’s class or course to load its subjects.');
                        schoolClass.reportValidity();
                    }
                });

                if (semester.value && ! course.value) {
                    course.value = semester.selectedOptions[0]?.dataset.course || '';
                }

                updateAcademicOptions();
            }
        });
    </script>
@endsection
