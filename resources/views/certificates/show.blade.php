@php
    $certificateTypes = [
        'transfer' => 'Transfer Certificate',
        'character' => 'Character Certificate',
        'bonafide' => 'Bonafide Certificate',
        'migration' => 'Migration Certificate',
        'school_leaving' => 'School Leaving Certificate',
    ];
    $institutionName = $snapshot['institution_name'] ?? 'Educational Institution';
    $institutionLogo = $tenant?->logo;
    $branchAddress = $snapshot['branch_address'] ?: ($snapshot['institution_address'] ?? null);
    $classDetails = collect([$snapshot['class_name'] ?? null, $snapshot['section_name'] ?? null])->filter()->implode(' · ');
    $courseDetails = collect([$snapshot['course_name'] ?? null, $snapshot['semester_name'] ?? null])->filter()->implode(' · ');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $certificateTypes[$certificate->certificate_type] ?? 'Student Certificate' }} · {{ $snapshot['student_name'] ?? 'Student' }}</title>
    <style>
        :root { color-scheme: light; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef2f7; color: #172033; font: 15px/1.65 Georgia, 'Times New Roman', serif; }
        .toolbar { max-width: 210mm; margin: 1rem auto; display: flex; justify-content: space-between; align-items: center; gap: 1rem; font: 14px/1.4 Arial, sans-serif; }
        .toolbar a, .toolbar button { border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; color: #1e3a5f; padding: .65rem 1rem; text-decoration: none; cursor: pointer; }
        .paper { position: relative; width: 210mm; min-height: 297mm; margin: 0 auto 2rem; padding: 22mm 22mm 20mm; background: #fff; box-shadow: 0 14px 50px rgba(15, 23, 42, .12); }
        .paper::before { content: ''; position: absolute; inset: 9mm; border: 1px solid #b6c7dc; pointer-events: none; }
        .certificate-head { position: relative; display: flex; align-items: center; justify-content: center; gap: 1rem; min-height: 82px; text-align: center; border-bottom: 2px solid #1d3c62; padding-bottom: 1rem; }
        .logo { max-width: 72px; max-height: 72px; object-fit: contain; }
        .institution h1 { margin: 0; color: #15375e; font: 700 24px/1.2 Arial, sans-serif; letter-spacing: .04em; text-transform: uppercase; }
        .institution p { margin: .35rem 0 0; color: #475569; font: 12px/1.4 Arial, sans-serif; }
        .document-meta { display: flex; justify-content: space-between; gap: 1rem; margin-top: 1.1rem; font: 12px Arial, sans-serif; }
        .certificate-title { margin: 2.1rem 0 1.7rem; text-align: center; color: #162f4d; font: 700 21px/1.3 Arial, sans-serif; text-decoration: underline; text-underline-offset: 6px; text-transform: uppercase; }
        .certificate-body { margin-top: 1.2rem; text-align: justify; }
        .certificate-body p { margin: 0 0 1rem; }
        .certificate-body strong { color: #111827; }
        .detail-table { width: 100%; margin: 1.2rem 0; border-collapse: collapse; }
        .detail-table th, .detail-table td { border: 1px solid #cbd5e1; padding: .5rem .65rem; text-align: left; }
        .detail-table th { width: 34%; background: #f5f8fc; font-weight: 700; }
        .closing { margin-top: 2.2rem; }
        .signature-row { display: flex; justify-content: space-between; gap: 2rem; margin-top: 5rem; font: 12px Arial, sans-serif; text-align: center; }
        .signature { width: 30%; padding-top: .55rem; border-top: 1px solid #475569; }
        .seal { position: absolute; right: 25mm; bottom: 38mm; color: #64748b; font: 11px Arial, sans-serif; }
        @media (max-width: 800px) {
            body { background: #fff; }
            .toolbar { margin: .75rem; }
            .paper { width: 100%; min-height: auto; margin: 0; padding: 18mm 12mm; box-shadow: none; }
            .paper::before { inset: 5mm; }
            .institution h1 { font-size: 18px; }
            .signature-row { margin-top: 3rem; }
        }
        @page { size: A4; margin: 0; }
        @media print {
            body { background: #fff; print-color-adjust: exact; -webkit-print-color-adjust: exact; }
            .toolbar { display: none; }
            .paper { width: 210mm; min-height: 297mm; margin: 0; box-shadow: none; page-break-after: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <a href="{{ route('students.certificates.index') }}"><span aria-hidden="true">←</span> Back to certificate register</a>
        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;justify-content:flex-end">
            @if (session('success'))<span role="status" style="color:#166534">{{ session('success') }}</span>@endif
            <button type="button" onclick="window.print()">Print / Save as PDF</button>
        </div>
    </div>

    <main class="paper">
        <header class="certificate-head">
            @if ($institutionLogo)
                <img class="logo" src="{{ \Illuminate\Support\Facades\Storage::url($institutionLogo) }}" alt="{{ $institutionName }} logo">
            @endif
            <div class="institution">
                <h1>{{ $institutionName }}</h1>
                @if (! empty($snapshot['branch_name']))<p>{{ $snapshot['branch_name'] }}</p>@endif
                <p>{{ collect([$branchAddress, $snapshot['branch_phone'] ?? $snapshot['institution_phone'] ?? null])->filter()->implode(' · ') }}</p>
            </div>
        </header>

        <div class="document-meta">
            <span>Certificate No.: <strong>{{ $certificate->certificate_number }}</strong></span>
            <span>Date: <strong>{{ $certificate->issued_on->format('d F Y') }}</strong></span>
        </div>

        <h2 class="certificate-title">{{ $certificateTypes[$certificate->certificate_type] ?? 'Student Certificate' }}</h2>

        <section class="certificate-body">
            @if ($certificate->certificate_type === 'bonafide')
                <p>This is to certify that <strong>{{ $snapshot['student_name'] ?? 'the student' }}</strong>
                    @if (! empty($snapshot['guardian_name'])) child of <strong>{{ $snapshot['guardian_name'] }}</strong>@endif,
                    bearing admission number <strong>{{ $snapshot['admission_number'] ?? '—' }}</strong>, is currently enrolled at {{ $institutionName }}.</p>
                @if ($classDetails || $courseDetails || ! empty($snapshot['academic_year']))
                    <table class="detail-table"><tbody>
                        @if ($classDetails)<tr><th>Class / section</th><td>{{ $classDetails }}</td></tr>@endif
                        @if ($courseDetails)<tr><th>Course / semester</th><td>{{ $courseDetails }}</td></tr>@endif
                        @if (! empty($snapshot['academic_year']))<tr><th>Academic year</th><td>{{ $snapshot['academic_year'] }}</td></tr>@endif
                    </tbody></table>
                @endif
                <p>This certificate is issued on the request of the student / parent @if ($certificate->purpose) for <strong>{{ $certificate->purpose }}</strong>@endif.</p>
            @elseif ($certificate->certificate_type === 'character')
                <p>This is to certify that <strong>{{ $snapshot['student_name'] ?? 'the student' }}</strong>, admission number <strong>{{ $snapshot['admission_number'] ?? '—' }}</strong>, has been a student of {{ $institutionName }}.</p>
                <p>Conduct and behaviour: <strong>{{ $certificate->conduct }}</strong></p>
                <p>This certificate is issued on the date stated above for the student’s records.</p>
            @elseif ($certificate->certificate_type === 'migration')
                <p>This is to certify that <strong>{{ $snapshot['student_name'] ?? 'the student' }}</strong>, admission number <strong>{{ $snapshot['admission_number'] ?? '—' }}</strong>, was enrolled at {{ $institutionName }}.</p>
                <p>The student’s migration is recorded with effect from <strong>{{ $certificate->effective_date?->format('d F Y') }}</strong>. @if ($certificate->purpose) Destination / purpose: <strong>{{ $certificate->purpose }}</strong>.@endif</p>
                <p>This certificate is issued for submission to the receiving institution or education board, where applicable.</p>
            @else
                @if ($certificate->certificate_type === 'transfer')
                    <p>This is to certify that <strong>{{ $snapshot['student_name'] ?? 'the student' }}</strong>
                        @if (! empty($snapshot['guardian_name'])) child of <strong>{{ $snapshot['guardian_name'] }}</strong>@endif,
                        admission number <strong>{{ $snapshot['admission_number'] ?? '—' }}</strong>, was enrolled at {{ $institutionName }}.</p>
                    <p>The student’s transfer / departure is recorded with effect from <strong>{{ $certificate->effective_date?->format('d F Y') }}</strong>.</p>
                @else
                    <p>This is to certify that <strong>{{ $snapshot['student_name'] ?? 'the student' }}</strong>
                        @if (! empty($snapshot['guardian_name'])) child of <strong>{{ $snapshot['guardian_name'] }}</strong>@endif,
                        admission number <strong>{{ $snapshot['admission_number'] ?? '—' }}</strong>, has left {{ $institutionName }}.</p>
                    <p>The school leaving date is <strong>{{ $certificate->effective_date?->format('d F Y') }}</strong>.</p>
                @endif
                @if ($classDetails || $courseDetails || ! empty($snapshot['academic_year']))
                    <table class="detail-table"><tbody>
                        <tr><th>Student ID</th><td>{{ $snapshot['student_id'] ?? '—' }}</td></tr>
                        @if (! empty($snapshot['date_of_birth']))<tr><th>Date of birth</th><td>{{ \Illuminate\Support\Carbon::parse($snapshot['date_of_birth'])->format('d F Y') }}</td></tr>@endif
                        @if (! empty($snapshot['admission_date']))<tr><th>Date of admission</th><td>{{ \Illuminate\Support\Carbon::parse($snapshot['admission_date'])->format('d F Y') }}</td></tr>@endif
                        @if ($classDetails)<tr><th>Class / section</th><td>{{ $classDetails }}</td></tr>@endif
                        @if ($courseDetails)<tr><th>Course / semester</th><td>{{ $courseDetails }}</td></tr>@endif
                        @if (! empty($snapshot['academic_year']))<tr><th>Last recorded academic year</th><td>{{ $snapshot['academic_year'] }}</td></tr>@endif
                    </tbody></table>
                @endif
                @if ($certificate->purpose)<p>Purpose / destination: <strong>{{ $certificate->purpose }}</strong>.</p>@endif
            @endif

            @if ($certificate->remarks)<p><strong>Remarks:</strong> {{ $certificate->remarks }}</p>@endif
            <p class="closing">Issued by {{ $institutionName }} on {{ $certificate->issued_on->format('d F Y') }}.</p>
        </section>

        <div class="signature-row">
            <div class="signature">Student / Parent</div>
            <div class="signature">Class teacher</div>
            <div class="signature">Principal / Head of institution</div>
        </div>
        <div class="seal">Institution seal</div>
    </main>
</body>
</html>
