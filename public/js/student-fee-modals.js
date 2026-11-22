/** Loads and submits the student fee setup and collection dialogs. */
(function () {
    'use strict';

    if (window.schoolErpStudentFeeModalsLoaded) return;
    window.schoolErpStudentFeeModalsLoaded = true;

    var modalElement = document.getElementById('student-fee-modal');

    function closeFeeModal() {
        if (window.jQuery && window.jQuery.fn.modal) {
            window.jQuery(modalElement).modal('hide');
        }

        window.setTimeout(function () {
            if (!modalElement.classList.contains('show')) return;

            modalElement.classList.remove('show');
            modalElement.style.display = 'none';
            modalElement.setAttribute('aria-hidden', 'true');
            modalElement.removeAttribute('aria-modal');
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('padding-right');
            document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
                backdrop.remove();
            });

            if (window.jQuery && window.jQuery.fn.modal) {
                window.jQuery(modalElement).modal('dispose');
            }
        }, 400);
    }

    document.addEventListener('click', function (event) {
        var target = event.target;
        var closeButton = target instanceof Element
            ? target.closest('#student-fee-modal [data-dismiss="modal"]')
            : null;

        if (!closeButton && target !== modalElement) return;

        event.preventDefault();
        closeFeeModal();
    }, true);

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modalElement?.classList.contains('show')) {
            closeFeeModal();
        }
    }, true);

    document.addEventListener('DOMContentLoaded', function () {
        var modal = jQuery('#student-fee-modal');
        var csrf = document.querySelector('meta[name="csrf-token"]')?.content;
        var pendingSuccessMessage = window.sessionStorage.getItem('studentFeeSuccessMessage');

        if (pendingSuccessMessage) {
            window.sessionStorage.removeItem('studentFeeSuccessMessage');
            showPageSuccess(pendingSuccessMessage);
        }

        modal.on('hidden.bs.modal.studentFeeCleanup', destroyModalPickers);

        function destroyModalPickers() {
            modal.find('select[multiple], select.bt-select').each(function () {
                var picker = jQuery(this);

                if (picker.data('selectpicker')) {
                    picker.selectpicker('destroy');
                }
            });
        }

        document.addEventListener('click', async function (event) {
            var trigger = event.target.closest('[data-open-fee-modal]');
            if (!trigger || trigger.disabled) return;

            trigger.disabled = true;
            destroyModalPickers();
            modal.data('fee-modal-url', trigger.dataset.openFeeModal);
            modal.html('<div class="modal-dialog modal-dialog-centered"><div class="modal-content p-5 text-center"><span class="spinner-border spinner-border-sm mx-auto mb-2" role="status"></span><span>Loading fee details…</span></div></div>');
            modal.modal('show');

            try {
                var response = await fetch(trigger.dataset.openFeeModal, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) throw new Error('Could not load this student’s fee details.');

                modal.html(await response.text());
                if (window.initBtSelect) window.initBtSelect(modal[0]);
                modal.find('.js-datepicker').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, orientation: 'bottom auto' });
                initializeFeeModal();
            } catch (error) {
                modal.html('<div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header"><h2 id="student-fee-modal-title" class="modal-title h5">Fee details</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><div class="alert alert-danger mb-0">' + error.message + '</div></div></div></div>');
            } finally {
                trigger.disabled = false;
            }
        });

        function initializeFeeModal() {
            modal.off('.feeModal');
            initializeFeeSetup();
            initializeFeeCollection();
            bindModalSubmission();
            bindFeeAssignmentEdits();
        }

        function showPageSuccess(message) {
            var alert = document.createElement('div');
            var closeButton = document.createElement('button');
            var content = document.querySelector('.admin-content');

            if (!content) return;

            alert.className = 'alert alert-success alert-dismissible fade show';
            alert.setAttribute('role', 'status');
            alert.append(document.createTextNode(message));

            closeButton.type = 'button';
            closeButton.className = 'close';
            closeButton.setAttribute('data-dismiss', 'alert');
            closeButton.setAttribute('aria-label', 'Close');
            closeButton.textContent = '×';
            alert.append(closeButton);

            var breadcrumb = content.querySelector('.admin-page-breadcrumb');
            content.insertBefore(alert, breadcrumb ? breadcrumb.nextSibling : content.firstChild);
        }

        async function loadFeeModalContent(url) {
            var response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

            if (!response.ok) {
                throw new Error('The fee details could not be refreshed.');
            }

            modal.html(await response.text());
            if (window.initBtSelect) window.initBtSelect(modal[0]);
            modal.find('.js-datepicker').datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, orientation: 'bottom auto' });
            initializeFeeModal();
        }

        async function refreshFeeSetupModal(message) {
            await loadFeeModalContent(modal.data('fee-modal-url'));

            var feedback = document.createElement('div');
            feedback.className = 'alert alert-success alert-dismissible fade show';
            feedback.setAttribute('role', 'status');
            feedback.textContent = message;
            modal.find('[data-fee-edit-feedback]').get(0)?.append(feedback);
        }

        function formatCurrency(amount) {
            return (Number(amount) || 0).toLocaleString('en-IN', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        }

        function showCollectionSuccess(result) {
            var template = modal.find('template[data-collection-success-template]').get(0);
            var payment = result.payment || {};
            var fragment = template.content.cloneNode(true);
            var receiptUrl = result.receipt_url;
            var receiptLink = fragment.querySelector('[data-collection-view-receipt]');
            var allocationList = fragment.querySelector('[data-recent-allocations]');

            fragment.querySelector('[data-recent-payment-amount]').textContent = '₹' + formatCurrency(payment.amount);
            fragment.querySelector('[data-recent-receipt-number]').textContent = payment.receipt_number || '—';
            fragment.querySelector('[data-recent-payment-method]').textContent = payment.payment_method || '—';
            fragment.querySelector('[data-recent-paid-at]').textContent = payment.paid_at || '—';
            fragment.querySelector('[data-recent-transaction-reference]').textContent = payment.transaction_reference || '—';
            var paymentReferenceRow = fragment.querySelector('[data-recent-payment-reference-row]');
            paymentReferenceRow.hidden = !payment.payment_reference;
            if (payment.payment_reference) {
                fragment.querySelector('[data-recent-payment-reference]').textContent = payment.payment_reference;
            }
            receiptLink.href = receiptUrl;

            (payment.allocations || []).forEach(function (allocation) {
                var row = document.createElement('div');
                var label = document.createElement('span');
                var amount = document.createElement('strong');

                row.className = 'fee-recent-allocation-row';
                label.textContent = [allocation.fee_name, allocation.installment_name].filter(Boolean).join(' · ');
                amount.textContent = '₹' + formatCurrency(allocation.amount);
                row.append(label, amount);
                allocationList.append(row);
            });

            modal.off('.feeModal');
            modal.empty().append(fragment);
            bindCollectionSuccessActions(receiptUrl);
        }

        function bindCollectionSuccessActions(receiptUrl) {
            var feedback = modal.find('[data-receipt-action-feedback]');

            modal.on('click.feeModal', '[data-collection-print-receipt]', function () {
                var receiptWindow = window.open(receiptUrl, '_blank');

                if (!receiptWindow) {
                    feedback.text('Allow pop-ups to open the printable receipt.');
                    return;
                }

                var printStarted = false;
                var printReceipt = function () {
                    if (printStarted) return;

                    printStarted = true;
                    receiptWindow.focus();
                    receiptWindow.print();
                };

                receiptWindow.addEventListener('load', printReceipt, { once: true });
                window.setTimeout(printReceipt, 1500);
            });

            modal.on('click.feeModal', '[data-collection-share-receipt]', async function () {
                try {
                    if (navigator.share) {
                        await navigator.share({
                            title: 'School fee receipt',
                            text: 'Payment receipt',
                            url: receiptUrl,
                        });
                        feedback.text('Receipt share options opened.');
                    } else if (navigator.clipboard?.writeText) {
                        await navigator.clipboard.writeText(receiptUrl);
                        feedback.text('Receipt link copied.');
                    } else {
                        window.prompt('Copy the receipt link:', receiptUrl);
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        feedback.text('The receipt could not be shared. You can open it and copy the link.');
                    }
                }
            });

            modal.on('click.feeModal', '[data-collect-another-fee]', async function () {
                var button = this;
                button.disabled = true;

                try {
                    await loadFeeModalContent(modal.data('fee-modal-url'));
                } catch (error) {
                    feedback.text(error.message);
                    button.disabled = false;
                }
            });
        }

        function initializeFeeSetup() {
            var form = modal.find('[data-setup-fee-form]');
            if (!form.length) return;

            var items = form.find('[data-fee-items]');
            var cardTemplate = modal.find('template[data-fee-card-template]').get(0);
            var installmentTemplate = modal.find('template[data-installment-row-template]').get(0);
            var addFeeButton = form.find('[data-add-fee-card]');
            var saveButton = modal.find('[data-modal-save-fee]');
            var nextFeeIndex = 0;

            function makeId(prefix, index, field) {
                return prefix + '-' + index + '-' + field.replace(/[^a-z0-9_-]/gi, '-');
            }

            function initializeDatepicker(input) {
                if (window.jQuery && jQuery.fn.datepicker) {
                    jQuery(input).datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, orientation: 'bottom auto' });
                }
            }

            function addInstallmentRow(card, rowIndex) {
                var row = installmentTemplate.content.cloneNode(true).firstElementChild;
                var feeIndex = card.dataset.feeIndex;

                row.querySelectorAll('[data-installment-field]').forEach(function (field) {
                    var name = field.dataset.installmentField;
                    var id = makeId('fee', feeIndex, 'installment-' + rowIndex + '-' + name);
                    field.id = id;
                    field.name = 'fees[' + feeIndex + '][installments][' + rowIndex + '][' + name + ']';
                    field.required = name !== 'name';
                    if (name === 'name') field.value = 'Installment ' + (rowIndex + 1);
                    row.querySelector('[data-label-for="' + name + '"]').htmlFor = id;
                });

                row.querySelector('[data-remove-installment]').setAttribute('aria-label', 'Remove installment ' + (rowIndex + 1));
                card.querySelector('[data-installment-rows]').appendChild(row);
                initializeDatepicker(row.querySelector('.js-datepicker'));
                return row;
            }

            function addFeeCard() {
                if (!cardTemplate) return;
                var fragment = cardTemplate.content.cloneNode(true);
                var card = fragment.querySelector('[data-fee-item]');
                var feeIndex = nextFeeIndex++;
                card.dataset.feeIndex = feeIndex;

                card.querySelectorAll('[data-fee-field]').forEach(function (field) {
                    var name = field.dataset.feeField;
                    var id = makeId('fee', feeIndex, name);
                    field.id = id;
                    field.name = 'fees[' + feeIndex + '][' + name + ']';
                    var label = card.querySelector('[data-label-for="' + name + '"]');
                    if (label) label.htmlFor = id;
                });

                items.append(card);
                if (window.initBtSelect) window.initBtSelect(card);
                initializeDatepicker(card.querySelector('[data-fee-full-date] .js-datepicker'));
                updateFeeCards();
            }

            function updateFeeCard(card) {
                var planSelect = card.querySelector('[data-fee-plan-select]');
                var amountInput = card.querySelector('[data-fee-plan-amount]');
                var modeSelect = card.querySelector('[data-fee-mode]');
                var installmentMode = modeSelect.value === 'installment';
                var fullDate = card.querySelector('[data-fee-full-date]');
                var fullDateInput = fullDate.querySelector('input');
                var installmentSection = card.querySelector('[data-fee-installment-section]');
                var rows = Array.from(card.querySelectorAll('[data-installment-row]'));
                var feeAmount = Math.round((Number(amountInput.value) || 0) * 100);
                var scheduledAmount = rows.reduce(function (sum, row) {
                    return sum + Math.round((Number(row.querySelector('[name$="[amount]"]').value) || 0) * 100);
                }, 0);
                var difference = feeAmount - scheduledAmount;

                fullDate.hidden = installmentMode;
                fullDateInput.disabled = installmentMode;
                fullDateInput.required = !installmentMode;
                installmentSection.hidden = !installmentMode;
                rows.forEach(function (row) {
                    row.querySelector('[name$="[name]"]').disabled = !installmentMode;
                    row.querySelector('[name$="[amount]"]').disabled = !installmentMode;
                    row.querySelector('[name$="[amount]"]').required = installmentMode;
                    row.querySelector('[name$="[due_date]"]').disabled = !installmentMode;
                    row.querySelector('[name$="[due_date]"]').required = installmentMode;
                });

                var amount = Number(amountInput.value) || 0;
                card.querySelector('[data-schedule-total]').textContent = (scheduledAmount / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                var message = card.querySelector('[data-schedule-message]');

                if (!amount) message.textContent = 'Select a plan and enter an amount.';
                else if (difference === 0) message.textContent = 'The schedule matches this fee.';
                else if (difference > 0) message.textContent = '₹' + (difference / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' left to allocate.';
                else message.textContent = '₹' + (Math.abs(difference) / 100).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' over this fee amount.';

                var hasValidSchedule = !installmentMode || (rows.length >= 2 && difference === 0 && rows.every(function (row) {
                    return Number(row.querySelector('[name$="[amount]"]').value) > 0 && row.querySelector('[name$="[due_date]"]').value.trim() !== '';
                }));
                var hasValidFullDate = installmentMode || fullDateInput.value.trim() !== '';

                return Boolean(planSelect.value) && amount > 0 && hasValidSchedule && hasValidFullDate;
            }

            function updateFeeCards() {
                var cards = Array.from(items.find('[data-fee-item]'));
                var selectedPlans = cards.map(function (card) {
                    return card.querySelector('[data-fee-plan-select]').value;
                }).filter(Boolean);
                var hasUniquePlans = new Set(selectedPlans).size === selectedPlans.length;
                var allValid = cards.length > 0 && hasUniquePlans && cards.every(updateFeeCard);
                saveButton.prop('disabled', !allValid);
            }

            addFeeButton.on('click.feeModal', addFeeCard);
            modal.on('change.feeModal', '[data-fee-plan-select]', function () {
                var card = this.closest('[data-fee-item]');
                var previousValue = this.dataset.lastValidValue || '';
                var duplicatePlan = Boolean(this.value) && Array.from(items.find('[data-fee-item]')).some(function (otherCard) {
                    return otherCard !== card && otherCard.querySelector('[data-fee-plan-select]').value === this.value;
                }, this);

                if (duplicatePlan) {
                    this.closest('.form-group').querySelector('[data-fee-plan-error]').classList.remove('d-none');
                    jQuery(this).selectpicker('val', previousValue);
                    card.querySelector('[data-fee-plan-amount]').value = this.dataset.lastValidAmount || '';
                    updateFeeCards();

                    return;
                }

                this.closest('.form-group').querySelector('[data-fee-plan-error]').classList.add('d-none');
                this.dataset.lastValidValue = this.value;
                var selected = this.options[this.selectedIndex];
                var defaultAmount = Number(selected?.dataset.defaultAmount) || 0;
                var amountInput = card.querySelector('[data-fee-plan-amount]');
                amountInput.value = defaultAmount > 0 ? defaultAmount.toFixed(2) : '';
                this.dataset.lastValidAmount = amountInput.value;
                updateFeeCards();
            });
            modal.on('change.feeModal', '[data-fee-mode]', function () {
                var card = this.closest('[data-fee-item]');
                var rows = card.querySelectorAll('[data-installment-row]');
                if (this.value === 'installment' && rows.length === 0) {
                    addInstallmentRow(card, 0);
                    addInstallmentRow(card, 1);
                }
                updateFeeCards();
            });
            modal.on('input.feeModal change.feeModal', '[data-fee-plan-amount], [data-fee-field="full_due_date"], [data-installment-field="amount"], [data-installment-field="due_date"]', updateFeeCards);
            modal.on('input.feeModal', '[data-fee-plan-amount]', function () {
                this.closest('[data-fee-item]').querySelector('[data-fee-plan-select]').dataset.lastValidAmount = this.value;
            });
            modal.on('click.feeModal', '[data-add-installment]', function () {
                var card = this.closest('[data-fee-item]');
                addInstallmentRow(card, card.querySelectorAll('[data-installment-row]').length);
                updateFeeCards();
            });
            modal.on('click.feeModal', '[data-remove-installment]', function () {
                var card = this.closest('[data-fee-item]');
                if (card.querySelectorAll('[data-installment-row]').length > 2) {
                    this.closest('[data-installment-row]').remove();
                    updateFeeCards();
                }
            });
            modal.on('click.feeModal', '[data-remove-fee-card]', function () {
                var cards = items.find('[data-fee-item]');
                if (cards.length > 1) {
                    var cardToRemove = this.closest('[data-fee-item]');
                    var planPicker = cardToRemove.querySelector('[data-fee-plan-select]');
                    var picker = jQuery(planPicker);

                    if (picker.data('selectpicker')) {
                        picker.selectpicker('destroy');
                    }
                    cardToRemove.remove();
                } else {
                    var card = cards.get(0);
                    var planSelect = card.querySelector('[data-fee-plan-select]');
                    jQuery(planSelect).selectpicker('val', '');
                    planSelect.dataset.lastValidValue = '';
                    planSelect.dataset.lastValidAmount = '';
                    card.querySelector('[data-fee-plan-amount]').value = '';
                    card.querySelector('[data-fee-mode]').value = 'full';
                    card.querySelector('[data-fee-full-date] input').value = '';
                    card.querySelector('[data-fee-plan-error]').classList.add('d-none');
                    card.querySelector('[data-installment-rows]').replaceChildren();
                }
                updateFeeCards();
            });

            addFeeCard();
        }

        function initializeFeeCollection() {
            var dueItems = modal.find('[data-collect-due-item]');
            var dueRows = modal.find('[data-collect-due-row]');
            var paidTotalInput = modal.find('[data-collect-total-paid-input]');
            var paidTotalDisplay = modal.find('[data-collect-total-paid-display]');
            var methodSelect = modal.find('[data-collect-payment-modes]');
            var methodAmountSection = modal.find('[data-collect-method-amount-section]');
            var methodAmountList = modal.find('[data-collect-method-amount-list]');
            var balance = modal.find('[data-collect-balance]');
            var balanceLabel = modal.find('[data-collect-balance-label]');
            var allocationScope = modal.find('[data-collect-scope]');
            var selectAllButton = modal.find('[data-collect-select-all]');
            var submitButton = modal.find('[data-collect-fee-form] [type="submit"]');
            var yearName = balanceLabel.text().split('·').pop().trim();
            if (!dueItems.length || !methodSelect.length) return;

            function formatCurrency(value) {
                return (Number(value) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            }

            function selectedMethods() {
                return methodSelect.find(':selected').toArray().map(function (option) {
                    return { value: option.value, label: option.textContent.trim() };
                });
            }

            function renderMethodAmounts() {
                var previousValues = new Map(methodAmountList.find('[data-collect-method-amount]').toArray().map(function (input) {
                    return [input.dataset.method, input.value];
                }));
                var methods = selectedMethods();
                methodAmountList.empty();
                methodAmountSection.prop('hidden', methods.length < 2);

                if (methods.length < 2) return;

                methods.forEach(function (method) {
                    var group = document.createElement('div');
                    var label = document.createElement('label');
                    var input = document.createElement('input');
                    var id = 'collect_method_amount_' + method.value;

                    group.className = 'form-group mb-2';
                    label.htmlFor = id;
                    label.textContent = method.label;
                    input.id = id;
                    input.className = 'form-control';
                    input.type = 'number';
                    input.name = 'method_amounts[' + method.value + ']';
                    input.min = '0.01';
                    input.step = '0.01';
                    input.placeholder = '0.00';
                    input.required = true;
                    input.dataset.method = method.value;
                    input.dataset.collectMethodAmount = '';
                    input.value = previousValues.get(method.value) || '';
                    input.addEventListener('input', updateCollectionTotals);
                    group.append(label, input);
                    methodAmountList.get(0).append(group);
                });
            }

            function updateCollectionTotals() {
                var totalDue = 0;
                var totalPaidCents = 0;
                var itemAmountsValid = true;
                var selectedCount = 0;

                dueRows.each(function () {
                    var checkbox = this.querySelector('[data-collect-due-item]');
                    var paidInput = this.querySelector('[data-collect-paid-input]');
                    var dueCents = Math.round((Number(this.dataset.balance) || 0) * 100);

                    totalDue += dueCents;
                    paidInput.disabled = !checkbox.checked;
                    if (!checkbox.checked) return;

                    selectedCount++;
                    var paidCents = Math.round((Number(paidInput.value) || 0) * 100);
                    totalPaidCents += paidCents;
                    if (paidCents < 1 || paidCents > dueCents) itemAmountsValid = false;
                });

                var methods = selectedMethods();
                var methodInputs = methodAmountList.find('[data-collect-method-amount]').toArray();
                var methodTotalCents = methodInputs.reduce(function (sum, input) {
                    return sum + Math.round((Number(input.value) || 0) * 100);
                }, 0);
                var methodsValid = methods.length === 1
                    || (methods.length > 1 && methodInputs.every(function (input) { return Number(input.value) > 0; }) && methodTotalCents === totalPaidCents);
                var methodAmountFeedback = methodAmountSection.find('[data-collect-method-amount-feedback]');

                if (methods.length > 1) {
                    methodAmountFeedback.text(methodsValid
                        ? 'Payment methods match total paid: ₹' + formatCurrency(methodTotalCents / 100) + '.'
                        : 'Payment methods total ₹' + formatCurrency(methodTotalCents / 100) + ' · Total paid ₹' + formatCurrency(totalPaidCents / 100) + '. These amounts must match.');
                    methodAmountFeedback.toggleClass('text-danger', !methodsValid).toggleClass('text-success', methodsValid);
                } else {
                    methodAmountFeedback.text('Payment method amounts must equal total paid.').removeClass('text-danger text-success');
                }

                balance.text(formatCurrency(totalDue / 100));
                balanceLabel.text('Total due · ' + yearName);
                paidTotalDisplay.val(formatCurrency(totalPaidCents / 100));
                paidTotalInput.val((totalPaidCents / 100).toFixed(2));
                allocationScope.text(!itemAmountsValid
                    ? 'Each paid amount must be above zero and no greater than its due amount.'
                    : (!methodsValid
                        ? 'Payment method amounts must add up to total paid.'
                        : (selectedCount
                            ? selectedCount + (selectedCount === 1 ? ' item selected.' : ' items selected.')
                            : 'Select due items and enter the paid amount for each.')));
                selectAllButton.text(selectedCount === dueItems.length ? 'Clear all' : 'Select all');
                selectAllButton.prop('disabled', dueItems.length < 2);

                var canSubmit = selectedCount > 0 && totalPaidCents > 0 && itemAmountsValid && methods.length > 0 && methodsValid;
                submitButton.prop('disabled', !canSubmit);
            }

            dueItems.on('change.feeModal', function () {
                var checkbox = this;
                var row = checkbox.closest('[data-collect-due-row]');
                var paidInput = row.querySelector('[data-collect-paid-input]');
                paidInput.value = checkbox.checked ? Number(row.dataset.balance).toFixed(2) : '';
                updateCollectionTotals();
            });
            dueRows.find('[data-collect-paid-input]').on('input.feeModal', updateCollectionTotals);
            selectAllButton.on('click.feeModal', function () {
                var shouldSelectAll = dueItems.filter(':checked').length !== dueItems.length;
                dueRows.each(function () {
                    var checkbox = this.querySelector('[data-collect-due-item]');
                    var paidInput = this.querySelector('[data-collect-paid-input]');
                    checkbox.checked = shouldSelectAll;
                    paidInput.value = shouldSelectAll ? Number(this.dataset.balance).toFixed(2) : '';
                });
                updateCollectionTotals();
            });
            methodSelect.on('change.feeModal', function () {
                renderMethodAmounts();
                updateCollectionTotals();
            });

            renderMethodAmounts();
            updateCollectionTotals();
        }

        function bindModalSubmission() {
            modal.on('submit.feeModal', '[data-fee-modal-form]', async function (event) {
                event.preventDefault();
                var form = event.currentTarget;
                var submitButton = form.querySelector('[type="submit"]') || modal.find('[data-modal-save-fee]').get(0);
                var errorBox = form.querySelector('[data-modal-errors]');
                errorBox.classList.add('d-none');
                errorBox.textContent = '';

                form.querySelectorAll('.js-datepicker').forEach(function (input) {
                    var match = input.value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
                    if (match) input.value = match[3] + '-' + match[2] + '-' + match[1];
                });
                submitButton.disabled = true;

                try {
                    var response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                    });
                    var result = await response.json();
                    if (!response.ok) {
                        var messages = result.errors ? Object.values(result.errors).flat() : [result.message || 'Please check the submitted details.'];
                        throw new Error(messages.join(' '));
                    }
                    if (result.receipt_url && form.matches('[data-collect-fee-form]')) {
                        showCollectionSuccess(result);
                        return;
                    }
                    if (result.receipt_url) {
                        window.location.assign(result.receipt_url);
                        return;
                    }

                    errorBox.classList.remove('alert-danger', 'd-none');
                    errorBox.classList.add('alert-success');
                    var successMessage = result.message || 'Fees saved successfully.';
                    errorBox.textContent = successMessage;
                    window.sessionStorage.setItem('studentFeeSuccessMessage', successMessage);
                    window.setTimeout(function () {
                        var hasReloaded = false;
                        var reloadPage = function () {
                            if (hasReloaded) return;

                            hasReloaded = true;
                            window.location.reload();
                        };

                        modal.one('hidden.bs.modal.feeModalSuccess', reloadPage);
                        modal.modal('hide');
                        window.setTimeout(reloadPage, 500);
                    }, 800);
                } catch (error) {
                    errorBox.classList.remove('d-none', 'alert-success');
                    errorBox.classList.add('alert-danger');
                    errorBox.textContent = error.message;
                    submitButton.disabled = false;
                }
            });
        }

        function bindFeeAssignmentEdits() {
            modal.on('submit.feeModal', '[data-fee-edit-form]', async function (event) {
                event.preventDefault();

                var form = event.currentTarget;
                var submitButton = form.querySelector('[type="submit"]');
                var errorBox = form.querySelector('[data-edit-errors]');

                errorBox.classList.add('d-none');
                errorBox.textContent = '';
                form.querySelectorAll('.js-datepicker').forEach(function (input) {
                    var match = input.value.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
                    if (match) input.value = match[3] + '-' + match[2] + '-' + match[1];
                });
                submitButton.disabled = true;

                try {
                    var response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                    });
                    var result = await response.json();

                    if (!response.ok) {
                        var messages = result.errors ? Object.values(result.errors).flat() : [result.message || 'Please check the installment details.'];
                        throw new Error(messages.join(' '));
                    }

                    await refreshFeeSetupModal(result.message || 'Fee installment updated successfully.');
                } catch (error) {
                    errorBox.classList.remove('d-none');
                    errorBox.textContent = error.message;
                    submitButton.disabled = false;
                }
            });

            modal.on('submit.feeModal', '[data-remove-fee-plan-form]', async function (event) {
                event.preventDefault();

                var form = event.currentTarget;
                var submitButton = form.querySelector('[type="submit"]');
                var errorBox = form.querySelector('[data-remove-errors]');

                if (!window.confirm('Remove this fee plan and all its unpaid installments from the student?')) return;

                errorBox.classList.add('d-none');
                errorBox.textContent = '';
                submitButton.disabled = true;

                try {
                    var response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf },
                    });
                    var result = await response.json();

                    if (!response.ok) {
                        var messages = result.errors ? Object.values(result.errors).flat() : [result.message || 'The fee plan could not be removed.'];
                        throw new Error(messages.join(' '));
                    }

                    await refreshFeeSetupModal(result.message || 'Unpaid fee plan removed from the student.');
                } catch (error) {
                    errorBox.classList.remove('d-none');
                    errorBox.textContent = error.message;
                    submitButton.disabled = false;
                }
            });
        }
    });
})();
