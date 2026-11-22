<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use Illuminate\Http\Request;

/**
 * Lists user notifications and records read state.
 */
class NotificationController extends Controller
{
    /**
     * Show the signed-in user's notification inbox and unread summary.
     */
    public function index(Request $request)
    {
        $filter = $request->validate(['status' => ['nullable', 'in:all,unread,read']])['status'] ?? 'all';
        $query = NotificationLog::query()->where('user_id', $request->user()->id);

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        } elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        return view('notifications.index', [
            'notifications' => $query->latest()->paginate(20)->withQueryString(),
            'filter' => $filter,
            'unreadCount' => NotificationLog::query()->where('user_id', $request->user()->id)->whereNull('read_at')->count(),
            'totalCount' => NotificationLog::query()->where('user_id', $request->user()->id)->count(),
            'failedCount' => NotificationLog::query()->where('user_id', $request->user()->id)->where('status', 'failed')->count(),
        ]);
    }

    /**
     * Mark one notification as read after checking it belongs to the signed-in user.
     */
    public function read(Request $request, NotificationLog $notificationLog)
    {
        abort_unless((int) $notificationLog->user_id === (int) $request->user()->id, 404);
        $notificationLog->update(['read_at' => now()]);

        return back()->with('success', 'Notification marked as read.');
    }

    /**
     * Mark every unread notification for the signed-in user as read.
     */
    public function readAll(Request $request)
    {
        NotificationLog::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
