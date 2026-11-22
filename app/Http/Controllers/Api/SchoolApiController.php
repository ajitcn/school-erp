<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notice;
use App\Models\Student;
use App\Models\StudentResult;
use App\Models\Timetable;
use App\Services\TenantContext;
use Illuminate\Http\Request;

/**
 * Serves authenticated, tenant-scoped read-only school API resources.
 */
class SchoolApiController extends Controller
{
    public function me(Request $request)
    {
        return response()->json(['user' => $request
            ->user()
            ->only(['id', 'name', 'email']), 'tenant' => app(TenantContext::class)
            ->get()
            ?->only(['id', 'institution_name', 'institution_type'])]);
    }

    public function students(Request $request)
    {
        abort_unless($request->user()->hasPermission('students.view'), 403);
        $branches = $request->user()->accessibleBranchIds();
        $items = Student::query()->with('branch')
            ->when($branches !== null, fn ($q) => $q
                ->whereIn('branch_id', $branches))
            ->orderBy('id')
            ->paginate(min(100, max(1, $request
                ->integer('per_page', 25))));
        $items->through(fn ($student) => ['id' => $student
            ->id, 'student_id' => $student
            ->student_id, 'admission_number' => $student
            ->admission_number, 'first_name' => $student
            ->first_name, 'last_name' => $student
            ->last_name, 'status' => $student
            ->status, 'branch' => $student
            ->branch
            ?->name]);

        return response()->json($items);
    }

    public function student(Request $request, Student $student)
    {
        abort_unless($request->user()->hasPermission('students.view') && $request->user()->canAccessBranch($student->branch_id), 404);
        $student->load('branch', 'enrollments.academicYear', 'enrollments.schoolClass', 'enrollments.section');

        return response()->json(['id' => $student
            ->id, 'student_id' => $student
            ->student_id, 'admission_number' => $student
            ->admission_number, 'first_name' => $student
            ->first_name, 'last_name' => $student
            ->last_name, 'date_of_birth' => $student
            ->date_of_birth, 'gender' => $student
            ->gender, 'status' => $student
            ->status, 'branch' => $student
            ->branch
            ?->name, 'enrollments' => $student
            ->enrollments]);
    }

    public function attendance(Request $request, Student $student)
    {
        abort_unless($request->user()->hasPermission('attendance.mark') && $request->user()->canAccessBranch($student->branch_id), 404);

        return response()->json($student->attendance()->latest('attendance_date')->paginate(100));
    }

    public function fees(Request $request, Student $student)
    {
        abort_unless(($request->user()
            ->hasPermission('payments.collect') || $request
            ->user()
            ->hasPermission('reports.view')) && $request
            ->user()
            ->canAccessBranch($student
                ->branch_id), 404);
        $items = $student->feeAssignments()
            ->with('component.structure.academicYear', 'component.academicYear', 'allocations')
            ->get()
            ->map(fn ($a) => ['id' => $a
                ->id, 'academic_year' => $a
                ->component
                ?->academicYear
                ?->name ?? $a->component?->structure?->academicYear?->name, 'installment' => $a
                ->component
                ?->name, 'due_date' => $a
                ->component
                ?->due_date, 'amount' => $a
                ->totalDue(), 'paid' => $a
                ->totalDue() - $a
                ->balanceDue(), 'balance' => $a
                ->balanceDue()]);

        return response()->json(['data' => $items]);
    }

    public function results(Request $request, Student $student)
    {
        abort_unless($request->user()->hasPermission('students.edit') && $request->user()->canAccessBranch($student->branch_id), 404);

        $results = StudentResult::query()
            ->with('academicYear', 'semester', 'examination')
            ->where('student_id', $student
                ->id)
            ->where('status', 'published')
            ->latest()
            ->get();
        $examinationResults = $results
            ->whereNotNull('examination_id')
            ->map(fn (StudentResult $result): array => [
                'id' => $result->id,
                'examination_id' => $result->examination_id,
                'exam_name' => $result->examination?->name,
                'student_id' => $result->student_id,
                'subject' => $result->subject,
                'marks' => $result->marks_obtained,
                'maximum_marks' => $result->marks,
                'grade' => $result->grade,
                'status' => $result->status,
            ])
            ->values();

        return response()->json([
            'academic_results' => $results,
            'examination_results' => $examinationResults,
            // Keep the older response key while clients transition to academic_results.
            'examination_marks' => $examinationResults,
        ]);
    }

    public function timetable(Request $request, Student $student)
    {
        abort_unless($request->user()->hasPermission('students.edit') && $request->user()->canAccessBranch($student->branch_id), 404);
        $enrollment = $student->enrollments()->where('status', 'active')->latest()->first();
        if (! $enrollment) {
            return response()->json(['data' => []]);
        }

        return response()->json(['data' => Timetable::query()
            ->with('teacher.user')
            ->where('branch_id', $student
                ->branch_id)
            ->where('academic_year_id', $enrollment
                ->academic_year_id)
            ->where(fn ($q) => $q
                ->whereNull('school_class_id')
                ->orWhere('school_class_id', $enrollment
                    ->school_class_id))
            ->where(fn ($q) => $q
                ->whereNull('section_id')
                ->orWhere('section_id', $enrollment
                    ->section_id))
            ->orderBy('weekday')
            ->orderBy('starts_at')
            ->get()]);
    }

    public function notices(Request $request)
    {
        $branches = $request->user()->accessibleBranchIds();

        return response()->json(['data' => Notice::query()
            ->visibleTo($request->user())
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn ($q) => $q
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->when($branches !== null, fn ($q) => $q
                ->where(fn ($scope) => $scope
                    ->whereNull('branch_id')
                    ->orWhereIn('branch_id', $branches)))
            ->latest('published_at')
            ->paginate(50)]);
    }
}
