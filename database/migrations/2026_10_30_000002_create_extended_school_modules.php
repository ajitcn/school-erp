<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        // MySQL commits DDL statements as they run. Skip tables already created if a prior
        // attempt stopped partway through this migration.
        $createTable = static function (string $tableName, Closure $definition): void {
            if (! Schema::hasTable($tableName)) {
                Schema::create($tableName, $definition);
            }
        };

        $createTable('library_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('isbn')->nullable();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('category')->nullable();
            $table->unsignedInteger('copies_total')->default(1);
            $table->unsignedInteger('copies_available')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'title']);
        });
        $createTable('library_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('book_id')->constrained('library_books')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('issued_at');
            $table->date('due_at');
            $table->date('returned_at')->nullable();
            $table->decimal('fine_amount', 10, 2)->default(0);
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'returned_at']);
        });
        $createTable('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('vehicle_number')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
            $table->decimal('monthly_fee', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'name']);
        });
        $createTable('student_transport', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transport_route_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('pickup_point')->nullable();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['tenant_id', 'student_id', 'status']);
        });
        $createTable('hostel_rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('building')->nullable();
            $table->string('room_type')->nullable();
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->decimal('monthly_fee', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'name']);
        });
        $createTable('hostel_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
            $table->index(['tenant_id', 'hostel_room_id', 'status']);
            $table->index(['tenant_id', 'student_id', 'status']);
        });
        $createTable('scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('criteria')->nullable();
            $table->string('award_type')->default('fixed');
            $table->decimal('amount', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'is_active']);
        });
        $createTable('student_scholarships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scholarship_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->decimal('awarded_amount', 10, 2);
            $table->string('status')->default('approved');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(
                ['scholarship_id', 'student_id', 'academic_year_id'],
                'student_scholarship_year_unique'
            );
        });
        $createTable('examinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('exam_type')->default('term');
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->index(['tenant_id', 'branch_id', 'academic_year_id']);
        });
        $createTable('examination_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('examination_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->decimal('marks', 8, 2)->nullable();
            $table->decimal('maximum_marks', 8, 2)->default(100);
            $table->string('grade')->nullable();
            $table->text('remarks')->nullable();
            $table->string('status')->default('draft');
            $table->timestamps();
            $table->unique(
                ['examination_id', 'student_id', 'subject'],
                'examination_student_subject_unique'
            );
        });
        $createTable('api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->char('token_hash', 64)->unique();
            $table->json('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'user_id', 'revoked_at']);
        });
        $createTable('report_exports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_type');
            $table->string('format');
            $table->json('filters')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['report_exports', 'api_tokens', 'examination_marks', 'examinations', 'student_scholarships', 'scholarships', 'hostel_allocations', 'hostel_rooms', 'student_transport', 'transport_routes', 'library_loans', 'library_books'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
