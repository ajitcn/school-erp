<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Course;
use App\Models\FeeComponent;
use App\Models\FeeStructure;
use App\Models\SchoolClass;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages fee structures, installments, and student fee assignments.
 */
class FeeStructureController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $branchId = $user->currentBranchId() ?: ($request->filled('branch_id') ? $request->integer('branch_id') : null);
        if ($branchId) {
            abort_unless($user->canAccessBranch($branchId), 403);
        }
        $accessibleBranches = $user->accessibleBranchIds();

        return view('fees.structures.index', [
            'structures' => FeeStructure::query()->with('academicYear', 'branch')
                ->when($accessibleBranches !== null, fn ($q) => $q
                    ->whereIn('branch_id', $accessibleBranches))
                ->when($branchId, fn ($q, $id) => $q
                    ->where('branch_id', $id))
                ->latest()
                ->paginate(15)
                ->withQueryString(),
            'years' => AcademicYear::query()
                ->when($branchId, fn ($q) => $q
                    ->where('branch_id', $branchId))
                ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q
                    ->whereIn('branch_id', $accessibleBranches))
                ->get(),
            'branches' => Branch::query()->where('is_active', true)->when($accessibleBranches !== null, fn ($q) => $q->whereIn('id', $accessibleBranches))->orderBy('name')->get(),
            'classes' => SchoolClass::query()->where('is_active', true)
                ->when($branchId, fn ($q) => $q
                    ->where('branch_id', $branchId))
                ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q
                    ->whereIn('branch_id', $accessibleBranches))
                ->orderBy('name')
                ->get(),
            'courses' => Course::query()->where('is_active', true)
                ->when($branchId, fn ($q) => $q
                    ->where('branch_id', $branchId))
                ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q
                    ->whereIn('branch_id', $accessibleBranches))
                ->orderBy('name')
                ->get(),
            'semesters' => Semester::query()->with('course')
                ->where('is_active', true)
                ->when($branchId, fn ($q) => $q
                    ->whereHas('course', fn ($c) => $c
                        ->where('branch_id', $branchId)))
                ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q
                    ->whereHas('course', fn ($c) => $c
                        ->whereIn('branch_id', $accessibleBranches)))
                ->get(),
            'branchId' => $branchId,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => $request->user()->currentBranchId() ? 'nullable' : 'required|integer',
            'academic_year_id' => 'nullable|integer', 'name' => 'required|string|max:150', 'default_amount' => 'required|numeric|gt:0',
            'school_class_id' => 'nullable|integer', 'course_id' => 'nullable|integer', 'semester_id' => 'nullable|integer',
        ]);
        $branchId = (int) ($request->user()->currentBranchId() ?: $data['branch_id']);
        abort_unless($request->user()->canAccessBranch($branchId) && Branch::query()->whereKey($branchId)->exists(), 403);
        if (! empty($data['academic_year_id']) && ! AcademicYear::query()->whereKey($data['academic_year_id'])->where('branch_id', $branchId)->exists()) {
            throw ValidationException::withMessages(['academic_year_id' => 'Choose an academic year from the selected branch.']);
        }
        $this->validateTarget($data, $branchId);

        FeeStructure::create(['branch_id' => $branchId, 'academic_year_id' => $data['academic_year_id'] ?? null, 'school_class_id' => $data['school_class_id'] ?? null, 'course_id' => $data['course_id'] ?? null, 'semester_id' => $data['semester_id'] ?? null, 'name' => $data['name'], 'default_amount' => $data['default_amount']]);

        return back()->with('success', 'Fee plan created. You can now set its amount and payment schedule for each student.');
    }

    public function edit(FeeStructure $feeStructure)
    {
        abort_unless(auth()->user()->canAccessBranch($feeStructure->branch_id), 403);

        return view('fees.structures.edit', ['structure' => $feeStructure, 'years' => AcademicYear::query()
            ->where('branch_id', $feeStructure
                ->branch_id)
            ->orderByDesc('start_date')
            ->get(), 'classes' => SchoolClass::query()
            ->where('branch_id', $feeStructure
                ->branch_id)
            ->when($feeStructure->academic_year_id, fn ($query) => $query->where('academic_year_id', $feeStructure->academic_year_id))
            ->orderBy('name')
            ->get(), 'courses' => Course::query()
            ->where('branch_id', $feeStructure
                ->branch_id)
            ->orderBy('name')
            ->get(), 'semesters' => Semester::query()
            ->whereHas('course', fn ($q) => $q
                ->where('branch_id', $feeStructure
                    ->branch_id))
            ->with('course')
            ->get()]);
    }

    public function update(Request $request, FeeStructure $feeStructure)
    {
        abort_unless($request->user()->canAccessBranch($feeStructure->branch_id), 403);
        $data = $request->validate(['name' => 'required|string|max:150', 'default_amount' => 'required|numeric|gt:0', 'academic_year_id' => 'nullable|integer', 'school_class_id' => 'nullable|integer', 'course_id' => 'nullable|integer', 'semester_id' => 'nullable|integer']);
        if (! empty($data['academic_year_id']) && ! AcademicYear::query()->whereKey($data['academic_year_id'])->where('branch_id', $feeStructure->branch_id)->exists()) {
            throw ValidationException::withMessages(['academic_year_id' => 'Choose an academic year from the structure’s branch.']);
        }
        $this->validateTarget($data, (int) $feeStructure->branch_id);
        $feeStructure->update(['name' => $data['name'], 'default_amount' => $data['default_amount'], 'academic_year_id' => $data['academic_year_id'] ?? null, 'school_class_id' => $data['school_class_id'] ?? null, 'course_id' => $data['course_id'] ?? null, 'semester_id' => $data['semester_id'] ?? null]);

        return back()->with('success', 'Fee structure updated.');
    }

    public function deactivate(FeeStructure $feeStructure)
    {
        abort_unless(auth()->user()->canAccessBranch($feeStructure->branch_id), 403);
        $feeStructure->update(['is_active' => false]);

        return back()->with('success', 'Fee structure deactivated.');
    }

    public function addInstallment(Request $request, FeeStructure $feeStructure)
    {
        abort_unless($request->user()->canAccessBranch($feeStructure->branch_id), 403);
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'amount' => 'required|numeric|gt:0',
            'due_date' => 'required|date',
            'installment_number' => 'required|integer|min:1',
        ]);
        if ($feeStructure->components()->where('installment_number', $data['installment_number'])->exists()) {
            throw ValidationException::withMessages(['installment_number' => 'That installment number already exists in this fee structure.']);
        }
        $feeStructure->components()->create($data);

        return back()->with('success', 'Installment added to the fee structure.');
    }

    public function updateInstallment(Request $request, FeeStructure $feeStructure, FeeComponent $component)
    {
        abort_unless($request->user()->canAccessBranch($feeStructure->branch_id), 403);
        abort_unless((int) $component->fee_structure_id === (int) $feeStructure->id, 404);
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'amount' => 'required|numeric|gt:0',
            'due_date' => 'required|date',
            'installment_number' => 'required|integer|min:1',
        ]);
        if ($feeStructure->components()->where('installment_number', $data['installment_number'])->where('id', '!=', $component->id)->exists()) {
            throw ValidationException::withMessages(['installment_number' => 'That installment number already exists in this fee structure.']);
        }
        DB::transaction(function () use ($component, $data) {
            $assignments = $component->studentFeeAssignments()->with('allocations')->lockForUpdate()->get();
            foreach ($assignments as $assignment) {
                $newTotal = max(0, (float) $data['amount'] - (float) $assignment->discount + (float) $assignment->late_fee);
                $paid = $assignment->allocations->sum(fn ($allocation) => (float) $allocation->amount);
                if ($newTotal + 0.001 < $paid) {
                    throw ValidationException::withMessages(['amount' => 'The installment amount cannot be lower than payments already received.']);
                }
            }
            $component->update($data);
            $assignments->each(function (StudentFeeAssignment $assignment) use ($data) {
                $paid = $assignment->allocations->sum(fn ($allocation) => (float) $allocation->amount);
                $newTotal = max(0, (float) $data['amount'] - (float) $assignment->discount + (float) $assignment->late_fee);
                $assignment->update(['amount' => $data['amount'], 'status' => $paid >= $newTotal ? 'paid' : ($paid > 0 ? 'partial' : 'open')]);
            });
        });

        return back()->with('success', 'Installment updated.');
    }

    public function assignments(Request $request)
    {
        $user = $request->user();
        $branchId = $user->currentBranchId() ?: ($request->filled('branch_id') ? $request->integer('branch_id') : null);
        if ($branchId) {
            abort_unless($user->canAccessBranch($branchId), 403);
        }
        $branches = Branch::query()->where('is_active', true)
            ->when($user
                ->accessibleBranchIds() !== null, fn ($q) => $q
                ->whereIn('id', $user
                    ->accessibleBranchIds()))
            ->orderBy('name')
            ->get();
        $students = Student::query()->with(['enrollments' => fn ($q) => $q->where('status', 'active')->with('academicYear')])
            ->where('status', 'active')->when($user->accessibleBranchIds() !== null, fn ($q) => $q->whereIn('branch_id', $user->accessibleBranchIds()))
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))->orderBy('first_name')->orderBy('last_name')->get();
        $structures = FeeStructure::query()->with('academicYear', 'components')->where('is_active', true)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(! $branchId && $user->accessibleBranchIds() !== null, fn ($q) => $q->whereIn('branch_id', $user->accessibleBranchIds()))
            ->latest()->get();
        $assignments = StudentFeeAssignment::query()->with(['student', 'component.structure.academicYear', 'allocations'])
            ->whereHas('student', fn ($q) => $q
                ->when($branchId, fn ($students) => $students
                    ->where('branch_id', $branchId))
                ->when(! $branchId && $user
                    ->accessibleBranchIds() !== null, fn ($students) => $students
                    ->whereIn('branch_id', $user
                        ->accessibleBranchIds())))
            ->latest()->paginate(20)->withQueryString();

        return view('fees.assignments', compact('branches', 'students', 'structures', 'assignments', 'branchId'));
    }

    public function assign(Request $request)
    {
        $data = $request->validate(['student_id' => 'required|integer', 'fee_structure_id' => 'required|integer']);
        $student = Student::query()->with(['enrollments' => fn ($q) => $q->where('status', 'active')->latest()])->findOrFail($data['student_id']);
        abort_unless($request->user()->canAccessBranch($student->branch_id), 403);
        $structure = FeeStructure::query()->with('components')->findOrFail($data['fee_structure_id']);
        abort_unless((int) $structure->branch_id === (int) $student->branch_id, 422);
        $enrollment = $student->enrollments->first();
        if (! $enrollment || (int) $enrollment->academic_year_id !== (int) $structure->academic_year_id) {
            throw ValidationException::withMessages(['fee_structure_id' => 'Choose a fee structure for the student’s active academic year.']);
        }
        if ($structure->school_class_id && (int) $structure->school_class_id !== (int) $enrollment->school_class_id) {
            throw ValidationException::withMessages(['fee_structure_id' => 'This fee structure is for a different class.']);
        }
        if ($structure->course_id && (int) $structure->course_id !== (int) $enrollment->course_id) {
            throw ValidationException::withMessages(['fee_structure_id' => 'This fee structure is for a different course.']);
        }
        if ($structure->semester_id && (int) $structure->semester_id !== (int) $enrollment->semester_id) {
            throw ValidationException::withMessages(['fee_structure_id' => 'This fee structure is for a different semester.']);
        }
        if ($structure->components->isEmpty()) {
            throw ValidationException::withMessages(['fee_structure_id' => 'Add at least one installment before assigning this fee structure.']);
        }

        $created = DB::transaction(function () use ($student, $structure) {
            $count = 0;
            foreach ($structure->components as $component) {
                $assignment = StudentFeeAssignment::query()->firstOrCreate(
                    ['student_id' => $student->id, 'fee_component_id' => $component->id],
                    ['amount' => $component->amount, 'discount' => 0, 'late_fee' => 0, 'status' => 'open'],
                );
                if ($assignment->wasRecentlyCreated) {
                    $count++;
                }
            }

            return $count;
        });

        return back()->with('success', $created ? "Assigned {$created} installment(s) to the student." : 'All installments in this fee structure are already assigned to this student.');
    }

    public function updateAssignment(Request $request, StudentFeeAssignment $assignment)
    {
        $assignment->load('student');
        abort_unless($request->user()->canAccessBranch($assignment->student->branch_id), 403);
        $data = $request->validate(['discount' => 'required|numeric|min:0', 'late_fee' => 'required|numeric|min:0']);
        if ((float) $data['discount'] > (float) $assignment->amount) {
            throw ValidationException::withMessages(['discount' => 'Discount cannot be greater than the assigned fee amount.']);
        }
        $paid = (float) $assignment->allocations()->sum('amount');
        $due = max(0, (float) $assignment->amount - (float) $data['discount'] + (float) $data['late_fee']);
        if ($paid > $due + 0.001) {
            throw ValidationException::withMessages(['discount' => 'The new fee total cannot be lower than payments already received.']);
        }
        $assignment->update($data + ['status' => $paid >= $due ? 'paid' : ($paid > 0 ? 'partial' : 'open')]);

        return back()->with('success', 'Student discount and late fee updated.');
    }

    private function validateTarget(array $data, int $branchId): void
    {
        if (! empty($data['school_class_id']) && ! SchoolClass::query()->whereKey($data['school_class_id'])
            ->where('branch_id', $branchId)
            ->when(! empty($data['academic_year_id']), fn ($query) => $query->where('academic_year_id', $data['academic_year_id']))
            ->exists()) {
            throw ValidationException::withMessages(['school_class_id' => 'Choose a class from the selected branch and year.']);
        }
        if (! empty($data['course_id']) && ! Course::query()->whereKey($data['course_id'])->where('branch_id', $branchId)->exists()) {
            throw ValidationException::withMessages(['course_id' => 'Choose a course from the selected branch.']);
        }
        if (! empty($data['semester_id']) && (! isset($data['course_id']) || ! Semester::query()->whereKey($data['semester_id'])
            ->where('course_id', $data['course_id'])
            ->exists())) {
            throw ValidationException::withMessages(['semester_id' => 'Choose a semester from the selected course.']);
        }
    }
}
