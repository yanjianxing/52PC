<?php

namespace App\Modules\Manage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrivilegesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
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
        return [
            'title' => 'required|string',
            'desc' => 'required|string',
            //'ico' => 'required',
            'list' => 'required'

        ];
    }

    public function messages()
    {
        return [
            'title.required' => '请输入标题',
            'title.string' => '请输入字符串',
            'desc.required' => '请输入描述',
            'desc.string' => '请输入字符串',
            //'ico.required' => '请上传图片',
            'list.required' => '请输入排序'
        ];
    }
}
