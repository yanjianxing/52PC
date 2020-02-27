<?php

namespace App\Console\Commands;

use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UserStatistic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'userStatistic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '用户数据统计';

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
        $data = UserDetailModel::with([
            'shop' => function($q){
                $q->select('uid','delivery_count', 'receive_task_num', 'publish_task_num', 'goods_num');
            },
            'goods' => function($q){
                $q->select('uid','sales_num','inquiry_num');
            }
        ])->get()->toArray();
        if($data){
            foreach($data as $k => $val){
                $arr = [
                    'receive_task_num'  => isset($val['shop']['receive_task_num']) ? $val['shop']['receive_task_num'] : 0 ,
                    'delivery_count'    => isset($val['shop']['delivery_count']) ? $val['shop']['delivery_count'] : 0,
                    'goods_num'         => isset($val['shop']['goods_num']) ? $val['shop']['goods_num'] : 0,
                    'inquiry_num'       => isset($val['goods']) && !empty($val['goods']) ? array_sum(array_pluck($val['goods'],'inquiry_num')) : 0,
                    'sales_num'         => isset($val['goods']) && !empty($val['goods']) ? array_sum(array_pluck($val['goods'],'sales_num')) : 0,
                    'updated_at'        => date('Y-m-d H:i:s')
                ];
                UserDetailModel::where('uid',$val['uid'])->update($arr);
            }
        }
    }
}
