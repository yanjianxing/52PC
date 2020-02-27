<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Modules\Article\Model\ArticleModel;
use Excel;
use File;

class MigrationKnowledge extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faw:update_knowledge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '知识百科数据迁移';
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
		$this->excelLoad=public_path("migration/article/knowledge.xls");
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
        $articelData=[];
        //咨询分类
        $cat_id=[
            1=>42,
            2=>42,
            3=>42,
            4=>61,
        ];
        //应用领域
        $appliyArr=[
            1=>1,2=>2,3=>3,4=>4,5=>5,
            6=>6,7=>7,8=>8,10=>10,
            11=>11,12=>12,14=>14,15=>15,
            21=>17,
        ];
        //技术标签
        $skillArr=[
            23=>24,24=>28,25=>29,
            26=>30,27=>31,28=>32,29=>19,30=>20,
            31=>33,32=>34,33=>83,

        ];
        foreach($this->excelData as $v){
            $pic='';
			//下载图片
           if(!empty($v[7])){
                $validation_img = \CommonClass::getimg($path,$url.$v[7]);
                $pic = \CommonClass::get_between($validation_img, "public/");     
            }
            $articelData[]=[
                'title'=>$v[1],
                'cat_id'=>73,
				//'publisher'=>$v[3],
                'online_time'=> date("Y-m-d H:i:s",$v[2]),
                'author'=>$v[3],
				'view_times'=>$v[4],
				'summary'=>$v[5],
				'articlefrom'=>!empty($v[6])?2:1,
				'pic'=>$pic,
				'content'=>$v[8],
				'keywords'=>$v[9],
            ];
        }
        //清除数据表
        //ArticleModel::whereRaw("1=1")->delete();
        $this->output->progressStart(ceil(count($articelData)/100));
        for($i = 0;$i < ceil(count($articelData)/100); $i++){
            $this->info('knowledge info  is migrating');
            $this->output->progressAdvance();
            if(!empty(array_slice($articelData,$i*100,100))){
                ArticleModel::insert(array_slice($articelData,$i*100,100));
            }
        }
        $this->output->progressFinish();
        $this->info('knowledge info success');
    }
}
