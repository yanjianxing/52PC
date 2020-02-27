<?php

namespace App\Console\Commands;

use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\VipModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserVipConfigModel;
use Illuminate\Console\Command;
use DB;

class VipUserOldDeal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'VipUserOldDeal';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '处理普通用户权限问题';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $now = date('Y-m-d H:i:s');
        //获取所有普通用户信息
        $ptUid=UserModel::where("level",1)->lists("id")->toArray();
        //获取用户权限配置表中普通会员不是普通用户权限的用户id
        $userVip=UserVipConfigModel::whereIn("uid",$ptUid)->where('level','!=',1)->lists("uid")->toArray();
        //获取vip用户
        $vipUid=UserModel::where("level","!=",1)->lists("id")->toArray();
        //更加vip用户判断vip用户购买的会员是否过期获取未过期的用户id
        $vipUidList=VipUserOrderModel::where(function($query){
            $query->where("frozen",1)->orWhere("status",1);
        })->whereIn("uid",$vipUid)->lists("uid")->toArray();
        //获取过期的vip
        $oldVipUser=[];
        foreach($vipUid as $vipV){
            if(!in_array($vipV,$vipUidList)){
                $oldVipUser[]=$vipV;
            }
        }
        //获取的最终需要处理的用户
        $resUser=array_unique(array_merge($userVip,$oldVipUser));
        // $resUser = ["49198"];
        $res=DB::transaction(function() use($resUser){
            foreach ($resUser as $v){
                   //查看用户是否有冻结vip
                    $userVipFrozen=VipUserOrderModel::where("frozen",1)->where("uid",$v)->first();
                    if(!$userVipFrozen){//不存在的时候
                        UserModel::where("id",$v)->update(["level"=>1]);
                        //用户配置信息还原
                        $userConfig=ConfigModel::getConfigByType("user");
                        UserVipConfigModel::where("uid",$v)->update([
                            'uid'=>$v,
                            'bid_num'=>$userConfig['user_bid_num'],
                            'bid_price'=>$userConfig['user_bid_price'],
                            'skill_num'=>$userConfig['user_skill_num'],
                            'appliy_num'=>$userConfig['user_appliy_num'],
                            //'appliy_num'=>$userConfig['user_bid_price'],
                            'inquiry_num'=>$userConfig['user_inquiry_num'],
                            'accept_inquiry_num'=>$userConfig['user_accept_inquiry_num'],
                            'scheme_num'=>$userConfig['user_scheme_num'],
                            'stick_discount'=>$userConfig['user_stick_discount'],
                            'urgent_discount'=>$userConfig['user_urgent_discount'],
                            'private_discount'=>$userConfig['user_private_discount'],
                            'train_discount'=>$userConfig['user_train_discount'],
                            'level'=>1,
                            'is_show'=>$userConfig['user_is_show'],
                            //'is_Invited'=>$userConfig['user_is_show'],
                            'is_logo'=>$userConfig['user_is_logo'],
                            'is_nav'=>$userConfig['user_is_nav'],
                            'is_slide'=>$userConfig['user_is_slide'],
                        ]);
                    }
            }
             return ;
        });

       // VipUserOrderModel::where('end_time','<',$now)->update(['status' => 2]);
    }
}
