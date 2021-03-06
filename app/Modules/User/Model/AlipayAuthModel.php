<?php

namespace App\Modules\User\Model;

use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class AlipayAuthModel extends Model
{
    //
    protected $table = 'alipay_auth';

    protected $fillable = [
        'uid', 'username', 'realname', 'alipay_account', 'pay_to_user_cash', 'user_get_cash', 'status', 'auth_time','alipay_name','is_del','reason'
    ];

    /**
     * 获得支付宝认证状态
     *
     * @param $id
     * @return mixed
     *
     */
    static function getAlipayAuthStatus($id)
    {
        $arrAuthStatus = [
            '0' => '待审核',
            '1' => '已打款待验证',
            '2' => '认证成功',
            '3' => '认证失败'
        ];

        $info = AlipayAuthModel::where('id', $id)->first();
        return $arrAuthStatus[$info['status']];
    }

    public $transactionData;

    /**
     * 新增支付宝认证
     *
     * @param $alipayAuthInfo
     * @param $authRecordInfo
     * @return bool
     */
    static function createAlipayAuth($alipayAuthInfo, $authRecordInfo)
    {
        return DB::transaction(function () use ($alipayAuthInfo, $authRecordInfo) {
            $authRecordInfo['auth_id'] = DB::table('alipay_auth')->insertGetId($alipayAuthInfo);
            DB::table('auth_record')->insert($authRecordInfo);
            return $authRecordInfo['auth_id'];
        });
    }

    /**
     * 停用启用支付宝绑定
     *
     * @param $id
     * @param $status
     * @return bool
     */
    static function changeAlipayAuth($id, $status)
    {
        $res = DB::transaction(function () use ($id, $status) {
            $user = Auth::User();
            AlipayAuthModel::where('id', $id)->where('uid', $user->id)->update(array('status' => $status));
            AuthRecordModel::where('auth_id', $id)->where('uid', $user->id)->where('auth_code', 'alipay')->update(array('status' => $status));
        });
        return is_null($res) ? true : $res;
    }


    /**
     * 后台审核通过支付宝认证
     *
     * @param $id
     * @return bool
     */
    static function alipayAuthPass($id)
    {
        $status = DB::transaction(function () use ($id) {
            AlipayAuthModel::where('id', $id)->update(array('status' => 2, 'auth_time' => date('Y-m-d H:i:s')));
            AuthRecordModel::where('auth_id', $id)
                ->where('auth_code', 'alipay')
                ->update(array('status' => 2, 'auth_time' => date('Y-m-d H:i:s')));
            // $info = AlipayAuthModel::find($id);
            // $userInfo = UserModel::where('id',$info->uid)->first();
            // $user = [
            //     'uid'    => $info->uid,
            //     'email'  => $userInfo->email,
            //     'mobile' => $userInfo->mobile
            // ];
            // $templateArr = [
            //     'username' => $userInfo->name
            // ];
            // \MessageTemplateClass::sendMessage('alipay_auth_pay',$user,$templateArr,$templateArr);
        });
        return is_null($status) ? true : $status;
    }

    /**
     * 后台审核失败支付宝认证
     *
     * @param $id
     * @return bool
     */
    static function alipayAuthDeny($id,$reason='')
    {
        $status = DB::transaction(function () use ($id,$reason) {
            AlipayAuthModel::where('id', $id)->update([
                'status' => 3,
                'reason' => $reason
            ]);
            AuthRecordModel::where('auth_id', $id)
                ->where('auth_code', 'alipay')
                ->update(array('status' => 3));
            $info = AlipayAuthModel::find($id);
            $userInfo = UserModel::where('id',$info->uid)->first();
            $user = [
                'uid'    => $info->uid,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username' => $userInfo->name
            ];
            \MessageTemplateClass::sendMessage('alipay_auth_failure',$user,$templateArr,$templateArr);
        });

        return is_null($status) ? true : $status;
    }

    /**
     * 后台打款预留
     *
     * @param $id
     */
    public function alipayAuthPay($id)
    {

    }

    /**
     * 后台删除支付宝认证
     *
     * @param $id
     * @return bool
     */
    public function alipayAuthDel($id)
    {
        $this->transactionData['id'] = $id;
        $status = DB::transaction(function () {
            AlipayAuthModel::where('id', $this->transactionData['id'])->delete();
            AuthRecordModel::where('auth_id', $this->transactionData['id'])
                ->where('auth_code', 'alipay')
                ->delete();
        });

        return is_null($status) ? true : $status;
    }
}
