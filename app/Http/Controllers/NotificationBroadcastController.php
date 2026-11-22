<?php

namespace App\Http\Controllers;

use App\Models\NotificationBroadcast;
use App\Models\NotificationLog;
use App\Services\NotificationBroadcastService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Provides a tenant-admin form for sending in-app notifications.
 */
class NotificationBroadcastController extends Controller
{
    /**
     * Show active tenant users that the current administrator can reach.
     */
    public function index(Request $request)
    {
        $tenantId = app(TenantContext::class)->id();
        $branchIds = $request->user()->accessibleBranchIds();
        $recipients = DB::table('tenant_user as memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.tenant_id', $tenantId)
            ->where('memberships.is_active', true)
            ->where('users.is_active', true)
            ->when($branchIds !== null, function ($query) use ($branchIds, $tenantId): void {
                $query->where(function ($scoped) use ($branchIds, $tenantId): void {
                    $scoped->whereIn('memberships.primary_branch_id', $branchIds)
                        ->orWhereExists(function ($branchAccess) use ($branchIds, $tenantId): void {
                            $branchAccess->selectRaw('1')
                                ->from('user_branch_access')
                                ->whereColumn('user_branch_access.user_id', 'memberships.user_id')
                                ->where('user_branch_access.tenant_id', $tenantId)
                                ->whereIn('user_branch_access.branch_id', $branchIds);
                        });
                });
            })
            ->orderBy('users.name')
            ->select('users.id', 'users.name', 'users.email', 'memberships.role')
            ->get();
        $broadcastStorageReady = Schema::hasTable('notification_broadcasts');
        $broadcasts = $broadcastStorageReady
            ? NotificationBroadcast::query()
                ->with('creator:id,name')
                ->when($branchIds !== null, fn ($query) => $query->where('created_by', $request->user()->id))
                ->latest()
                ->paginate(10)
            : collect();

        foreach ($broadcasts as $broadcast) {
            $broadcast->read_count = NotificationLog::query()
                ->where('event', 'manual.broadcast')
                ->where('payload->broadcast_id', $broadcast->id)
                ->whereNotNull('read_at')
                ->count();
        }

        return view('admin.notifications.send', compact('recipients', 'broadcasts', 'broadcastStorageReady'));
    }

    /**
     * Validate the message and send it to authorized tenant recipients.
     */
    public function store(Request $request, NotificationBroadcastService $broadcastService)
    {
        $data = $request->validate([
            'audience' => ['required', 'in:all,selected'],
            'recipient_ids' => ['required_if:audience,selected', 'array', 'min:1', 'max:500'],
            'recipient_ids.*' => ['required', 'integer', 'distinct'],
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:5000'],
        ]);
        $branchIds = $request->user()->accessibleBranchIds();
        $broadcast = $broadcastService->send(
            app(TenantContext::class)->id(),
            $request->user()->id,
            array_map('intval', $data['recipient_ids'] ?? []),
            $data['subject'],
            $data['message'],
            $data['audience'] === 'all',
            $branchIds?->all(),
        );

        return redirect()
            ->route('admin.notifications.send')
            ->with('success', "Notification sent to {$broadcast->recipient_count} user(s).");
    }
}
