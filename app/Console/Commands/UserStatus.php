<?php

namespace App\Console\Commands;

use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UserStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'userStatus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '解除用户禁用';

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

        $uid = UserModel::where('forbidden_at','<',date('Y-m-d H:i:s'))->where('forbidden_at','!=','0000-00-00 00:00:00')->where('status',2)->lists('id');
        //处理当前的置顶为不置顶
        UserModel::whereIn('id',$uid)->update(['status'=>1]);
    }
}
