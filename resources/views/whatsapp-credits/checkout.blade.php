@extends('layouts.app')

@section('content')
    <x-admin.page-header title="Complete WhatsApp credit purchase" description="Pay securely to add the selected message credits to your institution wallet." />
    <x-admin.card class="max-w-xl">
        <span class="text-muted small">{{ $purchase->pack_name }}</span>
        <h2 class="h4">{{ number_format($purchase->credits) }} WhatsApp credits</h2>
        <p>Amount: <strong>{{ $currency }} {{ number_format($purchase->amount, 2) }}</strong></p>
        <p class="text-muted">Credits become available only after Razorpay confirms the payment as captured.</p>
        <button id="pay-now" class="btn btn-primary" type="button">Continue to secure payment</button>
        <a href="{{ route('whatsapp-credits.index') }}" class="btn btn-outline-secondary ml-2">Cancel</a>
        <div id="payment-error" class="alert alert-danger mt-3 d-none" role="alert"></div>
    </x-admin.card>

    <form id="verify-form" method="post" action="{{ route('whatsapp-credits.verify') }}" class="d-none">
        @csrf
        <input name="purchase_id" value="{{ $purchase->id }}">
        <input name="razorpay_order_id">
        <input name="razorpay_payment_id">
        <input name="razorpay_signature">
    </form>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        document.getElementById('pay-now').addEventListener('click', () => {
            const checkout = new Razorpay({
                key: @json($key),
                order_id: @json($gatewayOrder['id']),
                amount: @json($gatewayOrder['amount']),
                currency: @json($currency),
                name: @json($tenant->institution_name),
                description: @json($purchase->pack_name.' WhatsApp credits'),
                handler: (result) => {
                    const form = document.getElementById('verify-form');
                    ['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature'].forEach((name) => {
                        form.elements[name].value = result[name];
                    });
                    form.submit();
                },
                modal: { ondismiss: () => {} }
            });
            checkout.on('payment.failed', (result) => {
                const error = document.getElementById('payment-error');
                error.textContent = result.error.description || 'Payment did not complete. You can retry from WhatsApp credit activity.';
                error.classList.remove('d-none');
            });
            checkout.open();
        });
    </script>
@endsection
