<?php

namespace App\Http\Controllers;

use App\Models\Examination;
use App\Models\Guardian;
use App\Models\Notice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentResult;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\Timetable;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** Serves tenant-scoped student and parent portal pages. */
class PortalController extends Controller
{
    /** Show the signed-in account's dashboard or the selected child dashboard. */
    public function dashboard(Request $request): View|RedirectResponse
    {
        $portalAccount = $request->user();

        if ($portalAccount instanceof Student) {
            if ($request->query->has('student_id')) {
                return redirect()->route('portal.dashboard');
            }

            return view('portal.student', $this->studentData($portalAccount, $portalAccount));
        }

        if ($portalAccount instanceof Guardian || ($portalAccount instanceof User && $this->isParentAccount($portalAccount))) {
            $children = $this->linkedChildren($portalAccount);
            $sessionKey = $this->selectedStudentSessionKey($portalAccount);

            if ($request->query->has('student_id')) {
                $requestedStudentId = filter_var($request->query('student_id'), FILTER_VALIDATE_INT);
                $student = $requestedStudentId ? $children->firstWhere('id', $requestedStudentId) : null;
                abort_unless($student instanceof Student, 404);

                $request->session()->put($sessionKey, $student->id);

                return redirect()->route('portal.dashboard');
            }

            $selectedStudentId = $request->session()->get($sessionKey);

            if ($selectedStudentId !== null) {
                $student = $children->firstWhere('id', (int) $selectedStudentId);

                if ($student instanceof Student) {
                    return view('portal.student', $this->studentData($student, $portalAccount) + [
                        'portalChildren' => $children,
                        'selectedPortalStudentId' => $student->id,
                        'isParentPortal' => true,
                    ]);
                }

                $request->session()->forget($sessionKey);
            }

            return view('portal.parent', [
                'children' => $children,
                'portalChildren' => $children,
                'selectedPortalStudentId' => null,
            ]);
        }

        abort_unless($portalAccount instanceof User, 403);
        abort_unless($portalAccount->hasRole('student') || $portalAccount->tenantMembership()?->role === 'student', 403);

        if ($request->query->has('student_id')) {
            return redirect()->route('portal.dashboard');
        }

        $student = $portalAccount->students()->firstOrFail();

        return view('portal.student', $this->studentData($student, $portalAccount));
    }

    /** Store the selected linked student in the signed-in parent's session. */
    public function selectChild(Request $request): RedirectResponse
    {
        $portalAccount = $request->user();
        abort_unless(
            $portalAccount instanceof Guardian
                || ($portalAccount instanceof User && $this->isParentAccount($portalAccount)),
            403,
        );

        $validated = $request->validate([
            'student_id' => ['nullable', 'integer', 'min:1'],
        ]);
        $sessionKey = $this->selectedStudentSessionKey($portalAccount);

        if (empty($validated['student_id'])) {
            $request->session()->forget($sessionKey);

            return redirect()->route('portal.dashboard');
        }

        $student = $this->linkedChildren($portalAccount)
            ->firstWhere('id', (int) $validated['student_id']);
        abort_unless($student instanceof Student, 404);

        $request->session()->put($sessionKey, $student->id);

        return redirect()->route('portal.dashboard');
    }

    /** Show one linked child's full portal dashboard. */
    public function student(Request $request, int $studentId): View
    {
        $student = Student::query()->findOrFail($studentId);
        $portalAccount = $request->user();
        $this->authorizeStudent($portalAccount, $student);

        $data = $this->studentData($student, $portalAccount);

        if ($this->isParentAccount($portalAccount)) {
            $request->session()->put($this->selectedStudentSessionKey($portalAccount), $student->id);
            $data += [
                'portalChildren' => $this->linkedChildren($portalAccount),
                'selectedPortalStudentId' => $student->id,
                'isParentPortal' => true,
            ];
        }

        return view('portal.student', $data);
    }

    /** Show attendance history for a student account or an authorized parent's child. */
    public function attendance(Request $request, int $studentId): View
    {
        $student = Student::query()->findOrFail($studentId);
        $portalAccount = $request->user();
        $this->authorizeStudent($portalAccount, $student);

        $data = [
            'student' => $student,
            'records' => $student->attendance()
                ->with(['schoolClass', 'section', 'course', 'semester'])
                ->latest('attendance_date')
                ->paginate(20),
            'attendanceCount' => $student->attendance()->count(),
            'presentCount' => $student->attendance()->where('status', 'present')->count(),
        ];

        if ($this->isParentAccount($portalAccount)) {
            $request->session()->put($this->selectedStudentSessionKey($portalAccount), $student->id);
            $data += [
                'portalChildren' => $this->linkedChildren($portalAccount),
                'selectedPortalStudentId' => $student->id,
            ];
        }

        return view('portal.attendance', $data);
    }

    /** Show one standalone section of the portal for the active student. */
    public function page(Request $request, string $page): View|RedirectResponse
    {
        $allowedPages = [
            'profile',
            'subjects',
            'examinations',
            'results',
            'timetable',
            'fees',
            'payments',
            'notices',
        ];
        abort_unless(in_array($page, $allowedPages, true), 404);

        $portalAccount = $request->user();
        $student = $this->activePortalStudent($request, $portalAccount);

        if (! $student) {
            return redirect()->route('portal.dashboard');
        }

        $data = $this->studentData($student, $portalAccount);
        $data['page'] = $page;

        if ($page === 'payments') {
            $data['payments'] = Payment::query()
                ->with('receipt')
                ->where('student_id', $student->id)
                ->latest('paid_at')
                ->paginate(20)
                ->withQueryString();
        }

        if ($page === 'results') {
            $data['results'] = StudentResult::query()
                ->with(['academicYear', 'semester', 'examination'])
                ->where('student_id', $student->id)
                ->where('status', 'published')
                ->latest()
                ->paginate(20)
                ->withQueryString();
        }

        if ($page === 'notices') {
            $data['notices'] = Notice::query()
                ->visibleTo($portalAccount, $student)
                ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $student->branch_id))
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                ->latest('published_at')
                ->paginate(20)
                ->withQueryString();
        }

        if ($this->isParentAccount($portalAccount)) {
            $data += [
                'portalChildren' => $this->linkedChildren($portalAccount),
                'selectedPortalStudentId' => $student->id,
                'isParentPortal' => true,
            ];
        }

        return view('portal.page', $data);
    }

    /** Show a receipt only to the student it belongs to or that student's linked parent. */
    public function receipt(Request $request, int $paymentId): View
    {
        $payment = Payment::query()->with('student')->findOrFail($paymentId);
        $student = $payment->student;
        abort_unless($student instanceof Student, 404);
        $portalAccount = $request->user();
        $this->authorizeStudent($portalAccount, $student);

        $payment->load([
            'student.branch',
            'academicYear',
            'receipt',
            'paymentTenders',
            'allocations.studentFeeAssignment.component.structure',
            'allocations.studentFeeAssignment.component.academicYear',
        ]);

        $data = [
            'payment' => $payment,
            'tenant' => Tenant::query()->findOrFail($payment->tenant_id),
            'student' => $student,
        ];

        if ($this->isParentAccount($portalAccount)) {
            $request->session()->put($this->selectedStudentSessionKey($portalAccount), $student->id);
            $data += [
                'portalChildren' => $this->linkedChildren($portalAccount),
                'selectedPortalStudentId' => $student->id,
            ];
        }

        return view('portal.receipt', $data);
    }

    /** Load all tenant-safe student data used by the portal dashboard. */
    private function studentData(Student $student, User|Student|Guardian $portalAccount): array
    {
        $student->load(
            'branch',
            'enrollments.schoolClass',
            'enrollments.section',
            'enrollments.course',
            'enrollments.semester',
            'enrollments.academicYear',
            'guardians',
        );

        $enrollment = $student->enrollments
            ->where('status', 'active')
            ->sortByDesc('created_at')
            ->first();
        $assignments = $student->feeAssignments()
            ->with(['component.structure.academicYear', 'component.academicYear', 'allocations'])
            ->get();
        $payments = Payment::query()
            ->with(['receipt', 'allocations.studentFeeAssignment.component'])
            ->where('student_id', $student->id)
            ->latest('paid_at')
            ->limit(20)
            ->get();
        $results = StudentResult::query()
            ->with(['academicYear', 'semester', 'examination'])
            ->where('student_id', $student->id)
            ->where('status', 'published')
            ->latest()
            ->limit(30)
            ->get();
        $attendanceCount = $student->attendance()->count();
        $presentCount = $student->attendance()->where('status', 'present')->count();
        $attendanceRate = $attendanceCount > 0 ? (int) round(($presentCount / $attendanceCount) * 100) : null;
        $attendanceBreakdown = $student->attendance()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $recentAttendance = $student->attendance()
            ->latest('attendance_date')
            ->limit(5)
            ->get(['attendance_date', 'status', 'session']);
        $timetable = $enrollment
            ? $this->studentTimetable($student, $enrollment->academic_year_id, $enrollment->school_class_id, $enrollment->section_id)
            : collect();
        $subjects = $enrollment && ($enrollment->school_class_id || $enrollment->course_id)
            ? Subject::query()
                ->where('branch_id', $student->branch_id)
                ->where('is_active', true)
                ->when($enrollment->school_class_id, fn ($query) => $query->where('school_class_id', $enrollment->school_class_id))
                ->when($enrollment->course_id, fn ($query) => $query->where('course_id', $enrollment->course_id))
                ->orderBy('name')
                ->get()
            : collect();
        $upcomingExaminations = $enrollment
            ? Examination::query()
                ->with('academicYear')
                ->where('branch_id', $student->branch_id)
                ->where('academic_year_id', $enrollment->academic_year_id)
                ->where('status', 'published')
                ->whereDate('starts_on', '>=', today())
                ->orderBy('starts_on')
                ->limit(5)
                ->get()
            : collect();
        $notices = Notice::query()
            ->visibleTo($portalAccount, $student)
            ->where(fn ($query) => $query->whereNull('branch_id')->orWhere('branch_id', $student->branch_id))
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('published_at')
            ->limit(20)
            ->get();

        $tenantId = app(TenantContext::class)->id();
        $transport = DB::table('student_transport')
            ->join('transport_routes', 'transport_routes.id', '=', 'student_transport.transport_route_id')
            ->where('student_transport.tenant_id', $tenantId)
            ->where('student_transport.student_id', $student->id)
            ->where('student_transport.status', 'active')
            ->select(
                'transport_routes.name as route_name',
                'transport_routes.vehicle_number',
                'transport_routes.driver_name',
                'transport_routes.driver_phone',
                'transport_routes.monthly_fee',
                'student_transport.pickup_point',
            )
            ->first();
        $hostel = DB::table('hostel_allocations')
            ->join('hostel_rooms', 'hostel_rooms.id', '=', 'hostel_allocations.hostel_room_id')
            ->where('hostel_allocations.tenant_id', $tenantId)
            ->where('hostel_allocations.student_id', $student->id)
            ->where('hostel_allocations.status', 'active')
            ->select('hostel_rooms.name', 'hostel_rooms.building', 'hostel_rooms.room_type', 'hostel_rooms.monthly_fee')
            ->first();
        $scholarships = DB::table('student_scholarships')
            ->join('scholarships', 'scholarships.id', '=', 'student_scholarships.scholarship_id')
            ->join('academic_years', 'academic_years.id', '=', 'student_scholarships.academic_year_id')
            ->where('student_scholarships.tenant_id', $tenantId)
            ->where('student_scholarships.student_id', $student->id)
            ->where('student_scholarships.status', 'approved')
            ->select('scholarships.name', 'academic_years.name as year_name', 'student_scholarships.awarded_amount')
            ->orderByDesc('academic_years.start_date')
            ->get();

        return compact(
            'student',
            'enrollment',
            'assignments',
            'payments',
            'results',
            'attendanceCount',
            'presentCount',
            'attendanceRate',
            'attendanceBreakdown',
            'recentAttendance',
            'timetable',
            'subjects',
            'upcomingExaminations',
            'notices',
            'transport',
            'hostel',
            'scholarships',
        );
    }

    /** Resolve the student's own record or the parent-selected child from session. */
    private function activePortalStudent(Request $request, User|Student|Guardian $portalAccount): ?Student
    {
        if ($portalAccount instanceof Student) {
            return $portalAccount;
        }

        if ($portalAccount instanceof Guardian || $this->isParentAccount($portalAccount)) {
            $selectedStudentId = $request->session()->get($this->selectedStudentSessionKey($portalAccount));
            $student = $selectedStudentId
                ? $this->linkedChildren($portalAccount)->firstWhere('id', (int) $selectedStudentId)
                : null;

            return $student instanceof Student ? $student : null;
        }

        abort_unless($portalAccount->hasRole('student') || $portalAccount->tenantMembership()?->role === 'student', 403);

        return $portalAccount->students()->first();
    }

    /** Load the student's weekly timetable for the active enrollment. */
    private function studentTimetable(Student $student, int $academicYearId, ?int $classId, ?int $sectionId): Collection
    {
        return Timetable::query()
            ->with(['schoolClass', 'section', 'teacher.user'])
            ->where('branch_id', $student->branch_id)
            ->where('academic_year_id', $academicYearId)
            ->where(fn ($query) => $query->whereNull('school_class_id')->orWhere('school_class_id', $classId))
            ->where(fn ($query) => $query->whereNull('section_id')->orWhere('section_id', $sectionId))
            ->orderByRaw("CASE weekday WHEN 'Monday' THEN 1 WHEN 'Tuesday' THEN 2 WHEN 'Wednesday' THEN 3 WHEN 'Thursday' THEN 4 WHEN 'Friday' THEN 5 WHEN 'Saturday' THEN 6 ELSE 7 END")
            ->orderBy('starts_at')
            ->get();
    }

    /** Resolve every student linked to a guardian or legacy parent user. */
    private function linkedChildren(Guardian|User $portalAccount): Collection
    {
        if ($portalAccount instanceof Guardian) {
            return $portalAccount->students()
                ->with(['branch', 'enrollments.schoolClass', 'enrollments.section', 'enrollments.academicYear', 'enrollments.course', 'enrollments.semester'])
                ->orderBy('first_name')
                ->orderBy('last_name')
                ->get();
        }

        return $portalAccount->guardians()
            ->with(['students.branch', 'students.enrollments.schoolClass', 'students.enrollments.section', 'students.enrollments.academicYear', 'students.enrollments.course', 'students.enrollments.semester'])
            ->get()
            ->flatMap(fn (Guardian $guardian) => $guardian->students)
            ->unique('id')
            ->sortBy(fn (Student $student) => strtolower($student->last_name.' '.$student->first_name))
            ->values();
    }

    /** Ensure a portal account can access only its own record or a linked child. */
    private function authorizeStudent(User|Student|Guardian|null $portalAccount, Student $student): void
    {
        if ($portalAccount instanceof Student) {
            abort_unless((int) $portalAccount->id === (int) $student->id, 403);

            return;
        }

        if ($portalAccount instanceof Guardian) {
            abort_unless($portalAccount->students()->whereKey($student->id)->exists(), 403);

            return;
        }

        abort_unless(
            $portalAccount instanceof User
                && ($portalAccount->students()->whereKey($student->id)->exists()
                    || $portalAccount->guardians()->whereHas('students', fn ($query) => $query->whereKey($student->id))->exists()),
            403,
        );
    }

    /** Determine whether the authenticated portal account represents a parent. */
    private function isParentAccount(User|Student|Guardian|null $portalAccount): bool
    {
        return $portalAccount instanceof Guardian
            || ($portalAccount instanceof User
                && ($portalAccount->hasRole('parent') || $portalAccount->tenantMembership()?->role === 'parent'));
    }

    /** Build a session key isolated to the current tenant and portal account. */
    private function selectedStudentSessionKey(User|Guardian $portalAccount): string
    {
        return implode('.', [
            'portal',
            'selected_student',
            app(TenantContext::class)->id() ?? 'none',
            class_basename($portalAccount),
            $portalAccount->getAuthIdentifier(),
        ]);
    }
}
