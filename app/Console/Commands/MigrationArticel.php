<?php

namespace App\Console\Commands;

use App\Modules\Article\Model\ArticleModel;
use Illuminate\Console\Command;
use Excel;
use File;
class MigrationArticel extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faw:update_articel';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '咨询数据迁移';
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
        $this->excelLoad=public_path("migration/article/articel.xls");
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
            5=>49,
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
           if(!empty($v[8])){
                $validation_img = \CommonClass::getimg($path,$url.$v[8]);
                $pic = \CommonClass::get_between($validation_img, "public/");
            }
            $articelData[]=[
                'id'=>$v[0],
                'title'=>$v[1],
                'cat_id'=>isset($cat_id[$v[3]])?$cat_id[$v[3]]:'42',
                //'publisher'=>$v[3],
				'author'=>$v[6],
                'summary'=>$v[4],
                'content'=>$v[11],
                'keywords'=>$v[5],
                'view_times'=>$v[13],
                'created_at'=>$v[14],
                'status'=>$v[12] ==1?1:0,
                'pic'=>$pic,
                'online_time'=>$v[2],
                'articlefrom'=>!empty($v[7])?2:1,
                'cate_id'=>isset($appliyArr[$v[9]])?$appliyArr[$v[9]]:'',
                'technology_id'=>isset($skillArr[$v[10]])?$skillArr[$v[10]]:'',
            ];
            $this->info('article count  is '.count($articelData));
        }
        $this->info('article count  is '.count($articelData));
        //清除数据表
        ArticleModel::whereRaw("1=1")->delete();
        $this->output->progressStart(ceil(count($articelData)/150));
        for($i = 0;$i < ceil(count($articelData)/150); $i++){
            $this->info('article info  is migrating');
            $this->output->progressAdvance();
            if(!empty(array_slice($articelData,$i*150,150))){
                ArticleModel::insert(array_slice($articelData,$i*150,150));
            }
        }
        $this->output->progressFinish();
        $this->info('article info success');
    }
}
