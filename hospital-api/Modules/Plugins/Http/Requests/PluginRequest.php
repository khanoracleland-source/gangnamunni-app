<?php

namespace Modules\Plugins\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PluginRequest extends FormRequest
{
    public function rules()
    {

        return [
            'plugin_name' => 'required|string|min:2',
            // 'version' => 'required',
            'description' => 'required|min:2',
            'file_name' => [
                'required',
                'file',
                'mimes:zip'
            ],
        ];
    }


    public function messages()
    {
        return [
            'coupon_code.required' => 'Name is required.',
            'type.required' => 'Type is required.',
            'value.required' => 'Value must be numeric.',
            'coupon_usage.numeric' => 'Value must be numeric.',
            'file_name.mimes' => 'The file you are trying to upload is not correct. Please upload a .zip file'
        ];
    }
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }
}
