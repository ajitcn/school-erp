<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\StudentEnrollment;
use App\Models\Tenant;
use App\Services\StudentRegisterNumberService;
use App\Services\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RollNumberGroupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('student_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('branch_id');
            $table->unsignedBigInteger('academic_year_id');
            $table->unsignedBigInteger('school_class_id')->nullable();
            $table->unsignedBigInteger('section_id')->nullable();
            $table->unsignedBigInteger('course_id')->nullable();
            $table->unsignedBigInteger('semester_id')->nullable();
            $table->string('roll_number')->nullable();
            $table->timestamps();
        });
        $tenant = new Tenant();
        $tenant->id = 1;
        app(TenantContext::class)->set($tenant);
    }

    public function test_roll_numbers_are_scoped_to_academic_group(): void
    {
        $branch = new Branch();
        $branch->id = 1;
        $group = ['academic_year_id' => 1, 'school_class_id' => 1, 'section_id' => 1];
        StudentEnrollment::create(['branch_id' => 1, 'academic_year_id' => 1, 'school_class_id' => 1, 'section_id' => 1, 'roll_number' => '01']);
        $service = app(StudentRegisterNumberService::class);

        $this->assertSame('02', $service->nextRollNumber($branch, $group));
        $this->assertSame('1', $service->nextRollNumber($branch, array_replace($group, ['section_id' => 2])));
        $this->assertSame('1', $service->nextRollNumber($branch, array_replace($group, ['academic_year_id' => 2])));
        $service->assertRollAvailable($branch, array_replace($group, ['section_id' => 2]), '01');
        $this->expectException(ValidationException::class);
        $service->assertRollAvailable($branch, $group, '01');
    }

    public function test_college_courses_and_semesters_have_separate_sequences(): void
    {
        $branch = new Branch();
        $branch->id = 1;
        $group = ['academic_year_id' => 1, 'course_id' => 1, 'semester_id' => 1];
        StudentEnrollment::create(['branch_id' => 1, 'academic_year_id' => 1, 'course_id' => 1, 'semester_id' => 1, 'roll_number' => '1']);
        $service = app(StudentRegisterNumberService::class);

        $this->assertSame('2', $service->nextRollNumber($branch, $group));
        $this->assertSame('1', $service->nextRollNumber($branch, array_replace($group, ['course_id' => 2])));
        $this->assertSame('1', $service->nextRollNumber($branch, array_replace($group, ['semester_id' => 2])));
    }
}
