<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Student;
use App\Models\StudentFeeAssignment;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Manages library, transport, hostel, and scholarship records.
 */
class StudentServicesController extends Controller
{
    private function branchId(Request $request, ?int $branchId = null): int
    {
        $branchId = (int) ($request->user()->currentBranchId() ?: ($branchId ?: $request->input('branch_id')));
        abort_unless($branchId && $request->user()->canAccessBranch($branchId) && Branch::query()->whereKey($branchId)->exists(), 403);

        return $branchId;
    }

    private function tenantId(): int
    {
        return (int) app(TenantContext::class)->id();
    }

    /** Resolve a service catalog record within this tenant and the user's branch access. */
    private function serviceRecord(Request $request, string $table, int $id): object
    {
        $record = DB::table($table)->where('id', $id)->where('tenant_id', $this->tenantId())->first();
        abort_unless($record && $request->user()->canAccessBranch($record->branch_id), 404);

        return $record;
    }

    public function index(Request $request)
    {
        $branches = $request->user()->accessibleBranchIds();
        $branchId = $request->user()->currentBranchId() ?: ($request->filled('branch_id') ? $request->integer('branch_id') : null);
        if (! $branchId && $branches === null) {
            $branchId = Branch::query()->where('is_active', true)->orderBy('id')->value('id');
        }
        if ($branchId) {
            abort_unless($request->user()->canAccessBranch($branchId), 403);
        }
        $tenant = (int) $this->tenantId();
        $scope = fn ($q) => $q->where('tenant_id', $tenant)
            ->when($branches !== null, fn ($query) => $query
                ->whereIn('branch_id', $branches))
            ->when($branchId, fn ($query) => $query
                ->where('branch_id', $branchId));
        $joinScope = fn ($q, string $alias) => $q->where($alias.'.tenant_id', $tenant)
            ->when($branches !== null, fn ($query) => $query
                ->whereIn($alias.'.branch_id', $branches))
            ->when($branchId, fn ($query) => $query
                ->where($alias.'.branch_id', $branchId));
        $students = Student::query()->where('status', 'active')
            ->when($branches !== null, fn ($q) => $q
                ->whereIn('branch_id', $branches))
            ->when($branchId, fn ($q) => $q
                ->where('branch_id', $branchId))
            ->orderBy('first_name')
            ->get();

        return view('services.index', [
            'branches' => Branch::query()->where('is_active', true)
                ->when($branches !== null, fn ($q) => $q
                    ->whereIn('id', $branches))
                ->orderBy('name')
                ->get(), 'branchId' => $branchId, 'students' => $students,
            'books' => $scope(DB::table('library_books'))->latest('id')
                ->get(), 'loans' => $joinScope(DB::table('library_loans')
                ->join('library_books', 'library_books.id', '=', 'library_loans.book_id')
                ->join('students', 'students.id', '=', 'library_loans.student_id')
                ->select('library_loans.*', 'library_books.title as book_title', 'students.first_name', 'students.last_name', 'students.student_id'), 'library_loans')
                ->whereNull('returned_at')
                ->latest('issued_at')
                ->get(),
            'routes' => $scope(DB::table('transport_routes'))->orderBy('name')->get(),
            'activeRoutes' => $scope(DB::table('transport_routes'))->where('is_active', true)->orderBy('name')->get(),
            'transport' => $joinScope(DB::table('student_transport')
                ->join('transport_routes', 'transport_routes.id', '=', 'student_transport.transport_route_id')
                ->join('students', 'students.id', '=', 'student_transport.student_id')
                ->select('student_transport.*', 'transport_routes.name as route_name', 'students.first_name', 'students.last_name', 'students.student_id'), 'student_transport')
                ->where('student_transport.status', 'active')
                ->latest('student_transport.id')
                ->get(),
            'rooms' => $scope(DB::table('hostel_rooms'))->orderBy('name')->get(),
            'activeRooms' => $scope(DB::table('hostel_rooms'))->where('is_active', true)->orderBy('name')->get(),
            'hostel' => $joinScope(DB::table('hostel_allocations')
                ->join('hostel_rooms', 'hostel_rooms.id', '=', 'hostel_allocations.hostel_room_id')
                ->join('students', 'students.id', '=', 'hostel_allocations.student_id')
                ->select('hostel_allocations.*', 'hostel_rooms.name as room_name', 'students.first_name', 'students.last_name', 'students.student_id'), 'hostel_allocations')
                ->where('hostel_allocations.status', 'active')
                ->latest('hostel_allocations.id')
                ->get(),
            'scholarships' => $scope(DB::table('scholarships'))->orderBy('name')->get(),
            'activeScholarships' => $scope(DB::table('scholarships'))->where('is_active', true)->orderBy('name')->get(),
            'awards' => $joinScope(DB::table('student_scholarships')->join('scholarships', 'scholarships.id', '=', 'student_scholarships.scholarship_id')
                ->join('students', 'students.id', '=', 'student_scholarships.student_id')
                ->join('academic_years', 'academic_years.id', '=', 'student_scholarships.academic_year_id')
                ->select('student_scholarships.*', 'scholarships.name as scholarship_name', 'students.first_name', 'students.last_name', 'students.student_id', 'academic_years.name as year_name'), 'student_scholarships')
                ->latest('student_scholarships.id')
                ->limit(100)
                ->get(),
            'years' => AcademicYear::query()->when($branches !== null, fn ($q) => $q
                ->whereIn('branch_id', $branches))
                ->when($branchId, fn ($q) => $q
                    ->where('branch_id', $branchId))
                ->orderByDesc('start_date')
                ->get(),
        ]);
    }

    public function storeBook(Request $request)
    {
        $d = $request->validate(['branch_id' => 'nullable|integer', 'isbn' => 'nullable|string|max:30', 'title' => 'required|string|max:180', 'author' => 'nullable|string|max:150', 'category' => 'nullable|string|max:100', 'copies_total' => 'required|integer|min:1|max:10000']);
        $b = $this->branchId($request, $d['branch_id'] ?? null);
        $n = (int) $d['copies_total'];
        DB::table('library_books')->insert(array_merge($d, ['tenant_id' => $this
            ->tenantId(), 'branch_id' => $b, 'copies_available' => $n, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]));

        return back()->with('success', 'Book added to the library.');
    }

    /** Update catalog details while preserving the number of books currently on loan. */
    /** Update catalog details while preserving copies currently checked out by students. */
    public function updateBook(Request $request, int $book)
    {
        $record = $this->serviceRecord($request, 'library_books', $book);
        $data = $request->validate([
            'isbn' => 'nullable|string|max:30',
            'title' => 'required|string|max:180',
            'author' => 'nullable|string|max:150',
            'category' => 'nullable|string|max:100',
            'copies_total' => 'required|integer|min:1|max:10000',
        ]);

        DB::transaction(function () use ($record, $data): void {
            $locked = DB::table('library_books')->where('id', $record->id)->lockForUpdate()->first();
            $checkedOut = (int) $locked->copies_total - (int) $locked->copies_available;

            if ((int) $data['copies_total'] < $checkedOut) {
                throw ValidationException::withMessages(['copies_total' => "At least {$checkedOut} copies are currently on loan."]);
            }

            DB::table('library_books')->where('id', $locked->id)->update($data + [
                'copies_available' => (int) $data['copies_total'] - $checkedOut,
                'updated_at' => now(),
            ]);
        });

        return back()->with('success', 'Library book updated.');
    }

    /** Deactivate or reactivate a book without removing its loan history. */
    /** Change book availability without deleting its circulation history. */
    public function toggleBook(Request $request, int $book)
    {
        $record = $this->serviceRecord($request, 'library_books', $book);
        DB::table('library_books')->where('id', $record->id)->update(['is_active' => ! $record->is_active, 'updated_at' => now()]);

        return back()->with('success', 'Library book status updated.');
    }

    public function loanBook(Request $request)
    {
        $d = $request->validate(['book_id' => 'required|integer', 'student_id' => 'required|integer', 'due_at' => 'required|date|after_or_equal:today']);
        $student = Student::query()->findOrFail($d['student_id']);
        $b = $this->branchId($request, $student->branch_id);
        abort_unless((int) $student->branch_id === $b, 403);
        DB::transaction(function () use ($d, $student, $b) {
            $book = DB::table('library_books')->where('id', $d['book_id'])->where('branch_id', $b)->where('tenant_id', $this->tenantId())->lockForUpdate()->first();
            if (! $book || ! $book->is_active || $book->copies_available < 1) {
                throw ValidationException::withMessages(['book_id' => 'This book is unavailable at the selected campus.']);
            } DB::table('library_books')->where('id', $book->id)->decrement('copies_available');
            DB::table('library_loans')->insert(['tenant_id' => $this
                ->tenantId(), 'branch_id' => $b, 'book_id' => $book
                ->id, 'student_id' => $student
                ->id, 'issued_by' => auth()
                ->id(), 'issued_at' => today(), 'due_at' => $d['due_at'], 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('success', 'Book issued to student.');
    }

    public function returnBook(Request $request, int $loan)
    {
        $record = DB::table('library_loans')->where('id', $loan)->where('tenant_id', $this->tenantId())->first();
        abort_unless($record && $request->user()->canAccessBranch($record->branch_id), 404);
        DB::transaction(function () use ($record) {
            $locked = DB::table('library_loans')->where('id', $record->id)->lockForUpdate()->first();
            if ($locked->returned_at) {
                return;
            } $days = max(0, today()->diffInDays(Carbon::parse($locked->due_at), false) * -1);
            DB::table('library_loans')->where('id', $record->id)->update(['returned_at' => today(), 'fine_amount' => $days * 5, 'updated_at' => now()]);
            DB::table('library_books')->where('id', $locked->book_id)->increment('copies_available');
        });

        return back()->with('success', 'Book returned; overdue fine calculated at ₹5 per day.');
    }

    public function storeTransportRoute(Request $request)
    {
        $d = $request->validate(['branch_id' => 'nullable|integer', 'name' => 'required|string|max:120', 'vehicle_number' => 'nullable|string|max:40', 'driver_name' => 'nullable|string|max:120', 'driver_phone' => 'nullable|string|max:30', 'monthly_fee' => 'required|numeric|min:0']);
        $b = $this->branchId($request, $d['branch_id'] ?? null);
        DB::table('transport_routes')->insert(array_merge($d, ['tenant_id' => $this
            ->tenantId(), 'branch_id' => $b, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]));

        return back()->with('success', 'Transport route created.');
    }

    /** Update route details without changing existing student assignments. */
    /** Update route details while keeping existing student assignments intact. */
    public function updateTransportRoute(Request $request, int $route)
    {
        $record = $this->serviceRecord($request, 'transport_routes', $route);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'vehicle_number' => 'nullable|string|max:40',
            'driver_name' => 'nullable|string|max:120',
            'driver_phone' => 'nullable|string|max:30',
            'monthly_fee' => 'required|numeric|min:0',
        ]);
        DB::table('transport_routes')->where('id', $record->id)->update($data + ['updated_at' => now()]);

        return back()->with('success', 'Transport route updated.');
    }

    /** Deactivate or reactivate a transport route while retaining its assignments. */
    /** Change whether a route can be assigned to new students. */
    public function toggleTransportRoute(Request $request, int $route)
    {
        $record = $this->serviceRecord($request, 'transport_routes', $route);
        DB::table('transport_routes')->where('id', $record->id)->update(['is_active' => ! $record->is_active, 'updated_at' => now()]);

        return back()->with('success', 'Transport route status updated.');
    }

    public function assignTransport(Request $request)
    {
        $d = $request->validate(['transport_route_id' => 'required|integer', 'student_id' => 'required|integer', 'pickup_point' => 'nullable|string|max:150', 'starts_on' => 'required|date']);
        $s = Student::query()->findOrFail($d['student_id']);
        $b = $this->branchId($request, $s->branch_id);
        abort_unless((int) $s->branch_id === $b, 403);
        $route = DB::table('transport_routes')->where('id', $d['transport_route_id'])
            ->where('branch_id', $b)
            ->where('tenant_id', $this
                ->tenantId())
            ->where('is_active', true)
            ->first();
        abort_unless($route, 422);
        if (DB::table('student_transport')->where('student_id', $s->id)->where('status', 'active')->exists()) {
            throw ValidationException::withMessages(['student_id' => 'End the current transport assignment before assigning another route.']);
        } DB::table('student_transport')->insert(['tenant_id' => $this
            ->tenantId(), 'branch_id' => $b, 'transport_route_id' => $route
            ->id, 'student_id' => $s
            ->id, 'pickup_point' => $d['pickup_point'] ?? null, 'starts_on' => $d['starts_on'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Student transport assigned.');
    }

    public function endTransport(Request $request, int $assignment)
    {
        $row = DB::table('student_transport')->where('id', $assignment)->where('tenant_id', $this->tenantId())->first();
        abort_unless($row && $request->user()->canAccessBranch($row->branch_id), 404);
        DB::table('student_transport')->where('id', $row->id)->update(['status' => 'ended', 'ends_on' => today(), 'updated_at' => now()]);

        return back()->with('success', 'Student transport assignment ended.');
    }

    public function storeRoom(Request $request)
    {
        $d = $request->validate(['branch_id' => 'nullable|integer', 'name' => 'required|string|max:80', 'building' => 'nullable|string|max:100', 'room_type' => 'nullable|string|max:60', 'capacity' => 'required|integer|min:1|max:500', 'monthly_fee' => 'required|numeric|min:0']);
        $b = $this->branchId($request, $d['branch_id'] ?? null);
        DB::table('hostel_rooms')->insert(array_merge($d, ['tenant_id' => $this
            ->tenantId(), 'branch_id' => $b, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]));

        return back()->with('success', 'Hostel room created.');
    }

    /** Update a room and prevent capacity from falling below current occupancy. */
    /** Update a hostel room while protecting its active occupancy limit. */
    public function updateRoom(Request $request, int $room)
    {
        $record = $this->serviceRecord($request, 'hostel_rooms', $room);
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'building' => 'nullable|string|max:100',
            'room_type' => 'nullable|string|max:60',
            'capacity' => 'required|integer|min:1|max:500',
            'monthly_fee' => 'required|numeric|min:0',
        ]);
        $occupied = DB::table('hostel_allocations')->where('hostel_room_id', $record->id)->where('status', 'active')->count();

        if ((int) $data['capacity'] < $occupied) {
            throw ValidationException::withMessages(['capacity' => "Capacity cannot be lower than the {$occupied} active allocations."]);
        }

        DB::table('hostel_rooms')->where('id', $record->id)->update($data + ['updated_at' => now()]);

        return back()->with('success', 'Hostel room updated.');
    }

    /** Deactivate or reactivate a room without removing allocation history. */
    /** Change room availability without removing allocation history. */
    public function toggleRoom(Request $request, int $room)
    {
        $record = $this->serviceRecord($request, 'hostel_rooms', $room);
        DB::table('hostel_rooms')->where('id', $record->id)->update(['is_active' => ! $record->is_active, 'updated_at' => now()]);

        return back()->with('success', 'Hostel room status updated.');
    }

    public function allocateRoom(Request $request)
    {
        $d = $request->validate(['hostel_room_id' => 'required|integer', 'student_id' => 'required|integer', 'starts_on' => 'required|date']);
        $s = Student::query()->findOrFail($d['student_id']);
        $b = $this->branchId($request, $s->branch_id);
        abort_unless((int) $s->branch_id === $b, 403);
        DB::transaction(function () use ($d, $s, $b) {
            $room = DB::table('hostel_rooms')->where('id', $d['hostel_room_id'])
                ->where('branch_id', $b)
                ->where('tenant_id', $this
                    ->tenantId())
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();
            if (! $room) {
                abort(404);
            } $count = DB::table('hostel_allocations')->where('hostel_room_id', $room->id)->where('status', 'active')->count();
            if ($count >= $room->capacity) {
                throw ValidationException::withMessages(['hostel_room_id' => 'This room is at capacity.']);
            } if (DB::table('hostel_allocations')->where('student_id', $s->id)->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['student_id' => 'End the current hostel allocation first.']);
            } DB::table('hostel_allocations')->insert(['tenant_id' => $this
                ->tenantId(), 'branch_id' => $b, 'hostel_room_id' => $room
                ->id, 'student_id' => $s
                ->id, 'starts_on' => $d['starts_on'], 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('success', 'Student assigned to hostel room.');
    }

    public function endHostel(Request $request, int $allocation)
    {
        $row = DB::table('hostel_allocations')->where('id', $allocation)->where('tenant_id', $this->tenantId())->first();
        abort_unless($row && $request->user()->canAccessBranch($row->branch_id), 404);
        DB::table('hostel_allocations')->where('id', $row->id)->update(['status' => 'ended', 'ends_on' => today(), 'updated_at' => now()]);

        return back()->with('success', 'Hostel allocation ended.');
    }

    public function storeScholarship(Request $request)
    {
        $d = $request->validate(['branch_id' => 'nullable|integer', 'name' => 'required|string|max:120', 'criteria' => 'nullable|string|max:2000', 'award_type' => 'required|in:fixed,percent', 'amount' => 'required|numeric|gt:0']);
        if ($d['award_type'] === 'percent' && $d['amount'] > 100) {
            throw ValidationException::withMessages(['amount' => 'Percentage scholarships cannot exceed 100%.']);
        } $b = $this->branchId($request, $d['branch_id'] ?? null);
        DB::table('scholarships')->insert(array_merge($d, ['tenant_id' => $this
            ->tenantId(), 'branch_id' => $b, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]));

        return back()->with('success', 'Scholarship program created.');
    }

    /** Update a scholarship template while preserving all awarded history. */
    /** Update scholarship terms while preserving previously issued awards. */
    public function updateScholarship(Request $request, int $scholarship)
    {
        $record = $this->serviceRecord($request, 'scholarships', $scholarship);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'criteria' => 'nullable|string|max:2000',
            'award_type' => 'required|in:fixed,percent',
            'amount' => 'required|numeric|gt:0',
        ]);

        if ($data['award_type'] === 'percent' && $data['amount'] > 100) {
            throw ValidationException::withMessages(['amount' => 'Percentage scholarships cannot exceed 100%.']);
        }

        DB::table('scholarships')->where('id', $record->id)->update($data + ['updated_at' => now()]);

        return back()->with('success', 'Scholarship program updated.');
    }

    /** Deactivate or reactivate a scholarship without deleting existing awards. */
    /** Change whether a scholarship can be awarded to additional students. */
    public function toggleScholarship(Request $request, int $scholarship)
    {
        $record = $this->serviceRecord($request, 'scholarships', $scholarship);
        DB::table('scholarships')->where('id', $record->id)->update(['is_active' => ! $record->is_active, 'updated_at' => now()]);

        return back()->with('success', 'Scholarship program status updated.');
    }

    public function awardScholarship(Request $request)
    {
        $d = $request->validate(['scholarship_id' => 'required|integer', 'student_id' => 'required|integer', 'academic_year_id' => 'required|integer', 'notes' => 'nullable|string|max:1000']);
        $s = Student::query()->findOrFail($d['student_id']);
        $b = $this->branchId($request, $s->branch_id);
        abort_unless((int) $s->branch_id === $b, 403);
        $sch = DB::table('scholarships')->where('id', $d['scholarship_id'])->where('branch_id', $b)->where('tenant_id', $this->tenantId())->where('is_active', true)->first();
        abort_unless($sch, 404);
        abort_unless(AcademicYear::query()->whereKey($d['academic_year_id'])->where('branch_id', $b)->exists(), 422);
        DB::transaction(function () use ($d, $s, $b, $sch) {
            $assignments = StudentFeeAssignment::query()->with('allocations')
                ->where('student_id', $s
                    ->id)
                ->whereHas('component', fn ($component) => $component->where(fn ($year) => $year
                    ->where('academic_year_id', $d['academic_year_id'])
                    ->orWhereHas('structure', fn ($structure) => $structure->where('academic_year_id', $d['academic_year_id']))))
                ->lockForUpdate()
                ->get();
            $key = ['scholarship_id' => $sch->id, 'student_id' => $s->id, 'academic_year_id' => $d['academic_year_id']];
            $old = (float) (DB::table('student_scholarships')->where($key)->value('awarded_amount') ?? 0);
            $gross = $assignments->sum(fn ($a) => (float) $a->amount);
            $requested = $sch->award_type === 'percent' ? $gross * (float) $sch->amount / 100 : (float) $sch->amount;
            $outstanding = $assignments->sum(fn ($a) => $a->balanceDue());
            $award = round(min($requested, $outstanding + $old), 2);
            $delta = round($award - $old, 2);
            foreach ($assignments as $assignment) {
                if (abs($delta) < 0.005) {
                    break;
                } $discount = (float) $assignment->discount;
                $paid = $assignment->allocations->sum(fn ($allocation) => (float) $allocation->amount);
                if ($delta > 0) {
                    $space = max(0, $assignment->totalDue() - $paid);
                    $change = min($delta, $space);
                    $discount += $change;
                    $delta -= $change;
                } else {
                    $change = min(abs($delta), $discount);
                    $discount -= $change;
                    $delta += $change;
                } $assignment->discount = round($discount, 2);
                $assignment->status = $assignment->balanceDue() <= 0 ? 'paid' : ($paid > 0 ? 'partial' : 'open');
                $assignment->save();
            }
            DB::table('student_scholarships')->updateOrInsert($key, ['tenant_id' => $this
                ->tenantId(), 'branch_id' => $b, 'awarded_amount' => $award, 'status' => 'approved', 'notes' => $d['notes'] ?? null, 'created_at' => now(), 'updated_at' => now()]);
        });

        return back()->with('success', 'Scholarship award saved and applied to the student’s fees for that year.');
    }
}
