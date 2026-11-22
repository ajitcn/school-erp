@extends('layouts.app')
@section('content')
<x-admin.page-header title="Complete subscription payment" description="Pay securely to renew your institution subscription." />
<x-admin.card class="max-w-xl"><h2 class="h5">{{ $plan->name }} · {{ ucfirst($subscription->billing_interval) }}</h2><p class="mb-1">Amount: <strong>{{ $currency }} {{ number_format($subscription->amount_due, 2) }}</strong></p><p class="text-muted">Your renewal activates after the payment provider confirms a captured payment.</p><button id="pay-now" class="btn btn-primary">Continue to secure payment</button><a href="{{ route('billing.index') }}" class="btn btn-outline-secondary ml-2">Cancel</a><div id="payment-error" class="alert alert-danger mt-3 d-none"></div></x-admin.card>
<form id="verify-form" method="post" action="{{ route('billing.checkout.verify') }}" class="d-none">@csrf<input name="subscription_id" value="{{ $subscription->id }}"><input name="razorpay_order_id"><input name="razorpay_payment_id"><input name="razorpay_signature"></form>
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script>
document.getElementById('pay-now').addEventListener('click', function () {
    const checkout = new Razorpay({
        key: @json($key), order_id: @json($gatewayOrder['id']), amount: @json($gatewayOrder['amount']), currency: @json($currency),
        name: @json($tenant->institution_name), description: @json($plan->name.' subscription'),
        handler: function (result) {
            const form = document.getElementById('verify-form');
            ['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature'].forEach((name) => form.elements[name].value = result[name]);
            form.submit();
        },
        modal: { ondismiss: function () {} }
    });
    checkout.on('payment.failed', function (result) {
        const error = document.getElementById('payment-error');
        error.textContent = result.error.description || 'Payment did not complete. You can retry from billing.';
        error.classList.remove('d-none');
    });
    checkout.open();
});
</script>
@endsection
