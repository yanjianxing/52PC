<?php

namespace App\Console\Commands;

use App\Modules\Manage\Model\SpecialModel;
use Illuminate\Console\Command;
use Excel;
use File;
class MigrationSpecial extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faw:update_special';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '专题报道数据迁移';
    protected $excelLoad;
    protected $excelData = array();

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->excelLoad=public_path("migration/article/special.xls");
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        header('Content-Type:text/html;Charset=utf-8;');
        //判断文件是否存在
        if(!file_exists($this->excelLoad)){
            $this->info('file Not Found');exit;
        }
        $content = file_get_contents($this->excelLoad);
        $fileType = mb_detect_encoding($content , array('UTF-8','GBK','LATIN1','BIG5'));//获取当前文本编码格式
        $path = public_path().'/attachment/sys/2019';
        $url = 'http://www.52solution.com/';
        $this->info('data start migrating');
        Excel::load($this->excelLoad, function($reader) {
            $reader = $reader->getSheet(0);
            $data = $reader->toArray();
            $this->excelData=[];
            $this->excelData=$data;
        },$fileType);
        unset($this->excelData[0]);
        $specialData=[];
        foreach($this->excelData as $v){
            $logo='';
			//下载图片
            if(!empty($v[6])){
               $validation_img = \CommonClass::getimg($path,$url.$v[6]);
                $logo = \CommonClass::get_between($validation_img, "public/");
            }
            $specialData[]=[
                'title'=>$v[1],
                'logo'=>$logo,
                'introduction'=>$v[3],
                'created_at'=>$v[9],
                'status'=>1,
                'view_times'=>$v[8],
                'edit_by'=>$v[5],
                'desc'=>$v[7],
            ];
        }
        //清除数据表
        SpecialModel::whereRaw("1=1")->delete();
        $this->output->progressStart(ceil(count($specialData)/100));
        for($i = 0;$i < ceil(count($specialData)/100); $i++){
            $this->info('article info  is migrating');
            $this->output->progressAdvance();
            if(!empty(array_slice($specialData,$i*100,100))){
                SpecialModel::insert(array_slice($specialData,$i*100,100));
            }
        }
        $this->output->progressFinish();
        $this->info('article info success');
    }
}
