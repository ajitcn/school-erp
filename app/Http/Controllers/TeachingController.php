<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Teacher;
use App\Models\TeacherAssignment;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manages teaching staff and academic assignments.
 */
class TeachingController extends Controller
{
    /**
     * Show campus-scoped teacher profiles, assignment forms, and assignment history.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $branchId = $user->currentBranchId() ?: ($request->filled('branch_id') ? $request->integer('branch_id') : null);
        if ($branchId) {
            abort_unless($user->canAccessBranch($branchId), 403);
        }
        $branchIds = $user->accessibleBranchIds();
        $teachers = Teacher::query()->with(['user', 'branch'])
            ->withCount('assignments')
            ->when($branchIds !== null, fn ($q) => $q
                ->whereIn('branch_id', $branchIds))
            ->when($branchId, fn ($q) => $q
                ->where('branch_id', $branchId))
            ->orderBy('employee_number')
            ->get();
        $assignmentQuery = TeacherAssignment::query()
            ->whereHas('teacher', fn ($q) => $q
                ->when($branchIds !== null, fn ($t) => $t
                    ->whereIn('branch_id', $branchIds))
                ->when($branchId, fn ($t) => $t
                    ->where('branch_id', $branchId)));
        $assignmentCount = (clone $assignmentQuery)->count();
        $assignments = (clone $assignmentQuery)
            ->with(['teacher.user', 'teacher.branch', 'academicYear', 'schoolClass', 'section', 'course'])
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $tenantId = app(TenantContext::class)->id();
        $teacherUserIds = DB::table('tenant_user')
            ->where('tenant_id', $tenantId)
            ->where('role', 'teacher')
            ->where('is_active', true)
            ->pluck('user_id');
        $availableUsers = User::query()->whereIn('id', $teacherUserIds)
            ->where('is_active', true)
            ->when($teachers->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $teachers->pluck('user_id')->filter()))
            ->orderBy('name')
            ->get();

        $teacherCount = $teachers->count();
        $activeTeacherCount = $teachers->where('is_active', true)->count();

        return view('academic.teachers', [
            'branches' => Branch::query()
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($query) => $query->whereIn('id', $branchIds))
                ->orderBy('name')
                ->get(),
            'years' => AcademicYear::query()
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->orderByDesc('start_date')
                ->get(),
            'classes' => SchoolClass::query()
                ->with('academicYear')
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
            'sections' => Section::query()
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
            'courses' => Course::query()
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
            'teachers' => $teachers,
            'assignments' => $assignments,
            'availableUsers' => $availableUsers,
            'branchId' => $branchId,
            'teacherCount' => $teacherCount,
            'activeTeacherCount' => $activeTeacherCount,
            'assignmentCount' => $assignmentCount,
        ]);
    }

    /**
     * Create a teacher profile only for an active teacher member of this tenant.
     */
    public function storeTeacher(Request $request)
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer'],
            'user_id' => ['required', 'integer'],
            'employee_number' => ['nullable', 'string', 'max:50', Rule::unique('teachers', 'employee_number')],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);
        $branch = Branch::query()->findOrFail($data['branch_id']);
        abort_unless($request->user()->canAccessBranch($branch->id), 403);
        $tenant = app(TenantContext::class)->get();
        abort_unless(
            $tenant
                && $tenant->users()
                    ->whereKey($data['user_id'])
                    ->wherePivot('role', 'teacher')
                    ->wherePivot('is_active', true)
                    ->exists(),
            422,
        );
        if (Teacher::query()->where('user_id', $data['user_id'])->where('branch_id', $branch->id)->exists()) {
            throw ValidationException::withMessages(['user_id' => 'This teacher already has a profile for the selected campus.']);
        }
        $data['employee_number'] = $data['employee_number'] ?: 'T-'.$branch->code.'-'.str()->upper(str()->random(6));
        Teacher::create($data + ['is_active' => true]);

        return redirect()
            ->route('academic.teachers', ['tab' => 'profiles'])
            ->with('success', 'Teacher profile created.');
    }

    /**
     * Save a campus-scoped class or course assignment for an existing teacher.
     */
    public function storeAssignment(Request $request)
    {
        $data = $request->validate([
            'teacher_id' => ['required', 'integer'],
            'academic_year_id' => ['required', 'integer'],
            'school_class_id' => ['nullable', 'integer'],
            'section_id' => ['nullable', 'integer'],
            'course_id' => ['nullable', 'integer'],
            'is_class_teacher' => ['nullable', 'boolean'],
        ]);

        $teacher = Teacher::query()->findOrFail($data['teacher_id']);
        abort_unless($request->user()->canAccessBranch($teacher->branch_id), 403);

        if (! $teacher->is_active) {
            throw ValidationException::withMessages([
                'teacher_id' => 'Choose an active teacher profile.',
            ]);
        }

        $academicYearExists = AcademicYear::query()
            ->whereKey($data['academic_year_id'])
            ->where('branch_id', $teacher->branch_id)
            ->exists();

        if (! $academicYearExists) {
            throw ValidationException::withMessages(['academic_year_id' => 'Choose an academic year from the teacher’s campus.']);
        }

        $classId = $data['school_class_id'] ?? null;
        $sectionId = $data['section_id'] ?? null;
        $courseId = $data['course_id'] ?? null;

        if ($classId) {
            $classExists = SchoolClass::query()
                ->whereKey($classId)
                ->where('branch_id', $teacher->branch_id)
                ->where('academic_year_id', $data['academic_year_id'])
                ->where('is_active', true)
                ->exists();

            if (! $classExists) {
                throw ValidationException::withMessages([
                    'school_class_id' => 'Choose an active class from the selected campus and year.',
                ]);
            }
        }

        if ($sectionId) {
            $sectionExists = Section::query()
                ->whereKey($sectionId)
                ->where('branch_id', $teacher->branch_id)
                ->where('is_active', true)
                ->exists();

            if (! $classId || ! $sectionExists) {
                throw ValidationException::withMessages([
                    'section_id' => 'Choose an active section from the teacher’s campus and select a class.',
                ]);
            }
        }

        if ($courseId) {
            $courseExists = Course::query()
                ->whereKey($courseId)
                ->where('branch_id', $teacher->branch_id)
                ->where('is_active', true)
                ->exists();

            if (! $courseExists) {
                throw ValidationException::withMessages([
                    'course_id' => 'Choose an active course from the teacher’s campus.',
                ]);
            }
        }

        if (! $classId && ! $courseId) {
            throw ValidationException::withMessages([
                'teaching_group' => 'Choose at least one teaching group: a class or a course.',
            ]);
        }

        $isClassTeacher = $request->boolean('is_class_teacher');

        if ($isClassTeacher && (! $classId || ! $sectionId)) {
            throw ValidationException::withMessages([
                'is_class_teacher' => 'A class teacher assignment requires both a class and section.',
            ]);
        }

        $duplicate = TeacherAssignment::query()
            ->where('teacher_id', $teacher->id)
            ->where('academic_year_id', $data['academic_year_id'])
            ->where('school_class_id', $classId)
            ->where('section_id', $sectionId)
            ->where('course_id', $courseId)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'teacher_id' => 'This teaching assignment already exists.',
            ]);
        }

        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'academic_year_id' => $data['academic_year_id'],
            'school_class_id' => $classId,
            'section_id' => $sectionId,
            'course_id' => $courseId,
            'is_class_teacher' => $isClassTeacher,
        ]);

        return redirect()
            ->route('academic.teachers', ['tab' => 'assignments'])
            ->with('success', 'Teaching assignment saved.');
    }
}
