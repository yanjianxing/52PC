<?php
namespace App\Modules\Manage\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
	/**
	 * Get the validation rules that apply to the request.
	 *
	 * @return array
	 */
	public function rules()
	{
		return [
				'catName' => 'required|between:2,10|alpha_num',
			    'displayOrder' => 'required'
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
				'catName.required' => '请输入分类名称',
				'catName.between' => '分类名称为:min - :max位',
				'displayOrder.required' => '请输入排序'
	    ];
	}
}
