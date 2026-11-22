<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantWhatsAppCreditLedger;
use App\Models\TenantWhatsAppCreditPurchase;
use App\Models\WhatsAppCreditPack;
use App\Services\PlatformConfigurationService;
use App\Services\TenantContext;
use App\Services\WhatsAppCreditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Lets tenant owners buy message credits and review their WhatsApp usage. */
class TenantWhatsAppCreditController extends Controller
{
    /** Show current balance, available packages, purchases, and usage history. */
    public function index(Request $request, WhatsAppCreditService $creditService, PlatformConfigurationService $configuration): View
    {
        $tenant = app(TenantContext::class)->get();
        abort_unless($tenant, 403);
        $this->authorizeManager($request, (int) $tenant->id);

        $razorpaySettings = $configuration->razorpaySettings();
        $currency = $razorpaySettings['currency'];
        $paymentConfigured = filled($razorpaySettings['key']) && filled($razorpaySettings['secret']);
        $walletBalance = $creditService->balance((int) $tenant->id);
        $purchasedCredits = (int) TenantWhatsAppCreditLedger::query()
            ->where('type', 'purchase')
            ->sum('credits');
        $usedCredits = (int) abs(TenantWhatsAppCreditLedger::query()
            ->whereIn('type', ['usage', 'refund'])
            ->sum('credits'));
        $packs = WhatsAppCreditPack::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('credits')
            ->get();
        $ledger = TenantWhatsAppCreditLedger::query()
            ->with(['purchase', 'notificationLog.user', 'creator'])
            ->latest()
            ->paginate(20)
            ->withQueryString();
        $purchases = TenantWhatsAppCreditPurchase::query()
            ->latest()
            ->limit(5)
            ->get();

        return view('whatsapp-credits.index', compact(
            'tenant',
            'currency',
            'paymentConfigured',
            'walletBalance',
            'purchasedCredits',
            'usedCredits',
            'packs',
            'ledger',
            'purchases',
        ));
    }

    /** Create a Razorpay order for a selected platform package. */
    public function checkout(Request $request, int $pack, PlatformConfigurationService $configuration): View|RedirectResponse
    {
        $tenant = app(TenantContext::class)->get();
        abort_unless($tenant, 403);
        $this->authorizeManager($request, (int) $tenant->id);

        $creditPack = WhatsAppCreditPack::query()->whereKey($pack)->where('is_active', true)->firstOrFail();
        $razorpay = $configuration->razorpaySettings();
        abort_unless($razorpay['key'] && $razorpay['secret'], 503, 'Online payments are not configured. Contact the platform administrator.');

        $purchase = TenantWhatsAppCreditPurchase::query()->create([
            'tenant_id' => $tenant->id,
            'pack_id' => $creditPack->id,
            'purchased_by' => $request->user()->id,
            'pack_name' => $creditPack->name,
            'credits' => $creditPack->credits,
            'amount' => $creditPack->price,
            'currency' => $razorpay['currency'],
            'status' => 'checkout_created',
        ]);

        $response = Http::withBasicAuth($razorpay['key'], $razorpay['secret'])
            ->acceptJson()
            ->timeout(20)
            ->post('https://api.razorpay.com/v1/orders', [
                'amount' => (int) round(((float) $purchase->amount) * 100),
                'currency' => $purchase->currency,
                'receipt' => 'wa-'.$tenant->id.'-'.Str::lower(Str::random(16)),
                'notes' => [
                    'tenant_id' => (string) $tenant->id,
                    'purchase_id' => (string) $purchase->id,
                    'credits' => (string) $purchase->credits,
                ],
            ]);

        if (! $response->successful() || ! $response->json('id')) {
            $purchase->update(['status' => 'failed']);
            Log::error('Razorpay WhatsApp credit order creation failed.', [
                'tenant_id' => $tenant->id,
                'purchase_id' => $purchase->id,
                'http_status' => $response->status(),
            ]);

            return redirect()->route('whatsapp-credits.index')
                ->withErrors(['payment' => 'The payment provider could not create an order. Please try again.']);
        }

        $purchase->update(['gateway_order_id' => $response->json('id')]);

        return $this->checkoutView($tenant, $purchase, $response->json(), $razorpay);
    }

    /** Resume an unfinished payment without creating a second order. */
    public function resume(Request $request, int $purchase, PlatformConfigurationService $configuration): View
    {
        $tenant = app(TenantContext::class)->get();
        abort_unless($tenant, 403);
        $this->authorizeManager($request, (int) $tenant->id);

        $creditPurchase = $tenant->whatsappCreditPurchases()
            ->whereKey($purchase)
            ->where('status', 'checkout_created')
            ->whereNotNull('gateway_order_id')
            ->firstOrFail();
        $razorpay = $configuration->razorpaySettings();

        return $this->checkoutView(
            $tenant,
            $creditPurchase,
            ['id' => $creditPurchase->gateway_order_id, 'amount' => (int) round(((float) $creditPurchase->amount) * 100)],
            $razorpay,
        );
    }

    /** Verify payment with Razorpay before crediting the tenant's wallet. */
    public function verify(Request $request, PlatformConfigurationService $configuration, WhatsAppCreditService $creditService): RedirectResponse
    {
        $data = $request->validate([
            'purchase_id' => ['required', 'integer'],
            'razorpay_order_id' => ['required', 'string', 'max:100'],
            'razorpay_payment_id' => ['required', 'string', 'max:100'],
            'razorpay_signature' => ['required', 'string', 'max:255'],
        ]);

        $tenant = app(TenantContext::class)->get();
        abort_unless($tenant, 403);
        $this->authorizeManager($request, (int) $tenant->id);

        $purchase = $tenant->whatsappCreditPurchases()
            ->whereKey($data['purchase_id'])
            ->where('gateway_order_id', $data['razorpay_order_id'])
            ->firstOrFail();

        if ($purchase->status === 'paid') {
            return redirect()->route('whatsapp-credits.index')->with('success', 'Payment was already confirmed and the credits are available.');
        }

        abort_unless($purchase->status === 'checkout_created', 409);

        $razorpay = $configuration->razorpaySettings();
        $expectedSignature = hash_hmac(
            'sha256',
            $data['razorpay_order_id'].'|'.$data['razorpay_payment_id'],
            (string) $razorpay['secret'],
        );
        abort_unless(hash_equals($expectedSignature, $data['razorpay_signature']), 422, 'The payment signature could not be verified.');

        $paymentResponse = Http::withBasicAuth($razorpay['key'], $razorpay['secret'])
            ->acceptJson()
            ->timeout(20)
            ->get('https://api.razorpay.com/v1/payments/'.rawurlencode($data['razorpay_payment_id']));
        $payment = $paymentResponse->json();

        if (! $paymentResponse->successful()
            || ($payment['status'] ?? null) !== 'captured'
            || ($payment['order_id'] ?? null) !== $purchase->gateway_order_id
            || (int) ($payment['amount'] ?? 0) !== (int) round(((float) $purchase->amount) * 100)
            || ($payment['currency'] ?? null) !== $purchase->currency) {
            return redirect()->route('whatsapp-credits.index')
                ->withErrors(['payment' => 'The payment is not captured or does not match this credit purchase. You can resume checkout from the purchase history.']);
        }

        DB::transaction(function () use ($purchase, $data, $creditService): void {
            $lockedPurchase = TenantWhatsAppCreditPurchase::query()
                ->whereKey($purchase->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPurchase->status === 'paid') {
                return;
            }

            $lockedPurchase->update([
                'status' => 'paid',
                'gateway_payment_id' => $data['razorpay_payment_id'],
                'paid_at' => now(),
            ]);
            $creditService->creditPurchase($lockedPurchase);
        });

        return redirect()->route('whatsapp-credits.index')
            ->with('success', number_format($purchase->credits).' WhatsApp credits were added to the tenant wallet.');
    }

    /** Render the shared Razorpay checkout view. */
    private function checkoutView(Tenant $tenant, TenantWhatsAppCreditPurchase $purchase, array $gatewayOrder, array $razorpay): View
    {
        return view('whatsapp-credits.checkout', [
            'tenant' => $tenant,
            'purchase' => $purchase,
            'gatewayOrder' => $gatewayOrder,
            'key' => $razorpay['key'],
            'currency' => $purchase->currency,
        ]);
    }

    /** Limit credit purchase and usage pages to tenant owners and administrators. */
    private function authorizeManager(Request $request, int $tenantId): void
    {
        abort_unless($request->user()->isTenantAdmin($tenantId), 403);
    }
}
