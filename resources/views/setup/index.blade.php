@extends('layouts.app')

@section('content')
    @php
        $tenant = app(\App\Services\TenantContext::class)->get();
        $requestedSection = request('section', 'campuses');
        $activeSection = in_array($requestedSection, ['profile', 'campuses', 'academic-years', 'financial-years'], true)
            ? $requestedSection
            : ($requestedSection === 'periods' ? 'academic-years' : 'campuses');
        $setupSections = [
            ['id' => 'profile', 'label' => 'Institution profile', 'anchor' => 'institution-profile'],
            ['id' => 'campuses', 'label' => 'Campuses', 'anchor' => 'campus-setup'],
            ['id' => 'academic-years', 'label' => 'Academic years', 'anchor' => 'academic-years'],
            ['id' => 'financial-years', 'label' => 'Financial years', 'anchor' => 'financial-years'],
        ];
    @endphp

    <x-admin.page-header
        title="Institution setup"
        description="Manage your institution profile, campuses, and academic and financial periods."
    />

    <nav class="setup-section-nav" aria-label="Setup sections">
        @foreach ($setupSections as $section)
            <a
                class="setup-section-nav-link {{ $activeSection === $section['id'] ? 'is-active' : '' }}"
                href="{{ route('setup.index', ['section' => $section['id']]) }}#{{ $section['anchor'] }}"
            >
                {{ $section['label'] }}
            </a>
        @endforeach
    </nav>

    <section id="institution-profile" class="setup-section" aria-labelledby="institution-profile-title" @if ($activeSection !== 'profile') hidden @endif>
        <div class="setup-section-heading">
            <div>
                <h2 id="institution-profile-title">Institution profile</h2>
                <p>Keep your institution’s name and contact details current.</p>
            </div>
        </div>

        <x-admin.card>
            <form method="post" action="{{ route('setup.institution') }}" enctype="multipart/form-data">
                @csrf
                @method('put')

                <div class="tenant-logo-editor mb-4">
                    @if ($tenant?->logo)
                        <img
                            src="{{ asset('storage/'.$tenant->logo) }}"
                            alt="{{ $tenant->institution_name }} logo"
                            class="tenant-logo-preview"
                        >
                    @else
                        <span class="tenant-logo-placeholder" aria-hidden="true">
                            <i class="fa-solid fa-school"></i>
                        </span>
                    @endif
                    <div class="tenant-logo-fields">
                        <label for="institution_logo">Institution logo</label>
                        <input
                            id="institution_logo"
                            name="logo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="form-control-file"
                        >
                        <small class="form-text text-muted">PNG, JPG, or WebP. Maximum file size: 2 MB.</small>
                        @if ($tenant?->logo)
                            <label class="custom-control custom-checkbox mt-2 mb-0">
                                <input type="checkbox" class="custom-control-input" name="remove_logo" value="1">
                                <span class="custom-control-label">Remove current logo</span>
                            </label>
                        @endif
                    </div>
                </div>

                <div class="form-row">
                    <div class="col-md-6 form-group">
                        <label for="institution_name">Institution name</label>
                        <input
                            id="institution_name"
                            name="institution_name"
                            class="form-control"
                            value="{{ old('institution_name', $tenant?->institution_name) }}"
                            required
                        >
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="institution_type">Institution type</label>
                        <select id="institution_type" name="institution_type" class="form-control" required>
                            <option value="school" @selected(old('institution_type', $tenant?->institution_type) === 'school')>School</option>
                            <option value="college" @selected(old('institution_type', $tenant?->institution_type) === 'college')>College</option>
                        </select>
                    </div>
                    <div class="col-md-3 form-group">
                        <label for="institution_phone">Phone</label>
                        <input id="institution_phone" name="phone" class="form-control" value="{{ old('phone', $tenant?->phone) }}">
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">
                    <i class="fa-regular fa-floppy-disk mr-2" aria-hidden="true"></i>Save institution profile
                </button>
            </form>
        </x-admin.card>
    </section>

    <section id="campus-setup" class="setup-section" aria-labelledby="campus-setup-title" @if ($activeSection !== 'campuses') hidden @endif>
        <div class="setup-section-heading">
            <div>
                <h2 id="campus-setup-title">Campuses</h2>
                <p>Add and manage the branches that belong to your institution.</p>
            </div>
            <span class="setup-count-badge">
                <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                {{ number_format($branches->total()) }} {{ \Illuminate\Support\Str::plural('campus', $branches->total()) }}
            </span>
        </div>

        @if ($errors->has('branch'))
            <div class="alert alert-warning" role="alert">{{ $errors->first('branch') }}</div>
        @endif

        <div class="setup-campus-stack">
            <x-admin.card class="setup-campus-card" :padding="false">
                <div class="setup-card-section p-3 p-md-4">
                    <div class="setup-card-heading setup-card-heading-inline">
                        <span class="setup-card-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                        <div>
                            <h3>Add a campus</h3>
                            <p>Create another school location.</p>
                        </div>
                    </div>

                    <form method="post" action="{{ route('setup.branches.store') }}" class="form-row setup-campus-form">
                        @csrf
                        <div class="col-md-6 col-lg-3 form-group">
                            <label for="branch_name">Campus name</label>
                            <input id="branch_name" name="name" class="form-control" value="{{ old('name') }}" placeholder="e.g. North Campus" required>
                        </div>
                        <div class="col-md-6 col-lg-2 form-group">
                            <label for="branch_code">Campus code</label>
                            <input id="branch_code" name="code" class="form-control text-uppercase" value="{{ old('code') }}" placeholder="e.g. NORTH" required>
                        </div>
                        <div class="col-md-6 col-lg-3 form-group">
                            <label for="branch_address">Address <span class="font-weight-normal text-muted">(optional)</span></label>
                            <input id="branch_address" name="address" class="form-control" value="{{ old('address') }}" placeholder="Campus street address">
                        </div>
                        <div class="col-md-6 col-lg-2 form-group">
                            <label for="branch_phone">Phone <span class="font-weight-normal text-muted">(optional)</span></label>
                            <input id="branch_phone" name="phone" class="form-control" value="{{ old('phone') }}" placeholder="Contact number">
                        </div>
                        <div class="col-md-6 col-lg-2 form-group d-flex align-items-end">
                            <button class="btn btn-primary btn-block setup-campus-submit" type="submit">
                                <span class="setup-campus-submit-icon" aria-hidden="true"><i class="fa-solid fa-plus"></i></span>
                                <span>Add campus</span>
                            </button>
                        </div>
                    </form>
                    <small class="form-text text-muted mt-2">Campus codes should be unique within your institution.</small>
                </div>

                <hr class="setup-divider">

                <div class="setup-list-heading">
                    <div>
                        <h3>Your campuses</h3>
                        <p>Review status and manage each campus below.</p>
                    </div>
                </div>

                <div class="setup-campus-list">
                    @forelse ($branches as $branch)
                        <article class="setup-campus-item">
                            <div class="setup-campus-item-main">
                                <span class="setup-campus-icon" aria-hidden="true">
                                    <i class="fa-solid fa-building-columns"></i>
                                </span>
                                <div class="setup-campus-identity">
                                    <div class="setup-campus-title-row">
                                        <h4>{{ $branch->name }}</h4>
                                        <span class="setup-status-badge {{ $branch->is_active ? 'is-active' : 'is-inactive' }}">
                                            {{ $branch->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                    <span class="setup-campus-code">{{ $branch->code }}</span>
                                    <div class="setup-campus-contact">
                                        @if ($branch->address)
                                            <span><i class="fa-solid fa-location-dot" aria-hidden="true"></i>{{ $branch->address }}</span>
                                        @endif
                                        @if ($branch->phone)
                                            <span><i class="fa-solid fa-phone" aria-hidden="true"></i>{{ $branch->phone }}</span>
                                        @endif
                                        @if (! $branch->address && ! $branch->phone)
                                            <span class="text-muted">No contact details added</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="setup-campus-actions">
                                <details class="setup-campus-edit">
                                    <summary class="btn btn-sm btn-outline-primary">
                                        <i class="fa-regular fa-pen-to-square" aria-hidden="true"></i>Edit details
                                    </summary>
                                    <form method="post" action="{{ route('setup.branches.update', $branch) }}" class="setup-campus-edit-form">
                                        @csrf
                                        @method('put')
                                        <h5>Edit {{ $branch->name }}</h5>
                                        <div class="form-group">
                                            <label for="branch-name-{{ $branch->id }}">Campus name</label>
                                            <input id="branch-name-{{ $branch->id }}" name="name" class="form-control" value="{{ $branch->name }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="branch-code-{{ $branch->id }}">Campus code</label>
                                            <input id="branch-code-{{ $branch->id }}" name="code" class="form-control" value="{{ $branch->code }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label for="branch-address-{{ $branch->id }}">Address</label>
                                            <textarea id="branch-address-{{ $branch->id }}" name="address" class="form-control" rows="2">{{ $branch->address }}</textarea>
                                        </div>
                                        <div class="form-group">
                                            <label for="branch-phone-{{ $branch->id }}">Phone</label>
                                            <input id="branch-phone-{{ $branch->id }}" name="phone" class="form-control" value="{{ $branch->phone }}">
                                        </div>
                                        <button class="btn btn-primary btn-sm" type="submit">Save changes</button>
                                    </form>
                                </details>

                                <form method="post" action="{{ route('setup.branches.toggle', $branch) }}">
                                    @csrf
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">
                                        <i class="fa-solid {{ $branch->is_active ? 'fa-pause' : 'fa-play' }}" aria-hidden="true"></i>
                                        {{ $branch->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                </form>

                                <form
                                    method="post"
                                    action="{{ route('setup.branches.destroy', $branch) }}"
                                    onsubmit="return confirm('Delete this campus? Only campuses with no linked records can be deleted.');"
                                >
                                    @csrf
                                    @method('delete')
                                    <button class="btn btn-sm btn-outline-danger" type="submit">
                                        <i class="fa-regular fa-trash-can" aria-hidden="true"></i>Delete
                                    </button>
                                </form>
                            </div>
                        </article>
                    @empty
                        <div class="setup-empty-state">
                            <span aria-hidden="true"><i class="fa-solid fa-building-circle-exclamation"></i></span>
                            <h4>No campuses yet</h4>
                            <p>Add your first campus using the form to get started.</p>
                        </div>
                    @endforelse
                </div>

                @if ($branches->hasPages())
                    <div class="p-3 border-top"><x-admin.pagination :paginator="$branches" /></div>
                @endif
            </x-admin.card>
        </div>
    </section>

    <section id="academic-periods" class="setup-section" aria-labelledby="academic-periods-title" @if (! in_array($activeSection, ['academic-years', 'financial-years'], true)) hidden @endif>
        <div class="setup-section-heading">
            <div>
                <h2 id="academic-periods-title">Academic &amp; financial periods</h2>
                <p>Keep each campus’s school calendar and accounting year together.</p>
            </div>
        </div>

        <div id="academic-years" class="setup-period-subsection" aria-labelledby="academic-years-title" @if ($activeSection !== 'academic-years') hidden @endif>
            <div class="setup-section-heading">
                <div>
                    <h2 id="academic-years-title">Academic years</h2>
                    <p>Set the teaching year for each campus and choose which one is current.</p>
                </div>
            </div>

            <x-admin.card class="setup-period-card" :padding="false">
                <div class="setup-card-section p-3 p-md-4">
                    <div class="setup-card-heading">
                        <span class="setup-card-icon" aria-hidden="true"><i class="fa-solid fa-calendar-plus"></i></span>
                        <div><h3>Add an academic year</h3><p>Define the date range used for class and student records.</p></div>
                    </div>

                    <form method="post" action="{{ route('setup.years.store') }}">
                        @csrf
                        <div class="form-row">
                            <div class="col-lg-3 form-group">
                                <label for="year_branch">Campus</label>
                                <select id="year_branch" name="branch_id" class="form-control" required>
                                    <option value="">Choose a campus</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected(old('branch_id', auth()->user()->currentBranchId()) == $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 form-group">
                                <label for="year_name">Academic year name</label>
                                <input id="year_name" name="name" class="form-control" value="{{ old('name') }}" placeholder="2026-2027" required>
                            </div>
                            <div class="col-md-6 col-lg-2 form-group">
                                <label for="year_start">Start date</label>
                                <input id="year_start" type="text" class="form-control js-datepicker" name="start_date" value="{{ old('start_date') }}" autocomplete="off" placeholder="DD/MM/YYYY" required>
                            </div>
                            <div class="col-md-6 col-lg-2 form-group">
                                <label for="year_end">End date</label>
                                <input id="year_end" type="text" class="form-control js-datepicker" name="end_date" value="{{ old('end_date') }}" autocomplete="off" placeholder="DD/MM/YYYY" required>
                            </div>
                            <div class="col-lg-2 form-group d-flex align-items-end">
                                <button class="btn btn-primary btn-block" type="submit">Add year</button>
                            </div>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input id="academic_current" type="checkbox" class="custom-control-input" name="is_current" value="1">
                            <label class="custom-control-label" for="academic_current">Make this the current academic year</label>
                        </div>
                    </form>
                </div>

                <hr class="setup-divider">

                <div class="setup-list-heading">
                    <div><h3>Configured academic years</h3><p>Each year is associated with one campus.</p></div>
                </div>
                @if ($errors->has('academic_year'))
                    <div class="alert alert-warning mx-3" role="alert">{{ $errors->first('academic_year') }}</div>
                @endif
                <x-admin.table>
                    <thead><tr><th>Campus</th><th>Year</th><th>Date range</th><th>Status</th><th>Manage</th></tr></thead>
                    <tbody>
                        @forelse ($years as $year)
                            <tr>
                                <td>{{ $year->branch?->name }}</td>
                                <td class="font-weight-bold">{{ $year->name }}</td>
                                <td>{{ $year->start_date?->format('d M Y') }} – {{ $year->end_date?->format('d M Y') }}</td>
                                <td><span class="setup-status-badge {{ $year->is_current ? 'is-active' : 'is-neutral' }}">{{ $year->is_current ? 'Current' : 'Not current' }}</span></td>
                                <td>
                                    <details class="setup-inline-edit">
                                        <summary class="btn btn-sm btn-outline-primary">Edit</summary>
                                        <form method="post" action="{{ route('setup.years.update', $year) }}" class="setup-inline-edit-form">
                                            @csrf
                                            @method('put')
                                            <div class="form-group"><label for="year-name-{{ $year->id }}">Year name</label><input id="year-name-{{ $year->id }}" name="name" class="form-control" value="{{ $year->name }}" required></div>
                                            <div class="form-row">
                                                <div class="col-md-6 form-group"><label for="year-start-{{ $year->id }}">Start date</label><input id="year-start-{{ $year->id }}" type="text" name="start_date" class="form-control js-datepicker" autocomplete="off" value="{{ $year->start_date?->format('d/m/Y') }}" required></div>
                                                <div class="col-md-6 form-group"><label for="year-end-{{ $year->id }}">End date</label><input id="year-end-{{ $year->id }}" type="text" name="end_date" class="form-control js-datepicker" autocomplete="off" value="{{ $year->end_date?->format('d/m/Y') }}" required></div>
                                            </div>
                                            <label class="custom-control custom-checkbox mb-3"><input type="checkbox" class="custom-control-input" name="is_current" value="1" @checked($year->is_current)><span class="custom-control-label">Current year</span></label>
                                            <button class="btn btn-primary btn-sm" type="submit">Save changes</button>
                                        </form>
                                    </details>
                                    @unless ($year->is_current)
                                        <form method="post" action="{{ route('setup.years.current', $year) }}" class="d-inline-block">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Set current</button>
                                        </form>
                                        <form method="post" action="{{ route('setup.years.destroy', $year) }}" class="d-inline-block" onsubmit="return confirm('Delete this academic year if it has no linked history?');">
                                            @csrf
                                            @method('delete')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No academic years configured yet.</td></tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
                <div class="p-3"><x-admin.pagination :paginator="$years" /></div>
            </x-admin.card>
        </div>

        <div id="financial-years" class="setup-period-subsection" aria-labelledby="financial-years-title" @if ($activeSection !== 'financial-years') hidden @endif>
            <div class="setup-section-heading">
                <div>
                    <h2 id="financial-years-title">Financial years</h2>
                    <p>Manage accounting periods independently for each campus.</p>
                </div>
            </div>

            <x-admin.card class="setup-period-card" :padding="false">
                <div class="setup-card-section p-3 p-md-4">
                    <div class="setup-card-heading">
                        <span class="setup-card-icon" aria-hidden="true"><i class="fa-solid fa-coins"></i></span>
                        <div><h3>Add a financial year</h3><p>Use the period your institution follows for its accounts.</p></div>
                    </div>

                    <form method="post" action="{{ route('setup.financial-years.store') }}">
                        @csrf
                        <div class="form-row">
                            <div class="col-lg-3 form-group">
                                <label for="financial_branch">Campus</label>
                                <select id="financial_branch" name="branch_id" class="form-control" required>
                                    <option value="">Choose a campus</option>
                                    @foreach ($branches as $branch)
                                        <option value="{{ $branch->id }}" @selected(old('branch_id', auth()->user()->currentBranchId()) == $branch->id)>{{ $branch->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 form-group">
                                <label for="financial_name">Financial year name</label>
                                <input id="financial_name" name="name" class="form-control" value="{{ old('name') }}" placeholder="2026-2027" required>
                            </div>
                            <div class="col-md-6 col-lg-2 form-group">
                                <label for="financial_start">Start date</label>
                                <input id="financial_start" type="text" name="start_date" class="form-control js-datepicker" autocomplete="off" value="{{ old('start_date') }}" placeholder="DD/MM/YYYY" required>
                            </div>
                            <div class="col-md-6 col-lg-2 form-group">
                                <label for="financial_end">End date</label>
                                <input id="financial_end" type="text" name="end_date" class="form-control js-datepicker" autocomplete="off" value="{{ old('end_date') }}" placeholder="DD/MM/YYYY" required>
                            </div>
                            <div class="col-lg-2 form-group d-flex align-items-end">
                                <button class="btn btn-primary btn-block" type="submit">Add year</button>
                            </div>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input id="financial_current" type="checkbox" class="custom-control-input" name="is_current" value="1">
                            <label class="custom-control-label" for="financial_current">Make this the current financial year</label>
                        </div>
                    </form>
                </div>

                <hr class="setup-divider">

                <div class="setup-list-heading">
                    <div><h3>Configured financial years</h3><p>Each accounting period is associated with one campus.</p></div>
                </div>
                @if ($errors->has('financial_year'))
                    <div class="alert alert-warning mx-3" role="alert">{{ $errors->first('financial_year') }}</div>
                @endif
                <x-admin.table>
                    <thead><tr><th>Campus</th><th>Year</th><th>Date range</th><th>Status</th><th>Manage</th></tr></thead>
                    <tbody>
                        @forelse ($financialYears as $year)
                            <tr>
                                <td>{{ $year->branch?->name }}</td>
                                <td class="font-weight-bold">{{ $year->name }}</td>
                                <td>{{ $year->start_date?->format('d M Y') }} – {{ $year->end_date?->format('d M Y') }}</td>
                                <td><span class="setup-status-badge {{ $year->is_current ? 'is-active' : 'is-neutral' }}">{{ $year->is_current ? 'Current' : 'Not current' }}</span></td>
                                <td>
                                    <details class="setup-inline-edit">
                                        <summary class="btn btn-sm btn-outline-primary">Edit</summary>
                                        <form method="post" action="{{ route('setup.financial-years.update', $year) }}" class="setup-inline-edit-form">
                                            @csrf
                                            @method('put')
                                            <div class="form-group"><label for="financial-name-{{ $year->id }}">Year name</label><input id="financial-name-{{ $year->id }}" name="name" class="form-control" value="{{ $year->name }}" required></div>
                                            <div class="form-row">
                                                <div class="col-md-6 form-group"><label for="financial-start-{{ $year->id }}">Start date</label><input id="financial-start-{{ $year->id }}" type="text" name="start_date" class="form-control js-datepicker" autocomplete="off" value="{{ $year->start_date?->format('d/m/Y') }}" required></div>
                                                <div class="col-md-6 form-group"><label for="financial-end-{{ $year->id }}">End date</label><input id="financial-end-{{ $year->id }}" type="text" name="end_date" class="form-control js-datepicker" autocomplete="off" value="{{ $year->end_date?->format('d/m/Y') }}" required></div>
                                            </div>
                                            <label class="custom-control custom-checkbox mb-3"><input type="checkbox" class="custom-control-input" name="is_current" value="1" @checked($year->is_current)><span class="custom-control-label">Current year</span></label>
                                            <button class="btn btn-primary btn-sm" type="submit">Save changes</button>
                                        </form>
                                    </details>
                                    @unless ($year->is_current)
                                        <form method="post" action="{{ route('setup.financial-years.current', $year) }}" class="d-inline-block">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-primary" type="submit">Set current</button>
                                        </form>
                                        <form method="post" action="{{ route('setup.financial-years.destroy', $year) }}" class="d-inline-block" onsubmit="return confirm('Delete this financial year if it has no linked records?');">
                                            @csrf
                                            @method('delete')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                                        </form>
                                    @endunless
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-4">No financial years configured yet.</td></tr>
                        @endforelse
                    </tbody>
                </x-admin.table>
                <div class="p-3"><x-admin.pagination :paginator="$financialYears" /></div>
            </x-admin.card>
        </div>
    </section>
@endsection
