<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Auth;

class UserCouponModel extends Model
{
    //
    protected $table = 'user_coupon';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id','uid','coupon_id','created_at','end_time','status'
    ];

    public $timestamps = false;
    //根据金额获取对应的优惠券
   static public function getCoupon($price,$type=[]){
        $type = is_array($type) ? $type : array($type);
        return self::leftJoin("coupon","user_coupon.coupon_id","=","coupon.id")
                        ->where("user_coupon.uid",Auth::user()->id)->where("coupon.full_price","<=",$price)->whereIn('type',$type)
                        ->where("coupon.status",2)->where("user_coupon.status",1)->where("coupon.start_time","<=",date("Y-m-d"))->where("coupon.end_time",">=",date("Y-m-d"))
                      ->select("coupon.*","user_coupon.id as u_id")->get();
    }
    //获取优惠券减免后的金额
    static  public function getEndPrice($price,$userCouponId){
         $res['endPrice']=$price;
         $res['coupon']=0;
          //获取优惠券金额
          $couponPrice=self::leftJoin("coupon","user_coupon.coupon_id","=","coupon.id")
                        ->where("user_coupon.id",$userCouponId)->pluck("price");
          $res['coupon']=$couponPrice;
          if(floatval($price) > floatval($couponPrice)){
              $res['endPrice']=floatval($price)-floatval($couponPrice);
          }else{
              $res['endPrice']=0;
          }
        return $res;
    }

}
