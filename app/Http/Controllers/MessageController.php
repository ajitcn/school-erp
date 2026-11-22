<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Models\NoticeRead;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Displays published announcements addressed to the signed-in tenant user.
 */
class MessageController extends Controller
{
    /**
     * List current messages visible to the user's accessible campuses.
     */
    public function index(Request $request)
    {
        $branchIds = $request->user()->accessibleBranchIds();
        $messages = Notice::query()
            ->visibleTo($request->user())
            ->with('branch:id,name')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->when($branchIds !== null, fn ($query) => $query->where(fn ($scoped) => $scoped
                ->whereNull('branch_id')
                ->orWhereIn('branch_id', $branchIds)))
            ->latest('published_at')
            ->paginate(15);

        $readTrackingEnabled = Schema::hasTable('notice_reads');
        $readAtByNotice = $readTrackingEnabled
            ? NoticeRead::query()
                ->where('user_id', $request->user()->id)
                ->whereIn('notice_id', $messages->getCollection()->pluck('id'))
                ->get(['notice_id', 'read_at'])
                ->keyBy('notice_id')
            : collect();

        return view('messages.index', compact('messages', 'readAtByNotice', 'readTrackingEnabled'));
    }

    /**
     * Record that the signed-in user has read a visible published message.
     */
    public function read(Request $request, Notice $notice)
    {
        abort_unless(Schema::hasTable('notice_reads'), 503, 'Message read tracking is not available yet.');
        $user = $request->user();
        abort_unless($notice->published_at && $notice->published_at->isPast(), 404);
        abort_unless(! $notice->expires_at || $notice->expires_at->isFuture(), 404);
        abort_unless($notice->branch_id === null || $user->canAccessBranch((int) $notice->branch_id), 404);
        abort_unless(Notice::query()->visibleTo($user)->whereKey($notice->id)->exists(), 404);

        NoticeRead::query()->updateOrCreate(
            ['notice_id' => $notice->id, 'user_id' => $user->id],
            ['tenant_id' => app(TenantContext::class)->id(), 'read_at' => now()],
        );

        return back()->with('success', 'Message marked as read.');
    }
}
