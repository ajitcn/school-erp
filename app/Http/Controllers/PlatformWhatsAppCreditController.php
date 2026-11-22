<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\TenantWhatsAppCreditLedger;
use App\Models\WhatsAppCreditPack;
use App\Services\PlatformConfigurationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Configures WhatsApp credit packages and monitors cross-tenant usage. */
class PlatformWhatsAppCreditController extends Controller
{
    /** Show the platform-managed WhatsApp credit packages. */
    public function index(PlatformConfigurationService $configuration): View
    {
        $packs = WhatsAppCreditPack::query()->orderBy('sort_order')->orderBy('credits')->get();

        return view('platform.whatsapp-credits.packages', [
            'packs' => $packs,
            'currency' => $configuration->razorpaySettings()['currency'],
            'summary' => $this->creditSummary(),
        ]);
    }

    /** Show each tenant's current WhatsApp wallet balance. */
    public function tenants(): View
    {
        $tenants = Tenant::query()
            ->with('whatsappCreditWallet')
            ->withCount('students')
            ->orderBy('institution_name')
            ->paginate(20, ['*'], 'tenants_page')
            ->withQueryString();

        return view('platform.whatsapp-credits.tenants', [
            'tenants' => $tenants,
            'summary' => $this->creditSummary(),
        ]);
    }

    /** Show and filter the purchase, message usage, and refund ledger. */
    public function activity(Request $request): View
    {
        $tenantFilter = $request->integer('tenant_id') ?: null;
        $typeFilter = $request->string('type')->toString();
        $tenantOptions = Tenant::query()->orderBy('institution_name')->get(['id', 'institution_name']);
        $ledger = TenantWhatsAppCreditLedger::withoutGlobalScopes()
            ->with(['tenant', 'purchase', 'notificationLog.user', 'creator'])
            ->when($tenantFilter, fn ($query) => $query->where('tenant_id', $tenantFilter))
            ->when(in_array($typeFilter, ['purchase', 'usage', 'refund'], true), fn ($query) => $query->where('type', $typeFilter))
            ->latest()
            ->paginate(30, ['*'], 'ledger_page')
            ->withQueryString();

        return view('platform.whatsapp-credits.activity', [
            'ledger' => $ledger,
            'tenantOptions' => $tenantOptions,
            'tenantFilter' => $tenantFilter,
            'typeFilter' => $typeFilter,
            'summary' => $this->creditSummary(),
        ]);
    }

    /** Create a package at the price set by the platform administrator. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', 'unique:whatsapp_credit_packs,name'],
            'credits' => ['required', 'integer', 'between:1,1000000'],
            'price' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'sort_order' => ['nullable', 'integer', 'between:0,65535'],
        ]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        WhatsAppCreditPack::query()->create($data + ['is_active' => true]);

        return back()->with('success', 'WhatsApp credit package created.');
    }

    /** Update package details while preserving past purchase snapshots. */
    public function update(Request $request, int $pack): RedirectResponse
    {
        $creditPack = WhatsAppCreditPack::query()->findOrFail($pack);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('whatsapp_credit_packs', 'name')->ignore($creditPack->id)],
            'credits' => ['required', 'integer', 'between:1,1000000'],
            'price' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'sort_order' => ['nullable', 'integer', 'between:0,65535'],
        ]);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        $creditPack->update($data);

        return back()->with('success', 'WhatsApp credit package updated.');
    }

    /** Enable or pause new purchases for a package. */
    public function toggle(int $pack): RedirectResponse
    {
        $creditPack = WhatsAppCreditPack::query()->findOrFail($pack);
        $creditPack->update(['is_active' => ! $creditPack->is_active]);

        return back()->with('success', 'WhatsApp credit package status updated.');
    }

    /** Summarize balances and net credit consumption for the platform header. */
    private function creditSummary(): array
    {
        return [
            'wallets' => DB::table('tenant_whatsapp_credit_wallets')->count(),
            'balance' => (int) DB::table('tenant_whatsapp_credit_wallets')->sum('credits_balance'),
            'purchased' => (int) DB::table('tenant_whatsapp_credit_ledger')->where('type', 'purchase')->sum('credits'),
            'used' => (int) abs(DB::table('tenant_whatsapp_credit_ledger')->whereIn('type', ['usage', 'refund'])->sum('credits')),
        ];
    }
}
