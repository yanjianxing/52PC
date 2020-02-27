<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/9/20
 * Time: 10:41
 */
namespace App\Modules\Shop\Models;

use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\EnterpriseAuthModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShopUpgradeModel extends Model
{
    protected $table = 'shop_upgrade';


    protected $fillable = [
        'id',
        'uid',
        'shop_id',
        'status',
        'check_time',
        'reason',
        'created_at',
        'updated_at'
    ];
     public  $timestamps=false;

    /**
     * 店铺升级通过
     * @param $id
     * @return bool
     */
    static function shopUpgradePass($id)
    {
        $info = self::find($id);
        if(!$info || $info->status != 0){
            return false;
        }
        $status = DB::transaction(function () use ($info) {
            self::where('id',$info->id)->update([
                'status'     => 1,
                'check_time' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $enterprise =  EnterpriseAuthModel::where('uid',$info->uid)
                ->orderBy('id','desc')->first();
            if($enterprise && $enterprise->status == 0){
                //审核通过最新的企业认证
                EnterpriseAuthModel::where('uid',$enterprise->id)->update([
                    'status'     => 1,
                    'auth_time'  => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                AuthRecordModel::where('uid',$info->uid)->where('auth_code','enterprise')->update([
                    'status'    => 1,
                    'auth_time' => date('Y-m-d H:i:s')
                ]);
            }

            //修改店铺类型
            ShopModel::where('id',$info->shop_id)->update([
                'type'       => 2,
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            $userInfo = UserModel::where('id',$info->uid)->first();
            $user = [
                'uid'    => $info->uid,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username' => $userInfo->name
            ];
            \MessageTemplateClass::sendMessage('shop_upgrade_success',$user,$templateArr,$templateArr);

        });
        return is_null($status) ? true : $status;

    }

    /**
     * 店铺升级不通过
     * @param $id
     * @return bool
     */
    static function shopUpgradeDeny($id,$reason='')
    {
        $info = self::find($id);
        if(!$info || $info->status != 0){
            return false;
        }
        $status = DB::transaction(function () use ($info,$reason) {
            self::where('id',$info->id)->update([
                'status'     => 2,
                'reason'     => $reason,
                'check_time' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $enterprise =  EnterpriseAuthModel::where('uid',$info->uid)
                ->orderBy('id','desc')->first();
            if($enterprise && $enterprise->status == 0){
                //审核失败最新的企业认证
                EnterpriseAuthModel::where('id',$enterprise->id)->update([
                    'status'     => 2,
                    'reason'     => $reason,
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                AuthRecordModel::where('uid',$info->uid)->where('auth_code','enterprise')->update([
                    'status'    => 2,
                    'auth_time' => date('Y-m-d H:i:s')
                ]);
            }
            $userInfo = UserModel::where('id',$info->uid)->first();
            $user = [
                'uid'    => $info->uid,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username' => $userInfo->name
            ];
            \MessageTemplateClass::sendMessage('shop_upgrade_failure',$user,$templateArr,$templateArr);


        });
        return is_null($status) ? true : $status;

    }


}