<?php

namespace App\Console\Commands;

use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Order\Model\ShopOrderModel;
use Illuminate\Console\Command;

class BuyGoods extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'BuyGoods';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 购买商品N天后没有确认源文件自动确认源文件
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //查询所有处于已支付的商品
        $goodsOrder = ShopOrderModel::where('object_type',2)->where('status',1)->get()->toArray();
        $expiredGoodsOrder = self::expireBuyGoods($goodsOrder);
        if(!empty($expiredGoodsOrder)){
            foreach($expiredGoodsOrder as $k => $v){
                //修改订单状态为确认源文件
                ShopOrderModel::confirmGoods($v['id'],$v['uid']);
            }
        }
    }


    /**
     * 超出系统配置的自动确认源文件时间商品订单
     * @param $goodsOrder
     * @return array
     */
    private function expireBuyGoods($goodsOrder)
    {
        //查询系统配置的自动确认源文件的时间限制
        $docConfirmArr = ConfigModel::getConfigByAlias('doc_confirm');
        if(!empty($docConfirmArr)){
            $docConfirm = intval($docConfirmArr->rule);
        }else{
            $docConfirm = 7;
        }
        $limitTime = $docConfirm*24*60*60;
        $expireGoodsOrder = array();
        if(!empty($goodsOrder)){
            foreach($goodsOrder as $k => $v){
                //判断当前商品订单是否超过确认源文件时间
                if((strtotime($v['pay_time'])+$limitTime)<= time()){
                    $expireGoodsOrder[] = $v;
                }
            }
        }
        return $expireGoodsOrder;

    }
}
