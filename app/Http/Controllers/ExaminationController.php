<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Services\TenantContext;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Manage examination schedules; all student marks are recorded through Results. */
class ExaminationController extends Controller
{
    /** Show tenant-scoped examination schedules and result-link counts. */
    public function index(Request $request): View
    {
        $user = $request->user();
        $accessibleBranchIds = $user->accessibleBranchIds();
        $branchId = $request->has('branch_id')
            ? ($request->filled('branch_id') ? $request->integer('branch_id') : null)
            : $user->currentBranchId();

        if ($branchId) {
            abort_unless($user->canAccessBranch($branchId), 403);
        }

        $tenantId = app(TenantContext::class)->id();
        $academicYearId = $request->filled('academic_year_id') ? $request->integer('academic_year_id') : null;
        $status = in_array($request->query('status'), ['draft', 'published'], true)
            ? $request->query('status')
            : null;
        $resultsLinked = Schema::hasColumn('results', 'examination_id');
        $editingExam = null;
        $editingExamHasResults = false;

        if ($request->filled('edit')) {
            $editingExam = DB::table('examinations')
                ->where('id', $request->integer('edit'))
                ->where('tenant_id', $tenantId)
                ->first();

            abort_unless($editingExam && $user->canAccessBranch($editingExam->branch_id), 404);

            $editingExamHasResults = $resultsLinked && DB::table('results')
                ->where('tenant_id', $tenantId)
                ->where('examination_id', $editingExam->id)
                ->exists();
        }

        $examScope = DB::table('examinations')
            ->where('examinations.tenant_id', $tenantId)
            ->when($accessibleBranchIds !== null, fn ($query) => $query->whereIn('examinations.branch_id', $accessibleBranchIds))
            ->when($branchId, fn ($query) => $query->where('examinations.branch_id', $branchId))
            ->when($academicYearId, fn ($query) => $query->where('examinations.academic_year_id', $academicYearId));
        $examQuery = (clone $examScope)
            ->when($status, fn ($query) => $query->where('examinations.status', $status))
            ->join('academic_years', 'academic_years.id', '=', 'examinations.academic_year_id')
            ->join('branches', 'branches.id', '=', 'examinations.branch_id');

        if ($resultsLinked) {
            $resultSummary = DB::table('results')
                ->select('examination_id')
                ->selectRaw('COUNT(*) as linked_result_count')
                ->where('tenant_id', $tenantId)
                ->whereNotNull('examination_id')
                ->groupBy('examination_id');
            $examQuery->leftJoinSub($resultSummary, 'result_summary', fn ($join) => $join
                ->on('result_summary.examination_id', '=', 'examinations.id'));
            $resultCountSelect = DB::raw('COALESCE(result_summary.linked_result_count, 0) as linked_result_count');
        } else {
            $resultCountSelect = DB::raw('0 as linked_result_count');
        }

        $exams = $examQuery
            ->select([
                'examinations.*',
                'academic_years.name as year_name',
                'branches.name as branch_name',
                $resultCountSelect,
            ])
            ->orderByDesc('examinations.starts_on')
            ->orderByDesc('examinations.id')
            ->paginate(20)
            ->withQueryString();

        $summaryScope = (clone $examScope)->select('examinations.status');

        return view('academic.examinations', [
            'exams' => $exams,
            'branches' => Branch::query()
                ->where('is_active', true)
                ->when($accessibleBranchIds !== null, fn ($query) => $query->whereIn('id', $accessibleBranchIds))
                ->orderBy('name')
                ->get(),
            'years' => AcademicYear::query()
                ->with('branch')
                ->when($accessibleBranchIds !== null, fn ($query) => $query->whereIn('branch_id', $accessibleBranchIds))
                ->when($branchId && ! ($editingExam && ! $editingExamHasResults), fn ($query) => $query->where('branch_id', $branchId))
                ->orderByDesc('start_date')
                ->get(),
            'branchId' => $branchId,
            'editingExam' => $editingExam,
            'editingExamHasResults' => $editingExamHasResults,
            'totalExamCount' => (clone $summaryScope)->count(),
            'draftExamCount' => (clone $examScope)->where('examinations.status', 'draft')->count(),
            'publishedExamCount' => (clone $examScope)->where('examinations.status', 'published')->count(),
            'linkedResultCount' => $resultsLinked
                ? DB::table('results')->where('tenant_id', $tenantId)->whereNotNull('examination_id')
                    ->whereIn('examination_id', (clone $examScope)->select('examinations.id'))->count()
                : 0,
            'resultsLinked' => $resultsLinked,
            'createFormOpen' => $editingExam !== null || ($request->session()->has('errors')
                && collect(['branch_id', 'academic_year_id', 'name', 'exam_type', 'starts_on', 'ends_on', 'status'])
                    ->contains(fn ($field) => $request->session()->get('errors')->has($field))),
        ]);
    }

    /** Create a draft examination for an academic year at an accessible branch. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => 'required|integer',
            'academic_year_id' => 'required|integer',
            'name' => 'required|string|max:150',
            'exam_type' => 'required|in:term,midterm,final,entrance,practical',
            'status' => 'required|in:draft,published',
            'starts_on' => 'nullable|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
        ]);
        $branchId = (int) $data['branch_id'];
        abort_unless($branchId && $request->user()->canAccessBranch($branchId), 403);

        if (! AcademicYear::query()->whereKey($data['academic_year_id'])->where('branch_id', $branchId)->exists()) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Choose an academic year from the selected branch.',
            ]);
        }

        DB::table('examinations')->insert([
            ...$data,
            'tenant_id' => app(TenantContext::class)->id(),
            'branch_id' => $branchId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('academic.examinations', ['branch_id' => $branchId])
            ->with('success', $data['status'] === 'published'
                ? 'Examination created and published.'
                : 'Examination created as a draft.');
    }

    /** Update an examination while keeping linked student results in sync with its publication state. */
    public function update(Request $request, int $examination): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => 'required|integer',
            'academic_year_id' => 'required|integer',
            'name' => 'required|string|max:150',
            'exam_type' => 'required|in:term,midterm,final,entrance,practical',
            'status' => 'required|in:draft,published',
            'starts_on' => 'nullable|date',
            'ends_on' => 'nullable|date|after_or_equal:starts_on',
        ]);
        $tenantId = app(TenantContext::class)->id();
        $user = $request->user();
        $exam = DB::table('examinations')
            ->where('id', $examination)
            ->where('tenant_id', $tenantId)
            ->first();
        abort_unless($exam && $user->canAccessBranch($exam->branch_id), 404);

        $branchId = (int) $data['branch_id'];
        abort_unless($user->canAccessBranch($branchId), 403);

        if (! AcademicYear::query()->whereKey($data['academic_year_id'])->where('branch_id', $branchId)->exists()) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Choose an academic year from the selected branch.',
            ]);
        }

        $resultsLinked = Schema::hasColumn('results', 'examination_id');
        $hasLinkedResults = $resultsLinked && DB::table('results')
            ->where('tenant_id', $tenantId)
            ->where('examination_id', $exam->id)
            ->exists();

        if ($hasLinkedResults && ((int) $exam->branch_id !== $branchId || (int) $exam->academic_year_id !== (int) $data['academic_year_id'])) {
            throw ValidationException::withMessages([
                'academic_year_id' => 'Branch and academic year cannot change after results have been linked.',
            ]);
        }

        DB::transaction(function () use ($data, $exam, $tenantId, $resultsLinked): void {
            DB::table('examinations')
                ->where('id', $exam->id)
                ->where('tenant_id', $tenantId)
                ->update([
                    ...$data,
                    'updated_at' => now(),
                ]);

            if ($resultsLinked) {
                DB::table('results')
                    ->where('tenant_id', $tenantId)
                    ->where('examination_id', $exam->id)
                    ->update([
                        'status' => $data['status'],
                        'updated_at' => now(),
                    ]);
            }
        });

        return redirect()
            ->route('academic.examinations', ['branch_id' => $branchId])
            ->with('success', $data['status'] === 'published'
                ? 'Examination updated and published. Its linked results are now published.'
                : 'Examination updated as a draft. Its linked results are now drafts.');
    }

    /** Publish an examination and its linked result rows as one transaction. */
    public function publish(Request $request, int $examination): RedirectResponse
    {
        $tenantId = app(TenantContext::class)->id();
        $exam = DB::table('examinations')
            ->where('id', $examination)
            ->where('tenant_id', $tenantId)
            ->first();
        abort_unless($exam && $request->user()->canAccessBranch($exam->branch_id), 404);

        $resultsLinked = Schema::hasColumn('results', 'examination_id');

        DB::transaction(function () use ($exam, $tenantId, $resultsLinked): void {
            DB::table('examinations')
                ->where('id', $exam->id)
                ->where('tenant_id', $tenantId)
                ->update(['status' => 'published', 'updated_at' => now()]);

            if ($resultsLinked) {
                DB::table('results')
                    ->where('tenant_id', $tenantId)
                    ->where('examination_id', $exam->id)
                    ->update(['status' => 'published', 'updated_at' => now()]);
            }
        });

        return back()->with('success', 'Examination published. Any linked results are now available to students and parents.');
    }
}
