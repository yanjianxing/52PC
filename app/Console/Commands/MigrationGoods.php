<?php

namespace App\Console\Commands;

use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\AttachmentModel;
use Illuminate\Console\Command;
use Excel;
use File;
class MigrationGoods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faw:update_goods';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '方案超市迁移';
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
        $this->excelLoad=public_path("migration/goods/goods.xls");
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
        $path = public_path().'/attachment/task/2019';
        $url = 'http://www.52solution.com/';
        //方案分类
        $goodClassify=[35,36,41];
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
        $this->info('data start migrating');
        Excel::load($this->excelLoad, function($reader) {
            $reader = $reader->getSheet(0);
            $data = $reader->toArray();
            $this->excelData=[];
            $this->excelData=$data;
        },$fileType);
        unset($this->excelData[0]);
        $goodData=[];
        foreach($this->excelData as $k=>$v){
            if(empty($v[13])){
                continue;
            }
            $cover='';
			//下载图片
            if(!empty($v[9])){
                $validation_img = \CommonClass::getimg($path,$url.$v[9]);
                $validation_img = \CommonClass::get_between($validation_img, "public/");
                $cover=AttachmentModel::insertGetId([
                    'url'=>$validation_img,
                ]);
            }
            //获取应用领域
            $ideCateId='';
            if(!empty($v[2])){
                $ideCateId=TaskCateModel::where("name",$v[2])->where("type",3)->pluck("id");
            }
            //标签没有完成
            $goodData[]=[
              'id'=>$v[0],
              'uid'=>$v[8],
              'shop_id'=>$v[8],
              'title'=>$v[1],
              'desc'=>$v[10],
              'cash'=>$v[3],
              'status'=>$v[14] ==1?0:$v[14] ==2?1:3,
              'view_num'=>$v[15],
              'type'=>in_array($v[13],$goodClassify)?1:2,
              'cover'=>$cover,
              'created_at'=>$v[16],
              'cate_id'=>isset($appliyArr[$v[11]])?$appliyArr[$v[11]]:'',
              'skill_id'=>isset($skillArr[$v[12]])?$skillArr[$v[12]]:'',
              'ide_cate_id'=>$ideCateId,
            ];
        }
        //清除数据表
        GoodsModel::whereRaw("1=1")->delete();
        $this->output->progressStart(ceil(count($goodData)/100));
        for($i = 0;$i < ceil(count($goodData)/100); $i++){
            $this->info('goods info  is migrating');
            $this->output->progressAdvance();
            if(!empty(array_slice($goodData,$i*100,100))){
                GoodsModel::insert(array_slice($goodData,$i*100,100));
            }
        }
        $this->output->progressFinish();
        $this->info('goods info success');
    }
}
