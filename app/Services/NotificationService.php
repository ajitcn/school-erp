<?php

namespace App\Services;

use App\Jobs\DeliverNotification;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotificationService
{
    public function __construct(
        private readonly PlatformConfigurationService $platformConfiguration,
        private readonly WhatsAppCreditService $whatsAppCredits,
    ) {
    }

    public function send(?int $userId, string $event, string $channel, array $payload = []): NotificationLog
    {
        $tenantId = app(TenantContext::class)->id();
        $template = DB::table('notification_templates')->where('tenant_id', $tenantId)->where('event', $event)->where('is_active', true)->first();
        if ($channel === 'auto') {
            $channel = $template?->channel ?: 'in_app';
        }
        if ($template) {
            $variables = collect($payload)->mapWithKeys(fn ($value, $key) => ['{{'.$key.'}}' => (string) $value])->all();
            $payload['message'] = strtr($template->body, $variables);
            if ($template->subject) {
                $payload['subject'] = strtr($template->subject, $variables);
            }
        }

        $log = DB::transaction(function () use ($userId, $event, $channel, $payload): NotificationLog {
            $notificationLog = NotificationLog::create([
                'user_id' => $userId,
                'event' => $event,
                'channel' => $channel,
                'status' => 'queued',
                'payload' => $payload,
            ]);

            if ($channel === 'whatsapp' && ! $this->whatsAppCredits->reserveForNotification($notificationLog)) {
                $notificationLog->update([
                    'status' => 'failed',
                    'error' => 'WhatsApp credits are insufficient. Ask a tenant administrator to top up the wallet.',
                ]);

                return $notificationLog;
            }

            if ($channel === 'in_app') {
                $notificationLog->update(['status' => 'sent', 'sent_at' => now()]);
            }

            return $notificationLog;
        });

        if (in_array($log->status, ['failed', 'sent'], true)) {
            return $log->fresh();
        }

        try {
            DeliverNotification::dispatch($log->id)->afterCommit();
        } catch (Throwable $exception) {
            if ($channel === 'whatsapp') {
                $this->whatsAppCredits->refundFailedNotification($log->id);
            }

            $log->update([
                'status' => 'failed',
                'error' => 'The notification could not be queued for delivery.',
            ]);
            Log::warning('Notification delivery could not be queued.', [
                'notification_log_id' => $log->id,
                'channel' => $channel,
                'exception' => $exception::class,
            ]);
        }

        return $log->fresh();
    }

    public function deliver(NotificationLog $log): void
    {
        $payload = $log->payload ?? [];
        $user = $log->user_id ? User::query()->withoutGlobalScopes()->find($log->user_id) : null;
        if ($log->channel === 'email') {
            $this->platformConfiguration->applyMailConfiguration();
            if (! $user?->email) {
                throw new \RuntimeException('The notification recipient has no email address.');
            }
            Mail::raw($payload['message'] ?? ucfirst(str_replace('.', ' ', $log->event)), fn ($mail) => $mail
                ->to($user->email)->subject($payload['subject'] ?? ucfirst(str_replace('.', ' ', $log->event))));
        } elseif (in_array($log->channel, ['sms', 'whatsapp'], true)) {
            $phone = trim((string) ($user?->phone ?? ''));
            $twilio = $this->platformConfiguration->twilioSettings();
            $sid = $twilio['account_sid'];
            $token = $twilio['auth_token'];
            $from = $log->channel === 'sms' ? $twilio['sms_from'] : $twilio['whatsapp_from'];
            if (! $sid || ! $token || ! $from) {
                throw new \RuntimeException('Twilio credentials and a sender number are required for this channel.');
            }
            if (! $phone) {
                throw new \RuntimeException('The notification recipient has no phone number.');
            }
            $to = preg_replace('/[^0-9+]/', '', $phone);
            if (! str_starts_with($to, '+')) {
                throw new \RuntimeException('Recipient phone numbers must be stored in E.164 format.');
            }
            if ($log->channel === 'whatsapp') {
                $to = 'whatsapp:'.$to;
                if (! str_starts_with($from, 'whatsapp:')) {
                    $from = 'whatsapp:'.$from;
                }
            }
            $response = Http::asForm()->withBasicAuth($sid, $token)->timeout(15)->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $to, 'From' => $from, 'Body' => $payload['message'] ?? ucfirst(str_replace('.', ' ', $log->event)),
            ]);
            $response->throw();
            $payload['provider_message_id'] = $response->json('sid');
        } else {
            throw new \RuntimeException("No {$log->channel} delivery provider is configured.");
        }

        $log->update(['status' => 'sent', 'sent_at' => now(), 'error' => null, 'payload' => $payload]);
    }
}
