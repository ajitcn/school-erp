<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\PlatformConfigurationService;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Presents subscription history and processes manual or Razorpay renewals.
 */
class SubscriptionController extends Controller
{
    /** Display tenant subscription status, paid renewal plans, and billing history. */
    public function index(Request $request)
    {
        $tenant = app(TenantContext::class)->get();
        abort_unless($tenant, 403);
        $membership = $request->user()->tenantMembership($tenant->id);

        return view('billing.index', [
            'tenant' => $tenant->load('plan'),
            'current' => $tenant->currentSubscription(),
            'subscriptions' => $tenant->subscriptions()->with('plan', 'requester', 'approver')->latest()->get(),
            'plans' => SubscriptionPlan::query()
                ->where('is_active', true)
                ->where('monthly_price', '>', 0)
                ->where('yearly_price', '>', 0)
                ->orderBy('monthly_price')
                ->get(),
            'canManage' => in_array($membership?->role, ['owner', 'administrator'], true)
                || $request->user()->hasRole('super_admin'),
        ]);
    }

    public function requestRenewal(Request $request)
    {
        $tenant = app(TenantContext::class)->get();
        abort_unless($tenant, 403);
        $membership = $request->user()->tenantMembership($tenant->id);
        abort_unless(in_array($membership?->role, ['owner', 'administrator'], true) || $request->user()->hasRole('super_admin'), 403);
        if ($tenant->subscriptions()->whereIn('payment_status', ['renewal_requested', 'checkout_created'])->exists()) {
            return back()->withErrors(['subscription' => 'A renewal payment or request is already in progress.']);
        }
        $data = $request->validate(['subscription_plan_id' => 'required|integer', 'billing_interval' => 'required|in:monthly,yearly', 'payment_reference' => 'required|string|max:150']);
        $plan = SubscriptionPlan::query()->whereKey($data['subscription_plan_id'])->where('is_active', true)->firstOrFail();
        $amount = (float) ($data['billing_interval'] === 'yearly' ? $plan->yearly_price : $plan->monthly_price);
        if ($amount <= 0) {
            return back()
                ->withErrors(['subscription_plan_id' => 'Choose a paid plan and billing term for renewal.'])
                ->withInput();
        }

        $current = $tenant->currentSubscription();
        $start = $current && $current->end_date->gte(today()) ? $current->end_date->copy()->addDay() : today();
        $end = $data['billing_interval'] === 'yearly' ? $start->copy()->addYearNoOverflow()->subDay() : $start->copy()->addMonthNoOverflow()->subDay();
        Subscription::create(['tenant_id' => $tenant->id, 'subscription_plan_id' => $plan
            ->id, 'status' => 'trial', 'start_date' => $start, 'end_date' => $end, 'renewal_date' => $start, 'billing_interval' => $data['billing_interval'], 'amount_due' => $amount, 'payment_reference' => $data['payment_reference'], 'payment_status' => 'renewal_requested', 'requested_by' => $request
            ->user()
            ->id]);

        return back()->with('success', 'Renewal request submitted. Access will activate after platform review.');
    }

    public function checkout(Request $request, PlatformConfigurationService $platformConfiguration)
    {
        $tenant = app(TenantContext::class)->get();
        abort_unless($tenant, 403);
        $membership = $request->user()->tenantMembership($tenant->id);
        abort_unless(in_array($membership?->role, ['owner', 'administrator'], true) || $request->user()->hasRole('super_admin'), 403);
        abort_if($tenant->subscriptions()->whereIn('payment_status', ['renewal_requested', 'checkout_created'])->exists(), 409, 'A renewal payment is already in progress.');
        $data = $request->validate(['subscription_plan_id' => 'required|integer', 'billing_interval' => 'required|in:monthly,yearly']);
        $plan = SubscriptionPlan::query()->whereKey($data['subscription_plan_id'])->where('is_active', true)->firstOrFail();
        $amount = (float) ($data['billing_interval'] === 'yearly' ? $plan->yearly_price : $plan->monthly_price);
        abort_if($amount <= 0, 422, 'This plan does not require an online payment.');
        $razorpay = $platformConfiguration->razorpaySettings();
        $key = $razorpay['key'];
        $secret = $razorpay['secret'];
        abort_unless($key && $secret, 503, 'Online payments are not configured. Contact the platform administrator.');

        $response = Http::withBasicAuth($key, $secret)->acceptJson()->timeout(20)->post('https://api.razorpay.com/v1/orders', [
            'amount' => (int) round($amount * 100), 'currency' => $razorpay['currency'],
            'receipt' => 'tenant-'.$tenant->id.'-'.substr((string) now()->timestamp, -10),
            'notes' => ['tenant_id' => (string) $tenant->id, 'plan_id' => (string) $plan->id, 'billing_interval' => $data['billing_interval']],
        ]);
        if (! $response->successful() || ! $response->json('id')) {
            Log::error('Razorpay order creation failed', ['tenant_id' => $tenant->id, 'http_status' => $response->status()]);

            return back()->withErrors(['payment' => 'The payment provider could not create an order. Please try again later.']);
        }

        $current = $tenant->currentSubscription();
        $start = $current && $current->end_date->gte(today()) ? $current->end_date->copy()->addDay() : today();
        // Persist the provider order before rendering checkout so the customer can resume it.
        $subscription = Subscription::create([
            'tenant_id' => $tenant->id, 'subscription_plan_id' => $plan->id, 'status' => 'trial',
            'start_date' => $start, 'end_date' => $start, 'renewal_date' => $start,
            'billing_interval' => $data['billing_interval'], 'amount_due' => $amount,
            'payment_status' => 'checkout_created', 'requested_by' => $request->user()->id,
            'gateway_order_id' => $response->json('id'),
        ]);

        return $this->checkoutView($tenant, $subscription, $response->json(), $razorpay);
    }

    public function resumeCheckout(Request $request, Subscription $subscription, PlatformConfigurationService $platformConfiguration)
    {
        $tenant = app(TenantContext::class)->get();
        abort_unless($tenant && (int) $subscription->tenant_id === (int) $tenant->id, 404);
        $membership = $request->user()->tenantMembership($tenant->id);
        abort_unless(in_array($membership?->role, ['owner', 'administrator'], true) || $request->user()->hasRole('super_admin'), 403);
        abort_unless($subscription->payment_status === 'checkout_created' && $subscription->gateway_order_id, 404);

        return $this->checkoutView(
            $tenant,
            $subscription,
            ['id' => $subscription->gateway_order_id, 'amount' => (int) round(((float) $subscription->amount_due) * 100)],
            $platformConfiguration->razorpaySettings(),
        );
    }

    private function checkoutView(Tenant $tenant, Subscription $subscription, array $gatewayOrder, array $razorpay)
    {
        return view('billing.checkout', [
            'tenant' => $tenant, 'plan' => $subscription->plan, 'subscription' => $subscription,
            'gatewayOrder' => $gatewayOrder, 'key' => $razorpay['key'],
            'currency' => $razorpay['currency'],
        ]);
    }

    public function verifyCheckout(Request $request, PlatformConfigurationService $platformConfiguration)
    {
        $data = $request->validate([
            'subscription_id' => 'required|integer', 'razorpay_order_id' => 'required|string|max:100',
            'razorpay_payment_id' => 'required|string|max:100', 'razorpay_signature' => 'required|string|max:255',
        ]);
        $tenant = app(TenantContext::class)->get();
        abort_unless($tenant, 403);
        $membership = $request->user()->tenantMembership($tenant->id);
        abort_unless(in_array($membership?->role, ['owner', 'administrator'], true) || $request->user()->hasRole('super_admin'), 403);
        $subscription = $tenant->subscriptions()->whereKey($data['subscription_id'])->where('gateway_order_id', $data['razorpay_order_id'])->firstOrFail();
        if ($subscription->payment_status === 'paid') {
            return redirect()->route('billing.index')->with('success', 'Payment was already confirmed.');
        }
        abort_unless($subscription->payment_status === 'checkout_created', 409);
        // Verify provider capture and amount independently; never trust the browser callback alone.
        $razorpay = $platformConfiguration->razorpaySettings();
        $expected = hash_hmac('sha256', $data['razorpay_order_id'].'|'.$data['razorpay_payment_id'], (string) $razorpay['secret']);
        abort_unless(hash_equals($expected, $data['razorpay_signature']), 422, 'The payment signature could not be verified.');

        $paymentResponse = Http::withBasicAuth($razorpay['key'], $razorpay['secret'])->acceptJson()
            ->timeout(20)
            ->get('https://api.razorpay.com/v1/payments/'.rawurlencode($data['razorpay_payment_id']));
        $payment = $paymentResponse->json();
        if (! $paymentResponse->successful() || ($payment['status'] ?? null) !== 'captured'
            || ($payment['order_id'] ?? null) !== $subscription->gateway_order_id
            || (int) ($payment['amount'] ?? 0) !== (int) round(((float) $subscription->amount_due) * 100)
            || ($payment['currency'] ?? null) !== $razorpay['currency']) {
            return redirect()->route('billing.index')
                ->withErrors(['payment' => 'Payment is not captured or does not match this subscription. You can resume the checkout from the billing page.']);
        }

        $plan = SubscriptionPlan::query()->whereKey($subscription->subscription_plan_id)->where('is_active', true)->firstOrFail();
        $current = $tenant->currentSubscription();
        $start = $current && $current->id !== $subscription->id && $current->end_date->gte(today()) ? $current->end_date->copy()->addDay() : today();
        $interval = $subscription->billing_interval === 'yearly' ? 'yearly' : 'monthly';
        $end = $interval === 'yearly' ? $start->copy()->addYearNoOverflow()->subDay() : $start->copy()->addMonthNoOverflow()->subDay();
        DB::transaction(function () use ($subscription, $tenant, $plan, $start, $end, $data, $request): void {
            $subscription->update(['status' => 'active', 'payment_status' => 'paid', 'payment_reference' => $data['razorpay_payment_id'], 'gateway_payment_id' => $data['razorpay_payment_id'], 'start_date' => $start, 'end_date' => $end, 'renewal_date' => $start, 'approved_by' => $request
                ->user()
                ->id, 'approved_at' => now()]);
            $tenant->update(['subscription_plan_id' => $plan->id, 'status' => 'active']);
        });

        return redirect()->route('billing.index')->with('success', 'Subscription payment confirmed through '.$end->format('d M Y').'.');
    }
}
