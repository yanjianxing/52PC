<?php
namespace App\Modules\Manage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
            'username' => 'required|string',
            'password' => 'required|between:3,16|string',
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
            'username.required' => '请输入账号',
            'usernmae.string' => '请输入正确的用账号格式',
            'password.required' => '请输入登录密码',
            'password.between' => '密码长度为:min - :max位'
        ];
    }
}
