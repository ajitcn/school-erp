<?php

namespace App\Http\Controllers;

use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Maintains tenant-specific message templates and delivery channels.
 */
class NotificationTemplateController extends Controller
{
    public function index()
    {
        return view('admin.notification-templates', ['templates' => DB::table('notification_templates')->where('tenant_id', app(TenantContext::class)
            ->id())
            ->orderBy('event')
            ->get()]);
    }

    public function save(Request $request)
    {
        $d = $request->validate(['event' => 'required|string|max:100', 'channel' => 'required|in:in_app,email,sms,whatsapp', 'subject' => 'nullable|string|max:180', 'body' => 'required|string|max:5000', 'is_active' => 'nullable|boolean']);
        DB::table('notification_templates')->updateOrInsert(['tenant_id' => app(TenantContext::class)
            ->id(), 'event' => $d['event']], ['channel' => $d['channel'], 'subject' => $d['subject'] ?? null, 'body' => $d['body'], 'is_active' => $request
            ->boolean('is_active'), 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Notification template saved.');
    }

    public function delete(string $event)
    {
        DB::table('notification_templates')->where('tenant_id', app(TenantContext::class)->id())->where('event', $event)->delete();

        return back()->with('success', 'Notification template removed.');
    }
}
