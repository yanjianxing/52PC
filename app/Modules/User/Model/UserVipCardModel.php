<?php

namespace App\Modules\User\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserVipCardModel extends Model
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'user_vip_card';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id','uid','level','do_use','has_use','surplus_use','created_at','max_price','card_name','type'
    ];
    public $timestamps =false;

     static  public function createVipCard($data){
         //查询用户vip开
         $findUserVipCard=self::where("uid",$data['uid'])
                            ->where("level",$data['level'])->where("type",$data['type'])->first();
         //查询用户信息
         $userInfo=UserModel::find($data['uid']);
         if($findUserVipCard){
             self::where("uid",$data['uid'])
                 ->where("level",$data['level'])->where("type",$data['type'])->update([
                     'type'=>$data['type'],
                     'do_use'=>$findUserVipCard['do_use'] + intval($data['num']),
                     'created_at'=>date("Y-m-d H:i:s"),
                     'card_name'=>$data['name'],
                     'max_price'=>$data['max_price'],
                 ]);
         }else{
             self::create(
                 [
                    "uid"=>$data['uid'],
                    'level'=>$data['level'],
                    'do_use'=>$data['num'],
                    'created_at'=>date("Y-m-d H:i:s"),
                    'type'=>$data['type'],
                    'card_name'=>$data['name'],
                    'max_price'=>$data['max_price'],
                 ]
             );
         }
         //给用户发送短信
                   $user = [
                       'uid'    =>$data['uid'],
                       'email'  => $userInfo['email'],
                       'mobile' => $userInfo['mobile']
                   ];
                   $templateArr = [
                       'username' =>$userInfo['name'],
                       'card'     =>$data['name'],
                   ];
                   \MessageTemplateClass::sendMessage("vip_card",$user,$templateArr,$templateArr);
         return ;
     }

    //.获取竞标卡(只考虑次卡)
    static public function getBidCard(){
        $biddingcardall=UserVipCardModel::where("uid",Auth::user()->id)
            ->where('type',1)//次卡
            ->where('do_use','>',0)
            ->lists('do_use')
            ->sum();//.获取所有竞标卡
       $biddingcardqt=UserVipCardModel::where("uid",Auth::user()->id)
            ->where('type',1)//次卡
            ->where('level','2')
            ->where('do_use','>',0)
            ->pluck('do_use');//.获取青铜竞标卡
         $biddingcardby=UserVipCardModel::where("uid",Auth::user()->id)
             ->where('type',1)//次卡
             ->where('level','3')
             ->where('do_use','>',0)
             ->pluck('do_use'); //.获取白银竞标卡
         $biddingcardhj=UserVipCardModel::where("uid",Auth::user()->id)
             ->where('type',1)//次卡
             ->where('level','4')
             ->where('do_use','>',0)
             ->pluck('do_use');//.获取黄金竞标卡
         $data=[
             'biddingcardall' =>$biddingcardall,
             'biddingcardqt' =>$biddingcardqt,
             'biddingcardby' =>$biddingcardby,
             'biddingcardhj' =>$biddingcardhj,
         ];
         return $data;
    }

    //.获取用户竞标卡列表
    static public function getUserVipCardList($merge=[],$paginate=10){
        $reslist=UserVipCardModel::leftJoin('users','user_vip_card.uid','=','users.id')->select('user_vip_card.*','users.name','users.mobile','users.created_at as register_time');
         //用户名搜索
        if(isset($merge['name']) && !empty($merge['name'])){
            $reslist = $reslist->where('users.name','like',$merge['name']);
        }
        //手机号搜索
        if(isset($merge['mobile']) && !empty($merge['mobile'])){
            $reslist = $reslist->where('users.mobile',$merge['mobile']);
        }
        //次卡类型搜索
        if(isset($merge['level']) && !empty($merge['level'])){
            $reslist = $reslist->where('user_vip_card.type',1)->where('user_vip_card.level',$merge['level']);
        }
        //注册时间搜索
        if(isset($merge['register_time_start']) && !empty($merge['register_time_start'])){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $merge['register_time_start']);
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $reslist = $reslist->where('users.created_at','>',$start);
        }
        if(isset($merge['register_time_end']) && !empty($merge['register_time_end'])){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $merge['register_time_end']);
            $end = date('Y-m-d  23:59:59',strtotime($end));
            $reslist = $reslist->where('users.created_at','<',$end);
        }
        $reslist=$reslist->orderBy('user_vip_card.id','desc')->paginate($paginate);
        return $reslist;

    }

}