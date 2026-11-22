<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\ApprovedTenantMasterSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Provides platform-only tenant, plan, and renewal administration. */
class PlatformController extends Controller
{
    /** Show the platform-only guide to registration, tenant access, and billing flows. */
    public function guide(): View
    {
        return view('platform.guide');
    }

    public function tenants(Request $request)
    {
        $tenants = Tenant::query()
            ->with('plan')
            ->when($request->filled('q'), fn ($query) => $query->where(function ($query) use ($request): void {
                $query->where('institution_name', 'like', '%'.$request->string('q').'%')
                    ->orWhere('email', 'like', '%'.$request->string('q').'%');
            }))
            ->when($request->filled('registration_status'), fn ($query) => $query->where('registration_status', $request->string('registration_status')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('institution_type'), fn ($query) => $query->where('institution_type', $request->string('institution_type')))
            ->orderByRaw("CASE WHEN registration_status = 'pending' THEN 0 ELSE 1 END")
            ->latest()
            ->paginate(20)->withQueryString();
        $summary = [
            'tenants' => Tenant::count(),
            'pending_registrations' => Tenant::where('registration_status', 'pending')->count(),
            'active' => Tenant::where('registration_status', 'approved')->where('status', 'active')->count(),
            'trials' => Tenant::where('registration_status', 'approved')->where('status', 'trial')->count(),
        ];

        return view('platform.tenants', compact('tenants', 'summary'));
    }

    /** Show one tenant's profile, platform usage, administrators, and billing history. */
    public function showTenant(Tenant $tenant)
    {
        $tenant->load([
            'plan',
            'owner',
            'registrationReviewer',
            'subscriptions' => fn ($query) => $query->with(['plan', 'requester', 'approver'])->latest(),
        ]);
        $tenant->setRelation('branches', $tenant->branches()->orderBy('name')->get());
        $tenant->setRelation('users', $tenant->users()->orderByDesc('tenant_user.joined_at')->limit(10)->get());
        $counts = [
            'branches' => $tenant->branches()->count(),
            'students' => $tenant->students()->count(),
            'users' => $tenant->users()->count(),
        ];
        $usage = $this->usageMetrics($tenant, $counts);
        $audit = AuditLog::query()->where('tenant_id', $tenant->id)
            ->where('action', 'like', 'platform.%')
            ->with('user')->latest()->limit(8)->get();

        return view('platform.tenant-show', compact('tenant', 'usage', 'audit', 'counts'));
    }

    /** Compare tenant resource usage with the effective subscription limits. */
    public function usage(Request $request)
    {
        $tenants = Tenant::query()->with('plan')->withCount(['branches', 'students', 'users'])
            ->when($request->filled('q'), fn ($query) => $query->where('institution_name', 'like', '%'.$request->string('q')->toString().'%'))
            ->when($request->filled('plan_id'), fn ($query) => $query->where('subscription_plan_id', $request->integer('plan_id')))
            ->when($request->filled('institution_type'), fn ($query) => $query->where('institution_type', $request->string('institution_type')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()->paginate(20)->withQueryString();

        $tenants->getCollection()->transform(function (Tenant $tenant): Tenant {
            $tenant->setAttribute('usage_metrics', $this->usageMetrics($tenant, [
                'branches' => $tenant->branches_count,
                'students' => $tenant->students_count,
                'users' => $tenant->users_count,
            ]));

            return $tenant;
        });

        $summary = [
            'tenants' => Tenant::count(),
            'branches' => DB::table('branches')->whereNotNull('tenant_id')->count(),
            'students' => DB::table('students')->whereNotNull('tenant_id')->count(),
            'users' => DB::table('tenant_user')->count(),
        ];
        $plans = SubscriptionPlan::query()->orderBy('name')->get(['id', 'name']);

        return view('platform.usage', compact('tenants', 'summary', 'plans'));
    }

    /** Review platform administration actions with actor, tenant, and date filters. */
    public function auditLog(Request $request)
    {
        $filters = $request->validate([
            'action' => 'nullable|string|max:120',
            'tenant_id' => 'nullable|integer',
            'actor_id' => 'nullable|integer',
            'from_date' => 'nullable|date',
            'to_date' => 'nullable|date|after_or_equal:from_date',
        ]);
        $baseQuery = AuditLog::query()->where('action', 'like', 'platform.%');
        $logs = (clone $baseQuery)->with(['user', 'auditable'])
            ->when($request->filled('action'), fn ($query) => $query->where('action', $filters['action']))
            ->when($request->filled('tenant_id'), fn ($query) => $query->where('tenant_id', $filters['tenant_id']))
            ->when($request->filled('actor_id'), fn ($query) => $query->where('user_id', $filters['actor_id']))
            ->when($request->filled('from_date'), fn ($query) => $query->whereDate('created_at', '>=', $filters['from_date']))
            ->when($request->filled('to_date'), fn ($query) => $query->whereDate('created_at', '<=', $filters['to_date']))
            ->latest()->paginate(30)->withQueryString();
        $actions = (clone $baseQuery)->distinct()->orderBy('action')->pluck('action');
        $tenantIds = (clone $baseQuery)->whereNotNull('tenant_id')->distinct()->pluck('tenant_id');
        $tenants = Tenant::query()->whereIn('id', $tenantIds)->orderBy('institution_name')->get(['id', 'institution_name']);
        $actorIds = (clone $baseQuery)->whereNotNull('user_id')->distinct()->pluck('user_id');
        $actors = User::query()->whereIn('id', $actorIds)->orderBy('name')->get(['id', 'name']);
        $summary = [
            'actions' => (clone $baseQuery)->count(),
            'today' => (clone $baseQuery)->whereDate('created_at', today())->count(),
            'actors' => (clone $baseQuery)->whereNotNull('user_id')->distinct('user_id')->count('user_id'),
        ];

        return view('platform.audit', compact('logs', 'actions', 'tenants', 'actors', 'summary'));
    }

    /** Build usage labels and progress values from the limits tenant creation currently enforces. */
    private function usageMetrics(Tenant $tenant, array $counts): array
    {
        $metrics = [];

        foreach (['branches', 'users', 'students'] as $resource) {
            $limitKey = 'max_'.$resource;
            $limit = data_get($tenant->subscription_limits, $limitKey)
                ?? $tenant->plan?->{$limitKey};
            $used = (int) ($counts[$resource] ?? 0);
            $percentage = $limit === null
                ? null
                : ($limit > 0 ? (int) round(($used / $limit) * 100) : ($used > 0 ? 100 : 0));
            $metrics[$resource] = [
                'used' => $used,
                'limit' => $limit,
                'percentage' => $percentage === null ? null : min($percentage, 100),
                'is_over' => $limit !== null && $used > $limit,
                'is_near' => $limit !== null && $used <= $limit && $percentage >= 80,
            ];
        }

        return $metrics;
    }

    /** Record a platform-level event in the existing tenant-aware audit log. */
    private function recordPlatformAudit(Request $request, string $action, Model $model, array $oldValues = [], array $newValues = [], ?int $tenantId = null): void
    {
        AuditLog::create([
            'tenant_id' => $tenantId,
            'user_id' => $request->user()->id,
            'branch_id' => null,
            'action' => $action,
            'auditable_type' => $model->getMorphClass(),
            'auditable_id' => $model->getKey(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
        ]);
    }

    /** Show subscription and payment activity with review actions. */
    public function subscriptions(Request $request)
    {
        $subscriptions = Subscription::query()->with(['tenant', 'plan', 'requester', 'approver'])
            ->when($request->filled('q'), fn ($query) => $query->whereHas('tenant', fn ($tenantQuery) => $tenantQuery
                ->where('institution_name', 'like', '%'.$request->string('q').'%')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('payment_status'), fn ($query) => $query->where('payment_status', $request->string('payment_status')))
            ->when($request->filled('billing_interval'), fn ($query) => $query->where('billing_interval', $request->string('billing_interval')))
            ->latest()->paginate(20)->withQueryString();

        $summary = [
            'subscriptions' => Subscription::count(),
            'active' => Subscription::where('status', 'active')->count(),
            'trials' => Subscription::where('status', 'trial')->count(),
            'payment_reviews' => Subscription::where('payment_status', 'renewal_requested')->count(),
            'paid_amount' => Subscription::where('payment_status', 'paid')->sum('amount_due'),
        ];

        return view('platform.subscriptions', compact('subscriptions', 'summary'));
    }

    /** Show plan catalog and tenant adoption counts. */
    public function plans(Request $request)
    {
        $plans = SubscriptionPlan::query()->withCount('tenants')
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('state'), fn ($query) => $query->where('is_active', $request->string('state') === 'active'))
            ->latest()->paginate(12)->withQueryString();
        $summary = [
            'plans' => SubscriptionPlan::count(),
            'active' => SubscriptionPlan::where('is_active', true)->count(),
            'inactive' => SubscriptionPlan::where('is_active', false)->count(),
            'subscribed_tenants' => Tenant::whereNotNull('subscription_plan_id')->count(),
        ];

        return view('platform.plans', compact('plans', 'summary'));
    }

    public function status(Request $request, Tenant $tenant)
    {
        abort_unless($tenant->registration_status === 'approved', 409, 'Approve the institution registration before changing tenant access.');

        $data = $request->validate([
            'status' => 'required|in:trial,active,suspended,cancelled',
        ]);

        if ($data['status'] === 'active' && ! $tenant->hasValidSubscription()) {
            return back()->withErrors([
                'status' => 'An unexpired paid or trial subscription is required before activating this tenant.',
            ]);
        }

        $oldValues = ['status' => $tenant->status];
        DB::transaction(function () use ($request, $tenant, $data, $oldValues): void {
            $tenant->update(['status' => $data['status']]);
            $this->recordPlatformAudit($request, 'platform.tenant.status_changed', $tenant, $oldValues, [
                'status' => $tenant->status,
            ], $tenant->id);
        });

        return back()->with('success', 'Tenant status updated.');
    }

    /**
     * Approve or reject a newly submitted institution registration.
     */
    public function reviewRegistration(Request $request, Tenant $tenant)
    {
        abort_unless($tenant->registration_status === 'pending', 409, 'This registration has already been reviewed.');

        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
        ]);

        $reviewedAt = now();

        if ($data['decision'] === 'reject') {
            DB::transaction(function () use ($request, $tenant, $reviewedAt): void {
                $oldValues = ['registration_status' => $tenant->registration_status];
                $tenant->update([
                    'registration_status' => 'rejected',
                    'registration_reviewed_by' => $request->user()->id,
                    'registration_reviewed_at' => $reviewedAt,
                ]);
                $this->recordPlatformAudit($request, 'platform.tenant.registration_rejected', $tenant, $oldValues, [
                    'registration_status' => $tenant->registration_status,
                ], $tenant->id);
            });

            return back()->with('success', 'Institution registration rejected.');
        }

        $plan = SubscriptionPlan::query()
            ->whereKey($tenant->subscription_plan_id)
            ->where('is_active', true)
            ->first()
            ?? SubscriptionPlan::query()->where('is_active', true)->orderBy('id')->first();

        if (! $plan) {
            return back()->withErrors([
                'registration' => 'Create or activate a subscription plan before approving registrations.',
            ]);
        }

        $trialEndsAt = $reviewedAt->copy()->addDays(14);

        DB::transaction(function () use ($request, $tenant, $plan, $reviewedAt, $trialEndsAt): void {
            $oldValues = [
                'registration_status' => $tenant->registration_status,
                'status' => $tenant->status,
                'subscription_plan_id' => $tenant->subscription_plan_id,
            ];
            $tenant->update([
                'registration_status' => 'approved',
                'registration_reviewed_by' => $request->user()->id,
                'registration_reviewed_at' => $reviewedAt,
                'status' => 'trial',
                'trial_started_at' => $reviewedAt,
                'trial_ends_at' => $trialEndsAt,
                'subscription_plan_id' => $plan->id,
                'subscription_limits' => [
                    'max_branches' => $plan->max_branches,
                    'max_users' => $plan->max_users,
                    'max_students' => $plan->max_students,
                ],
            ]);

            $tenant->subscriptions()->create([
                'subscription_plan_id' => $plan->id,
                'status' => 'trial',
                'start_date' => $reviewedAt->toDateString(),
                'end_date' => $trialEndsAt->toDateString(),
                'payment_status' => 'not_required',
            ]);
            app(ApprovedTenantMasterSeeder::class)->seedFor($tenant);
            $this->recordPlatformAudit($request, 'platform.tenant.registration_approved', $tenant, $oldValues, [
                'registration_status' => $tenant->registration_status,
                'status' => $tenant->status,
                'subscription_plan_id' => $plan->id,
                'trial_ends_at' => $trialEndsAt->toDateString(),
            ], $tenant->id);
        });

        return back()->with('success', 'Institution approved. The 14-day trial has started and standard setup masters are ready for the tenant to review.');
    }

    public function reviewSubscription(Request $request, Tenant $tenant, Subscription $subscription)
    {
        abort_unless((int) $subscription->tenant_id === (int) $tenant->id, 404);

        $data = $request->validate([
            'decision' => 'required|in:approve,reject',
        ]);
        abort_unless($subscription->payment_status === 'renewal_requested', 404);

        if ($data['decision'] === 'reject') {
            DB::transaction(function () use ($request, $tenant, $subscription): void {
                $oldValues = ['status' => $subscription->status, 'payment_status' => $subscription->payment_status];
                $subscription->update([
                    'status' => 'expired',
                    'payment_status' => 'rejected',
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);
                $this->recordPlatformAudit($request, 'platform.subscription.payment_rejected', $subscription, $oldValues, [
                    'status' => $subscription->status,
                    'payment_status' => $subscription->payment_status,
                    'payment_reference' => $subscription->payment_reference,
                ], $tenant->id);
            });

            return back()->with('success', 'Renewal request rejected.');
        }

        $plan = SubscriptionPlan::query()
            ->whereKey($subscription->subscription_plan_id)
            ->where('is_active', true)
            ->firstOrFail();
        $current = $tenant->currentSubscription();
        $start = $current && $current->end_date->gte(today())
            ? $current->end_date->copy()->addDay()
            : today();
        $interval = $subscription->billing_interval === 'yearly' ? 'yearly' : 'monthly';
        $end = $interval === 'yearly'
            ? $start->copy()->addYearNoOverflow()->subDay()
            : $start->copy()->addMonthNoOverflow()->subDay();
        $amount = $interval === 'yearly' ? $plan->yearly_price : $plan->monthly_price;

        // Keep subscription activation and tenant plan changes atomic.
        DB::transaction(function () use ($subscription, $tenant, $plan, $start, $end, $amount, $request): void {
            $oldValues = [
                'status' => $subscription->status,
                'payment_status' => $subscription->payment_status,
                'subscription_plan_id' => $subscription->subscription_plan_id,
                'end_date' => $subscription->end_date?->toDateString(),
            ];
            $subscription->update([
                'status' => 'active',
                'payment_status' => 'paid',
                'subscription_plan_id' => $plan->id,
                'start_date' => $start,
                'end_date' => $end,
                'renewal_date' => $start,
                'amount_due' => $amount,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]);
            $tenant->update([
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'subscription_limits' => [
                    'max_branches' => $plan->max_branches,
                    'max_users' => $plan->max_users,
                    'max_students' => $plan->max_students,
                ],
            ]);
            $this->recordPlatformAudit($request, 'platform.subscription.payment_approved', $subscription, $oldValues, [
                'status' => $subscription->status,
                'payment_status' => $subscription->payment_status,
                'subscription_plan_id' => $plan->id,
                'amount_due' => $amount,
                'end_date' => $end->toDateString(),
            ], $tenant->id);
        });

        return back()->with('success', 'Subscription renewed through '.$end->format('d M Y').'.');
    }

    public function plan(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'required|numeric|min:0',
            'max_branches' => 'nullable|integer|min:1',
            'max_users' => 'nullable|integer|min:1',
            'max_students' => 'nullable|integer|min:1',
        ]);

        DB::transaction(function () use ($request, $data): void {
            $plan = SubscriptionPlan::create($data + ['is_active' => true]);
            $this->recordPlatformAudit($request, 'platform.plan.created', $plan, [], [
                'name' => $plan->name,
                'monthly_price' => $plan->monthly_price,
                'yearly_price' => $plan->yearly_price,
                'max_branches' => $plan->max_branches,
                'max_users' => $plan->max_users,
                'max_students' => $plan->max_students,
                'is_active' => $plan->is_active,
            ]);
        });

        return back()->with('success', 'Subscription plan created.');
    }

    /** Update pricing, limits, or availability for an existing plan. */
    public function updatePlan(Request $request, SubscriptionPlan $plan)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120|unique:subscription_plans,name,'.$plan->id,
            'monthly_price' => 'required|numeric|min:0',
            'yearly_price' => 'required|numeric|min:0',
            'max_branches' => 'nullable|integer|min:1',
            'max_users' => 'nullable|integer|min:1',
            'max_students' => 'nullable|integer|min:1',
            'is_active' => 'required|boolean',
        ]);

        $oldValues = $plan->only(array_keys($data));
        DB::transaction(function () use ($request, $plan, $data, $oldValues): void {
            $plan->update($data);
            $this->recordPlatformAudit($request, 'platform.plan.updated', $plan, $oldValues, $plan->only(array_keys($data)));
        });

        return back()->with('success', 'Subscription plan updated.');
    }
}
