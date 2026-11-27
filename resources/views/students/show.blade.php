@extends('layouts.app')
@section('content')
    @php($statusTone=['active'=>'success','inactive'=>'slate','graduated'=>'primary','transferred'=>'warning','withdrawn'=>'danger'])
    <x-admin.page-header title="{{ $student->first_name }} {{ $student->last_name }}" description="{{ $student->student_id }} · {{ $student->admission_number }} · {{ $student->branch?->name }}">
        <x-slot:actions><x-admin.badge :tone="$statusTone[$student->status]??'slate'">{{ ucfirst($student->status) }}</x-admin.badge>@if(auth()->user()->hasPermission('fees.assign'))<button class="btn btn-outline-primary ml-2" type="button" data-open-fee-modal="{{ route('students.fees.setup-modal',$student) }}">Add Fee</button>@endif @if(auth()->user()->hasPermission('payments.collect'))<button class="btn btn-primary ml-2" type="button" data-open-fee-modal="{{ route('students.fees.collect-modal',$student) }}">Collect Fee</button>@endif<a class="btn btn-outline-secondary ml-2" href="{{ route('students.edit',$student) }}">Edit student</a></x-slot:actions>
    </x-admin.page-header>

    @if (session('portal_password_link'))
        <div class="alert alert-info" role="status">
            <strong>{{ ucfirst(session('portal_password_link.account_type')) }} password setup link for {{ session('portal_password_link.account_name') }}</strong>
            <p class="mb-2">Login ID: {{ session('portal_password_link.identifier') ?: 'No email or mobile recorded' }}. Share this one-time link directly; it expires in {{ session('portal_password_link.expires_in') }} minutes.</p>
            <div class="input-group">
                <input class="form-control" type="text" value="{{ session('portal_password_link.url') }}" readonly data-password-setup-link>
                <div class="input-group-append">
                    <button class="btn btn-outline-primary" type="button" data-copy-password-setup-link>Copy link</button>
                </div>
            </div>
        </div>
    @endif

    @if (session('portal_password_pin'))
        <div class="alert alert-success portal-password-pin-result" role="status">
            <div class="portal-password-pin-result-copy">
                <strong>{{ ucfirst(session('portal_password_pin.account_type')) }} setup PIN for {{ session('portal_password_pin.account_name') }}</strong>
                <span>Login ID: {{ session('portal_password_pin.identifier') }} · Expires in {{ session('portal_password_pin.expires_in') }} minutes · Works once</span>
            </div>
            <div class="portal-password-pin-share">
                <code data-portal-password-pin>{{ session('portal_password_pin.pin') }}</code>
                <button class="btn btn-sm btn-outline-success" type="button" data-copy-portal-password-pin>Copy PIN</button>
            </div>
            <small class="d-block mt-2">Share the PIN and login ID directly with the account holder.</small>
        </div>
    @endif

    <x-admin.card padding="false">
        <div class="card-header"><h2>Enrollment history</h2></div>
        <x-admin.table><thead><tr><th>Campus</th><th>Academic year</th><th>Class / section</th><th>Course / semester</th><th>R.NO</th><th>Start date</th><th>Status</th></tr></thead><tbody>
            @forelse($student->enrollments->sortByDesc('created_at') as $enrollment)<tr><td>{{ $enrollment->branch?->name ?? $student->branch?->name }}</td><td>{{ $enrollment->academicYear?->name ?? '—' }}</td><td>{{ $enrollment->schoolClass?->name ?? '—' }}{{ $enrollment->section?->name ? ' / '.$enrollment->section->name : '' }}</td><td>{{ $enrollment->course?->name ?? '—' }}{{ $enrollment->semester?->name ? ' / '.$enrollment->semester->name : '' }}</td><td>{{ $enrollment->roll_number ?? '—' }}</td><td>{{ $enrollment->admission_date?->format('d M Y') ?? '—' }}</td><td><x-admin.badge :tone="$enrollment->status==='active'?'success':'slate'">{{ ucfirst($enrollment->status) }}</x-admin.badge></td></tr>@empty<tr><td colspan="7"><x-admin.empty-state title="No enrollment history recorded" /></td></tr>@endforelse
        </tbody></x-admin.table>
    </x-admin.card>

    <x-admin.card>
        <h2 class="h5 mb-3">Promote or transfer</h2>
        <form method="post" action="{{ route('students.enrollments.store',$student) }}" class="grid gap-4 md:grid-cols-3">
            @csrf
            <div><label for="branch_id">Campus</label><select id="branch_id" name="branch_id" class="form-control" required>@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('branch_id',$student->branch_id)==$branch->id)>{{ $branch->name }}</option>@endforeach</select></div>
            <div><label for="academic_year_id">Academic year</label><select id="academic_year_id" name="academic_year_id" class="form-control" required>@foreach($years as $year)<option value="{{ $year->id }}" @selected(old('academic_year_id')==$year->id)>{{ $year->branch?->name }} · {{ $year->name }}</option>@endforeach</select></div>
            <div><label for="admission_date">Start date</label><input id="admission_date" name="admission_date" type="text" class="form-control js-datepicker" value="{{ old('admission_date',today()->format('d/m/Y')) }}" autocomplete="off"></div>
            <div><label for="school_class_id">Class</label><select id="school_class_id" name="school_class_id" class="form-control"><option value="">Not applicable</option>@foreach($classes as $class)<option value="{{ $class->id }}" @selected(old('school_class_id')==$class->id)>{{ $class->branch?->name }} · {{ $class->name }}</option>@endforeach</select></div>
            <div><label for="section_id">Section</label><select id="section_id" name="section_id" class="form-control"><option value="">Not applicable</option>@foreach($sections as $section)<option value="{{ $section->id }}" @selected(old('section_id')==$section->id)>{{ $section->name }}</option>@endforeach</select></div>
            <div><label for="course_id">Course</label><select id="course_id" name="course_id" class="form-control"><option value="">Not applicable</option>@foreach($courses as $course)<option value="{{ $course->id }}" @selected(old('course_id')==$course->id)>{{ $course->branch?->name }} · {{ $course->name }}</option>@endforeach</select></div>
            <div><label for="semester_id">Semester</label><select id="semester_id" name="semester_id" class="form-control"><option value="">Not applicable</option>@foreach($semesters as $semester)<option value="{{ $semester->id }}" @selected(old('semester_id')==$semester->id)>{{ $semester->course?->name }} · {{ $semester->name }}</option>@endforeach</select></div>
            <div class="md:col-span-2"><label for="transition_reason">Reason</label><input id="transition_reason" name="transition_reason" class="form-control" value="{{ old('transition_reason') }}" placeholder="Promotion, campus transfer, or academic placement" required></div>
            <div class="md:col-span-3"><button class="btn btn-primary">Save enrollment transition</button></div>
        </form>
    </x-admin.card>

    <x-admin.card padding="false">
        <div class="card-header"><h2>Fee balance</h2></div>
        <x-admin.table><thead><tr><th>Academic year</th><th>Installment</th><th>Due date</th><th>Total</th><th>Paid</th><th>Balance</th></tr></thead><tbody>
            @forelse($student->feeAssignments as $assignment)@php($balance=$assignment->balanceDue())@php($paid=$assignment->totalDue()-$balance)<tr><td>{{ $assignment->component?->academicYear?->name ?? $assignment->component?->structure?->academicYear?->name ?? '—' }}</td><td>{{ $assignment->component?->installment_number ? 'Installment '.$assignment->component->installment_number.' · ' : '' }}{{ $assignment->component?->name }}</td><td>{{ $assignment->component?->due_date?->format('d M Y') ?? '—' }}</td><td>₹{{ number_format($assignment->totalDue(),2) }}</td><td>₹{{ number_format($paid,2) }}</td><td><strong>₹{{ number_format($balance,2) }}</strong></td></tr>@empty<tr><td colspan="6"><x-admin.empty-state title="No fees assigned" description="Set up a fee plan from this student’s Fees action." /></td></tr>@endforelse
        </tbody></x-admin.table>
    </x-admin.card>

    <div class="grid gap-6 xl:grid-cols-2">
        <x-admin.card>
            <h2 class="h5 mb-3">Student details</h2>@if($student->photo_path)<img src="{{ route('students.photo', $student) }}" alt="{{ $student->first_name }} student photo" class="mb-3 h-24 w-24 rounded-full object-cover">@endif
            <dl class="row small mb-0">
                <dt class="col-4 text-muted">Birth date</dt><dd class="col-8">{{ $student->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                <dt class="col-4 text-muted">R.NO / GR.NO</dt><dd class="col-8">{{ $student->roll_number ?: '—' }} / {{ $student->gr_number ?: '—' }}</dd>
                <dt class="col-4 text-muted">Place of birth</dt><dd class="col-8">{{ $student->place_of_birth ?: '—' }}{{ $student->state ? ', '.$student->state : '' }}</dd>
                <dt class="col-4 text-muted">Nationality</dt><dd class="col-8">{{ $student->nationality ?: '—' }}</dd>
                <dt class="col-4 text-muted">Mother tongue</dt><dd class="col-8">{{ $student->mother_tongue ?: '—' }}</dd>
                <dt class="col-4 text-muted">Religion / category</dt><dd class="col-8">{{ $student->religion ?: '—' }} / {{ $student->category ?: '—' }}</dd>
                <dt class="col-4 text-muted">Caste / blood group</dt><dd class="col-8">{{ $student->caste ?: '—' }} / {{ $student->blood_group ?: '—' }}</dd>
                <dt class="col-4 text-muted">Aadhaar</dt><dd class="col-8">{{ $student->aadhaar_number ? 'On file (encrypted)' : '—' }}</dd>
                <dt class="col-4 text-muted">Contact</dt><dd class="col-8">{{ $student->phone ?: 'No phone' }}<br>{{ $student->email ?: 'No email' }}</dd>
                <dt class="col-4 text-muted">Address</dt><dd class="col-8">{{ $student->address ?: 'No address recorded.' }}</dd>
            </dl>
            @if (auth()->user()->isTenantAdmin() || auth()->user()->hasPermission('users.manage'))
                <hr>
                <h3 class="h6">Student portal password</h3>
                <div class="d-flex align-items-center justify-content-between flex-wrap border rounded p-3 mb-2">
                    <span class="small">Student login ID: {{ $student->student_id }}{{ $student->email ? ' · '.$student->email : '' }}</span>
                    <div class="d-flex flex-wrap mt-2 mt-sm-0">
                        <form method="post" action="{{ route('students.portal-password-pin', $student) }}" class="mr-2">@csrf<button class="btn btn-sm btn-primary" type="submit">Generate 6-digit PIN</button></form>
                        <form method="post" action="{{ route('students.portal-password-link', $student) }}">@csrf<button class="btn btn-sm btn-outline-primary" type="submit">Generate secure link</button></form>
                    </div>
                </div>
            @endif
            <hr><h3 class="h6">Student status</h3><form method="post" action="{{ route('students.status',$student) }}" class="form-row align-items-end">@csrf<div class="col-md-7"><label for="status">Status</label><select id="status" name="status" class="form-control">@foreach(['active','inactive','graduated','transferred','withdrawn'] as $status)<option value="{{ $status }}" @selected($student->status===$status)>{{ ucfirst($status) }}</option>@endforeach</select></div><div class="col-md-auto"><button class="btn btn-outline-secondary">Update status</button></div></form>
        </x-admin.card>
        <x-admin.card>
            <div class="d-flex justify-content-between"><h2 class="h5">Guardians</h2><x-admin.badge tone="slate">{{ $student->guardians->count() }}</x-admin.badge></div>
            @forelse($student->guardians as $guardian)
                <div class="border rounded p-3 mt-3">
                    <strong>{{ $guardian->first_name }} {{ $guardian->last_name }}</strong>
                    <small class="d-block text-muted">
                        {{ $guardian->relation ?: 'Guardian' }} · {{ $guardian->phone ?: 'No phone' }}
                        @if ($guardian->email) · {{ $guardian->email }} @endif
                        @if ($guardian->occupation) · {{ $guardian->occupation }} @endif
                    </small>
                    @if (auth()->user()->isTenantAdmin() || auth()->user()->hasPermission('users.manage'))
                        <div class="d-flex align-items-center justify-content-between flex-wrap border-top mt-3 pt-3">
                            <span class="small">Parent login ID: {{ $guardian->email ?: ($guardian->phone ?: 'Add an email or mobile number first') }}</span>
                            <div class="d-flex flex-wrap mt-2 mt-sm-0">
                                <form method="post" action="{{ route('students.guardians.portal-password-pin', [$student, $guardian]) }}" class="mr-2">@csrf<button class="btn btn-sm btn-primary" type="submit" @disabled(! $guardian->email && ! $guardian->phone)>Generate 6-digit PIN</button></form>
                                <form method="post" action="{{ route('students.guardians.portal-password-link', [$student, $guardian]) }}">@csrf<button class="btn btn-sm btn-outline-primary" type="submit" @disabled(! $guardian->email && ! $guardian->phone)>Generate secure link</button></form>
                            </div>
                        </div>
                    @endif
                </div>
            @empty
                <x-admin.empty-state title="No guardians recorded" />
            @endforelse
        </x-admin.card>
    </div>

    <x-admin.card padding="false"><div class="card-header"><h2>Student history</h2></div><x-admin.table><thead><tr><th>Date</th><th>Activity</th><th>Details</th></tr></thead><tbody>@forelse($histories as $history)<tr><td>{{ \Illuminate\Support\Carbon::parse($history->created_at)->format('d M Y H:i') }}</td><td>{{ str($history->event)->after('student.')->replace('_',' ')->title() }}</td><td>{{ collect(json_decode($history->details,true) ?: [])->except('reason')->map(fn($value,$key)=>str($key)->replace('_',' ')->title().': '.$value)->implode(' · ') ?: '—' }}{{ data_get(json_decode($history->details,true) ?: [],'reason') ? ' · '.data_get(json_decode($history->details,true),'reason') : '' }}</td></tr>@empty<tr><td colspan="3">No lifecycle history recorded.</td></tr>@endforelse</tbody></x-admin.table></x-admin.card>
    @include('students.partials.fee-modals')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const linkInput = document.querySelector('[data-password-setup-link]');
            const copyButton = document.querySelector('[data-copy-password-setup-link]');
            const setupPin = document.querySelector('[data-portal-password-pin]');
            const copyPinButton = document.querySelector('[data-copy-portal-password-pin]');

            copyButton?.addEventListener('click', async () => {
                if (!linkInput) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(linkInput.value);
                } catch {
                    linkInput.select();
                    document.execCommand('copy');
                }

                copyButton.textContent = 'Copied';
            });

            copyPinButton?.addEventListener('click', async () => {
                if (!setupPin) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(setupPin.textContent.trim());
                } catch {
                    const selection = window.getSelection();
                    const range = document.createRange();
                    range.selectNodeContents(setupPin);
                    selection.removeAllRanges();
                    selection.addRange(range);
                    document.execCommand('copy');
                    selection.removeAllRanges();
                }

                copyPinButton.textContent = 'Copied';
            });
        });
    </script>
@endsection
