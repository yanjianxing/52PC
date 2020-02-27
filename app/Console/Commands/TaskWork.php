<?php

namespace App\Console\Commands;

use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskTypeModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\UserDetailModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Log;

class TaskWork extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'taskWork';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '任务选稿';

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
        //获取任务类型id
        $taskTypeId = TaskTypeModel::getTaskTypeIdByAlias('jingbiao');
        //查询正在进行投稿的任务
        $tasks = TaskModel::where('type_id',$taskTypeId)->where('status',2)->get()->toArray();

        //查询系统设定的时间规则筛选筛选出交稿时间到期的任务
        $expireTasks = self::expireTasks($tasks);

        //过期任务失败
        foreach($expireTasks as $v){
            DB::transaction(function() use($v){
                //修改当前任务状态
                TaskModel::where('id',$v)->update(['status'=>10,'end_at'=>date('Y-m-d H:i:s',time())]);
                if($v['is_car'] == 1 && $v['car_cash'] > 0){
                    //退回直通车费用
                    UserDetailModel::where('uid',$v['uid'])->increment('balance',$v['car_cash']);
                    //产生一条财务记录
                    $remainder = DB::table('user_detail')->where('uid',$v['uid'])->first()->balance;
                    $finance_data = [
                        'action'     => 7,//退款
                        'pay_type'   => 1,
                        'cash'       => $v['car_cash'],
                        'uid'        => $v['uid'],
                        'created_at' => date('Y-m-d H:i:s',time()),
                        'updated_at' => date('Y-m-d H:i:s',time()),
                        'status'     => 1,//用户收入
                        'remainder'  => $remainder
                    ];
                    FinancialModel::create($finance_data);
                }

            });
        }

    }

    private function expireTasks($data)
    {
        $expireTasks = [];
        foreach($data as $k=>$v) {
            $time = time();
            //判断当前到期的任务
            if(strtotime($v['delivery_deadline']) <= $time) {
                $expireTasks[] = $v['id'];
            }
        }
        return $expireTasks;
    }

}
