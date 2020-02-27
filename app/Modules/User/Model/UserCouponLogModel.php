<?php

namespace App\Modules\User\Model;

use App\Modules\Manage\Model\UserCouponModel;
use Illuminate\Database\Eloquent\Model;

class UserCouponLogModel extends Model
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
     protected $table = 'user_coupon_log';

    public $timestamps = false;

    protected $fillable = [
        'id','order_num', 'uid','user_coupon_id','price','created_at','payment_at','status'
    ];

    //优惠券处理
    static  public function userCouponHandle($orderNum,$uid,$type,$userCouponId){
          if($type ==2){
              //查询优惠券使用记录是否有
              $userCouponLog=self::where("order_num",$orderNum)->where("status",1)->first();
              if($userCouponLog){
                  //修改用户使用优惠券的状态
                  self::where("order_num",$orderNum)->update(["status"=>2,'payment_at'=>date("Y-m-d H:i:s")]);
                  //给该优惠券做已使用的标识
                  UserCouponModel::where("id",$userCouponId)->update(["status"=>2]);
              }else{
                  //创建数据
                  self::create([
                      'order_num'=>$orderNum,
                      'uid'=>$uid,
                      'user_coupon_id'=>$userCouponId,
                      'created_at'=>date("Y-m-d H:i:s"),
                      'payment_at'=>date("Y-m-d H:i:s"),
                      'status'=>2,
                  ]);
                  UserCouponModel::where("id",$userCouponId)->update(["status"=>2]);
              }
          }else{
              //创建数据
              self::create([
                  'order_num'=>$orderNum,
                  'uid'=>$uid,
                  'user_coupon_id'=>$userCouponId,
                  'created_at'=>date("Y-m-d H:i:s"),
                  'payment_at'=>date("Y-m-d H:i:s"),
                  'status'=>1,
              ]);
          }
    }


}