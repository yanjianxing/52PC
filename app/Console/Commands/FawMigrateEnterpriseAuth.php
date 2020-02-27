<?php

namespace App\Console\Commands;

use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\EnterpriseAuthModel;
use Illuminate\Console\Command;
use Excel;
use File;
use Illuminate\Support\Facades\DB;


class FawMigrateEnterpriseAuth extends Command
{
    protected $signature = 'faw:update_user_enterprise_auth';

    protected $excelLoad ;
    protected $excelData = array();

    protected $description = 'update user enterprise auth';


    public function __construct()
    {
        parent::__construct();

        $this->excelLoad = public_path('migration/user/enterpriseAuth.xls');

    }

    public function handle()
    {
        //导入数据先删除已有数据
        AuthRecordModel::whereRaw('1=1')->where('auth_code','enterprise')->delete();
        EnterpriseAuthModel::whereRaw('1=1')->delete();

        if(!file_exists($this->excelLoad)){
            $this->info('user enterprise auth excel Not Found');exit;
        }
        $this->info('data start migrating');
        $content = file_get_contents($this->excelLoad);
        $fileType = mb_detect_encoding($content , array('UTF-8','GBK','LATIN1','BIG5'));//获取当前文本编码格式
        Excel::load($this->excelLoad, function($reader) {

            $reader = $reader->getSheet(0);
            $data = $reader->toArray();
            $this->excelData = [];
            $this->excelData = $data;
        },$fileType);

        unset($this->excelData[0]);
        $ResultData = [];
        $path = public_path().'/attachment/user/enterprise';
        $url = 'http://www.52solution.com/';
        foreach($this->excelData as $Ked => $Ved) {

            $attachment = '';
            if(!empty($Ved[3])){
                $attachment = \CommonClass::getimg($path,$url.$Ved[3]);
                $attachment = \CommonClass::get_between($attachment, "public/");
            }
            $username = !empty($Ved[1]) ? $Ved[1] : '';
            switch($Ved[5]){
                case 0:
                    $status = 2;
                    break;
                case 1:
                    $status = 1;
                    break;
                case 2:
                    $status = 0;
                    break;
                default:
                    $status = 0;
            }
            $ResultData[] = [
                'uid'               => $Ved[0],
                'username'          => $username,
                'company_name'      => $Ved[2],
                'business_license'  => $Ved[4],
                'status'            => $status,
                'created_at'        =>  date('Y-m-d H:i:s',strtotime($Ved[6])),
                'auth_time'         =>  $Ved[5] == 1 ? date('Y-m-d H:i:s',strtotime($Ved[6])) : '',
                'license_img'       => $attachment
            ];

        }
        $total = count($ResultData);
        $this->output->progressStart(ceil($total/1000));
        for($i = 0;$i < ceil($total/1000); $i++){
            $this->info('user enterprise auth is migrating');
            $this->output->progressAdvance();
            if(!empty(array_slice($ResultData,$i*1000,1000))){
                DB::transaction(function() use($ResultData,$i){
                    $authRecord = [];
                    foreach(array_slice($ResultData,$i*1000,1000) as $k => $v){
                        if(!empty($v)){
                            $auth = EnterpriseAuthModel::create($v);
                            $authRecord[] = [
                                'auth_id'   => $auth['id'],
                                'uid'       => $v['uid'],
                                'username'  => $v['username'],
                                'auth_code' => 'enterprise',
                                'status'    => $v['status'],
                                'auth_time' => $v['auth_time']
                            ];
                        }
                    }
                    if($authRecord){
                        AuthRecordModel::insert($authRecord);
                    }
                });

            }

        }
        $this->output->progressFinish();
        $this->info('user enterprise auth success');
    }

}
