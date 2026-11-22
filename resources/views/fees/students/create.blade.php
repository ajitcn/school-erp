@extends('layouts.app')

@section('content')
    <x-admin.page-header title="Set up student fees" description="{{ $student->first_name }} {{ $student->last_name }} · {{ $student->student_id }} · {{ $enrollment?->academicYear?->name ?? 'No active enrollment' }}">
        <x-slot:actions><a class="btn btn-outline-secondary" href="{{ route('students.show', $student) }}">Back to student</a></x-slot:actions>
    </x-admin.page-header>

    @if(!$enrollment)
        <x-admin.card><x-admin.empty-state title="An active enrollment is required" description="Add the student’s academic year, class, course, or semester enrollment before setting up fees." /></x-admin.card>
    @else
        <section class="admin-card" aria-labelledby="setup-student-fee">
            <div class="card-header"><h2 id="setup-student-fee">Create student fee schedule</h2><p class="mb-0 text-muted">Choose a master plan, set the total charge, then collect in full or split into installments.</p></div>
            <form method="post" action="{{ route('students.fees.store', $student) }}" class="p-4" data-student-fee-form>
                @csrf
                <div class="form-row">
                    <div class="form-group col-md-6"><label for="fee_structure_id">Fee plan</label><select id="fee_structure_id" name="fee_structure_id" class="form-control bt-select" data-live-search="true" data-fee-plan-select required><option value="">Choose a matching plan</option>@foreach($plans as $plan)<option value="{{ $plan->id }}" data-default-amount="{{ number_format($plan->default_amount, 2, '.', '') }}" @selected(old('fee_structure_id') == $plan->id)>{{ $plan->name }}</option>@endforeach</select>@error('fee_structure_id')<small class="text-danger">{{ $message }}</small>@enderror</div>
                    <div class="form-group col-md-3"><label for="academic_year_id">Academic year</label><select id="academic_year_id" name="academic_year_id" class="form-control" required><option value="{{ $enrollment->academic_year_id }}">{{ $enrollment->academicYear?->name }}</option></select></div>
                    <div class="form-group col-md-3"><label for="total_amount">Total amount</label><div class="input-group"><div class="input-group-prepend"><span class="input-group-text">₹</span></div><input id="total_amount" name="total_amount" type="number" min="0.01" step="0.01" class="form-control" data-fee-plan-amount value="{{ old('total_amount') }}" required></div><small class="form-text text-muted">Prefilled from the fee plan; editable for this student.</small>@error('total_amount')<small class="text-danger">{{ $message }}</small>@enderror</div>
                    <div class="form-group col-md-4"><label for="payment_plan">Payment mode</label><select id="payment_plan" name="payment_plan" class="form-control" required><option value="full" @selected(old('payment_plan', 'full') === 'full')>Full payment</option><option value="installment" @selected(old('payment_plan') === 'installment')>Installments</option></select></div>
                    <div class="form-group col-md-4" data-full-due-date><label for="full_due_date">Due date</label><input id="full_due_date" name="full_due_date" type="text" class="form-control js-datepicker" autocomplete="off" placeholder="DD/MM/YYYY" value="{{ old('full_due_date') }}"></div>
                </div>
                <div class="border rounded p-3 mb-3" data-installment-section hidden>
                    <div class="d-flex justify-content-between align-items-center mb-3"><div><h3 class="h6 mb-1">Installment details</h3><p class="small text-muted mb-0">Enter each due date and amount. The amounts must equal the total above.</p></div><button type="button" class="btn btn-sm btn-outline-primary" data-add-fee-installment>Add installment</button></div>
                    <div data-fee-installments>
                        @foreach(old('installments', [['name' => 'Installment 1', 'amount' => '', 'due_date' => ''], ['name' => 'Installment 2', 'amount' => '', 'due_date' => '']]) as $index => $row)
                            <div class="form-row align-items-end" data-fee-installment-row>
                                <div class="form-group col-md-4"><label for="inst-{{ $index }}-name">Name</label><input id="inst-{{ $index }}-name" name="installments[{{ $index }}][name]" class="form-control" value="{{ $row['name'] ?? '' }}" placeholder="Installment {{ $index + 1 }}"></div>
                                <div class="form-group col-md-3"><label for="inst-{{ $index }}-amount">Amount</label><input id="inst-{{ $index }}-amount" name="installments[{{ $index }}][amount]" type="number" min="0.01" step="0.01" class="form-control" value="{{ $row['amount'] ?? '' }}" required></div>
                                <div class="form-group col-md-3"><label for="inst-{{ $index }}-date">Due date</label><input id="inst-{{ $index }}-date" name="installments[{{ $index }}][due_date]" type="text" class="form-control js-datepicker" autocomplete="off" placeholder="DD/MM/YYYY" value="{{ $row['due_date'] ?? '' }}" required></div>
                                <div class="form-group col-md-2"><button type="button" class="btn btn-outline-danger" data-remove-fee-installment>Remove</button></div>
                            </div>
                        @endforeach
                    </div>
                    @error('installments')<small class="text-danger d-block">{{ $message }}</small>@enderror
                </div>
                <button type="submit" class="btn btn-primary">Save student fee schedule</button>
            </form>
        </section>
    @endif

    <section class="admin-card mt-4" aria-labelledby="student-current-fees">
        <div class="card-header"><h2 id="student-current-fees">Current fee installments</h2><a class="btn btn-sm btn-outline-primary" href="{{ route('payments.create', ['student_id' => $student->id]) }}">Collect payment</a></div>
        <x-admin.table><thead><tr><th>Plan</th><th>Installment</th><th>Due date</th><th>Fee</th><th>Balance</th><th>Status</th><th></th></tr></thead><tbody>
            @forelse($assignments as $assignment)
                @php($balance = $assignment->balanceDue())
                <tr><td>{{ $assignment->component?->structure?->name }}</td><td>{{ $assignment->component?->name }}</td><td>{{ $assignment->component?->due_date?->format('d M Y') ?? '—' }}</td><td>₹{{ number_format($assignment->totalDue(), 2) }}</td><td>₹{{ number_format($balance, 2) }}</td><td><x-admin.badge :tone="$balance <= 0 ? 'success' : ($balance < $assignment->totalDue() ? 'warning' : 'slate')">{{ $balance <= 0 ? 'Paid' : ($balance < $assignment->totalDue() ? 'Part paid' : 'Due') }}</x-admin.badge></td><td>@if($balance > 0)<a class="btn btn-sm btn-primary" href="{{ route('payments.create', ['student_id' => $student->id, 'student_fee_assignment_id' => $assignment->id]) }}">Collect</a>@endif</td></tr>
            @empty<tr><td colspan="7"><x-admin.empty-state title="No fees set up yet" description="Create a student fee schedule above." /></td></tr>@endforelse
        </tbody></x-admin.table>
    </section>

    <template id="student-fee-installment-template"><div class="form-row align-items-end" data-fee-installment-row><div class="form-group col-md-4"><label>Name</label><input class="form-control" data-field="name" placeholder="Installment"></div><div class="form-group col-md-3"><label>Amount</label><input class="form-control" data-field="amount" type="number" min="0.01" step="0.01" required></div><div class="form-group col-md-3"><label>Due date</label><input class="form-control js-datepicker" data-field="due_date" type="text" autocomplete="off" placeholder="DD/MM/YYYY" required></div><div class="form-group col-md-2"><button type="button" class="btn btn-outline-danger" data-remove-fee-installment>Remove</button></div></div></template>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mode = document.getElementById('payment_plan');
            const feePlan = document.querySelector('[data-fee-plan-select]');
            const totalAmount = document.querySelector('[data-fee-plan-amount]');
            const fullDate = document.querySelector('[data-full-due-date]');
            const installmentSection = document.querySelector('[data-installment-section]');
            const list = document.querySelector('[data-fee-installments]');
            const template = document.getElementById('student-fee-installment-template');
            if (!mode || !fullDate || !installmentSection || !list || !template || !totalAmount) return;
            const updateMode = function () {
                const installmentMode = mode.value === 'installment';
                installmentSection.hidden = !installmentMode;
                fullDate.hidden = installmentMode;
                document.getElementById('full_due_date').required = !installmentMode;
                installmentSection.querySelectorAll('[name$="[amount]"], [name$="[due_date]"]').forEach(function (field) {
                    field.required = installmentMode;
                    field.disabled = !installmentMode;
                });
                installmentSection.querySelectorAll('[name$="[name]"]').forEach(function (field) {
                    field.disabled = !installmentMode;
                });
            };
            mode?.addEventListener('change', updateMode);
            updateMode();
            feePlan?.addEventListener('change', function () {
                const selectedAmount = Number(feePlan.selectedOptions[0]?.dataset.defaultAmount);
                if (selectedAmount > 0) totalAmount.value = selectedAmount.toFixed(2);
            });
            if (feePlan?.value && !totalAmount.value) feePlan.dispatchEvent(new Event('change'));
            document.querySelector('[data-add-fee-installment]')?.addEventListener('click', function () {
                const index = Date.now();
                const fragment = template.content.cloneNode(true);
                fragment.querySelectorAll('[data-field]').forEach(function (field) {
                    field.name = 'installments[' + index + '][' + field.dataset.field + ']';
                    field.id = 'installments-' + index + '-' + field.dataset.field;
                    field.previousElementSibling.htmlFor = field.id;
                });
                list.appendChild(fragment);
                const date = list.lastElementChild.querySelector('.js-datepicker');
                if (window.jQuery && jQuery.fn.datepicker) jQuery(date).datepicker({ format: 'dd/mm/yyyy', autoclose: true, todayHighlight: true, orientation: 'bottom auto' });
            });
            list?.addEventListener('click', function (event) {
                const remove = event.target.closest('[data-remove-fee-installment]');
                if (remove && list.querySelectorAll('[data-fee-installment-row]').length > 2) remove.closest('[data-fee-installment-row]').remove();
            });
        });
    </script>
@endsection
