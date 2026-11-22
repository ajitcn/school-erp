<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Notice;
use App\Models\Student;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Creates and publishes tenant announcements.
 */
class NoticeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $branchIds = $user->accessibleBranchIds();
        $selectedBranchId = $user->currentBranchId() ?: $request->integer('branch_id') ?: null;
        $audienceSchemaReady = Schema::hasTable('notice_students')
            && Schema::hasColumn('notices', 'audience_type')
            && Schema::hasColumn('notices', 'student_scope');

        if ($selectedBranchId) {
            abort_unless($user->canAccessBranch($selectedBranchId), 403);
        }

        $notices = Notice::query()->with(['branch', 'creator'])
            ->when($audienceSchemaReady, fn ($query) => $query->withCount('students'))
            ->when($branchIds !== null, fn ($q) => $q
                ->where(fn ($n) => $n
                    ->whereNull('branch_id')
                    ->orWhereIn('branch_id', $branchIds)))
            ->latest()
            ->paginate(20);

        return view('notices.index', [
            'notices' => $notices,
            'branches' => Branch::query()->where('is_active', true)->when($branchIds !== null, fn ($q) => $q->whereIn('id', $branchIds))->orderBy('name')->get(),
            'students' => $audienceSchemaReady ? Student::query()
                ->with('branch')
                ->when($branchIds !== null, fn ($query) => $query->whereIn('branch_id', $branchIds))
                ->when($selectedBranchId, fn ($query) => $query->where('branch_id', $selectedBranchId))
                ->orderBy('first_name')
                ->get(['id', 'branch_id', 'first_name', 'last_name', 'student_id']) : collect(),
            'selectedBranchId' => $selectedBranchId,
            'audienceSchemaReady' => $audienceSchemaReady,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'nullable|integer|exists:branches,id',
            'audience' => 'required|in:all,staff,students,parents',
            'student_scope' => 'required_if:audience,students|nullable|in:all,selected',
            'student_ids' => 'nullable|array|max:500',
            'student_ids.*' => 'required|integer|distinct|exists:students,id',
            'title' => 'required|string|max:180',
            'body' => 'required|string|max:10000',
            'expires_at' => 'nullable|date|after:now',
            'publish_now' => 'nullable|boolean',
        ]);
        $user = $request->user();
        $branchId = $user->currentBranchId() ?: ($data['branch_id'] ?? null);
        if ($branchId) {
            abort_unless($user->canAccessBranch((int) $branchId), 403);
            Branch::query()->findOrFail($branchId);
        }
        $audience = $data['audience'];
        $studentScope = $audience === 'students' ? $data['student_scope'] : 'all';
        $studentIds = array_values(array_unique(array_map('intval', $data['student_ids'] ?? [])));
        $audienceSchemaReady = Schema::hasTable('notice_students')
            && Schema::hasColumn('notices', 'audience_type')
            && Schema::hasColumn('notices', 'student_scope');

        if (! $audienceSchemaReady && $audience !== 'all') {
            throw ValidationException::withMessages([
                'audience' => 'Apply the latest database migration to target staff, students, or parents.',
            ]);
        }

        if ($audience === 'students' && $studentScope === 'selected' && ! $studentIds) {
            throw ValidationException::withMessages(['student_ids' => 'Select at least one student for this announcement.']);
        }

        if ($studentScope === 'selected') {
            $accessibleBranches = $user->accessibleBranchIds();
            $selectedStudents = Student::query()
                ->whereIn('id', $studentIds)
                ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('branch_id', $accessibleBranches))
                ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
                ->get(['id']);

            if ($selectedStudents->count() !== count($studentIds)) {
                throw ValidationException::withMessages([
                    'student_ids' => 'One or more selected students are outside your accessible campuses or the selected campus.',
                ]);
            }
        }

        $notice = DB::transaction(function () use (
            $branchId,
            $data,
            $request,
            $audience,
            $studentScope,
            $studentIds,
            $audienceSchemaReady,
        ): Notice {
            $attributes = [
                'branch_id' => $branchId,
                'created_by' => $request->user()->id,
                'title' => $data['title'],
                'body' => $data['body'],
                'published_at' => $request->boolean('publish_now') ? now() : null,
                'expires_at' => $data['expires_at'] ?? null,
            ];

            if ($audienceSchemaReady) {
                $attributes['audience_type'] = $audience;
                $attributes['student_scope'] = $studentScope;
            }

            $notice = Notice::create($attributes);

            if ($audience === 'students' && $studentScope === 'selected') {
                $tenantId = app(TenantContext::class)->id();
                $pivotRows = array_map(
                    fn (int $studentId): array => [
                        'tenant_id' => $tenantId,
                        'notice_id' => $notice->id,
                        'student_id' => $studentId,
                    ],
                    $studentIds,
                );
                DB::table('notice_students')->insert($pivotRows);
            }

            return $notice;
        });

        return back()->with('success', $notice->published_at
            ? 'Announcement published to the selected audience.'
            : 'Announcement saved as a draft.');
    }

    public function publish(Request $request, Notice $notice)
    {
        abort_unless($notice->branch_id === null || $request->user()->canAccessBranch($notice->branch_id), 403);
        $notice->update(['published_at' => $notice->published_at ? null : now()]);

        return back()->with('success', $notice->published_at ? 'Announcement published.' : 'Announcement returned to draft.');
    }
}
