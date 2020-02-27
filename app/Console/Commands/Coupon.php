<?php

namespace App\Console\Commands;
use App\Modules\Manage\Model\CouponModel;
use Illuminate\Console\Command;

class Coupon extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'Coupon';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '系统优惠券到期处理';

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

        CouponModel::where('end_time','<',$now)->update(['status' => 3]);
    }
}
