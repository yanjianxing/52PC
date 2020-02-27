<?php

namespace App\Console\Commands;

use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\WorkAttachmentModel;
use App\Modules\Task\Model\WorkModel;
use Illuminate\Console\Command;
use Excel;
use File;
use Illuminate\Support\Facades\DB;


class FawMigrateWork extends Command
{
    protected $signature = 'faw:update_work';

    protected $excelLoad ;
    protected $excelData = array();

    protected $description = 'update work';


    public function __construct()
    {
        parent::__construct();

        $this->excelLoad = public_path('migration/task/taskWork.xls');

    }

    public function handle()
    {
        //导入数据先删除已有数据
        // WorkModel::whereRaw('1=1')->delete();
        // WorkAttachmentModel::whereRaw('1=1')->delete();

        if(!file_exists($this->excelLoad)){
            $this->info('work excel Not Found');exit;
        }

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

            $status = intval($Ved[3]) == 1 ? 1 : 0;
            $days = !empty($Ved[8]) ? intval($Ved[8]) + 1 : 2;
            $start = date("Y-m-d 00:00:00",strtotime("+1 day",strtotime($Ved[4])));
            $end =  date("Y-m-d 00:00:00",strtotime("+".$days." day",strtotime($Ved[4])));
            $ResultData[] = [
                'id'            => intval($Ved[0]),
                'task_id'       => intval($Ved[1]),
                'uid'           => intval($Ved[2]),
                'status'        => $status,
                'created_at'    => date('Y-m-d H:i:s',strtotime($Ved[4])),
                'price'         => $Ved[5],
                'desc'          => $Ved[6],
                'bid_at'        => $status == 1 ? date('Y-m-d H:i:s',strtotime($Ved[7])) : '',
                'start_time'    => $start,
                'end_time'      => $end,

            ];

        }
        $total = count($ResultData);
        $this->output->progressStart(ceil($total/100));
        for($i = 0;$i < ceil($total/100); $i++){
            $this->info('work is migrating');
            $this->output->progressAdvance();
            WorkModel::insert(array_slice($ResultData,$i*100,100),true);
        }
        $this->output->progressFinish();
        $this->info('work success');

    }

}
