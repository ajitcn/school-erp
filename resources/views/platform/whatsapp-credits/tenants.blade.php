@extends('layouts.app')

@section('content')
    <div class="platform-whatsapp-page">
        <x-admin.page-header
            title="WhatsApp tenant balances"
            description="Review each tenant’s available message credits and institution details."
        />

        @include('platform.whatsapp-credits._navigation')
        @include('platform.whatsapp-credits._summary')

        <section class="admin-card platform-section wa-credit-section" aria-labelledby="wa-wallets-title">
            <div class="platform-section-heading wa-credit-section-heading">
                <div>
                    <h2 id="wa-wallets-title">Tenant wallet balances</h2>
                    <p>Balances update automatically when a tenant buys credits or sends a WhatsApp message.</p>
                </div>
                <span class="platform-count">{{ number_format($tenants->total()) }} tenants</span>
            </div>

            <div class="table-responsive platform-table-wrap">
                <table class="table wa-tenant-table mb-0">
                    <thead>
                        <tr>
                            <th>Institution</th>
                            <th>Type</th>
                            <th>Students</th>
                            <th class="text-right">Available credits</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tenants as $tenant)
                            <tr>
                                <td>
                                    <strong>{{ $tenant->institution_name }}</strong>
                                    <small>{{ $tenant->email }}</small>
                                </td>
                                <td>{{ ucfirst($tenant->institution_type) }}</td>
                                <td>{{ number_format($tenant->students_count) }}</td>
                                <td class="text-right"><span class="wa-balance-value">{{ number_format($tenant->whatsappCreditWallet?->credits_balance ?? 0) }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4"><div class="wa-table-empty">No tenant accounts are available.</div></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="platform-pagination"><x-admin.pagination :paginator="$tenants" /></div>
        </section>
    </div>
@endsection
