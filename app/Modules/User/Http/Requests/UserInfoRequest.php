<?php
namespace App\Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UserInfoRequest extends FormRequest
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
		return [
			'mobile'=>'size:11|mobile_phone',
		];
	}

	/**
	 * @return array
	 */
	public function messages()
	{
		return [
			'mobile.size'=>'国内的手机号码长度为11位',
			'mobile.mobile_phone'=>'请输入一个手机号码'
		];
	}
}
