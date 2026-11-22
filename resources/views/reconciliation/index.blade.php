@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Reconciliation"
        description="Compare recorded collections with cash drawer and external payment totals."
    />

    <x-admin.alert />
    <x-admin.validation-errors />

    @if (!auth()->user()->currentBranchId())
        <x-admin.card class="mb-4">
            <form method="get" action="{{ route('reconciliation.index') }}" class="form-row align-items-end">
                <div class="col-md-6 form-group mb-md-0">
                    <label for="branch_id">Branch</label>
                    <select id="branch_id" name="branch_id" class="form-control" required>
                        <option value="">Choose a branch</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($branchId == $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto form-group mb-0">
                    <button class="btn btn-outline-primary" type="submit">Load branch</button>
                </div>
            </form>
        </x-admin.card>
    @elseif ($branchId)
        <p class="text-muted mb-4">Branch: <strong>{{ $branches->firstWhere('id', $branchId)?->name ?? 'Current branch' }}</strong></p>
    @endif

    @if (!$branchId)
        <x-admin.card>
            <div class="text-center py-4">
                <h2 class="h5">Choose a branch to begin</h2>
                <p class="text-muted mb-0">Select one of your branches to prepare a closing or reconcile payment totals.</p>
            </div>
        </x-admin.card>
    @else
        <div class="row">
            <div class="col-xl-6 mb-4">
                <x-admin.card class="h-100">
                    <div class="mb-4">
                        <p class="text-primary text-uppercase small font-weight-bold mb-1">Cash drawer</p>
                        <h2 class="h5 mb-1">Cashier closing</h2>
                        <p class="text-muted small mb-0">Count cash on hand and compare it with the day’s recorded cash collections.</p>
                    </div>

                    <form
                        method="post"
                        action="{{ route('reconciliation.close') }}"
                        id="cashier-closing-form"
                        data-expected-url="{{ route('reconciliation.expected-cash') }}"
                    >
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">

                        <div class="form-group">
                            <label for="closing_date">Closing date</label>
                            <input
                                id="closing_date"
                                type="text"
                                name="closing_date"
                                class="form-control js-datepicker"
                                value="{{ old('closing_date', $closingDate) }}"
                                autocomplete="off"
                                placeholder="DD/MM/YYYY"
                                required
                            >
                        </div>

                        <div class="rounded border bg-light p-3 mb-3" aria-live="polite">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-muted">Expected cash</span>
                                <strong class="h5 mb-0" id="expected-cash-value">₹{{ number_format($expectedCash, 2) }}</strong>
                            </div>
                            <small class="text-muted" id="expected-cash-help">Calculated from completed cash payments, including split tenders.</small>
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <span class="text-muted">Variance <small>(actual − expected)</small></span>
                                <strong id="cash-variance-value">—</strong>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="actual_cash">Actual cash counted</label>
                            <div class="input-group">
                                <div class="input-group-prepend"><span class="input-group-text">₹</span></div>
                                <input
                                    id="actual_cash"
                                    type="number"
                                    name="actual_cash"
                                    class="form-control"
                                    min="0"
                                    step="0.01"
                                    value="{{ old('actual_cash', $existingClosing->actual_cash ?? '') }}"
                                    required
                                >
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="closing_notes">Notes <span class="text-muted font-weight-normal">(optional)</span></label>
                            <textarea id="closing_notes" name="notes" rows="3" class="form-control" maxlength="2000">{{ old('notes', $existingClosing->notes ?? '') }}</textarea>
                        </div>

                        <button class="btn btn-primary" type="submit">
                            {{ $existingClosing ? 'Update today’s closing' : 'Submit closing' }}
                        </button>
                    </form>
                </x-admin.card>
            </div>

            <div class="col-xl-6 mb-4">
                <x-admin.card class="h-100">
                    <div class="mb-4">
                        <p class="text-primary text-uppercase small font-weight-bold mb-1">External settlement</p>
                        <h2 class="h5 mb-1">Payment reconciliation</h2>
                        <p class="text-muted small mb-0">Compare system collections with your bank, UPI, card, or cash settlement report.</p>
                    </div>

                    <form method="post" action="{{ route('reconciliation.store') }}">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">

                        <div class="form-row">
                            <div class="col-md-6 form-group">
                                <label for="from_date">From date</label>
                                <input
                                    id="from_date"
                                    type="text"
                                    name="from_date"
                                    class="form-control js-datepicker"
                                    value="{{ old('from_date') }}"
                                    autocomplete="off"
                                    placeholder="DD/MM/YYYY"
                                    required
                                >
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="to_date">To date</label>
                                <input
                                    id="to_date"
                                    type="text"
                                    name="to_date"
                                    class="form-control js-datepicker"
                                    value="{{ old('to_date') }}"
                                    autocomplete="off"
                                    placeholder="DD/MM/YYYY"
                                    required
                                >
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="col-md-6 form-group">
                                <label for="payment_mode">Payment method</label>
                                <select id="payment_mode" name="payment_mode" class="form-control" required>
                                    @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank_transfer' => 'Bank transfer', 'online' => 'Online'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('payment_mode', 'cash') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group">
                                <label for="bank_total">External settlement total</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">₹</span></div>
                                    <input
                                        id="bank_total"
                                        type="number"
                                        name="bank_total"
                                        class="form-control"
                                        min="0"
                                        step="0.01"
                                        value="{{ old('bank_total') }}"
                                        required
                                    >
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="reconcile_notes">Notes <span class="text-muted font-weight-normal">(optional)</span></label>
                            <textarea id="reconcile_notes" name="notes" rows="3" class="form-control" maxlength="2000">{{ old('notes') }}</textarea>
                        </div>

                        <button class="btn btn-primary" type="submit">Save reconciliation</button>
                    </form>
                </x-admin.card>
            </div>
        </div>

        <x-admin.card class="mb-4" padding="false">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="h5 mb-1">Cashier closing history</h2>
                    <p class="small text-muted mb-0">Daily expected cash, counted cash, variance, and cashier.</p>
                </div>
            </div>
            <x-admin.table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Branch</th>
                        <th>Cashier</th>
                        <th class="text-right">Expected</th>
                        <th class="text-right">Actual</th>
                        <th class="text-right">Variance</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($closings as $closing)
                        <tr>
                            <td>{{ \Illuminate\Support\Carbon::parse($closing->closing_date)->format('d M Y') }}</td>
                            <td>{{ $closing->branch_name }}</td>
                            <td>
                                {{ $closing->cashier_name }}
                                @if ($closing->notes)
                                    <div class="small text-muted">{{ $closing->notes }}</div>
                                @endif
                            </td>
                            <td class="text-right">₹{{ number_format((float) $closing->expected_cash, 2) }}</td>
                            <td class="text-right">₹{{ number_format((float) $closing->actual_cash, 2) }}</td>
                            <td class="text-right {{ (float) $closing->variance < 0 ? 'text-danger' : ((float) $closing->variance > 0 ? 'text-success' : '') }}">
                                {{ (float) $closing->variance > 0 ? '+' : '' }}₹{{ number_format((float) $closing->variance, 2) }}
                            </td>
                            <td><x-admin.badge :tone="$closing->status === 'submitted' ? 'info' : 'slate'">{{ ucfirst($closing->status) }}</x-admin.badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-5 text-center text-muted">No cashier closings have been submitted for this branch.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.table>
            <div class="p-3">{{ $closings->links() }}</div>
        </x-admin.card>

        <x-admin.card padding="false">
            <div class="card-header">
                <h2 class="h5 mb-1">Payment reconciliation history</h2>
                <p class="small text-muted mb-0">Saved comparisons between recorded collections and external settlement reports.</p>
            </div>
            <x-admin.table>
                <thead>
                    <tr>
                        <th>Period</th>
                        <th>Branch</th>
                        <th>Method</th>
                        <th class="text-right">System</th>
                        <th class="text-right">External</th>
                        <th class="text-right">Variance</th>
                        <th>Submitted by</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reconciliations as $reconciliation)
                        <tr>
                            <td>
                                {{ \Illuminate\Support\Carbon::parse($reconciliation->from_date)->format('d M Y') }}
                                @if ($reconciliation->from_date !== $reconciliation->to_date)
                                    <span class="text-muted">– {{ \Illuminate\Support\Carbon::parse($reconciliation->to_date)->format('d M Y') }}</span>
                                @endif
                                @if ($reconciliation->notes)
                                    <div class="small text-muted">{{ $reconciliation->notes }}</div>
                                @endif
                            </td>
                            <td>{{ $reconciliation->branch_name }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $reconciliation->payment_mode)) }}</td>
                            <td class="text-right">₹{{ number_format((float) $reconciliation->system_total, 2) }}</td>
                            <td class="text-right">₹{{ number_format((float) $reconciliation->bank_total, 2) }}</td>
                            <td class="text-right {{ (float) $reconciliation->variance < 0 ? 'text-danger' : ((float) $reconciliation->variance > 0 ? 'text-success' : '') }}">
                                {{ (float) $reconciliation->variance > 0 ? '+' : '' }}₹{{ number_format((float) $reconciliation->variance, 2) }}
                            </td>
                            <td>{{ $reconciliation->reconciler_name }}</td>
                            <td><x-admin.badge tone="info">{{ ucfirst($reconciliation->status) }}</x-admin.badge></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-5 text-center text-muted">No payment reconciliations have been saved for this branch.</td></tr>
                    @endforelse
                </tbody>
            </x-admin.table>
            <div class="p-3">{{ $reconciliations->links() }}</div>
        </x-admin.card>
    @endif

    @if ($branchId)
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('cashier-closing-form');
                const dateInput = document.getElementById('closing_date');
                const actualInput = document.getElementById('actual_cash');
                const expectedOutput = document.getElementById('expected-cash-value');
                const varianceOutput = document.getElementById('cash-variance-value');
                const help = document.getElementById('expected-cash-help');
                let expectedCash = Number(@json($expectedCash));

                function updateVariance() {
                    const actualCash = Number(actualInput.value);
                    if (!actualInput.value || !Number.isFinite(actualCash)) {
                        varianceOutput.textContent = '—';
                        return;
                    }

                    const variance = actualCash - expectedCash;
                    const sign = variance > 0 ? '+' : variance < 0 ? '−' : '';
                    varianceOutput.textContent = sign + '₹' + Math.abs(variance).toFixed(2);
                    varianceOutput.classList.toggle('text-danger', variance < 0);
                    varianceOutput.classList.toggle('text-success', variance > 0);
                }

                function toIsoDate(value) {
                    const match = value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
                    return match ? match[3] + '-' + match[2] + '-' + match[1] : value;
                }

                async function refreshExpectedCash() {
                    if (!dateInput.value) return;

                    help.textContent = 'Updating expected cash…';
                    try {
                        const url = new URL(form.dataset.expectedUrl, window.location.origin);
                        url.searchParams.set('branch_id', form.elements.branch_id.value);
                        url.searchParams.set('date', toIsoDate(dateInput.value));
                        const response = await fetch(url, { headers: { Accept: 'application/json' } });
                        if (!response.ok) throw new Error('Expected cash could not be loaded.');

                        const data = await response.json();
                        expectedCash = Number(data.expected_cash || 0);
                        expectedOutput.textContent = '₹' + expectedCash.toFixed(2);
                        actualInput.value = data.actual_cash ?? '';
                        document.getElementById('closing_notes').value = data.notes ?? '';
                        help.textContent = 'Calculated from completed cash payments, including split tenders.';
                        updateVariance();
                    } catch (error) {
                        help.textContent = 'Could not refresh the expected cash total. Reload the page and try again.';
                    }
                }

                dateInput.addEventListener('changeDate', refreshExpectedCash);
                dateInput.addEventListener('change', refreshExpectedCash);
                actualInput.addEventListener('input', updateVariance);
                updateVariance();
            });
        </script>
    @endif
@endsection
