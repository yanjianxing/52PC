<?php
namespace App\Modules\Manage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class NavRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
				'title' => 'required|between:2,10|alpha_num',
			    'link_url' => 'required'
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
				'title.required' => '请输入标题',
				'title.between' => '标题为:min - :max位',
				'link_url.required' => '请输入链接'
	    ];
	}
}
