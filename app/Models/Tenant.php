<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tenant extends Model
{
    protected $guarded = [];

    protected $casts = [
        'trial_started_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'subscription_limits' => 'array',
        'registration_reviewed_at' => 'datetime',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class)->withPivot(['role', 'is_active', 'joined_at'])->withTimestamps();
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function students()
    {
        return $this->hasMany(Student::class);
    }

    /** Get the tenant's WhatsApp message-credit wallet. */
    public function whatsappCreditWallet()
    {
        return $this->hasOne(TenantWhatsAppCreditWallet::class);
    }

    /** Get purchases of WhatsApp message credits for this tenant. */
    public function whatsappCreditPurchases()
    {
        return $this->hasMany(TenantWhatsAppCreditPurchase::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function latestSubscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function pendingRenewal()
    {
        return $this->hasOne(Subscription::class)->where('payment_status', 'renewal_requested')->latestOfMany();
    }

    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['trial', 'active'])
            ->whereIn('payment_status', ['not_required', 'paid'])
            ->whereDate('start_date', '<=', today())
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', today())
            ->orderByDesc('end_date')
            ->first();
    }

    public function hasValidSubscription(): bool
    {
        if ($this->registration_status !== 'approved') {
            return false;
        }

        if ($this->currentSubscription()) {
            return true;
        }

        return ! $this->subscriptions()->exists() && $this->status === 'trial' && $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    /** Determine whether students and parents may access this institution's portal. */
    public function isPortalAccessAllowed(): bool
    {
        return $this->registration_status === 'approved'
            && in_array($this->status, ['active', 'trial'], true)
            && $this->hasValidSubscription();
    }

    public function subscriptionDaysRemaining(): ?int
    {
        $subscription = $this->currentSubscription();
        $expiryDate = $subscription?->end_date ?? $this->trial_ends_at?->toDate();

        return $expiryDate ? (int) today()->diffInDays($expiryDate, false) : null;
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function settings()
    {
        return $this->hasMany(TenantSetting::class);
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function registrationReviewer()
    {
        return $this->belongsTo(User::class, 'registration_reviewed_by');
    }
}
