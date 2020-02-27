<?php
namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PayRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
			'cash' => 'required|numeric',
            'pay_type' => 'required'
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


    public function messages()
    {
        return [
            'cash.required' => '请输入充值金额',
            'cash.numeric' => '请输入正确的格式',

            'pay_type.required' => '请选择支付方式'
        ];
    }
}
