<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\FinancialYear;
use App\Services\TenantContext;
use App\Services\TenantLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Manages institution, branch, and financial-year configuration.
 */
class SetupController extends Controller
{
    public function index()
    {
        $accessibleBranches = auth()->user()->accessibleBranchIds();

        return view('setup.index', [
            'branches' => Branch::query()->when($accessibleBranches !== null, fn ($q) => $q->whereIn('id', $accessibleBranches))->latest()->paginate(10, ['*'], 'branches_page')->withQueryString(),
            'years' => AcademicYear::query()->with('branch')
                ->when($accessibleBranches !== null, fn ($q) => $q
                    ->whereIn('branch_id', $accessibleBranches))
                ->latest()
                ->paginate(10, ['*'], 'years_page')
                ->withQueryString(),
            'financialYears' => FinancialYear::query()->with('branch')
                ->when($accessibleBranches !== null, fn ($q) => $q
                    ->whereIn('branch_id', $accessibleBranches))
                ->latest()
                ->paginate(10, ['*'], 'financial_page')
                ->withQueryString(),
        ]);
    }

    public function branch(Request $request, TenantLimitService $limits)
    {
        $data = $request->validate(['name' => 'required|string|max:150', 'code' => 'required|string|max:30|unique:branches,code', 'address' => 'nullable|string', 'phone' => 'nullable|string|max:30']);
        $limits->enforce(app(TenantContext::class)->get(), 'branches');
        Branch::create($data);

        return back()->with('success', 'Branch created.');
    }

    public function year(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|integer', 'name' => 'required|string|max:30', 'start_date' => 'required|date', 'end_date' => 'required|date|after:start_date', 'is_current' => 'nullable|boolean',
        ]);
        $branch = Branch::query()->findOrFail($data['branch_id']);
        abort_unless($request->user()->canAccessBranch($branch->id), 403);
        $request->validate([
            'name' => [
                Rule::unique('academic_years', 'name')
                    ->where('tenant_id', $branch->tenant_id)
                    ->where('branch_id', $branch->id),
            ],
        ]);
        $makeCurrent = $request->boolean('is_current') || ! AcademicYear::query()->where('branch_id', $branch->id)->where('is_current', true)->exists();
        unset($data['is_current']);
        if ($makeCurrent) {
            AcademicYear::query()->where('branch_id', $branch->id)->update(['is_current' => false]);
        }
        AcademicYear::create($data + ['is_current' => $makeCurrent]);

        return back()->with('success', 'Academic year created.');
    }

    public function financialYear(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|integer', 'name' => 'required|string|max:30', 'start_date' => 'required|date', 'end_date' => 'required|date|after:start_date', 'is_current' => 'nullable|boolean',
        ]);
        $branch = Branch::query()->findOrFail($data['branch_id']);
        abort_unless($request->user()->canAccessBranch($branch->id), 403);
        $request->validate(['name' => Rule::unique('financial_years')->where('branch_id', $branch->id)]);
        $makeCurrent = $request->boolean('is_current') || ! FinancialYear::query()->where('branch_id', $branch->id)->where('is_current', true)->exists();
        unset($data['is_current']);
        if ($makeCurrent) {
            FinancialYear::query()->where('branch_id', $branch->id)->update(['is_current' => false]);
        }
        FinancialYear::create($data + ['is_current' => $makeCurrent]);

        return back()->with('success', 'Financial year created.');
    }

    public function toggleBranch(Branch $branch)
    {
        abort_unless(auth()->user()->canAccessBranch($branch->id), 403);
        $branch->update(['is_active' => ! $branch->is_active]);

        return back()->with('success', 'Branch status updated.');
    }

    /** Update a branch after confirming it belongs to the current user's tenant access. */
    public function updateBranch(Request $request, Branch $branch)
    {
        abort_unless($request->user()->canAccessBranch($branch->id), 403);

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'code' => ['required', 'string', 'max:30', Rule::unique('branches', 'code')->ignore($branch->id)],
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:30',
        ]);

        $branch->update($data);

        return back()->with('success', 'Branch updated.');
    }

    /** Delete only empty branches so campus history cannot be removed by cascading foreign keys. */
    public function destroyBranch(Request $request, Branch $branch)
    {
        abort_unless($request->user()->canAccessBranch($branch->id), 403);

        foreach (Schema::getTables() as $table) {
            $tableName = $table['name'];

            if ($tableName === 'branches') {
                continue;
            }

            $hasBranchReference = collect(Schema::getColumns($tableName))
                ->contains(fn (array $column): bool => $column['name'] === 'branch_id');

            if ($hasBranchReference && Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'branch_id')
                && DB::table($tableName)->where('branch_id', $branch->id)->exists()) {
                return back()->withErrors([
                    'branch' => 'This campus has records linked to it. Deactivate it instead to preserve its history.',
                ]);
            }
        }

        $branch->delete();

        return back()->with('success', 'Empty branch deleted.');
    }

    public function currentAcademicYear(Request $request, AcademicYear $academicYear)
    {
        abort_unless($request->user()->canAccessBranch($academicYear->branch_id), 403);
        AcademicYear::query()->where('branch_id', $academicYear->branch_id)->update(['is_current' => false]);
        $academicYear->update(['is_current' => true]);

        return back()->with('success', 'Current academic year updated.');
    }

    public function currentFinancialYear(Request $request, FinancialYear $financialYear)
    {
        abort_unless($request->user()->canAccessBranch($financialYear->branch_id), 403);
        FinancialYear::query()->where('branch_id', $financialYear->branch_id)->update(['is_current' => false]);
        $financialYear->update(['is_current' => true]);

        return back()->with('success', 'Current financial year updated.');
    }

    /** Update a branch-scoped academic year without changing its campus ownership. */
    public function updateAcademicYear(Request $request, AcademicYear $academicYear)
    {
        abort_unless($request->user()->canAccessBranch($academicYear->branch_id), 403);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:30',
                Rule::unique('academic_years', 'name')
                    ->where('tenant_id', $academicYear->tenant_id)
                    ->where('branch_id', $academicYear->branch_id)
                    ->ignore($academicYear->id),
            ],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'nullable|boolean',
        ]);
        $makeCurrent = $request->boolean('is_current');
        unset($data['is_current']);

        DB::transaction(function () use ($academicYear, $data, $makeCurrent): void {
            if ($makeCurrent) {
                AcademicYear::query()->where('branch_id', $academicYear->branch_id)->update(['is_current' => false]);
            }

            $academicYear->update($data + ['is_current' => $makeCurrent || $academicYear->is_current]);
        });

        return back()->with('success', 'Academic year updated.');
    }

    /** Delete an academic year only when no tenant history references it. */
    public function destroyAcademicYear(Request $request, AcademicYear $academicYear)
    {
        abort_unless($request->user()->canAccessBranch($academicYear->branch_id), 403);

        if ($academicYear->is_current) {
            return back()->withErrors(['academic_year' => 'Set another academic year as current before deleting this one.']);
        }

        if ($this->hasReferences('academic_year_id', $academicYear->id)) {
            return back()->withErrors(['academic_year' => 'This year has linked student or finance history and cannot be deleted.']);
        }

        $academicYear->delete();

        return back()->with('success', 'Academic year deleted.');
    }

    /** Update a branch-scoped financial year while retaining its campus ownership. */
    public function updateFinancialYear(Request $request, FinancialYear $financialYear)
    {
        abort_unless($request->user()->canAccessBranch($financialYear->branch_id), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:30', Rule::unique('financial_years')->where('branch_id', $financialYear->branch_id)->ignore($financialYear->id)],
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'nullable|boolean',
        ]);
        $makeCurrent = $request->boolean('is_current');
        unset($data['is_current']);

        DB::transaction(function () use ($financialYear, $data, $makeCurrent): void {
            if ($makeCurrent) {
                FinancialYear::query()->where('branch_id', $financialYear->branch_id)->update(['is_current' => false]);
            }

            $financialYear->update($data + ['is_current' => $makeCurrent || $financialYear->is_current]);
        });

        return back()->with('success', 'Financial year updated.');
    }

    /** Delete a financial year only when no accounting records reference it. */
    public function destroyFinancialYear(Request $request, FinancialYear $financialYear)
    {
        abort_unless($request->user()->canAccessBranch($financialYear->branch_id), 403);

        if ($financialYear->is_current) {
            return back()->withErrors(['financial_year' => 'Set another financial year as current before deleting this one.']);
        }

        if ($this->hasReferences('financial_year_id', $financialYear->id)) {
            return back()->withErrors(['financial_year' => 'This year has linked accounting history and cannot be deleted.']);
        }

        $financialYear->delete();

        return back()->with('success', 'Financial year deleted.');
    }

    /** Check all installed tables before deleting a period that may have history. */
    private function hasReferences(string $column, int $id): bool
    {
        foreach (Schema::getTables() as $table) {
            $tableName = $table['name'];

            if ($tableName !== 'academic_years' && $tableName !== 'financial_years'
                && Schema::hasColumn($tableName, $column)
                && DB::table($tableName)->where($column, $id)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function institution(Request $request)
    {
        $data = $request->validate([
            'institution_name' => 'required|string|max:255',
            'institution_type' => 'required|in:school,college',
            'phone' => 'nullable|string|max:30',
            'logo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'remove_logo' => 'nullable|boolean',
        ]);
        $tenant = app(TenantContext::class)->get();
        abort_unless($tenant, 404);

        unset($data['logo'], $data['remove_logo']);
        $oldLogo = $tenant->logo;

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store("tenants/{$tenant->id}/branding", 'public');
        } elseif ($request->boolean('remove_logo')) {
            $data['logo'] = null;
        }

        $tenant->update($data);

        if ($oldLogo && $oldLogo !== $tenant->logo) {
            Storage::disk('public')->delete($oldLogo);
        }

        return back()->with('success', 'Institution profile updated.');
    }
}
