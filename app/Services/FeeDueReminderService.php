<?php

namespace App\Services;

use App\Models\NotificationLog;
use App\Models\StudentFeeAssignment;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends one reminder per user and fee installment before or after its due date.
 */
class FeeDueReminderService
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * Remind student and guardian accounts for balances due within seven days.
     */
    public function sendDueReminders(): int
    {
        $sentCount = 0;
        $tenantIds = Tenant::query()->where('registration_status', 'approved')->pluck('id');

        foreach ($tenantIds as $tenantId) {
            $tenant = Tenant::query()->find($tenantId);
            if (! $tenant) {
                continue;
            }

            $this->tenantContext->set($tenant);
            try {
                $sentCount += $this->sendTenantReminders();
            } catch (Throwable $exception) {
                Log::error('Fee due reminder run failed for a tenant.', [
                    'tenant_id' => $tenantId,
                    'exception' => $exception,
                ]);
            } finally {
                $this->tenantContext->clear();
            }
        }

        return $sentCount;
    }

    /**
     * Process due installments with tenant scope active for templates and logs.
     */
    private function sendTenantReminders(): int
    {
        $sentCount = 0;
        $reminderCutoff = today()->addDays(7);

        StudentFeeAssignment::query()
            ->with([
                'component.structure',
                'allocations',
                'student.userAccounts',
                'student.guardians.userAccounts',
            ])
            ->whereIn('status', ['open', 'due', 'partial'])
            ->whereHas('component', fn ($query) => $query
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<=', $reminderCutoff))
            ->chunkById(100, function (Collection $assignments) use (&$sentCount): void {
                foreach ($assignments as $assignment) {
                    $balance = $assignment->balanceDue();
                    if ($balance <= 0) {
                        continue;
                    }

                    $student = $assignment->student;
                    if (! $student) {
                        continue;
                    }

                    $component = $assignment->component;
                    $dueDate = $component?->due_date;
                    if (! $dueDate) {
                        continue;
                    }

                    $recipients = $student->userAccounts
                        ->merge($student->guardians->flatMap->userAccounts)
                        ->unique('id');

                    foreach ($recipients as $recipient) {
                        $alreadySent = NotificationLog::query()
                            ->where('user_id', $recipient->id)
                            ->where('event', 'fee.due_reminder')
                            ->where('payload->fee_assignment_id', $assignment->id)
                            ->exists();

                        if ($alreadySent) {
                            continue;
                        }

                        $this->notifications->send($recipient->id, 'fee.due_reminder', 'auto', [
                            'fee_assignment_id' => $assignment->id,
                            'student' => trim($student->first_name.' '.$student->last_name),
                            'fee_plan' => $component->structure?->name ?? $component->name,
                            'amount' => number_format($balance, 2),
                            'due_date' => $dueDate->format('d M Y'),
                            'due_status' => $dueDate->lt(today()) ? 'Overdue' : 'Due soon',
                        ]);
                        $sentCount++;
                    }
                }
            });

        return $sentCount;
    }
}
