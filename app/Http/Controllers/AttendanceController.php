<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\NotificationService;
use App\Services\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Records daily attendance and manages correction requests.
 */
class AttendanceController extends Controller
{
    /** Show attendance controls and students enrolled in one selected branch group. */
    public function index(Request $request)
    {
        $request->validate([
            'branch_id' => 'nullable|integer',
            'academic_year_id' => 'nullable|integer',
            'school_class_id' => 'nullable|integer',
            'section_id' => 'nullable|integer',
            'course_id' => 'nullable|integer',
            'semester_id' => 'nullable|integer',
        ]);

        $user = $request->user();
        $accessibleBranches = $user->accessibleBranchIds();
        $branches = Branch::query()->where('is_active', true)
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('id', $accessibleBranches))
            ->orderBy('name')
            ->get();
        $branchId = $user->currentBranchId()
            ?: ($request->filled('branch_id') ? $request->integer('branch_id') : ($branches->count() === 1 ? $branches->first()->id : null));
        if ($branchId) {
            abort_unless($user->canAccessBranch($branchId), 403);
        }
        $institutionType = app(TenantContext::class)->get()?->institution_type ?? 'school';
        $date = today();
        $academicYearId = $request->integer('academic_year_id') ?: null;
        $schoolClassId = $request->integer('school_class_id') ?: null;
        $sectionId = $request->integer('section_id') ?: null;
        $courseId = $request->integer('course_id') ?: null;
        $semesterId = $request->integer('semester_id') ?: null;

        $years = AcademicYear::query()
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when(! $branchId, fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('start_date')
            ->get();
        if (! $academicYearId && $years->isNotEmpty()) {
            $academicYearId = $years->firstWhere('is_current', true)?->id
                ?? ($years->count() === 1 ? $years->first()->id : null);
        }
        $academicYearIsValid = $branchId && $academicYearId
            && $years->contains('id', $academicYearId);

        $classes = SchoolClass::query()
            ->where('branch_id', $branchId ?? 0)
            ->where('level_type', $institutionType)
            ->when($academicYearIsValid, fn ($query) => $query->where('academic_year_id', $academicYearId))
            ->when(! $academicYearIsValid, fn ($query) => $query->whereRaw('1 = 0'))
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();
        if (! $schoolClassId && $classes->count() === 1) {
            $schoolClassId = $classes->first()->id;
        }
        $sections = Section::query()
            ->where('branch_id', $branchId ?? 0)
            ->where('is_active', true)
            ->orderBy('name')->get();
        if (! $sectionId && $sections->count() === 1) {
            $sectionId = $sections->first()->id;
        }
        $courses = Course::query()
            ->where('branch_id', $branchId ?? 0)
            ->where('is_active', true)
            ->orderBy('name')->get();
        if (! $courseId && $courses->count() === 1) {
            $courseId = $courses->first()->id;
        }
        $semesters = Semester::query()
            ->where('course_id', $courseId ?? 0)
            ->where('is_active', true)
            ->orderBy('semester_number')->get();
        if (! $semesterId && $semesters->count() === 1) {
            $semesterId = $semesters->first()->id;
        }

        $groupSelectionIsValid = $institutionType === 'college'
            ? $courses->contains('id', $courseId) && $semesters->contains('id', $semesterId)
            : $classes->contains('id', $schoolClassId) && $sections->contains('id', $sectionId);
        $canLoadStudents = (bool) ($branchId && $academicYearIsValid && $groupSelectionIsValid);

        $enrollments = $canLoadStudents
            ? StudentEnrollment::query()
                ->with(['schoolClass', 'section', 'course', 'semester'])
                ->where('branch_id', $branchId)
                ->where('academic_year_id', $academicYearId)
                ->where('status', 'active')
                ->when($institutionType === 'college', fn ($query) => $query
                    ->where('course_id', $courseId)->where('semester_id', $semesterId))
                ->when($institutionType !== 'college', fn ($query) => $query
                    ->where('school_class_id', $schoolClassId)->where('section_id', $sectionId))
                ->whereHas('student', fn ($query) => $query->where('status', 'active'))
                ->get()
            : collect();
        $students = $enrollments->pluck('student_id')->isNotEmpty()
            ? Student::query()->whereIn('id', $enrollments->pluck('student_id'))
                ->orderBy('first_name')->orderBy('last_name')->get()->keyBy('id')
            : collect();
        $enrollments = $enrollments->keyBy('student_id');
        $selectedGroupName = $institutionType === 'college'
            ? collect([
                $courses->firstWhere('id', $courseId)?->name,
                $semesters->firstWhere('id', $semesterId)?->name,
            ])->filter()->implode(' · ')
            : collect([
                $classes->firstWhere('id', $schoolClassId)?->name,
                $sections->firstWhere('id', $sectionId)?->name,
            ])->filter()->implode(' · ');

        return view('attendance.index', [
            'students' => $students,
            'branches' => $branches,
            'years' => $years,
            'classes' => $classes,
            'sections' => $sections,
            'courses' => $courses,
            'semesters' => $semesters,
            'enrollments' => $enrollments,
            'institutionType' => $institutionType,
            'canLoadStudents' => $canLoadStudents,
            'selectedGroupName' => $selectedGroupName,
            'branchId' => $branchId,
            'academicYearId' => $academicYearId,
            'schoolClassId' => $schoolClassId,
            'sectionId' => $sectionId,
            'courseId' => $courseId,
            'semesterId' => $semesterId,
            'date' => $date,
            'existing' => $canLoadStudents ? Attendance::query()
                ->whereDate('attendance_date', $date)
                ->where('academic_year_id', $academicYearId)
                ->when($branchId, fn ($q) => $q
                    ->where('branch_id', $branchId))
                ->get()
                ->keyBy('student_id') : collect(),
        ]);
    }

    /** Show one summary row for each branch and academic year with recorded attendance. */
    public function records(Request $request)
    {
        $accessibleBranches = $request->user()->accessibleBranchIds();
        $filters = $request->validate([
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
            'branch_id' => 'nullable|integer',
            'academic_year_id' => 'nullable|integer',
            'school_class_id' => 'nullable|integer',
            'section_id' => 'nullable|integer',
            'course_id' => 'nullable|integer',
            'semester_id' => 'nullable|integer',
        ]);

        $summaries = Attendance::query()
            ->select([
                'attendance_date',
                'branch_id',
                'academic_year_id',
                'school_class_id',
                'section_id',
                'course_id',
                'semester_id',
            ])
            ->selectRaw('COUNT(*) as student_count')
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count")
            ->selectRaw("SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count")
            ->selectRaw("SUM(CASE WHEN status IN ('half_day', 'leave') THEN 1 ELSE 0 END) as other_count")
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('branch_id', $accessibleBranches))
            ->when($filters['from_date'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', '>=', $date))
            ->when($filters['to_date'] ?? null, fn ($query, $date) => $query->whereDate('attendance_date', '<=', $date))
            ->when($filters['branch_id'] ?? null, fn ($query, $branchId) => $query->where('branch_id', $branchId))
            ->when($filters['academic_year_id'] ?? null, fn ($query, $yearId) => $query->where('academic_year_id', $yearId))
            ->when($filters['school_class_id'] ?? null, fn ($query, $classId) => $query->where('school_class_id', $classId))
            ->when($filters['section_id'] ?? null, fn ($query, $sectionId) => $query->where('section_id', $sectionId))
            ->when($filters['course_id'] ?? null, fn ($query, $courseId) => $query->where('course_id', $courseId))
            ->when($filters['semester_id'] ?? null, fn ($query, $semesterId) => $query->where('semester_id', $semesterId))
            ->groupBy(
                'attendance_date',
                'branch_id',
                'academic_year_id',
                'school_class_id',
                'section_id',
                'course_id',
                'semester_id',
            )
            ->orderByDesc('attendance_date')
            ->orderBy('branch_id')
            ->paginate(20)
            ->withQueryString();

        $branchIds = $summaries->getCollection()->pluck('branch_id')->unique();
        $academicYearIds = $summaries->getCollection()->pluck('academic_year_id')->unique();
        $classIds = $summaries->getCollection()->pluck('school_class_id')->filter()->unique();
        $sectionIds = $summaries->getCollection()->pluck('section_id')->filter()->unique();
        $courseIds = $summaries->getCollection()->pluck('course_id')->filter()->unique();
        $semesterIds = $summaries->getCollection()->pluck('semester_id')->filter()->unique();
        $branches = Branch::query()->whereIn('id', $branchIds)->get()->keyBy('id');
        $academicYears = AcademicYear::query()->whereIn('id', $academicYearIds)->get()->keyBy('id');
        $classes = SchoolClass::query()->whereIn('id', $classIds)->get()->keyBy('id');
        $sections = Section::query()->whereIn('id', $sectionIds)->get()->keyBy('id');
        $courses = Course::query()->whereIn('id', $courseIds)->get()->keyBy('id');
        $semesters = Semester::query()->whereIn('id', $semesterIds)->get()->keyBy('id');

        $summaries->getCollection()->transform(function ($summary) use ($branches, $academicYears, $classes, $sections, $courses, $semesters) {
            $summary->branch = $branches->get($summary->branch_id);
            $summary->academicYear = $academicYears->get($summary->academic_year_id);
            $summary->schoolClass = $classes->get($summary->school_class_id);
            $summary->section = $sections->get($summary->section_id);
            $summary->course = $courses->get($summary->course_id);
            $summary->semester = $semesters->get($summary->semester_id);

            return $summary;
        });

        $branchOptions = Branch::query()
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('id', $accessibleBranches))
            ->orderBy('name')
            ->get();
        $academicYearOptions = AcademicYear::query()
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('branch_id', $accessibleBranches))
            ->orderByDesc('start_date')
            ->get();
        $classOptions = SchoolClass::query()
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('branch_id', $accessibleBranches))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $sectionOptions = Section::query()
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('branch_id', $accessibleBranches))
            ->orderBy('name')
            ->get();
        $courseOptions = Course::query()
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('branch_id', $accessibleBranches))
            ->orderBy('name')
            ->get();
        $semesterOptions = Semester::query()
            ->whereHas('course', fn ($query) => $query
                ->when($accessibleBranches !== null, fn ($courseQuery) => $courseQuery->whereIn('branch_id', $accessibleBranches)))
            ->orderBy('semester_number')
            ->get();
        $institutionType = app(TenantContext::class)->get()?->institution_type ?? 'school';

        return view('attendance.records.index', compact(
            'academicYearOptions',
            'branchOptions',
            'classOptions',
            'courseOptions',
            'filters',
            'institutionType',
            'sectionOptions',
            'semesterOptions',
            'summaries',
        ));
    }

    /** Show the student level attendance records for one summarized branch and date. */
    public function showRecords(Request $request, string $date, Branch $branch, AcademicYear $academicYear)
    {
        abort_unless($request->user()->canAccessBranch($branch->id), 403);
        abort_unless((int) $academicYear->branch_id === (int) $branch->id, 404);
        $group = $request->validate([
            'school_class_id' => 'nullable|integer|min:0',
            'section_id' => 'nullable|integer|min:0',
            'course_id' => 'nullable|integer|min:0',
            'semester_id' => 'nullable|integer|min:0',
        ]);

        $date = rawurldecode($date);
        $dateFormat = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $date)
            ? '!Y-m-d H:i:s'
            : '!Y-m-d';

        try {
            $attendanceDate = CarbonImmutable::createFromFormat($dateFormat, $date);
        } catch (\Throwable) {
            abort(404);
        }

        $expectedDateValue = $attendanceDate instanceof CarbonImmutable
            ? ($dateFormat === '!Y-m-d H:i:s'
                ? $attendanceDate->format('Y-m-d H:i:s')
                : $attendanceDate->format('Y-m-d'))
            : null;

        abort_unless($attendanceDate && $expectedDateValue === $date, 404);

        $records = Attendance::query()
            ->with('student')
            ->where('branch_id', $branch->id)
            ->where('academic_year_id', $academicYear->id)
            ->whereDate('attendance_date', $attendanceDate->toDateString())
            ->when(isset($group['school_class_id']), fn ($query) => $group['school_class_id'] > 0
                ? $query->where('school_class_id', $group['school_class_id'])
                : $query->whereNull('school_class_id'))
            ->when(isset($group['section_id']), fn ($query) => $group['section_id'] > 0
                ? $query->where('section_id', $group['section_id'])
                : $query->whereNull('section_id'))
            ->when(isset($group['course_id']), fn ($query) => $group['course_id'] > 0
                ? $query->where('course_id', $group['course_id'])
                : $query->whereNull('course_id'))
            ->when(isset($group['semester_id']), fn ($query) => $group['semester_id'] > 0
                ? $query->where('semester_id', $group['semester_id'])
                : $query->whereNull('semester_id'))
            ->with(['student', 'schoolClass', 'section', 'course', 'semester'])
            ->orderBy('student_id')
            ->get();

        abort_if($records->isEmpty(), 404);

        return view('attendance.records.show', compact('academicYear', 'attendanceDate', 'branch', 'records'));
    }

    /** Save attendance only when every submitted student belongs to the selected group. */
    public function store(StoreAttendanceRequest $request, NotificationService $notifications)
    {
        $data = $request->validated();
        $user = $request->user();
        $tenant = app(TenantContext::class)->get();
        $branchId = (int) $data['branch_id'];
        abort_unless($user->canAccessBranch($branchId), 403);
        abort_unless(! $user->currentBranchId() || (int) $user->currentBranchId() === $branchId, 403);
        abort_unless(Branch::query()->whereKey($branchId)->where('is_active', true)->exists(), 422);

        $academicYear = AcademicYear::query()
            ->whereKey($data['academic_year_id'])
            ->where('branch_id', $branchId)
            ->firstOrFail();
        $institutionType = $tenant?->institution_type ?? 'school';
        $enrollmentQuery = StudentEnrollment::query()
            ->where('branch_id', $branchId)
            ->where('academic_year_id', $academicYear->id)
            ->where('status', 'active')
            ->whereIn('student_id', collect($data['records'])->pluck('student_id'));

        if ($institutionType === 'college') {
            abort_unless(Course::query()->whereKey($data['course_id'])->where('branch_id', $branchId)->where('is_active', true)->exists(), 422);
            abort_unless(Semester::query()->whereKey($data['semester_id'])->where('course_id', $data['course_id'])->where('is_active', true)->exists(), 422);
            $enrollmentQuery->where('course_id', $data['course_id'])->where('semester_id', $data['semester_id']);
        } else {
            abort_unless(SchoolClass::query()->whereKey($data['school_class_id'])->where('branch_id', $branchId)->where('academic_year_id', $academicYear->id)->where('is_active', true)->exists(), 422);
            abort_unless(Section::query()->whereKey($data['section_id'])->where('branch_id', $branchId)->where('is_active', true)->exists(), 422);
            $enrollmentQuery->where('school_class_id', $data['school_class_id'])->where('section_id', $data['section_id']);
        }

        $enrolledStudentIds = $enrollmentQuery->pluck('student_id');
        if ($enrolledStudentIds->count() !== count($data['records'])) {
            throw ValidationException::withMessages(['records' => 'Attendance can only be marked for active students enrolled in the selected group.']);
        }

        $students = Student::query()->with('guardians.userAccounts')->whereIn('id', collect($data['records'])->pluck('student_id'))->get()->keyBy('id');
        if ($students->count() !== count($data['records'])) {
            throw ValidationException::withMessages(['records' => 'One or more selected students could not be found.']);
        }
        $branchIds = $students->pluck('branch_id')->unique();
        if ($branchIds->count() !== 1) {
            throw ValidationException::withMessages(['records' => 'Attendance can only be submitted for one branch at a time.']);
        }
        abort_unless((int) $branchIds->first() === $branchId, 403);

        DB::transaction(function () use ($data, $branchId, $user) {
            foreach ($data['records'] as $row) {
                Attendance::updateOrCreate(
                    ['student_id' => $row['student_id'], 'attendance_date' => $data['attendance_date'], 'session' => 'full_day'],
                    [
                        'branch_id' => $branchId,
                        'academic_year_id' => $data['academic_year_id'],
                        'school_class_id' => $data['school_class_id'] ?? null,
                        'section_id' => $data['section_id'] ?? null,
                        'course_id' => $data['course_id'] ?? null,
                        'semester_id' => $data['semester_id'] ?? null,
                        'status' => $row['status'],
                        'remarks' => $row['remarks'] ?? null,
                        'marked_by' => $user->id,
                    ],
                );
            }
        });

        foreach ($data['records'] as $row) {
            if ($row['status'] !== 'absent') {
                continue;
            }
            $student = $students->get($row['student_id']);
            $recipients = $student->guardians->flatMap->userAccounts->unique('id');
            foreach ($recipients as $recipient) {
                $notifications->send($recipient
                    ->id, 'attendance.absent', 'auto', ['student' => trim($student
                    ->first_name.' '.$student
                    ->last_name), 'date' => $data['attendance_date']]);
            }
        }

        return redirect()->route('attendance.index', $request->only([
            'branch_id', 'academic_year_id', 'school_class_id', 'section_id', 'course_id', 'semester_id',
        ]))->with('success', 'Attendance submitted.');
    }

    public function requestCorrection(Request $request, Attendance $attendance)
    {
        abort_unless($request->user()->canAccessBranch($attendance->branch_id), 403);
        $data = $request->validate(['new_status' => 'required|in:present,absent,late,half_day,leave', 'reason' => 'required|string|max:1000']);
        if ($data['new_status'] === $attendance->status) {
            throw ValidationException::withMessages(['new_status' => 'Choose a status different from the recorded attendance.']);
        }
        if (DB::table('attendance_corrections')->where('attendance_id', $attendance->id)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['attendance' => 'A correction request is already pending for this attendance record.']);
        }
        DB::table('attendance_corrections')->insert([
            'attendance_id' => $attendance->id, 'requested_by' => $request->user()->id,
            'tenant_id' => app(TenantContext::class)->id(), 'old_status' => $attendance->status,
            'new_status' => $data['new_status'], 'reason' => $data['reason'], 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('success', 'Attendance correction sent for approval.');
    }
}
