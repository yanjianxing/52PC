<?php

namespace App\Console\Commands;

use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\VipConfigModel;
use App\Modules\Manage\Model\VipModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserVipConfigModel;
use App\Modules\Manage\Model\PromoteFreegrantModel;
use App\Modules\Manage\Model\PromoteFreegrantUserlistModel;
use App\Modules\Manage\Model\UserCouponModel;
use Illuminate\Console\Command;
use DB;

class VipRenew extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'VipRenew';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'VIP续费自动发优惠券';

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
        $is_has = PromoteFreegrantModel::where("action","14")->where("is_open",1)->first();
        if($is_has){
            $days = $is_has['send_time']>0 ? $is_has['send_time'] : '1';
            $now_time = date("Y-m-d H:i:s",time()+$days*24*60*60); 
            $result = VipUserOrderModel::where("end_time","<",$now_time)->where("frozen",0)->where("pay_status",2)->where("type",1)->where("status",1)->get()->toArray();
            foreach ($result as $v) {
                  $Has_coupon = UserCouponModel::where("coupon_id",$is_has['prize'])->where("uid",$v['uid'])->where("end_time",">",date("Y-m-d H:i:s"))->first(); 
                  if($Has_coupon){
                    continue;
                  }
                  UserModel::sendfreegrant($v['uid'],14);//.自动发放
            }   
        }

    }
}
