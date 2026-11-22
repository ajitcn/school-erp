@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="WhatsApp credits"
        description="Buy message credits, check your balance, and review each WhatsApp delivery charged to your institution."
    />

    <x-admin.alert />
    <x-admin.validation-errors />

    <section class="platform-summary" aria-label="WhatsApp credit balance summary">
        <x-admin.stat-card label="Available credits" :value="number_format($walletBalance)" detail="Ready for WhatsApp messages" icon="fa-message" />
        <x-admin.stat-card label="Credits purchased" :value="number_format($purchasedCredits)" detail="Successful top-ups" tone="success" icon="fa-cart-shopping" />
        <x-admin.stat-card label="Net credits used" :value="number_format($usedCredits)" detail="Failed deliveries are refunded" tone="warning" icon="fa-paper-plane" />
        <x-admin.stat-card label="Packages" :value="number_format($packs->count())" detail="Currently available to buy" tone="info" icon="fa-box-open" />
    </section>

    <div class="alert alert-info mt-4" role="note">
        <strong>How usage is counted:</strong> one credit is reserved for each WhatsApp message before it is queued. If delivery fails after all retries, the credit is returned automatically. SMS and in-app notifications do not use WhatsApp credits.
    </div>
    @unless ($paymentConfigured)
        <div class="alert alert-warning" role="status">Online checkout is not configured yet. Contact the platform administrator before buying credits.</div>
    @endunless

    <x-admin.card class="mt-4">
        <div class="d-flex align-items-start justify-content-between flex-wrap mb-3">
            <div>
                <h2 class="h5 mb-1">Buy credits</h2>
                <p class="text-muted mb-0">Payments are verified through Razorpay before credits are added.</p>
            </div>
        </div>
        @if ($packs->isEmpty())
            <x-admin.empty-state title="No credit packages are available" description="The platform administrator has not published WhatsApp credit packages yet." />
        @else
            <div class="row">
                @foreach ($packs as $pack)
                    <div class="col-md-6 col-xl-4 mb-3">
                        <article class="admin-card h-100 p-3">
                            <div class="d-flex align-items-start justify-content-between">
                                <div><span class="text-muted small">{{ $pack->name }}</span><strong class="d-block h3 mb-1">{{ number_format($pack->credits) }} credits</strong></div>
                                <span class="portal-summary-icon"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></span>
                            </div>
                            <p class="text-muted mb-3">{{ $currency }} {{ number_format($pack->price, 2) }} · {{ number_format($pack->price / $pack->credits, 2) }} per credit</p>
                            <form method="post" action="{{ route('whatsapp-credits.checkout', $pack) }}">
                                @csrf
                                <button class="btn btn-primary btn-block" type="submit" @disabled(! $paymentConfigured)>Buy this package</button>
                            </form>
                        </article>
                    </div>
                @endforeach
            </div>
        @endif
    </x-admin.card>

    @if ($purchases->isNotEmpty())
        <x-admin.card class="mt-4">
            <h2 class="h5 mb-3">Recent purchases</h2>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Package</th><th>Credits</th><th>Amount</th><th>Date</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($purchases as $purchase)
                            <tr>
                                <td>{{ $purchase->pack_name }}</td>
                                <td>{{ number_format($purchase->credits) }}</td>
                                <td>{{ $purchase->currency }} {{ number_format($purchase->amount, 2) }}</td>
                                <td>{{ $purchase->created_at->format('d M Y, h:i A') }}</td>
                                <td><x-admin.badge :tone="$purchase->status === 'paid' ? 'success' : ($purchase->status === 'checkout_created' ? 'warning' : 'slate')">{{ str($purchase->status)->replace('_', ' ')->title() }}</x-admin.badge></td>
                                <td class="text-right">
                                    @if ($purchase->status === 'checkout_created' && $purchase->gateway_order_id)
                                        <a class="btn btn-sm btn-outline-primary" href="{{ route('whatsapp-credits.resume', $purchase) }}">Resume payment</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-admin.card>
    @endif

    <x-admin.card class="mt-4">
        <div class="d-flex align-items-end justify-content-between flex-wrap mb-3">
            <div><h2 class="h5 mb-1">Credit activity</h2><p class="text-muted mb-0">A transaction is recorded for every purchase, message use, and delivery refund.</p></div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Date and time</th><th>Activity</th><th>Details</th><th class="text-right">Credits</th></tr></thead>
                <tbody>
                    @forelse ($ledger as $entry)
                        <tr>
                            <td>{{ $entry->created_at->format('d M Y, h:i A') }}</td>
                            <td><x-admin.badge :tone="$entry->type === 'purchase' ? 'success' : ($entry->type === 'usage' ? 'info' : 'warning')">{{ ucfirst($entry->type) }}</x-admin.badge></td>
                            <td>{{ $entry->description }}@if ($entry->notificationLog)<small class="d-block text-muted">{{ str($entry->notificationLog->event)->replace('.', ' ')->title() }} · {{ $entry->notificationLog->status }}</small>@endif</td>
                            <td class="text-right font-weight-bold {{ $entry->credits < 0 ? 'text-danger' : 'text-success' }}">{{ $entry->credits > 0 ? '+' : '' }}{{ number_format($entry->credits) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-admin.empty-state title="No credit activity yet" description="Purchases and WhatsApp usage will appear here." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-3"><x-admin.pagination :paginator="$ledger" /></div>
    </x-admin.card>
@endsection
