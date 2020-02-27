<?php

namespace App\Console\Commands;

use App\Modules\User\Model\AlipayAuthModel;
use App\Modules\User\Model\AuthRecordModel;
use Illuminate\Console\Command;
use Excel;
use File;
use Illuminate\Support\Facades\DB;


class FawMigrateAlipayAuth extends Command
{
    protected $signature = 'faw:update_user_alipay_auth';

    protected $excelLoad ;
    protected $excelData = array();

    protected $description = 'update user alipay auth';


    public function __construct()
    {
        parent::__construct();

        $this->excelLoad = public_path('migration/user/alipayAuth.xls');

    }

    public function handle()
    {
        //导入数据先删除已有数据
        AuthRecordModel::whereRaw('1=1')->where('auth_code','alipay')->delete();
        AlipayAuthModel::whereRaw('1=1')->delete();

        if(!file_exists($this->excelLoad)){
            $this->info('user alipay auth excel Not Found');exit;
        }
        $content = file_get_contents($this->excelLoad);
        $fileType = mb_detect_encoding($content , array('UTF-8','GBK','LATIN1','BIG5'));//获取当前文本编码格式
        $this->info('data start migrating');
        Excel::load($this->excelLoad, function($reader) {

            $reader = $reader->getSheet(0);
            $data = $reader->toArray();
            $this->excelData = [];
            $this->excelData = $data;
        },$fileType);

        unset($this->excelData[0]);
        $ResultData = [];
        foreach($this->excelData as $Ked => $Ved) {

            $status = 0;
            switch($Ved[5]){
                case 0:
                    $status = 3;
                    break;
                case 1:
                    $status = 2;
                    break;
                case 2:
                    $status = 0;
                    break;
                case 3:
                    $status = 1;
                    break;
            }
            $username = !empty($Ved[1]) ? $Ved[1] : '';
            $ResultData[] = [
                'uid'               => $Ved[0],
                'username'          => $username,
                'alipay_account'    => $Ved[2],
                'alipay_name'       => $Ved[3],
                'pay_to_user_cash'  => $Ved[4],
                'user_get_cash'     => $Ved[5] == 1 ? $Ved[4] : '',
                'status'            => $status,
                'created_at'        =>  date('Y-m-d H:i:s',strtotime($Ved[6])),
                'auth_time'         =>  date('Y-m-d H:i:s',strtotime($Ved[7])),
            ];

        }
        //dd($ResultData[0]);
        $total = count($ResultData);
        $this->output->progressStart(ceil($total/1000));
        for($i = 0;$i < ceil($total/1000); $i++){
            $this->info('user alipay auth is migrating');
            $this->output->progressAdvance();
            if(!empty(array_slice($ResultData,$i*1000,1000))){
                DB::transaction(function() use($ResultData,$i){
                    $authRecord = [];
                    foreach(array_slice($ResultData,$i*1000,1000) as $k => $v){
                        if(!empty($v)){
                            $auth = AlipayAuthModel::create($v);
                            $authRecord[] = [
                                'auth_id'   => $auth['id'],
                                'uid'       => $v['uid'],
                                'username'  => $v['username'],
                                'auth_code' => 'alipay',
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
        $this->info('user alipay auth success');
    }

}
