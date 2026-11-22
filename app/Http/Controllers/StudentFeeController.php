<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\FeeComponent;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/** Creates student-specific charges and lists their installment balances. */
class StudentFeeController extends Controller
{
    /** Return the fee setup form used inside the student directory modal. */
    public function setupModal(Request $request, Student $student)
    {
        abort_unless($request->user()->canAccessBranch($student->branch_id), 403);
        $enrollment = $student->enrollments()->where('status', 'active')->with('academicYear')->latest()->first();
        $plans = $enrollment ? FeeStructure::query()->with(['academicYear', 'schoolClass', 'course', 'semester'])
            ->where('branch_id', $student->branch_id)->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('academic_year_id')->orWhere('academic_year_id', $enrollment->academic_year_id))
            ->where(fn ($query) => $query->whereNull('school_class_id')->orWhere('school_class_id', $enrollment->school_class_id))
            ->where(fn ($query) => $query->whereNull('course_id')->orWhere('course_id', $enrollment->course_id))
            ->where(fn ($query) => $query->whereNull('semester_id')->orWhere('semester_id', $enrollment->semester_id))
            ->orderBy('name')->get() : collect();
        // Imported duplicate plans should behave as one option with one configuration signature.
        $planSignature = fn (FeeStructure $plan) => implode('|', [
            mb_strtolower(trim($plan->name)),
            $plan->academic_year_id ?? '',
            $plan->school_class_id ?? '',
            $plan->course_id ?? '',
            $plan->semester_id ?? '',
            number_format((float) $plan->default_amount, 2, '.', ''),
        ]);
        $plans = $plans->unique($planSignature)->values();
        $existingAssignments = $enrollment ? $student->feeAssignments()
            ->with(['component.structure', 'component.academicYear', 'allocations'])
            ->get()
            ->filter(fn (StudentFeeAssignment $assignment) => (int) ($assignment->component?->academic_year_id ?? $assignment->component?->structure?->academic_year_id) === (int) $enrollment->academic_year_id)
            ->values() : collect();
        $existingPlanIds = $existingAssignments->pluck('component.fee_structure_id')->filter()->unique()->values();
        $existingPlanSignatures = $existingAssignments->map(fn (StudentFeeAssignment $assignment) => $assignment->component?->structure)
            ->filter()->map($planSignature)->unique()->values();
        $existingFeeGroups = $existingAssignments->groupBy(function (StudentFeeAssignment $assignment) {
            $structureId = $assignment->component?->fee_structure_id;

            return $structureId ? 'structure-'.$structureId : 'unlinked-'.$assignment->id;
        })->map(function ($assignments) {
            $first = $assignments->first();

            return [
                'name' => $first->component?->structure?->name ?? 'Fee plan',
                'fee_structure_id' => $first->component?->fee_structure_id,
                'installment_count' => $assignments->count(),
                'total' => $assignments->sum(fn (StudentFeeAssignment $assignment) => $assignment->totalDue()),
                'balance' => $assignments->sum(fn (StudentFeeAssignment $assignment) => $assignment->balanceDue()),
                'created_at' => $assignments->min('created_at'),
                'can_remove' => $assignments->every(fn (StudentFeeAssignment $assignment) => $assignment->component && $assignment->allocations->isEmpty()),
                'assignments' => $assignments->sortBy(fn (StudentFeeAssignment $assignment) => $assignment->component?->installment_number ?? 0)->values(),
            ];
        })->values();
        $unpricedPlans = $plans->filter(fn (FeeStructure $plan) => (float) $plan->default_amount <= 0)->values();
        $availablePlans = $plans->filter(fn (FeeStructure $plan) => (float) $plan->default_amount > 0)
            ->reject(fn (FeeStructure $plan) => $existingPlanIds->contains($plan->id) || $existingPlanSignatures->contains($planSignature($plan)))
            ->values();

        return view('fees.students._setup-modal', compact('student', 'enrollment', 'plans', 'unpricedPlans', 'availablePlans', 'existingAssignments', 'existingFeeGroups'));
    }

    /** Update an assigned installment only while it has no payment allocations. */
    public function updateAssignment(Request $request, Student $student, StudentFeeAssignment $assignment)
    {
        abort_unless($request->user()->canAccessBranch($student->branch_id), 403);
        abort_unless((int) $assignment->student_id === (int) $student->id, 404);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'amount' => 'required|numeric|gt:0',
            'due_date' => 'required|date',
        ]);

        DB::transaction(function () use ($assignment, $data, $student) {
            $lockedAssignment = StudentFeeAssignment::query()->whereKey($assignment->id)->lockForUpdate()->firstOrFail();
            $paidAmount = $lockedAssignment->allocations()->lockForUpdate()->get()->sum('amount');

            if ((float) $paidAmount > 0) {
                throw ValidationException::withMessages([
                    'assignment' => 'This installment cannot be edited because a payment has already been recorded.',
                ]);
            }

            $lockedAssignment->load('component');
            abort_unless(
                $lockedAssignment->component
                    && ($lockedAssignment->component->student_id === null
                        || (int) $lockedAssignment->component->student_id === (int) $student->id),
                404,
            );

            if ((float) $data['amount'] < (float) $lockedAssignment->discount) {
                throw ValidationException::withMessages([
                    'amount' => 'The installment amount cannot be lower than its current discount.',
                ]);
            }

            $component = $lockedAssignment->component;

            if ($component->student_id === null) {
                // Detach this student's assignment before editing a component shared by the fee plan.
                $component = $component->replicate();
                $component->student_id = $student->id;
                $component->save();
                $lockedAssignment->component()->associate($component);
                $lockedAssignment->save();
            }

            $component->update([
                'name' => trim($data['name']),
                'amount' => $data['amount'],
                'due_date' => Carbon::parse($data['due_date'])->toDateString(),
            ]);
            $lockedAssignment->update([
                'amount' => $data['amount'],
                'status' => 'open',
            ]);
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Fee installment updated successfully.']);
        }

        return redirect()->route('students.index')->with('success', 'Fee installment updated successfully.');
    }

    /** Remove a complete student fee plan only while none of its installments are paid. */
    public function destroyAssignments(Request $request, Student $student, FeeStructure $feeStructure)
    {
        abort_unless($request->user()->canAccessBranch($student->branch_id), 403);
        abort_unless((int) $feeStructure->branch_id === (int) $student->branch_id, 404);

        $data = $request->validate([
            'academic_year_id' => 'required|integer',
        ]);
        $academicYearId = (int) $data['academic_year_id'];

        abort_unless(AcademicYear::query()->whereKey($academicYearId)->where('branch_id', $student->branch_id)->exists(), 404);

        DB::transaction(function () use ($student, $feeStructure, $academicYearId) {
            $assignments = $student->feeAssignments()
                ->whereHas('component', fn ($query) => $query
                    ->where('fee_structure_id', $feeStructure->id)
                    ->where(function ($yearQuery) use ($academicYearId) {
                        $yearQuery->where('academic_year_id', $academicYearId)
                            ->orWhere(fn ($legacyQuery) => $legacyQuery->whereNull('academic_year_id')
                                ->whereHas('structure', fn ($structureQuery) => $structureQuery->where('academic_year_id', $academicYearId)));
                    }))
                ->with('component')
                ->lockForUpdate()
                ->get();

            if ($assignments->isEmpty()) {
                abort(404);
            }

            $assignmentIds = $assignments->modelKeys();
            $paymentExists = DB::table('payment_allocations')
                ->whereIn('student_fee_assignment_id', $assignmentIds)
                ->lockForUpdate()
                ->exists();

            if ($paymentExists) {
                throw ValidationException::withMessages([
                    'fee' => 'This fee plan cannot be removed because a payment has already been recorded.',
                ]);
            }

            $components = $assignments->pluck('component')->filter();
            $assignments->each->delete();

            foreach ($components as $component) {
                if ((int) $component->student_id !== (int) $student->id) {
                    continue;
                }

                if (! $component->studentFeeAssignments()->exists()) {
                    $component->delete();
                }
            }
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unpaid fee plan removed from the student.']);
        }

        return redirect()->route('students.index')->with('success', 'Unpaid fee plan removed from the student.');
    }

    /** Return only outstanding, already assigned student charges for collection. */
    public function collectModal(Request $request, Student $student)
    {
        abort_unless($request->user()->canAccessBranch($student->branch_id), 403);
        $enrollment = $student->enrollments()->where('status', 'active')->latest()->first();
        $assignments = $student->feeAssignments()->with(['component.structure', 'component.academicYear', 'allocations'])
            ->get()->map(function (StudentFeeAssignment $assignment) {
                $assignment->setAttribute('balance_due', $assignment->balanceDue());

                return $assignment;
            })->filter(fn (StudentFeeAssignment $assignment) => $assignment->balance_due > 0)->values();

        // Keep a single receipt within one academic year while showing all current-year dues together.
        $yearId = $enrollment?->academic_year_id
            ?? $assignments->first(fn (StudentFeeAssignment $assignment) => $assignment->component?->academic_year_id
                ?? $assignment->component?->structure?->academic_year_id)?->component?->academic_year_id
            ?? $assignments->first()?->component?->structure?->academic_year_id;
        $assignments = $assignments->filter(fn (StudentFeeAssignment $assignment) => (int) ($assignment->component?->academic_year_id
            ?? $assignment->component?->structure?->academic_year_id) === (int) $yearId)
            ->sortBy(fn (StudentFeeAssignment $assignment) => sprintf(
                '%s-%06d-%010d',
                $assignment->component?->due_date?->format('Y-m-d') ?? '9999-12-31',
                $assignment->component?->installment_number ?? 999999,
                $assignment->id,
            ))
            ->values();

        return view('fees.students._collect-modal', compact('student', 'enrollment', 'assignments'));
    }

    /** Show fee plans that fit the student's branch and active enrollment. */
    public function create(Request $request, Student $student)
    {
        abort_unless($request->user()->canAccessBranch($student->branch_id), 403);
        $student->load(['enrollments' => fn ($query) => $query->where('status', 'active')->with('academicYear')->latest()]);
        $enrollment = $student->enrollments->first();

        return view('fees.students.create', [
            'student' => $student,
            'enrollment' => $enrollment,
            'plans' => FeeStructure::query()->where('branch_id', $student->branch_id)->where('is_active', true)->where('default_amount', '>', 0)
                ->where(fn ($query) => $query->whereNull('academic_year_id')->orWhere('academic_year_id', $enrollment?->academic_year_id))
                ->where(fn ($query) => $query->whereNull('school_class_id')->orWhere('school_class_id', $enrollment?->school_class_id))
                ->where(fn ($query) => $query->whereNull('course_id')->orWhere('course_id', $enrollment?->course_id))
                ->where(fn ($query) => $query->whereNull('semester_id')->orWhere('semester_id', $enrollment?->semester_id))
                ->orderBy('name')->get(),
            'assignments' => $student->feeAssignments()->with(['component.structure', 'component.academicYear', 'allocations'])->latest()->get(),
        ]);
    }

    /** Persist a full charge or a validated installment schedule atomically. */
    public function store(Request $request, Student $student)
    {
        abort_unless($request->user()->canAccessBranch($student->branch_id), 403);
        $input = $request->all();
        if (! isset($input['fees'])) {
            $input['fees'] = [[
                'fee_structure_id' => $input['fee_structure_id'] ?? null,
                'total_amount' => $input['total_amount'] ?? null,
                'payment_plan' => $input['payment_plan'] ?? null,
                'full_due_date' => $input['full_due_date'] ?? null,
                'installments' => $input['installments'] ?? null,
            ]];
        }

        $data = Validator::make($input, [
            'academic_year_id' => 'required|integer',
            'fees' => 'required|array|min:1',
            'fees.*.fee_structure_id' => 'required|integer|distinct',
            'fees.*.total_amount' => 'required|numeric|gt:0',
            'fees.*.payment_plan' => 'required|in:full,installment',
            'fees.*.full_due_date' => 'nullable|date',
            'fees.*.installments' => 'nullable|array',
            'fees.*.installments.*.name' => 'nullable|string|max:100',
            'fees.*.installments.*.amount' => 'nullable|numeric|gt:0',
            'fees.*.installments.*.due_date' => 'nullable|date',
        ])->validate();

        $yearId = (int) $data['academic_year_id'];
        if (! AcademicYear::query()->whereKey($yearId)->where('branch_id', $student->branch_id)->exists()) {
            throw ValidationException::withMessages(['academic_year_id' => 'Choose an academic year from the student’s branch.']);
        }

        $enrollment = $student->enrollments()->where('status', 'active')->where('academic_year_id', $yearId)->latest()->first();
        if (! $enrollment) {
            throw ValidationException::withMessages(['academic_year_id' => 'The student needs an active enrollment in the selected academic year.']);
        }

        $preparedFees = [];
        $errors = [];
        foreach ($data['fees'] as $feeIndex => $fee) {
            $rules = [
                'full_due_date' => $fee['payment_plan'] === 'full' ? 'required|date' : 'nullable|date',
                'installments' => $fee['payment_plan'] === 'installment' ? 'required|array|min:2' : 'nullable|array',
                'installments.*.name' => 'nullable|string|max:100',
                'installments.*.amount' => $fee['payment_plan'] === 'installment' ? 'required|numeric|gt:0' : 'nullable|numeric|gt:0',
                'installments.*.due_date' => $fee['payment_plan'] === 'installment' ? 'required|date' : 'nullable|date',
            ];
            $feeValidator = Validator::make($fee, $rules);
            if ($feeValidator->fails()) {
                foreach ($feeValidator->errors()->messages() as $field => $messages) {
                    $errors["fees.{$feeIndex}.{$field}"] = $messages;
                }

                continue;
            }

            $plan = FeeStructure::query()->whereKey($fee['fee_structure_id'])
                ->where('branch_id', $student->branch_id)->where('is_active', true)->first();
            if (! $plan) {
                $errors["fees.{$feeIndex}.fee_structure_id"] = ['Choose an active fee plan from the student’s branch.'];

                continue;
            }
            if ((float) $plan->default_amount <= 0) {
                $errors["fees.{$feeIndex}.fee_structure_id"] = ['Add a standard amount to this fee plan before assigning it.'];

                continue;
            }
            if ($plan->academic_year_id !== null && (int) $plan->academic_year_id !== $yearId) {
                $errors["fees.{$feeIndex}.fee_structure_id"] = ['This plan is limited to a different academic year.'];

                continue;
            }
            if (($plan->school_class_id && (int) $plan->school_class_id !== (int) $enrollment->school_class_id)
                || ($plan->course_id && (int) $plan->course_id !== (int) $enrollment->course_id)
                || ($plan->semester_id && (int) $plan->semester_id !== (int) $enrollment->semester_id)) {
                $errors["fees.{$feeIndex}.fee_structure_id"] = ['Choose a fee plan that matches the student’s enrollment.'];

                continue;
            }
            if ($student->feeAssignments()->whereHas('component', fn ($query) => $query
                ->where('fee_structure_id', $plan->id)->where('academic_year_id', $yearId))->exists()) {
                $errors["fees.{$feeIndex}.fee_structure_id"] = ['This fee plan is already assigned for the student in this academic year.'];

                continue;
            }

            $rows = $fee['payment_plan'] === 'full'
                ? [['name' => 'Full payment', 'amount' => $fee['total_amount'], 'due_date' => $fee['full_due_date']]]
                : array_values($fee['installments']);
            $scheduledCents = collect($rows)->sum(fn ($row) => (int) round((float) $row['amount'] * 100));
            $totalCents = (int) round((float) $fee['total_amount'] * 100);
            if ($scheduledCents !== $totalCents) {
                $errors["fees.{$feeIndex}.installments"] = ['Installment amounts must add up to this fee’s total amount.'];

                continue;
            }

            $preparedFees[] = ['plan' => $plan, 'fee' => $fee, 'rows' => $rows];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($preparedFees, $student, $yearId) {
            foreach ($preparedFees as $preparedFee) {
                foreach ($preparedFee['rows'] as $index => $row) {
                    $component = FeeComponent::create([
                        'fee_structure_id' => $preparedFee['plan']->id,
                        'student_id' => $student->id,
                        'academic_year_id' => $yearId,
                        'name' => trim($row['name'] ?? '') ?: ($preparedFee['fee']['payment_plan'] === 'full' ? 'Full payment' : 'Installment '.($index + 1)),
                        'amount' => $row['amount'],
                        'due_date' => Carbon::parse($row['due_date'])->toDateString(),
                        'installment_number' => $preparedFee['fee']['payment_plan'] === 'full' ? null : $index + 1,
                    ]);
                    StudentFeeAssignment::create(['student_id' => $student->id, 'fee_component_id' => $component->id, 'amount' => $row['amount'], 'discount' => 0, 'late_fee' => 0, 'status' => 'open']);
                }
            }
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => count($preparedFees).' fee plan(s) added to the student.'], 201);
        }

        return redirect()->route('students.fees.create', $student)->with('success', count($preparedFees).' fee plan(s) added to the student.');
    }

    /** Display the searchable installment ledger. */
    public function index(Request $request)
    {
        $user = $request->user();
        $filters = $request->validate([
            'branch_id' => 'nullable|integer',
            'student_id' => 'nullable|integer',
            'academic_year_id' => 'nullable|integer',
            'status' => 'nullable|in:open,partial,paid,overdue',
            'due_from' => 'nullable|date',
            'due_to' => 'nullable|date|after_or_equal:due_from',
        ]);
        $accessibleBranches = $user->accessibleBranchIds();
        $branchId = $user->currentBranchId() ?: ($filters['branch_id'] ?? null);

        if ($branchId) {
            abort_unless($user->canAccessBranch($branchId), 403);
        }

        $baseQuery = StudentFeeAssignment::query()
            ->whereHas('student', fn ($students) => $students
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->when(! $branchId && $accessibleBranches !== null, fn ($query) => $query->whereIn('branch_id', $accessibleBranches)))
            ->when($filters['student_id'] ?? null, fn ($query, $studentId) => $query->where('student_id', $studentId))
            ->when($filters['academic_year_id'] ?? null, fn ($query, $yearId) => $query->whereHas('component', fn ($component) => $component
                ->where('academic_year_id', $yearId)
                ->orWhere(fn ($legacyComponent) => $legacyComponent
                    ->whereNull('academic_year_id')
                    ->whereHas('structure', fn ($structure) => $structure->where('academic_year_id', $yearId)))))
            ->when($filters['due_from'] ?? null, fn ($query, $date) => $query->whereHas('component', fn ($component) => $component->whereDate('due_date', '>=', $date)))
            ->when($filters['due_to'] ?? null, fn ($query, $date) => $query->whereHas('component', fn ($component) => $component->whereDate('due_date', '<=', $date)));

        $summary = [
            'total' => (clone $baseQuery)->count(),
            'due' => (clone $baseQuery)->where('status', 'open')->count(),
            'partial' => (clone $baseQuery)->where('status', 'partial')->count(),
            'paid' => (clone $baseQuery)->where('status', 'paid')->count(),
            'overdue' => (clone $baseQuery)
                ->whereIn('status', ['open', 'partial'])
                ->whereHas('component', fn ($component) => $component->whereDate('due_date', '<', today()))
                ->count(),
        ];

        $query = (clone $baseQuery)
            ->with(['student.branch', 'component.structure.academicYear', 'component.academicYear', 'allocations']);

        if (($filters['status'] ?? null) === 'overdue') {
            $query->whereIn('status', ['open', 'partial'])->whereHas('component', fn ($c) => $c->whereDate('due_date', '<', today()));
        } elseif (in_array($filters['status'] ?? null, ['open', 'partial', 'paid'], true)) {
            $query->where('status', $filters['status']);
        }

        return view('fees.assignments', [
            'assignments' => $query
                ->leftJoin('fee_components as assignment_components', 'student_fee_assignments.fee_component_id', '=', 'assignment_components.id')
                ->select('student_fee_assignments.*')
                ->orderByRaw('CASE WHEN assignment_components.due_date IS NULL THEN 1 ELSE 0 END')
                ->orderBy('assignment_components.due_date')
                ->orderBy('student_fee_assignments.id')
                ->paginate(25)
                ->withQueryString(),
            'filters' => $filters,
            'summary' => $summary,
            'students' => Student::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q->whereIn('branch_id', $accessibleBranches))
                ->orderBy('first_name')->get(),
            'branches' => Branch::query()->where('is_active', true)->when($accessibleBranches !== null, fn ($q) => $q->whereIn('id', $accessibleBranches))->orderBy('name')->get(),
            'years' => AcademicYear::query()->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q->whereIn('branch_id', $accessibleBranches))
                ->orderByDesc('start_date')->get(),
            'branchId' => $branchId,
        ]);
    }
}
