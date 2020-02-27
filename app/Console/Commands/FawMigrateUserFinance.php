<?php

namespace App\Console\Commands;

use App\Modules\Finance\Model\FinancialModel;
use Illuminate\Console\Command;
use Excel;
use File;


class FawMigrateUserFinance extends Command
{
    protected $signature = 'faw:update_user_finance';

    protected $excelLoad ;
    protected $excelData = array();

    protected $description = 'update user finance flow';


    public function __construct()
    {
        parent::__construct();

        $this->excelLoad = public_path('migration/financial/userFinance.xls');

    }

    public function handle()
    {
        //导入数据先删除已有数据
        //FinancialModel::whereRaw('1=1')->delete();

        if(!file_exists($this->excelLoad)){
            $this->info('user finance flow excel Not Found');exit;
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
        //收支行为(1 项目交易 2 方案交易 3 充值 4 提现 5 购买增值服务 6  购买工具 7 添加资讯 8 询价 9 缴纳保证金 10 维权退款  11 推广赏金 12 购买会员 13:直通车退款 14：保证金退款15：资讯退款)
        $arr = [
            'CITE参展增值服务'    => 16,
            'vip会员支付'   => 12,
            'wapvip会员支付'    => 12,
            'WAP快包自营'   => 2,
            'wap方案超市询价' => 8,
            'wap申请任务支付' => 17,
            'wap端余额充值'   => 3,
            '余额充值'  => 3,
            '余额提现'  => 4,
            '保证金'   => 14,
            '增加保证金' => 14,
            '开通任务通服务'   => 18,
            '快包自产'  => 2,
            '提现'    => 4,
            '提现失败返还' => 19,
            '支付诚意金 ' => 5,
            '支付项目定金' => 5,
            '方案超市询价'   => 8,
            '申请任务支付'   => 17,
            '结案收取任务金额' => 1,
            '网站手动充值'    => 20,
            '网站手动扣除'    => 21,
            '诚意金退还' => 22,
            '购买竞标卡'     => 12,
            '雇主托管任务金额' => 1,
        ];
        unset($this->excelData[0]);
        $ResultData = [];
        foreach($this->excelData as $Ked=>$Ved) {
            $action = $arr[$Ved[3]];
            $status = $Ved[1] == 1 ? 2 : 1;
            $ResultData[] = [
                'action'     => $action,
                'cash'       => $Ved[2],
                'uid'        => $Ved[0],
                'created_at' => date('Y-m-d H:i:s',strtotime($Ved[5])),
                'status'     => $status,
                'remainder'  => $Ved[4]
            ];
        }
        $total = count($ResultData);
        $this->output->progressStart(ceil($total/1000));
        for($i = 0;$i < ceil($total/1000); $i++){
            $this->info('user finance flow is migrating');
            $this->output->progressAdvance();
            FinancialModel::insert(array_slice($ResultData,$i*1000,1000),true);
        }
        $this->output->progressFinish();
        $this->info('user finance flow success');
    }

}
