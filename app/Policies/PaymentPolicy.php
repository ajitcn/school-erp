<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->hasRole('super_admin') ? true : null;
    }

    public function view(User $actor, Payment $payment): bool
    {
        return $actor->canAccessBranch($payment
            ->branch_id) && ($actor
            ->hasPermission('payments.collect') || $actor
            ->students()
            ->whereKey($payment
                ->student_id)
            ->exists() || $actor
            ->guardians()
            ->whereHas('students', fn ($q) => $q
                ->whereKey($payment
                    ->student_id))
            ->exists());
    }
}
