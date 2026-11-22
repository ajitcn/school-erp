<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy
{
    public function before(User $actor): ?bool
    {
        return $actor->hasRole('super_admin') ? true : null;
    }

    public function view(User $actor, Student $student): bool
    {
        return $actor->hasPermission('students.view') && $actor
            ->canAccessBranch($student
                ->branch_id) || $actor
            ->students()
            ->whereKey($student
                ->id)
            ->exists() || $actor
            ->guardians()
            ->whereHas('students', fn ($q) => $q
                ->whereKey($student
                    ->id))
            ->exists();
    }

    public function update(User $actor, Student $student): bool
    {
        return $actor->hasPermission('students.edit') && $actor->canAccessBranch($student->branch_id);
    }
}
