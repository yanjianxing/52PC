<?php
namespace App\Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AlipayAuthRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
            'alipayName' => 'required|string|between:2,39',
            'alipayAccount' => 'required|string',
            'confirmAlipayAccount' => 'required|same:alipayAccount',
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
            'alipayName.required' => '请输入支付宝姓名',
            'alipayAccount.required' => '请输入支付宝账户',
            'alipayAccount.string' => '请输入正确的支付宝账户格式',
            'confirmAlipayAccount.required' => '请确认支付宝账户',
            'confirmAlipayAccount.same' => '确认账户与支付宝账户不匹配'
        ];
    }
}
