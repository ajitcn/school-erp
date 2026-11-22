@extends('layouts.app')

@section('content')
    <x-admin.page-header title="Collect fee" description="Select a student, choose due items, and record the amount received for each." />

    @if (! auth()->user()->currentBranchId() && ! $branchId)
        <section class="admin-card p-4" aria-labelledby="payment-branch-title">
            <h2 id="payment-branch-title" class="h5">Choose a branch to begin</h2>
            <form method="get" action="{{ route('payments.create') }}" class="form-row align-items-end mb-0">
                <div class="form-group col-md-6 col-xl-4 mb-md-0">
                    <label for="branch_id">Branch</label>
                    <select id="branch_id" name="branch_id" class="form-control" required>
                        <option value="">Choose a branch</option>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto mt-3 mt-md-0">
                    <button class="btn btn-primary" type="submit">Continue</button>
                </div>
            </form>
        </section>
    @elseif (! $hasStudents)
        <div class="admin-card">
            <x-admin.empty-state title="No active students found" description="This branch has no active students available for fee collection." />
        </div>
    @else
        <section class="admin-card fee-collection-card" aria-labelledby="payment-form-title">
            <div class="fee-collection-heading">
                <span class="fee-step-number">1</span>
                <div>
                    <h2 id="payment-form-title">Payment details</h2>
                    <p>Select a student to review due dates and remaining balances.</p>
                </div>
            </div>

            <form method="post" action="{{ route('payments.store') }}" data-fee-payment-form data-initial-assignment="{{ request('student_fee_assignment_id') }}">
                @csrf
                <input type="hidden" name="collect_selected_items" value="1">

                <div class="fee-collection-fields">
                    <div class="form-row">
                        <div class="form-group col-lg-7">
                            <label for="student_id">Student</label>
                            <div class="payment-student-picker" data-student-picker>
                                <input
                                    id="student_search"
                                    type="search"
                                    class="form-control @error('student_id') is-invalid @enderror"
                                    value="{{ $selectedStudent ? trim($selectedStudent->first_name.' '.$selectedStudent->last_name).' · '.$selectedStudent->student_id : '' }}"
                                    placeholder="Type a name or student ID"
                                    autocomplete="off"
                                    role="combobox"
                                    aria-haspopup="listbox"
                                    aria-autocomplete="list"
                                    aria-expanded="false"
                                    aria-controls="student-search-results"
                                    data-student-search
                                    data-search-url="{{ route('payments.students.search') }}"
                                    data-dues-url="{{ route('payments.students.dues', 'STUDENT_ID') }}"
                                    data-branch-id="{{ $branchId }}"
                                >
                                <div id="student-search-results" class="payment-student-results" role="listbox" aria-label="Matching students" data-student-results hidden></div>
                                <span class="payment-student-search-status small text-muted" data-student-search-status aria-live="polite">Search by student name or ID.</span>
                            </div>
                            <select id="student_id" name="student_id" class="d-none" data-student-id>
                                <option value="">Choose a student</option>
                                @if ($selectedStudent)
                                    @php
                                        $selectedEnrollment = $selectedStudent->enrollments->first();
                                    @endphp
                                    <option value="{{ $selectedStudent->id }}" data-year="{{ $selectedEnrollment?->academic_year_id }}" selected>
                                        {{ trim($selectedStudent->first_name.' '.$selectedStudent->last_name) }} · {{ $selectedStudent->student_id }}
                                    </option>
                                @endif
                            </select>
                            @error('student_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="form-group col-lg-5">
                            <label for="academic_year_id">Academic year</label>
                            <select class="form-control bt-select" id="academic_year_id" name="academic_year_id" data-live-search="true" data-size="6" data-width="100%" data-initial-year="{{ request('academic_year_id') }}" title="Use current enrollment">
                                <option value="">Use current enrollment</option>
                                @foreach ($years as $year)
                                    <option value="{{ $year->id }}" @selected(old('academic_year_id', request('academic_year_id')) == $year->id)>{{ $year->name }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">Change this only when collecting a previous year’s dues.</small>
                        </div>
                    </div>

                    <section class="dashboard-metrics payment-dashboard-metrics" data-fee-balance-summary data-balance="0" aria-label="Payment summary" aria-live="polite">
                        <div class="admin-stat">
                            <div class="admin-stat-content">
                                <small class="admin-stat-label" data-fee-balance-label>Select a student to view total due</small>
                                <strong class="admin-stat-value">₹<span data-fee-balance-total>0.00</span></strong>
                                <span class="admin-stat-detail" data-fee-balance-count>0 open items</span>
                            </div>
                            <span class="stat-icon bg-info" aria-hidden="true"><i class="fa-solid fa-wallet"></i></span>
                        </div>
                        <div class="admin-stat">
                            <div class="admin-stat-content">
                                <small class="admin-stat-label">Selected due items</small>
                                <strong class="admin-stat-value" data-selected-item-count>0</strong>
                                <span class="admin-stat-detail">Items included in this payment</span>
                            </div>
                            <span class="stat-icon bg-warning" aria-hidden="true"><i class="fa-solid fa-list-check"></i></span>
                        </div>
                        <div class="admin-stat">
                            <div class="admin-stat-content">
                                <small class="admin-stat-label">Total paid</small>
                                <strong class="admin-stat-value">₹<span data-total-paid-metric>0.00</span></strong>
                                <span class="admin-stat-detail" data-total-paid-detail>Amount to record</span>
                            </div>
                            <span class="stat-icon bg-success" aria-hidden="true"><i class="fa-solid fa-indian-rupee-sign"></i></span>
                        </div>
                    </section>

                    <div class="fee-no-dues" data-payment-empty-state hidden role="status" aria-live="polite">
                        <span class="fee-no-dues-icon" aria-hidden="true"><i class="fa-solid fa-circle-check"></i></span>
                        <div><strong>No outstanding dues</strong><p class="mb-0">This student has no unpaid fee items for the selected academic year.</p></div>
                    </div>

                    <section class="fee-due-items my-4" aria-labelledby="payment-due-items-title" data-payment-due-items hidden>
                        <div class="fee-due-items-heading">
                            <div>
                                <h3 id="payment-due-items-title" class="h6 mb-1">Due items</h3>
                                <p class="small text-muted mb-0">Dues are oldest first. Select items and enter the amount received for each.</p>
                            </div>
                            <button class="btn btn-sm btn-link" type="button" data-payment-select-all>Select all</button>
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
                                <tbody data-payment-due-rows></tbody>
                            </table>
                        </div>
                    </section>

                    <input id="amount" type="hidden" name="amount" value="0.00" data-total-paid-input>
                    <section class="fee-payment-summary" aria-labelledby="payment-bottom-title">
                        <div class="fee-payment-primary-row">
                            <div class="fee-payment-total">
                                <label id="payment-bottom-title" for="total_paid_display">Total paid</label>
                                <div class="input-group">
                                    <div class="input-group-prepend"><span class="input-group-text">₹</span></div>
                                    <input id="total_paid_display" class="form-control" type="text" value="0.00" readonly data-total-paid-display>
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label for="payment_modes">Payment method</label>
                                <select class="form-control bt-select" id="payment_modes" name="payment_modes[]" multiple data-live-search="true" data-width="100%" title="Select payment method(s)" data-payment-modes aria-required="true">
                                    @foreach (['cash' => 'Cash', 'upi' => 'UPI', 'card' => 'Card', 'bank_transfer' => 'Bank transfer', 'online' => 'Online'] as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group mb-0">
                                <label for="payment_reference">Payment reference <span class="text-muted font-weight-normal">(optional)</span></label>
                                <input id="payment_reference" class="form-control" name="payment_reference" type="text" maxlength="100" autocomplete="off" placeholder="Bank, UPI, or card reference">
                            </div>
                        </div>
                        <div class="form-group fee-payment-method-details" data-method-amount-section hidden>
                            <label>Payment method amounts</label>
                            <div class="fee-method-amount-list" data-method-amount-list></div>
                            <small class="form-text text-muted" data-method-amount-feedback role="status" aria-live="polite">Payment method amounts must equal total paid.</small>
                        </div>
                    </section>

                </div>

                <div class="fee-collection-footer">
                    <p class="small text-muted mb-0" data-fee-payment-message>Select a student with an outstanding balance to continue.</p>
                    <button class="btn btn-primary" type="submit" data-record-payment disabled>
                        <i class="fa-solid fa-receipt mr-1" aria-hidden="true"></i>Record payment and issue receipt
                    </button>
                </div>
            </form>

            <section class="fee-collection-success-banner mt-3" data-payment-recorded hidden role="status" aria-live="polite">
                <span class="fee-collection-success-icon" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
                <div class="flex-grow-1">
                    <strong>Payment recorded successfully</strong>
                    <small data-payment-success-details>Receipt is ready.</small>
                </div>
                <a class="btn btn-sm btn-outline-primary" href="#" target="_blank" rel="noopener" data-payment-receipt>View receipt</a>
                <button class="btn btn-sm btn-outline-primary" type="button" data-payment-print>Print</button>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-payment-share>Share</button>
            </section>
        </section>
    @endif

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('[data-fee-payment-form]');
            const student = document.getElementById('student_id');
            const studentSearch = document.querySelector('[data-student-search]');
            const studentResults = document.querySelector('[data-student-results]');
            const studentSearchStatus = document.querySelector('[data-student-search-status]');
            const year = document.getElementById('academic_year_id');
            const dueRowsBody = document.querySelector('[data-payment-due-rows]');
            let dueRows = [];
            const dueList = document.querySelector('[data-payment-due-items]');
            const emptyState = document.querySelector('[data-payment-empty-state]');
            const selectAllButton = document.querySelector('[data-payment-select-all]');
            const paidTotalInput = document.querySelector('[data-total-paid-input]');
            const paidTotalDisplay = document.querySelector('[data-total-paid-display]');
            const methodSelect = document.querySelector('[data-payment-modes]');
            const methodAmountSection = document.querySelector('[data-method-amount-section]');
            const methodAmountList = document.querySelector('[data-method-amount-list]');
            const summary = document.querySelector('[data-fee-balance-summary]');
            const balanceTotal = document.querySelector('[data-fee-balance-total]');
            const balanceLabel = document.querySelector('[data-fee-balance-label]');
            const balanceCount = document.querySelector('[data-fee-balance-count]');
            const selectedItemCount = document.querySelector('[data-selected-item-count]');
            const paidMetric = document.querySelector('[data-total-paid-metric]');
            const paidMetricDetail = document.querySelector('[data-total-paid-detail]');
            const submitButton = document.querySelector('[data-record-payment]');
            const message = document.querySelector('[data-fee-payment-message]');

            if (!form || !student || !studentSearch || !studentResults || !dueRowsBody || !year || !dueList || !paidTotalInput || !methodSelect || !summary) {
                return;
            }

            let selectedStudentLabel = studentSearch.value;
            let studentSearchTimer = null;
            let studentSearchController = null;
            let studentDuesController = null;

            const formatMoney = function (amount) {
                return (Number(amount) || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            };

            function selectedModes() {
                return Array.from(methodSelect.selectedOptions).map(function (option) {
                    return { value: option.value, label: option.textContent.trim() };
                });
            }

            function refreshSelect(select) {
                if (window.jQuery && jQuery.fn.selectpicker) {
                    if (window.refreshBtSelect) {
                        window.refreshBtSelect(select);
                    } else {
                        jQuery(select).selectpicker('refresh');
                    }
                }
            }

            function rebuildAcademicYears(academicYears, preferredYear, currentYear) {
                if (window.jQuery && jQuery.fn.selectpicker && jQuery(year).data('selectpicker')) {
                    jQuery(year).selectpicker('destroy');
                }

                year.replaceChildren(new Option('Use current enrollment', ''));
                const seenYearIds = new Set();

                (academicYears || []).forEach(function (academicYear) {
                    const yearId = String(academicYear.id);

                    if (seenYearIds.has(yearId)) {
                        return;
                    }

                    seenYearIds.add(yearId);
                    year.add(new Option(academicYear.name, yearId));
                });

                const requestedYear = String(preferredYear || '');
                const enrollmentYear = String(currentYear || '');
                year.value = seenYearIds.has(requestedYear)
                    ? requestedYear
                    : (seenYearIds.has(enrollmentYear) ? enrollmentYear : '');

                if (window.initBtSelect) {
                    window.initBtSelect(year);
                } else {
                    refreshSelect(year);
                }
            }

            function renderStudentResults(students) {
                studentResults.replaceChildren();

                if (!students.length) {
                    const empty = document.createElement('div');
                    empty.className = 'payment-student-result-empty';
                    empty.textContent = 'No matching active students found.';
                    studentResults.append(empty);
                } else {
                    students.forEach(function (result) {
                        const option = document.createElement('button');
                        option.type = 'button';
                        option.className = 'payment-student-result';
                        option.setAttribute('role', 'option');
                        option.id = 'student-result-' + result.id;
                        option.textContent = result.label;
                        option.addEventListener('click', function () {
                            chooseStudent(result);
                        });
                        studentResults.append(option);
                    });
                }

                studentResults.hidden = false;
                studentSearch.setAttribute('aria-expanded', 'true');
                studentSearchStatus.textContent = students.length
                    ? students.length + ' matching student' + (students.length === 1 ? '' : 's') + '.'
                    : 'Try another name or student ID.';
            }

            function clearCurrentStudent() {
                student.value = '';
                student.replaceChildren(new Option('Choose a student', ''));
                selectedStudentLabel = '';
                year.value = '';
                refreshSelect(year);
                dueRows = [];
                dueRowsBody.replaceChildren();
                updateTotals();
            }

            async function searchStudents(query) {
                if (studentSearchController) {
                    studentSearchController.abort();
                }

                studentSearchController = new AbortController();
                const url = new URL(studentSearch.dataset.searchUrl, window.location.origin);
                url.searchParams.set('q', query);

                if (studentSearch.dataset.branchId) {
                    url.searchParams.set('branch_id', studentSearch.dataset.branchId);
                }

                studentSearchStatus.textContent = 'Searching students…';

                try {
                    const response = await fetch(url, {
                        headers: { 'Accept': 'application/json' },
                        signal: studentSearchController.signal,
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.message || 'Student search failed.');
                    }

                    renderStudentResults(result.students || []);
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        studentSearchStatus.textContent = error.message;
                        studentResults.hidden = true;
                        studentSearch.setAttribute('aria-expanded', 'false');
                    }
                }
            }

            function renderDueRows(assignments) {
                dueRowsBody.replaceChildren();

                assignments.forEach(function (assignment) {
                    const row = document.createElement('tr');
                    const selectCell = document.createElement('td');
                    const checkbox = document.createElement('input');
                    const planCell = document.createElement('td');
                    const planName = document.createElement('strong');
                    const installmentName = document.createElement('small');
                    const amountCell = document.createElement('td');
                    const dueDateCell = document.createElement('td');
                    const paidCell = document.createElement('td');
                    const paidInput = document.createElement('input');

                    row.className = 'fee-due-table-row';
                    row.dataset.paymentDueRow = '';
                    row.dataset.student = student.value;
                    row.dataset.year = assignment.year_id || '';
                    row.dataset.balance = assignment.balance;
                    row.hidden = true;

                    checkbox.type = 'checkbox';
                    checkbox.id = 'payment_assignment_' + assignment.id;
                    checkbox.name = 'student_fee_assignment_ids[]';
                    checkbox.value = assignment.id;
                    checkbox.dataset.paymentDueCheckbox = '';
                    checkbox.setAttribute('aria-label', 'Select ' + assignment.plan_name);
                    selectCell.append(checkbox);

                    planName.textContent = assignment.plan_name;
                    installmentName.className = 'd-block text-muted';
                    installmentName.textContent = assignment.installment_name;
                    planCell.append(planName, installmentName);

                    amountCell.className = 'text-right text-nowrap';
                    amountCell.textContent = '₹' + formatMoney(assignment.balance);
                    dueDateCell.className = 'text-nowrap';
                    dueDateCell.textContent = assignment.due_date;

                    paidInput.id = 'payment_paid_' + assignment.id;
                    paidInput.className = 'form-control form-control-sm fee-due-paid-input';
                    paidInput.type = 'number';
                    paidInput.name = 'paid_amounts[' + assignment.id + ']';
                    paidInput.min = '0.01';
                    paidInput.max = assignment.balance;
                    paidInput.step = '0.01';
                    paidInput.placeholder = '0.00';
                    paidInput.disabled = true;
                    paidInput.dataset.paymentPaidInput = '';
                    paidInput.setAttribute('aria-label', 'Paid amount for ' + assignment.plan_name);
                    paidCell.append(paidInput);

                    row.append(selectCell, planCell, amountCell, dueDateCell, paidCell);
                    dueRowsBody.append(row);
                });

                dueRows = Array.from(dueRowsBody.querySelectorAll('[data-payment-due-row]'));
            }

            async function loadStudentDues(studentId, initialLoad) {
                if (studentDuesController) {
                    studentDuesController.abort();
                }

                studentDuesController = new AbortController();
                dueRows = [];
                dueRowsBody.replaceChildren();
                dueList.hidden = true;
                emptyState.hidden = true;
                message.textContent = 'Loading student fees…';
                submitButton.disabled = true;

                try {
                    const duesUrl = studentSearch.dataset.duesUrl.replace('STUDENT_ID', encodeURIComponent(studentId));
                    const response = await fetch(duesUrl, {
                        headers: { 'Accept': 'application/json' },
                        signal: studentDuesController.signal,
                    });
                    const result = await response.json();

                    if (!response.ok) {
                        throw new Error(result.message || 'Could not load this student’s fee details.');
                    }

                    const requestedYear = initialLoad ? year.dataset.initialYear : '';
                    rebuildAcademicYears(result.years, requestedYear, result.current_year_id);

                    renderDueRows(result.assignments || []);
                    selectCurrentStudentYear(initialLoad);
                    studentSearchStatus.textContent = 'Student selected. Fee details are up to date.';

                    if (!initialLoad) {
                        summary.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        clearCurrentStudent();
                        studentSearchStatus.textContent = error.message;
                        message.textContent = error.message;
                        message.classList.add('text-danger');
                    }
                }
            }

            function chooseStudent(result) {
                window.clearTimeout(studentSearchTimer);
                if (studentSearchController) {
                    studentSearchController.abort();
                }

                const option = new Option(result.label, result.id, true, true);
                option.dataset.year = result.current_year_id || '';
                student.replaceChildren(new Option('Choose a student', ''), option);
                student.value = String(result.id);
                studentSearch.value = result.label;
                selectedStudentLabel = result.label;
                studentResults.hidden = true;
                studentResults.replaceChildren();
                studentSearch.setAttribute('aria-expanded', 'false');
                studentSearch.focus();
                form.dataset.initialAssignment = '';
                form.dataset.initialYear = '';
                message.classList.remove('text-danger');
                loadStudentDues(result.id, false);
            }

            studentSearch.addEventListener('input', function () {
                if (studentSearchController) {
                    studentSearchController.abort();
                }

                studentResults.hidden = true;
                studentResults.replaceChildren();
                studentSearch.setAttribute('aria-expanded', 'false');

                if (student.value && studentSearch.value !== selectedStudentLabel) {
                    clearCurrentStudent();
                }

                const query = studentSearch.value.trim();
                window.clearTimeout(studentSearchTimer);

                if (query.length < 2) {
                    studentResults.hidden = true;
                    studentSearch.setAttribute('aria-expanded', 'false');
                    studentSearchStatus.textContent = 'Enter at least 2 characters to search.';
                    return;
                }

                studentSearchTimer = window.setTimeout(function () {
                    searchStudents(query);
                }, 250);
            });

            studentSearch.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') {
                    studentResults.hidden = true;
                    studentSearch.setAttribute('aria-expanded', 'false');
                } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                    const options = Array.from(studentResults.querySelectorAll('[role="option"]'));

                    if (options.length) {
                        event.preventDefault();
                        studentResults.hidden = false;
                        studentSearch.setAttribute('aria-expanded', 'true');
                        options[event.key === 'ArrowDown' ? 0 : options.length - 1].focus();
                    }
                } else if (event.key === 'Enter' && ! studentResults.hidden) {
                    const firstOption = studentResults.querySelector('[role="option"]');

                    if (firstOption) {
                        event.preventDefault();
                        firstOption.click();
                    }
                }
            });

            studentResults.addEventListener('keydown', function (event) {
                const options = Array.from(studentResults.querySelectorAll('[role="option"]'));
                const currentIndex = options.indexOf(document.activeElement);

                if (event.key === 'Escape') {
                    event.preventDefault();
                    studentResults.hidden = true;
                    studentSearch.setAttribute('aria-expanded', 'false');
                    studentSearch.focus();
                } else if (event.key === 'ArrowDown' && options.length) {
                    event.preventDefault();
                    options[(currentIndex + 1) % options.length].focus();
                } else if (event.key === 'ArrowUp' && options.length) {
                    event.preventDefault();

                    if (currentIndex <= 0) {
                        studentSearch.focus();
                    } else {
                        options[currentIndex - 1].focus();
                    }
                }
            });

            document.addEventListener('click', function (event) {
                if (!event.target.closest('[data-student-picker]')) {
                    studentResults.hidden = true;
                    studentSearch.setAttribute('aria-expanded', 'false');
                }
            });

            function renderMethodAmounts() {
                const previousValues = new Map(Array.from(methodAmountList.querySelectorAll('[data-method-amount]')).map(function (input) {
                    return [input.dataset.method, input.value];
                }));
                const modes = selectedModes();
                methodAmountList.replaceChildren();
                methodAmountSection.hidden = modes.length < 2;

                if (modes.length < 2) {
                    return;
                }

                modes.forEach(function (mode) {
                    const group = document.createElement('div');
                    const label = document.createElement('label');
                    const input = document.createElement('input');
                    const id = 'method_amount_' + mode.value;

                    group.className = 'form-group mb-2';
                    label.htmlFor = id;
                    label.textContent = mode.label;
                    input.id = id;
                    input.className = 'form-control';
                    input.type = 'number';
                    input.name = 'method_amounts[' + mode.value + ']';
                    input.min = '0.01';
                    input.step = '0.01';
                    input.placeholder = '0.00';
                    input.required = true;
                    input.dataset.method = mode.value;
                    input.dataset.methodAmount = '';
                    input.value = previousValues.get(mode.value) || '';
                    input.addEventListener('input', updateTotals);
                    group.append(label, input);
                    methodAmountList.append(group);
                });
            }

            function updateTotals() {
                let totalDue = 0;
                let totalPaid = 0;
                let selectedItems = 0;
                let itemAmountsValid = true;
                const visibleRows = dueRows.filter(function (row) {
                    const matches = Boolean(student.value)
                        && row.dataset.student === student.value
                        && row.dataset.year === (year.value || student.selectedOptions[0]?.dataset.year || '');
                    const checkbox = row.querySelector('[data-payment-due-checkbox]');
                    const paidInput = row.querySelector('[data-payment-paid-input]');
                    const balance = Number(row.dataset.balance) || 0;

                    row.hidden = !matches || balance <= 0;
                    checkbox.disabled = !matches || balance <= 0;
                    paidInput.disabled = !checkbox.checked || checkbox.disabled;
                    if (matches && balance > 0) totalDue += balance;

                    if (checkbox.checked && !checkbox.disabled) {
                        const paid = Number(paidInput.value) || 0;
                        totalPaid += paid;
                        selectedItems += 1;
                        if (paid <= 0 || paid > balance) itemAmountsValid = false;
                    }

                    return matches && balance > 0;
                });
                const modes = selectedModes();
                const tenderInputs = Array.from(methodAmountList.querySelectorAll('[data-method-amount]'));
                const tenderTotal = tenderInputs.reduce(function (sum, input) {
                    return sum + Math.round((Number(input.value) || 0) * 100);
                }, 0);
                const totalPaidCents = Math.round(totalPaid * 100);
                const methodsValid = modes.length === 1
                    || (modes.length > 1 && tenderInputs.every(function (input) { return Number(input.value) > 0; }) && tenderTotal === totalPaidCents);
                const methodAmountFeedback = methodAmountSection.querySelector('[data-method-amount-feedback]');

                if (modes.length > 1) {
                    methodAmountFeedback.textContent = methodsValid
                        ? 'Payment methods match total paid: ₹' + formatMoney(tenderTotal / 100) + '.'
                        : 'Payment methods total ₹' + formatMoney(tenderTotal / 100) + ' · Total paid ₹' + formatMoney(totalPaidCents / 100) + '. These amounts must match.';
                    methodAmountFeedback.classList.toggle('text-danger', !methodsValid);
                    methodAmountFeedback.classList.toggle('text-success', methodsValid);
                } else {
                    methodAmountFeedback.textContent = 'Payment method amounts must equal total paid.';
                    methodAmountFeedback.classList.remove('text-danger', 'text-success');
                }

                balanceTotal.textContent = formatMoney(totalDue);
                balanceLabel.textContent = student.value ? 'Total due' : 'Select a student to view total due';
                balanceCount.textContent = visibleRows.length + (visibleRows.length === 1 ? ' open item' : ' open items');
                selectedItemCount.textContent = String(selectedItems);
                summary.dataset.balance = totalDue.toFixed(2);
                paidTotalDisplay.value = formatMoney(totalPaid);
                paidMetric.textContent = formatMoney(totalPaid);
                paidMetricDetail.textContent = !student.value
                    ? 'Amount to record'
                    : (selectedItems > 0 ? 'Amount to collect now' : 'Select due items to calculate');
                paidTotalInput.value = totalPaid.toFixed(2);
                dueList.hidden = !student.value || visibleRows.length === 0;
                emptyState.hidden = !student.value || visibleRows.length > 0;
                selectAllButton.textContent = visibleRows.length && visibleRows.every(function (row) {
                    return row.querySelector('[data-payment-due-checkbox]').checked;
                }) ? 'Clear all' : 'Select all';

                const canSubmit = totalPaidCents > 0 && totalPaid <= totalDue && itemAmountsValid && modes.length > 0 && methodsValid;
                submitButton.disabled = !canSubmit;
                message.textContent = modes.length > 1 && !methodsValid
                    ? 'Enter payment method amounts that add up to total paid.'
                    : (canSubmit
                        ? 'Review the total and issue a receipt.'
                        : (student.value && visibleRows.length === 0
                            ? 'No unpaid fee items are available for this academic year.'
                            : 'Select due items and enter the amounts received.'));
            }

            function selectCurrentStudentYear(useInitialAssignment) {
                dueRows.forEach(function (row) {
                    const checkbox = row.querySelector('[data-payment-due-checkbox]');
                    const paidInput = row.querySelector('[data-payment-paid-input]');
                    const matchesStudentYear = Boolean(student.value)
                        && row.dataset.student === student.value
                        && row.dataset.year === (year.value || student.selectedOptions[0]?.dataset.year || '')
                        && Number(row.dataset.balance) > 0;
                    const selected = Boolean(useInitialAssignment && form.dataset.initialAssignment)
                        && checkbox.value === form.dataset.initialAssignment
                        && matchesStudentYear;

                    checkbox.checked = selected;
                    paidInput.value = selected ? Number(row.dataset.balance).toFixed(2) : '';
                });
                form.dataset.initialAssignment = '';
                updateTotals();
            }

            year.addEventListener('change', function () { selectCurrentStudentYear(false); });
            dueRowsBody.addEventListener('change', function (event) {
                const checkbox = event.target.closest('[data-payment-due-checkbox]');

                if (!checkbox) {
                    return;
                }

                const row = checkbox.closest('[data-payment-due-row]');
                const paidInput = row.querySelector('[data-payment-paid-input]');
                paidInput.value = checkbox.checked ? Number(row.dataset.balance).toFixed(2) : '';
                updateTotals();
            });
            dueRowsBody.addEventListener('input', function (event) {
                if (event.target.matches('[data-payment-paid-input]')) {
                    updateTotals();
                }
            });
            selectAllButton.addEventListener('click', function () {
                const visibleRows = dueRows.filter(function (row) { return !row.hidden; });
                const selectItems = visibleRows.some(function (row) {
                    return !row.querySelector('[data-payment-due-checkbox]').checked;
                });
                visibleRows.forEach(function (row) {
                    const checkbox = row.querySelector('[data-payment-due-checkbox]');
                    const paidInput = row.querySelector('[data-payment-paid-input]');
                    checkbox.checked = selectItems;
                    paidInput.value = selectItems ? Number(row.dataset.balance).toFixed(2) : '';
                });
                updateTotals();
            });
            methodSelect.addEventListener('change', function () {
                renderMethodAmounts();
                updateTotals();
            });

            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                submitButton.disabled = true;
                message.classList.remove('text-danger');
                message.textContent = 'Recording payment…';

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        body: new FormData(form),
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    const result = await response.json();
                    if (!response.ok) {
                        const errors = result.errors ? Object.values(result.errors).flat() : [result.message || 'Payment could not be recorded.'];
                        throw new Error(errors.join(' '));
                    }

                    const success = document.querySelector('[data-payment-recorded]');
                    success.querySelector('[data-payment-receipt]').href = result.receipt_url;
                    success.querySelector('[data-payment-success-details]').textContent = 'Receipt ' + (result.payment.receipt_number || 'issued') + ' · ₹' + result.payment.amount;
                    success.hidden = false;
                    success.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    form.querySelectorAll('input, select, button').forEach(function (field) { field.disabled = true; });
                    message.textContent = 'Payment recorded. Print or share the receipt below.';
                } catch (error) {
                    message.classList.add('text-danger');
                    message.textContent = error.message;
                    updateTotals();
                }
            });

            document.querySelector('[data-payment-print]').addEventListener('click', function () {
                const receiptWindow = window.open(document.querySelector('[data-payment-receipt]').href, '_blank');
                if (receiptWindow) receiptWindow.addEventListener('load', function () { receiptWindow.print(); }, { once: true });
            });
            document.querySelector('[data-payment-share]').addEventListener('click', async function () {
                const receiptUrl = document.querySelector('[data-payment-receipt]').href;
                if (navigator.share) {
                    await navigator.share({ title: 'School fee receipt', text: 'Payment receipt', url: receiptUrl });
                } else if (navigator.clipboard?.writeText) {
                    await navigator.clipboard.writeText(receiptUrl);
                    message.textContent = 'Receipt link copied.';
                } else {
                    window.prompt('Copy the receipt link:', receiptUrl);
                }
            });

            renderMethodAmounts();
            if (student.value) {
                loadStudentDues(student.value, true);
            } else {
                updateTotals();
            }
        });
    </script>
@endsection
