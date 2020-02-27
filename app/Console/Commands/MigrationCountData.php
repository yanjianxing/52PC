<?php

namespace App\Console\Commands;

use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\UserDetailModel;
use Illuminate\Console\Command;
use DB;

class MigrationCountData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faw:count_data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'count data statistic';

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
        //项目竞标数量统计
        $this->info('项目竞标数量统计');
        $workArr = WorkModel::whereIn('status',[0,1])->groupBy('task_id')->get(['task_id',DB::raw('COUNT(*) as value')])->toArray();
        $this->output->progressStart(count($workArr));
        if(!empty($workArr)){
            foreach($workArr as $k => $v){
                TaskModel::where('id',$v['task_id'])->update([
                    'delivery_count' => $v['value']
                ]);
                $this->output->progressAdvance();
            }
        }
        $this->output->progressFinish();

        $this->info('用户竞标任务数量统计');
        //用户竞标任务数量
        $workUserArr = WorkModel::whereIn('status',[0,1])->groupBy('uid')->get(['uid',DB::raw('COUNT(*) as delivery_count')])->toArray();
        $this->output->progressStart(count($workUserArr));
        if(!empty($workUserArr)){
            foreach($workUserArr as $k => $v){
                UserDetailModel::where('id',$v['uid'])->update([
                    'delivery_count' => $v['delivery_count']
                ]);
                ShopModel::where('uid',$v['uid'])->update([
                    'delivery_count' => $v['delivery_count']
                ]);
                $this->output->progressAdvance();
            }
        }
        $this->output->progressFinish();

        //用户参与任务数量
        $this->info('用户参与任务数量统计');
        $workUserTask = WorkModel::where('status',1)->groupBy('uid')->get(['uid',DB::raw('COUNT(*) as receive_task_num')])->toArray();
        $this->output->progressStart(count($workUserTask));
        if(!empty($workUserTask)){
            foreach($workUserTask as $k => $v){
                UserDetailModel::where('id',$v['uid'])->update([
                    'receive_task_num' => $v['receive_task_num']
                ]);
                ShopModel::where('uid',$v['uid'])->update([
                    'receive_task_num' => $v['receive_task_num']
                ]);
                $this->output->progressAdvance();
            }
        }
        $this->output->progressFinish();

        //用户发布任务
        $this->info('用户发布任务数量统计');
        $taskUserArr = TaskModel::groupBy('uid')->get(['uid',DB::raw('COUNT(*) as publish_task_num')])->toArray();
        $this->output->progressStart(count($taskUserArr));
        if(!empty($taskUserArr)){
            foreach($taskUserArr as $k => $v){
                UserDetailModel::where('id',$v['uid'])->update([
                    'publish_task_num' => $v['publish_task_num']
                ]);
                ShopModel::where('uid',$v['uid'])->update([
                    'publish_task_num' => $v['publish_task_num']
                ]);
                $this->output->progressAdvance();
            }
        }
        $this->output->progressFinish();
    }
}
