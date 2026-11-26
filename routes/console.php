<?php

use App\Models\Subscription;
use App\Services\FeeDueReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('imports:prune-previews', function (): void {
    $disk = Storage::disk('local');
    $cutoff = now()->subDay()->timestamp;
    $deleted = 0;

    foreach ($disk->files('student-imports') as $path) {
        if (! Str::isUuid(pathinfo($path, PATHINFO_FILENAME)) || pathinfo($path, PATHINFO_EXTENSION) !== 'json') {
            continue;
        }

        if ($disk->lastModified($path) < $cutoff && $disk->delete($path)) {
            $deleted++;
        }
    }

    $this->info("Deleted {$deleted} expired import preview(s).");
})->purpose('Remove expired private student import previews');

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
