<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Course;
use App\Models\Department;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Semester;
use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Maintains branch-scoped classes, sections, departments, courses, and semesters.
 */
class AcademicStructureController extends Controller
{
    private array $types = [
        'classes' => SchoolClass::class,
        'sections' => Section::class,
        'departments' => Department::class,
        'courses' => Course::class,
        'semesters' => Semester::class,
    ];

    private function model(string $type): string
    {
        abort_unless(isset($this->types[$type]), 404);

        return $this->types[$type];
    }

    public function index(Request $request, string $type)
    {
        $model = $this->model($type);
        $branchId = auth()->user()->currentBranchId();
        $accessibleBranches = auth()->user()->accessibleBranchIds();
        $itemsQuery = $model::query();

        if ($branchId) {
            if ($type === 'semesters') {
                $itemsQuery->whereHas('course', fn (Builder $query) => $query->where('branch_id', $branchId));
            } else {
                $itemsQuery->where('branch_id', $branchId);
            }
        }
        if (! $branchId && $accessibleBranches !== null) {
            if ($type === 'semesters') {
                $itemsQuery->whereHas('course', fn (Builder $query) => $query->whereIn('branch_id', $accessibleBranches));
            } elseif (in_array($type, ['classes', 'sections', 'departments', 'courses'], true)) {
                $itemsQuery->whereIn('branch_id', $accessibleBranches);
            }
        }

        if ($type === 'classes') {
            $itemsQuery->with('academicYear');
        } elseif ($type === 'sections') {
            $itemsQuery->with('branch');
        }

        $items = $itemsQuery
            ->when($request->filled('q'), fn (Builder $query) => $query->where('name', 'like', '%'.$request->string('q')->toString().'%'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        $branches = Branch::query()
            ->where('is_active', true)
            ->when($accessibleBranches !== null, fn (Builder $query) => $query->whereIn('id', $accessibleBranches))
            ->orderBy('name')
            ->get();

        $years = AcademicYear::query()
            ->where('is_current', true)
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when(! $branchId && $accessibleBranches !== null, fn (Builder $query) => $query->whereIn('branch_id', $accessibleBranches))
            ->orderByDesc('start_date')
            ->get();

        $classes = SchoolClass::query()
            ->where('is_active', true)
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when(! $branchId && $accessibleBranches !== null, fn (Builder $query) => $query->whereIn('branch_id', $accessibleBranches))
            ->orderBy('name')
            ->get();

        $departments = Department::query()
            ->where('is_active', true)
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->when(! $branchId && $accessibleBranches !== null, fn (Builder $query) => $query->whereIn('branch_id', $accessibleBranches))
            ->orderBy('name')
            ->get();

        $courses = Course::query()
            ->where('is_active', true)
            ->when($branchId, fn (Builder $query) => $query->where('branch_id', $branchId))
            ->orderBy('name')
            ->get();

        return view('academic.index', compact('type', 'items', 'branches', 'years', 'classes', 'departments', 'courses'));
    }

    public function store(Request $request, string $type)
    {
        $model = $this->model($type);
        $data = $this->validated($request, $type, true);
        $branchId = auth()->user()->currentBranchId();

        if ($type === 'semesters') {
            $course = Course::query()->findOrFail($data['course_id']);
            abort_unless(auth()->user()->canAccessBranch($course->branch_id), 403);
            if (Semester::query()->where('tenant_id', $course->tenant_id)
                ->where('course_id', $course->id)
                ->where('semester_number', $data['semester_number'])
                ->exists()) {
                throw ValidationException::withMessages(['semester_number' => 'That semester number already exists for this course.']);
            }
        } elseif ($branchId && in_array($type, ['classes', 'sections', 'departments', 'courses'], true)) {
            $data['branch_id'] = $branchId;
        } elseif (isset($data['branch_id'])) {
            abort_unless(auth()->user()->canAccessBranch((int) $data['branch_id']) && Branch::query()->whereKey($data['branch_id'])->exists(), 403);
        }

        if (in_array($type, ['classes', 'sections', 'departments', 'courses'], true) && empty($data['branch_id'])) {
            throw ValidationException::withMessages(['branch_id' => 'Choose a branch.']);
        }
        if ($type === 'classes' && ! AcademicYear::query()->whereKey($data['academic_year_id'])->where('branch_id', $data['branch_id'] ?? $branchId)->exists()) {
            throw ValidationException::withMessages(['academic_year_id' => 'Choose an academic year from the selected branch.']);
        }
        if ($type === 'courses' && ! empty($data['department_id']) && ! Department::query()->whereKey($data['department_id'])
            ->where('branch_id', $data['branch_id'] ?? $branchId)
            ->exists()) {
            throw ValidationException::withMessages(['department_id' => 'Choose a department from the selected branch.']);
        }
        $effectiveBranchId = $data['branch_id'] ?? $branchId;
        $model::create($data);

        return back()->with('success', ucfirst($type).' record created.');
    }

    public function edit(string $type, int $id)
    {
        $item = $this->scopedQuery($type)->findOrFail($id);

        return view('academic.edit', ['type' => $type, 'item' => $item]);
    }

    public function update(Request $request, string $type, int $id)
    {
        $item = $this->scopedQuery($type)->findOrFail($id);
        $data = $this->validated($request, $type, false, $item);
        if ($type === 'semesters') {
            unset($data['course_id'], $data['semester_number']);
        }
        $item->update($data);

        return redirect()->route('academic.index', $type)->with('success', 'Record updated.');
    }

    public function toggle(string $type, int $id)
    {
        $item = $this->scopedQuery($type)->findOrFail($id);
        $item->update(['is_active' => ! $item->is_active]);

        return back()->with('success', 'Record status updated.');
    }

    /** Delete unused masters without cascading away student, timetable, or teaching history. */
    public function destroy(string $type, int $id)
    {
        $item = $this->scopedQuery($type)->findOrFail($id);

        if ($type === 'classes') {
            if ($this->hasReferences('school_class_id', $item->id, ['sections'])) {
                return back()->withErrors(['master' => 'This class is linked to existing records. Deactivate it to preserve its history.']);
            }
        }

        if ($type === 'sections' && $this->hasReferences('section_id', $item->id, ['sections'])) {
            return back()->withErrors(['master' => 'This section has student or teaching history. Deactivate it to preserve its records.']);
        }

        if ($type === 'semesters' && $this->hasReferences('semester_id', $item->id, ['semesters'])) {
            return back()->withErrors(['master' => 'This semester has enrollment or academic history. Deactivate it to preserve its records.']);
        }

        if ($type === 'courses') {
            $semesterIds = Semester::query()->where('course_id', $item->id)->pluck('id');
            foreach ($semesterIds as $semesterId) {
                if ($this->hasReferences('semester_id', $semesterId, ['semesters'])) {
                    return back()->withErrors(['master' => 'This course has enrollment or academic history. Deactivate it to preserve its records.']);
                }
            }

            if ($this->hasReferences('course_id', $item->id, ['semesters'])) {
                return back()->withErrors(['master' => 'This course has linked academic records. Deactivate it to preserve its history.']);
            }
        }

        DB::transaction(fn () => $item->delete());

        $label = match ($type) {
            'classes' => 'Class',
            'sections' => 'Section',
            'departments' => 'Department',
            'courses' => 'Course',
            'semesters' => 'Semester',
        };

        return back()->with('success', $label.' deleted.');
    }

    /** Check installed tables for references before removing a master record. */
    private function hasReferences(string $column, int $id, array $ignoredTables = []): bool
    {
        foreach (Schema::getTables() as $table) {
            $tableName = $table['name'];

            if (in_array($tableName, $ignoredTables, true) || ! Schema::hasColumn($tableName, $column)) {
                continue;
            }

            if (DB::table($tableName)->where($column, $id)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function scopedQuery(string $type): Builder
    {
        $model = $this->model($type);
        $query = $model::query();
        $branchId = auth()->user()->currentBranchId();
        $accessibleBranches = auth()->user()->accessibleBranchIds();

        if ($branchId) {
            if ($type === 'semesters') {
                $query->whereHas('course', fn (Builder $courseQuery) => $courseQuery->where('branch_id', $branchId));
            } else {
                $query->where('branch_id', $branchId);
            }
        }
        if (! $branchId && $accessibleBranches !== null) {
            if ($type === 'semesters') {
                $query->whereHas('course', fn (Builder $courseQuery) => $courseQuery->whereIn('branch_id', $accessibleBranches));
            } elseif (in_array($type, ['classes', 'sections', 'departments', 'courses'], true)) {
                $query->whereIn('branch_id', $accessibleBranches);
            }
        }

        return $query;
    }

    private function validated(Request $request, string $type, bool $creating = false, ?object $item = null): array
    {
        $tenantId = app(TenantContext::class)->id();
        $branchId = $item?->branch_id
            ?? auth()->user()->currentBranchId()
            ?? $request->integer('branch_id');
        $nameRule = ['required', 'string', 'max:150'];
        $codeRule = ['nullable', 'string', 'max:30'];

        if (in_array($type, ['classes', 'sections', 'departments'], true) && $branchId) {
            $uniqueName = Rule::unique($this->model($type) === SchoolClass::class
                ? 'school_classes'
                : ($type === 'sections' ? 'sections' : 'departments'), 'name')
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $branchId);

            if ($type === 'classes') {
                $academicYearId = $item?->academic_year_id ?? $request->input('academic_year_id');
                $uniqueName->where('academic_year_id', $academicYearId);
            }

            if ($item) {
                $uniqueName->ignore($item->id);
            }

            $nameRule[] = $uniqueName;
        }

        if ($type === 'courses' && $branchId) {
            $uniqueCode = Rule::unique('courses', 'code')
                ->where('tenant_id', $tenantId)
                ->where('branch_id', $branchId);

            if ($item) {
                $uniqueCode->ignore($item->id);
            }

            $codeRule[] = $uniqueCode;
        }

        $semesterNumberRule = ['nullable', 'integer', 'min:1'];
        if ($type === 'semesters') {
            $courseId = $item?->course_id ?? $request->input('course_id');
            $uniqueSemesterNumber = Rule::unique('semesters', 'semester_number')
                ->where('tenant_id', $tenantId)
                ->where('course_id', $courseId);

            if ($item) {
                $uniqueSemesterNumber->ignore($item->id);
            }

            $semesterNumberRule[] = $uniqueSemesterNumber;
        }

        $rules = [
            'name' => $nameRule,
            'branch_id' => in_array($type, ['classes', 'sections', 'departments', 'courses'], true) && $creating && ! auth()->user()->currentBranchId()
                ? ['required', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)]
                : ['nullable', 'integer', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'academic_year_id' => $type === 'classes' && $creating ? 'required|exists:academic_years,id' : 'nullable|exists:academic_years,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'course_id' => $type === 'semesters' && $creating ? 'required|exists:courses,id' : 'nullable|exists:courses,id',
            'department_id' => 'nullable|exists:departments,id',
            'semester_number' => $type === 'semesters' && $creating
                ? array_merge(['required'], array_slice($semesterNumberRule, 1))
                : $semesterNumberRule,
            'code' => $type === 'courses' ? array_merge(['required'], array_slice($codeRule, 1)) : ['nullable', 'string', 'max:30'],
            'level_type' => 'nullable|in:school,college',
            'duration_years' => 'nullable|integer|min:1',
            'capacity' => 'nullable|integer|min:1',
        ];

        return $request->validate($rules);
    }
}
