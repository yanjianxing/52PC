<?php

namespace App\Modules\Shop\Models;

use App\Modules\Finance\Model\FinancialModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProgrammeRightsModel extends Model
{
    protected $table = 'programme_order_rights';
    //
    public $timestamps = false;
    protected $fillable = [
        'id',
        'programme_order_id',
        'sub_order_id',
        'role',
        'type',
        'status',
        'desc',
        'from_uid',
        'to_uid',
        'handle_uid',
        'handled_at',
        'created_at',
        'to_price',
        'from_price',
        'deal_name'
    ];

    /**
     * 根据维权id获取维权详情
     * @param int $id 维权id
     * @return mixed
     */
    static function rightsInfoById($id)
    {
        $rightsInfo = self::where('id',$id)->where('status','!=',-1)->first();
        if(!$rightsInfo){
            return false;
        }
        //查询维权人的信息
        $fromUser = self::getUserInfo($rightsInfo->from_uid);

        $rightsInfo['from_name'] = $fromUser['name'];
        $rightsInfo['from_email'] = $fromUser['email'];
        $rightsInfo['from_qq'] = $fromUser['qq'];
        $rightsInfo['from_mobile'] = $fromUser['mobile'];

        //查询被维权人的信息
        $toUser = self::getUserInfo($rightsInfo->to_uid);
        $rightsInfo['to_name'] = $toUser['name'];
        $rightsInfo['to_email'] = $toUser['email'];
        $rightsInfo['to_qq'] = $toUser['qq'];
        $rightsInfo['to_mobile'] = $toUser['mobile'];

        //查询订单信息
        $orderInfo = ProgrammeOrderModel::where('id',$rightsInfo->programme_order_id)->first();
        //查询实际支付的金额
        $financial=FinancialModel::where("action",2)->where("related_id",$rightsInfo->programme_order_id)->first();
        //$subOrderInfo = ProgrammeOrderSubModel::where('id',$rightsInfo->sub_order_id)->first();
        $rightsInfo['cash'] = 0.00;
        if($orderInfo){
            $rightsInfo['cash'] = $orderInfo->price + $orderInfo->freight;
        }
        if($financial){
            $rightsInfo['cash']=$financial['cash']-$financial['coupon'];
        }
        return $rightsInfo;
    }

    /**
     * 查询用户名称 邮箱 qq mobile
     * @param $uid
     * @return array
     */
    static function getUserInfo($uid)
    {
        $userInfo = array();
        //查询维权人的信息
        $toUser = UserModel::where('id',$uid)->select('id','name','email')->first();
        if(!empty($toUser)){
            $userInfo['name'] = $toUser->name;
            $userInfo['email'] = $toUser->email;
        }else{
            $userInfo['name'] = '';
            $userInfo['email'] = '';
        }
        $toUserDetail = UserDetailModel::where('uid',$uid)
            ->select('qq','qq_status','mobile','mobile_status')->first();
        if(!empty($toUserDetail)){
            if($toUserDetail->qq_status == 1){
                $userInfo['qq'] = $toUserDetail->qq;
            }else{
                $userInfo['qq'] = '';
            }
            if($toUserDetail->mobile_status == 1){
                $userInfo['mobile'] = $toUserDetail->mobile;
            }else{
                $userInfo['mobile'] = '';
            }
        }else{
            $userInfo['qq'] = '';
            $userInfo['mobile'] = '';
        }
        return $userInfo;
    }

    /**
     * 方案维权处理 (方案订单确认收货 卖家获得金额 维权时双方分配所得金额)
     * @param $id
     * @param $fromPrice
     * @param int $toPrice
     * @return bool
     */
    static function dealGoodsRights($id,$fromPrice,$toPrice=0,$dealName='')
    {
        $status = DB::transaction(function() use($id,$fromPrice,$toPrice,$dealName){
            $rightsInfo = self::where('id',$id)->first();
            if($fromPrice > 0){
                self::where('id', $id)->update(['status' => 2,'to_price' => $toPrice,'from_price' => $fromPrice,'deal_name' => $dealName]);
                //维权方获得金额
                UserDetailModel::where('uid', $rightsInfo['from_uid'])->increment('balance', $fromPrice);
                $userFromUid= UserDetailModel::where('uid', $rightsInfo['from_uid'])->pluck("balance");
                //产生一笔财务流水，接受任务产生收益
                $finance_data = [
                    'action' => 10, //维权退款
                    'pay_type' => 1,
                    'cash' => $fromPrice,
                    'uid' => $rightsInfo['from_uid'],
                    'created_at' => date('Y-m-d H:i:s', time()),
                    'remainder'=>$userFromUid,
                ];
                FinancialModel::create($finance_data);
            }
            if($toPrice){
                //被维权方获得金额
                UserDetailModel::where('uid', $rightsInfo['to_uid'])->increment('balance', $toPrice);
                $userToUid= UserDetailModel::where('uid', $rightsInfo['to_uid'])->pluck("balance");
                //产生一笔财务流水，接受任务产生收益
                $finance_data = [
                    'action' => 10,//维权退款
                    'pay_type' => 1,
                    'cash' => $toPrice,
                    'uid' => $rightsInfo['to_uid'],
                    'created_at' => date('Y-m-d H:i:s', time()),
                    'remainder'=>$userToUid,
                ];
                FinancialModel::create($finance_data);
            }
             //修改订单状态
            ProgrammeOrderModel::where('id',$rightsInfo->programme_order_id)
                ->update([
                    'status'=>7,
                    'end_at'=>date("Y-m-d H:i:s"),
                ]);
            //方案子订单状态变为维权结束
            //ProgrammeOrderSubModel::where('id',$rightsInfo->sub_order_id)->update(['status' => 7]);

            //查询主订单时候还有待维权 1：提交订单，2：付款，3：卖家发货，4：确定收货，5：交易成功
            $orderInfo = ProgrammeOrderModel::find($rightsInfo->programme_order_id);
            if(in_array($orderInfo->status ,[3,4])){
                $res = ProgrammeRightsModel::where('programme_order_id',$rightsInfo->programme_order_id)->where('sub_order_id','!=',$rightsInfo->sub_order_id)->where('status',1)->count();
                if($res == 0){
                    ProgrammeOrderModel::where('id',$rightsInfo->programme_order_id)->update(['status' => 5,'complete_at' => date('Y-m-d H:i:s')]);
                }
            }

        });
        return is_null($status)?true:false;
    }


}

