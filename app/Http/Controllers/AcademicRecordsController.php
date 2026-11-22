<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Services\StudentResultPdfWriter;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Reads and records academic results and timetable entries.
 */
class AcademicRecordsController extends Controller
{
    private function scope(Request $request): array
    {
        $user = $request->user();
        $branchIds = $user->accessibleBranchIds();
        $branchId = $user->currentBranchId() ?: ($request->filled('branch_id') ? $request->integer('branch_id') : null);
        if ($branchId) {
            abort_unless($user->canAccessBranch($branchId), 403);
        }

        return [$branchIds, $branchId];
    }

    /**
     * Show tenant-scoped academic results, filters, and the form's current student selection.
     */
    public function results(Request $request, ?int $studentId = null, ?int $academicYearId = null)
    {
        [$branchIds, $branchId] = $this->scope($request);
        abort_if(($studentId === null) !== ($academicYearId === null), 404);
        $branchFilterId = $request->has('branch_id')
            ? ($request->filled('branch_id') ? $request->integer('branch_id') : null)
            : $branchId;
        if ($branchFilterId) {
            abort_unless($request->user()->canAccessBranch($branchFilterId), 403);
        }
        $examinationLinkReady = Schema::hasTable('examinations')
            && Schema::hasColumn('results', 'examination_id');
        $statusFilter = in_array($request->query('status'), ['draft', 'published'], true)
            ? $request->query('status')
            : null;
        $studentSearch = trim($request->string('student')->toString());
        $recordsQuery = StudentResult::query()
            ->whereHas('student', fn ($query) => $query
                ->when($branchIds !== null, fn ($studentQuery) => $studentQuery->whereIn('branch_id', $branchIds))
                ->when($branchFilterId, fn ($studentQuery) => $studentQuery->where('branch_id', $branchFilterId))
                ->when($studentSearch !== '', fn ($studentQuery) => $studentQuery->where(function ($searchQuery) use ($studentSearch): void {
                    $searchQuery
                        ->where('first_name', 'like', '%'.$studentSearch.'%')
                        ->orWhere('last_name', 'like', '%'.$studentSearch.'%')
                        ->orWhere('student_id', 'like', '%'.$studentSearch.'%')
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%'.$studentSearch.'%']);
                })))
            ->when($request->filled('academic_year_id'), fn ($query) => $query->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('school_class_id'), fn ($query) => $query->where('school_class_id', $request->integer('school_class_id')))
            ->when($request->filled('section_id'), fn ($query) => $query->where('section_id', $request->integer('section_id')))
            ->when($request->filled('course_id'), fn ($query) => $query->where('course_id', $request->integer('course_id')))
            ->when($request->filled('semester_id'), fn ($query) => $query->where('semester_id', $request->integer('semester_id')))
            ->when($examinationLinkReady && $request->filled('examination_id'), fn ($query) => $query->where('examination_id', $request->integer('examination_id')))
            ->when($request->filled('subject_id') && Schema::hasTable('academic_subjects') && Schema::hasColumn('results', 'subject_id'), fn ($query) => $query->where('subject_id', $request->integer('subject_id')))
            ->when($statusFilter, fn ($query) => $query->where('status', $statusFilter));
        $resultCount = (clone $recordsQuery)->count();
        $publishedResultCount = (clone $recordsQuery)->where('status', 'published')->count();
        $draftResultCount = (clone $recordsQuery)->where('status', 'draft')->count();
        $groupFields = [
            'student_id',
            'academic_year_id',
            'school_class_id',
            'section_id',
            'course_id',
            'semester_id',
            'status',
        ];
        if ($examinationLinkReady) {
            $groupFields[] = 'examination_id';
        }
        $results = (clone $recordsQuery)
            ->select($groupFields)
            ->selectRaw('COUNT(*) as subject_count, SUM(results.marks) as total_marks, SUM(results.marks_obtained) as total_obtained, MAX(results.updated_at) as group_updated_at')
            ->groupBy($groupFields)
            ->orderByDesc('group_updated_at')
            ->paginate(20)
            ->withQueryString();
        $results->getCollection()->load([
            'student.branch',
            'academicYear',
            'semester',
            'schoolClass',
            'section',
            'course',
        ]);
        if ($examinationLinkReady) {
            $results->getCollection()->load('examination');
        }
        $editingRecords = collect();

        if ($studentId !== null && $academicYearId !== null) {
            $editingRecords = $this->resultGroupQuery($request, $studentId, $academicYearId)
                ->with(['student.branch', 'academicYear', 'semester', 'schoolClass', 'section', 'course', 'examination'])
                ->orderBy('subject')
                ->get();
            abort_if($editingRecords->isEmpty(), 404);
        }

        $selectedStudent = $editingRecords->first()?->student;

        if (! $selectedStudent && old('student_id')) {
            $selectedStudent = Student::query()
                ->where('status', 'active')
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($branchFilterId, fn ($query) => $query->where('branch_id', $branchFilterId))
                ->find(old('student_id'));
        }

        $subjectMastersReady = Schema::hasTable('academic_subjects')
            && Schema::hasColumn('results', 'subject_id');

        return view('academic.results', [
            'results' => $results,
            'editingRecords' => $editingRecords,
            'selectedStudent' => $selectedStudent,
            'years' => AcademicYear::query()
                ->with('branch')
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($branchFilterId, fn ($query) => $query->where('branch_id', $branchFilterId))
                ->orderByDesc('start_date')
                ->get(),
            'semesters' => Semester::query()
                ->with('course')
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($query) => $query->whereHas('course', fn ($courseQuery) => $courseQuery->whereIn('branch_id', $branchIds)))
                ->when($branchFilterId, fn ($query) => $query->whereHas('course', fn ($courseQuery) => $courseQuery->where('branch_id', $branchFilterId)))
                ->orderBy('name')
                ->get(),
            'classes' => SchoolClass::query()
                ->with('branch')
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($branchFilterId, fn ($query) => $query->where('branch_id', $branchFilterId))
                ->orderBy('name')
                ->get(),
            'sections' => Section::query()
                ->with('branch')
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($branchFilterId, fn ($query) => $query->where('branch_id', $branchFilterId))
                ->orderBy('name')
                ->get(),
            'courses' => Course::query()
                ->with('branch')
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($branchFilterId, fn ($query) => $query->where('branch_id', $branchFilterId))
                ->orderBy('name')
                ->get(),
            'subjects' => $subjectMastersReady ? Subject::query()
                ->with(['schoolClass', 'course'])
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($branchFilterId, fn ($query) => $query->where('branch_id', $branchFilterId))
                ->orderBy('name')
                ->get() : collect(),
            'examinations' => $examinationLinkReady ? DB::table('examinations')
                ->join('academic_years', 'academic_years.id', '=', 'examinations.academic_year_id')
                ->join('branches', 'branches.id', '=', 'examinations.branch_id')
                ->where('examinations.tenant_id', app(TenantContext::class)->id())
                ->when($branchIds !== null, fn ($query) => $query->whereIn('examinations.branch_id', $branchIds))
                ->when($branchFilterId, fn ($query) => $query->where('examinations.branch_id', $branchFilterId))
                ->select([
                    'examinations.id',
                    'examinations.name',
                    'examinations.exam_type',
                    'examinations.status',
                    'examinations.branch_id',
                    'examinations.academic_year_id',
                    'academic_years.name as year_name',
                    'branches.name as branch_name',
                ])
                ->orderByDesc('examinations.starts_on')
                ->orderByDesc('examinations.id')
                ->get() : collect(),
            'branches' => Branch::query()
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($query) => $query->whereIn('id', $branchIds))
                ->orderBy('name')
                ->get(),
            'branchId' => $branchFilterId,
            'resultCount' => $resultCount,
            'publishedResultCount' => $publishedResultCount,
            'draftResultCount' => $draftResultCount,
            'subjectMastersReady' => $subjectMastersReady,
            'examinationLinkReady' => $examinationLinkReady,
        ]);
    }

    /** Build a tenant and branch scoped query for one student's academic result group. */
    private function resultGroupQuery(Request $request, int $studentId, int $academicYearId)
    {
        [$branchIds, $branchId] = $this->scope($request);
        $query = StudentResult::query()
            ->where('student_id', $studentId)
            ->where('academic_year_id', $academicYearId)
            ->whereHas('student', fn ($studentQuery) => $studentQuery
                ->when($branchIds !== null, fn ($scoped) => $scoped->whereIn('branch_id', $branchIds))
                ->when($branchId, fn ($scoped) => $scoped->where('branch_id', $branchId)));

        foreach (['school_class_id', 'section_id', 'course_id', 'semester_id'] as $field) {
            $value = $request->query($field);
            $value = $value === '0' || $value === '' ? null : $value;
            $value ? $query->where($field, $value) : $query->whereNull($field);
        }

        if (Schema::hasColumn('results', 'examination_id')) {
            $examinationId = $request->query('examination_id');
            $examinationId = $examinationId === '0' || $examinationId === '' ? null : $examinationId;
            $examinationId ? $query->where('examination_id', $examinationId) : $query->whereNull('examination_id');
        }

        if (in_array($request->query('group_status'), ['draft', 'published'], true)) {
            $query->where('status', $request->query('group_status'));
        }

        return $query;
    }

    /** Show subject-level marks and academic details for one result group. */
    public function showResultGroup(Request $request, int $student, int $academicYear)
    {
        $records = $this->resultGroupQuery($request, $student, $academicYear)
            ->with(['student.branch', 'academicYear', 'schoolClass', 'section', 'course', 'semester', 'examination'])
            ->orderBy('subject')
            ->get();
        abort_if($records->isEmpty(), 404);

        return view('academic.results.show', ['records' => $records]);
    }

    /** Download a branded PDF result sheet for one tenant-scoped student group. */
    public function downloadResultGroup(Request $request, int $student, int $academicYear, StudentResultPdfWriter $pdf)
    {
        $records = $this->resultGroupQuery($request, $student, $academicYear)
            ->with(['student.branch', 'student.tenant', 'academicYear', 'schoolClass', 'section', 'course', 'semester', 'examination'])
            ->orderBy('subject')
            ->get();
        abort_if($records->isEmpty(), 404);

        $first = $records->first();
        $tenant = $first->student?->tenant;
        $logoPath = $tenant?->logo ? Storage::disk('public')->path($tenant->logo) : null;
        $document = [
            'institution_name' => $tenant?->institution_name ?? config('app.name'),
            'institution_type' => $tenant?->institution_type ?? 'school',
            'logo_path' => $logoPath,
            'branch_name' => $first->student?->branch?->name,
            'address' => $first->student?->branch?->address ?? $tenant?->address,
            'phone' => $first->student?->branch?->phone ?? $tenant?->phone,
            'email' => $tenant?->email,
            'student_name' => trim(($first->student?->first_name ?? '').' '.($first->student?->last_name ?? '')),
            'student_number' => $first->student?->student_id,
            'academic_year' => $first->academicYear?->name,
            'examination_name' => $first->examination?->name ?? 'Not linked',
            'class_section' => trim(implode(' · ', array_filter([
                $first->schoolClass?->name,
                $first->section?->name ? 'Section '.$first->section->name : null,
            ]))) ?: '—',
            'course_semester' => trim(implode(' · ', array_filter([
                $first->course?->name ?? $first->semester?->course?->name,
                $first->semester?->name,
            ]))) ?: '—',
            'status' => $first->status,
        ];
        $subjects = $records->map(fn (StudentResult $record): array => [
            'subject' => $record->subject,
            'marks' => $record->marks,
            'marks_obtained' => $record->marks_obtained,
            'grade' => $record->grade,
        ]);
        $file = $pdf->write($document, $subjects);
        $filename = str($first->student?->student_id ?? 'student')->slug().'-result-'.$academicYear.'.pdf';

        return response()->download($file, $filename, ['Content-Type' => 'application/pdf'])->deleteFileAfterSend(true);
    }

    /** Save edits to an existing result group without allowing its ownership or academic group to change. */
    public function updateResultGroup(Request $request, int $student, int $academicYear)
    {
        $records = $this->resultGroupQuery($request, $student, $academicYear)->get();
        abort_if($records->isEmpty(), 404);
        $first = $records->first();
        $request->merge([
            'student_id' => $first->student_id,
            'academic_year_id' => $first->academic_year_id,
            'school_class_id' => $first->school_class_id,
            'section_id' => $first->section_id,
            'course_id' => $first->course_id,
            'semester_id' => $first->semester_id,
        ]);
        if (Schema::hasColumn('results', 'examination_id')) {
            $request->merge(['examination_id' => $first->examination_id]);
        }

        $this->storeResult($request);

        $updatedStatus = $request->string('status')->toString();

        return redirect()
            ->route('academic.results.show', [
                'student' => $first->student_id,
                'academicYear' => $first->academic_year_id,
                'school_class_id' => $first->school_class_id ?? 0,
                'section_id' => $first->section_id ?? 0,
                'course_id' => $first->course_id ?? 0,
                'semester_id' => $first->semester_id ?? 0,
                'examination_id' => $first->examination_id ?? 0,
                'group_status' => $updatedStatus,
            ])
            ->with('success', 'Student results updated.');
    }

    /** Delete all subject rows in a single tenant and branch scoped result group. */
    public function destroyResultGroup(Request $request, int $student, int $academicYear)
    {
        $records = $this->resultGroupQuery($request, $student, $academicYear)->get();
        abort_if($records->isEmpty(), 404);
        $records->each->delete();

        return redirect()->route('academic.results')->with('success', 'Student result group deleted.');
    }

    /** Search active students within the signed-in user's tenant and campus access. */
    public function searchStudentsForResults(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'branch_id' => ['nullable', 'integer'],
            'school_class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'academic_year_id' => ['nullable', 'integer'],
        ]);
        [$branchIds, $branchId] = $this->scope($request);
        $branchId ??= isset($data['branch_id']) ? (int) $data['branch_id'] : null;

        if ($branchId) {
            abort_unless($request->user()->canAccessBranch($branchId), 403);
        }

        $term = trim($data['q']);
        $students = Student::query()
            ->with('branch')
            ->where('status', 'active')
            ->whereHas('enrollments', fn ($enrollment) => $enrollment
                ->where('status', 'active')
                ->when($data['school_class_id'] ?? null, fn ($query, $id) => $query->where('school_class_id', $id))
                ->when($data['section_id'] ?? null, fn ($query, $id) => $query->where('section_id', $id))
                ->when($data['course_id'] ?? null, fn ($query, $id) => $query->where('course_id', $id))
                ->when($data['academic_year_id'] ?? null, fn ($query, $id) => $query->where('academic_year_id', $id)))
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->where(fn ($query) => $query
                ->where('first_name', 'like', '%'.$term.'%')
                ->orWhere('last_name', 'like', '%'.$term.'%')
                ->orWhere('student_id', 'like', '%'.$term.'%')
                ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ['%'.$term.'%']))
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit(20)
            ->get()
            ->map(fn (Student $student): array => [
                'id' => $student->id,
                'label' => trim($student->first_name.' '.$student->last_name).' · '.$student->student_id,
                'branch_id' => $student->branch_id,
                'branch_name' => $student->branch?->name,
            ]);

        return response()->json(['students' => $students]);
    }

    /** Prepare a campus-scoped sheet with the students and subjects in the selected group. */
    public function resultImportTemplate(Request $request): \Illuminate\Http\Response
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'school_class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'semester_id' => ['nullable', 'integer'],
        ]);
        if (empty($data['school_class_id']) && empty($data['course_id'])) {
            throw ValidationException::withMessages(['import_file' => 'Choose an academic year and a class or course to download the sheet.']);
        }
        $year = AcademicYear::query()->findOrFail($data['academic_year_id']);
        abort_unless($request->user()->canAccessBranch($year->branch_id), 403);

        $students = Student::query()->where('branch_id', $year->branch_id)->where('status', 'active')
            ->whereHas('enrollments', fn ($query) => $query->where('academic_year_id', $year->id)
                ->where('branch_id', $year->branch_id)->where('status', 'active')
                ->when($data['school_class_id'] ?? null, fn ($q, $id) => $q->where('school_class_id', $id))
                ->when($data['section_id'] ?? null, fn ($q, $id) => $q->where('section_id', $id))
                ->when($data['course_id'] ?? null, fn ($q, $id) => $q->where('course_id', $id))
                ->when($data['semester_id'] ?? null, fn ($q, $id) => $q->where('semester_id', $id)))
            ->orderBy('first_name')->orderBy('last_name')->get();
        $subjects = Subject::query()->where('branch_id', $year->branch_id)->where('is_active', true)
            ->where(fn ($query) => $query
                ->when($data['school_class_id'] ?? null, fn ($q, $id) => $q->orWhere('school_class_id', $id))
                ->when($data['course_id'] ?? null, fn ($q, $id) => $q->orWhere('course_id', $id)))
            ->orderBy('name')->get();
        if ($students->isEmpty() || $subjects->isEmpty()) {
            throw ValidationException::withMessages(['import_file' => 'No active students or subjects were found for the selected group.']);
        }
        if ($students->count() * $subjects->count() > 10000) {
            throw ValidationException::withMessages(['import_file' => 'This group is too large for one sheet. Select a narrower group.']);
        }
        $csv = fopen('php://temp', 'w+');
        fputcsv($csv, ['student_id', 'student_name', 'subject_id', 'subject_name', 'total_marks', 'marks_obtained', 'grade'], ',', '"', '');
        foreach ($students as $student) {
            foreach ($subjects as $subject) {
                $safe = fn ($value) => preg_match('/^[\s]*[=+@\-\t\r]/u', (string) $value) ? "'".$value : $value;
                fputcsv($csv, [$safe($student->student_id), $safe(trim($student->first_name.' '.$student->last_name)), $subject->id, $safe($subject->name), '', '', ''], ',', '"', '');
            }
        }
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        return response("\xEF\xBB\xBF".$content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="result-import-template.csv"',
        ]);
    }

    /** Import results atomically using the same checks as individual result entry. */
    public function importResults(Request $request, ImportController $imports)
    {
        $data = $request->validate([
            'academic_year_id' => ['required', 'integer'],
            'examination_id' => ['nullable', 'integer'],
            'school_class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'semester_id' => ['nullable', 'integer'],
            'file' => ['required', 'file', 'mimes:csv,txt,xlsx', 'max:5120'],
        ]);

        if (empty($data['school_class_id']) && empty($data['course_id'])) {
            throw ValidationException::withMessages(['import_file' => 'Choose a class or course for the import.']);
        }
        if (! empty($data['semester_id']) && empty($data['course_id'])) {
            throw ValidationException::withMessages(['import_file' => 'Choose a course when selecting a semester.']);
        }

        $year = AcademicYear::query()->findOrFail($data['academic_year_id']);
        abort_unless($request->user()->canAccessBranch($year->branch_id), 403);

        try {
            $rows = $imports->spreadsheetRows($request->file('file')->getRealPath(), strtolower($request->file('file')->getClientOriginalExtension()));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw ValidationException::withMessages(['import_file' => 'The spreadsheet could not be read.']);
        }

        $header = array_map(fn ($value) => strtolower(trim(ltrim((string) $value, "\xEF\xBB\xBF"))), array_shift($rows) ?: []);
        $expected = ['student_id', 'student_name', 'subject_id', 'subject_name', 'total_marks', 'marks_obtained', 'grade'];
        $legacy = ['student_id', 'subject_code', 'total_marks', 'marks_obtained', 'grade'];
        if ($header !== $expected && $header !== $legacy) {
            throw ValidationException::withMessages(['import_file' => 'Use the downloaded sheet without changing its column headings.']);
        }
        if (! $rows) {
            throw ValidationException::withMessages(['import_file' => 'The spreadsheet contains no result rows.']);
        }

        $imported = DB::transaction(function () use ($rows, $data, $year, $request, $header, $legacy): int {
            $seen = [];
            $count = 0;
            foreach ($rows as $index => $columns) {
                if (count($columns) > count($header) || count(array_filter($columns, fn ($value) => filled($value))) === 0) {
                    throw ValidationException::withMessages(['import_file' => 'Row '.($index + 2).' is empty or has extra columns.']);
                }
                $row = array_combine($header, array_map(fn ($value) => trim((string) $value), array_pad($columns, count($header), '')));
                $studentCode = $row['student_id'];
                $marks = $row['total_marks'];
                $obtained = $row['marks_obtained'];
                $grade = $row['grade'];
                if ($header !== $legacy && $marks === '' && $obtained === '' && $grade === '') {
                    continue;
                }
                $student = Student::query()->where('branch_id', $year->branch_id)->where('student_id', $studentCode)->where('status', 'active')->first();
                $subjects = Subject::query()->where('branch_id', $year->branch_id)->where('is_active', true)
                    ->when($header === $legacy, fn ($q) => $q->where('code', $row['subject_code']), fn ($q) => $q->whereKey($row['subject_id']))
                    ->where(fn ($query) => $query
                        ->when(! empty($data['school_class_id']), fn ($q) => $q->orWhere('school_class_id', $data['school_class_id']))
                        ->when(! empty($data['course_id']), fn ($q) => $q->orWhere('course_id', $data['course_id'])))
                    ->get();
                if (! $student || $subjects->count() !== 1) {
                    throw ValidationException::withMessages(['import_file' => 'Row '.($index + 2).': student ID or subject code was not found uniquely in the selected campus and group.']);
                }
                $key = $student->id.':'.$subjects->first()->id;
                if (isset($seen[$key])) {
                    throw ValidationException::withMessages(['import_file' => 'Row '.($index + 2).': duplicate student and subject.']);
                }
                $seen[$key] = true;
                $existing = StudentResult::query()
                    ->where('student_id', $student->id)
                    ->where('academic_year_id', $year->id)
                    ->where('subject_id', $subjects->first()->id);
                foreach (['examination_id', 'school_class_id', 'section_id', 'course_id', 'semester_id'] as $field) {
                    if ($field === 'examination_id' && ! Schema::hasColumn('results', $field)) {
                        continue;
                    }
                    $existing->where($field, $data[$field] ?? null);
                }
                if ($existing->exists()) {
                    throw ValidationException::withMessages(['import_file' => 'Row '.($index + 2).': this result already exists. Edit it from the results list.']);
                }
                $payload = collect($data)->except('file')->all() + [
                    'student_id' => $student->id,
                    'status' => 'draft',
                    'subjects' => [[
                        'subject_id' => $subjects->first()->id,
                        'marks' => $marks === '' ? null : $marks,
                        'marks_obtained' => $obtained === '' ? null : $obtained,
                        'grade' => $grade === '' ? null : $grade,
                    ]],
                ];
                $rowRequest = Request::create('/', 'POST', $payload);
                $rowRequest->setUserResolver(fn () => $request->user());
                $rowRequest->setLaravelSession($request->session());
                try {
                    $this->storeResult($rowRequest);
                    $count++;
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['import_file' => 'Row '.($index + 2).': '.collect($exception->errors())->flatten()->first()]);
                }
            }
            if ($count === 0) {
                throw ValidationException::withMessages(['import_file' => 'Enter marks or a grade for at least one subject.']);
            }

            return $count;
        });

        return redirect()->route('academic.results')->with('success', $imported.' subject results imported as drafts.');
    }

    /** Validate and save a student's subject results together as one transaction. */
    public function storeResult(Request $request)
    {
        abort_unless(
            Schema::hasTable('academic_subjects') && Schema::hasColumn('results', 'subject_id'),
            503,
            'Apply the pending database migrations before recording results with subject masters.',
        );

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
            'examination_id' => ['nullable', 'integer'],
            'school_class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'semester_id' => ['nullable', 'integer'],
            'subjects' => ['required', 'array', 'min:1', 'max:20'],
            'subjects.*.subject_id' => ['required', 'integer', 'distinct'],
            'subjects.*.marks' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'subjects.*.marks_obtained' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'subjects.*.grade' => ['nullable', 'string', 'max:20'],
            'status' => ['required', 'in:draft,published'],
        ]);
        $student = Student::query()
            ->where('status', 'active')
            ->findOrFail($data['student_id']);
        abort_unless($request->user()->canAccessBranch($student->branch_id), 403);

        if (! AcademicYear::query()
            ->whereKey($data['academic_year_id'])
            ->where('branch_id', $student->branch_id)
            ->exists()) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Choose an academic year from the student’s campus.',
            ]);
        }

        $examinationId = $data['examination_id'] ?? null;
        if ($examinationId && ! Schema::hasColumn('results', 'examination_id')) {
            abort(503, 'Apply the examination-link migration before attaching results to an examination.');
        }

        if ($examinationId) {
            $examination = DB::table('examinations')
                ->where('id', $examinationId)
                ->where('tenant_id', app(TenantContext::class)->id())
                ->where('branch_id', $student->branch_id)
                ->where('academic_year_id', $data['academic_year_id'])
                ->first();

            if (! $examination) {
                throw ValidationException::withMessages([
                    'examination_id' => 'Choose an examination from the student’s branch and academic year.',
                ]);
            }

            if ($data['status'] === 'published' && $examination->status !== 'published') {
                throw ValidationException::withMessages([
                    'status' => 'Publish the examination first, then publish its linked results.',
                ]);
            }
        }

        $enrollment = $student->enrollments()
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('branch_id', $student->branch_id)
            ->first();

        if (! $enrollment) {
            throw ValidationException::withMessages([
                'student_id' => 'The student is not enrolled for this academic year.',
            ]);
        }

        if (! empty($data['school_class_id']) && ! SchoolClass::query()
            ->whereKey($data['school_class_id'])
            ->where('branch_id', $student->branch_id)
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'school_class_id' => 'Choose a class from the student’s campus and academic year.',
            ]);
        }

        if (! empty($data['section_id']) && ! Section::query()
            ->whereKey($data['section_id'])
            ->where('branch_id', $student->branch_id)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'section_id' => 'Choose an active section from the student’s campus.',
            ]);
        }

        if (! empty($data['course_id']) && ! Course::query()
            ->whereKey($data['course_id'])
            ->where('branch_id', $student->branch_id)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages([
                'course_id' => 'Choose an active course from the student’s campus.',
            ]);
        }

        if (! empty($data['semester_id']) && ! Semester::query()
            ->whereKey($data['semester_id'])
            ->where('is_active', true)
            ->whereHas('course', fn ($query) => $query
                ->where('branch_id', $student->branch_id)
                ->when($data['course_id'] ?? null, fn ($courseQuery, $courseId) => $courseQuery->whereKey($courseId)))
            ->exists()) {
            throw ValidationException::withMessages([
                'semester_id' => 'Choose an active semester from the selected course and campus.',
            ]);
        }

        $selectedSemester = ! empty($data['semester_id'])
            ? Semester::query()->with('course')->find($data['semester_id'])
            : null;
        $selectedCourseId = $data['course_id'] ?? $selectedSemester?->course_id;
        $enrollmentFields = [
            'school_class_id' => $data['school_class_id'] ?? null,
            'section_id' => $data['section_id'] ?? null,
            'course_id' => $selectedCourseId,
            'semester_id' => $data['semester_id'] ?? null,
        ];
        $resultContextFields = $enrollmentFields;

        if (Schema::hasColumn('results', 'examination_id')) {
            $resultContextFields['examination_id'] = $examinationId;
        }

        foreach ($enrollmentFields as $field => $selectedValue) {
            if ($selectedValue && (int) $enrollment->{$field} !== (int) $selectedValue) {
                throw ValidationException::withMessages([
                    $field => 'The selected academic group does not match the student’s enrollment for this year.',
                ]);
            }
        }

        if (! $enrollmentFields['school_class_id'] && ! $enrollmentFields['course_id']) {
            throw ValidationException::withMessages([
                'school_class_id' => 'Choose the student’s class or course to load its subject list.',
            ]);
        }

        $subjectIds = collect($data['subjects'])->pluck('subject_id')->map(fn ($id) => (int) $id)->unique();
        $subjectQuery = Subject::query()
            ->whereIn('id', $subjectIds)
            ->where('branch_id', $student->branch_id)
            ->where('is_active', true)
            ->where(function ($query) use ($enrollmentFields): void {
                if ($enrollmentFields['school_class_id']) {
                    $query->orWhere('school_class_id', $enrollmentFields['school_class_id']);
                }

                if ($enrollmentFields['course_id']) {
                    $query->orWhere('course_id', $enrollmentFields['course_id']);
                }
            });
        $selectedSubjects = $subjectQuery->get()->keyBy('id');

        foreach ($data['subjects'] as $index => $subjectData) {
            if (! $selectedSubjects->has((int) $subjectData['subject_id'])) {
                throw ValidationException::withMessages([
                    "subjects.{$index}.subject_id" => 'Choose an active subject linked to the selected class or course.',
                ]);
            }
        }

        foreach ($data['subjects'] as $index => $subjectData) {
            $maximumMarks = $subjectData['marks'] ?? null;
            $obtainedMarks = $subjectData['marks_obtained'] ?? null;

            if (filled($maximumMarks) !== filled($obtainedMarks)) {
                throw ValidationException::withMessages([
                    "subjects.{$index}.marks_obtained" => 'Enter both total marks and marks obtained.',
                ]);
            }

            if (filled($maximumMarks) && (float) $obtainedMarks > (float) $maximumMarks) {
                throw ValidationException::withMessages([
                    "subjects.{$index}.marks_obtained" => 'Marks obtained cannot be greater than total marks.',
                ]);
            }
        }

        DB::transaction(function () use ($data, $student, $resultContextFields, $selectedSubjects, $request): void {
            foreach ($data['subjects'] as $subjectData) {
                $subject = $selectedSubjects->get((int) $subjectData['subject_id']);
                $resultQuery = StudentResult::query()
                    ->where('student_id', $student->id)
                    ->where('academic_year_id', $data['academic_year_id'])
                    ->where('subject_id', $subject->id);

                foreach ($resultContextFields as $field => $value) {
                    if ($value) {
                        $resultQuery->where($field, $value);
                    } else {
                        $resultQuery->whereNull($field);
                    }
                }

                $result = $resultQuery->lockForUpdate()->first();
                if ($result && ! $request->user()->hasPermission('results.edit')) {
                    throw ValidationException::withMessages([
                        'subjects' => 'You do not have permission to edit an existing result.',
                    ]);
                }
                $result ??= new StudentResult([
                    'student_id' => $student->id,
                    'academic_year_id' => $data['academic_year_id'],
                    ...$resultContextFields,
                    'subject_id' => $subject->id,
                    'subject' => $subject->name,
                ]);
                $result->fill([
                    'subject' => $subject->name,
                    'marks' => $subjectData['marks'] ?? null,
                    'marks_obtained' => $subjectData['marks_obtained'] ?? null,
                    'grade' => $subjectData['grade'] ?? null,
                    'status' => $data['status'],
                ])->save();
            }
        });

        $count = count($data['subjects']);

        return back()->with('success', $count === 1 ? 'Result saved.' : $count.' subject results saved.');
    }

    /**
     * Show campus-scoped timetable periods and the academic groups available for scheduling.
     */
    public function timetable(Request $request)
    {
        [$branchIds, $branchId] = $this->scope($request);
        $editingPeriod = null;

        if ($request->filled('edit')) {
            $editingPeriod = Timetable::query()
                ->with(['branch', 'academicYear', 'schoolClass', 'section', 'teacher.user'])
                ->findOrFail($request->integer('edit'));

            abort_unless(
                $request->user()->canAccessBranch($editingPeriod->branch_id)
                    && ($branchIds === null || $branchIds->contains($editingPeriod->branch_id))
                    && (! $branchId || $branchId === $editingPeriod->branch_id),
                404,
            );
        }

        $itemsQuery = Timetable::query()
            ->with(['branch', 'academicYear', 'schoolClass', 'section', 'teacher.user'])
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($request->filled('academic_year_id'), fn ($query) => $query->where('academic_year_id', $request->integer('academic_year_id')))
            ->when($request->filled('school_class_id'), fn ($query) => $query->where('school_class_id', $request->integer('school_class_id')))
            ->when($request->filled('section_id'), fn ($query) => $query->where('section_id', $request->integer('section_id')))
            ->when($request->filled('weekday'), fn ($query) => $query->where('weekday', $request->string('weekday')->toString()));
        $periodCount = (clone $itemsQuery)->count();
        $scheduledClassCount = (clone $itemsQuery)
            ->whereNotNull('school_class_id')
            ->distinct()
            ->count('school_class_id');
        $scheduledTeacherCount = (clone $itemsQuery)
            ->whereNotNull('teacher_id')
            ->distinct()
            ->count('teacher_id');
        $items = (clone $itemsQuery)
            ->orderByRaw("CASE weekday WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 ELSE 7 END")
            ->orderBy('starts_at')
            ->paginate(25)
            ->withQueryString();

        return view('academic.timetable', [
            'items' => $items,
            'branches' => Branch::query()
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($query) => $query->whereIn('id', $branchIds))
                ->orderBy('name')
                ->get(),
            'years' => AcademicYear::query()
                ->with('branch')
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->orderByDesc('start_date')
                ->get(),
            'classes' => SchoolClass::query()
                ->with(['branch', 'academicYear'])
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
            'sections' => Section::query()
                ->with('branch')
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
            'teachers' => Teacher::query()
                ->with(['user', 'branch'])
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->orderBy('employee_number')
                ->get(),
            'branchId' => $branchId,
            'editingPeriod' => $editingPeriod,
            'periodCount' => $periodCount,
            'scheduledClassCount' => $scheduledClassCount,
            'scheduledTeacherCount' => $scheduledTeacherCount,
        ]);
    }

    /**
     * Validate the academic group and reject overlapping class or teacher periods.
     */
    public function storeTimetable(Request $request)
    {
        return $this->saveTimetablePeriod($request);
    }

    /**
     * Update an existing period after verifying campus access and schedule conflicts.
     */
    public function updateTimetable(Request $request, Timetable $timetable)
    {
        abort_unless($request->user()->canAccessBranch($timetable->branch_id), 404);

        return $this->saveTimetablePeriod($request, $timetable);
    }

    /**
     * Validate and persist a period, excluding its existing record from conflict checks.
     */
    private function saveTimetablePeriod(Request $request, ?Timetable $period = null)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
            'school_class_id' => ['required', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'teacher_id' => ['nullable', 'integer'],
            'weekday' => ['required', 'in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i', 'after:starts_at'],
            'subject' => ['nullable', 'string', 'max:100'],
        ]);
        $user = $request->user();
        $branchId = (int) $data['branch_id'];
        abort_unless($user->canAccessBranch($branchId), 403);
        if (! AcademicYear::query()->whereKey($data['academic_year_id'])->where('branch_id', $branchId)->exists()) {
            throw ValidationException::withMessages(['academic_year_id' => 'Choose an academic year from this campus.']);
        }
        if (! empty($data['school_class_id']) && ! SchoolClass::query()->whereKey($data['school_class_id'])
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages(['school_class_id' => 'Choose a class from the selected year and campus.']);
        }
        if (! empty($data['section_id']) && (! isset($data['school_class_id']) || ! Section::query()->whereKey($data['section_id'])
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->exists())) {
            throw ValidationException::withMessages(['section_id' => 'Choose an active section from this campus and select a class.']);
        }
        if (! empty($data['teacher_id']) && ! Teacher::query()
            ->whereKey($data['teacher_id'])
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->exists()) {
            throw ValidationException::withMessages(['teacher_id' => 'Choose a teacher from this campus.']);
        }
        $conflict = Timetable::query()
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('weekday', $data['weekday'])
            ->where('starts_at', '<', $data['ends_at'])
            ->where('ends_at', '>', $data['starts_at'])
            ->when($period, fn ($query) => $query->where('id', '!=', $period->id))
            ->where(function ($query) use ($data) {
                if (! empty($data['teacher_id'])) {
                    $query->where('teacher_id', $data['teacher_id']);
                }
                $query->orWhere(function ($classQuery) use ($data): void {
                    $classQuery->where('school_class_id', $data['school_class_id'])
                        ->when(! empty($data['section_id']), fn ($sectionQuery) => $sectionQuery->where(fn ($selectedSection) => $selectedSection
                            ->whereNull('section_id')
                            ->orWhere('section_id', $data['section_id'])));
                });
            })->exists();
        if ($conflict) {
            throw ValidationException::withMessages(['starts_at' => 'This time overlaps an existing class or teacher schedule.']);
        }

        if ($period) {
            $period->update($data);
        } else {
            Timetable::create($data);
        }

        return redirect()
            ->route('academic.timetable', [
                'branch_id' => $branchId,
                'academic_year_id' => $data['academic_year_id'],
            ])
            ->with('success', $period ? 'Timetable period updated.' : 'Timetable period saved.');
    }
}
