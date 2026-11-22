<nav class="wa-credit-tabs" aria-label="WhatsApp credit sections">
    <a href="{{ route('platform.whatsapp-credits') }}" @class(['is-active' => request()->routeIs('platform.whatsapp-credits')])>
        <i class="fa-solid fa-box" aria-hidden="true"></i>
        <span>Credit packages</span>
    </a>
    <a href="{{ route('platform.whatsapp-credits.tenants') }}" @class(['is-active' => request()->routeIs('platform.whatsapp-credits.tenants')])>
        <i class="fa-solid fa-building" aria-hidden="true"></i>
        <span>Tenant balances</span>
    </a>
    <a href="{{ route('platform.whatsapp-credits.activity') }}" @class(['is-active' => request()->routeIs('platform.whatsapp-credits.activity')])>
        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
        <span>Credit activity</span>
    </a>
</nav>
