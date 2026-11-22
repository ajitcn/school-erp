@extends('layouts.app')

@section('content')
    @php
        $states = [
            'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat', 'Haryana',
            'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh', 'Maharashtra', 'Manipur',
            'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana',
            'Tripura', 'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Andaman and Nicobar Islands', 'Chandigarh',
            'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry',
        ];
        $religions = ['Hindu', 'Muslim', 'Christian', 'Sikh', 'Buddhist', 'Jain', 'Parsi', 'Other'];
        $languages = [
            'Assamese', 'Bengali', 'Bodo', 'Dogri', 'English', 'Gujarati', 'Hindi', 'Kannada', 'Kashmiri',
            'Konkani', 'Maithili', 'Malayalam', 'Manipuri', 'Marathi', 'Nepali', 'Odia', 'Punjabi', 'Sanskrit',
            'Santali', 'Sindhi', 'Tamil', 'Telugu', 'Urdu', 'Other',
        ];
        $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
        $categories = ['General', 'OBC', 'SC', 'ST', 'EWS', 'Other'];
    @endphp

    <x-admin.page-header title="Edit student" description="Update personal details without changing tenant ownership.">
        <x-slot:actions>
            <a class="btn btn-outline-secondary" href="{{ route('students.show', $student) }}">Cancel</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ route('students.update', $student) }}" enctype="multipart/form-data">
        @csrf
        @method('put')

        <div class="admin-card mb-4 p-4">
            <div class="d-flex justify-content-between mb-4">
                <div>
                    <h3 class="h5">Student details</h3>
                    <p class="text-muted small mb-0">{{ $student->student_id }} · {{ $student->admission_number }}</p>
                </div>
                <x-admin.badge tone="primary">{{ $student->branch?->name ?? 'Branch' }}</x-admin.badge>
            </div>

            <div class="form-row">
                <div class="col-md-4">
                    <x-admin.field label="First name" name="first_name" :value="$student->first_name" required />
                </div>
                <div class="col-md-4">
                    <x-admin.field label="Last name" name="last_name" :value="$student->last_name" />
                </div>
                <div class="col-md-4">
                    <x-admin.field
                        label="Date of birth"
                        name="date_of_birth"
                        type="text"
                        class="js-datepicker"
                        autocomplete="off"
                        :value="$student->date_of_birth?->format('d/m/Y')"
                    />
                </div>
                <div class="col-md-4">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-control">
                        <option value="">Select gender</option>
                        <option value="male" @selected($student->gender === 'male')>Male</option>
                        <option value="female" @selected($student->gender === 'female')>Female</option>
                        <option value="other" @selected($student->gender === 'other')>Other</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <x-admin.field label="Phone" name="phone" type="tel" :value="$student->phone" />
                </div>
                <div class="col-md-4">
                    <x-admin.field label="Email" name="email" type="email" :value="$student->email" />
                </div>
            </div>

            <div class="form-group">
                <label for="photo">Student photo (JPG, PNG or WebP; up to 5 MB)</label>
                <input
                    id="photo"
                    name="photo"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="form-control-file"
                >
                @if ($student->photo_path)
                    <small class="text-muted">A photo is already on file.</small>
                @endif
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="3" class="form-control">{{ old('address', $student->address) }}</textarea>
            </div>
        </div>

        <div class="admin-card mb-4 p-4">
            <h3 class="h5">Additional admission details</h3>
            <p class="text-muted small">All fields are optional.</p>
            <div class="form-row">
                <div class="col-md-3"><x-admin.field label="R.NO" name="roll_number" :value="$student->roll_number" /></div>
                <div class="col-md-3"><x-admin.field label="GR.NO" name="gr_number" :value="$student->gr_number" /></div>
                <div class="col-md-3"><x-admin.field label="Place of birth" name="place_of_birth" :value="$student->place_of_birth" /></div>
                <div class="form-group col-md-3">
                    <label for="state">State</label>
                    <select id="state" name="state" class="form-control">
                        <option value="">Select state / UT</option>
                        @foreach ($states as $state)
                            <option value="{{ $state }}" @selected(old('state', $student->state) === $state)>{{ $state }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><x-admin.field label="Nationality" name="nationality" :value="$student->nationality" /></div>
                <div class="form-group col-md-3">
                    <label for="mother_tongue">Mother tongue</label>
                    <select id="mother_tongue" name="mother_tongue" class="form-control">
                        <option value="">Select language</option>
                        @foreach ($languages as $language)
                            <option value="{{ $language }}" @selected(old('mother_tongue', $student->mother_tongue) === $language)>{{ $language }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="religion">Religion</label>
                    <select id="religion" name="religion" class="form-control">
                        <option value="">Select religion</option>
                        @foreach ($religions as $religion)
                            <option value="{{ $religion }}" @selected(old('religion', $student->religion) === $religion)>{{ $religion }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="blood_group">Blood group</label>
                    <select id="blood_group" name="blood_group" class="form-control">
                        <option value="">Select blood group</option>
                        @foreach ($bloodGroups as $bloodGroup)
                            <option value="{{ $bloodGroup }}" @selected(old('blood_group', $student->blood_group) === $bloodGroup)>{{ $bloodGroup }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <x-admin.field label="Student's Aadhaar card no." name="aadhaar_number" inputmode="numeric" maxlength="12" autocomplete="off" />
                    @if ($student->aadhaar_number)
                        <small class="form-text text-muted">Aadhaar is stored encrypted. Leave blank to keep the saved number.</small>
                    @endif
                </div>
                <div class="col-md-3"><x-admin.field label="Caste" name="caste" :value="$student->caste" /></div>
                <div class="form-group col-md-3">
                    <label for="category">Category</label>
                    <select id="category" name="category" class="form-control">
                        <option value="">Select category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(old('category', $student->category) === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="admin-card mb-4 p-4">
            <h3 class="h5 mb-1">Father and mother details</h3>
            <p class="text-muted small">All parent details are optional.</p>
            <div class="form-row">
                <div class="col-lg-6">
                    <h4 class="h6">Father</h4>
                    <div class="form-row">
                        <div class="col-md-6"><x-admin.field label="Father name" name="father_name" :value="$father ? trim($father->first_name.' '.$father->last_name) : ''" /></div>
                        <div class="col-md-6"><x-admin.field label="Occupation" name="father_occupation" :value="$father?->occupation" /></div>
                        <div class="col-md-6"><x-admin.field label="Contact no." name="father_phone" type="tel" :value="$father?->phone" /></div>
                        <div class="col-md-6"><x-admin.field label="Email" name="father_email" type="email" :value="$father?->email" /></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <h4 class="h6">Mother</h4>
                    <div class="form-row">
                        <div class="col-md-6"><x-admin.field label="Mother name" name="mother_name" :value="$mother ? trim($mother->first_name.' '.$mother->last_name) : ''" /></div>
                        <div class="col-md-6"><x-admin.field label="Occupation" name="mother_occupation" :value="$mother?->occupation" /></div>
                        <div class="col-md-6"><x-admin.field label="Contact no." name="mother_phone" type="tel" :value="$mother?->phone" /></div>
                        <div class="col-md-6"><x-admin.field label="Email" name="mother_email" type="email" :value="$mother?->email" /></div>
                    </div>
                </div>
            </div>
            @php($otherGuardians = $student->guardians->reject(fn ($guardian) => in_array(strtolower((string) $guardian->relation), ['father', 'mother'], true)))
            @if ($otherGuardians->isNotEmpty())
                <hr>
                <h4 class="h6">Other linked guardians</h4>
                @foreach ($otherGuardians as $guardian)
                    <p class="small mb-1">{{ trim($guardian->first_name.' '.$guardian->last_name) }} · {{ $guardian->relation ?: 'Guardian' }} · {{ $guardian->phone ?: 'No phone' }}</p>
                @endforeach
            @endif
        </div>

        <div class="text-right">
            <x-admin.button type="submit">Save changes</x-admin.button>
        </div>
    </form>
@endsection
