<?php
namespace App\Modules\User\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ResetRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
        return [
            'password' => 'required|between:6,15|alpha_num',
            'confirmPassword' => 'required|same:password',
//            'code' => 'required|alpha_num'
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
            'password.required' => '请输入注册密码',
            'password.between' => '密码长度在:min - :max 个字符',
            'password.alpha_num' => '密码仅允许字母和数字',

            'confirmPassword.required' => '请输入确认密码',
            'confirmPassword.same' => '确认密码与密码不一致',

//            'code.required' => '请输入验证码'
        ];
    }
}
