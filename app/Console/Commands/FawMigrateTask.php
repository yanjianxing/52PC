<?php

namespace App\Console\Commands;

use App\Modules\Task\Model\TaskAgentModel;
use App\Modules\Task\Model\TaskAttachmentModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskFocusModel;
use App\Modules\Task\Model\TaskFollowModel;
use App\Modules\Task\Model\TaskInviteModel;
use App\Modules\Task\Model\TaskPaySectionModel;
use App\Modules\Task\Model\TaskPayTypeModel;
use App\Modules\Task\Model\TaskPublishingModel;
use App\Modules\Task\Model\TaskRightsModel;
use App\Modules\Task\Model\TaskServiceModel;
use App\Modules\Task\Model\TaskTemplateModel;
use App\Modules\Task\Model\WorkAttachmentModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\TaskModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Console\Command;
use Excel;
use File;
use Illuminate\Support\Facades\DB;


class FawMigrateTask extends Command
{
    protected $signature = 'faw:update_task';

    protected $excelLoad ;
    protected $excelData = array();

    protected $description = 'update task';


    public function __construct()
    {
        parent::__construct();

        $this->excelLoad = public_path('migration/task/task.xls');
    }

    public function handle()
    {
        //导入数据先删除已有数据
        TaskModel::whereRaw('1=1')->delete();
        TaskServiceModel::whereRaw('1=1')->delete();
        TaskAgentModel::whereRaw('1=1')->delete();
        TaskAttachmentModel::whereRaw('1=1')->delete();
        TaskFocusModel::whereRaw('1=1')->delete();
        TaskFollowModel::whereRaw('1=1')->delete();
        TaskInviteModel::whereRaw('1=1')->delete();
        TaskPaySectionModel::whereRaw('1=1')->delete();
        TaskPayTypeModel::whereRaw('1=1')->delete();
        TaskPublishingModel::whereRaw('1=1')->delete();
        TaskRightsModel::whereRaw('1=1')->delete();
        TaskTemplateModel::whereRaw('1=1')->delete();
        WorkModel::whereRaw('1=1')->delete();
        WorkAttachmentModel::whereRaw('1=1')->delete();

        if(!file_exists($this->excelLoad)){
            $this->info('task excel Not Found');exit;
        }

        $fieldArr = [
            5 => 1,
            6 => 2,
            21 => 10,
            30 => 9,
            41 => 11,
            48 => 14,
            58 => 8,
            91 => 7,
            118 => 12,
            129 => 5,
            142 => 6,
            154 => 13,
            163 => 3,
            177 => 4,
            178 => 15,
            194 => 17,
            361 => 16,
            371 => 17
        ];

        $skillArr = [
            11 => 18,
            12 => 24,
            13 => 28,
            14 => 29,
            293 => 30,
            301 => 31,
            305 => 32,
            313 => 27,
            326 => 20,
            342 => 35,
            343 => 21,
            365 => 22,
        ];

        $this->info('data start migrating');
        $content = file_get_contents($this->excelLoad);
        //获取当前文本编码格式
        $fileType = mb_detect_encoding($content , array('UTF-8','GBK','LATIN1','BIG5'));
        Excel::load($this->excelLoad, function($reader) {

            $reader = $reader->getSheet(0);
            $data = $reader->toArray();
            $this->excelData = [];
            $this->excelData = $data;
        },$fileType);

        unset($this->excelData[0]);
        $ResultData = [];
        foreach($this->excelData as $Ked => $Ved) {

            $status = 8;//已完成
            $province = !empty($Ved[4]) && is_array(explode(',',$Ved[4])) && isset(explode(',',$Ved[4])[0]) ? explode(',',$Ved[4])[0] : 0;
            $city = !empty($Ved[4]) && is_array(explode(',',$Ved[4])) && isset(explode(',',$Ved[4])[1]) ? explode(',',$Ved[4])[1] : 0;

            $isCar = $Ved[8] == 1 ? 0 : 1;
            $fieldId = 0;
            if($Ved[12] && in_array(intval($Ved[12]),array_keys($fieldArr))){

                $fieldId = $fieldArr[intval($Ved[12])];
            }
            $skillId = 0;
            if($Ved[13] && in_array(intval($Ved[13]),array_keys($skillArr))){
                $skillId = $skillArr[intval($Ved[13])];
            }
            $phone = '';
            $user = UserModel::find(intval($Ved[1]));
            if($user){
                $phone = $user->mobile;
            }
            $ResultData[] = [
                'type_id'           => 1,
                'id'                => intval($Ved[0]),
                'uid'               => intval($Ved[1]),
                'bounty'            => $Ved[2],
                'title'             => $Ved[3],
                'province'          => $province,
                'city'              => $city,
                'desc'              => $Ved[5],
                'view_count'        => $Ved[6],
                'status'            => $status,
                'is_car'            => $isCar,
                'created_at'        => date('Y-m-d H:i:s',strtotime($Ved[9])),
                'delivery_deadline' => date('Y-m-d 23:59:59',strtotime($Ved[10])),
                'field_id'          => $fieldId,
                'cate_id'           => $skillId,
                'project_agent'     => 0,
                'worker_num'        => 1,
                'begin_at'          => date('Y-m-d H:i:s',strtotime($Ved[9])),
                'show_cash'         => $Ved[2],
                'is_free'           => 1,
                'verified_at'       => isset($Ved[14]) ? date('Y-m-d H:i:s',strtotime($Ved[14])) : '',
                'phone'             => $phone
            ];

        }
        $total = count($ResultData);
        $this->output->progressStart(ceil($total/100));
        for($i = 0;$i < ceil($total/100); $i++){
            $this->info('task is migrating');
            $this->output->progressAdvance();
            TaskModel::insert(array_slice($ResultData,$i*100,100),true);
        }
        $this->output->progressFinish();
        $this->info('task success');
    }

}
