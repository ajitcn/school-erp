<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->hasRole('super_admin') ? true : null;
    }

    public function viewAny(User $actor): bool
    {
        return $actor->hasPermission('expenses.view');
    }

    public function update(User $actor, Expense $expense): bool
    {
        return $actor->hasPermission('expenses.edit') && $actor->canAccessBranch($expense->branch_id) && $expense->status !== 'approved';
    }
}
