<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /** Create a tenant-scoped archive of issued student certificates. */
    public function up(): void
    {
        Schema::create('student_certificates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('certificate_type', 32);
            $table->string('certificate_number', 64)->nullable();
            $table->date('issued_on');
            $table->date('effective_date')->nullable();
            $table->string('purpose', 255)->nullable();
            $table->text('conduct')->nullable();
            $table->text('remarks')->nullable();
            $table->json('student_snapshot');
            $table->timestamps();

            $table->unique(['tenant_id', 'certificate_number'], 'stud_cert_tenant_number_uq');
            $table->index(['tenant_id', 'student_id', 'issued_on'], 'stud_cert_tenant_student_date_idx');
            $table->index(['tenant_id', 'certificate_type'], 'stud_cert_tenant_type_idx');
        });
    }

    /** Remove the certificate archive table. */
    public function down(): void
    {
        Schema::dropIfExists('student_certificates');
    }
};
