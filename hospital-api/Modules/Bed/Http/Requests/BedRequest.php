<?php

namespace Modules\Bed\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Add role check here if needed
    }

    public function rules(): array
    {

        $id = request()->id ?? null;

        return [
            'type' => [
                'required',
                'string',
                'max:255',
                Rule::unique('bed_type', 'type')->ignore($id),
            ],
            'description' => 'nullable|string|max:250',
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Bed type is required.',
            'type.unique' => 'This bed type already exists.',
            'type.max' => 'Type may not be greater than 255 characters.',
            'description.max' => 'Description may not be greater than 250 characters.',
        ];
    }
}
