<?php

namespace App\Services;

use App\Models\NotificationBroadcast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Creates tenant-scoped in-app notification records for chosen recipients.
 */
class NotificationBroadcastService
{
    /**
     * Send a notification to active members the sender is allowed to reach.
     *
     * Recipient and tenant checks happen inside the service so the UI cannot
     * broaden a branch-limited administrator's audience by editing the form.
     *
     * @param  array<int, int>  $requestedRecipientIds
     */
    public function send(int $tenantId, int $senderId, array $requestedRecipientIds, string $subject, string $message, bool $sendToAll, ?array $accessibleBranchIds): NotificationBroadcast
    {
        if (! Schema::hasTable('notification_broadcasts')) {
            throw ValidationException::withMessages([
                'audience' => 'Apply the notification history migration before sending broadcasts.',
            ]);
        }

        $members = DB::table('tenant_user as memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.tenant_id', $tenantId)
            ->where('memberships.is_active', true)
            ->where('users.is_active', true)
            ->select('memberships.user_id');

        if ($accessibleBranchIds !== null) {
            $members->where(function ($query) use ($accessibleBranchIds, $tenantId): void {
                $query->whereIn('memberships.primary_branch_id', $accessibleBranchIds)
                    ->orWhereExists(function ($branchAccess) use ($accessibleBranchIds, $tenantId): void {
                        $branchAccess->selectRaw('1')
                            ->from('user_branch_access')
                            ->whereColumn('user_branch_access.user_id', 'memberships.user_id')
                            ->where('user_branch_access.tenant_id', $tenantId)
                            ->whereIn('user_branch_access.branch_id', $accessibleBranchIds);
                    });
            });
        }

        $eligibleIds = $sendToAll
            ? $members->distinct()->pluck('memberships.user_id')->all()
            : $members->whereIn('memberships.user_id', $requestedRecipientIds)->distinct()->pluck('memberships.user_id')->all();

        if (! $eligibleIds) {
            throw ValidationException::withMessages([
                'recipient_ids' => 'No active tenant users are available for this notification.',
            ]);
        }

        if (! $sendToAll && count($eligibleIds) !== count(array_unique($requestedRecipientIds))) {
            throw ValidationException::withMessages([
                'recipient_ids' => 'One or more selected recipients are outside your tenant or branch access.',
            ]);
        }

        return DB::transaction(function () use ($tenantId, $senderId, $eligibleIds, $subject, $message): NotificationBroadcast {
            $now = now();
            $broadcast = NotificationBroadcast::create([
                'tenant_id' => $tenantId,
                'created_by' => $senderId,
                'subject' => $subject,
                'message' => $message,
                'recipient_count' => count($eligibleIds),
            ]);
            $records = collect($eligibleIds)->map(fn (int $recipientId): array => [
                'tenant_id' => $tenantId,
                'user_id' => $recipientId,
                'channel' => 'in_app',
                'event' => 'manual.broadcast',
                'status' => 'sent',
                'payload' => json_encode([
                    'subject' => $subject,
                    'message' => $message,
                    'broadcast_id' => $broadcast->id,
                ], JSON_THROW_ON_ERROR),
                'sent_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $records->chunk(500)->each(fn ($batch) => DB::table('notification_logs')->insert($batch->all()));

            return $broadcast;
        });
    }
}
