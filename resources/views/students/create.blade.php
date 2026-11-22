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
        $toOptions = fn ($items, $label) => $items->map(fn ($item) => [
            'value' => $item->id,
            'label' => $label($item),
        ])->values()->all();
    @endphp

    <x-admin.page-header title="New student admission" description="Create a student record and initial enrollment.">
        <x-slot:actions>
            <a class="btn btn-outline-secondary" href="{{ route('students.index') }}">Cancel</a>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="post" action="{{ route('students.store') }}" enctype="multipart/form-data">
        @csrf

        <div class="admin-card mb-4 p-4">
            <h3 class="h5">Enrollment</h3>
            <p class="text-muted small">Assign the student to the correct academic structure.</p>
            <div class="form-row">
                <div class="col-md-4">
                    <x-admin.searchable-select
                        name="branch_id"
                        label="Branch"
                        :options="$toOptions($branches, fn ($item) => $item->name)"
                        :selected="old('branch_id', $selectedBranchId)"
                    />
                </div>
                <div class="col-md-4">
                    <x-admin.searchable-select
                        name="academic_year_id"
                        label="Academic year"
                        :options="$toOptions($years, fn ($item) => $item->name)"
                        :selected="old('academic_year_id', $selectedAcademicYearId)"
                    />
                </div>
                <div class="col-md-4">
                    <x-admin.searchable-select
                        name="school_class_id"
                        label="Class"
                        :options="$toOptions($classes, fn ($item) => $item->name)"
                        :selected="old('school_class_id')"
                    />
                </div>
                <div class="col-md-4">
                    <x-admin.searchable-select
                        name="section_id"
                        label="Section"
                        :options="$toOptions($sections, fn ($item) => $item->name)"
                        :selected="old('section_id')"
                    />
                </div>
                <div class="col-md-4">
                    <x-admin.searchable-select
                        name="course_id"
                        label="Course"
                        :options="$toOptions($courses, fn ($item) => $item->name.' ('.$item->code.')')"
                        :selected="old('course_id')"
                    />
                </div>
                <div class="col-md-4">
                    <x-admin.searchable-select
                        name="semester_id"
                        label="Semester"
                        :options="$toOptions($semesters, fn ($item) => $item->course?->name.' · '.$item->name)"
                        :selected="old('semester_id')"
                    />
                </div>
            </div>
        </div>

        <div class="admin-card mb-4 p-4">
            <h3 class="h5">Student details</h3>
            <p class="text-muted small">Basic identity and contact information. Additional admission details are optional.</p>
            <div class="form-group">
                <label for="photo">Student photo (JPG, PNG or WebP; up to 5 MB)</label>
                <input
                    id="photo"
                    name="photo"
                    type="file"
                    accept="image/jpeg,image/png,image/webp"
                    class="form-control-file"
                >
            </div>
            <div class="form-row">
                <div class="col-md-4"><x-admin.field label="First name" name="first_name" required /></div>
                <div class="col-md-4"><x-admin.field label="Last name" name="last_name" /></div>
                <div class="col-md-4">
                    <x-admin.field label="Date of birth" name="date_of_birth" type="text" class="js-datepicker" autocomplete="off" placeholder="dd/mm/yyyy" />
                </div>
                <div class="col-md-4">
                    <label for="gender">Gender</label>
                    <select id="gender" name="gender" class="form-control">
                        <option value="">Select gender</option>
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="col-md-4"><x-admin.field label="Phone" name="phone" type="tel" /></div>
                <div class="col-md-4"><x-admin.field label="Email" name="email" type="email" /></div>
            </div>
            <div class="form-group">
                <label for="address">Address</label>
                <textarea id="address" name="address" rows="3" class="form-control">{{ old('address') }}</textarea>
            </div>
        </div>

        <div class="admin-card mb-4 p-4">
            <h3 class="h5">Additional admission details</h3>
            <p class="text-muted small">These fields are optional and can be updated later.</p>
            <div class="form-row">
                <div class="col-md-3"><x-admin.field label="R.NO" name="roll_number" /></div>
                <div class="col-md-3"><x-admin.field label="GR.NO" name="gr_number" /></div>
                <div class="col-md-3"><x-admin.field label="Place of birth" name="place_of_birth" /></div>
                <div class="form-group col-md-3">
                    <label for="state">State</label>
                    <select id="state" name="state" class="form-control">
                        <option value="">Select state / UT</option>
                        @foreach ($states as $state)
                            <option value="{{ $state }}" @selected(old('state') === $state)>{{ $state }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3"><x-admin.field label="Nationality" name="nationality" /></div>
                <div class="form-group col-md-3">
                    <label for="mother_tongue">Mother tongue</label>
                    <select id="mother_tongue" name="mother_tongue" class="form-control">
                        <option value="">Select language</option>
                        @foreach ($languages as $language)
                            <option value="{{ $language }}" @selected(old('mother_tongue') === $language)>{{ $language }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="religion">Religion</label>
                    <select id="religion" name="religion" class="form-control">
                        <option value="">Select religion</option>
                        @foreach ($religions as $religion)
                            <option value="{{ $religion }}" @selected(old('religion') === $religion)>{{ $religion }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="blood_group">Blood group</label>
                    <select id="blood_group" name="blood_group" class="form-control">
                        <option value="">Select blood group</option>
                        @foreach ($bloodGroups as $bloodGroup)
                            <option value="{{ $bloodGroup }}" @selected(old('blood_group') === $bloodGroup)>{{ $bloodGroup }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <x-admin.field label="Student's Aadhaar card no." name="aadhaar_number" inputmode="numeric" maxlength="12" autocomplete="off" />
                </div>
                <div class="col-md-3"><x-admin.field label="Caste" name="caste" /></div>
                <div class="form-group col-md-3">
                    <label for="category">Category</label>
                    <select id="category" name="category" class="form-control">
                        <option value="">Select category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category }}" @selected(old('category') === $category)>{{ $category }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="admin-card mb-4 p-4">
            <h3 class="h5">Father and mother details</h3>
            <p class="text-muted small">All parent details are optional. You can link an existing guardian if needed.</p>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <x-admin.searchable-select
                        name="guardian_id"
                        label="Link existing guardian"
                        :options="$toOptions($guardians, fn ($item) => $item->first_name.' '.$item->last_name.' · '.$item->phone)"
                        :selected="old('guardian_id')"
                    />
                </div>
            </div>
            <div class="form-row">
                <div class="col-lg-6">
                    <h4 class="h6">Father</h4>
                    <div class="form-row">
                        <div class="col-md-6"><x-admin.field label="Father name" name="father_name" /></div>
                        <div class="col-md-6"><x-admin.field label="Occupation" name="father_occupation" /></div>
                        <div class="col-md-6"><x-admin.field label="Contact no." name="father_phone" type="tel" /></div>
                        <div class="col-md-6"><x-admin.field label="Email" name="father_email" type="email" /></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <h4 class="h6">Mother</h4>
                    <div class="form-row">
                        <div class="col-md-6"><x-admin.field label="Mother name" name="mother_name" /></div>
                        <div class="col-md-6"><x-admin.field label="Occupation" name="mother_occupation" /></div>
                        <div class="col-md-6"><x-admin.field label="Contact no." name="mother_phone" type="tel" /></div>
                        <div class="col-md-6"><x-admin.field label="Email" name="mother_email" type="email" /></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="text-right">
            <a class="btn btn-outline-secondary mr-2" href="{{ route('students.index') }}">Cancel</a>
            <x-admin.button type="submit">Create admission</x-admin.button>
        </div>
    </form>
@endsection
