@extends('layouts.app')

@section('content')
    <x-admin.page-header
        title="Student services"
        description="Manage everyday student support from one place: library, transport, hostel, and scholarships."
    />

    <x-admin.alert />
    <x-admin.validation-errors />

    @if (! auth()->user()->currentBranchId())
        <div class="admin-card mb-4 p-3">
            <form method="get" class="form-row align-items-end">
                <div class="form-group col-md-5 mb-md-0">
                    <label for="branch_id">Campus</label>
                    <select id="branch_id" name="branch_id" class="form-control" required>
                        @foreach ($branches as $branch)
                            <option value="{{ $branch->id }}" @selected($branchId == $branch->id)>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-auto">
                    <button class="btn btn-outline-primary">Load campus</button>
                </div>
            </form>
        </div>
    @endif

    @if ($branchId || auth()->user()->currentBranchId())
        @php
            $serviceSections = ['library', 'transport', 'hostel', 'scholarships'];
            $activeServiceSection = in_array(request()->query('section'), $serviceSections, true)
                ? request()->query('section')
                : 'library';

            $serviceSummary = [
                ['label' => 'Books available', 'value' => number_format($books->where('is_active', true)->sum('copies_available')), 'detail' => $books->where('is_active', true)->count().' active titles', 'icon' => 'fa-book-open', 'tone' => 'blue'],
                ['label' => 'Open loans', 'value' => number_format($loans->count()), 'detail' => 'Books currently with students', 'icon' => 'fa-book-reader', 'tone' => 'violet'],
                ['label' => 'Transport routes', 'value' => number_format($activeRoutes->count()), 'detail' => $transport->count().' active assignments', 'icon' => 'fa-bus', 'tone' => 'amber'],
                ['label' => 'Hostel occupancy', 'value' => number_format($hostel->count()), 'detail' => 'Active room allocations', 'icon' => 'fa-bed', 'tone' => 'teal'],
                ['label' => 'Scholarships', 'value' => number_format($activeScholarships->count()), 'detail' => $awards->count().' recent awards', 'icon' => 'fa-award', 'tone' => 'rose'],
            ];
        @endphp

        <section class="services-overview" aria-label="Student services summary">
            @foreach ($serviceSummary as $summary)
                <article class="services-summary-card services-summary-card--{{ $summary['tone'] }}">
                    <span class="services-summary-icon" aria-hidden="true"><i class="fa-solid {{ $summary['icon'] }}"></i></span>
                    <div class="services-summary-copy">
                        <span>{{ $summary['label'] }}</span>
                        <strong>{{ $summary['value'] }}</strong>
                        <small>{{ $summary['detail'] }}</small>
                    </div>
                </article>
            @endforeach
        </section>

        <nav class="services-section-nav" aria-label="Student service sections" role="tablist">
            <button type="button" id="services-tab-library" class="{{ $activeServiceSection === 'library' ? 'is-active' : '' }}" role="tab" aria-controls="services-panel-library" aria-selected="{{ $activeServiceSection === 'library' ? 'true' : 'false' }}" data-services-tab="library">
                <i class="fa-solid fa-book-open" aria-hidden="true"></i> Library
            </button>
            <button type="button" id="services-tab-transport" class="{{ $activeServiceSection === 'transport' ? 'is-active' : '' }}" role="tab" aria-controls="services-panel-transport" aria-selected="{{ $activeServiceSection === 'transport' ? 'true' : 'false' }}" data-services-tab="transport">
                <i class="fa-solid fa-bus" aria-hidden="true"></i> Transport
            </button>
            <button type="button" id="services-tab-hostel" class="{{ $activeServiceSection === 'hostel' ? 'is-active' : '' }}" role="tab" aria-controls="services-panel-hostel" aria-selected="{{ $activeServiceSection === 'hostel' ? 'true' : 'false' }}" data-services-tab="hostel">
                <i class="fa-solid fa-bed" aria-hidden="true"></i> Hostel
            </button>
            <button type="button" id="services-tab-scholarships" class="{{ $activeServiceSection === 'scholarships' ? 'is-active' : '' }}" role="tab" aria-controls="services-panel-scholarships" aria-selected="{{ $activeServiceSection === 'scholarships' ? 'true' : 'false' }}" data-services-tab="scholarships">
                <i class="fa-solid fa-award" aria-hidden="true"></i> Scholarships
            </button>
        </nav>

        <div class="services-module-grid">
            <section id="services-panel-library" class="services-tab-panel" role="tabpanel" aria-labelledby="services-tab-library" data-services-panel="library" @if ($activeServiceSection !== 'library') hidden @endif>
            <x-admin.card id="library-services" class="services-module-card">
                <div class="services-module-heading"><span class="services-module-icon services-module-icon--blue"><i class="fa-solid fa-book-open" aria-hidden="true"></i></span><div><h2 class="h5 mb-1">Library</h2><p class="text-muted small mb-0">Catalog books, issue them to students, and track returns.</p></div></div>
                <h3 class="h6 services-form-title">Add a book</h3>
                <form method="post" action="{{ route('services.library.books.store') }}" class="grid gap-3 md:grid-cols-2">
                    @csrf
                    @if (! auth()->user()->currentBranchId())
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    @endif
                    <div><label>Title</label><input name="title" class="form-control" required></div>
                    <div><label>Author</label><input name="author" class="form-control"></div>
                    <div><label>ISBN</label><input name="isbn" class="form-control"></div>
                    <div><label>Category</label><input name="category" class="form-control"></div>
                    <div>
                        <label>Copies</label>
                        <input name="copies_total" type="number" min="1" value="1" class="form-control" required>
                    </div>
                    <button class="btn btn-primary self-end">Add book</button>
                </form>

                <hr>
                <div class="services-subsection-heading"><h3 class="h6 mb-0">Book catalog</h3><span>{{ $books->count() }} titles</span></div>
                <div class="max-h-64 overflow-auto">
                    @forelse ($books as $book)
                        <div class="border-b py-2">
                            <div class="flex items-center justify-between gap-3"><div><strong>{{ $book->title }}</strong><small class="d-block text-muted">{{ $book->author ?: 'No author' }} · {{ $book->copies_available }}/{{ $book->copies_total }} available · {{ $book->is_active ? 'Active' : 'Inactive' }}</small></div>
                                <div class="flex gap-2"><details><summary class="btn btn-sm btn-outline-primary">Edit</summary><form method="post" action="{{ route('services.library.books.update', $book->id) }}" class="grid gap-2 mt-2">@csrf @method('put')
                                    <input name="title" class="form-control" value="{{ $book->title }}" aria-label="Book title" required><input name="author" class="form-control" value="{{ $book->author }}" aria-label="Author"><input name="isbn" class="form-control" value="{{ $book->isbn }}" aria-label="ISBN"><input name="category" class="form-control" value="{{ $book->category }}" aria-label="Category"><label>Total copies<input name="copies_total" type="number" min="1" class="form-control" value="{{ $book->copies_total }}" required></label><button class="btn btn-sm btn-primary">Save</button></form></details>
                                    <form method="post" action="{{ route('services.library.books.toggle', $book->id) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $book->is_active ? 'Deactivate' : 'Activate' }}</button></form></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted">No books cataloged.</p>
                    @endforelse
                </div>

                <div class="services-subsection-heading"><h3 class="h6 mb-0">Issue a book</h3><span>{{ $loans->count() }} open</span></div>
                <form method="post" action="{{ route('services.library.loans.store') }}" class="grid gap-3 md:grid-cols-2">
                    @csrf
                    @if (! auth()->user()->currentBranchId())
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    @endif
                    <div>
                        <label>Book</label>
                        <select name="book_id" class="form-control" required>
                            <option value="">Select book</option>
                            @foreach ($books->where('is_active', true)->where('copies_available', '>', 0) as $book)
                                <option value="{{ $book->id }}">
                                    {{ $book->title }} ({{ $book->copies_available }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label>Student</label>
                        <select name="student_id" class="form-control" required>
                            <option value="">Select student</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}">
                                    {{ $student->first_name }} {{ $student->last_name }} · {{ $student->student_id }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div><label for="library_due_at">Due date</label><input id="library_due_at" type="text" name="due_at" class="form-control js-datepicker" autocomplete="off" required></div>
                    <button class="btn btn-primary self-end">Issue book</button>
                </form>

                <hr>
                <div class="services-subsection-heading"><h3 class="h6 mb-0">Open loans</h3><span>{{ $loans->count() }} records</span></div>
                @forelse ($loans as $loan)
                    <div class="flex items-center justify-between border-b py-2">
                        <div>
                            <strong>{{ $loan->book_title }}</strong>
                            <small class="d-block text-muted">
                                {{ $loan->first_name }} {{ $loan->last_name }} · due {{ $loan->due_at }}
                            </small>
                        </div>
                        <form method="post" action="{{ route('services.library.loans.return', $loan->id) }}">
                            @csrf
                            @if (! auth()->user()->currentBranchId())
                                <input type="hidden" name="branch_id" value="{{ $branchId }}">
                            @endif
                            <button class="btn btn-sm btn-outline-primary">Return</button>
                        </form>
                    </div>
                @empty
                    <p class="text-muted">No open loans.</p>
                @endforelse
            </x-admin.card>
            </section>

            <section id="services-panel-transport" class="services-tab-panel" role="tabpanel" aria-labelledby="services-tab-transport" data-services-panel="transport" @if ($activeServiceSection !== 'transport') hidden @endif>
            <x-admin.card id="transport-services" class="services-module-card">
                <div class="services-module-heading"><span class="services-module-icon services-module-icon--amber"><i class="fa-solid fa-bus" aria-hidden="true"></i></span><div><h2 class="h5 mb-1">Transport</h2><p class="text-muted small mb-0">Set up routes and assign students to their pickup points.</p></div></div>
                <h3 class="h6 services-form-title">Create a route</h3>
                <form method="post" action="{{ route('services.transport.routes.store') }}" class="grid gap-3 md:grid-cols-2">
                    @csrf
                    @if (! auth()->user()->currentBranchId())
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    @endif
                    <div><label>Route name</label><input name="name" class="form-control" required></div>
                    <div><label>Vehicle number</label><input name="vehicle_number" class="form-control"></div>
                    <div><label>Driver</label><input name="driver_name" class="form-control"></div>
                    <div><label>Driver phone</label><input name="driver_phone" class="form-control"></div>
                    <div>
                        <label>Monthly fee</label>
                        <input name="monthly_fee" type="number" min="0" step="0.01" value="0" class="form-control">
                    </div>
                    <button class="btn btn-primary self-end">Create route</button>
                </form>

                <hr>
                <h3 class="h6">Route catalog</h3>
                @forelse ($routes as $route)
                    <div class="border-b py-2">
                        <div class="flex items-center justify-between gap-3">
                            <div><strong>{{ $route->name }}</strong><small class="d-block text-muted">{{ $route->vehicle_number ?: 'No vehicle' }} · ₹{{ number_format($route->monthly_fee, 2) }} / month · {{ $route->is_active ? 'Active' : 'Inactive' }}</small></div>
                            <div class="flex gap-2">
                                <details><summary class="btn btn-sm btn-outline-primary">Edit</summary><form method="post" action="{{ route('services.transport.routes.update', $route->id) }}" class="grid gap-2 mt-2">@csrf @method('put')<input name="name" class="form-control" value="{{ $route->name }}" aria-label="Route name" required><input name="vehicle_number" class="form-control" value="{{ $route->vehicle_number }}" aria-label="Vehicle number"><input name="driver_name" class="form-control" value="{{ $route->driver_name }}" aria-label="Driver name"><input name="driver_phone" class="form-control" value="{{ $route->driver_phone }}" aria-label="Driver phone"><label>Monthly fee<input name="monthly_fee" type="number" min="0" step="0.01" class="form-control" value="{{ $route->monthly_fee }}" required></label><button class="btn btn-sm btn-primary">Save</button></form></details>
                                <form method="post" action="{{ route('services.transport.routes.toggle', $route->id) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $route->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                            </div>
                        </div>
                    </div>
                @empty<p class="text-muted">No routes created.</p>@endforelse

                <hr>
                <form method="post" action="{{ route('services.transport.assignments.store') }}" class="grid gap-3 md:grid-cols-3">
                    @csrf
                    @if (! auth()->user()->currentBranchId())
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    @endif
                    <select name="transport_route_id" class="form-control" aria-label="Transport route" required>
                        <option value="">Route</option>
                        @foreach ($activeRoutes as $route)
                            <option value="{{ $route->id }}">{{ $route->name }}</option>
                        @endforeach
                    </select>
                    <select name="student_id" class="form-control" aria-label="Student" required>
                        <option value="">Student</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">
                                {{ $student->first_name }} {{ $student->last_name }}
                            </option>
                        @endforeach
                    </select>
                    <input name="pickup_point" class="form-control" placeholder="Pickup point" aria-label="Pickup point">
                    <input type="text" name="starts_on" class="form-control js-datepicker" autocomplete="off" placeholder="Start date" aria-label="Transport start date" required>
                    <button class="btn btn-outline-primary">Assign transport</button>
                </form>

                <hr>
                <div class="max-h-48 overflow-auto">
                    @forelse ($transport as $item)
                        <div class="border-b py-2">
                            {{ $item->route_name }} · {{ $item->first_name }} {{ $item->last_name }}
                            <small class="text-muted">{{ $item->pickup_point }}</small>
                            <form
                                method="post"
                                action="{{ route('services.transport.assignments.end', $item->id) }}"
                                class="inline"
                            >
                                @csrf
                                @if (! auth()->user()->currentBranchId())
                                    <input type="hidden" name="branch_id" value="{{ $branchId }}">
                                @endif
                                <button class="btn btn-sm btn-outline-secondary ml-2">End</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-muted">No active transport assignments.</p>
                    @endforelse
                </div>
            </x-admin.card>
            </section>

            <section id="services-panel-hostel" class="services-tab-panel" role="tabpanel" aria-labelledby="services-tab-hostel" data-services-panel="hostel" @if ($activeServiceSection !== 'hostel') hidden @endif>
            <x-admin.card id="hostel-services" class="services-module-card">
                <div class="services-module-heading"><span class="services-module-icon services-module-icon--teal"><i class="fa-solid fa-bed" aria-hidden="true"></i></span><div><h2 class="h5 mb-1">Hostel</h2><p class="text-muted small mb-0">Manage rooms, capacity, and student allocations.</p></div></div>
                <h3 class="h6 services-form-title">Add a room</h3>
                <form method="post" action="{{ route('services.hostel.rooms.store') }}" class="grid gap-3 md:grid-cols-2">
                    @csrf
                    @if (! auth()->user()->currentBranchId())
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    @endif
                    <div><label>Room name</label><input name="name" class="form-control" required></div>
                    <div><label>Building</label><input name="building" class="form-control"></div>
                    <div><label>Room type</label><input name="room_type" class="form-control" placeholder="Dormitory / private"></div>
                    <div>
                        <label>Capacity</label>
                        <input type="number" min="1" name="capacity" value="1" class="form-control" required>
                    </div>
                    <div>
                        <label>Monthly fee</label>
                        <input type="number" min="0" step="0.01" name="monthly_fee" value="0" class="form-control">
                    </div>
                    <button class="btn btn-primary self-end">Add room</button>
                </form>

                <hr>
                <h3 class="h6">Room catalog</h3>
                @forelse ($rooms as $room)
                    <div class="border-b py-2">
                        <div class="flex items-center justify-between gap-3">
                            <div><strong>{{ $room->building }} {{ $room->name }}</strong><small class="d-block text-muted">{{ $room->room_type ?: 'Room' }} · Capacity {{ $room->capacity }} · ₹{{ number_format($room->monthly_fee, 2) }} / month · {{ $room->is_active ? 'Active' : 'Inactive' }}</small></div>
                            <div class="flex gap-2">
                                <details><summary class="btn btn-sm btn-outline-primary">Edit</summary><form method="post" action="{{ route('services.hostel.rooms.update', $room->id) }}" class="grid gap-2 mt-2">@csrf @method('put')<input name="name" class="form-control" value="{{ $room->name }}" aria-label="Room name" required><input name="building" class="form-control" value="{{ $room->building }}" aria-label="Building"><input name="room_type" class="form-control" value="{{ $room->room_type }}" aria-label="Room type"><label>Capacity<input name="capacity" type="number" min="1" class="form-control" value="{{ $room->capacity }}" required></label><label>Monthly fee<input name="monthly_fee" type="number" min="0" step="0.01" class="form-control" value="{{ $room->monthly_fee }}" required></label><button class="btn btn-sm btn-primary">Save</button></form></details>
                                <form method="post" action="{{ route('services.hostel.rooms.toggle', $room->id) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $room->is_active ? 'Deactivate' : 'Activate' }}</button></form>
                            </div>
                        </div>
                    </div>
                @empty<p class="text-muted">No hostel rooms created.</p>@endforelse

                <hr>
                <form method="post" action="{{ route('services.hostel.allocations.store') }}" class="grid gap-3 md:grid-cols-3">
                    @csrf
                    @if (! auth()->user()->currentBranchId())
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    @endif
                    <select name="hostel_room_id" class="form-control" aria-label="Hostel room" required>
                        <option value="">Room</option>
                        @foreach ($activeRooms as $room)
                            <option value="{{ $room->id }}">{{ $room->building }} {{ $room->name }}</option>
                        @endforeach
                    </select>
                    <select name="student_id" class="form-control" aria-label="Student" required>
                        <option value="">Student</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">
                                {{ $student->first_name }} {{ $student->last_name }}
                            </option>
                        @endforeach
                    </select>
                    <input type="text" name="starts_on" class="form-control js-datepicker" autocomplete="off" placeholder="Start date" aria-label="Hostel start date" required>
                    <button class="btn btn-outline-primary">Allocate bed</button>
                </form>

                <hr>
                <div class="max-h-48 overflow-auto">
                    @forelse ($hostel as $item)
                        <div class="border-b py-2">
                            {{ $item->room_name }} · {{ $item->first_name }} {{ $item->last_name }}
                            <form
                                method="post"
                                action="{{ route('services.hostel.allocations.end', $item->id) }}"
                                class="inline"
                            >
                                @csrf
                                @if (! auth()->user()->currentBranchId())
                                    <input type="hidden" name="branch_id" value="{{ $branchId }}">
                                @endif
                                <button class="btn btn-sm btn-outline-secondary ml-2">Check out</button>
                            </form>
                        </div>
                    @empty
                        <p class="text-muted">No active hostel allocations.</p>
                    @endforelse
                </div>
            </x-admin.card>
            </section>

            <section id="services-panel-scholarships" class="services-tab-panel" role="tabpanel" aria-labelledby="services-tab-scholarships" data-services-panel="scholarships" @if ($activeServiceSection !== 'scholarships') hidden @endif>
            <x-admin.card id="scholarship-services" class="services-module-card">
                <div class="services-module-heading"><span class="services-module-icon services-module-icon--rose"><i class="fa-solid fa-award" aria-hidden="true"></i></span><div><h2 class="h5 mb-1">Scholarships</h2><p class="text-muted small mb-0">Create programs and apply awards to student fees.</p></div></div>
                <h3 class="h6 services-form-title">Create a scholarship</h3>
                <form method="post" action="{{ route('services.scholarships.store') }}" class="grid gap-3 md:grid-cols-2">
                    @csrf
                    @if (! auth()->user()->currentBranchId())
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    @endif
                    <div><label>Name</label><input name="name" class="form-control" required></div>
                    <div>
                        <label>Amount type</label>
                        <select name="award_type" class="form-control">
                            <option value="fixed">Fixed amount</option>
                            <option value="percent">Percent of assigned fees</option>
                        </select>
                    </div>
                    <div>
                        <label>Amount / percent</label>
                        <input name="amount" type="number" min="0.01" step="0.01" class="form-control" required>
                    </div>
                    <div><label>Eligibility</label><input name="criteria" class="form-control"></div>
                    <button class="btn btn-primary">Create scholarship</button>
                </form>

                <hr>
                <h3 class="h6">Scholarship catalog</h3>
                @forelse ($scholarships as $scholarship)
                    <div class="border-b py-2"><div class="flex items-center justify-between gap-3"><div><strong>{{ $scholarship->name }}</strong><small class="d-block text-muted">{{ ucfirst($scholarship->award_type) }} · {{ $scholarship->award_type === 'percent' ? $scholarship->amount.'%' : '₹'.number_format($scholarship->amount, 2) }} · {{ $scholarship->is_active ? 'Active' : 'Inactive' }}</small></div>
                        <div class="flex gap-2"><details><summary class="btn btn-sm btn-outline-primary">Edit</summary><form method="post" action="{{ route('services.scholarships.update', $scholarship->id) }}" class="grid gap-2 mt-2">@csrf @method('put')<input name="name" class="form-control" value="{{ $scholarship->name }}" aria-label="Scholarship name" required><input name="criteria" class="form-control" value="{{ $scholarship->criteria }}" aria-label="Eligibility criteria"><select name="award_type" class="form-control"><option value="fixed" @selected($scholarship->award_type === 'fixed')>Fixed amount</option><option value="percent" @selected($scholarship->award_type === 'percent')>Percent of assigned fees</option></select><label>Amount / percent<input name="amount" type="number" min="0.01" step="0.01" class="form-control" value="{{ $scholarship->amount }}" required></label><button class="btn btn-sm btn-primary">Save</button></form></details><form method="post" action="{{ route('services.scholarships.toggle', $scholarship->id) }}">@csrf<button class="btn btn-sm btn-outline-secondary">{{ $scholarship->is_active ? 'Deactivate' : 'Activate' }}</button></form></div>
                    </div></div>
                @empty<p class="text-muted">No scholarship programs created.</p>@endforelse

                <hr>
                <form method="post" action="{{ route('services.scholarships.award') }}" class="grid gap-3 md:grid-cols-2">
                    @csrf
                    @if (! auth()->user()->currentBranchId())
                        <input type="hidden" name="branch_id" value="{{ $branchId }}">
                    @endif
                    <select name="scholarship_id" class="form-control" aria-label="Scholarship" required>
                        <option value="">Scholarship</option>
                        @foreach ($activeScholarships as $scholarship)
                            <option value="{{ $scholarship->id }}">{{ $scholarship->name }}</option>
                        @endforeach
                    </select>
                    <select name="student_id" class="form-control" aria-label="Student" required>
                        <option value="">Student</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}">
                                {{ $student->first_name }} {{ $student->last_name }}
                            </option>
                        @endforeach
                    </select>
                    <select name="academic_year_id" class="form-control" aria-label="Academic year" required>
                        <option value="">Academic year</option>
                        @foreach ($years as $year)
                            <option value="{{ $year->id }}">{{ $year->name }}</option>
                        @endforeach
                    </select>
                    <input name="notes" class="form-control" placeholder="Award notes">
                    <button class="btn btn-outline-primary">Award</button>
                </form>

                <hr>
                @forelse ($awards as $award)
                    <div class="border-b py-2">
                        {{ $award->scholarship_name }} · {{ $award->first_name }} {{ $award->last_name }} ·
                        {{ $award->year_name }} · ₹{{ number_format($award->awarded_amount, 2) }}
                    </div>
                @empty
                    <p class="text-muted">No scholarship awards recorded.</p>
                @endforelse
            </x-admin.card>
            </section>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const tabs = [...document.querySelectorAll('[data-services-tab]')];
                const panels = [...document.querySelectorAll('[data-services-panel]')];

                tabs.forEach((tab) => {
                    tab.addEventListener('click', () => {
                        const section = tab.dataset.servicesTab;

                        tabs.forEach((item) => {
                            const isActive = item === tab;
                            item.classList.toggle('is-active', isActive);
                            item.setAttribute('aria-selected', String(isActive));
                        });

                        panels.forEach((panel) => {
                            panel.hidden = panel.dataset.servicesPanel !== section;
                        });

                        const url = new URL(window.location.href);
                        url.searchParams.set('section', section);
                        window.history.replaceState({}, '', url);
                    });
                });
            });
        </script>
    @endif
@endsection
