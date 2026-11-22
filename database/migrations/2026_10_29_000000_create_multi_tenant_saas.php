<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('monthly_price', 12, 2)->default(0);
            $table->decimal('yearly_price', 12, 2)->default(0);
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_students')->nullable();
            $table->json('enabled_modules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('institution_name');
            $table->enum('institution_type', ['school', 'college']);
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('logo')->nullable();
            $table->enum('status', ['trial', 'active', 'suspended', 'cancelled'])->default('trial');
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->json('subscription_limits')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('status', ['trial', 'active', 'expired', 'suspended', 'cancelled'])->default('trial');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('renewal_date')->nullable();
            $table->string('payment_reference')->nullable();
            $table->string('payment_status')->default('pending');
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
        });

        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['owner', 'administrator', 'staff', 'teacher', 'fee_staff', 'student', 'parent'])->default('staff');
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'user_id']);
        });

        Schema::create('tenant_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->string('email');
            $table->string('role');
            $table->string('token', 80)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'email']);
        });

        Schema::create('tenant_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->text('value')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->timestamps();
            $table->unique(['tenant_id', 'key']);
        });

        if (! Schema::hasColumn('users', 'is_platform_super_admin')) {
            Schema::table('users', fn (Blueprint $table) => $table->boolean('is_platform_super_admin')->default(false)->after('id'));
        }

        $tenantTables = [
            'branches', 'academic_years', 'financial_years', 'school_classes', 'sections', 'departments', 'courses',
            'semesters', 'guardians', 'students', 'student_enrollments', 'teachers', 'admissions', 'attendance',
            'fee_structures', 'fee_components', 'student_fee_assignments', 'payments', 'payment_allocations',
            'receipts', 'refunds', 'expenses', 'expense_categories', 'expense_approvals', 'notices',
            'notification_logs', 'import_histories', 'audit_logs', 'biometric_devices', 'biometric_sync_logs',
            'settings', 'results', 'timetables', 'opening_balances', 'payment_corrections', 'refund_requests',
            'receipt_cancellations', 'cashier_closings', 'payment_reconciliations', 'student_histories',
            'teacher_assignments', 'attendance_corrections', 'collection_counters',
        ];

        foreach ($tenantTables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'tenant_id')) {
                Schema::table($tableName, fn (Blueprint $table) => $table->unsignedBigInteger('tenant_id')->nullable()->index());
            }
        }

        $owner = DB::table('users')->orderBy('id')->first();
        $defaultTenant = DB::table('tenants')->where('email', 'main@school.test')->first();
        if (! $defaultTenant) {
            $now = now();
            $planId = DB::table('subscription_plans')->insertGetId([
                'name' => 'Trial', 'monthly_price' => 0, 'yearly_price' => 0,
                'max_branches' => 10, 'max_users' => 50, 'max_students' => 500,
                'enabled_modules' => json_encode(['*']), 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $tenantId = DB::table('tenants')->insertGetId([
                'institution_name' => 'Main School/College', 'institution_type' => 'school',
                'email' => 'main@school.test', 'status' => 'trial', 'trial_started_at' => $now,
                'trial_ends_at' => $now->copy()->addDays(14), 'subscription_plan_id' => $planId,
                'subscription_limits' => json_encode(['max_branches' => 10, 'max_users' => 50, 'max_students' => 500]),
                'created_by' => $owner?->id, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('subscriptions')->insert([
                'tenant_id' => $tenantId, 'subscription_plan_id' => $planId, 'status' => 'trial',
                'start_date' => $now->toDateString(), 'end_date' => $now->copy()->addDays(14)->toDateString(),
                'payment_status' => 'not_required', 'created_at' => $now, 'updated_at' => $now,
            ]);
        } else {
            $tenantId = $defaultTenant->id;
        }

        foreach ($tenantTables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'tenant_id')) {
                continue;
            }
            foreach (['branch_id' => 'branches', 'student_id' => 'students', 'payment_id' => 'payments'] as $foreign => $parent) {
                if (! Schema::hasColumn($tableName, $foreign) || ! Schema::hasColumn($parent, 'tenant_id')) {
                    continue;
                }
                foreach (DB::table($tableName)->whereNull('tenant_id')->whereNotNull($foreign)->get(['id', $foreign]) as $row) {
                    $parentTenant = DB::table($parent)->where('id', $row->{$foreign})->value('tenant_id');
                    if ($parentTenant) {
                        DB::table($tableName)->where('id', $row->id)->update(['tenant_id' => $parentTenant]);
                    }
                }
            }
            if (Schema::hasColumn($tableName, 'tenant_id')) {
                DB::table($tableName)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            }
        }

        DB::table('users')->whereNull('is_platform_super_admin')->update(['is_platform_super_admin' => false]);
        if ($owner) {
            DB::table('tenant_user')->updateOrInsert(['tenant_id' => $tenantId, 'user_id' => $owner->id], [
                'role' => 'owner', 'is_active' => true, 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('users')->where('id', $owner->id)->update(['is_platform_super_admin' => true]);
        }
        DB::table('branches')->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
        DB::table('tenants')->where('id', $tenantId)->update(['created_by' => $owner?->id]);
    }

    public function down(): void
    {
        foreach (['tenant_settings', 'tenant_invitations', 'tenant_user', 'subscriptions', 'tenants', 'subscription_plans'] as $table) {
            Schema::dropIfExists($table);
        }
        if (Schema::hasColumn('users', 'is_platform_super_admin')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_platform_super_admin'));
        }
    }
};
