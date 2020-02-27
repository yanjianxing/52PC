<?php

namespace App\Modules\User\Model;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Auth;

class BankAuthModel extends Model
{
    //
    protected $table = 'bank_auth';

    protected $fillable = [
        'uid', 'username', 'realname', 'bank_name', 'bank_account', 'deposit_area', 'deposit_name', 'pay_to_user_cash',
        'user_get_cash', 'status', 'auth_time','is_del','reason'
    ];

    /**
     * 获取银行认证状态
     *
     * @param $id
     * @return mixed
     */
    static function getBankAuthStatus($id)
    {
        $arrAuthStatus = [
            '0' => '待审核',
            '1' => '已打款待验证',
            '2' => '认证成功',
            '3' => '认证失败'
        ];

        $info = BankAuthModel::where('id', $id)->first();
        return $arrAuthStatus[$info['status']];
    }

    /**
     * 通过id查找银行卡绑定信息
     * @param $id
     */
    static function findByUid($id)
    {
        $query = Self::where('uid','=',$id);
        $data = $query->where(function($query){
            $query->where('status','=',4);
        })->get();

        return $data;
    }


    public $transactionData;

    /**
     * 新增银行认证
     *
     * @param $bankAuthInfo
     * @param $authRecordInfo
     * @return bool
     */
    static function createBankAuth($bankAuthInfo, $authRecordInfo)
    {

        return DB::transaction(function () use ($bankAuthInfo, $authRecordInfo) {
            $authRecordInfo['auth_id'] = DB::table('bank_auth')->insertGetId($bankAuthInfo);
            DB::table('auth_record')->insert($authRecordInfo);
            return $authRecordInfo['auth_id'];
        });
    }

    /**
     * 用户停用启用银行绑定
     *
     * @param $id
     * @param $status
     * @return bool
     */
    static function changeBankAuth($id, $status)
    {
        $res = DB::transaction(function () use ($id, $status) {
            $user = Auth::User();
            BankAuthModel::where('id', $id)->where('uid', $user->id)->update(array('status' => $status));
            AuthRecordModel::where('auth_id', $id)->where('uid', $user->id)->where('auth_code', 'bank')->update(array('status' => $status));
        });
        return is_null($res) ? true : $res;
    }

    /**
     * 后台审核通过银行认证
     *
     * @param $id
     * @return bool
     */
    static function bankAuthPass($id)
    {
        $status = DB::transaction(function () use ($id) {
            BankAuthModel::where('id', $id)->update(array('status' => 2, 'auth_time' => date('Y-m-d H:i:s')));
            AuthRecordModel::where('auth_id', $id)
                ->where('auth_code', 'bank')
                ->update(array('status' => 2, 'auth_time' => date('Y-m-d H:i:s')));
            $info = BankAuthModel::find($id);
            $userInfo = UserModel::where('id',$info->uid)->first();
            $user = [
                'uid'    => $info->uid,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username' => $userInfo->name
            ];
            \MessageTemplateClass::sendMessage('bank_auth_pay',$user,$templateArr,$templateArr);
        });

        return is_null($status) ? true : $status;
    }

    /**
     * 后台审核失败银行认证
     *
     * @param $id
     * @return bool
     */
    static function bankAuthDeny($id,$reason='')
    {
        $status = DB::transaction(function () use ($id,$reason) {
            BankAuthModel::where('id', $id)->update([
                'status' => 3,
                'reason' => $reason
            ]);
            AuthRecordModel::where('auth_id', $id)
                ->where('auth_code', 'bank')
                ->update(array('status' => 3));
            $info = BankAuthModel::find($id);
            $userInfo = UserModel::where('id',$info->uid)->first();
            $user = [
                'uid'    => $info->uid,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username' => $userInfo->name
            ];
            \MessageTemplateClass::sendMessage('bank_auth_failure',$user,$templateArr,$templateArr);
        });

        return is_null($status) ? true : $status;
    }


    /**
     * 后台删除支付宝认证
     *
     * @param $id
     * @return bool
     */
    public function bankAuthDel($id)
    {
        $this->transactionData['id'] = $id;
        $status = DB::transaction(function () {
            BankAuthModel::where('id', $this->transactionData['id'])->delete();
            AuthRecordModel::where('auth_id', $this->transactionData['id'])
                ->where('auth_code', 'bank')
                ->delete();
        });

        return is_null($status) ? true : $status;
    }

    /**
     * 通过卡号获取银行名称
     *
     * @param $account
     * @return mixed
     */
    static function getBankname($account)
    {
        $info = BankAuthModel::select('bank_name')->where('bank_account', $account)->first();
        if (!empty($info)){
            return $info->bank_name;
        }

    }

}
