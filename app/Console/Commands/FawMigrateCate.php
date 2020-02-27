<?php

namespace App\Console\Commands;

use App\Modules\Task\Model\TaskCateModel;
use Illuminate\Console\Command;
use Excel;
use File;
use Illuminate\Support\Facades\DB;


class FawMigrateCate extends Command
{
    protected $signature = 'faw:update_cate';

    protected $excelLoad ;
    protected $excelData = array();

    protected $description = 'update cate';


    public function __construct()
    {
        parent::__construct();

        $this->excelLoad = public_path('migration/cate.xls');

    }

    public function handle()
    {
        //导入数据先删除已有数据
        TaskCateModel::whereRaw('1=1')->delete();

        if(!file_exists($this->excelLoad)){
            $this->info('cate excel Not Found');exit;
        }
        $content = file_get_contents($this->excelLoad);
        $fileType = mb_detect_encoding($content , array('UTF-8','GBK','LATIN1','BIG5'));//获取当前文本编码格式
        $this->info('data start migrating');
        Excel::load($this->excelLoad, function($reader) {
            $data[0] = $reader->getSheet(0)->toArray();
            $data[1] = $reader->getSheet(1)->toArray();
            $data[2] = $reader->getSheet(2)->toArray();
            $data[3] = $reader->getSheet(3)->toArray();
            $data[4] = $reader->getSheet(4)->toArray();
            $this->excelData = [];
            $this->excelData = $data;
        },$fileType);

        $ResultData = [];
        foreach($this->excelData as $Ked => $Ved) {
            if($Ved){
                foreach($Ved as $kk => $vv){
                    if($kk > 0){
                        $ResultData[] = [
                            'id'    => intval($vv[0]),
                            'name'  => $vv[1],
                            'pid'   => 0,
                            'type'  => intval($vv[2]),

                        ];
                    }
                }
            }

        }
        $total = count($ResultData);
        $this->output->progressStart(ceil($total/1000));
        for($i = 0;$i < ceil($total/1000); $i++){
            $this->info('cate is migrating');
            $this->output->progressAdvance();
            TaskCateModel::insert(array_slice($ResultData,$i*1000,1000),true);
        }
        $this->output->progressFinish();
        $this->info('cate success');
    }

}
