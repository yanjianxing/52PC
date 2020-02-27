<?php

namespace App\Modules\Vipshop\Models;

use App\Modules\Finance\Model\FinancialModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Database\Eloquent\Model;

class VipshopOrderModel extends Model
{
    protected $table = 'vipshop_order';

    protected $fillable = [
        'code', 'title', 'uid', 'package_id', 'shop_id', 'cash', 'time_period', 'status'
    ];

    /**
     * 第三方支付vipshop回调逻辑
     * @param $data
     * @param $payType
     * @return mixed
     */
    static public function payVipShop($data,$payType=1)
    {
        $status = DB::transaction(function () use ($data,$payType) {
            $orderInfo = VipshopOrderModel::where('code', $data['code'])->first();
            UserDetailModel::where('uid', $orderInfo['uid'])->decrement('balance', $orderInfo->cash);
            FinancialModel::create([
                'action' => 15,
                'pay_type' => $payType,
                'cash' => $orderInfo->cash,
                'uid' => $orderInfo['uid'],
                'pay_account' => $data['pay_account'],
                'pay_code' => $data['pay_code']
            ]);
            VipshopOrderModel::where('code', $orderInfo->code)->update(['status' => 1]);
            $arrPrivilegeId = PackagePrivilegesModel::where('package_id', $orderInfo->package_id)->get(['privileges_id'])
                ->map(function ($v, $k) {
                    return $v['privileges_id'];
                });
            $user = UserModel::find($orderInfo['uid']);
            ShopPackageModel::create([
                'shop_id' => $orderInfo->shop_id,
                'package_id' => $orderInfo->package_id,
                'privileges_package' => json_encode($arrPrivilegeId),
                'uid' => $orderInfo['uid'],
                'username' => $user->name,
                'duration' => $orderInfo->time_period,
                'price' => $orderInfo->cash,
                'start_time' => date('Y-m-d H:i:s', time()),
                'end_time' => date('Y-m-d H:i:s', strtotime('+' . $orderInfo->time_period . ' month')),
                'status' => 0
            ]);
        });
        return $status;
    }
}
