<?php

namespace Modules\Bed\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BedMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'bed' => 'required|string|max:255',
            'bed_type_id' => 'required|exists:bed_type,id',
            'charges' => 'required|numeric|min:0',
            'capacity' => 'required|integer|min:1',
            'description' => 'nullable|string|max:250',
            'clinic_id' => 'required|exists:clinic,id',
        ];

        if (multiVendor()) {
            $rules['clinic_admin_id'] = 'required|exists:users,id';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'bed.required' => 'The bed name is required.',
            'bed_type_id.required' => 'Please select a bed type.',
            'bed_type_id.exists' => 'Selected bed type does not exist.',
            'charges.required' => 'Charges are required.',
            'capacity.required' => 'Capacity is required.',
            'clinic_admin_id.required' => 'clinic admin is required field',
            'clinic_id.required' => 'clinic is required field',
        ];
    }
}
