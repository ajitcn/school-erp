@php
    $defaultYearId = $assignments->firstWhere('component.academic_year_id', $enrollment?->academic_year_id)?->component?->academic_year_id
        ?? $assignments->firstWhere('component.structure.academic_year_id', $enrollment?->academic_year_id)?->component?->structure?->academic_year_id
        ?? $assignments->first()?->component?->academic_year_id
        ?? $assignments->first()?->component?->structure?->academic_year_id;
    $defaultYear = $assignments->first(fn ($assignment) => (int) ($assignment->component?->academic_year_id ?? $assignment->component?->structure?->academic_year_id) === (int) $defaultYearId)?->component?->academicYear
        ?? $assignments->first()?->component?->structure?->academicYear;
    $defaultBalance = $assignments
        ->filter(fn ($assignment) => (int) ($assignment->component?->academic_year_id ?? $assignment->component?->structure?->academic_year_id) === (int) $defaultYearId)
        ->sum('balance_due');
@endphp

<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content">
        <form method="post" action="{{ route('payments.store') }}" data-fee-modal-form data-collect-fee-form>
            @csrf
            <input type="hidden" name="student_id" value="{{ $student->id }}">
            <input type="hidden" name="academic_year_id" value="{{ $defaultYearId }}" data-collect-year>
            <input type="hidden" name="collect_selected_items" value="1">

            <div class="modal-header">
                <div>
                    <h2 id="student-fee-modal-title" class="modal-title h5">Collect fee</h2>
                    <p class="small text-muted mb-0">{{ $student->first_name }} {{ $student->last_name }} · {{ $student->student_id }} · {{ $student->branch?->name }}</p>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>

            <div class="modal-body">
                <div class="alert alert-danger d-none" role="alert" data-modal-errors></div>

                @if($assignments->isEmpty())
                    <div class="alert alert-info mb-0">There are no unpaid fees set up for this student. Add a fee before collecting payment.</div>
                @else
                    <section class="fee-collection-summary" aria-live="polite" aria-label="Outstanding fee balance">
                        <span class="fee-collection-summary-icon" aria-hidden="true"><i class="fa-solid fa-wallet"></i></span>
                        <div class="fee-collection-summary-copy">
                            <span data-collect-balance-label>Total due · {{ $defaultYear?->name ?? 'Selected academic year' }}</span>
                            <strong>₹<span data-collect-balance>{{ number_format($defaultBalance, 2) }}</span></strong>
                            <small data-collect-scope>Choose items and enter the amount received for each.</small>
                        </div>
                    </section>

                    <div class="fee-collection-fields">
                        <section class="fee-due-items" aria-labelledby="fee-due-items-title">
                            <div class="fee-due-items-heading">
                                <div>
                                    <h3 id="fee-due-items-title" class="h6 mb-1">Due items</h3>
                                    <p class="small text-muted mb-0">Dues are oldest first. Select items and enter the amount received for each.</p>
                                </div>
                                <button class="btn btn-sm btn-link" type="button" data-collect-select-all>Select all</button>
                            </div>

                            <div class="fee-due-table-wrap">
                                <table class="table fee-due-table mb-0">
                                    <thead>
                                        <tr>
                                            <th scope="col">Select</th>
                                            <th scope="col">Fee plan</th>
                                            <th scope="col" class="text-right">Amount due</th>
                                            <th scope="col">Due date</th>
                                            <th scope="col" class="text-right">Paid</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                @foreach($assignments as $assignment)
                                    @php
                                        $dueDate = $assignment->component?->due_date;
                                        $feeName = $assignment->component?->structure?->name ?? 'Fee';
                                        $installmentName = $assignment->component?->name ?? 'Installment';
                                    @endphp
                                    <tr class="fee-due-table-row" data-collect-due-row data-balance="{{ number_format($assignment->balance_due, 2, '.', '') }}">
                                        <td>
                                            <input id="collect_assignment_{{ $assignment->id }}" type="checkbox" name="student_fee_assignment_ids[]" value="{{ $assignment->id }}" data-collect-due-item aria-label="Select {{ $feeName }} · {{ $installmentName }}">
                                        </td>
                                        <td>
                                            <strong>{{ $feeName }}</strong>
                                            <small class="d-block text-muted">{{ $installmentName }}</small>
                                        </td>
                                        <td class="text-right text-nowrap">₹{{ number_format($assignment->balance_due, 2) }}</td>
                                        <td class="text-nowrap">{{ $dueDate ? $dueDate->format('d M Y') : 'Not set' }}</td>
                                        <td>
                                            <input id="collect_paid_{{ $assignment->id }}" class="form-control form-control-sm fee-due-paid-input" type="number" name="paid_amounts[{{ $assignment->id }}]" min="0.01" max="{{ number_format($assignment->balance_due, 2, '.', '') }}" step="0.01" placeholder="0.00" data-collect-paid-input disabled aria-label="Paid amount for {{ $feeName }}">
                                        </td>
                                    </tr>
                                @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <input type="hidden" name="amount" value="0.00" data-collect-total-paid-input>
                        <section class="fee-payment-summary" aria-label="Payment summary">
                            <div class="fee-payment-primary-row">
                                <div class="fee-payment-total">
                                    <label for="modal_total_paid_display">Total paid</label>
                                    <div class="input-group">
                                        <div class="input-group-prepend"><span class="input-group-text">₹</span></div>
                                        <input id="modal_total_paid_display" class="form-control" type="text" value="0.00" readonly data-collect-total-paid-display>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="modal_payment_modes">Payment method</label>
                                    <select id="modal_payment_modes" class="form-control bt-select" name="payment_modes[]" multiple data-collect-payment-modes aria-required="true">
                                        @foreach(['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank_transfer' => 'Bank transfer', 'online' => 'Online'] as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="modal_payment_reference">Payment reference <span class="text-muted font-weight-normal">(optional)</span></label>
                                    <input id="modal_payment_reference" class="form-control" name="payment_reference" type="text" maxlength="100" autocomplete="off" placeholder="Bank, UPI, or card reference">
                                </div>
                            </div>
                            <div class="form-group fee-payment-method-details" data-collect-method-amount-section hidden>
                                <label>Payment method amounts</label>
                                <div class="fee-method-amount-list" data-collect-method-amount-list></div>
                                <small class="form-text text-muted" data-collect-method-amount-feedback role="status" aria-live="polite">Payment method amounts must equal total paid.</small>
                            </div>
                        </section>
                    </div>
                @endif
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
                @if($assignments->isNotEmpty())
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-receipt mr-1" aria-hidden="true"></i>Collect and issue receipt</button>
                @endif
            </div>
        </form>
    </div>
</div>

<template data-collection-success-template>
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h2 id="student-fee-modal-title" class="modal-title h5">Payment recorded</h2>
                    <p class="small text-muted mb-0">{{ $student->first_name }} {{ $student->last_name }} · {{ $student->student_id }}</p>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>

            <div class="modal-body">
                <div class="fee-collection-success-banner" role="status">
                    <span class="fee-collection-success-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                    <div>
                        <strong>Payment collected successfully</strong>
                        <small>Receipt is ready to view, print, or share.</small>
                    </div>
                </div>

                <section class="fee-recent-collection" aria-labelledby="recent-collection-title">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h3 id="recent-collection-title" class="h6 mb-0">Recent collection</h3>
                        <span class="badge badge-success">Completed</span>
                    </div>
                    <div class="fee-recent-collection-details">
                        <div><small>Amount received</small><strong data-recent-payment-amount>₹0.00</strong></div>
                        <div><small>Receipt number</small><strong data-recent-receipt-number>—</strong></div>
                        <div><small>Payment method</small><strong data-recent-payment-method>—</strong></div>
                        <div><small>Collected at</small><strong data-recent-paid-at>—</strong></div>
                    </div>
                    <div class="fee-recent-collection-reference">
                        <small>Transaction reference</small>
                        <strong data-recent-transaction-reference>—</strong>
                    </div>
                    <div class="fee-recent-collection-reference" data-recent-payment-reference-row hidden>
                        <small>Payment reference</small>
                        <strong data-recent-payment-reference>—</strong>
                    </div>
                    <div class="fee-recent-allocation-list" data-recent-allocations aria-label="Payment allocation"></div>
                </section>

                <div class="small text-muted mt-3" role="status" aria-live="polite" data-receipt-action-feedback></div>
            </div>

            <div class="modal-footer fee-collection-success-actions">
                <button type="button" class="btn btn-outline-secondary mr-auto" data-collect-another-fee>Collect another fee</button>
                <button type="button" class="btn btn-outline-secondary" data-collection-share-receipt>Share</button>
                <button type="button" class="btn btn-outline-primary" data-collection-print-receipt>Print</button>
                <a class="btn btn-primary" href="#" target="_blank" rel="noopener" data-collection-view-receipt>View receipt</a>
            </div>
        </div>
    </div>
</template>
