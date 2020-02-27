<?php

namespace App\Console\Commands;

use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\RealnameAuthModel;
use Illuminate\Console\Command;
use Excel;
use File;
use Illuminate\Support\Facades\DB;


class FawMigrateRealnameAuth extends Command
{
    protected $signature = 'faw:update_user_realname_auth';

    protected $excelLoad ;
    protected $excelData = array();

    protected $description = 'update user realname auth';


    public function __construct()
    {
        parent::__construct();

        $this->excelLoad = public_path('migration/user/realnameAuth.xls');

    }

    public function handle()
    {
        //导入数据先删除已有数据
        AuthRecordModel::whereRaw('1=1')->where('auth_code','realname')->delete();
        RealnameAuthModel::whereRaw('1=1')->delete();

        if(!file_exists($this->excelLoad)){
            $this->info('user realname auth excel Not Found');exit;
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
        $path = public_path().'/attachment/user/realnameauth';
        $url = 'http://www.52solution.com/';
        foreach($this->excelData as $Ked => $Ved) {

            $validation_img = '';
            if(!empty($Ved[7])){
                $validation_img = \CommonClass::getimg($path,$url.$Ved[7]);
                $validation_img = \CommonClass::get_between($validation_img, "public/");
            }
            $card_front_side = '';
            if(!empty($Ved[8])){
                $card_front_side = \CommonClass::getimg($path,$url.$Ved[8]);
                $card_front_side = \CommonClass::get_between($card_front_side, "public/");
            }
            $card_back_dside = '';
            if(!empty($Ved[9])){
                $card_back_dside = \CommonClass::getimg($path,$url.$Ved[7]);
                $card_back_dside = \CommonClass::get_between($card_back_dside, "public/");
            }
            $endTime = '';
            if($Ved[4] && $Ved[4] != '0000-00-00 00:00:00'){
                $endTime = date('Y-m-d 00:00:00',strtotime($Ved[4]));
            }
            $username = empty($Ved[1]) ? $Ved[2] : $Ved[1];

            switch($Ved[6]){
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
                'realname'          => $Ved[2],
                'card_number'       => $Ved[3],
                'end_time'          => $endTime,
                'auth_time'         => date('Y-m-d H:i:s',strtotime($Ved[5])),
                'status'            => $status,
                'card_front_side'   => $card_front_side,
                'card_back_dside'   => $card_back_dside,
                'validation_img'    => $validation_img,
                'created_at'        =>  date('Y-m-d H:i:s',strtotime($Ved[10])),
            ];

        }
        $total = count($ResultData);
        $this->output->progressStart(ceil($total/1000));
        for($i = 0;$i < ceil($total/1000); $i++){
            $this->info('user realname auth is migrating');
            $this->output->progressAdvance();
            if(!empty(array_slice($ResultData,$i*1000,1000))){
                DB::transaction(function() use($ResultData,$i){
                    $authRecord = [];
                    foreach(array_slice($ResultData,$i*1000,1000) as $k => $v){
                        $realname = RealnameAuthModel::create($v);
                        $authRecord[] = [
                            'auth_id'   => $realname['id'],
                            'uid'       => $realname['uid'],
                            'username'  => $realname['username'],
                            'auth_code' => 'realname',
                            'status'    => $realname['status'],
                            'auth_time' => $realname['auth_time']
                        ];

                    }
                    if($authRecord){
                        AuthRecordModel::insert($authRecord);
                    }
                });
            }

        }
        $this->output->progressFinish();
        $this->info('user realname auth success');
    }

}
