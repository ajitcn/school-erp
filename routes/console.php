<?php

use App\Models\Subscription;
use App\Services\FeeDueReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('subscriptions:expire', function () {
    $count = Subscription::query()->whereIn('status', ['trial', 'active'])
        ->whereIn('payment_status', ['not_required', 'paid'])
        ->whereNotNull('end_date')
        ->whereDate('end_date', '<', today())
        ->update(['status' => 'expired', 'updated_at' => now()]);
    $this->info("Expired {$count} subscription(s).");
})->purpose('Mark ended tenant subscriptions as expired');

Artisan::command('fees:send-due-reminders', function (FeeDueReminderService $reminders): void {
    $sent = $reminders->sendDueReminders();
    $this->info("Sent {$sent} fee due reminder(s).");
})->purpose('Send one in-app reminder for fee installments due within seven days');
