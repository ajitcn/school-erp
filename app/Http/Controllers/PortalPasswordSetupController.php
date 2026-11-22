<?php

namespace App\Http\Controllers;

use App\Models\Guardian;
use App\Models\Student;
use App\Models\Tenant;
use App\Services\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Issues and consumes one-time password setup links for student and guardian records. */
class PortalPasswordSetupController extends Controller
{
    /** Show the student or parent PIN-based password setup form. */
    public function pinForm(): View
    {
        return view('auth.portal-password-pin');
    }

    /** Verify a short-lived one-time PIN and save the password to its portal record. */
    public function storePin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'account_type' => ['required', 'in:student,parent'],
            'identifier' => ['required', 'string', 'max:255'],
            'setup_pin' => ['required', 'digits:6'],
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $identifier = trim($data['identifier']);
        $rateLimitKey = 'portal-password-pin:'.hash('sha256', implode('|', [
            strtolower($data['account_type']),
            strtolower($identifier),
            (string) $request->ip(),
        ]));

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return back()
                ->withErrors(['setup_pin' => 'Too many attempts. Please wait before trying again.'])
                ->onlyInput('account_type', 'identifier');
        }

        RateLimiter::hit($rateLimitKey, 600);

        $account = $this->findPortalRecord($data['account_type'], $identifier, $data['setup_pin']);
        if (! $account) {
            return $this->invalidPinResponse($request);
        }

        $tenant = Tenant::query()->find($account->tenant_id);
        if (! $tenant || ! $tenant->hasValidSubscription()) {
            return $this->invalidPinResponse($request);
        }

        $consumed = DB::transaction(function () use ($account, $data): bool {
            $query = DB::table('portal_password_setup_tokens')
                ->where('tenant_id', $account->tenant_id)
                ->where('pin_hash', $this->pinHash($data['setup_pin']))
                ->whereNull('used_at')
                ->where('pin_expires_at', '>', now())
                ->lockForUpdate();

            $setup = $account instanceof Student
                ? $query->where('student_id', $account->id)->first()
                : $query->where('guardian_id', $account->id)->first();

            if (! $setup) {
                return false;
            }

            $record = $account instanceof Student
                ? Student::withoutGlobalScope('tenant')->where('tenant_id', $account->tenant_id)->where('status', 'active')->findOrFail($account->id)
                : Guardian::withoutGlobalScope('tenant')->where('tenant_id', $account->tenant_id)->findOrFail($account->id);

            $record->forceFill(['password' => $data['password']])->save();

            DB::table('portal_password_setup_tokens')
                ->where('id', $setup->id)
                ->update(['used_at' => now(), 'updated_at' => now()]);

            return true;
        });

        if (! $consumed) {
            return $this->invalidPinResponse($request);
        }

        RateLimiter::clear($rateLimitKey);

        return redirect()
            ->route('portal.password.login')
            ->with('status', 'Your password is ready. Sign in using your portal account.');
    }

    /** Generate a single-use setup link for a student record. */
    public function issueStudent(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStaff($request, $student->branch_id);

        abort_unless($student->status === 'active', 422, 'Activate this student before enabling portal sign-in.');

        return $this->issue(
            studentId: $student->id,
            guardianId: null,
            identifier: $student->student_id,
            accountType: 'student',
            accountName: trim($student->first_name.' '.$student->last_name),
        );
    }

    /** Generate a single-use setup link for a guardian attached to this student. */
    public function issueGuardian(Request $request, Student $student, Guardian $guardian): RedirectResponse
    {
        $this->authorizeStaff($request, $student->branch_id);
        abort_unless($student->guardians()->whereKey($guardian->id)->exists(), 404);
        abort_unless($request->user()->canAccessBranch($guardian->branch_id), 403);

        return $this->issue(
            studentId: null,
            guardianId: $guardian->id,
            identifier: $guardian->email ?: $guardian->phone,
            accountType: 'parent',
            accountName: trim($guardian->first_name.' '.$guardian->last_name),
        );
    }

    /** Generate a short-lived PIN for a student portal password setup. */
    public function issueStudentPin(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeStaff($request, $student->branch_id);
        abort_unless($student->status === 'active', 422, 'Activate this student before enabling portal sign-in.');

        return $this->issuePin(
            studentId: $student->id,
            guardianId: null,
            identifier: $student->student_id,
            accountType: 'student',
            accountName: trim($student->first_name.' '.$student->last_name),
        );
    }

    /** Generate a short-lived PIN for a guardian attached to the selected student. */
    public function issueGuardianPin(Request $request, Student $student, Guardian $guardian): RedirectResponse
    {
        $this->authorizeStaff($request, $student->branch_id);
        abort_unless($student->guardians()->whereKey($guardian->id)->exists(), 404);
        abort_unless($request->user()->canAccessBranch($guardian->branch_id), 403);
        abort_unless($guardian->email || $guardian->phone, 422, 'Add an email address or mobile number before enabling parent portal sign-in.');

        return $this->issuePin(
            studentId: null,
            guardianId: $guardian->id,
            identifier: $guardian->email ?: $guardian->phone,
            accountType: 'parent',
            accountName: trim($guardian->first_name.' '.$guardian->last_name),
        );
    }

    /** Show the password form only while the hashed token remains valid. */
    public function show(string $token): View|Response
    {
        $setup = $this->findValidToken($token);

        if (! $setup) {
            $tenantId = DB::table('portal_password_setup_tokens')
                ->where('token_hash', hash('sha256', $token))
                ->value('tenant_id');
            $tenant = $tenantId ? Tenant::query()->find($tenantId) : null;

            return response()
                ->view('auth.portal-password-link-expired', compact('tenant'), 410)
                ->header('Cache-Control', 'no-store, private')
                ->header('Referrer-Policy', 'no-referrer');
        }

        $account = $setup->student_id
            ? Student::withoutGlobalScope('tenant')->where('tenant_id', $setup->tenant_id)->findOrFail($setup->student_id)
            : Guardian::withoutGlobalScope('tenant')->where('tenant_id', $setup->tenant_id)->findOrFail($setup->guardian_id);
        $tenant = Tenant::query()->findOrFail($setup->tenant_id);

        return response()->view('auth.portal-password-setup', [
            'token' => $token,
            'tenant' => $tenant,
            'accountType' => $setup->student_id ? 'student' : 'parent',
            'accountName' => trim($account->first_name.' '.$account->last_name),
            'loginIdentifier' => $setup->student_id ? $account->student_id : ($account->email ?: $account->phone),
            'expiresAt' => $setup->expires_at,
        ])->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer');
    }

    /** Save the password hash on the targeted record and consume the setup token atomically. */
    public function store(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string', 'min:10', 'confirmed'],
        ]);

        $consumed = DB::transaction(function () use ($data, $token): bool {
            $setup = DB::table('portal_password_setup_tokens')
                ->where('token_hash', hash('sha256', $token))
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $setup) {
                return false;
            }

            if ($setup->student_id) {
                Student::withoutGlobalScope('tenant')
                    ->where('tenant_id', $setup->tenant_id)
                    ->where('status', 'active')
                    ->findOrFail($setup->student_id)
                    ->forceFill(['password' => $data['password']])
                    ->save();
            } else {
                Guardian::withoutGlobalScope('tenant')
                    ->where('tenant_id', $setup->tenant_id)
                    ->findOrFail($setup->guardian_id)
                    ->forceFill(['password' => $data['password']])
                    ->save();
            }

            DB::table('portal_password_setup_tokens')
                ->where('id', $setup->id)
                ->update(['used_at' => now(), 'updated_at' => now()]);

            return true;
        });

        if (! $consumed) {
            throw ValidationException::withMessages([
                'password' => 'This password setup link has expired or has already been used. Ask your institution for a new link.',
            ]);
        }

        return redirect()
            ->route('portal.password.login')
            ->with('status', 'Your password is ready. Sign in using your portal account.');
    }

    /** Create and persist only a hash of the bearer token. */
    private function issue(
        ?int $studentId,
        ?int $guardianId,
        string $identifier,
        string $accountType,
        string $accountName,
    ): RedirectResponse {
        $plainToken = Str::random(64);
        $expiresInMinutes = 60;

        DB::table('portal_password_setup_tokens')->insert([
            'tenant_id' => app(TenantContext::class)->id(),
            'student_id' => $studentId,
            'guardian_id' => $guardianId,
            'generated_by' => auth()->id(),
            'token_hash' => hash('sha256', $plainToken),
            'expires_at' => now()->addMinutes($expiresInMinutes),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()
            ->with('success', "A one-time {$accountType} password link was generated. It expires in {$expiresInMinutes} minutes and works once.")
            ->with('portal_password_link', [
                'account_type' => $accountType,
                'account_name' => $accountName,
                'identifier' => $identifier,
                'url' => route('portal.password.setup', $plainToken),
                'expires_in' => $expiresInMinutes,
            ]);
    }

    /** Ensure only authorized staff can issue links for records in accessible branches. */
    private function authorizeStaff(Request $request, int $branchId): void
    {
        $user = $request->user();

        abort_unless($user->isTenantAdmin() || $user->hasPermission('users.manage'), 403);
        abort_unless($user->canAccessBranch($branchId), 403);
    }

    /** Look up an unconsumed link by its SHA-256 token hash. */
    private function findValidToken(string $token): ?object
    {
        return DB::table('portal_password_setup_tokens')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();
    }

    /** Save only a keyed digest of a one-time six-digit PIN. */
    private function issuePin(
        ?int $studentId,
        ?int $guardianId,
        string $identifier,
        string $accountType,
        string $accountName,
    ): RedirectResponse {
        $pin = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $expiresInMinutes = 10;
        $expiresAt = now()->addMinutes($expiresInMinutes);
        $tenantId = app(TenantContext::class)->id();

        DB::transaction(function () use ($studentId, $guardianId, $tenantId, $pin, $expiresAt): void {
            if ($studentId !== null) {
                Student::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->findOrFail($studentId);
            } else {
                Guardian::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->findOrFail($guardianId);
            }

            $previousPins = DB::table('portal_password_setup_tokens')
                ->where('tenant_id', $tenantId)
                ->whereNull('used_at')
                ->whereNotNull('pin_hash');

            if ($studentId !== null) {
                $previousPins->where('student_id', $studentId);
            } else {
                $previousPins->where('guardian_id', $guardianId);
            }

            $previousPins->update(['used_at' => now(), 'updated_at' => now()]);

            DB::table('portal_password_setup_tokens')->insert([
                'tenant_id' => $tenantId,
                'student_id' => $studentId,
                'guardian_id' => $guardianId,
                'generated_by' => auth()->id(),
                'token_hash' => hash('sha256', Str::random(64)),
                'pin_hash' => $this->pinHash($pin),
                'pin_expires_at' => $expiresAt,
                'expires_at' => $expiresAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return back()
            ->with('success', "A one-time {$accountType} setup PIN was generated. Share it with the account holder within {$expiresInMinutes} minutes.")
            ->with('portal_password_pin', [
                'account_type' => $accountType,
                'account_name' => $accountName,
                'identifier' => $identifier,
                'pin' => $pin,
                'expires_in' => $expiresInMinutes,
            ]);
    }

    /** Create a keyed digest so a database leak cannot reveal short PINs offline. */
    private function pinHash(string $pin): string
    {
        return hash_hmac('sha256', $pin, (string) config('app.key'));
    }

    /** Resolve a unique active student or guardian account without exposing whether it exists. */
    private function findPortalRecord(string $accountType, string $identifier, string $pin): Student|Guardian|null
    {
        $records = $accountType === 'student'
            ? Student::withoutGlobalScope('tenant')
                ->where('status', 'active')
                ->where(fn ($query) => $query->where('student_id', $identifier)->orWhere('email', $identifier))
                ->get()
            : Guardian::withoutGlobalScope('tenant')
                ->where(fn ($query) => $query->where('email', $identifier)->orWhere('phone', $identifier))
                ->get();

        $accountColumn = $accountType === 'student' ? 'student_id' : 'guardian_id';
        $matchingTokens = DB::table('portal_password_setup_tokens')
            ->where('pin_hash', $this->pinHash($pin))
            ->whereNull('used_at')
            ->where('pin_expires_at', '>', now())
            ->whereIn($accountColumn, $records->pluck('id'))
            ->get(['tenant_id', $accountColumn]);
        $matches = $records->filter(fn ($record) => $matchingTokens->contains(
            fn ($token) => (int) $token->tenant_id === (int) $record->tenant_id
                && (int) $token->{$accountColumn} === (int) $record->id,
        ));

        return $matches->count() === 1 ? $matches->first() : null;
    }

    /** Return the same response for unknown accounts and invalid or expired PINs. */
    private function invalidPinResponse(Request $request): RedirectResponse
    {
        return back()
            ->withErrors(['setup_pin' => 'The account details or PIN are invalid, expired, or already used. Ask your institution for a new PIN.'])
            ->onlyInput('account_type', 'identifier');
    }
}
