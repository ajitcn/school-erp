<section class="platform-summary wa-credit-summary" aria-label="WhatsApp credit totals">
    <x-admin.stat-card
        label="Tenant wallets"
        :value="number_format($summary['wallets'])"
        detail="Wallets with credit activity"
        icon="fa-wallet"
    />
    <x-admin.stat-card
        label="Available credits"
        :value="number_format($summary['balance'])"
        detail="Across all tenant wallets"
        tone="info"
        icon="fa-coins"
    />
    <x-admin.stat-card
        label="Credits purchased"
        :value="number_format($summary['purchased'])"
        detail="Confirmed top-ups"
        tone="success"
        icon="fa-cart-shopping"
    />
    <x-admin.stat-card
        label="Net credits used"
        :value="number_format($summary['used'])"
        detail="After delivery refunds"
        tone="warning"
        icon="fa-paper-plane"
    />
</section>
