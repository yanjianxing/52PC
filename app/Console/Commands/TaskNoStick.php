<?php

namespace App\Console\Commands;

use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\UserDetailModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TaskNoStick extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'taskNoStick';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '任务置顶过期';

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
        $stick_day = 3;
        //查询当前时间超过置顶时间的任务
        $stickOffTask = TaskModel::where('verified_at','<',date('Y-m-d H:i:s',time()-$stick_day*24*3600))->where('top_status','>',0)->lists('id');
        //处理当前的置顶为不置顶
        TaskModel::whereIn('id',$stickOffTask)->update(['top_status'=>0]);
    }
}
