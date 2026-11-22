@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Student certificates"
        description="Issue, print, and keep a searchable register of official student certificates."
    />

    <x-admin.alert />
    <x-admin.validation-errors />

    <style>
        .certificate-workspace { display: grid; gap: 1.25rem; }
        .certificate-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
        .certificate-full { grid-column: 1 / -1; }
        .certificate-register-head { display: flex; align-items: end; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
        .certificate-filter { display: flex; align-items: end; gap: .75rem; flex-wrap: wrap; }
        .certificate-meta { color: #64748b; font-size: .85rem; }
        @media (max-width: 767px) {
            .certificate-form-grid { grid-template-columns: 1fr; }
            .certificate-full { grid-column: auto; }
            .certificate-filter { width: 100%; }
            .certificate-filter > * { flex: 1 1 100%; }
        }
    </style>

    <div class="certificate-workspace">
        @if (! auth()->user()->currentBranchId() && $branches->count() > 1)
            <x-admin.card>
                <form method="get" class="form-row align-items-end mb-0">
                    <div class="form-group col-md-5 mb-md-0">
                        <label for="branch_id">Campus</label>
                        <select id="branch_id" name="branch_id" class="form-control">
                            <option value="">All accessible campuses</option>
                            @foreach ($branches as $branch)
                                <option value="{{ $branch->id }}" @selected($branchId == $branch->id)>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-auto"><button class="btn btn-outline-primary">Apply campus</button></div>
                </form>
            </x-admin.card>
        @endif

        @if (auth()->user()->hasPermission('students.edit'))
            <x-admin.card>
                <div class="d-flex align-items-start justify-content-between flex-wrap mb-3">
                    <div>
                        <h2 class="h5 mb-1">Issue a certificate</h2>
                        <p class="text-muted mb-0">Select a student and certificate type. The issued document is saved to the register.</p>
                    </div>
                </div>

                <form method="post" action="{{ route('students.certificates.store') }}">
                    @csrf
                    @if ($branchId)
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    @endif
                    <div class="certificate-form-grid">
                        <div class="form-group">
                            <label for="student_id">Student <span class="text-danger">*</span></label>
                            <select id="student_id" name="student_id" class="form-control bt-select @error('student_id') is-invalid @enderror" data-live-search="true" data-size="8" data-width="100%" title="Choose a student" required>
                                @foreach ($students as $student)
                                    @php($activeEnrollment = $student->enrollments->first())
                                    <option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>
                                        {{ $student->first_name }} {{ $student->last_name }} · {{ $student->admission_number }} · {{ str($student->status)->title() }}{{ $activeEnrollment ? ' · currently enrolled' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            @error('student_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group">
                            <label for="certificate_type">Certificate type <span class="text-danger">*</span></label>
                            <select id="certificate_type" name="certificate_type" class="form-control" required>
                                <option value="">Choose certificate</option>
                                <option value="transfer" @selected(old('certificate_type') === 'transfer')>Transfer Certificate (TC)</option>
                                <option value="character" @selected(old('certificate_type') === 'character')>Character Certificate</option>
                                <option value="bonafide" @selected(old('certificate_type') === 'bonafide')>Bonafide Certificate</option>
                                <option value="migration" @selected(old('certificate_type') === 'migration')>Migration Certificate</option>
                                <option value="school_leaving" @selected(old('certificate_type') === 'school_leaving')>School Leaving Certificate (SLC)</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="issued_on">Issue date <span class="text-danger">*</span></label>
                            <input id="issued_on" name="issued_on" type="text" class="form-control js-datepicker @error('issued_on') is-invalid @enderror" value="{{ old('issued_on', today()->format('d/m/Y')) }}" placeholder="DD/MM/YYYY" autocomplete="off" required>
                            @error('issued_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group" data-certificate-effective hidden>
                            <label for="effective_date">Leaving / transfer date <span class="text-danger">*</span></label>
                            <input id="effective_date" name="effective_date" type="text" class="form-control js-datepicker @error('effective_date') is-invalid @enderror" value="{{ old('effective_date') }}" placeholder="DD/MM/YYYY" autocomplete="off">
                            @error('effective_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group certificate-full" data-certificate-conduct hidden>
                            <label for="conduct">Conduct and behaviour <span class="text-danger">*</span></label>
                            <textarea id="conduct" name="conduct" class="form-control @error('conduct') is-invalid @enderror" rows="3" maxlength="2000" placeholder="Enter an accurate conduct statement for this student.">{{ old('conduct') }}</textarea>
                            @error('conduct')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group certificate-full" data-certificate-purpose hidden>
                            <label for="purpose">Purpose / destination</label>
                            <input id="purpose" name="purpose" class="form-control @error('purpose') is-invalid @enderror" value="{{ old('purpose') }}" maxlength="255" placeholder="Optional reason or receiving institution">
                            @error('purpose')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group certificate-full">
                            <label for="remarks">Additional remarks <span class="text-muted">(optional)</span></label>
                            <textarea id="remarks" name="remarks" class="form-control @error('remarks') is-invalid @enderror" rows="2" maxlength="2000">{{ old('remarks') }}</textarea>
                            @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="d-flex justify-content-end mt-2">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-file-circle-check mr-1" aria-hidden="true"></i> Issue and open certificate</button>
                    </div>
                </form>
            </x-admin.card>
        @endif

        <x-admin.card>
            <div class="certificate-register-head mb-3">
                <div>
                    <h2 class="h5 mb-1">Issued certificate register</h2>
                    <p class="text-muted mb-0">Previously issued documents remain available to print again.</p>
                </div>
                <form method="get" class="certificate-filter">
                    @if ($branchId)<input type="hidden" name="branch_id" value="{{ $branchId }}">@endif
                    <div class="form-group mb-0">
                        <label for="certificate-filter-type">Type</label>
                        <select id="certificate-filter-type" name="certificate_type" class="form-control">
                            <option value="">All certificate types</option>
                            <option value="transfer" @selected(request('certificate_type') === 'transfer')>Transfer Certificate</option>
                            <option value="character" @selected(request('certificate_type') === 'character')>Character Certificate</option>
                            <option value="bonafide" @selected(request('certificate_type') === 'bonafide')>Bonafide Certificate</option>
                            <option value="migration" @selected(request('certificate_type') === 'migration')>Migration Certificate</option>
                            <option value="school_leaving" @selected(request('certificate_type') === 'school_leaving')>School Leaving Certificate</option>
                        </select>
                    </div>
                    <div class="form-group mb-0">
                        <label for="certificate-search">Student / certificate no.</label>
                        <input id="certificate-search" name="search" value="{{ request('search') }}" class="form-control" placeholder="Search register">
                    </div>
                    <button class="btn btn-outline-primary">Filter</button>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Certificate</th><th>Student</th><th>Admission no.</th><th>Issued on</th><th>Issued by</th><th class="text-right">Action</th></tr></thead>
                    <tbody>
                        @forelse ($certificates as $certificate)
                            <tr>
                                <td><strong>{{ $certificate->certificate_number }}</strong><span class="d-block certificate-meta">{{ str($certificate->certificate_type)->replace('_', ' ')->title() }}</span></td>
                                <td>{{ $certificate->student_snapshot['student_name'] ?? 'Student record unavailable' }}</td>
                                <td>{{ $certificate->student_snapshot['admission_number'] ?? '—' }}</td>
                                <td>{{ $certificate->issued_on->format('d M Y') }}</td>
                                <td>{{ $certificate->issuer?->name ?? '—' }}</td>
                                <td class="text-right"><a class="btn btn-sm btn-outline-primary" href="{{ route('students.certificates.show', $certificate) }}"><i class="fa-solid fa-print mr-1" aria-hidden="true"></i> View / print</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No certificates have been issued for this filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $certificates->links('components.admin.pagination') }}</div>
        </x-admin.card>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const typeSelect = document.getElementById('certificate_type');
            const effectiveDate = document.querySelector('[data-certificate-effective]');
            const conduct = document.querySelector('[data-certificate-conduct]');
            const purpose = document.querySelector('[data-certificate-purpose]');
            const effectiveInput = document.getElementById('effective_date');
            const conductInput = document.getElementById('conduct');

            const updateCertificateFields = () => {
                const type = typeSelect?.value;
                const hasLeavingDate = ['transfer', 'migration', 'school_leaving'].includes(type);
                effectiveDate.hidden = !hasLeavingDate;
                conduct.hidden = type !== 'character';
                purpose.hidden = !['bonafide', 'migration'].includes(type);
                effectiveInput.required = hasLeavingDate;
                conductInput.required = type === 'character';
            };

            typeSelect?.addEventListener('change', updateCertificateFields);
            updateCertificateFields();
        });
    </script>
@endsection
