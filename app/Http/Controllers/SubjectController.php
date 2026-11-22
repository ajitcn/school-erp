<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Course;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Manage tenant-owned subjects linked to a class or course. */
class SubjectController extends Controller
{
    /** Show the subject list, create form, and any selected subject for editing. */
    public function index(Request $request)
    {
        [$branchIds, $branchId] = $this->branchScope();
        $subjectMastersReady = $this->subjectTablesReady();
        $query = Subject::query()
            ->with(['branch', 'schoolClass', 'course'])
            ->when($branchIds !== null, fn ($builder) => $builder->whereIn('branch_id', $branchIds))
            ->when($branchId, fn ($builder) => $builder->where('branch_id', $branchId))
            ->when($request->filled('q'), fn ($builder) => $builder
                ->where(fn ($search) => $search
                    ->where('name', 'like', '%'.$request->string('q')->toString().'%')
                    ->orWhere('code', 'like', '%'.$request->string('q')->toString().'%')))
            ->when($request->query('status') === 'active', fn ($builder) => $builder->where('is_active', true))
            ->when($request->query('status') === 'inactive', fn ($builder) => $builder->where('is_active', false));
        $subjects = $subjectMastersReady
            ? $query->orderBy('name')->paginate(20)->withQueryString()
            : new LengthAwarePaginator([], 0, 20, 1, ['path' => $request->url()]);
        $editingSubject = $subjectMastersReady && $request->filled('edit')
            ? $this->scopedSubjects($branchIds, $branchId)->findOrFail($request->integer('edit'))
            : null;

        return view('academic.subjects', [
            'subjects' => $subjects,
            'editingSubject' => $editingSubject,
            'branches' => Branch::query()
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($builder) => $builder->whereIn('id', $branchIds))
                ->when($branchId, fn ($builder) => $builder->whereKey($branchId))
                ->orderBy('name')
                ->get(),
            'classes' => SchoolClass::query()
                ->with('branch')
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($builder) => $builder->whereIn('branch_id', $branchIds))
                ->when($branchId, fn ($builder) => $builder->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
            'courses' => Course::query()
                ->with('branch')
                ->where('is_active', true)
                ->when($branchIds !== null, fn ($builder) => $builder->whereIn('branch_id', $branchIds))
                ->when($branchId, fn ($builder) => $builder->where('branch_id', $branchId))
                ->orderBy('name')
                ->get(),
            'branchId' => $branchId,
            'subjectMastersReady' => $subjectMastersReady,
        ]);
    }

    /** Validate ownership and create a subject master for one class or course. */
    public function store(Request $request)
    {
        $this->ensureSubjectTablesReady();

        $user = $request->user();
        $currentBranchId = $user->currentBranchId();
        $tenantId = app(TenantContext::class)->id();
        $data = $request->validate([
            'branch_id' => $currentBranchId
                ? ['nullable', 'integer']
                : ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'owner_type' => ['required', Rule::in(['class', 'course'])],
            'school_class_id' => ['nullable', 'integer', 'required_if:owner_type,class'],
            'course_id' => ['nullable', 'integer', 'required_if:owner_type,course'],
            'name' => ['required', 'string', 'max:150'],
            'code' => ['nullable', 'string', 'max:30'],
        ]);
        $branchId = $currentBranchId ?: (int) $data['branch_id'];
        abort_unless($user->canAccessBranch($branchId), 403);

        if (($data['owner_type'] === 'class' && ! empty($data['course_id']))
            || ($data['owner_type'] === 'course' && ! empty($data['school_class_id']))) {
            throw ValidationException::withMessages([
                'owner_type' => 'Link a subject to either one class or one course.',
            ]);
        }

        $ownerId = $data['owner_type'] === 'class' ? (int) $data['school_class_id'] : (int) $data['course_id'];
        $ownerQuery = $data['owner_type'] === 'class'
            ? SchoolClass::query()
            : Course::query();
        $owner = $ownerQuery->whereKey($ownerId)
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->first();

        if (! $owner) {
            throw ValidationException::withMessages([
                $data['owner_type'] === 'class' ? 'school_class_id' : 'course_id' => 'Choose an active class or course from this branch.',
            ]);
        }

        $name = trim($data['name']);
        $uniqueQuery = Subject::query()
            ->where('branch_id', $branchId)
            ->where('name', $name);

        if ($data['owner_type'] === 'class') {
            $uniqueQuery->where('school_class_id', $ownerId);
        } else {
            $uniqueQuery->where('course_id', $ownerId);
        }

        if ($uniqueQuery->exists()) {
            throw ValidationException::withMessages([
                'name' => 'This subject already exists for the selected class or course.',
            ]);
        }

        Subject::query()->create([
            'branch_id' => $branchId,
            'school_class_id' => $data['owner_type'] === 'class' ? $ownerId : null,
            'course_id' => $data['owner_type'] === 'course' ? $ownerId : null,
            'name' => $name,
            'code' => filled($data['code'] ?? null) ? trim($data['code']) : null,
            'is_active' => true,
        ]);

        return redirect()->route('academic.subjects')->with('success', 'Subject created.');
    }

    /** Update a subject's name or code while preserving its academic owner. */
    public function update(Request $request, int $subject)
    {
        $this->ensureSubjectTablesReady();

        $item = $this->findAccessibleSubject($request, $subject);
        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('academic_subjects', 'name')
                    ->where('tenant_id', app(TenantContext::class)->id())
                    ->where('branch_id', $item->branch_id)
                    ->where('school_class_id', $item->school_class_id)
                    ->where('course_id', $item->course_id)
                    ->ignore($item->id),
            ],
            'code' => ['nullable', 'string', 'max:30'],
        ]);
        $item->update([
            'name' => trim($data['name']),
            'code' => filled($data['code'] ?? null) ? trim($data['code']) : null,
        ]);

        return redirect()->route('academic.subjects')->with('success', 'Subject updated.');
    }

    /** Activate or deactivate a subject without deleting linked result history. */
    public function toggle(Request $request, int $subject)
    {
        $this->ensureSubjectTablesReady();

        $item = $this->findAccessibleSubject($request, $subject);
        $item->update(['is_active' => ! $item->is_active]);

        return back()->with('success', 'Subject status updated.');
    }

    /** Delete an unused subject master while retaining any recorded result history. */
    public function destroy(Request $request, int $subject)
    {
        $this->ensureSubjectTablesReady();

        $item = $this->findAccessibleSubject($request, $subject);

        if ($item->results()->exists()) {
            return back()->withErrors([
                'subject' => 'This subject has recorded results. Deactivate it to preserve the history.',
            ]);
        }

        DB::transaction(fn () => $item->delete());

        return back()->with('success', 'Subject deleted.');
    }

    /** Return branch scoping used by subject lists and owner choices. */
    private function branchScope(): array
    {
        $user = auth()->user();

        return [$user->accessibleBranchIds(), $user->currentBranchId()];
    }

    /** Build a tenant and branch scoped query for subject records. */
    private function scopedSubjects($branchIds, ?int $branchId)
    {
        return Subject::query()
            ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId));
    }

    /** Ensure a subject belongs to the current tenant and an accessible branch. */
    private function findAccessibleSubject(Request $request, int $subject): Subject
    {
        $item = Subject::query()->findOrFail($subject);
        abort_unless($request->user()->canAccessBranch($item->branch_id), 404);

        return $item;
    }

    /** Check whether the subject master and results link have been migrated. */
    private function subjectTablesReady(): bool
    {
        return Schema::hasTable('academic_subjects') && Schema::hasColumn('results', 'subject_id');
    }

    /** Block subject writes with a useful message until the schema is installed. */
    private function ensureSubjectTablesReady(): void
    {
        abort_unless(
            $this->subjectTablesReady(),
            503,
            'Apply the pending database migrations before managing subject masters.',
        );
    }
}
