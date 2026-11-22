<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Student;
use App\Models\StudentCertificate;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

/** Issues archived, tenant-scoped certificates from verified school records. */
class StudentCertificateService
{
    /**
     * Store a certificate with a stable snapshot and a branch-scoped serial.
     *
     * The row ID is allocated inside the transaction and supplies the serial,
     * avoiding duplicate counters when different staff issue certificates concurrently.
     *
     * @param  array<string, mixed>  $data
     */
    public function issue(Student $student, Branch $branch, Tenant $tenant, int $issuerId, array $data): StudentCertificate
    {
        return DB::transaction(function () use ($student, $branch, $tenant, $issuerId, $data): StudentCertificate {
            $enrollment = $student->enrollments()
                ->with(['academicYear', 'schoolClass', 'section', 'course', 'semester'])
                ->where('status', 'active')
                ->latest('id')
                ->first()
                ?? $student->enrollments()
                    ->with(['academicYear', 'schoolClass', 'section', 'course', 'semester'])
                    ->latest('id')
                    ->first();

            $guardian = $student->guardians()->wherePivot('is_primary', true)->first()
                ?? $student->guardians()->first();

            $certificate = StudentCertificate::query()->create([
                'tenant_id' => $tenant->id,
                'branch_id' => $branch->id,
                'student_id' => $student->id,
                'issued_by' => $issuerId,
                'certificate_type' => $data['certificate_type'],
                'certificate_number' => null,
                'issued_on' => $data['issued_on'],
                'effective_date' => $data['effective_date'] ?? null,
                'purpose' => $data['purpose'] ?? null,
                'conduct' => $data['conduct'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'student_snapshot' => [
                    'institution_name' => $tenant->institution_name,
                    'institution_type' => $tenant->institution_type,
                    'institution_address' => $tenant->address,
                    'institution_phone' => $tenant->phone,
                    'branch_name' => $branch->name,
                    'branch_code' => $branch->code,
                    'branch_address' => $branch->address,
                    'branch_phone' => $branch->phone,
                    'student_name' => trim($student->first_name.' '.$student->last_name),
                    'student_id' => $student->student_id,
                    'admission_number' => $student->admission_number,
                    'date_of_birth' => $student->date_of_birth?->format('Y-m-d'),
                    'gender' => $student->gender,
                    'guardian_name' => $guardian
                        ? trim($guardian->first_name.' '.$guardian->last_name)
                        : null,
                    'class_name' => $enrollment?->schoolClass?->name,
                    'section_name' => $enrollment?->section?->name,
                    'course_name' => $enrollment?->course?->name,
                    'semester_name' => $enrollment?->semester?->name,
                    'academic_year' => $enrollment?->academicYear?->name,
                    'admission_date' => $enrollment?->admission_date?->format('Y-m-d'),
                ],
            ]);

            $prefix = strtoupper(match ($data['certificate_type']) {
                'transfer' => 'TC',
                'character' => 'CC',
                'bonafide' => 'BC',
                'migration' => 'MC',
                'school_leaving' => 'SLC',
            });
            $branchCode = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $branch->code) ?: 'CAMPUS');
            $year = $certificate->issued_on->format('Y');
            $certificate->update([
                'certificate_number' => sprintf('%s/%s/%s/%06d', $prefix, $year, $branchCode, $certificate->id),
            ]);

            return $certificate->refresh();
        });
    }
}
