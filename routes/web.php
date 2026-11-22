<?php

use App\Http\Controllers\AcademicRecordsController;
use App\Http\Controllers\AcademicStructureController;
use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\ApiTokenController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BiometricController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExaminationController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\FeeStructureController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\NotificationBroadcastController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationTemplateController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\PlatformSettingsController;
use App\Http\Controllers\PlatformWhatsAppCreditController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\PortalPasswordAuthController;
use App\Http\Controllers\PortalPasswordSetupController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SetupController;
use App\Http\Controllers\StudentCertificateController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\StudentFeeController;
use App\Http\Controllers\StudentServicesController;
use App\Http\Controllers\SubjectController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\TeachingController;
use App\Http\Controllers\TenantController;
use App\Http\Controllers\TenantWhatsAppCreditController;
use App\Http\Controllers\UserAdminController;
use App\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

// Public authentication and invitation routes.
Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return auth()->user()->is_platform_super_admin
        ? redirect()->route('platform.tenants')
        : redirect()->route('dashboard');
});
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/register', [TenantController::class, 'registration'])->name('register');
    Route::post('/register', [TenantController::class, 'register'])->middleware('throttle:registration')->name('register.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:password-reset')->name('password.email');
});
// Password links must also work when a student or parent is already signed in with OTP.
Route::get('/reset-password/{token}', [PasswordResetController::class, 'form'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
Route::get('/portal/login', [PortalPasswordAuthController::class, 'create'])->name('portal.password.login');
Route::post('/portal/login', [PortalPasswordAuthController::class, 'store'])->middleware('throttle:10,1')->name('portal.password.login.store');
Route::get('/portal/password/setup/{token}', [PortalPasswordSetupController::class, 'show'])->name('portal.password.setup');
Route::post('/portal/password/setup/{token}', [PortalPasswordSetupController::class, 'store'])->middleware('throttle:10,1')->name('portal.password.setup.store');
Route::get('/portal/password/setup-pin', [PortalPasswordSetupController::class, 'pinForm'])->name('portal.password.pin');
Route::post('/portal/password/setup-pin', [PortalPasswordSetupController::class, 'storePin'])->middleware('throttle:10,1')->name('portal.password.pin.store');
Route::middleware('auth')->group(function () {
    Route::get('/account/password', [AccountPasswordController::class, 'edit'])->name('account.password.edit');
    Route::put('/account/password', [AccountPasswordController::class, 'update'])->name('account.password.update');
});
Route::get('/tenant/invitations/{token}', [TenantController::class, 'showInvitation'])->name('tenant.invitation.show');
Route::post('/tenant/invitations/{token}/accept', [TenantController::class, 'accept'])->name('tenant.invitation.accept');

// Application routes require both an authenticated user and an active tenant membership.
Route::middleware(['auth', 'tenant'])->group(function () {
    Route::get('/whatsapp/credits', [TenantWhatsAppCreditController::class, 'index'])->name('whatsapp-credits.index');
    Route::post('/whatsapp/credits/packages/{pack}/checkout', [TenantWhatsAppCreditController::class, 'checkout'])->whereNumber('pack')->name('whatsapp-credits.checkout');
    Route::get('/whatsapp/credits/purchases/{purchase}/resume', [TenantWhatsAppCreditController::class, 'resume'])->whereNumber('purchase')->name('whatsapp-credits.resume');
    Route::post('/whatsapp/credits/checkout/verify', [TenantWhatsAppCreditController::class, 'verify'])->name('whatsapp-credits.verify');
    Route::get('/billing', [SubscriptionController::class, 'index'])->name('billing.index');
    Route::post('/billing/renewals', [SubscriptionController::class, 'requestRenewal'])->name('billing.renewals.store');
    Route::post('/billing/checkout', [SubscriptionController::class, 'checkout'])->name('billing.checkout');
    Route::get('/billing/checkout/{subscription}', [SubscriptionController::class, 'resumeCheckout'])->name('billing.checkout.resume');
    Route::post('/billing/checkout/verify', [SubscriptionController::class, 'verifyCheckout'])->name('billing.checkout.verify');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::post('/tenant/switch', [TenantController::class, 'switch'])->name('tenant.switch');
    Route::post('/tenant/invitations', [TenantController::class, 'invite'])->middleware('permission:granular')->name('tenant.invite');
    Route::get('/admin/invitations', [TenantController::class, 'invitations'])->middleware('permission:granular')->name('admin.invitations');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notificationLog}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/messages', [MessageController::class, 'index'])->name('messages.index');
    Route::post('/messages/{notice}/read', [MessageController::class, 'read'])->name('messages.read');
    Route::middleware('permission:granular')->group(function () {
        Route::get('/reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
        Route::get('/reconciliation/expected-cash', [ReconciliationController::class, 'expectedCash'])->name('reconciliation.expected-cash');
        Route::post('/reconciliation/closing', [ReconciliationController::class, 'close'])->name('reconciliation.close');
        Route::post('/reconciliation', [ReconciliationController::class, 'reconcile'])->name('reconciliation.store');
    });
    Route::middleware('permission:granular')->group(function () {
        Route::post('/workflows/attendance/{id}', [WorkflowController::class, 'approveAttendance'])->name('workflow.attendance');
        Route::post('/workflows/expenses/{id}', [WorkflowController::class, 'approveExpense'])->name('workflow.expense');
        Route::post('/workflows/refunds/{id}', [WorkflowController::class, 'approveRefund'])->name('workflow.refund');
        Route::post('/workflows/receipts/{id}', [WorkflowController::class, 'cancelReceipt'])->name('workflow.receipt');
        Route::post('/workflows/payments/{id}', [WorkflowController::class, 'correctPayment'])->name('workflow.payment');
    });
    Route::get('/approvals', [WorkflowController::class, 'index'])->middleware('permission:granular')->name('approvals.index');
    Route::middleware('permission:granular')->group(function () {
        Route::get('/imports/students', [ImportController::class, 'create'])->name('imports.students');
        Route::get('/imports/students/template', [ImportController::class, 'template'])->name('imports.template');
        Route::post('/imports/students/preview', [ImportController::class, 'preview'])->name('imports.preview');
        Route::post('/imports/students/confirm', [ImportController::class, 'confirm'])->name('imports.confirm');
        Route::post('/imports/{history}/rollback', [ImportController::class, 'rollback'])->name('imports.rollback');
    });
    Route::middleware('permission:granular')->group(function () {
        Route::get('/students/certificates', [StudentCertificateController::class, 'index'])->name('students.certificates.index');
        Route::post('/students/certificates', [StudentCertificateController::class, 'store'])->name('students.certificates.store');
        Route::get('/students/certificates/{certificate}', [StudentCertificateController::class, 'show'])
            ->whereNumber('certificate')
            ->name('students.certificates.show');
        Route::get('/fees/assignments', [StudentFeeController::class, 'index'])->name('fees.assignments.index');
        Route::get('/students/{student}/fees/setup-modal', [StudentFeeController::class, 'setupModal'])->name('students.fees.setup-modal');
        Route::get('/students/{student}/fees/create', [StudentFeeController::class, 'create'])->name('students.fees.create');
        Route::post('/students/{student}/fees', [StudentFeeController::class, 'store'])->name('students.fees.store');
        Route::put(
            '/students/{student}/fees/{assignment}',
            [StudentFeeController::class, 'updateAssignment'],
        )->name('students.fees.assignments.update');
        Route::delete(
            '/students/{student}/fees/structures/{feeStructure}/assignments',
            [StudentFeeController::class, 'destroyAssignments'],
        )->name('students.fees.structures.assignments.destroy');
        Route::get('/admin/users', [UserAdminController::class, 'index'])->name('admin.users.index');
        Route::get('/admin/users/create', [UserAdminController::class, 'create'])->name('admin.users.create');
        Route::post('/admin/users', [UserAdminController::class, 'store'])->name('admin.users.store');
        Route::get('/admin/users/{user}/edit', [UserAdminController::class, 'edit'])->name('admin.users.edit');
        Route::put('/admin/users/{user}', [UserAdminController::class, 'update'])->name('admin.users.update');
        Route::post('/admin/users/{user}/toggle', [UserAdminController::class, 'toggle'])->name('admin.users.toggle');
        Route::get('/admin/roles', [UserAdminController::class, 'roles'])->name('admin.roles.index');
        Route::post('/admin/roles', [UserAdminController::class, 'roleStore'])->name('admin.roles.store');
        Route::post('/admin/roles/{role}/customize', [UserAdminController::class, 'roleCustomize'])->name('admin.roles.customize');
        Route::put('/admin/roles/{role}', [UserAdminController::class, 'roleUpdate'])->name('admin.roles.update');
    });
    Route::middleware('permission:granular')->group(function () {
        Route::resource('students', StudentController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);
        Route::get('/students/{student}/photo', [StudentController::class, 'photo'])->name('students.photo');
        Route::post('/students/{student}/status', [StudentController::class, 'status'])->name('students.status');
        Route::post('/students/{student}/enrollments', [StudentController::class, 'enroll'])->name('students.enrollments.store');
        Route::get('/admissions', [AdmissionController::class, 'index'])->name('admissions.index');
        Route::put('/admissions/{admission}', [AdmissionController::class, 'update'])->name('admissions.update');
        Route::post('/admissions/{admission}/documents', [AdmissionController::class, 'upload'])->name('admissions.documents.store');
        Route::get('/admissions/{admission}/documents/{document}', [AdmissionController::class, 'download'])->name('admissions.documents.download');
    });
    Route::middleware('permission:users.manage')->group(function () {
        Route::post('/students/{student}/portal-password-setup-link', [PortalPasswordSetupController::class, 'issueStudent'])
            ->name('students.portal-password-link');
        Route::post('/students/{student}/guardians/{guardian}/portal-password-setup-link', [PortalPasswordSetupController::class, 'issueGuardian'])
            ->name('students.guardians.portal-password-link');
        Route::post('/students/{student}/portal-password-setup-pin', [PortalPasswordSetupController::class, 'issueStudentPin'])
            ->name('students.portal-password-pin');
        Route::post('/students/{student}/guardians/{guardian}/portal-password-setup-pin', [PortalPasswordSetupController::class, 'issueGuardianPin'])
            ->name('students.guardians.portal-password-pin');
    });
    Route::middleware('permission:granular')->group(function () {
        Route::get('/attendance', [AttendanceController::class, 'index'])->name('attendance.index');
        Route::get('/attendance/records', [AttendanceController::class, 'records'])->name('attendance.records');
        Route::get('/attendance/records/{date}/{branch}/{academicYear}', [AttendanceController::class, 'showRecords'])
            ->where([
                'date' => '\\d{4}-\\d{2}-\\d{2}(?:(?:%20| )\\d{2}:\\d{2}:\\d{2})?',
                'branch' => '\\d+',
                'academicYear' => '\\d+',
            ])
            ->name('attendance.records.show');
        Route::post('/attendance', [AttendanceController::class, 'store'])->name('attendance.store');
        Route::post('/attendance/{attendance}/corrections', [AttendanceController::class, 'requestCorrection'])->name('attendance.corrections.store');
    });
    Route::middleware('permission:granular')->group(function () {
        Route::get('/students/{student}/fees/collect-modal', [StudentFeeController::class, 'collectModal'])->name('students.fees.collect-modal');
        Route::get('/payments/students/search', [PaymentController::class, 'searchStudents'])->name('payments.students.search');
        Route::get('/payments/students/{student}/dues', [PaymentController::class, 'studentDues'])->name('payments.students.dues');
        Route::get('/payments/create', [PaymentController::class, 'create'])->name('payments.create');
        Route::post('/payments', [PaymentController::class, 'store'])->name('payments.store');
        Route::post('/payments/{payment}/correction', [PaymentController::class, 'requestCorrection'])->name('payments.corrections.store');
        Route::post('/payments/{payment}/refund', [PaymentController::class, 'requestRefund'])->name('payments.refunds.store');
        Route::post('/payments/{payment}/receipt-cancellation', [PaymentController::class, 'requestReceiptCancellation'])->name('payments.receipt-cancellations.store');
        Route::get('/payments/{payment}/history', [PaymentController::class, 'history'])->name('payments.history');
    });
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->middleware('permission:granular')->name('payments.receipt');
    Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:granular')->name('expenses.index');
    Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('permission:granular')->name('expenses.store');
    Route::get('/expenses/{expense}/edit', [ExpenseController::class, 'edit'])->middleware('permission:granular')->name('expenses.edit');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->middleware('permission:granular')->name('expenses.update');
    Route::post('/expenses/categories', [ExpenseController::class, 'storeCategory'])->middleware('permission:granular')->name('expenses.categories.store');
    Route::put('/expenses/categories/{category}', [ExpenseController::class, 'updateCategory'])->middleware('permission:granular')->name('expenses.categories.update');
    Route::post('/expenses/categories/{category}/toggle', [ExpenseController::class, 'toggleCategory'])->middleware('permission:granular')->name('expenses.categories.toggle');
    Route::delete('/expenses/categories/{category}', [ExpenseController::class, 'destroyCategory'])->middleware('permission:granular')->name('expenses.categories.destroy');
    Route::get('/expenses/{expense}/bill/preview', [ExpenseController::class, 'previewBill'])->middleware('permission:granular')->name('expenses.bill.preview');
    Route::get('/expenses/{expense}/bill', [ExpenseController::class, 'bill'])->middleware('permission:granular')->name('expenses.bill');
    Route::middleware('permission:granular')->group(function () {
        Route::get('/notices', [NoticeController::class, 'index'])->name('notices.index');
        Route::post('/notices', [NoticeController::class, 'store'])->name('notices.store');
        Route::post('/notices/{notice}/publish', [NoticeController::class, 'publish'])->name('notices.publish');
        Route::get('/admin/notifications/send', [NotificationBroadcastController::class, 'index'])->name('admin.notifications.send');
        Route::post('/admin/notifications/send', [NotificationBroadcastController::class, 'store'])->name('admin.notifications.send.store');
    });
    Route::middleware('permission:granular')->group(function () {
        Route::get('/setup', [SetupController::class, 'index'])->name('setup.index');
        Route::put('/setup/institution', [SetupController::class, 'institution'])->name('setup.institution');
        Route::post('/setup/branches', [SetupController::class, 'branch'])->name('setup.branches.store');
        Route::put('/setup/branches/{branch}', [SetupController::class, 'updateBranch'])->name('setup.branches.update');
        Route::delete('/setup/branches/{branch}', [SetupController::class, 'destroyBranch'])->name('setup.branches.destroy');
        Route::post('/setup/branches/{branch}/toggle', [SetupController::class, 'toggleBranch'])->name('setup.branches.toggle');
        Route::post('/setup/academic-years', [SetupController::class, 'year'])->name('setup.years.store');
        Route::put('/setup/academic-years/{academicYear}', [SetupController::class, 'updateAcademicYear'])->name('setup.years.update');
        Route::delete('/setup/academic-years/{academicYear}', [SetupController::class, 'destroyAcademicYear'])->name('setup.years.destroy');
        Route::post('/setup/academic-years/{academicYear}/current', [SetupController::class, 'currentAcademicYear'])->name('setup.years.current');
        Route::post('/setup/financial-years', [SetupController::class, 'financialYear'])->name('setup.financial-years.store');
        Route::put('/setup/financial-years/{financialYear}', [SetupController::class, 'updateFinancialYear'])->name('setup.financial-years.update');
        Route::delete('/setup/financial-years/{financialYear}', [SetupController::class, 'destroyFinancialYear'])->name('setup.financial-years.destroy');
        Route::post('/setup/financial-years/{financialYear}/current', [SetupController::class, 'currentFinancialYear'])->name('setup.financial-years.current');
        Route::get('/academic/teachers', [TeachingController::class, 'index'])->name('academic.teachers');
        Route::post('/academic/teachers', [TeachingController::class, 'storeTeacher'])->name('academic.teachers.store');
        Route::post('/academic/teacher-assignments', [TeachingController::class, 'storeAssignment'])->name('academic.teacher-assignments.store');
        Route::get('/academic/subjects', [SubjectController::class, 'index'])->name('academic.subjects');
        Route::post('/academic/subjects', [SubjectController::class, 'store'])->name('academic.subjects.store');
        Route::put('/academic/subjects/{subject}', [SubjectController::class, 'update'])->name('academic.subjects.update');
        Route::post('/academic/subjects/{subject}/toggle', [SubjectController::class, 'toggle'])->name('academic.subjects.toggle');
        Route::delete('/academic/subjects/{subject}', [SubjectController::class, 'destroy'])->name('academic.subjects.destroy');
        Route::get('/academic/results', [AcademicRecordsController::class, 'results'])->name('academic.results');
        Route::get('/academic/students/search', [AcademicRecordsController::class, 'searchStudentsForResults'])->name('academic.students.search');
        Route::post('/academic/results', [AcademicRecordsController::class, 'storeResult'])->name('academic.results.store');
        Route::get('/academic/results/import/template', [AcademicRecordsController::class, 'resultImportTemplate'])->name('academic.results.import.template');
        Route::post('/academic/results/import', [AcademicRecordsController::class, 'importResults'])->name('academic.results.import.store');
        Route::get('/academic/results/{student}/{academicYear}/details', [AcademicRecordsController::class, 'showResultGroup'])->name('academic.results.show');
        Route::get('/academic/results/{student}/{academicYear}/download', [AcademicRecordsController::class, 'downloadResultGroup'])->name('academic.results.download');
        Route::get('/academic/results/{studentId}/{academicYearId}/edit', [AcademicRecordsController::class, 'results'])->name('academic.results.edit');
        Route::put('/academic/results/{student}/{academicYear}', [AcademicRecordsController::class, 'updateResultGroup'])->name('academic.results.update');
        Route::delete('/academic/results/{student}/{academicYear}', [AcademicRecordsController::class, 'destroyResultGroup'])->name('academic.results.destroy');
        Route::get('/academic/examinations', [ExaminationController::class, 'index'])->name('academic.examinations');
        Route::post('/academic/examinations', [ExaminationController::class, 'store'])->name('academic.examinations.store');
        Route::put('/academic/examinations/{examination}', [ExaminationController::class, 'update'])->name('academic.examinations.update');
        Route::post('/academic/examinations/{examination}/publish', [ExaminationController::class, 'publish'])->name('academic.examinations.publish');
        Route::get('/academic/timetable', [AcademicRecordsController::class, 'timetable'])->name('academic.timetable');
        Route::post('/academic/timetable', [AcademicRecordsController::class, 'storeTimetable'])->name('academic.timetable.store');
        Route::put('/academic/timetable/{timetable}', [AcademicRecordsController::class, 'updateTimetable'])->name('academic.timetable.update');
        Route::get('/academic/{type}', [AcademicStructureController::class, 'index'])->name('academic.index');
        Route::post('/academic/{type}', [AcademicStructureController::class, 'store'])->name('academic.store');
        Route::get('/academic/{type}/{id}/edit', [AcademicStructureController::class, 'edit'])->name('academic.edit');
        Route::put('/academic/{type}/{id}', [AcademicStructureController::class, 'update'])->name('academic.update');
        Route::post('/academic/{type}/{id}/toggle', [AcademicStructureController::class, 'toggle'])->name('academic.toggle');
        Route::delete('/academic/{type}/{id}', [AcademicStructureController::class, 'destroy'])->name('academic.destroy');
        Route::put('/fees/assignments/{assignment}', [FeeStructureController::class, 'updateAssignment'])->name('fees.assignments.update');
        Route::get('/fees/structures', [FeeStructureController::class, 'index'])->name('fees.structures.index');
        Route::post('/fees/structures', [FeeStructureController::class, 'store'])->name('fees.structures.store');
        Route::get('/fees/structures/{feeStructure}/edit', [FeeStructureController::class, 'edit'])->name('fees.structures.edit');
        Route::put('/fees/structures/{feeStructure}', [FeeStructureController::class, 'update'])->name('fees.structures.update');
        Route::post('/fees/structures/{feeStructure}/deactivate', [FeeStructureController::class, 'deactivate'])->name('fees.structures.deactivate');
    });
    Route::middleware('permission:granular')->group(function () {
        Route::get('/services', [StudentServicesController::class, 'index'])->name('services.index');
        Route::post('/services/library/books', [StudentServicesController::class, 'storeBook'])->name('services.library.books.store');
        Route::put('/services/library/books/{book}', [StudentServicesController::class, 'updateBook'])->name('services.library.books.update');
        Route::post('/services/library/books/{book}/toggle', [StudentServicesController::class, 'toggleBook'])->name('services.library.books.toggle');
        Route::post('/services/library/loans', [StudentServicesController::class, 'loanBook'])->name('services.library.loans.store');
        Route::post('/services/library/loans/{loan}/return', [StudentServicesController::class, 'returnBook'])->name('services.library.loans.return');
        Route::post('/services/transport/routes', [StudentServicesController::class, 'storeTransportRoute'])->name('services.transport.routes.store');
        Route::put('/services/transport/routes/{route}', [StudentServicesController::class, 'updateTransportRoute'])->name('services.transport.routes.update');
        Route::post('/services/transport/routes/{route}/toggle', [StudentServicesController::class, 'toggleTransportRoute'])->name('services.transport.routes.toggle');
        Route::post('/services/transport/assignments', [StudentServicesController::class, 'assignTransport'])->name('services.transport.assignments.store');
        Route::post('/services/transport/assignments/{assignment}/end', [StudentServicesController::class, 'endTransport'])->name('services.transport.assignments.end');
        Route::post('/services/hostel/rooms', [StudentServicesController::class, 'storeRoom'])->name('services.hostel.rooms.store');
        Route::put('/services/hostel/rooms/{room}', [StudentServicesController::class, 'updateRoom'])->name('services.hostel.rooms.update');
        Route::post('/services/hostel/rooms/{room}/toggle', [StudentServicesController::class, 'toggleRoom'])->name('services.hostel.rooms.toggle');
        Route::post('/services/hostel/allocations', [StudentServicesController::class, 'allocateRoom'])->name('services.hostel.allocations.store');
        Route::post('/services/hostel/allocations/{allocation}/end', [StudentServicesController::class, 'endHostel'])->name('services.hostel.allocations.end');
        Route::post('/services/scholarships', [StudentServicesController::class, 'storeScholarship'])->name('services.scholarships.store');
        Route::put('/services/scholarships/{scholarship}', [StudentServicesController::class, 'updateScholarship'])->name('services.scholarships.update');
        Route::post('/services/scholarships/{scholarship}/toggle', [StudentServicesController::class, 'toggleScholarship'])->name('services.scholarships.toggle');
        Route::post('/services/scholarships/awards', [StudentServicesController::class, 'awardScholarship'])->name('services.scholarships.award');
        Route::get('/admin/api-tokens', [ApiTokenController::class, 'index'])->name('admin.api-tokens');
        Route::post('/admin/api-tokens', [ApiTokenController::class, 'store'])->name('admin.api-tokens.store');
        Route::post('/admin/api-tokens/{token}/revoke', [ApiTokenController::class, 'revoke'])->name('admin.api-tokens.revoke');
        Route::get('/admin/notification-templates', [NotificationTemplateController::class, 'index'])->name('admin.notification-templates');
        Route::post('/admin/notification-templates', [NotificationTemplateController::class, 'save'])->name('admin.notification-templates.save');
        Route::delete('/admin/notification-templates/{event}', [NotificationTemplateController::class, 'delete'])->name('admin.notification-templates.delete');
        Route::get('/admin/biometric-devices', [BiometricController::class, 'index'])->name('admin.biometric-devices');
        Route::post('/admin/biometric-devices', [BiometricController::class, 'store'])->name('admin.biometric-devices.store');
        Route::post('/admin/biometric-devices/{device}/deactivate', [BiometricController::class, 'deactivate'])->name('admin.biometric-devices.deactivate');
        Route::post('/admin/biometric-mappings', [BiometricController::class, 'mapping'])->name('admin.biometric-mappings.store');
    });
    Route::middleware('permission:granular')->group(function () {
        Route::get('/reports/financial', [ReportController::class, 'financial'])->name('reports.financial');
        Route::get('/reports/expenses', [ReportController::class, 'expenses'])->name('reports.expenses');
        Route::get('/reports/fees', [ReportController::class, 'fees'])->name('reports.fees');
        Route::get('/reports/previous-year-dues', [ReportController::class, 'previousYearDues'])->name('reports.previous-dues');
        Route::get('/reports/attendance', [ReportController::class, 'attendance'])->name('reports.attendance');
        Route::get('/reports/{type}/export', [ReportController::class, 'export'])->name('reports.export');
    });
});
Route::middleware('portal.access')->group(function () {
    Route::get('/portal', [PortalController::class, 'dashboard'])->name('portal.dashboard');
    Route::post('/portal/children/select', [PortalController::class, 'selectChild'])->name('portal.children.select');
    Route::get('/portal/students/{student}', [PortalController::class, 'student'])->name('portal.student');
    Route::get('/portal/pages/{page}', [PortalController::class, 'page'])
        ->whereIn('page', ['profile', 'subjects', 'examinations', 'results', 'timetable', 'fees', 'payments', 'notices'])
        ->name('portal.page');
    Route::get('/portal/attendance/{student}', [PortalController::class, 'attendance'])->name('portal.attendance');
    Route::get('/portal/payments/{payment}/receipt', [PortalController::class, 'receipt'])->name('portal.receipt');
    Route::post('/portal/logout', [PortalPasswordAuthController::class, 'destroy'])->name('portal.logout');
});
// Platform controls are separate from tenant-scoped routes and require platform authorization.
Route::middleware(['auth', 'platform'])->prefix('platform')->name('platform.')->group(function () {
    Route::get('/guide', [PlatformController::class, 'guide'])->name('guide');
    Route::get('/tenants', [PlatformController::class, 'tenants'])->name('tenants');
    Route::get('/tenants/{tenant}', [PlatformController::class, 'showTenant'])->name('tenants.show');
    Route::get('/subscriptions', [PlatformController::class, 'subscriptions'])->name('subscriptions');
    Route::get('/plans', [PlatformController::class, 'plans'])->name('plans');
    Route::get('/usage', [PlatformController::class, 'usage'])->name('usage');
    Route::get('/audit-log', [PlatformController::class, 'auditLog'])->name('audit');
    Route::get('/settings', [PlatformSettingsController::class, 'index'])->name('settings');
    Route::put('/settings', [PlatformSettingsController::class, 'update'])->name('settings.update');
    Route::post('/settings/test-email', [PlatformSettingsController::class, 'testEmail'])->name('settings.test-email');
    Route::get('/whatsapp-credits', [PlatformWhatsAppCreditController::class, 'index'])->name('whatsapp-credits');
    Route::get('/whatsapp-credits/tenants', [PlatformWhatsAppCreditController::class, 'tenants'])->name('whatsapp-credits.tenants');
    Route::get('/whatsapp-credits/activity', [PlatformWhatsAppCreditController::class, 'activity'])->name('whatsapp-credits.activity');
    Route::post('/whatsapp-credits/packages', [PlatformWhatsAppCreditController::class, 'store'])->name('whatsapp-credits.packages.store');
    Route::put('/whatsapp-credits/packages/{pack}', [PlatformWhatsAppCreditController::class, 'update'])->whereNumber('pack')->name('whatsapp-credits.packages.update');
    Route::post('/whatsapp-credits/packages/{pack}/toggle', [PlatformWhatsAppCreditController::class, 'toggle'])->whereNumber('pack')->name('whatsapp-credits.packages.toggle');
    Route::post('/tenants/{tenant}/registration-review', [PlatformController::class, 'reviewRegistration'])->name('tenants.registration-review');
    Route::post('/tenants/{tenant}/status', [PlatformController::class, 'status'])->name('tenants.status');
    Route::post('/tenants/{tenant}/subscriptions/{subscription}/review', [PlatformController::class, 'reviewSubscription'])->name('subscriptions.review');
    Route::post('/plans', [PlatformController::class, 'plan'])->name('plans.store');
    Route::put('/plans/{plan}', [PlatformController::class, 'updatePlan'])->name('plans.update');
});
