<?php

namespace App\Console\Commands;

use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskTypeModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Log;

class TaskNotice extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'taskNotice';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '任务提醒';

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

        //即将到期提醒
        foreach($expireTasks as $v){
            DB::transaction(function() use($v){
                //发送提醒消息
                $userInfo = UserModel::find($v['uid']);
                $user = [
                    'uid'    => $userInfo->id,
                    'email'  => $userInfo->email,
                    'mobile' => $userInfo->mobile
                ];
                $templateArr = [
                    'username'      => $userInfo->name,
                    'title'         => $v['title']
                ];
                \MessageTemplateClass::sendMessage('task_in_time',$user,$templateArr,$templateArr);

            });
        }

    }

    private function expireTasks($data)
    {
        $expireTasks = [];
        foreach($data as $k=>$v) {
            $time = time();
            //判断当前到期时间24h的任务
            if($time - strtotime($v['delivery_deadline']) <= 24*3600 && $time - strtotime($v['delivery_deadline']) > 0) {
                $expireTasks[] = $v['id'];
            }
        }
        return $expireTasks;
    }

}
