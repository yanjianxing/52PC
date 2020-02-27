<?php

namespace App\Console\Commands;

use App\Modules\Manage\Model\VipModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\Shop\Models\ProgrammeInquiryPayModel;
use App\Modules\User\Model\UserCouponLogModel;
use App\Modules\User\Model\UserDepositModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserToolModel;
use App\Modules\User\Model\UserVipCardModel;
use Illuminate\Console\Command;
use DB;

class UserRelevant extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UserRelevant';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '即将到期通知';

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
        $now = date('Y-m-d');
        //到期限制时间
        $expire=date("Y-m-d",strtotime("+3 day"));
        //用户vip即将到期信息通知
         //获取当前即将到期的vip信息
        $userVipList=VipUserOrderModel::where("frozen",0)->where("type",1)->where("status",1)->where("pay_status",2)->where("end_time","like","%".$expire."%")->get();
        foreach($userVipList as $v){
            //获取用户信息
            $userInfo=UserModel::find($v['uid']);
            //获取vip信息
            $vipInfo=VipModel::find($v['vipid']);
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
            \MessageTemplateClass::sendMessage('vip_intime',$user,$templateArr,$templateArr);
        }
        //删除超过24小时未支付的订单
        $_24h=date("Y-m-d H:i:s",strtotime("-1"));
        //删除购买vip 购买次卡
        VipUserOrderModel::where("pay_status",1)->where("created_at","<=",$_24h)->delete();
        //删除保证金
        UserDepositModel::where("status",1)->where("created_at","<=",$_24h)->delete();
        //删除购买工具
        UserToolModel::where("pay_status",1)->where("created_at","<=",$_24h)->delete();
        //删除查看联系方式
        ProgrammeInquiryPayModel::where("status",1)->where("created_at","<=",$_24h)->delete();
        //未使用优惠券时
        UserCouponLogModel::where("status",1)->where("created_at","<=",$_24h)->delete();
        //日卡到期处理
        $rika=UserVipCardModel:: where("type",2)->where("created_at","<=",$now)->get();
        foreach ($rika as $k=>$v){
            $has_use=$v['surplus_use']+$v['has_use'];
            UserVipCardModel::where("id",$v['id'])->update([
                'do_use'=>0,
                'has_use'=>$has_use,
                'surplus_use'=>0,
            ]);
        }
        //UserVipCardModel

    }
}
