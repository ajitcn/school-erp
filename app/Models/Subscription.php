<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $guarded = [];

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'renewal_date' => 'date', 'approved_at' => 'datetime', 'amount_due' => 'decimal:2'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isExpired(): bool
    {
        return $this->end_date !== null && $this->end_date->lt(today());
    }
}
