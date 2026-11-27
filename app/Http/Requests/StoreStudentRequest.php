<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    /** A signed-in user needs student-management permission to admit students. */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('students.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'branch_id' => 'required|exists:branches,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'school_class_id' => 'nullable|exists:school_classes,id',
            'section_id' => 'nullable|exists:sections,id',
            'course_id' => 'nullable|exists:courses,id',
            'semester_id' => 'nullable|exists:semesters,id',
            'guardian_id' => 'nullable|exists:guardians,id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string',
            'roll_number' => [
                'nullable',
                'string',
                'max:50',
            ],
            'gr_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('students', 'gr_number')->where('branch_id', $this->input('branch_id')),
            ],
            'place_of_birth' => 'nullable|string|max:150',
            'state' => 'nullable|string|max:100',
            'nationality' => 'nullable|string|max:100',
            'mother_tongue' => 'nullable|string|max:100',
            'religion' => 'nullable|in:Hindu,Muslim,Christian,Sikh,Buddhist,Jain,Parsi,Other',
            'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
            'aadhaar_number' => 'nullable|digits:12',
            'caste' => 'nullable|string|max:100',
            'category' => 'nullable|in:General,OBC,SC,ST,EWS,Other',
            'father_name' => 'nullable|string|max:150',
            'father_occupation' => 'nullable|string|max:150',
            'father_phone' => 'nullable|string|max:30',
            'father_email' => 'nullable|email|max:150',
            'mother_name' => 'nullable|string|max:150',
            'mother_occupation' => 'nullable|string|max:150',
            'mother_phone' => 'nullable|string|max:30',
            'mother_email' => 'nullable|email|max:150',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ];
    }
}
