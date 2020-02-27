<?php

namespace App\Modules\Manage\Model;

use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserVipCard;
use App\Modules\User\Model\UserVipCardModel;
use App\Modules\User\Model\UserVipConfigModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VipUserOrderModel extends Model
{
    //
    protected $table = 'user_viporder';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id','uid','order_num','vipid','price','pay_status','pay_time','end_time','status','created_at','type','level','do_use','num','user_balance'
    ];
    public $timestamps = false;

    //存储vip订单数据
    static public function createVipData($data){
        //用户购买vip的次数userBuyVIPCount
        $userBuyVIPCount=VipUserOrderModel::where("uid",$data['uid'])->where("pay_status",2)
            ->count();
        $userInfo=UserModel::find($data['uid']);
         switch($data['action']){
             case "vip":
                 $vip=VipModel::select("id","grade","price_time","vipconfigid","name")->where("id",$data['order_id'])->first();
                 $type=1;
                 $end_time="0000-00-00 00:00:00";
                 if($vip['price_time']==1){
                     $end_time=date("Y-m-d H:i:s",strtotime("+ 3 month"));
                 }elseif($vip['price_time']==2){
                     $end_time=date("Y-m-d H:i:s",strtotime("+ 1 year"));
                 }elseif($vip['price_time']==3){
                     $end_time=date("Y-m-d H:i:s",strtotime("+ 1 month"));
                 }elseif($vip['price_time']==4){
                     $end_time=date("Y-m-d H:i:s",strtotime("+ 6 month"));
                 }
                 $num=0;
                 //用户等级修改
                 //获取用户等级
                 $userLevel=UserModel::where("id",$data['uid'])->pluck("level");
                 if($vip['grade'] > $userLevel){
                     UserModel::where("id",$data['uid'])->update(["level"=>$vip['grade']]);
                 }
                 //冻结之前的vip 信息
                 $uservipOrderList=VipUserOrderModel::where("uid",$data['uid'])->where("status",1)->where("type",1)
                               ->get();
                 foreach ($uservipOrderList as $v){
                     $addTime=0;
                     if($vip['price_time']==1){
                         $addTime=3;
                     }elseif($vip['price_time']==2){
                         $addTime=12;
                     }
                     VipUserOrderModel::where("id",$v['id'])->where("type",1)->update(["end_time"=>date("Y-m-d H:i:s",strtotime("+ " .$addTime ." month ," .$v['end_time'])),'frozen'=>1]);
                 }
                 //用户权限修改
                   //获取vip配置表
                 $vipConfig=VipConfigModel::find($vip['vipconfigid']);
                 //获取当前用户是否有权限配置
                 $userVipConfig=UserVipConfigModel::where("uid",$data['uid'])->first();
                 //存在数据是
                 if($userVipConfig){
                     if($vip['grade'] >= $userVipConfig['level']){
                         //修改用户权限配置
                         UserVipConfigModel::where("uid",$data['uid'])->update([
                             'bid_num'=>$vipConfig['jb_times'],
                             'bid_price'=>$vipConfig['jb_price'],
                             'skill_num'=>$vipConfig['jb_times'],
                             'inquiry_num'=>$vipConfig['facs_start_xunjia'],
                             'accept_inquiry_num'=>$vipConfig['facs_accept_xunjia'],
                             'scheme_num'=>$vipConfig['facs_accept_xunjia'],
                             'stick_discount'=>$vipConfig['appreciation_zhiding'],
                             'urgent_discount'=>$vipConfig['appreciation_jiaji'],
                             'private_discount'=>$vipConfig['appreciation_duijie'],
                             'consult_discount'=>$vipConfig['appreciation_zixun'],
                             'train_discount'=>$vipConfig['appreciation_zhitongche'],
                             'appliy_num'   =>$vipConfig['facs_hangye_num'],
                             'level'=>$vip['grade'],
                             'is_show'=>$vipConfig['facs_mobile'],
                             'is_nav'=>$vipConfig['facs_daohang'] ==1?1:0,
                             'is_slide'=>$vipConfig['facs_slide'] ==1?1:0,
                             'is_logo'=>$vipConfig['facs_logo'] ==1?1:0,
                             'is_Invited'=>$vipConfig['facs_yaoqingjb'] ==1?1:0,
                         ]);
                         //修改用户的使用模板
                         if($vipConfig['facs_muban'] ==2){
                             UserModel::where("id",$data['uid'])->update(["shop_template"=>"1,2"]);
                         }elseif($vipConfig['facs_muban'] ==3){
                             UserModel::where("id",$data['uid'])->update(["shop_template"=>"1,2,3"]);
                         }
                     }
                 }else{
                     //添加用户权限
                     UserVipConfigModel::create([
                         "uid"=>$data['uid'],
                         'bid_num'=>$vipConfig['jb_times'],
                         'bid_price'=>$vipConfig['jb_price'],
                         'skill_num'=>$vipConfig['jb_times'],
                         'inquiry_num'=>$vipConfig['facs_start_xunjia'],
                         'accept_inquiry_num'=>$vipConfig['facs_accept_xunjia'],
                         'scheme_num'=>$vipConfig['facs_accept_xunjia'],
                         'stick_discount'=>$vipConfig['appreciation_zhiding'],
                         'urgent_discount'=>$vipConfig['appreciation_jiaji'],
                         'private_discount'=>$vipConfig['appreciation_duijie'],
                         'consult_discount'=>$vipConfig['appreciation_zixun'],
                         'train_discount'=>$vipConfig['appreciation_zhitongche'],
                         'appliy_num'   =>$vipConfig['facs_hangye_num'],
                         'level'=>$vip['grade'],
                         'is_show'=>$vipConfig['facs_mobile'],
                         'is_nav'=>$vipConfig['facs_daohang'] ==1?1:0,
                         'is_slide'=>$vipConfig['facs_slide'] ==1?1:0,
                         'is_logo'=>$vipConfig['facs_logo'] ==1?1:0,
                         'is_Invited'=>$vipConfig['facs_yaoqingjb'] ==1?1:0,
                     ]);

                     //修改用户的使用模板
                     if($vipConfig['facs_muban'] ==2){
                         UserModel::where("id",$data['uid'])->update(["shop_template"=>"1,2"]);
                     }elseif($vipConfig['facs_muban'] ==3){
                         UserModel::where("id",$data['uid'])->update(["shop_template"=>"1,2,3"]);
                     }
                 }
                 //给用户发送短信
                     $user = [
                         'uid'    => $data['uid'],
                         'email'  => $userInfo['email'],
                         'mobile' => $userInfo['mobile']
                     ];
                     $templateArr = [
                         'username' => $userInfo['name'],
                         'vip'     =>$vip['name'],
                     ];
                     \MessageTemplateClass::sendMessage('vip_buy',$user,$templateArr,$templateArr);
                 //dd();
                 break;
             case "ck":
             case "cika":
                 $vip=VipConfigModel::leftJoin("vip","vip_config.id","=","vip.vipconfigid")->where("vip_config.id",$data['order_id'])->select("vip.id","vip.grade","vip.price_time","vip_config.vip_cika_price","vip_config.vip_cika_num","vip_config.jb_price","vip.name")->first();
                 $type=2;
                 $end_time="0000-00-00 00:00:00";
                 if($vip['price_time']==1){
                     $end_time=date("Y-m-d H:i:s",strtotime("+ 3 month"));
                 }elseif($vip['price_time']==2){
                     $end_time=date("Y-m-d H:i:s",strtotime("+ 1 year"));
                 }elseif($vip['price_time']==3){
                     $end_time=date("Y-m-d H:i:s",strtotime("+ 1 month"));
                 }elseif($vip['price_time']==4){
                     $end_time=date("Y-m-d H:i:s",strtotime("+ 6 month"));
                 }
                 // $end_time="0000-00-00 00:00:00";
                 $num=$vip['vip_cika_num']?$vip['vip_cika_num']:1;
                 UserVipCardModel::createVipCard(
                     [
                         'level'=>$vip['grade'],
                         'num'=>$num,
                         "name"=>$vip['name']."次卡",
                         "type"=>1,
                         'max_price'=>$vip['jb_price'],
                         "uid"=>$data['uid'],
                     ]
                 );
                 break;
             case "rk":
             case "rika":
                 $vip=VipConfigModel::leftJoin("vip","vip_config.id","=","vip.vipconfigid")->where("vip_config.id",$data['order_id'])->select("vip.id","vip.grade","vip_config.vip_rika_price","vip_config.vip_rika_num","vip_config.jb_price","vip.name")->first();
                 $type=3;
                 $end_time=date("Y-m-d H:i:s",strtotime(date("Y-m-d")) +60*60*24);
                 $num=$vip['vip_rika_num']?$vip['vip_rika_num']:1;
                 UserVipCardModel::createVipCard(
                     [
                         'level'=>$vip['grade'],
                         'num'=>$num,
                         "name"=>$vip['name']."日卡",
                         "type"=>2,
                         'max_price'=>$vip['jb_price'],
                         "uid"=>$data['uid'],
                     ]
                 );
                 break;
         }
        $userVipOrder=self::where("order_num",$data['order_num'])->first();
        if($userVipOrder){
            $orderId=$userVipOrder['id'];
            self::where("order_num",$data['order_num'])->update(
                [
                    "order_num"=>$data['order_num'],
                    "uid"=>$data['uid'],
                    "price"=>$data['price'],
                    "vipid"=>$vip['id'],
                    "pay_status"=>2,
                    "pay_time"=>date("Y-m-d H:i:s"),
                    "status"=>1,
                    "end_time"=>$end_time,
                    "created_at"=>date("Y-m-d H:i:s"),
                    "type"=>$type,
                    'level'=>$vip['grade'],
                    'do_use'=>$num,
                    'user_balance'=>$data['user_balance'],
                    'num'=>$userBuyVIPCount +1,
                ]
            );
        }else{
            $orderId=self::insertGetId([
                "order_num"=>$data['order_num'],
                "uid"=>$data['uid'],
                "price"=>$data['price'],
                "vipid"=>$vip['id'],
                "pay_status"=>2,
                "pay_time"=>date("Y-m-d H:i:s"),
                "status"=>1,
                "end_time"=>$end_time,
                "created_at"=>date("Y-m-d H:i:s"),
                "type"=>$type,
                'level'=>$vip['grade'],
                'do_use'=>$num,
                'user_balance'=>$data['user_balance'],
                'num'=>$userBuyVIPCount +1,
            ]);
        }
        return $orderId;
    }


    static public function getStatusByUid($uid){
        $vipstatus = self::where('uid',$uid)->where('pay_status','2')->where('frozen','0')->orderBy('pay_time','desc')->first();
        $nowdate = date("Y-m-d H:i:s",time());
        if($vipstatus){
            if($vipstatus->status == 1 && $vipstatus->end_time >$nowdate){
                return true;
            }else{
                return false;
            }
        }
        return false;
    }
}
