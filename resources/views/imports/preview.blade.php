@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Import preview"
        description="Check student details and enrollment matches before creating records."
    >
        <x-slot:actions>
            <a class="btn btn-outline-secondary" href="{{ route('imports.students') }}">Cancel import</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="student-import-preview-summary">
        <x-admin.card class="student-import-preview-stat">
            <span class="student-import-preview-stat-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></span>
            <div><small>Branch</small><strong>{{ $branch->name }}</strong></div>
        </x-admin.card>
        <x-admin.card class="student-import-preview-stat">
            <span class="student-import-preview-stat-icon"><i class="fa-solid fa-list-check" aria-hidden="true"></i></span>
            <div><small>Rows to review</small><strong>{{ number_format(count($rows)) }}</strong></div>
        </x-admin.card>
        <x-admin.card class="student-import-preview-stat is-valid">
            <span class="student-import-preview-stat-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></span>
            <div><small>Ready to import</small><strong>{{ number_format($validRows) }}</strong></div>
        </x-admin.card>
        <x-admin.card class="student-import-preview-stat {{ $invalidRows ? 'is-invalid' : '' }}">
            <span class="student-import-preview-stat-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></span>
            <div><small>Needs correction</small><strong>{{ number_format($invalidRows) }}</strong></div>
        </x-admin.card>
    </div>

    @if ($invalidRows)
        <div class="alert alert-warning" role="status">
            Only rows marked <strong>Ready</strong> will be imported. Rejected rows will be recorded in import history with their errors.
        </div>
    @else
        <div class="alert alert-success" role="status">
            All rows passed validation and are ready to import.
        </div>
    @endif

    <form method="post" action="{{ route('imports.confirm') }}">
        @csrf
        <x-admin.card padding="false">
            <x-admin.table>
                <thead>
                    <tr>
                        <th>Row</th>
                        <th>Student</th>
                        <th>Enrollment</th>
                        <th>Guardian</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $data = $row['data'];
                            $hasErrors = ! empty($row['errors']);
                            $hasGuardianDetails = ! empty($data['guardian_name']) || ! empty($data['guardian_phone']) || collect(['father', 'mother'])->contains(fn ($parentKey) => collect(['name', 'occupation', 'phone', 'email'])->contains(fn ($field) => filled($data[$parentKey.'_'.$field] ?? null)));
                        @endphp
                        <tr>
                            <td>{{ $row['row'] }}</td>
                            <td>
                                <strong>{{ $data['first_name'] ?? '' }} {{ $data['last_name'] ?? '' }}</strong>
                                <small class="d-block text-muted">
                                    {{ $data['date_of_birth'] ?? 'No date of birth' }}
                                    @if (!empty($data['gender'])) · {{ ucfirst($data['gender']) }} @endif
                                </small>
                                <small class="d-block text-muted">
                                    {{ $data['phone'] ?? '' }}
                                    @if (!empty($data['email'])) · {{ $data['email'] }} @endif
                                </small>
                                @if (!empty($data['roll_number']) || !empty($data['gr_number']))
                                    <small class="d-block text-muted">
                                        R.NO {{ filled($data['roll_number'] ?? null) ? $data['roll_number'] : 'Auto-generated' }}
                                        · GR.NO {{ filled($data['gr_number'] ?? null) ? $data['gr_number'] : 'Auto-generated' }}
                                    </small>
                                @else
                                    <small class="d-block text-muted">R.NO and GR.NO will be generated for this branch</small>
                                @endif
                                @if (!empty($data['place_of_birth']) || !empty($data['state']))
                                    <small class="d-block text-muted">
                                        {{ collect([$data['place_of_birth'] ?? null, $data['state'] ?? null])->filter()->implode(', ') }}
                                    </small>
                                @endif
                                @if (!empty($data['nationality']) || !empty($data['mother_tongue']) || !empty($data['religion']) || !empty($data['blood_group']) || !empty($data['category']) || !empty($data['caste']))
                                    <small class="d-block text-muted">
                                        {{ collect([$data['nationality'] ?? null, $data['mother_tongue'] ?? null, $data['religion'] ?? null, $data['blood_group'] ?? null, $data['caste'] ?? null, $data['category'] ?? null])->filter()->implode(' · ') }}
                                    </small>
                                @endif
                                @if (!empty($data['aadhaar_number']))
                                    <small class="d-block text-muted">Aadhaar number provided (stored encrypted)</small>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $data['academic_year'] ?? '—' }}</strong>
                                <small class="d-block text-muted">
                                    {{ $data['class_name'] ?? '' }}
                                    @if (!empty($data['section_name'])) · {{ $data['section_name'] }} @endif
                                </small>
                                <small class="d-block text-muted">
                                    {{ $data['course_code'] ?? '' }}
                                    @if (!empty($data['semester_name'])) · {{ $data['semester_name'] }} @endif
                                </small>
                            </td>
                            <td>
                                @if (!empty($data['guardian_name']) || !empty($data['guardian_phone']))
                                    {{ $data['guardian_name'] ?? 'Existing guardian' }}
                                    <small class="d-block text-muted">{{ $data['guardian_phone'] ?? '' }}</small>
                                @endif
                                @foreach (['father' => 'Father', 'mother' => 'Mother'] as $parentKey => $parentLabel)
                                    @if (!empty($data[$parentKey.'_name']) || !empty($data[$parentKey.'_occupation']) || !empty($data[$parentKey.'_phone']) || !empty($data[$parentKey.'_email']))
                                        <div class="{{ !empty($data['guardian_name']) || !empty($data['guardian_phone']) || ! $loop->first ? 'mt-2' : '' }}">
                                            <strong>{{ $parentLabel }}:</strong>
                                            {{ filled($data[$parentKey.'_name'] ?? null) ? $data[$parentKey.'_name'] : 'Details provided' }}
                                            <small class="d-block text-muted">
                                                {{ collect([$data[$parentKey.'_occupation'] ?? null, $data[$parentKey.'_phone'] ?? null, $data[$parentKey.'_email'] ?? null])->filter()->implode(' · ') }}
                                            </small>
                                        </div>
                                    @endif
                                @endforeach
                                @if (! $hasGuardianDetails)
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if ($hasErrors)
                                    <span class="badge badge-danger">Needs correction</span>
                                    <ul class="student-import-row-errors">
                                        @foreach ($row['errors'] as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                @else
                                    <span class="badge badge-success">Ready</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-admin.table>
        </x-admin.card>

        <div class="student-import-preview-actions">
            <a class="btn btn-outline-secondary" href="{{ route('imports.students') }}">Cancel</a>
            <button class="btn btn-primary" type="submit" @disabled($validRows === 0)>
                <i class="fa-solid fa-file-import mr-2" aria-hidden="true"></i>
                Import {{ number_format($validRows) }} {{ \Illuminate\Support\Str::plural('student', $validRows) }}
            </button>
        </div>
    </form>
@endsection
