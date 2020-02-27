<?php

namespace App\Modules\Employ\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EvaluateRequest extends FormRequest
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
            'employ_id'=>'required',
            'type'=>'required',
            'comment'=>'required',
        ];
    }

    public function messages()
    {
        return [
            'employ_id.required'=>'请正确操作！',
            'type.required'=>'请选择好中差评！',
            'comment.required'=>'请填写评价类容'
        ];
    }
}
