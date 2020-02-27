<?php
namespace App\Modules\Pay\Http\Controllers;

use App\Http\Controllers\UserCenterController;
use App\Modules\Pay\OrderModel;
use Omnipay;
use Theme;
use QrCode;

class WechatpayController extends UserCenterController
{

    /**
     * 处理微信的请求消息
     *
     * @return string
     */
    public function getWechatpay()
    {
        $wechat = Omnipay::gateway('wechat');
        $out_trade_no = OrderModel::randomCode();
        $params = array(
            'out_trade_no' => $out_trade_no, // billing id in your system
            'notify_url' => \CommonClass::getDomain() . '/pay/wechatpay/notify?out_trade_no=' . $out_trade_no, // URL for asynchronous notify
            'body' => '123', // A simple description
            'total_fee' => 0.01, // Amount with less than 2 decimals places
            'fee_type' => 'CNY', // Currency name from ISO4217, Optional, default as CNY
        );

        $response = $wechat->purchase($params)->send();

        $img = QrCode::size('200')->generate($response->getRedirectUrl());

        $theme = Theme::uses('default')->layout('usercenter');

        $view = array(
            'img' => $img
        );

        return $theme->scope('pay.wechatpay', $view)->render();
    }

    //微信回调
    public function notify()
    {
        //获取微信回调参数
        $arrNotify = \CommonClass::xmlToArray($GLOBALS['HTTP_RAW_POST_DATA']);

        $content = '<xml>
                    <return_code><![CDATA[SUCCESS]]></return_code>
                    <return_msg><![CDATA[OK]]></return_msg>
                    </xml>';

        if ($arrNotify['result_code'] == 'SUCCESS' && $arrNotify['return_code'] = 'SUCCESS') {

            /**
             * 此处处理订单业务逻辑
             */

            //回复微信端请求成功
            return response($content)->header('Content-Type', 'text/xml');
        }


    }

    /**
     * 查询订单状态
     *
     * @param $out_trade_no
     */
    public function queryOrder($out_trade_no)
    {
        $wechat = Omnipay::gateway('wechat');
        $params = array(
            'out_trade_no' => $out_trade_no, // billing id in your system
            //or you can use 'transaction_id', the trade number from WeChat
        );

        $response = $wechat->completePurchase($params)->send();

        if ($response->isSuccessful() && $response->isTradeStatusOk()) {
            $responseData = $response->getData();
            // Do something here
        }
    }
}
