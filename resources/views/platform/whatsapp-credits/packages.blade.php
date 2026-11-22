@extends('layouts.app')

@section('content')
    <div class="platform-whatsapp-page">
        <x-admin.page-header
            title="WhatsApp credits"
            description="Create and manage the credit packages available for tenant purchases."
        />

        @include('platform.whatsapp-credits._navigation')
        <x-admin.alert />
        <x-admin.validation-errors />
        @include('platform.whatsapp-credits._summary')

        <section class="admin-card platform-section wa-credit-section" aria-labelledby="wa-packages-title">
            <div class="platform-section-heading wa-credit-section-heading">
                <div>
                    <h2 id="wa-packages-title">Credit packages</h2>
                    <p>Package prices use {{ $currency }} and are charged through the configured Razorpay account.</p>
                </div>

                <details class="wa-package-create">
                    <summary class="btn btn-primary"><i class="fa-solid fa-plus mr-1" aria-hidden="true"></i> Add package</summary>
                    <form method="post" action="{{ route('platform.whatsapp-credits.packages.store') }}" class="wa-package-form">
                        @csrf
                        <div class="wa-package-form-heading">
                            <h3>Create a package</h3>
                            <p>Published packages appear on tenant WhatsApp credit pages.</p>
                        </div>

                        <div class="wa-package-fields">
                            <div class="form-group">
                                <label for="pack-name">Package name</label>
                                <input id="pack-name" name="name" class="form-control" maxlength="120" placeholder="Starter" required>
                            </div>
                            <div class="form-group">
                                <label for="pack-credits">Credits</label>
                                <input id="pack-credits" name="credits" type="number" class="form-control" min="1" max="1000000" placeholder="500" required>
                            </div>
                            <div class="form-group">
                                <label for="pack-price">Price ({{ $currency }})</label>
                                <input id="pack-price" name="price" type="number" class="form-control" min="0.01" step="0.01" placeholder="500.00" required>
                            </div>
                            <div class="form-group">
                                <label for="pack-order">Display order</label>
                                <input id="pack-order" name="sort_order" type="number" class="form-control" min="0" max="65535" value="0">
                            </div>
                        </div>

                        <div class="wa-package-form-actions">
                            <button class="btn btn-primary" type="submit">Save package</button>
                        </div>
                    </form>
                </details>
            </div>

            @if ($packs->isEmpty())
                <div class="wa-package-empty">
                    <span aria-hidden="true"><i class="fa-solid fa-box-open"></i></span>
                    <div>
                        <h3>No packages published</h3>
                        <p>Create a package to let tenant administrators top up their WhatsApp wallets.</p>
                    </div>
                </div>
            @else
                <div class="row wa-package-grid">
                    @foreach ($packs as $pack)
                        <div class="col-md-6 col-xl-4 mb-3">
                            <article class="wa-package-card {{ $pack->is_active ? 'is-active' : 'is-paused' }}">
                                <div class="wa-package-card-top">
                                    <span class="wa-package-icon" aria-hidden="true"><i class="fa-solid fa-box"></i></span>
                                    <x-admin.badge :tone="$pack->is_active ? 'success' : 'slate'">
                                        {{ $pack->is_active ? 'Published' : 'Paused' }}
                                    </x-admin.badge>
                                </div>

                                <h3>{{ $pack->name }}</h3>
                                <div class="wa-package-price">{{ $currency }} {{ number_format($pack->price, 2) }}</div>
                                <div class="wa-package-credit-count">{{ number_format($pack->credits) }} <span>credits</span></div>
                                <div class="wa-package-unit-price">{{ $currency }} {{ number_format($pack->price / $pack->credits, 2) }} per credit</div>

                                <div class="wa-package-actions">
                                    <details class="wa-package-edit">
                                        <summary class="btn btn-outline-primary btn-sm">Edit package</summary>
                                        <form method="post" action="{{ route('platform.whatsapp-credits.packages.update', $pack) }}" class="wa-package-edit-form">
                                            @csrf
                                            @method('put')
                                            <div class="form-group">
                                                <label for="pack-name-{{ $pack->id }}">Package name</label>
                                                <input id="pack-name-{{ $pack->id }}" name="name" class="form-control" value="{{ $pack->name }}" maxlength="120" required>
                                            </div>
                                            <div class="wa-package-edit-fields">
                                                <div class="form-group">
                                                    <label for="pack-credits-{{ $pack->id }}">Credits</label>
                                                    <input id="pack-credits-{{ $pack->id }}" name="credits" type="number" class="form-control" min="1" max="1000000" value="{{ $pack->credits }}" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="pack-price-{{ $pack->id }}">Price ({{ $currency }})</label>
                                                    <input id="pack-price-{{ $pack->id }}" name="price" type="number" class="form-control" min="0.01" step="0.01" value="{{ $pack->price }}" required>
                                                </div>
                                                <div class="form-group">
                                                    <label for="pack-order-{{ $pack->id }}">Display order</label>
                                                    <input id="pack-order-{{ $pack->id }}" name="sort_order" type="number" class="form-control" min="0" max="65535" value="{{ $pack->sort_order }}">
                                                </div>
                                            </div>
                                            <button class="btn btn-primary btn-sm" type="submit">Save changes</button>
                                        </form>
                                    </details>

                                    <form method="post" action="{{ route('platform.whatsapp-credits.packages.toggle', $pack) }}">
                                        @csrf
                                        <button class="btn btn-sm {{ $pack->is_active ? 'btn-outline-secondary' : 'btn-outline-success' }}" type="submit">
                                            {{ $pack->is_active ? 'Pause purchases' : 'Publish package' }}
                                        </button>
                                    </form>
                                </div>
                            </article>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    </div>
@endsection
