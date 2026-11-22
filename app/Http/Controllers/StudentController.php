<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentRequest;
use App\Models\AcademicYear;
use App\Models\Admission;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Guardian;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\AuditService;
use App\Services\StudentRegisterNumberService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Manages student admissions, enrollments, transfers, and profiles.
 */
class StudentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $branchId = $user->currentBranchId() ?: ($request->filled('branch_id') ? $request->integer('branch_id') : null);
        if ($branchId) {
            abort_unless($user->canAccessBranch($branchId), 403);
        }
        $accessibleBranches = $user->accessibleBranchIds();
        $students = Student::with(['branch', 'enrollments.schoolClass', 'enrollments.section', 'enrollments.academicYear'])
            ->when($accessibleBranches !== null, fn ($q) => $q->whereIn('branch_id', $accessibleBranches))
            ->when($request->filled('branch_id') && ! auth()->user()->currentBranchId(), fn ($q) => $q->where('branch_id', $request->integer('branch_id')))
            ->when($request->filled('academic_year_id'), fn ($q) => $q->whereHas('enrollments', fn ($e) => $e->where('academic_year_id', $request->integer('academic_year_id'))))
            ->when($request->filled('school_class_id'), fn ($q) => $q->whereHas('enrollments', fn ($e) => $e->where('school_class_id', $request->integer('school_class_id'))))
            ->when($request->filled('section_id'), fn ($q) => $q->whereHas('enrollments', fn ($e) => $e->where('section_id', $request->integer('section_id'))))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request
                ->filled('q'), fn ($q) => $q
                ->where(fn ($q) => $q
                    ->where('first_name', 'like', '%'.$request
                        ->string('q').'%')
                    ->orWhere('last_name', 'like', '%'.$request
                        ->string('q').'%')
                    ->orWhere('student_id', $request
                        ->string('q'))
                    ->orWhere('admission_number', $request
                        ->string('q'))))
            ->latest()->paginate(15)->withQueryString();

        $branches = Branch::where('is_active', true)->when($accessibleBranches !== null, fn ($q) => $q->whereIn('id', $accessibleBranches))->orderBy('name')->get();
        $years = AcademicYear::where('is_current', true)->when($branchId, fn ($q) => $q
            ->where('branch_id', $branchId))
            ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q
                ->whereIn('branch_id', $accessibleBranches))
            ->orderByDesc('start_date')
            ->get();
        $classes = SchoolClass::where('is_active', true)->when($branchId, fn ($q) => $q
            ->where('branch_id', $branchId))
            ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q
                ->whereIn('branch_id', $accessibleBranches))
            ->orderBy('name')
            ->get();
        $sections = Section::where('is_active', true)->when($branchId, fn ($q) => $q
            ->where('branch_id', $branchId))
            ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q
                ->whereIn('branch_id', $accessibleBranches))
            ->orderBy('name')
            ->get();

        return view('students.index', compact('students', 'branches', 'years', 'classes', 'sections'));
    }

    public function create()
    {
        $branchId = auth()->user()->currentBranchId();
        $accessibleBranches = auth()->user()->accessibleBranchIds();
        $branches = Branch::query()
            ->where('is_active', true)
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('id', $accessibleBranches))
            ->orderBy('name')
            ->get();

        if (! $branchId && $branches->count() === 1) {
            $branchId = (int) $branches->first()->id;
        }

        $years = AcademicYear::query()
            ->where('is_current', true)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when(! $branchId && $accessibleBranches !== null, fn ($query) => $query->whereIn('branch_id', $accessibleBranches))
            ->orderByDesc('start_date')
            ->get();
        $selectedAcademicYearId = ($branchId || $years->count() === 1)
            ? $years->first()?->id
            : null;

        return view('students.create', [
            'branches' => $branches,
            'years' => $years,
            'selectedBranchId' => $branchId,
            'selectedAcademicYearId' => $selectedAcademicYearId,
            'classes' => SchoolClass::where('is_active', true)->when($branchId, fn ($q) => $q
                ->where('branch_id', $branchId))
                ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q
                    ->whereIn('branch_id', $accessibleBranches))
                ->orderBy('name')
                ->get(),
            'sections' => Section::where('is_active', true)->when($branchId, fn ($q) => $q
                ->where('branch_id', $branchId))
                ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q
                    ->whereIn('branch_id', $accessibleBranches))
                ->orderBy('name')
                ->get(),
            'courses' => Course::where('is_active', true)->when($branchId, fn ($q) => $q
                ->where('branch_id', $branchId))
                ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q
                    ->whereIn('branch_id', $accessibleBranches))
                ->orderBy('name')
                ->get(),
            'semesters' => Semester::where('is_active', true)->with('course')
                ->when($branchId, fn ($q) => $q
                    ->whereHas('course', fn ($c) => $c
                        ->where('branch_id', $branchId)))
                ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q
                    ->whereHas('course', fn ($c) => $c
                        ->whereIn('branch_id', $accessibleBranches)))
                ->get(),
            'guardians' => Guardian::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->when(! $branchId && $accessibleBranches !== null, fn ($q) => $q
                    ->whereIn('branch_id', $accessibleBranches))
                ->orderBy('first_name')
                ->get(),
        ]);
    }

    public function store(
        StoreStudentRequest $request,
        AuditService $audit,
        StudentRegisterNumberService $registerNumbers,
    ) {
        $data = $request->validated();
        $branch = Branch::query()->findOrFail($data['branch_id']);
        abort_unless(auth()->user()->canAccessBranch($branch->id), 403);
        $this->assertEnrollmentBelongsToBranch($data, $branch->id);
        $photoPath = $request->file('photo')?->store('student-photos', 'local');
        $student = DB::transaction(function () use ($data, $photoPath, $registerNumbers) {
            $branch = Branch::query()->whereKey($data['branch_id'])->lockForUpdate()->firstOrFail();
            $next = Student::where('branch_id', $branch->id)->count() + 1;
            $student = Student::create([
                'branch_id' => $branch->id,
                'student_id' => $branch->code.'-'.str_pad($next, 5, '0', STR_PAD_LEFT),
                'admission_number' => $branch->code.'/'.date('Y').'/'.str_pad($next, 5, '0', STR_PAD_LEFT),
                'first_name' => $data['first_name'], 'last_name' => $data['last_name'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null, 'gender' => $data['gender'] ?? null,
                'email' => $data['email'] ?? null, 'phone' => $data['phone'] ?? null, 'address' => $data['address'] ?? null,
                'roll_number' => filled($data['roll_number'] ?? null)
                    ? $data['roll_number']
                    : $registerNumbers->nextRollNumber($branch),
                'gr_number' => filled($data['gr_number'] ?? null)
                    ? $data['gr_number']
                    : $registerNumbers->nextGrNumber($branch),
                'place_of_birth' => $data['place_of_birth'] ?? null,
                'state' => $data['state'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'mother_tongue' => $data['mother_tongue'] ?? null,
                'religion' => $data['religion'] ?? null,
                'blood_group' => $data['blood_group'] ?? null,
                'aadhaar_number' => $data['aadhaar_number'] ?? null,
                'caste' => $data['caste'] ?? null,
                'category' => $data['category'] ?? null,
                'photo_path' => $photoPath,
            ]);
            StudentEnrollment::create(['student_id' => $student->id, 'branch_id' => $branch
                ->id, 'academic_year_id' => $data['academic_year_id'], 'school_class_id' => $data['school_class_id'] ?? null, 'section_id' => $data['section_id'] ?? null, 'course_id' => $data['course_id'] ?? null, 'semester_id' => $data['semester_id'] ?? null, 'admission_date' => today()]);
            $hasPrimaryGuardian = false;
            if (! empty($data['guardian_id'])) {
                $student->guardians()->attach($data['guardian_id'], ['is_primary' => true]);
                $hasPrimaryGuardian = true;
            }

            $hasPrimaryGuardian = $this->createParentGuardian(
                $student,
                (int) $branch->id,
                'father',
                $data,
                ! $hasPrimaryGuardian,
            ) || $hasPrimaryGuardian;
            $this->createParentGuardian(
                $student,
                (int) $branch->id,
                'mother',
                $data,
                ! $hasPrimaryGuardian,
            );

            Admission::create([
                'student_id' => $student->id, 'branch_id' => $branch->id,
                'academic_year_id' => $data['academic_year_id'], 'admission_number' => $student->admission_number,
                'admission_date' => today(), 'status' => 'admitted', 'created_by' => auth()->id(),
            ]);

            return $student;
        });
        $audit->record('student.created', $student, [], $student->toArray());

        return redirect()->route('students.show', $student)->with('success', 'Student admitted successfully.');
    }

    private function assertEnrollmentBelongsToBranch(array $data, int $branchId): void
    {
        if (! AcademicYear::query()->whereKey($data['academic_year_id'])->where('branch_id', $branchId)->exists()) {
            throw ValidationException::withMessages(['academic_year_id' => 'Choose an academic year from the selected branch.']);
        }
        foreach (['school_class_id' => SchoolClass::class, 'section_id' => Section::class, 'course_id' => Course::class] as $field => $model) {
            if (! empty($data[$field])) {
                if (! $model::query()->whereKey($data[$field])->where('branch_id', $branchId)->exists()) {
                    throw ValidationException::withMessages([$field => 'The selected academic option does not belong to this branch.']);
                }
            }
        }
        if (! empty($data['semester_id'])) {
            if (! Semester::query()->whereKey($data['semester_id'])->whereHas('course', fn ($q) => $q->where('branch_id', $branchId))->exists()) {
                throw ValidationException::withMessages(['semester_id' => 'The selected semester does not belong to this branch.']);
            }
        }
        if (! empty($data['guardian_id'])) {
            if (! Guardian::query()->whereKey($data['guardian_id'])->where('branch_id', $branchId)->exists()) {
                throw ValidationException::withMessages(['guardian_id' => 'The selected guardian does not belong to this branch.']);
            }
        }
    }

    /** Create an optional father or mother guardian record during student admission. */
    private function createParentGuardian(
        Student $student,
        int $branchId,
        string $parent,
        array $data,
        bool $isPrimary,
    ): bool {
        if (! $this->parentDetailsArePresent($data, $parent)) {
            return false;
        }

        $guardian = Guardian::query()->create($this->parentGuardianAttributes($branchId, $parent, $data));
        $student->guardians()->attach($guardian->id, ['is_primary' => $isPrimary]);

        return true;
    }

    /** Keep a student's parent guardian record synchronized with the optional form fields. */
    private function syncParentGuardian(Student $student, string $parent, array $data): void
    {
        $guardian = $student->guardians()
            ->whereRaw('LOWER(relation) = ?', [$parent])
            ->first();

        if (! $this->parentDetailsArePresent($data, $parent)) {
            if ($guardian) {
                $guardian->students()->detach($student->id);

                if (! $guardian->students()->exists()) {
                    $guardian->delete();
                }
            }

            return;
        }

        $attributes = $this->parentGuardianAttributes((int) $student->branch_id, $parent, $data);

        if ($guardian) {
            $guardian->update($attributes);

            return;
        }

        $guardian = Guardian::query()->create($attributes);
        $student->guardians()->attach($guardian->id, [
            'is_primary' => ! $student->guardians()->exists(),
        ]);
    }

    /** Build guardian attributes while allowing every parent field to remain blank. */
    private function parentGuardianAttributes(int $branchId, string $parent, array $data): array
    {
        $name = trim((string) ($data[$parent.'_name'] ?? ''));
        $nameParts = $name === ''
            ? [ucfirst($parent), null]
            : array_pad(preg_split('/\s+/', $name, 2) ?: [], 2, null);

        return [
            'branch_id' => $branchId,
            'first_name' => $nameParts[0],
            'last_name' => $nameParts[1],
            'relation' => ucfirst($parent),
            'occupation' => filled($data[$parent.'_occupation'] ?? null) ? $data[$parent.'_occupation'] : null,
            'phone' => filled($data[$parent.'_phone'] ?? null) ? $data[$parent.'_phone'] : null,
            'email' => filled($data[$parent.'_email'] ?? null) ? $data[$parent.'_email'] : null,
        ];
    }

    /** Check whether the form contains any information for a parent. */
    private function parentDetailsArePresent(array $data, string $parent): bool
    {
        foreach (['name', 'occupation', 'phone', 'email'] as $field) {
            if (filled($data[$parent.'_'.$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    public function show(Student $student)
    {
        abort_unless(auth()->user()->canAccessBranch($student->branch_id), 403);
        $user = auth()->user();
        $branches = $user->accessibleBranchIds();

        return view('students.show', [
            'student' => $student->load('branch', 'guardians', 'enrollments.branch', 'enrollments.schoolClass', 'enrollments.section', 'enrollments.academicYear', 'enrollments.course', 'enrollments.semester', 'feeAssignments.component.structure.academicYear', 'feeAssignments.component.academicYear', 'feeAssignments.allocations'),
            'branches' => Branch::query()->where('is_active', true)->when($branches !== null, fn ($q) => $q->whereIn('id', $branches))->orderBy('name')->get(),
            'years' => AcademicYear::query()->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))->orderByDesc('start_date')->get(),
            'classes' => SchoolClass::query()->where('is_active', true)->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))->orderBy('name')->get(),
            'sections' => Section::query()->where('is_active', true)->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))->orderBy('name')->get(),
            'courses' => Course::query()->where('is_active', true)->when($branches !== null, fn ($q) => $q->whereIn('branch_id', $branches))->orderBy('name')->get(),
            'semesters' => Semester::query()->with('course')
                ->where('is_active', true)
                ->when($branches !== null, fn ($q) => $q
                    ->whereHas('course', fn ($q) => $q
                        ->whereIn('branch_id', $branches)))
                ->get(),
            'histories' => DB::table('student_histories')->where('student_id', $student->id)->latest()->get(),
        ]);
    }

    public function edit(Student $student)
    {
        abort_unless(auth()->user()->canAccessBranch($student->branch_id), 403);

        $student->load('guardians');
        $parents = $student->guardians->keyBy(fn (Guardian $guardian): string => strtolower((string) $guardian->relation));

        return view('students.edit', [
            'student' => $student,
            'father' => $parents->get('father'),
            'mother' => $parents->get('mother'),
            'statuses' => ['active', 'inactive', 'graduated', 'transferred', 'withdrawn'],
        ]);
    }

    public function photo(Request $request, Student $student)
    {
        abort_unless($request->user()->canAccessBranch($student->branch_id), 403);
        abort_unless($student->photo_path && Storage::disk('local')->exists($student->photo_path), 404);

        return Storage::disk('local')->response($student->photo_path);
    }

    public function update(
        Request $request,
        Student $student,
        AuditService $audit,
        StudentRegisterNumberService $registerNumbers,
    ) {
        abort_unless(auth()->user()->canAccessBranch($student->branch_id), 403);
        $data = $request->validate([
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string',
            'roll_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('students', 'roll_number')->where('branch_id', $student->branch_id)->ignore($student->id),
            ],
            'gr_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('students', 'gr_number')->where('branch_id', $student->branch_id)->ignore($student->id),
            ],
            'place_of_birth' => 'nullable|string|max:150',
            'state' => 'nullable|string|max:100',
            'nationality' => 'nullable|string|max:100',
            'mother_tongue' => 'nullable|string|max:100',
            'religion' => 'nullable|in:Hindu,Muslim,Christian,Sikh,Buddhist,Jain,Parsi,Other',
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'aadhaar_number' => 'nullable|digits:12',
            'caste' => 'nullable|string|max:100',
            'category' => 'nullable|in:General,OBC,SC,ST,EWS,Other',
            'father_name' => 'nullable|string|max:150',
            'father_occupation' => 'nullable|string|max:150',
            'father_phone' => 'nullable|string|max:30',
            'father_email' => 'nullable|email|max:150',
            'mother_name' => 'nullable|string|max:150',
            'mother_occupation' => 'nullable|string|max:150',
            'mother_phone' => 'nullable|string|max:30',
            'mother_email' => 'nullable|email|max:150',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ]);

        $parentDetails = [];
        foreach (['father', 'mother'] as $parent) {
            foreach (['name', 'occupation', 'phone', 'email'] as $field) {
                $key = $parent.'_'.$field;
                $parentDetails[$key] = $data[$key] ?? null;
                unset($data[$key]);
            }
        }

        if (blank($data['aadhaar_number'] ?? null)) {
            unset($data['aadhaar_number']);
        }

        if ($request->hasFile('photo')) {
            if ($student->photo_path) {
                Storage::disk('local')->delete($student->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('student-photos', 'local');
        }
        unset($data['photo']);
        $before = $student->toArray();

        DB::transaction(function () use ($student, $data, $parentDetails, $registerNumbers): void {
            $branch = Branch::query()->whereKey($student->branch_id)->lockForUpdate()->firstOrFail();

            if (blank($data['roll_number'] ?? null)) {
                $data['roll_number'] = $registerNumbers->nextRollNumber($branch);
            }

            if (blank($data['gr_number'] ?? null)) {
                $data['gr_number'] = $registerNumbers->nextGrNumber($branch);
            }

            $student->update($data);
            $this->syncParentGuardian($student, 'father', $parentDetails);
            $this->syncParentGuardian($student, 'mother', $parentDetails);
        });

        $audit->record('student.updated', $student, $before, $student->fresh()->toArray());

        return redirect()->route('students.show', $student)->with('success', 'Student details updated.');
    }

    public function status(Request $request, Student $student)
    {
        abort_unless(auth()->user()->canAccessBranch($student->branch_id), 403);
        $data = $request->validate(['status' => 'required|in:active,inactive,graduated,transferred,withdrawn']);
        if ($data['status'] === 'active' && ! $student->enrollments()->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['status' => 'Create a new active enrollment before reactivating this student.']);
        }
        DB::transaction(function () use ($student, $data, $request) {
            $student->update(['status' => $data['status']]);
            if ($data['status'] !== 'active') {
                $student->enrollments()->where('status', 'active')->update(['status' => $data['status'] === 'graduated' ? 'completed' : 'withdrawn']);
            }
            DB::table('student_histories')->insert(['student_id' => $student
                ->id, 'user_id' => $request
                ->user()
                ->id, 'tenant_id' => app(TenantContext::class)
                ->id(), 'event' => 'student.status_changed', 'details' => json_encode(['status' => $data['status']], JSON_THROW_ON_ERROR), 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('success', 'Student status updated.');
    }

    public function enroll(Request $request, Student $student)
    {
        abort_unless($request->user()->canAccessBranch($student->branch_id), 403);
        $data = $request->validate([
            'branch_id' => 'required|integer', 'academic_year_id' => 'required|integer',
            'school_class_id' => 'nullable|integer', 'section_id' => 'nullable|integer',
            'course_id' => 'nullable|integer', 'semester_id' => 'nullable|integer',
            'admission_date' => 'nullable|date', 'transition_reason' => 'required|string|max:500',
        ]);
        $branch = Branch::query()->findOrFail($data['branch_id']);
        abort_unless($request->user()->canAccessBranch($branch->id), 403);
        $this->assertEnrollmentBelongsToBranch($data, $branch->id);
        if ($student->enrollments()->where('branch_id', $branch->id)->where('academic_year_id', $data['academic_year_id'])->exists()) {
            throw ValidationException::withMessages(['academic_year_id' => 'This student already has an enrollment for that campus and academic year.']);
        }
        $isTransfer = (int) $branch->id !== (int) $student->branch_id;
        DB::transaction(function () use ($student, $branch, $data, $request, $isTransfer) {
            $student->enrollments()->where('status', 'active')->lockForUpdate()->update(['status' => $isTransfer ? 'withdrawn' : 'promoted']);
            StudentEnrollment::create([
                'student_id' => $student->id, 'branch_id' => $branch->id,
                'academic_year_id' => $data['academic_year_id'], 'school_class_id' => $data['school_class_id'] ?? null,
                'section_id' => $data['section_id'] ?? null, 'course_id' => $data['course_id'] ?? null,
                'semester_id' => $data['semester_id'] ?? null, 'admission_date' => $data['admission_date'] ?? today(), 'status' => 'active',
            ]);
            $student->update(['branch_id' => $branch->id, 'status' => 'active']);
            DB::table('student_histories')->insert([
                'student_id' => $student->id, 'user_id' => $request->user()->id,
                'tenant_id' => app(TenantContext::class)->id(),
                'event' => $isTransfer ? 'student.transferred' : 'student.promoted',
                'details' => json_encode(['branch_id' => $branch->id, 'academic_year_id' => $data['academic_year_id'], 'school_class_id' => $data['school_class_id'] ?? null, 'section_id' => $data['section_id'] ?? null, 'reason' => $data['transition_reason']], JSON_THROW_ON_ERROR),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        return redirect()->route('students.show', $student)->with('success', $isTransfer ? 'Student transferred and enrolled.' : 'Student promoted and enrolled.');
    }
}
