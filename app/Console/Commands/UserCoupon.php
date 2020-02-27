<?php

namespace App\Console\Commands;
use App\Modules\Manage\Model\UserCouponModel;
use Illuminate\Console\Command;

class UserCoupon extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'UserCoupon';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '个人优惠券到期处理';

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
        //
        $now = date('Y-m-d H:i:s', time());
        //个人优化劵到期
        UserCouponModel::where('end_time','<',$now)->update(['status' => 3]);
    }
}
