<?php

namespace App\Console\Commands;

use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\VipModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserVipConfigModel;
use Illuminate\Console\Command;
use DB;

class VipUserRight extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'VipUserRight';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'ViP过期用户权限重置';

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
        //修改vip订单过期问题
        //查询所有的过期的订单
        $userVipListId=VipUserOrderModel::where("frozen",0)->where("pay_status",2)->where("status",1)->where('end_time','>=',$now)->lists("uid")->toArray();
        //查询过期
        $userVipOld=VipUserOrderModel::where("frozen",0)->where("type",1)->whereNotIn("uid",$userVipListId)->where("status",2)->lists("uid")->toArray();
        $userVipOld=array_unique($userVipOld);
        $res=DB::transaction(function() use($userVipOld){
            foreach ($userVipOld as $v){
                   //查看用户是否有冻结vip
                    $userVipFrozen=VipUserOrderModel::where("frozen",1)->where("uid",$v)->first();
                    if(!$userVipFrozen){//不存在的时候
                        UserModel::where("id",$v['uid'])->update(["level"=>1]);
                        //用户配置信息还原
                        $userConfig=ConfigModel::getConfigByType("user");
                        UserVipConfigModel::where("uid",$v['uid'])->update([
                            'uid'=>$v['uid'],
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
