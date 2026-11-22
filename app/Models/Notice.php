<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Schema;

class Notice extends Model
{
    use BelongsToTenant;

    protected $guarded = [];

    protected $casts = ['published_at' => 'datetime', 'expires_at' => 'datetime'];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Get this announcement's individual user read records. */
    public function reads()
    {
        return $this->hasMany(NoticeRead::class);
    }

    /** Get the students selected for an audience-specific announcement. */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'notice_students')
            ->withPivot('tenant_id');
    }

    /**
     * Scope announcements to the recipient's role or the student dashboard they are viewing.
     */
    public function scopeVisibleTo(Builder $query, User|Student|Guardian $recipient, ?Student $student = null): Builder
    {
        if (! Schema::hasTable('notice_students')
            || ! Schema::hasColumn('notices', 'audience_type')
            || ! Schema::hasColumn('notices', 'student_scope')) {
            return $query;
        }

        $membershipRole = $recipient instanceof User ? $recipient->tenantMembership()?->role : null;
        $audiences = match (true) {
            $recipient instanceof Student => ['all', 'students'],
            $recipient instanceof Guardian => ['all', 'students', 'parents'],
            $membershipRole === 'student' => ['all', 'students'],
            $membershipRole === 'parent' => ['all', 'parents'],
            default => ['all', 'staff'],
        };

        if ($student && $recipient instanceof User) {
            $audiences = ['all', 'students', 'parents'];
        }

        $query->whereIn('audience_type', $audiences);

        if ($student) {
            return $query->where(function (Builder $audienceQuery) use ($student): void {
                $audienceQuery
                    ->where('student_scope', 'all')
                    ->orWhereHas('students', fn (Builder $studentQuery) => $studentQuery->whereKey($student->id));
            });
        }

        if ($membershipRole === 'student' && $recipient instanceof User) {
            $studentIds = $recipient->students()->pluck('students.id');

            return $query->where(function (Builder $audienceQuery) use ($studentIds): void {
                $audienceQuery->where('student_scope', 'all');

                if ($studentIds->isNotEmpty()) {
                    $audienceQuery->orWhere(function (Builder $selectedAudience) use ($studentIds): void {
                        $selectedAudience
                            ->where('student_scope', 'selected')
                            ->whereHas('students', fn (Builder $studentQuery) => $studentQuery->whereIn('students.id', $studentIds));
                    });
                }
            });
        }

        return $query;
    }
}
