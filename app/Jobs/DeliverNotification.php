<?php

namespace App\Jobs;

use App\Models\NotificationLog;
use App\Services\NotificationService;
use App\Services\WhatsAppCreditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DeliverNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public array $backoff = [30, 120, 600];

    public function __construct(public int $notificationLogId)
    {
    }

    public function handle(NotificationService $service): void
    {
        $log = NotificationLog::withoutGlobalScopes()->findOrFail($this->notificationLogId);
        if ($log->status === 'sent') {
            return;
        }
        $service->deliver($log);
    }

    public function failed(Throwable $exception): void
    {
        NotificationLog::withoutGlobalScopes()->whereKey($this->notificationLogId)->update([
            'status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 4000), 'updated_at' => now(),
        ]);
        app(WhatsAppCreditService::class)->refundFailedNotification($this->notificationLogId);
    }
}
