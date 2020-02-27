<?php

namespace App\Console\Commands;

use App\Modules\Manage\Model\VipModel;
use App\Modules\User\Model\UserVipCardModel;
use Illuminate\Console\Command;
use Excel;
use File;
class MigrationVipCard extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faw:update_vip_card';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'vip次卡数据迁移';
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
        $this->excelLoad=public_path("migration/user/vipCard.xls");
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
        $this->info('data start migrating');
        Excel::load($this->excelLoad, function($reader) {
            $reader = $reader->getSheet(0);
            $data = $reader->toArray();
            $this->excelData=[];
            $this->excelData=$data;
        },$fileType);
        unset($this->excelData[0]);
        $vipCardData=[];
        //获取青铜竞标金额
        $bronzeAmo=VipModel::leftJoin("vip_config","vip.vipconfigid","=","vip_config.id")
            ->where("vip.grade",2)->pluck("jb_price");
        //获取白银竞标金额
        $silverAmo=VipModel::leftJoin("vip_config","vip.vipconfigid","=","vip_config.id")
            ->where("vip.grade",3)->pluck("jb_price");
        foreach($this->excelData as $dv){
            //青铜次卡
            $vipCardData[]=[
                'uid'=>$dv[0],
                'level'=>2,
                'do_use'=>$dv[2],
                'has_use'=>$dv[3],
                'surplus_use'=>$dv[2]-$dv[3] <0?0:$dv[2]-$dv[3],
                'max_price'=>$bronzeAmo,
                'card_name'=>"青铜会员次卡",
                'created_at'=>date("Y-m-d H:i:s"),
            ];
            //白银次卡
            $vipCardData[]=[
                'uid'=>$dv[0],
                'level'=>3,
                'do_use'=>$dv[4],
                'has_use'=>$dv[5],
                'surplus_use'=>$dv[4]-$dv[5]<0?0:$dv[4]-$dv[5],
                'max_price'=>$silverAmo,
                'card_name'=>"白银会员次卡",
                'created_at'=>date("Y-m-d H:i:s"),
            ];
        }
        //清除数据表
        UserVipCardModel::whereRaw('1=1')->delete();
        $this->output->progressStart(ceil(count($vipCardData)/1000));
        for($i = 0;$i < ceil(count($vipCardData)/1000); $i++){
            $this->info('vip card  is migrating');
            $this->output->progressAdvance();
            if(!empty(array_slice($vipCardData,$i*1000,1000))){
                UserVipCardModel::insert(array_slice($vipCardData,$i*1000,1000));
            }
        }
        $this->output->progressFinish();
        $this->info('vip card success');
    }
}
