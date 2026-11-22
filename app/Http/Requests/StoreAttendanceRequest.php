<?php

namespace App\Http\Requests;

use App\Services\TenantContext;
use Illuminate\Foundation\Http\FormRequest;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('attendance.mark') ?? false;
    }

    public function rules(): array
    {
        $institutionType = app(TenantContext::class)->get()?->institution_type ?? 'school';

        return [
            'attendance_date' => 'required|date|date_equals:today',
            'branch_id' => 'required|integer',
            'academic_year_id' => 'required|integer',
            'school_class_id' => $institutionType === 'college' ? 'nullable|integer' : 'required|integer',
            'section_id' => $institutionType === 'college' ? 'nullable|integer' : 'required|integer',
            'course_id' => $institutionType === 'college' ? 'required|integer' : 'nullable|integer',
            'semester_id' => $institutionType === 'college' ? 'required|integer' : 'nullable|integer',
            'records' => 'required|array|min:1',
            'records.*.student_id' => 'required|integer|distinct',
            'records.*.status' => 'required|in:present,absent,late,half_day,leave',
            'records.*.remarks' => 'nullable|string|max:500',
        ];
    }
}
