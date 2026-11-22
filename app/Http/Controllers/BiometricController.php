<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Registers biometric devices, mappings, and idempotent attendance events.
 */
class BiometricController extends Controller
{
    public function index(Request $request)
    {
        $branches = $request->user()->accessibleBranchIds();
        $devices = DB::table('biometric_devices')->where('tenant_id', app(TenantContext::class)
            ->id())
            ->when($branches !== null, fn ($q) => $q
                ->whereIn('branch_id', $branches))
            ->latest()
            ->get();
        $mappings = DB::table('biometric_mappings')->join('biometric_devices', 'biometric_devices.id', '=', 'biometric_mappings.biometric_device_id')
            ->leftJoin('students', 'students.id', '=', 'biometric_mappings.student_id')
            ->leftJoin('teachers', 'teachers.id', '=', 'biometric_mappings.teacher_id')
            ->leftJoin('users as teacher_users', 'teacher_users.id', '=', 'teachers.user_id')
            ->where('biometric_mappings.tenant_id', app(TenantContext::class)
                ->id())
            ->when($branches !== null, fn ($q) => $q
                ->whereIn('biometric_mappings.branch_id', $branches))
            ->select('biometric_mappings.*', 'biometric_devices.name as device_name', 'students.first_name', 'students.last_name', 'students.student_id', 'teacher_users.name as teacher_name')
            ->latest('biometric_mappings.id')
            ->get();
        $events = DB::table('biometric_sync_logs')->join('biometric_devices', 'biometric_devices.id', '=', 'biometric_sync_logs.biometric_device_id')
            ->where('biometric_sync_logs.tenant_id', app(TenantContext::class)
                ->id())
            ->when($branches !== null, fn ($q) => $q
                ->whereIn('biometric_devices.branch_id', $branches))
            ->select('biometric_sync_logs.*', 'biometric_devices.name as device_name')
            ->latest('biometric_sync_logs.occurred_at')
            ->limit(100)
            ->get();

        return view('admin.biometric-devices', ['devices' => $devices, 'mappings' => $mappings, 'events' => $events, 'students' => Student::query()->where('status', 'active')
            ->when($branches !== null, fn ($q) => $q
                ->whereIn('branch_id', $branches))
            ->orderBy('first_name')
            ->get(), 'teachers' => Teacher::query()
            ->where('is_active', true)
            ->when($branches !== null, fn ($q) => $q
                ->whereIn('branch_id', $branches))
            ->with('user')
            ->get(), 'branches' => Branch::query()
            ->where('is_active', true)
            ->when($branches !== null, fn ($q) => $q
                ->whereIn('id', $branches))
            ->orderBy('name')
            ->get()]);
    }

    public function store(Request $request)
    {
        $d = $request->validate(['branch_id' => 'required|integer', 'name' => 'required|string|max:120', 'device_identifier' => 'required|string|max:120', 'type' => 'required|string|max:50']);
        $branch = Branch::query()->findOrFail($d['branch_id']);
        abort_unless($request->user()->canAccessBranch($branch->id), 403);
        $secret = Str::random(64);
        DB::table('biometric_devices')->insert(array_merge($d, ['tenant_id' => app(TenantContext::class)
            ->id(), 'api_token_hash' => hash('sha256', $secret), 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]));

        return back()->with('device_secret', $secret)->with('success', 'Device registered. Copy the secret now; it is shown only once.');
    }

    public function mapping(Request $request)
    {
        $d = $request->validate(['biometric_device_id' => 'required|integer', 'person_identifier' => 'required|string|max:120', 'target_type' => 'required|in:student,teacher', 'student_id' => 'nullable|required_if:target_type,student|integer', 'teacher_id' => 'nullable|required_if:target_type,teacher|integer']);
        $person = $d['target_type'] === 'student' ? Student::query()->findOrFail($d['student_id']) : Teacher::query()->findOrFail($d['teacher_id']);
        $device = DB::table('biometric_devices')->where('id', $d['biometric_device_id'])
            ->where('tenant_id', app(TenantContext::class)
                ->id())
            ->where('branch_id', $person
                ->branch_id)
            ->where('is_active', true)
            ->first();
        abort_unless($device && $request->user()->canAccessBranch($device->branch_id), 403);
        DB::table('biometric_mappings')->updateOrInsert(['biometric_device_id' => $device
            ->id, 'person_identifier' => $d['person_identifier']], ['tenant_id' => $device
            ->tenant_id, 'branch_id' => $device
            ->branch_id, 'student_id' => $d['target_type'] === 'student' ? $person
            ->id : null, 'teacher_id' => $d['target_type'] === 'teacher' ? $person
            ->id : null, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Device person mapped.');
    }

    public function deactivate(Request $request, int $deviceId)
    {
        $device = DB::table('biometric_devices')->where('id', $deviceId)->where('tenant_id', app(TenantContext::class)->id())->first();
        abort_unless($device && $request->user()->canAccessBranch($device->branch_id), 404);
        DB::table('biometric_devices')->where('id', $device->id)->update(['is_active' => false, 'updated_at' => now()]);

        return back()->with('success', 'Biometric device disabled; its token can no longer submit attendance.');
    }

    public function receive(Request $request)
    {
        $device = $request->attributes->get('biometric_device');
        $latestAcceptedTime = now()->addMinutes(5)->toDateTimeString();
        $data = $request->validate([
            'events' => ['required', 'array', 'min:1', 'max:250'],
            'events.*.event_id' => ['required', 'string', 'max:120'],
            'events.*.person_id' => ['required', 'string', 'max:120'],
            'events.*.occurred_at' => ['required', 'date', 'before_or_equal:'.$latestAcceptedTime],
        ]);
        $results = [];
        foreach ($data['events'] as $event) {
            $processed = false;
            DB::transaction(function () use ($device, $event, &$processed): void {
                $now = now();
                $logInserted = DB::table('biometric_sync_logs')->insertOrIgnore([
                    'tenant_id' => $device->tenant_id,
                    'biometric_device_id' => $device->id,
                    'event_identifier' => $event['event_id'],
                    'person_identifier' => $event['person_id'],
                    'occurred_at' => $event['occurred_at'],
                    'status' => 'received',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                if ($logInserted === 0) {
                    return;
                }

                $mapping = DB::table('biometric_mappings')
                    ->where('biometric_device_id', $device->id)
                    ->where('person_identifier', $event['person_id'])
                    ->where('tenant_id', $device->tenant_id)
                    ->first();

                if (! $mapping || (! $mapping->student_id && ! $mapping->teacher_id)) {
                    $this->updateEventStatus($device->id, $event['event_id'], 'unmapped', 'Person identifier has no student or staff mapping.');
                    $processed = true;

                    return;
                }

                $occurredAt = Carbon::parse($event['occurred_at']);
                $attendanceDate = $occurredAt->toDateString();

                if ($mapping->student_id) {
                    $student = Student::query()
                        ->whereKey($mapping->student_id)
                        ->where('branch_id', $device->branch_id)
                        ->where('status', 'active')
                        ->first();
                    $academicYear = AcademicYear::query()
                        ->where('branch_id', $device->branch_id)
                        ->where('start_date', '<=', $attendanceDate)
                        ->where('end_date', '>=', $attendanceDate)
                        ->orderByDesc('start_date')
                        ->first();

                    if (! $student || ! $academicYear) {
                        $this->updateEventStatus($device->id, $event['event_id'], 'failed', 'Student or matching academic year is unavailable.');
                        $processed = true;

                        return;
                    }

                    DB::table('attendance')->insertOrIgnore([
                        'student_id' => $student->id,
                        'attendance_date' => $attendanceDate,
                        'session' => 'full_day',
                        'tenant_id' => $device->tenant_id,
                        'branch_id' => $device->branch_id,
                        'academic_year_id' => $academicYear->id,
                        'status' => 'present',
                        'marked_by' => null,
                        'remarks' => 'Biometric device: '.$device->name,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } else {
                    $teacher = Teacher::query()
                        ->whereKey($mapping->teacher_id)
                        ->where('branch_id', $device->branch_id)
                        ->where('is_active', true)
                        ->first();

                    if (! $teacher) {
                        $this->updateEventStatus($device->id, $event['event_id'], 'failed', 'Mapped teacher is inactive or unavailable.');
                        $processed = true;

                        return;
                    }

                    DB::table('teacher_attendance')->insertOrIgnore([
                        'tenant_id' => $device->tenant_id,
                        'branch_id' => $device->branch_id,
                        'teacher_id' => $teacher->id,
                        'attendance_date' => $attendanceDate,
                        'session' => 'full_day',
                        'status' => 'present',
                        'marked_by' => null,
                        'remarks' => 'Biometric device: '.$device->name,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }

                $this->updateEventStatus($device->id, $event['event_id'], 'synced');
                $processed = true;
            });
            $results[] = ['event_id' => $event['event_id'], 'status' => $processed ? 'processed' : 'duplicate'];
        }

        return response()->json(['accepted' => count($results), 'events' => $results], 202);
    }

    /** Update the processing result for one event from the authenticated device. */
    private function updateEventStatus(int $deviceId, string $eventId, string $status, ?string $error = null): void
    {
        DB::table('biometric_sync_logs')
            ->where('biometric_device_id', $deviceId)
            ->where('event_identifier', $eventId)
            ->update([
                'status' => $status,
                'error' => $error,
                'updated_at' => now(),
            ]);
    }
}
