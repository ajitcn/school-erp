<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Validate student payment collection fields and selected installment scope. */
class StorePaymentRequest extends FormRequest
{
    /** Collectors must have the payment permission in their active tenant context. */
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('payments.collect') ?? false;
    }

    /** Validate both the legacy single-installment and modal multi-item collection flows. */
    public function rules(): array
    {
        return [
            'student_id' => 'required|exists:students,id',
            'student_fee_assignment_id' => 'nullable|exists:student_fee_assignments,id',
            'student_fee_assignment_ids' => 'nullable|array|min:1',
            'student_fee_assignment_ids.*' => 'required|integer|distinct|exists:student_fee_assignments,id',
            'collect_selected_items' => 'nullable|boolean',
            'paid_amounts' => 'nullable|array',
            'paid_amounts.*' => 'nullable|numeric|decimal:0,2|min:0|max:9999999999.99',
            'amount' => 'nullable|numeric|decimal:0,2|min:0.01|max:9999999999.99',
            'payment_mode' => 'required_without:payment_modes|in:cash,upi,card,bank_transfer,online',
            'payment_modes' => 'nullable|array|min:1',
            'payment_modes.*' => 'required|distinct|in:cash,upi,card,bank_transfer,online',
            'method_amounts' => 'nullable|array',
            'method_amounts.*' => 'nullable|numeric|decimal:0,2|min:0|max:9999999999.99',
            'payment_reference' => 'nullable|string|max:100',
            'academic_year_id' => 'nullable|exists:academic_years,id',
        ];
    }
}
