<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Student;
use App\Models\StudentCertificate;
use App\Models\Tenant;
use App\Services\StudentCertificateService;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Lists, issues, and prints tenant-scoped student certificates. */
class StudentCertificateController extends Controller
{
    /** Show available students and the certificate issue history. */
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('students.view'), 403);

        $user = $request->user();
        $tenantId = (int) app(TenantContext::class)->id();
        $accessibleBranches = $user->accessibleBranchIds();
        $branchId = $user->currentBranchId() ?: ($request->filled('branch_id') ? $request->integer('branch_id') : null);

        if ($branchId) {
            abort_unless($user->canAccessBranch($branchId), 403);
        }

        $branches = Branch::query()
            ->where('is_active', true)
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('id', $accessibleBranches))
            ->orderBy('name')
            ->get();

        $students = Student::query()
            ->with(['enrollments' => fn ($query) => $query->where('status', 'active')->latest('id')])
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('branch_id', $accessibleBranches))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->orderBy('first_name')
            ->get();

        $certificates = StudentCertificate::query()
            ->with(['student', 'issuer'])
            ->where('tenant_id', $tenantId)
            ->when($accessibleBranches !== null, fn ($query) => $query->whereIn('branch_id', $accessibleBranches))
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->when($request->filled('certificate_type'), fn ($query) => $query->where('certificate_type', $request->string('certificate_type')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim().'%';
                $query->where(function ($certificateQuery) use ($search): void {
                    $certificateQuery->where('certificate_number', 'like', $search)
                        ->orWhereHas('student', fn ($studentQuery) => $studentQuery
                            ->where('first_name', 'like', $search)
                            ->orWhere('last_name', 'like', $search)
                            ->orWhere('admission_number', 'like', $search));
                });
            })
            ->latest('issued_on')
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return view('certificates.index', compact('branches', 'branchId', 'students', 'certificates'));
    }

    /** Validate permissions and issue one archived certificate. */
    public function store(Request $request, StudentCertificateService $certificateService): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('students.edit'), 403);

        foreach (['issued_on', 'effective_date'] as $dateField) {
            $normalizedDate = $this->normalizeDate($request->input($dateField));
            if ($normalizedDate !== null) {
                $request->merge([$dateField => $normalizedDate]);
            }
        }

        $data = $request->validate([
            'branch_id' => ['nullable', 'integer'],
            'student_id' => ['required', 'integer'],
            'certificate_type' => ['required', 'in:transfer,character,bonafide,migration,school_leaving'],
            'issued_on' => ['required', 'date_format:Y-m-d'],
            'effective_date' => ['nullable', 'required_if:certificate_type,transfer,migration,school_leaving', 'date_format:Y-m-d'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'conduct' => ['nullable', 'required_if:certificate_type,character', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $student = Student::query()->findOrFail($data['student_id']);
        $branchId = (int) ($request->user()->currentBranchId() ?: ($data['branch_id'] ?? $student->branch_id));
        abort_unless($request->user()->canAccessBranch($branchId), 403);
        abort_unless((int) $student->branch_id === $branchId, 422, 'Choose a student from the selected campus.');

        $branch = Branch::query()->findOrFail($branchId);
        $tenant = Tenant::query()->findOrFail(app(TenantContext::class)->id());

        if ($data['certificate_type'] === 'bonafide') {
            abort_unless(
                $student->status === 'active' && $student->enrollments()->where('status', 'active')->exists(),
                422,
                'A Bonafide Certificate can only be issued to a currently enrolled student.'
            );
        }

        $certificate = $certificateService->issue($student, $branch, $tenant, (int) $request->user()->id, $data);

        return redirect()
            ->route('students.certificates.show', $certificate)
            ->with('success', 'Certificate issued and added to the student certificate register.');
    }

    /** Render an archived certificate as an A4 print-ready document. */
    public function show(Request $request, int $certificate): View
    {
        abort_unless($request->user()->hasPermission('students.view'), 403);

        $record = StudentCertificate::query()->whereKey($certificate)->firstOrFail();
        abort_unless($request->user()->canAccessBranch($record->branch_id), 404);
        $tenant = Tenant::query()->findOrFail($record->tenant_id);

        return view('certificates.show', [
            'certificate' => $record,
            'snapshot' => $record->student_snapshot,
            'tenant' => $tenant,
        ]);
    }

    /** Convert shared datepicker input formats to a database date string. */
    private function normalizeDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        foreach (['!d/m/Y', '!Y-m-d', '!d-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();

            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }
}
