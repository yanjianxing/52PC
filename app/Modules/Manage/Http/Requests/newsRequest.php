<?php
namespace App\Modules\Manage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class newsRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
				'title' => 'required|between:3,100',
			    'catID' => 'required',
				'author' => 'required',
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
				'title.required' => '请输入文章标题',
				'title.between' => '文章标题为:min - :max位',
				'catID.required' => '请选择资讯分类',
				'author.required' => '请输入作者',
		];
	}
}
