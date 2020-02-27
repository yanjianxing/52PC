<?php

namespace App\Console\Commands;

use App\Modules\Shop\Models\ShopModel;
use App\Modules\Shop\Models\ShopTagsModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Console\Command;
use Excel;
use File;
class MigrationUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'faw:update_user';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '用户信息迁移';
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
        $this->excelLoad=public_path("migration/user/userInfo.xls");
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
        $path = public_path().'/attachment/avatar/2019';
        $url = 'http://www.52solution.com/';
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
        $userData=[];$userInfoData=[];$shopData=[];$tagShopData=[];
        $openNewArr=[
            36=>36,37=>37,38=>38,39=>39,40=>40,41=>41,42=>42,43=>43,44=>44,45=>45,
            46=>46,47=>47,48=>48,49=>49,50=>50,51=>51,52=>52,53=>53,54=>54,55=>55,
            56=>56,57=>57,58=>58,59=>59,
        ];
        $skillNewArr=[
            18=>32,35=>35
        ];
        //应用领域
        $appliyArr=[
            1=>1,2=>2,3=>3,4=>4,5=>5,
            6=>6,7=>7,8=>8,10=>10,
            11=>11,12=>12,14=>14,15=>15,
            21=>17,
        ];
        $jobLevel=[
            1=>60,2=>61,3=>62,4=>63,5=>64,6=>65,7=>66
        ];
        $function=[
            9=>67,10=>68,11=>69,12=>70,13=>71,14=>72,
            15=>73,16=>74,17=>75,18=>76,19=>77,20=>78,21=>79,
            22=>80,23=>81,24=>82
        ];
        $total = count($this->excelData);
        $this->output->progressStart(ceil($total/1000));
        foreach($this->excelData as $k=>$v){
            $userData[]=[
                'id'=>$v[0],
                'name'=>$v[1],
                'unique'=>$v[1],
                'password'=>$v[2],
                'alternate_password'=>$v[3],
                'mobile'=>$v[4],
                'email'=>$v[5],
                'salt'=>$v[24],
                'status'=>$v[10] ==3?2:1,
                'level'=>in_array($v[23],[1,2,3])?2:$v[23] ==4?3:1,
            ];
            $avatar='';
            $addr=[];
            if(!empty($v['9'])){
                $addr=explode(",",$v['9']);
            }
            //下载图片
            if(!empty($v[6])){
                $validation_img = \CommonClass::getimg($path,$url.$v[6]);
                $avatar = \CommonClass::get_between($validation_img, "public/");
            }
            //存储用户详情
            $userInfoData[]=[
                'uid'=>$v[0],
                'realname'=>$v[7],
                'sex' =>$v[8] =="男"?1:0,
                'mobile'=>$v[4],
                'nickname'=>$v[1],
                'avatar'=>$avatar,
                'province'=>isset($addr[0])?$addr[0]:'',
                'city'   =>isset($addr[1])?$addr[1]:'',
                'area' =>isset($addr[2])?$addr[2]:'',
                'introduce'=>$v[11],
                //'function'=>$v[15],有问题
                //'job_level'=>$v[14],有问题
                'deposit'=>$v[22],
                'job_level'=>isset($jobLevel[$v[14]])?$jobLevel[$v[14]]:'',
                'function'=>isset($function[$v[15]])?$function[$v[15]]:'',
                'balance' => $v[25],
            ];
            $openArr=[];
            if(!empty($v[13])){
                $openArr=explode(",",$v[13]);
                $openArr=array_filter($openArr);
                foreach($openArr as $ov){
                    if(!isset($openNewArr[$ov])){
                        continue;
                    }
                    $tagShopData[]=[
                        'cate_id'=>$ov,
                        'shop_id'=>$v[0],
                        'type'   =>3
                    ];
                }
            }
            $serviceArr=[];
            if(!empty($v[16])){
                $serviceArr=explode(",",$v[16]);
                $serviceArr=array_filter($serviceArr);
                foreach($serviceArr as $sv){
                    if(!isset($appliyArr[$sv])){
                        continue;
                    }
                    $tagShopData[]=[
                        'cate_id'=>$sv,
                        'shop_id'=>$v[0],
                        'type'   =>1
                    ];
                }
            }
            $skillArr=[];
            if(!empty($v[17])){
                $skillArr=explode(",",$v[17]);
                $skillArr=array_filter($skillArr);
                foreach($skillArr as $kv){
                    if(!isset($skillNewArr[$kv])){
                        continue;
                    }
                    $tagShopData[]=[
                        'cate_id'=>$kv,
                        'shop_id'=>$v[0],
                        'type'   =>2
                    ];
                }
            }
            //存储店铺信息
            $shopData[]=[
                'id'=>$v[0],
                'uid'=>$v[0],
                'shop_name'=>$v[1],
                'job_year'=>$v[21],
                'province'=>isset($addr[0])?$addr[0]:'',
                'city'   =>isset($addr[1])?$addr[1]:'',
                'area' =>isset($addr[2])?$addr[2]:'',
                'status'=>1,
                'created_at'=>date("Y-m-d H:i:s"),
                'service_company'=>$v[12],
                'type'=>1,
            ];
            //存储技能标签
        }
        //清除数据表
        UserModel::whereRaw('1=1')->delete();
        UserDetailModel::whereRaw('1=1')->delete();
        ShopModel::whereRaw('1=1')->delete();
        ShopTagsModel::whereRaw('1=1')->delete();
        for($i = 0;$i < ceil($total/1000); $i++){
            $this->info('user info  is migrating');
            $this->output->progressAdvance();
            if(!empty(array_slice($userData,$i*1000,1000))){
                UserModel::insert(array_slice($userData,$i*1000,1000));
                UserDetailModel::insert(array_slice($userInfoData,$i*1000,1000));
                ShopModel::insert(array_slice($shopData,$i*1000,1000));
            }
        }
        for($j=0;$j<ceil(count($tagShopData)/1000);$j++){
            if(!empty(array_slice($tagShopData,$i*1000,1000))){
                ShopTagsModel::insert(array_slice($tagShopData,$i*1000,1000));
            }
        }
        $this->output->progressFinish();
        $this->info('user info success');
    }
}
