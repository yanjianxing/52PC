<?php
namespace App\Modules\Finance\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CashoutRequest extends FormRequest
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
            'cashout_account' => 'required'
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
            'cash.required' => '请输入提现金额',
            'cash.numeric' => '请输入正确的格式',

            'cashout_account.required' => '请选择提现账户'
        ];
    }
}
