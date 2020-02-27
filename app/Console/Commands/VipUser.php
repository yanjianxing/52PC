<?php

namespace App\Console\Commands;

use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\VipConfigModel;
use App\Modules\Manage\Model\VipModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserVipConfigModel;
use Illuminate\Console\Command;
use DB;

class VipUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'VipUser';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'VIP订单过期处理';

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
        $userVipList=VipUserOrderModel::where("frozen",0)->where("pay_status",2)->where("status",1)->where('end_time','<',$now)->get();
        $res=DB::transaction(function() use($userVipList){
            foreach ($userVipList as $v){
                //修改vip订单
                 VipUserOrderModel::where("id",$v['id'])->update(["status"=>2]);
                //获取用户信息
                $userInfo=UserModel::find($v['uid']);
                //获取vip信息
                $vipInfo=VipModel::find($v['vipid']);
                //修改用户的权限
                if($v['type'] ==1){
                    //给用户发送短信
                    $user = [
                        'uid'    => $userInfo->id,
                        'email'  => $userInfo->email,
                        'mobile' => $userInfo->mobile
                    ];
                    $templateArr = [
                        'username' => $userInfo->name,
                         'vip'     =>$vipInfo->name,
                    ];
                    \MessageTemplateClass::sendMessage('vip_overtime',$user,$templateArr,$templateArr);
                    //查看用户是否有冻结vip
                    $userVipFrozen=VipUserOrderModel::where("frozen",1)->where("uid",$v['uid'])->orderBy("level","desc")->orderBy("id","desc")->first();
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

                    }else{
                        $userLevel=UserVipConfigModel::where("uid",$v['uid'])->pluck("level");
                        if($userVipFrozen['level'] < $userLevel){
                            //修改用户的配置
                            $vipInfo=VipModel::select("vipconfigid")->where("grade",$userLevel)->first();
                            //获取该级别的权限
                            $vipConfig=VipConfigModel::find($vipInfo['vipconfigid']);
                                $vipConfig && UserVipConfigModel::where("uid",$v['uid'])->update([
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
                                'level'=>$userLevel,
                                'is_show'=>$vipConfig['facs_mobile'],
                                'is_nav'=>$vipConfig['facs_daohang'] ==1?1:0,
                                'is_slide'=>$vipConfig['facs_slide'] ==1?1:0,
                                'is_logo'=>$vipConfig['facs_logo'] ==1?1:0,
                                'is_Invited'=>$vipConfig['facs_yaoqingjb'] ==1?1:0,
                            ]);
                        }
                        UserModel::where("id",$v['uid'])->update(["level"=>$userVipFrozen['level']]);
                        //修改冻结的状态
                        VipUserOrderModel::where("id",$userVipFrozen)->update(["frozen"=>0]);
                    }
                }else{
                     //修改次卡订单
                    VipUserOrderModel::where("id",$v['id'])->update(["status"=>2]);
                }
            }
             return ;
        });

       // VipUserOrderModel::where('end_time','<',$now)->update(['status' => 2]);
    }
}
