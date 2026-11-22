<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Admission;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Guardian;
use App\Models\ImportHistory;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\StudentRegisterNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Previews, records, and safely reverses tenant-scoped student imports.
 */
class ImportController extends Controller
{
    /**
     * Read CSV or the first worksheet of an XLSX file without adding a parser dependency.
     * Workbook expansion and row counts are capped to keep uploads bounded.
     *
     * @return array<int, array<int, string|null>>
     */
    public function spreadsheetRows(string $path, string $extension): array
    {
        if ($extension !== 'xlsx') {
            $handle = fopen($path, 'r');
            try {
                $rows = [];
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($rows) >= 10000) {
                        throw ValidationException::withMessages(['file' => 'A single import may contain no more than 10,000 rows.']);
                    }
                    $rows[] = $row;
                }

                return $rows;
            } finally {
                fclose($handle);
            }
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw ValidationException::withMessages(['file' => 'The XLSX workbook could not be opened.']);
        }
        try {
            $shared = [];
            $sharedStat = $zip->statName('xl/sharedStrings.xml');
            if ($sharedStat && $sharedStat['size'] > 20 * 1024 * 1024) {
                throw ValidationException::withMessages(['file' => 'The XLSX shared strings are too large to import safely.']);
            }
            if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $strings = simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
                foreach ($strings->si ?? [] as $item) {
                    $shared[] = implode('', array_map('strval', $item->xpath('.//*[local-name()="t"]') ?: []));
                }
            }

            $sheetStat = $zip->statName('xl/worksheets/sheet1.xml');
            if (! $sheetStat) {
                throw ValidationException::withMessages(['file' => 'The XLSX workbook has no first worksheet.']);
            }
            if ($sheetStat['size'] > 20 * 1024 * 1024) {
                throw ValidationException::withMessages(['file' => 'The first XLSX worksheet is too large to import safely.']);
            }

            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) {
                throw ValidationException::withMessages(['file' => 'The XLSX workbook has no first worksheet.']);
            }
            $sheet = simplexml_load_string($sheetXml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
            $rows = [];
            foreach ($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $xmlRow) {
                if (count($rows) >= 10001) {
                    throw ValidationException::withMessages(['file' => 'A single import may contain no more than 10,000 rows plus a header.']);
                }
                $cells = [];
                foreach ($xmlRow->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                    if (! preg_match('/^([A-Z]{1,3})[1-9][0-9]*$/', (string) $cell['r'], $match)) {
                        throw ValidationException::withMessages(['file' => 'The XLSX worksheet contains an invalid cell reference.']);
                    }

                    $letters = $match[1];
                    $column = 0;
                    foreach (str_split($letters) as $letter) {
                        $column = $column * 26 + ord($letter) - 64;
                    }
                    $index = $column - 1;
                    if ($index >= 256) {
                        throw ValidationException::withMessages(['file' => 'The XLSX worksheet contains too many columns to import safely.']);
                    }

                    $type = (string) $cell['t'];
                    if ($type === 's') {
                        $value = $shared[(int) ($cell->v ?? 0)] ?? '';
                    } elseif ($type === 'inlineStr') {
                        $value = implode('', array_map('strval', $cell->xpath('.//*[local-name()="t"]') ?: []));
                    } else {
                        $value = (string) ($cell->v ?? '');
                    }
                    $cells[$index] = $value;
                }
                if ($cells) {
                    ksort($cells);
                    $rows[] = array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells);
                }
            }

            return $rows;
        } finally {
            $zip->close();
        }
    }

    public function create(Request $request)
    {
        $accessibleBranches = $request->user()->accessibleBranchIds();
        $histories = ImportHistory::query()->with(['uploader', 'branch'])
            ->where('entity', 'students')
            ->when($accessibleBranches !== null, fn ($q) => $q
                ->whereIn('branch_id', $accessibleBranches))
            ->latest()
            ->paginate(10);

        return view('imports.students', ['branches' => Branch::query()->where('is_active', true)
            ->when($accessibleBranches !== null, fn ($q) => $q
                ->whereIn('id', $accessibleBranches))
            ->orderBy('name')
            ->get(), 'histories' => $histories]);
    }

    public function template()
    {
        $headers = [
            'first_name',
            'last_name',
            'date_of_birth',
            'gender',
            'phone',
            'email',
            'address',
            'branch_code',
            'academic_year',
            'class_name',
            'section_name',
            'course_code',
            'semester_name',
            'guardian_name',
            'guardian_phone',
            'roll_number',
            'gr_number',
            'place_of_birth',
            'state',
            'nationality',
            'mother_tongue',
            'religion',
            'blood_group',
            'aadhaar_number',
            'caste',
            'category',
            'father_name',
            'father_occupation',
            'father_phone',
            'father_email',
            'mother_name',
            'mother_occupation',
            'mother_phone',
            'mother_email',
        ];

        return response(implode(',', $headers)."\r\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=student-import-template.csv',
        ]);
    }

    /** Normalize spreadsheet column labels to the documented snake_case header names. */
    private function normalizeHeader(string $header): string
    {
        $header = ltrim(trim($header), "\xEF\xBB\xBF");
        $normalized = trim((string) preg_replace('/[^a-z0-9]+/i', '_', strtolower($header)), '_');

        return $normalized === 'student_class' ? 'class_name' : $normalized;
    }

    /** Normalize master values for case-insensitive matching within a branch. */
    private function normalizeValue(?string $value): string
    {
        return mb_strtolower(trim((string) $value));
    }

    /** Convert supported spreadsheet date formats to the database date format. */
    private function parseImportDate(string $value): ?string
    {
        $value = trim($value);

        if (is_numeric($value) && (float) $value >= 20000 && (float) $value <= 80000) {
            return (new \DateTimeImmutable('1899-12-30'))
                ->modify('+'.(int) $value.' days')
                ->format('Y-m-d');
        }

        foreach (['!d/m/Y', '!Y-m-d', '!d-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();

            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /** Create one optional father or mother guardian from imported parent details. */
    private function createImportedParentGuardian(
        Branch $branch,
        array $data,
        string $parent,
    ): ?Guardian {
        $fields = ['name', 'occupation', 'phone', 'email'];
        $hasDetails = collect($fields)->contains(fn (string $field): bool => filled($data[$parent.'_'.$field] ?? null));

        if (! $hasDetails) {
            return null;
        }

        $name = trim((string) ($data[$parent.'_name'] ?? ''));
        $nameParts = $name === ''
            ? [ucfirst($parent), null]
            : array_pad(preg_split('/\s+/', $name, 2) ?: [], 2, null);

        return Guardian::query()->create([
            'branch_id' => $branch->id,
            'first_name' => $nameParts[0],
            'last_name' => $nameParts[1],
            'relation' => ucfirst($parent),
            'occupation' => filled($data[$parent.'_occupation'] ?? null) ? $data[$parent.'_occupation'] : null,
            'phone' => filled($data[$parent.'_phone'] ?? null) ? $data[$parent.'_phone'] : null,
            'email' => filled($data[$parent.'_email'] ?? null) ? $data[$parent.'_email'] : null,
        ]);
    }

    public function preview(Request $request)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:csv,txt,xlsx|max:5120',
            'branch_id' => $request->user()->currentBranchId() ? 'nullable|integer|exists:branches,id' : 'required|integer|exists:branches,id',
        ]);
        $branchId = (int) ($request->user()->currentBranchId() ?: $data['branch_id']);
        abort_unless($request->user()->canAccessBranch($branchId), 403);
        $branch = Branch::query()->findOrFail($branchId);

        try {
            $sheetRows = $this->spreadsheetRows($request->file('file')->getRealPath(), strtolower($request->file('file')->getClientOriginalExtension()));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            return back()->withErrors(['file' => 'The spreadsheet is invalid or could not be read.'])->withInput();
        }
        $header = array_map(fn ($value): string => $this->normalizeHeader((string) $value), array_shift($sheetRows) ?: []);
        $allowedHeaders = [
            'first_name', 'last_name', 'date_of_birth', 'gender', 'phone', 'email', 'address',
            'branch_code', 'academic_year', 'class_name', 'section_name', 'course_code',
            'semester_name', 'guardian_name', 'guardian_phone', 'roll_number', 'gr_number',
            'place_of_birth', 'state', 'nationality', 'mother_tongue', 'religion', 'blood_group',
            'aadhaar_number', 'caste', 'category', 'father_name', 'father_occupation', 'father_phone',
            'father_email', 'mother_name', 'mother_occupation', 'mother_phone', 'mother_email',
        ];
        $requiredHeaders = ['first_name', 'branch_code', 'academic_year'];

        if (in_array('', $header, true)) {
            return back()->withErrors(['file' => 'Every column needs a header. Remove blank columns and try again.'])->withInput();
        }

        if (count($header) !== count(array_unique($header))) {
            return back()->withErrors(['file' => 'Column names must be unique. Check the header row and try again.'])->withInput();
        }

        $missing = array_diff($requiredHeaders, $header);
        $unsupported = array_diff($header, $allowedHeaders);
        if ($missing || $unsupported) {
            $messages = [];
            if ($missing) {
                $messages[] = 'Missing required columns: '.implode(', ', $missing).'.';
            }
            if ($unsupported) {
                $messages[] = 'Unrecognized columns: '.implode(', ', $unsupported).'. Use the downloaded template headers.';
            }

            return back()->withErrors(['file' => implode(' ', $messages)])->withInput();
        }

        $years = AcademicYear::query()
            ->where('branch_id', $branchId)
            ->get()
            ->keyBy(fn (AcademicYear $year): string => $this->normalizeValue($year->name));
        $classes = SchoolClass::query()
            ->where('branch_id', $branchId)
            ->get()
            ->keyBy(fn (SchoolClass $class): string => $class->academic_year_id.'|'.$this->normalizeValue($class->name));
        $sections = Section::query()
            ->where('branch_id', $branchId)
            ->get()
            ->keyBy(fn (Section $section): string => $this->normalizeValue($section->name));
        $courses = Course::query()
            ->where('branch_id', $branchId)
            ->get()
            ->keyBy(fn (Course $course): string => $this->normalizeValue($course->code));
        $semesters = Semester::query()
            ->with('course')
            ->whereHas('course', fn ($query) => $query->where('branch_id', $branchId))
            ->get()
            ->keyBy(fn (Semester $semester): string => $semester->course_id.'|'.$this->normalizeValue($semester->name));
        $guardians = Guardian::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('phone')
            ->get()
            ->keyBy(fn (Guardian $guardian): string => $this->normalizeValue($guardian->phone));
        $existingRollNumbers = Student::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('roll_number')
            ->pluck('roll_number')
            ->map(fn (string $value): string => $this->normalizeValue($value))
            ->all();
        $existingGrNumbers = Student::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('gr_number')
            ->pluck('gr_number')
            ->map(fn (string $value): string => $this->normalizeValue($value))
            ->all();

        $preview = [];
        $seen = [];
        $seenRegisterNumbers = [];
        $rowNumber = 1;
        foreach ($sheetRows as $columns) {
            if (! collect($columns)->contains(fn ($value) => ! blank($value))) {
                continue;
            }
            $rowNumber++;
            $unexpectedValues = array_slice($columns, count($header));
            if (collect($unexpectedValues)->contains(fn ($value) => ! blank($value))) {
                $preview[] = ['row' => $rowNumber, 'data' => [], 'errors' => ['Column count does not match the header.']];

                continue;
            }
            $columns = array_slice(array_pad($columns, count($header), ''), 0, count($header));
            $row = array_combine($header, $columns);
            $row = array_map(fn ($value): string => trim((string) $value), $row);
            $errors = [];
            $resolved = [
                'academic_year_id' => null,
                'school_class_id' => null,
                'section_id' => null,
                'course_id' => null,
                'semester_id' => null,
                'guardian_id' => null,
                'father_guardian_id' => null,
                'mother_guardian_id' => null,
                'date_of_birth' => null,
            ];

            if (blank($row['first_name'] ?? null)) {
                $errors[] = 'First name is required.';
            }
            if (mb_strlen($row['first_name'] ?? '') > 100 || mb_strlen($row['last_name'] ?? '') > 100) {
                $errors[] = 'First and last names must be 100 characters or fewer.';
            }
            if (mb_strlen($row['phone'] ?? '') > 30 || mb_strlen($row['email'] ?? '') > 150) {
                $errors[] = 'Phone must be 30 characters or fewer and email 150 characters or fewer.';
            }
            if (mb_strlen($row['address'] ?? '') > 10000) {
                $errors[] = 'Address is too long.';
            }
            if (strtoupper($row['branch_code'] ?? '') !== strtoupper((string) $branch->code)) {
                $errors[] = 'The branch code does not match the selected branch.';
            }
            $year = $years->get($this->normalizeValue($row['academic_year'] ?? ''));
            if (! $year) {
                $errors[] = 'Academic year was not found for this branch.';
            } else {
                $resolved['academic_year_id'] = $year->id;
            }
            if (! blank($row['date_of_birth'] ?? null)) {
                $resolved['date_of_birth'] = $this->parseImportDate($row['date_of_birth']);
                if (! $resolved['date_of_birth']) {
                    $errors[] = 'Date of birth must use DD/MM/YYYY, YYYY-MM-DD, or DD-MM-YYYY.';
                }
            }
            if (! blank($row['gender'] ?? null)) {
                $row['gender'] = strtolower($row['gender']);
                if (! in_array($row['gender'], ['male', 'female', 'other'], true)) {
                    $errors[] = 'Gender must be male, female, or other.';
                }
            }
            if (! blank($row['email'] ?? null) && ! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email address is not valid.';
            }

            $optionalTextLimits = [
                'roll_number' => 50,
                'gr_number' => 50,
                'place_of_birth' => 150,
                'state' => 100,
                'nationality' => 100,
                'mother_tongue' => 100,
                'caste' => 100,
                'father_name' => 150,
                'father_occupation' => 150,
                'father_phone' => 30,
                'father_email' => 150,
                'mother_name' => 150,
                'mother_occupation' => 150,
                'mother_phone' => 30,
                'mother_email' => 150,
            ];
            foreach ($optionalTextLimits as $field => $limit) {
                if (mb_strlen($row[$field] ?? '') > $limit) {
                    $errors[] = str($field)->replace('_', ' ')->title().' must be '.$limit.' characters or fewer.';
                }
            }

            foreach (['father_email', 'mother_email'] as $emailField) {
                if (! blank($row[$emailField] ?? null) && ! filter_var($row[$emailField], FILTER_VALIDATE_EMAIL)) {
                    $errors[] = str($emailField)->replace('_', ' ')->title().' is not valid.';
                }
            }

            $optionFields = [
                'religion' => ['Hindu', 'Muslim', 'Christian', 'Sikh', 'Buddhist', 'Jain', 'Parsi', 'Other'],
                'blood_group' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
                'category' => ['General', 'OBC', 'SC', 'ST', 'EWS', 'Other'],
            ];
            foreach ($optionFields as $field => $options) {
                if (blank($row[$field] ?? null)) {
                    continue;
                }

                $canonicalValue = collect($options)->first(fn (string $option): bool => strcasecmp($option, $row[$field]) === 0);
                if ($canonicalValue === null) {
                    $errors[] = str($field)->replace('_', ' ')->title().' must be one of: '.implode(', ', $options).'.';
                } else {
                    $row[$field] = $canonicalValue;
                }
            }

            if (! blank($row['aadhaar_number'] ?? null) && ! preg_match('/^\d{12}$/D', $row['aadhaar_number'])) {
                $errors[] = 'Aadhaar number must contain exactly 12 digits. Format this spreadsheet cell as text to preserve leading zeroes.';
            }

            foreach (['roll_number', 'gr_number'] as $field) {
                if (blank($row[$field] ?? null)) {
                    continue;
                }

                $numberKey = $this->normalizeValue($row[$field]);
                $existingValues = $field === 'roll_number' ? $existingRollNumbers : $existingGrNumbers;
                if (in_array($numberKey, $existingValues, true) || isset($seenRegisterNumbers[$field][$numberKey])) {
                    $label = $field === 'roll_number' ? 'R.NO' : 'GR.NO';
                    $errors[] = $label.' is already used in this branch.';
                }
                $seenRegisterNumbers[$field][$numberKey] = true;
            }

            if (! blank($row['class_name'] ?? null)) {
                $class = $year
                    ? $classes->get($year->id.'|'.$this->normalizeValue($row['class_name']))
                    : null;
                if (! $class) {
                    $errors[] = 'Class was not found for this branch and academic year.';
                } else {
                    $resolved['school_class_id'] = $class->id;
                }
            }
            if (! blank($row['section_name'] ?? null)) {
                $section = $sections->get($this->normalizeValue($row['section_name']));
                if (! $section) {
                    $errors[] = 'Section was not found for this branch.';
                } else {
                    $resolved['section_id'] = $section->id;
                }
            }
            if (! blank($row['course_code'] ?? null)) {
                $course = $courses->get($this->normalizeValue($row['course_code']));
                if (! $course) {
                    $errors[] = 'Course code was not found for this branch.';
                } else {
                    $resolved['course_id'] = $course->id;
                }
            }
            if (! blank($row['semester_name'] ?? null)) {
                $semester = $resolved['course_id']
                    ? $semesters->get($resolved['course_id'].'|'.$this->normalizeValue($row['semester_name']))
                    : null;
                if (! $semester) {
                    $errors[] = 'Semester was not found for the selected course. Include a valid course_code.';
                } else {
                    $resolved['semester_id'] = $semester->id;
                }
            }
            if (! blank($row['guardian_name'] ?? null) || ! blank($row['guardian_phone'] ?? null)) {
                if (blank($row['guardian_name'] ?? null) || blank($row['guardian_phone'] ?? null)) {
                    $existingGuardian = $guardians->get($this->normalizeValue($row['guardian_phone'] ?? ''));
                    if (! $existingGuardian) {
                        $errors[] = 'Enter both guardian_name and guardian_phone, or use an existing guardian phone.';
                    } else {
                        $resolved['guardian_id'] = $existingGuardian->id;
                    }
                } else {
                    $existingGuardian = $guardians->get($this->normalizeValue($row['guardian_phone']));
                    $resolved['guardian_id'] = $existingGuardian?->id;
                }
            }
            foreach (['father', 'mother'] as $parent) {
                $parentPhone = $row[$parent.'_phone'] ?? '';
                if ($parentPhone !== '') {
                    $resolved[$parent.'_guardian_id'] = $guardians->get($this->normalizeValue($parentPhone))?->id;
                }
            }

            $key = implode('|', [
                $this->normalizeValue($row['first_name'] ?? ''),
                $this->normalizeValue($row['last_name'] ?? ''),
                $row['date_of_birth'] ?? '',
                $this->normalizeValue($row['phone'] ?? ''),
            ]);
            if (isset($seen[$key])) {
                $errors[] = 'Duplicate row in file.';
            }
            $seen[$key] = true;
            $preview[] = ['row' => $rowNumber, 'data' => $row, 'resolved' => $resolved, 'errors' => $errors];
        }

        if (! $preview) {
            return back()->withErrors(['file' => 'The spreadsheet has no student rows to import.'])->withInput();
        }
        session(['student_import' => [
            'branch_id' => $branchId,
            'file_name' => $request->file('file')->getClientOriginalName(),
            'rows' => $preview,
        ]]);

        return view('imports.preview', [
            'rows' => $preview,
            'branch' => $branch,
            'validRows' => collect($preview)->filter(fn (array $row): bool => empty($row['errors']))->count(),
            'invalidRows' => collect($preview)->filter(fn (array $row): bool => ! empty($row['errors']))->count(),
        ]);
    }

    public function confirm(Request $request, StudentRegisterNumberService $registerNumbers)
    {
        $batch = session('student_import');
        if (! is_array($batch) || empty($batch['rows'])) {
            return redirect()->route('imports.students')->withErrors(['file' => 'Import preview has expired. Upload the CSV again.']);
        }
        $branch = Branch::query()->findOrFail($batch['branch_id']);
        abort_unless($request->user()->canAccessBranch($branch->id), 403);
        $rows = $batch['rows'];

        $importedStudentIds = [];
        // Keep batch history, imported students, and their admissions/enrollments consistent.
        $history = DB::transaction(function () use ($rows, $branch, $request, $batch, $registerNumbers, &$importedStudentIds) {
            $history = ImportHistory::create(['branch_id' => $branch->id, 'uploaded_by' => $request
                ->user()
                ->id, 'entity' => 'students', 'file_name' => basename($batch['file_name'] ?? 'student-import.csv'), 'total_rows' => count($rows), 'status' => 'processing']);
            $success = 0;
            $errors = [];
            foreach ($rows as $row) {
                if ($row['errors']) {
                    $errors[$row['row']] = $row['errors'];

                    continue;
                }
                try {
                    DB::transaction(function () use ($row, $branch, $registerNumbers, &$success, &$importedStudentIds, $request) {
                        Branch::query()->whereKey($branch->id)->lockForUpdate()->firstOrFail();
                        $resolved = $row['resolved'];
                        $year = AcademicYear::query()
                            ->whereKey($resolved['academic_year_id'])
                            ->where('branch_id', $branch->id)
                            ->firstOrFail();
                        $next = Student::query()->where('branch_id', $branch->id)->count() + 1;
                        $student = Student::create([
                            'branch_id' => $branch->id,
                            'student_id' => $branch->code.'-'.str_pad($next, 5, '0', STR_PAD_LEFT),
                            'admission_number' => $branch->code.'/'.now()->format('Y').'/'.str_pad($next, 5, '0', STR_PAD_LEFT),
                            'first_name' => $row['data']['first_name'],
                            'last_name' => ($row['data']['last_name'] ?? null) ?: null,
                            'date_of_birth' => $resolved['date_of_birth'],
                            'gender' => ($row['data']['gender'] ?? null) ?: null,
                            'phone' => ($row['data']['phone'] ?? null) ?: null,
                            'email' => ($row['data']['email'] ?? null) ?: null,
                            'address' => ($row['data']['address'] ?? null) ?: null,
                            'roll_number' => filled($row['data']['roll_number'] ?? null)
                                ? $row['data']['roll_number']
                                : $registerNumbers->nextRollNumber($branch),
                            'gr_number' => filled($row['data']['gr_number'] ?? null)
                                ? $row['data']['gr_number']
                                : $registerNumbers->nextGrNumber($branch),
                            'place_of_birth' => ($row['data']['place_of_birth'] ?? null) ?: null,
                            'state' => ($row['data']['state'] ?? null) ?: null,
                            'nationality' => ($row['data']['nationality'] ?? null) ?: null,
                            'mother_tongue' => ($row['data']['mother_tongue'] ?? null) ?: null,
                            'religion' => ($row['data']['religion'] ?? null) ?: null,
                            'blood_group' => ($row['data']['blood_group'] ?? null) ?: null,
                            'aadhaar_number' => ($row['data']['aadhaar_number'] ?? null) ?: null,
                            'caste' => ($row['data']['caste'] ?? null) ?: null,
                            'category' => ($row['data']['category'] ?? null) ?: null,
                        ]);
                        StudentEnrollment::create([
                            'student_id' => $student->id,
                            'branch_id' => $branch->id,
                            'academic_year_id' => $year->id,
                            'school_class_id' => $resolved['school_class_id'],
                            'section_id' => $resolved['section_id'],
                            'course_id' => $resolved['course_id'],
                            'semester_id' => $resolved['semester_id'],
                            'admission_date' => today(),
                        ]);

                        $hasPrimaryGuardian = false;
                        if ($resolved['guardian_id']) {
                            $guardian = Guardian::query()
                                ->whereKey($resolved['guardian_id'])
                                ->where('branch_id', $branch->id)
                                ->firstOrFail();
                            $student->guardians()->attach($guardian->id, ['is_primary' => true]);
                            $hasPrimaryGuardian = true;
                        } elseif (filled($row['data']['guardian_name'] ?? null) && filled($row['data']['guardian_phone'] ?? null)) {
                            $nameParts = explode(' ', $row['data']['guardian_name'], 2);
                            $guardian = Guardian::create([
                                'branch_id' => $branch->id,
                                'first_name' => $nameParts[0],
                                'last_name' => $nameParts[1] ?? null,
                                'phone' => $row['data']['guardian_phone'],
                            ]);
                            $student->guardians()->attach($guardian->id, ['is_primary' => true]);
                            $hasPrimaryGuardian = true;
                        }

                        foreach (['father', 'mother'] as $parent) {
                            $parentGuardianId = $resolved[$parent.'_guardian_id'] ?? null;
                            $parentGuardian = $parentGuardianId
                                ? Guardian::query()->whereKey($parentGuardianId)->where('branch_id', $branch->id)->firstOrFail()
                                : $this->createImportedParentGuardian($branch, $row['data'], $parent);

                            if ($parentGuardian && ! $student->guardians()->whereKey($parentGuardian->id)->exists()) {
                                $student->guardians()->attach($parentGuardian->id, [
                                    'is_primary' => ! $hasPrimaryGuardian,
                                ]);
                                $hasPrimaryGuardian = true;
                            }
                        }

                        Admission::create([
                            'student_id' => $student->id,
                            'branch_id' => $branch->id,
                            'academic_year_id' => $year->id,
                            'admission_number' => $student->admission_number,
                            'admission_date' => today(),
                            'status' => 'admitted',
                            'created_by' => $request->user()->id,
                        ]);
                        $success++;
                        $importedStudentIds[] = $student->id;
                    });
                } catch (\Throwable) {
                    $errors[$row['row']] = ['Could not import this row. Check the date, gender, and required values.'];
                }
            }
            $history->update(['success_rows' => $success, 'failed_rows' => count($errors), 'errors' => $errors, 'imported_student_ids' => $importedStudentIds, 'status' => $errors ? 'completed_with_errors' : 'completed']);

            return $history;
        });
        session()->forget('student_import');

        return redirect()->route('imports.students')->with('success', "Import completed: {$history->success_rows} successful, {$history->failed_rows} failed.");
    }

    public function rollback(Request $request, ImportHistory $history)
    {
        abort_unless($history->entity === 'students' && $request->user()->canAccessBranch($history->branch_id), 403);
        abort_if($history->rolled_back_at, 409, 'This import has already been rolled back.');
        $studentIds = $history->imported_student_ids ?? [];
        abort_if(! $studentIds, 422, 'This import does not have rollback tracking.');
        $linkedGuardianIds = DB::table('guardian_student')
            ->whereIn('student_id', $studentIds)
            ->distinct()
            ->pluck('guardian_id');
        // Only the import's own enrollment, admission, and history rows are safe to cascade away.
        $generatedTables = ['student_enrollments', 'admissions', 'student_histories'];
        foreach (DB::getSchemaBuilder()->getTables() as $tableInfo) {
            $table = is_array($tableInfo) ? ($tableInfo['name'] ?? null) : ($tableInfo->name ?? null);
            if (! $table || $table === 'students' || in_array($table, $generatedTables, true) || ! Schema::hasColumn($table, 'student_id')) {
                continue;
            }
            if (DB::table($table)->whereIn('student_id', $studentIds)->exists()) {
                return back()->withErrors(['rollback' => 'This import includes students with linked activity or accounts in '.$table.'. Remove those dependent records through their normal workflows before rolling it back.']);
            }
        }
        DB::transaction(function () use ($history, $studentIds, $linkedGuardianIds): void {
            Student::query()->where('branch_id', $history->branch_id)->whereIn('id', $studentIds)->delete();

            Guardian::query()->whereIn('id', $linkedGuardianIds)->get()->each(function (Guardian $guardian): void {
                if (! $guardian->students()->exists() && ! $guardian->userAccounts()->exists()) {
                    $guardian->delete();
                }
            });

            $history->update(['rolled_back_at' => now(), 'status' => 'rolled_back']);
        });

        return back()->with('success', 'Imported student records were rolled back. Import history has been retained.');
    }
}
