<?php
namespace App\Modules\Pay\Http\Controllers;

use App\Http\Controllers\UserCenterController;
use App\Http\Requests;
use App\Modules\Pay\OrderModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Input;
use Omnipay;

class AlipayController extends UserCenterController
{
    //支付宝支付页面
    public function getAlipay()
    {
        $objOminipay = Omnipay::gateway('alipay');
        $siteUrl = \CommonClass::getConfig('site_url');
        $objOminipay->setReturnUrl($siteUrl . '/pay/alipay/return');
        $objOminipay->setNotifyUrl($siteUrl . '/pay/alipay/notify');

        $response = Omnipay::purchase([
            'out_trade_no' => OrderModel::randomCode(), //your site trade no, unique
            'subject' => \CommonClass::getConfig('site_name'), //order title
            'total_fee' => '0.01', //order total fee
        ])->send();

        $response->redirect();
    }

    //支付宝页面跳转同步通知页面
    public function result()
    {
        $gateway = Omnipay::gateway('alipay');

        $options = [
            'request_params' => $_REQUEST,
        ];

        $response = $gateway->completePurchase($options)->send();

        if ($response->isSuccessful() && $response->isTradeStatusOk()) {
            //支付成功后操作
            exit('支付成功');
        } else {
            //支付失败通知.
            exit('支付失败');
        }

    }

    //支付宝服务器异步通知页面
    public function notify()
    {
        $gateway = Omnipay::gateway('alipay');

        $options = [
            'request_params' => $_REQUEST,
        ];

        $response = $gateway->completePurchase($options)->send();

        if ($response->isSuccessful() && $response->isTradeStatusOk()) {
            //支付成功后操作
            exit('支付成功');
        } else {
            //支付失败通知.
            exit('支付失败');
        }
    }


}