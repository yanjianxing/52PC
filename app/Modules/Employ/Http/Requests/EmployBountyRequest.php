<?php

namespace App\Modules\Employ\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployRequest extends FormRequest
{

    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $time = time();
        return [
            'password'=>'required',
        ];
    }

    public function messages()
    {
        return [
            'password.required' => '请输入密码',
        ];
    }
}
