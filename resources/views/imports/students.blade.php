@extends('layouts.app')

@section('content')
    <div class="student-import-page">
        <x-admin.page-header
            title="Import students"
            description="Upload student and enrollment details in one spreadsheet, review every row, then import the valid records."
        >
            <x-slot:actions>
                <a class="btn btn-outline-primary" href="{{ route('imports.template') }}">
                    <i class="fa-solid fa-download mr-2" aria-hidden="true"></i>Download CSV template
                </a>
            </x-slot:actions>
        </x-admin.page-header>

        <x-admin.card class="student-import-card">
            <div class="student-import-section-heading">
                <span class="student-import-step">1</span>
                <div>
                    <h2>Upload student file</h2>
                    <p>Select one branch and upload its CSV or Excel file. The preview checks every row before saving.</p>
                </div>
            </div>

            @if ($branches->isEmpty())
                <div class="alert alert-warning" role="alert">
                    <strong>No active branch is available.</strong>
                    Contact your administrator to activate a branch before importing students.
                </div>
            @endif

            <form method="post" action="{{ route('imports.preview') }}" enctype="multipart/form-data" data-student-import-form>
                @csrf
                <div class="form-row align-items-end">
                    @if (! auth()->user()->currentBranchId())
                        <div class="form-group col-12 col-md-4">
                            <label for="branch_id">Branch</label>
                            <select
                                id="branch_id"
                                name="branch_id"
                                class="form-control @error('branch_id') is-invalid @enderror"
                                required
                            >
                                <option value="">Choose a branch</option>
                                @foreach ($branches as $branch)
                                    <option value="{{ $branch->id }}" @selected(old('branch_id') == $branch->id)>
                                        {{ $branch->name }} ({{ $branch->code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('branch_id') <small class="invalid-feedback">{{ $message }}</small> @enderror
                        </div>
                    @else
                        <div class="form-group col-12 col-md-4">
                            <label>Branch</label>
                            <div class="student-import-branch-value">
                                {{ $branches->firstWhere('id', auth()->user()->currentBranchId())?->name ?? 'Your assigned branch' }}
                            </div>
                        </div>
                    @endif

                    <div class="form-group col-12 col-md-8">
                        <label for="file">Student spreadsheet</label>
                        <input
                            id="file"
                            class="form-control-file @error('file') is-invalid @enderror"
                            type="file"
                            name="file"
                            accept=".csv,.xlsx"
                            aria-describedby="student-import-file-help student-import-file-name"
                            required
                        >
                        <small id="student-import-file-help" class="form-text text-muted">
                            CSV or XLSX, up to 5 MB. Use one branch per upload.
                        </small>
                        <small id="student-import-file-name" class="student-import-file-name d-none" data-student-import-file-name role="status"></small>
                        @error('file') <small class="d-block text-danger">{{ $message }}</small> @enderror
                    </div>
                </div>

                <div class="student-import-actions">
                    <button class="btn btn-primary" type="submit" @disabled($branches->isEmpty())>
                        <i class="fa-solid fa-magnifying-glass mr-2" aria-hidden="true"></i>Preview and validate
                    </button>
                </div>
            </form>
        </x-admin.card>

        <x-admin.card class="student-import-guide">
            <div class="student-import-section-heading">
                <span class="student-import-step student-import-step-muted">2</span>
                <div>
                    <h2>Prepare your spreadsheet</h2>
                    <p>Use the downloaded template headers. One row creates a student, an initial enrollment, and an admission record.</p>
                </div>
            </div>

            <div class="student-import-fields">
                <section>
                    <h3>Required columns</h3>
                    <div class="student-import-field-list">
                        <span><code>first_name</code> Student's first name</span>
                        <span><code>branch_code</code> Must match the selected branch</span>
                        <span><code>academic_year</code> Exact year name, such as 2026-2027</span>
                    </div>
                </section>
                <section>
                    <h3>Optional student, enrollment, and admission columns</h3>
                    <div class="student-import-field-list">
                        <span><code>last_name</code>, <code>date_of_birth</code>, <code>gender</code></span>
                        <span><code>phone</code>, <code>email</code>, <code>address</code></span>
                        <span><code>class_name</code>, <code>section_name</code></span>
                        <span><code>course_code</code>, <code>semester_name</code></span>
                        <span><code>roll_number</code> (R.NO), <code>gr_number</code> (GR.NO)</span>
                        <span><code>place_of_birth</code>, <code>state</code>, <code>nationality</code>, <code>mother_tongue</code></span>
                        <span><code>religion</code>, <code>blood_group</code>, <code>aadhaar_number</code></span>
                        <span><code>caste</code>, <code>category</code></span>
                    </div>
                </section>
                <section>
                    <h3>Optional father and mother columns</h3>
                    <div class="student-import-field-list">
                        <span><code>father_name</code>, <code>father_occupation</code></span>
                        <span><code>father_phone</code>, <code>father_email</code></span>
                        <span><code>mother_name</code>, <code>mother_occupation</code></span>
                        <span><code>mother_phone</code>, <code>mother_email</code></span>
                        <span>Legacy <code>guardian_name</code> and <code>guardian_phone</code> columns are also accepted.</span>
                    </div>
                </section>
            </div>

            <div class="student-import-note">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>
                    Class, section, course, and semester names are matched against this branch's setup. A semester requires a valid
                    <code>course_code</code>. Dates accept <strong>DD/MM/YYYY</strong>, <strong>YYYY-MM-DD</strong>, or <strong>DD-MM-YYYY</strong>. Student ID and admission number are generated automatically. Blank R.NO and GR.NO values are generated sequentially within the selected branch.
                    Aadhaar numbers must be exactly 12 digits; format the spreadsheet column as text to preserve leading zeroes. Aadhaar is stored encrypted.
                    Student photos can be added from the student profile after import.
                </span>
            </div>
        </x-admin.card>

        <section class="student-import-history" aria-labelledby="student-import-history-title">
            <div class="student-import-section-heading">
                <span class="student-import-step student-import-step-muted">3</span>
                <div>
                    <h2 id="student-import-history-title">Recent import history</h2>
                    <p>Review imported and failed row counts, or roll back an import that has no linked activity.</p>
                </div>
            </div>

            <x-admin.card padding="false">
                <x-admin.table>
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Branch</th>
                            <th>Uploaded by</th>
                            <th>Rows</th>
                            <th>Result</th>
                            <th>Date</th>
                            <th>Recovery</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($histories as $history)
                            <tr>
                                <td>{{ $history->file_name }}</td>
                                <td>{{ $history->branch?->name }}</td>
                                <td>{{ $history->uploader?->name ?? '—' }}</td>
                                <td>{{ number_format($history->total_rows) }}</td>
                                <td>
                                    <span class="student-import-result">
                                        <span class="badge badge-success">{{ number_format($history->success_rows) }} imported</span>
                                        @if ($history->failed_rows)
                                            <span class="badge badge-danger">{{ number_format($history->failed_rows) }} failed</span>
                                        @endif
                                    </span>
                                    @if ($history->errors)
                                        <details class="student-import-history-errors">
                                            <summary>View {{ count($history->errors) }} row {{ \Illuminate\Support\Str::plural('error', count($history->errors)) }}</summary>
                                            <ul>
                                                @foreach ($history->errors as $rowNumber => $messages)
                                                    <li>
                                                        <strong>Row {{ $rowNumber }}:</strong>
                                                        {{ implode(' ', (array) $messages) }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </details>
                                    @endif
                                </td>
                                <td>{{ $history->created_at?->format('d M Y, h:i A') }}</td>
                                <td>
                                    @if ($history->rolled_back_at)
                                        <span class="text-muted">Rolled back {{ $history->rolled_back_at->format('d M Y') }}</span>
                                    @elseif ($history->imported_student_ids)
                                        <form
                                            method="post"
                                            action="{{ route('imports.rollback', $history) }}"
                                            onsubmit="return confirm('Roll back this student import? Imports with dependent activity will be blocked.');"
                                        >
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Rollback</button>
                                        </form>
                                    @else
                                        <span class="text-muted">No imported rows</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-6 text-center text-muted">No import history yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
                <div class="p-3">
                    <x-admin.pagination :paginator="$histories" />
                </div>
            </x-admin.card>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.querySelector('[data-student-import-form]');
            const fileInput = form?.querySelector('input[type="file"]');
            const fileName = form?.querySelector('[data-student-import-file-name]');

            if (!fileInput || !fileName) {
                return;
            }

            fileInput.addEventListener('change', () => {
                const selectedFile = fileInput.files?.[0];

                fileName.textContent = selectedFile
                    ? `${selectedFile.name} · ${(selectedFile.size / 1024 / 1024).toFixed(2)} MB`
                    : '';
                fileName.classList.toggle('d-none', !selectedFile);
            });
        });
    </script>
@endsection
