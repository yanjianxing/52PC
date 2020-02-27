<?php

namespace App\Console\Commands;

use App\Modules\Manage\Model\VipModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\User\Model\UserVipConfigModel;
use Illuminate\Console\Command;
use Excel;
use File;
class MigrationVipPay extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faw:update_vip_pay';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'vip购买';
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
        $this->excelLoad=public_path("migration/user/vipPay.xls");
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
        //获取青铜信息
        $bronzeInfo=VipModel::leftJoin("vip_config","vip.vipconfigid","=","vip_config.id")
            ->where("vip.grade",2)->select("vip_config.*")->first();
        $bronzeInfo['facs_mobile']=$bronzeInfo['facs_mobile']==1?1:0;
        $bronzeInfo['facs_yaoqingjb']=$bronzeInfo['facs_yaoqingjb']==1?1:0;
        $bronzeInfo['facs_logo']=$bronzeInfo['facs_logo']==1?1:0;
        $bronzeInfo['facs_daohang']=$bronzeInfo['facs_daohang']==1?1:0;
        $bronzeInfo['facs_slide']=$bronzeInfo['facs_slide']==1?1:0;
        //获取白银信息
        $silverInfo=VipModel::leftJoin("vip_config","vip.vipconfigid","=","vip_config.id")
            ->where("vip.grade",3)->select("vip_config.*")->first();
        $silverInfo['facs_mobile']=$silverInfo['facs_mobile']==1?1:0;
        $silverInfo['facs_yaoqingjb']=$silverInfo['facs_yaoqingjb']==1?1:0;
        $silverInfo['facs_logo']=$silverInfo['facs_logo']==1?1:0;
        $silverInfo['facs_daohang']=$silverInfo['facs_daohang']==1?1:0;
        $silverInfo['facs_slide']=$silverInfo['facs_slide']==1?1:0;
        //获取王者信息 没有王者信息 暂时用铂金会员代替
        $kingInfo=VipModel::leftJoin("vip_config","vip.vipconfigid","=","vip_config.id")
            ->where("vip.grade",5)->select("vip_config.*")->first();
        $kingInfo['facs_mobile']=$kingInfo['facs_mobile']==1?1:0;
        $kingInfo['facs_yaoqingjb']=$kingInfo['facs_yaoqingjb']==1?1:0;
        $kingInfo['facs_logo']=$kingInfo['facs_logo']==1?1:0;
        $kingInfo['facs_daohang']=$kingInfo['facs_daohang']==1?1:0;
        $kingInfo['facs_slide']=$kingInfo['facs_slide']==1?1:0;
        //用户配置信息
        $userVipConfigData=[];
        //vip购买信息
        $userVipOrderData=[];
        foreach($this->excelData as $k=>$v){
            $userVipConfigData[]=[
                     'uid'=>$v[0],
                      'bid_num'=>in_array($v[3],[1,2,3])?$bronzeInfo['jb_times']:$v[3]==4?$silverInfo['jb_times']:$kingInfo['jb_times'],
                      'bid_price'=>in_array($v[3],[1,2,3])?$bronzeInfo['jb_price']:$v[3]==4?$silverInfo['jb_price']:$kingInfo['jb_price'],
                      'skill_num'=>in_array($v[3],[1,2,3])?$bronzeInfo['facs_technology_num']:$v[3]==4?$silverInfo['facs_technology_num']:$kingInfo['facs_technology_num'],
                      'appliy_num'=>in_array($v[3],[1,2,3])?$bronzeInfo['facs_technology_num']:$v[3]==4?$silverInfo['facs_technology_num']:$kingInfo['facs_technology_num'],
                      'inquiry_num'=>in_array($v[3],[1,2,3])?$bronzeInfo['facs_start_xunjia']:$v[3]==4?$silverInfo['facs_start_xunjia']:$kingInfo['facs_start_xunjia'],
                      //'scheme_num'=>in_array($v[3],[1,2,3])?$bronzeInfo['jb_times']:$v[3]==4?$silverInfo['jb_times']:$silverInfo['jb_times'],
                      'stick_discount'=>in_array($v[3],[1,2,3])?$bronzeInfo['appreciation_zhiding']:$v[3]==4?$silverInfo['appreciation_zhiding']:$kingInfo['appreciation_zhiding'],
                      'urgent_discount'=>in_array($v[3],[1,2,3])?$bronzeInfo['appreciation_jiaji']:$v[3]==4?$silverInfo['appreciation_jiaji']:$kingInfo['appreciation_jiaji'],
                      'private_discount'=>in_array($v[3],[1,2,3])?$bronzeInfo['appreciation_duijie']:$v[3]==4?$silverInfo['appreciation_duijie']:$kingInfo['appreciation_duijie'],
                      'train_discount'=>in_array($v[3],[1,2,3])?$bronzeInfo['appreciation_zhitongche']:$v[3]==4?$silverInfo['appreciation_zhitongche']:$kingInfo['appreciation_zhitongche'],
                      'consult_discount'=>in_array($v[3],[1,2,3])?$bronzeInfo['appreciation_zixun']:$v[3]==4?$silverInfo['appreciation_zixun']:$kingInfo['appreciation_zixun'],
                      'level'=>in_array($v[3],[1,2,3])?2:$v[3]==4?3:2,
                      'is_show'=>in_array($v[3],[1,2,3])?$bronzeInfo['facs_mobile']:$v[3]==4?$silverInfo['facs_mobile']:$kingInfo['facs_mobile'],
                      'is_Invited'=>in_array($v[3],[1,2,3])?$bronzeInfo['facs_yaoqingjb']:$v[3]==4?$silverInfo['facs_yaoqingjb']:$kingInfo['facs_yaoqingjb'],
                       'is_logo'=>in_array($v[3],[1,2,3])?$bronzeInfo['facs_logo']:$v[3]==4?$silverInfo['facs_logo']:$kingInfo['facs_logo'],
                       'is_nav'=>in_array($v[3],[1,2,3])?$bronzeInfo['facs_daohang']:$v[3]==4?$silverInfo['facs_daohang']:$kingInfo['facs_daohang'],
                       'is_slide'=>in_array($v[3],[1,2,3])?$bronzeInfo['facs_slide']:$v[3]==4?$silverInfo['facs_slide']:$kingInfo['facs_slide'],
            ];
            $userVipOrderData[]=[
                'uid'=>$v[0],
                'pay_time'=>$v[1],
                'end_time'=>$v[2],
                'created_at'=>$v[1],
                'level'=>in_array($v[3],[1,2,3])?2:$v[3]==4?3:2,
                'num'=>$v[5],
                'status'=>1,
                'vipid'=>in_array($v[3],[1,2,3])?1:$v[3]==4?2:1,
                'price'=>in_array($v[3],[1,2,3])?499:$v[3]==4?6999:499,
            ];
        }
        //清除数据表
        UserVipConfigModel::whereRaw('1=1')->delete();
        VipUserOrderModel::whereRaw('1=1')->delete();
        $this->output->progressStart(ceil(count($userVipConfigData)/1000));
        for($i = 0;$i < ceil(count($userVipConfigData)/1000); $i++){
            $this->info('vip pay  is migrating');
            $this->output->progressAdvance();
            if(!empty(array_slice($userVipConfigData,$i*1000,1000))){
                UserVipConfigModel::insert(array_slice($userVipConfigData,$i*1000,1000));
                VipUserOrderModel::insert($userVipOrderData);
            }
        }
        $this->output->progressFinish();
        $this->info('vip pay success');
    }
}
