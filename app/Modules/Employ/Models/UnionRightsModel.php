<?php

namespace App\Modules\Employ\Models;

use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Order\Model\ShopOrderModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class UnionRightsModel extends Model
{
    protected $table = 'union_rights';
    public $timestamps = false;
    protected $fillable = [
        'type','object_id','object_type','desc','status','from_uid','to_uid','handel_uid','created_at','handled_at','is_delete'
    ];

    /**
     * 雇佣维权创建
     * @param $data
     * @param $role
     * @return bool
     */
    static function employRights($data,$role)
    {
        $status = DB::transaction(function() use($data,$role){
            //创建一个维权记录
            self::create($data);
            //将雇佣任务的状态更改成维权状态
            switch($role)
            {
                case 1:
                    $status = 7;
                    break;
                case 2:
                    $status = 8;
                    break;
                default:
                    $status = 7;
                    break;
            }
            EmployModel::where('id',$data['object_id'])->update(['status'=>$status]);
        });
        return is_null($status)?true:false;
    }

    /**
     * 商品购买维权
     * @param $data 维权信息
     * @param $orderId 商品订单id
     * @return bool
     */
    static function buyGoodsRights($data,$orderId)
    {
        $status = DB::transaction(function() use($data,$orderId){
            //创建一个维权记录
            self::create($data);
            //将购买商品订单状态改为维权中
            ShopOrderModel::where('id',$orderId)->update(['status'=>3]);
        });
        return is_null($status)?true:false;
    }


    /**
     * 根据维权id获取维权详情
     * @param $id 维权id
     * @return mixed
     */
    static function rightsInfoById($id)
    {
        $rightsInfo = UnionRightsModel::where('id',$id)->where('is_delete',0)->first();
        //查询维权人的信息
        $fromUser = self::getUserInfo($rightsInfo->from_uid);

        $rightsInfo['from_name'] = $fromUser['name'];
        $rightsInfo['from_email'] = $fromUser['email'];
        $rightsInfo['from_qq'] = $fromUser['qq'];
        $rightsInfo['from_mobile'] = $fromUser['mobile'];
        if(!empty($rightsInfo)){
            //判断维权对象类型
            if(!empty($rightsInfo->object_type)){
                switch($rightsInfo->object_type){
                    case 1:
                        //查询
                        $employ = EmployModel::where('id',$rightsInfo->object_id)->first();
                        $toUser = UserModel::where('id',$rightsInfo['to_uid'])->first();
                        $rightsInfo['to_name'] = $toUser['name'];
                        $rightsInfo['employ_cash'] = $employ['bounty'];
                        break;
                    case 2://购买商品
                        //查询订单信息
                        $orderInfo = ShopOrderModel::where('id',$rightsInfo->object_id)->first();
                        if($orderInfo){
                            $rightsInfo['title'] = $orderInfo->title;
                            $rightsInfo['cash'] = $orderInfo->cash;
                            //查询商品信息
                            $goodsInfo = GoodsModel::where('id',$orderInfo->object_id)->first();
                            if(!empty($goodsInfo)){
                                //查询商品附件
                                $attachment = UnionAttachmentModel::where('object_id',$goodsInfo->id)
                                    ->where('object_type',4)->get()->toArray();
                                if(!empty($attachment)){
                                    $attachmentId = array();
                                    foreach($attachment as $k => $v){
                                        $attachmentId[] = $v['attachment_id'];
                                    }
                                    //查询附件表
                                    $attachmentInfo = AttachmentModel::whereIn('id',$attachmentId)->get()->toarray();
                                    if(!empty($attachmentInfo)){
                                        $orderInfo['attachment'] = $attachmentInfo;
                                    }
                                }else{
                                    $orderInfo['attachment'] = array();
                                }
                                //查询被维权人的信息
                                $toUser = self::getUserInfo($goodsInfo->uid);
                                $rightsInfo['to_name'] = $toUser['name'];
                                $rightsInfo['to_email'] = $toUser['email'];
                                $rightsInfo['to_qq'] = $toUser['qq'];
                                $rightsInfo['to_mobile'] = $toUser['mobile'];
                            }
                        }else{
                            $rightsInfo['title'] = '';
                            $rightsInfo['cash'] = '';
                        }
                        break;
                }
            }
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
        //查询被维权人的信息
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
     * 作品维权成功处理
     * @param $id 维权id
     * @param $fromPrice 维权方获取金额
     * @return bool
     */
    static function dealGoodsRights($id,$fromPrice,$toPrice=0)
    {
        $status = DB::transaction(function() use($id,$fromPrice,$toPrice){
            $rightsInfo = UnionRightsModel::where('id',$id)->first();
            UnionRightsModel::where('id', $id)->update(['status' => 1,'to_price' => $toPrice,'from_price' => $fromPrice]);
            //维权方获得金额
            UserDetailModel::where('uid', $rightsInfo['from_uid'])->increment('balance', $fromPrice);
            //产生一笔财务流水，接受任务产生收益
            $finance_data = [
                'action' => 10, //维权退款
                'pay_type' => 1,
                'cash' => $fromPrice,
                'uid' => $rightsInfo['from_uid'],
                'created_at' => date('Y-m-d H:i:s', time()),
            ];
            FinancialModel::create($finance_data);
            /*//被维权方获得金额
            UserDetailModel::where('uid', $rightsInfo['to_uid'])->increment('balance', $toPrice);
            //产生一笔财务流水，接受任务产生收益
            $finance_data = [
                'action' => 10,//维权退款
                'pay_type' => 1,
                'cash' => $toPrice,
                'uid' => $rightsInfo['to_uid'],
                'created_at' => date('Y-m-d H:i:s', time()),
            ];
            FinancialModel::create($finance_data);*/
            //商品订单状态变为维权结束
            ShopOrderModel::where('id',$rightsInfo->object_id)->update(['status' => 5]);
        });
        return is_null($status)?true:false;
    }

    static function dealSeriviceRights($id,$fromPrice,$toPrice)
    {
        $status = DB::transaction(function() use($id,$fromPrice,$toPrice){
            $rightsInfo = UnionRightsModel::where('id',$id)->first();
            UnionRightsModel::where('id', $id)->update(['status' => 1,'to_price' => $toPrice,'from_price' => $fromPrice,'handled_at'=>date('Y-m-d H:i:s',time())]);
            //维权方获得金额
            UserDetailModel::where('uid', $rightsInfo['from_uid'])->increment('balance', $fromPrice);
            //产生一笔财务流水，接受任务产生收益
            $finance_data = [
                'action' => 10, //维权退款
                'pay_type' => 1,
                'cash' => $fromPrice,
                'uid' => $rightsInfo['from_uid'],
                'created_at' => date('Y-m-d H:i:s', time()),
            ];
            FinancialModel::create($finance_data);
            //被维权方获得金额
            UserDetailModel::where('uid', $rightsInfo['to_uid'])->increment('balance', $toPrice);
            //产生一笔财务流水，接受任务产生收益
            $finance_data = [
                'action' => 10,//维权退款
                'pay_type' => 1,
                'cash' => $toPrice,
                'uid' => $rightsInfo['to_uid'],
                'created_at' => date('Y-m-d H:i:s', time()),
            ];
            FinancialModel::create($finance_data);
            //修改当前雇佣的状态
            EmployModel::where('id',$rightsInfo['object_id'])->update(['status'=>4,'end_at'=>date('Y-m-d H:i:s',time())]);
        });
        return is_null($status)?true:false;
    }
    /**
     * 作品维权失败处理(作品订单状态变为确认源文件并结算改订单)
     * @param $id 维权id
     * @return bool
     */
    static function dealGoodsRightsFailure($id)
    {
        $status = DB::transaction(function() use($id){
            $rightsInfo = UnionRightsModel::where('id',$id)->first();
            UnionRightsModel::where('id', $id)->update(['status' => 2]);
            //商品订单状态变为确认源文件
            $shopOrder = ShopOrderModel::where('id',$rightsInfo->object_id)->first();
            ShopOrderModel::where('id',$rightsInfo->object_id)->update(['status' => 2,'confirm_time' => date('Y-m-d H:i:s')]);
            //计算交易平台提成金额
            if(!empty($shopOrder->trade_rate)){
                $tradePay = $shopOrder->cash*$shopOrder->trade_rate*0.01;
            }else{
                $tradePay = 0;
            }
            //结算金额
            $cash = $shopOrder->cash - $tradePay;
            //查询商品详情
            $goodsInfo = GoodsModel::where('id', $shopOrder->object_id)->first();
            //交易完成后给卖家结算交易金额
            UserDetailModel::where('uid', $goodsInfo->uid)->increment('balance', $cash);
            //产生一笔财务流水，接受任务产生收益
            $finance_data = [
                'action' => 9, //出售商品
                'pay_type' => 1,
                'cash' => $cash,
                'uid' => $goodsInfo->uid,
                'created_at' => date('Y-m-d H:i:s', time()),
            ];
            FinancialModel::create($finance_data);

        });
        return is_null($status)?true:false;
    }
    //雇佣维权不成立
    static function serviceRightsHandel($id)
    {
        $status = DB::transaction(function() use($id)
        {
            //查询维权信息
            $rights = self::where('id',$id)->first();
            //维权状态变更
            self::where('id',$id)->update(['status'=>2]);

            EmployModel::where('id',$rights['object_id'])->whereIn('status',[7,8])->update(['status'=>2]);

        });

        return (is_null($status))?true:false;
    }
}
