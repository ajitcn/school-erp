<div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable" role="document">
    <div class="modal-content">
        <div class="modal-header">
            <div>
                <h2 id="student-fee-modal-title" class="modal-title h5">Add fees</h2>
                <p class="small text-muted mb-0">{{ $student->first_name }} {{ $student->last_name }} · {{ $student->student_id }}</p>
            </div>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        </div>

        <div class="modal-body">
            <div data-fee-edit-feedback></div>
            @if($enrollment)
                <div class="alert alert-danger d-none" role="alert" data-modal-errors></div>
                <div class="d-flex flex-wrap align-items-center mb-3 text-muted small">
                    <span class="mr-3"><i class="fa-solid fa-building-columns mr-1" aria-hidden="true"></i>{{ $student->branch?->name }}</span>
                    <span><i class="fa-regular fa-calendar mr-1" aria-hidden="true"></i>{{ $enrollment->academicYear?->name }}</span>
                </div>

                @if($plans->isEmpty())
                    <div class="alert alert-info">No active fee plan matches this student’s branch and enrollment. Create a fee plan first.</div>
                @endif
                @if($availablePlans->isEmpty() && $unpricedPlans->isEmpty() && $plans->isNotEmpty())
                    <div class="alert alert-info">All matching fee plans have already been added for this academic year.</div>
                @endif
                @if($unpricedPlans->isNotEmpty())
                    <div class="alert alert-warning">Add a standard amount to these plans before using them: {{ $unpricedPlans->pluck('name')->unique()->join(', ') }}. Edit them from Fee structures.</div>
                @endif

                @if($existingFeeGroups->isNotEmpty())
                    <section class="fee-modal-existing mb-4" aria-labelledby="existing-student-fees-title">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h3 id="existing-student-fees-title" class="h6 mb-0">Already assigned</h3>
                            <span class="badge badge-light">{{ $existingFeeGroups->count() }} fee plan(s)</span>
                        </div>
                        <div class="fee-modal-existing-list">
                            @foreach($existingFeeGroups as $feeGroup)
                                @if(!$loop->first)
                                    <hr class="fee-modal-group-divider">
                                @endif
                                <section class="fee-modal-existing-group" aria-label="{{ $feeGroup['name'] }}">
                                    <div class="fee-modal-existing-row">
                                        <div>
                                            <strong>{{ $feeGroup['name'] }}</strong>
                                            <small class="d-block text-muted">{{ $feeGroup['installment_count'] }} {{ \Illuminate\Support\Str::plural('installment', $feeGroup['installment_count']) }} · Added {{ $feeGroup['created_at']?->format('d M Y, h:i A') ?? 'date unavailable' }}</small>
                                        </div>
                                        <div class="d-flex align-items-center">
                                            <div class="text-right"><strong>₹{{ number_format($feeGroup['balance'], 2) }} due</strong><small class="d-block text-muted">of ₹{{ number_format($feeGroup['total'], 2) }}</small></div>
                                            @if($feeGroup['can_remove'] && $feeGroup['fee_structure_id'])
                                                <form class="ml-2" method="post" action="{{ route('students.fees.structures.assignments.destroy', [$student, $feeGroup['fee_structure_id']]) }}" data-remove-fee-plan-form>
                                                    @csrf
                                                    @method('delete')
                                                    <input type="hidden" name="academic_year_id" value="{{ $enrollment->academic_year_id }}">
                                                    <div class="alert alert-danger d-none" role="alert" data-remove-errors></div>
                                                    <button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Remove {{ $feeGroup['name'] }} fee plan">Remove</button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="fee-modal-existing-installments">
                                        @foreach($feeGroup['assignments'] as $assignment)
                                            @php($canEditAssignment = $assignment->component && $assignment->allocations->isEmpty())
                                            <div class="fee-modal-assignment-row">
                                                <div>
                                                    <strong>{{ $assignment->component?->name ?? 'Fee installment' }}</strong>
                                                    <small class="d-block text-muted">Due {{ $assignment->component?->due_date?->format('d M Y') ?? 'date not set' }} · Added {{ $assignment->created_at?->format('d M Y, h:i A') ?? 'date unavailable' }}</small>
                                                </div>
                                                <div class="d-flex align-items-center">
                                                    <strong class="mr-2">₹{{ number_format($assignment->totalDue(), 2) }}</strong>
                                                    @if($canEditAssignment)
                                                        <button class="btn btn-sm btn-outline-primary" type="button" data-toggle="collapse" data-target="#fee-assignment-edit-{{ $assignment->id }}" aria-expanded="false" aria-controls="fee-assignment-edit-{{ $assignment->id }}">Edit</button>
                                                    @else
                                                        <span class="badge badge-light">Collected</span>
                                                    @endif
                                                </div>
                                            </div>
                                            @if($canEditAssignment)
                                                <div class="collapse fee-modal-assignment-editor" id="fee-assignment-edit-{{ $assignment->id }}">
                                                    <form method="post" action="{{ route('students.fees.assignments.update', [$student, $assignment]) }}" data-fee-edit-form>
                                                        @csrf
                                                        @method('put')
                                                        <div class="alert alert-danger d-none" role="alert" data-edit-errors></div>
                                                        <div class="form-row align-items-end">
                                                            <div class="form-group col-md-5"><label for="fee-assignment-name-{{ $assignment->id }}">Installment name</label><input id="fee-assignment-name-{{ $assignment->id }}" class="form-control" type="text" name="name" value="{{ $assignment->component?->name }}" maxlength="100" required></div>
                                                            <div class="form-group col-md-3"><label for="fee-assignment-amount-{{ $assignment->id }}">Amount</label><input id="fee-assignment-amount-{{ $assignment->id }}" class="form-control" type="number" name="amount" min="0.01" step="0.01" value="{{ number_format((float) $assignment->amount, 2, '.', '') }}" required></div>
                                                            <div class="form-group col-md-3"><label for="fee-assignment-date-{{ $assignment->id }}">Due date</label><input id="fee-assignment-date-{{ $assignment->id }}" class="form-control js-datepicker" type="text" name="due_date" value="{{ $assignment->component?->due_date?->format('d/m/Y') }}" autocomplete="off" placeholder="DD/MM/YYYY" required></div>
                                                            <div class="form-group col-md-1"><button class="btn btn-primary btn-block" type="submit" aria-label="Save installment changes"><i class="fa-solid fa-check" aria-hidden="true"></i></button></div>
                                                        </div>
                                                    </form>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if($availablePlans->isNotEmpty())
                    <form id="student-add-fees-form" method="post" action="{{ route('students.fees.store', $student) }}" data-fee-modal-form data-setup-fee-form>
                        @csrf
                        <input type="hidden" name="academic_year_id" value="{{ $enrollment->academic_year_id }}">
                        <section aria-labelledby="new-fee-items-title">
                            <div class="mb-3">
                                <h3 id="new-fee-items-title" class="h6 mb-1">New fees</h3>
                                <p class="small text-muted mb-0">Choose a plan and payment option. For installments, the schedule must add up to the fee amount.</p>
                            </div>
                            <div class="alert alert-danger d-none" role="alert" data-modal-errors></div>
                            <div class="fee-modal-items" data-fee-items></div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-add-fee-card><i class="fa-solid fa-plus mr-1" aria-hidden="true"></i>Add more fee</button>
                        </section>
                    </form>
                @endif
            @else
                <div class="alert alert-warning mb-0">Add an active academic enrollment before setting this student’s fees.</div>
            @endif
        </div>

        <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
            @if($enrollment && $availablePlans->isNotEmpty())
                <button type="submit" class="btn btn-primary" data-modal-save-fee form="student-add-fees-form" disabled>Save fees</button>
            @endif
        </div>
    </div>
</div>

@if($enrollment && $availablePlans->isNotEmpty())
    <template data-fee-card-template>
        <section class="fee-modal-item" data-fee-item>
            <div class="form-row align-items-end fee-modal-entry-row">
                <div class="form-group col-lg-4">
                    <label data-label-for="fee_structure_id">Fee</label>
                    <select class="form-control bt-select" data-fee-field="fee_structure_id" data-fee-plan-select data-live-search="true" required>
                        <option value="">Choose a fee plan</option>
                        @foreach($availablePlans as $plan)
                            @php($planContext = collect([$plan->academicYear?->name, $plan->schoolClass?->name, $plan->course?->name, $plan->semester?->name])->filter()->join(' · '))
                            <option value="{{ $plan->id }}" data-default-amount="{{ number_format($plan->default_amount, 2, '.', '') }}">{{ $plan->name }} · ₹{{ number_format($plan->default_amount, 2) }}{{ $planContext ? ' · '.$planContext : '' }}</option>
                        @endforeach
                    </select>
                    <small class="text-danger d-none" data-fee-plan-error>This plan is already selected in another fee row.</small>
                </div>
                <div class="form-group col-lg-2">
                    <label data-label-for="total_amount">Amount</label>
                    <div class="input-group"><div class="input-group-prepend"><span class="input-group-text">₹</span></div><input class="form-control" data-fee-field="total_amount" data-fee-plan-amount type="number" min="0.01" step="0.01" required></div>
                </div>
                <div class="form-group col-lg-2">
                    <label data-label-for="payment_plan">Payment option</label>
                    <select class="form-control" data-fee-field="payment_plan" data-fee-mode required><option value="full">Full</option><option value="installment">Installments</option></select>
                </div>
                <div class="form-group col-lg-3" data-fee-full-date>
                    <label data-label-for="full_due_date">Due date</label>
                    <input class="form-control js-datepicker" data-fee-field="full_due_date" type="text" autocomplete="off" placeholder="DD/MM/YYYY" required>
                </div>
                <div class="form-group col-lg-1 fee-modal-remove-group">
                    <label class="sr-only">Remove fee</label>
                    <button class="btn btn-outline-danger btn-block" type="button" data-remove-fee-card aria-label="Remove fee"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="fee-modal-installment-box" data-fee-installment-section hidden>
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-2">
                    <div><h4 class="h6 mb-1">Installments</h4><p class="small text-muted mb-0">Scheduled amount must match this fee.</p></div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2 mt-md-0" data-add-installment><i class="fa-solid fa-plus mr-1" aria-hidden="true"></i>Add installment</button>
                </div>
                <div data-installment-rows></div>
                <div class="fee-modal-total" aria-live="polite"><span data-schedule-message>Enter the installment amounts.</span><strong>Total: ₹<span data-schedule-total>0.00</span></strong></div>
            </div>
        </section>
    </template>
    <template data-installment-row-template>
        <div class="form-row align-items-end" data-installment-row>
            <div class="form-group col-md-4"><label data-label-for="name">Installment name</label><input class="form-control" data-installment-field="name" placeholder="Installment"></div>
            <div class="form-group col-md-3"><label data-label-for="amount">Amount</label><input class="form-control" data-installment-field="amount" type="number" min="0.01" step="0.01"></div>
            <div class="form-group col-md-3"><label data-label-for="due_date">Due date</label><input class="form-control js-datepicker" data-installment-field="due_date" type="text" autocomplete="off" placeholder="DD/MM/YYYY"></div>
            <div class="form-group col-md-2"><button type="button" class="btn btn-outline-danger" data-remove-installment aria-label="Remove installment"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button></div>
        </div>
    </template>
@endif
