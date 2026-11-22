<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        $create = function (string $name, Closure $definition): void {
            if (! Schema::hasTable($name)) {
                Schema::create($name, $definition);
            }
        };
        if (! Schema::hasColumn('users', 'branch_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained()->nullOnDelete();
                $table->boolean('is_active')->default(true)->after('password');
                $table->timestamp('last_login_at')->nullable();
            });
        }

        $create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->timestamps();
        });
        $create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('label');
            $table->timestamps();
        });
        $create('role_permission', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
        $create('user_role', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
        $create('user_branch_access', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'branch_id']);
        });
        $create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();
            $table->unique(['branch_id', 'key']);
        });
        $create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('auditable');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->timestamps();
            $table->index(['action', 'created_at']);
        });
        $create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('employee_number')->unique();
            $table->string('phone')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('section_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_class_teacher')->default(false);
            $table->timestamps();
            $table->index(['teacher_id', 'academic_year_id']);
        });
        $create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('admission_number');
            $table->date('admission_date');
            $table->string('status')->default('admitted');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'admission_number']);
        });
        $create('student_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event');
            $table->json('details')->nullable();
            $table->timestamps();
        });
        $create('attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->date('attendance_date');
            $table->string('session')->default('full_day');
            $table->enum('status', ['present', 'absent', 'late', 'half_day', 'leave']);
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->unique(['student_id', 'attendance_date', 'session']);
        });
        $create('attendance_corrections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attendance_id')->constrained('attendance')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('old_status');
            $table->string('new_status');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->timestamps();
        });
        $create('fee_structures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_class_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('semester_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $create('fee_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->nullable();
            $table->unsignedInteger('installment_number')->nullable();
            $table->timestamps();
        });
        $create('student_fee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_component_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('late_fee', 12, 2)->default(0);
            $table->string('status')->default('open');
            $table->timestamps();
            $table->unique(['student_id', 'fee_component_id']);
        });
        $create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('transaction_reference')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('payment_mode');
            $table->string('status')->default('completed');
            $table->string('notes')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();
        });
        $create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_fee_assignment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('allocation_type')->default('current_year');
            $table->timestamps();
        });
        $create('receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->string('status')->default('issued');
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });
        $create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason');
            $table->string('status')->default('processed');
            $table->timestamps();
        });
        $create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('voucher_number')->unique();
            $table->date('expense_date');
            $table->string('description');
            $table->string('vendor_name')->nullable();
            $table->string('payment_mode');
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');
            $table->string('bill_path')->nullable();
            $table->timestamps();
        });
        $create('expense_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
        $create('notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        $create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('event');
            $table->string('status')->default('queued');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
        $create('import_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entity');
            $table->string('file_name');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('success_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('errors')->nullable();
            $table->string('status')->default('uploaded');
            $table->timestamps();
        });
        $create('biometric_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('device_identifier')->unique();
            $table->string('type');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        $create('biometric_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biometric_device_id')->constrained()->cascadeOnDelete();
            $table->string('event_identifier');
            $table->string('person_identifier')->nullable();
            $table->timestamp('occurred_at');
            $table->string('status')->default('received');
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['biometric_device_id', 'event_identifier']);
        });
    }

    public function down(): void
    {
        foreach (['biometric_sync_logs', 'biometric_devices', 'import_histories', 'notification_logs', 'notices', 'expense_approvals', 'expenses', 'expense_categories', 'refunds', 'receipts', 'payment_allocations', 'payments', 'student_fee_assignments', 'fee_components', 'fee_structures', 'attendance_corrections', 'attendance', 'student_histories', 'admissions', 'teacher_assignments', 'teachers', 'audit_logs', 'settings', 'user_branch_access', 'user_role', 'role_permission', 'permissions', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropColumn(['branch_id', 'is_active', 'last_login_at']);
        });
    }
};
