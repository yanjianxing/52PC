<?php

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\ApiBaseController;
use App\Http\Requests;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Order\Model\ShopOrderModel;
use App\Modules\Pay\OrderModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskTypeModel;
use App\Modules\User\Model\UserDetailModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use Omnipay;

class PayNotifyController extends ApiBaseController
{
    /**
     * 支付宝充值异步回调
     *
     * @return string
     */
    public function alipayNotify(Request $request)
    {
        Log::info('支付宝回调');

        $config = ConfigModel::getConfigByAlias('app_alipay');
        $info = [];
        if($config && !empty($config['rule'])){
            $info = json_decode($config['rule'],true);
        }
        if(!isset($info['alipay_type']) || (isset($info['alipay_type']) && $info['alipay_type']== 1)) {
            $alipay = app('alipay.mobile');
            if(!empty($info) && isset($info['partner_id'])){
                $alipay->setPartner($info['partner_id']);
            }
            if(!empty($info) && isset($info['seller_id'])){
                $alipay->setSellerId($info['seller_id']);
            }
            $flag = $alipay->verify();
            $data = [
                'pay_account' => Input::get('buy_email'),
                'code' => Input::get('out_trade_no'),
                'pay_code' => Input::get('trade_no'),
                'money' => Input::get('total_fee')
            ];
        }else{
            $aop = new \AopClient;
            //$public_path = "key/rsa_public_key.pem";//公钥路径
            $alipayrsaPublicKey = storage_path('app/alipay/rsa_public_key.pem');
            $aop->alipayPublicKey = storage_path('app/alipay/rsa_public_key.pem');
            $appId = '';
            if(!empty($info) && isset($info['appId'])){
                $appId = $info['appId'];
            }
            $aop->appId  = $appId;
            //此处验签方式必须与下单时的签名方式一致
            $flag = $aop->rsaCheckV1($request->all(), $alipayrsaPublicKey, "RSA");
            $data = array(
                'pay_account' => $request->get('buyer_email'),//支付账号
                'code' => $request->get('out_trade_no'),//订单编号
                'pay_code' => $request->get('trade_no'),//支付宝订单号
                'money' => $request->get('total_amount'),//支付金额
            );
        }

        if($flag){
            Log::info('支付宝回调,验证通过');
            $type = ShopOrderModel::handleOrderCode($data['code']);

            //回调逻辑
            $this->aliPayNotifyHandle($type,$data);

            return $this->formateResponse(2023, '订单信息错误');

        } else {
            //支付失败通知.
            //exit('支付失败');
            return $this->formateResponse(2023, '订单信息错误');
        }

            /* $public_path = 'MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQDDI6d306Q8fIfCOaTXyiUeJHkr
     IvYISRcc73s3vF1ZT7XN8RNPwJxo8pWaJMmvyTn9N4HQ632qJBVHf8sxHi/fEsra
     prwCtzvzQETrNRwVxLO5jVmRGi60j8Ue1efIlzPXV9je9mkjzOmdssymZkh2QhUr
     CmZYI/FCEa3/cNMW0QIDAQAB';
             $aop = new \AopClient;
             //$public_path = "key/rsa_public_key.pem";//公钥路径
             $alipayrsaPublicKey = storage_path('app/alipay/rsa_public_key.pem');
             $aop->alipayPublicKey = storage_path('app/alipay/rsa_public_key.pem');
             $configInfo = ConfigModel::getConfigByAlias('app_alipay');
             $info = [];
             if($configInfo && !empty($configInfo['rule'])){
                 $info = json_decode($configInfo['rule'],true);
             }
             Log::info('支付宝支付回调'.var_export ( $request->all(), 1 ) );
             $aop->appId  = '2017121700929928';
             //此处验签方式必须与下单时的签名方式一致
             $flag = $aop->rsaCheckV1($request->all(), $alipayrsaPublicKey, "RSA");*/

        /*$gateway = Omnipay::gateway('alipayMobile');

        $configInfo = ConfigModel::getConfigByAlias('app_alipay');
        $info = [];
        if($configInfo && !empty($configInfo['rule'])){
            $info = json_decode($configInfo['rule'],true);
        }
        $gateway->setPartner($info['partner_id']);
        $gateway->setKey($info['key']);
        $gateway->setSellerEmail($info['seller_id']);

        $gateway->setNotifyUrl(url('api/alipay/notify'));
        $options = [
            'request_params' => $_REQUEST,
        ];

        $response = $gateway->completePurchase($options)->send();

        if ($response->isSuccessful() && $response->isTradeStatusOk()) {
        //if($flag){
            Log::info('支付宝回调,验证通过');
            $data = array(
                'pay_account' => $request->get('buyer_email'),//支付账号
                'code' => $request->get('out_trade_no'),//订单编号
                'pay_code' => $request->get('trade_no'),//支付宝订单号
                'money' => $request->get('total_amount'),//支付金额
            );

            $type = ShopOrderModel::handleOrderCode($data['code']);

            $this->aliPayNotifyHandle($type,$data);

            return $this->formateResponse(2023, '订单信息错误');

        } else {
            //支付失败通知.
            //exit('支付失败');
            return $this->formateResponse(2023, '订单信息错误');
        }

        /*$alipay = app('alipay.mobile');
        $config = ConfigModel::getConfigByAlias('app_alipay');
        $info = [];
        if($config && !empty($config['rule'])){
            $info = json_decode($config['rule'],true);
        }
        if(!empty($info) && isset($info['partner_id'])){
            $alipay->setPartner($info['partner_id']);
        }
        if(!empty($info) && isset($info['seller_id'])){
            $alipay->setSellerId($info['seller_id']);
        }
        if ($alipay->verify()) {
            $data = [
                'pay_account' => Input::get('buy_email'),
                'code' => Input::get('out_trade_no'),
                'pay_code' => Input::get('trade_no'),
                'money' => Input::get('total_fee')
            ];

            $type = ShopOrderModel::handleOrderCode($data['code']);
            // 判断通知类型。
            switch (Input::get('trade_status')) {
                case 'TRADE_SUCCESS':
                case 'TRADE_FINISHED':
                switch($type){
                    case 'cash':
                        $orderInfo = OrderModel::where('code', $data['code'])->first();
                        if (!empty($orderInfo) && $orderInfo['status'] == 0 && empty($orderInfo->task_id)) {
                            $result = UserDetailModel::recharge($orderInfo->uid, 2, $data);
                            if (!$result) {
                                return $this->formateResponse(2022, '支付失败');
                            }
                            echo 'success';
                        }
                        break;
                    case 'pub task':
                        Log::info('支付宝回调,订单编号'.$data['code']);
                        $orderInfo = OrderModel::where('code', $data['code'])->first();
                        if (!empty($orderInfo) && $orderInfo['status'] == 0 && $orderInfo->task_id) {
                            $uid = $orderInfo->uid;
                            $money = $data['money'];
                            $task_id = $orderInfo->task_id;
                            $code = $data['code'];
                            Log::info('支付宝回调,订单编号'.$data['code'].'金额'.$money.'任务id'.$task_id);
                            $result1 = UserDetailModel::recharge($uid, 2, $data);
                            if (!$result1) {
                                echo '支付失败！';
                            }
                            Log::info('支付宝回调,订单编号'.$data['code'].'充值成功');
                            $task = TaskModel::find($orderInfo->task_id);
                            $taskTypeAlias = TaskTypeModel::getTaskTypeAliasById($task['type_id']);
                            switch($taskTypeAlias){
                                case 'xuanshang':
                                    $result = TaskModel::bounty($money, $task_id, $uid, $code, 2);
                                    break;
                                case 'zhaobiao':
                                    $result = TaskModel::bidBounty($money, $task_id, $uid, $code, 2);
                                    break;
                            }

                            if (isset($result) && !$result) {
                                return $this->formateResponse(2022, '支付失败');
                            }
                            Log::info('支付宝回调,订单编号'.$data['code'].'扣款成功');
                            echo 'success';
                        }
                        break;
                    case 'pub goods':
                        break;
                    case 'employ':
                        $order = ShopOrderModel::where('code', $data['code'])->first();
                        if (!empty($order) && $order['status'] == 0) {
                            //给用户充值
                            $result = UserDetailModel::recharge($order['uid'], 2, $data);
                            if (!$result) {
                                echo '支付失败！';
                            }
                            $result2 = EmployModel::employBounty($data['money'], $order['object_id'], $order['uid'], $data['code'],2);
                            if ($result2) {
                                echo('支付成功');
                            }
                            echo 'success';
                        }
                        break;
                    case 'pub service':
                        break;
                    case 'buy goods':
                        $data['pay_type'] = 2;
                        $res = ShopOrderModel::where(['code'=>$data['code'],'status'=>0,'object_type' => 2])->first();
                        if (!empty($res)){
                            $status = ShopOrderModel::thirdBuyGoods($res->code, $data);
                            if ($status) {
                                //查询商品数据
                                $goodsInfo = GoodsModel::where('id',$res->object_id)->first();
                                //修改商品销量
                                $salesNum = intval($goodsInfo->sales_num + 1);
                                GoodsModel::where('id',$goodsInfo->id)->update(['sales_num' => $salesNum]);
                                echo '支付成功';
                            }
                            echo 'success';
                        }
                        break;
                    case 'buy service':
                        break;
                    case 'buy shop service':
                        break;
                    case 'vipshop':
                        break;
                    case 'task service':
                        //查询未支付的订单
                        $waitHandle = OrderModel::where('code', $data['code'])->first();
                        if (!empty($waitHandle)){
                            switch ($waitHandle->status){
                                case 0:
                                    //给用户充值
                                    $res = UserDetailModel::recharge($waitHandle['uid'], 2, $data);
                                    if (!$res) {
                                        echo '支付失败！';
                                    }
                                    //余额支付
                                    $status = TaskModel::buyServiceTaskBid($waitHandle->cash, $waitHandle->task_id, $waitHandle->uid, $data['code'], 2);

                                    $result = is_null($status) ? true : false;
                                    break;
                                case 1:
                                    $result = true;
                                    break;
                                default:
                                    $result = true;
                                    break;
                            }
                            if ($result){
                                echo '支付成功';
                            }
                            echo '支付失败';
                        }
                        break;
                }

                    return $this->formateResponse(2023, '订单信息错误');
                    break;
            }

            return $this->formateResponse(2023, '支付失败');
        }*/
    }

    /**
     * 微信异步回调
     * @return mixed
     */
    public function wechatpayNotify(Request $request)
    {

        Log::info('微信支付回调开始');

        $gateway = Omnipay::gateway('WechatPay');
        $configInfo = ConfigModel::getConfigByAlias('app_wechat');
        $config = [];
        if($configInfo && !empty($configInfo['rule'])){
            $config = json_decode($configInfo['rule'],true);
        }
        if(isset($config['appId'])){
            $gateway->setAppId($config['appId']);
        }
        if(isset($config['mchId'])){
            $gateway->setMchId($config['mchId']);
        }
        if(isset($config['apiKey'])){
            $gateway->setApiKey($config['apiKey']);
        }
        $response = $gateway->completePurchase([
            'request_params' => file_get_contents('php://input')
        ])->send();

        if ($response->isPaid()) {
            $result = \CommonClass::xmlToArray($GLOBALS['HTTP_RAW_POST_DATA']);
            $data = [
                'pay_account' => $result['openid'],
                'code' => $result['out_trade_no'],
                'pay_code' => $result['transaction_id'],
                'money' => $result['total_fee']
            ];

            $type = ShopOrderModel::handleOrderCode($data['code']);
            Log::info('微信支付回调,订单编号'.$data['code']);
            $this->wechatNotifyHandle($type,$data);

        } else {
            //pay fail
        }
    }


    public function aliPayNotifyHandle($type, $data)
    {
        switch($type){
            case 'cash':
                $orderInfo = OrderModel::where('code', $data['code'])->first();
                if (!empty($orderInfo) && $orderInfo['status'] == 0 && empty($orderInfo->task_id)) {
                    $result = UserDetailModel::recharge($orderInfo->uid, 2, $data);
                    if (!$result) {
                        return $this->formateResponse(2022, '支付失败');
                    }
                    echo 'success';
                }
                break;
            case 'pub task':
                Log::info('支付宝回调,订单编号'.$data['code']);
                $orderInfo = OrderModel::where('code', $data['code'])->first();
                if (!empty($orderInfo) && $orderInfo['status'] == 0 && $orderInfo->task_id) {
                    $uid = $orderInfo->uid;
                    $money = $data['money'];
                    $task_id = $orderInfo->task_id;
                    $code = $data['code'];
                    Log::info('支付宝回调,订单编号'.$data['code'].'金额'.$money.'任务id'.$task_id);
                    $result1 = UserDetailModel::recharge($uid, 2, $data);
                    if (!$result1) {
                        echo '支付失败！';
                    }
                    Log::info('支付宝回调,订单编号'.$data['code'].'充值成功');
                    $task = TaskModel::find($orderInfo->task_id);
                    $taskTypeAlias = TaskTypeModel::getTaskTypeAliasById($task['type_id']);
                    switch($taskTypeAlias){
                        case 'xuanshang':
                            $result = TaskModel::bounty($money, $task_id, $uid, $code, 2);
                            break;
                        case 'zhaobiao':
                            $result = TaskModel::bidBounty($money, $task_id, $uid, $code, 2);
                            break;
                    }

                    if (isset($result) && !$result) {
                        return $this->formateResponse(2022, '支付失败');
                    }
                    Log::info('支付宝回调,订单编号'.$data['code'].'扣款成功');
                    echo 'success';
                }
                break;
            case 'pub goods':
                break;
            case 'employ':
                $order = ShopOrderModel::where('code', $data['code'])->first();
                if (!empty($order) && $order['status'] == 0) {
                    //给用户充值
                    $result = UserDetailModel::recharge($order['uid'], 2, $data);
                    if (!$result) {
                        echo '支付失败！';
                    }
                    $result2 = EmployModel::employBounty($data['money'], $order['object_id'], $order['uid'], $data['code'],2);
                    if ($result2) {
                        echo('支付成功');
                    }
                    echo 'success';
                }
                break;
            case 'pub service':
                break;
            case 'buy goods':
                $data['pay_type'] = 2;
                $res = ShopOrderModel::where(['code'=>$data['code'],'status'=>0,'object_type' => 2])->first();
                if (!empty($res)){
                    $status = ShopOrderModel::thirdBuyGoods($res->code, $data);
                    if ($status) {
                        //查询商品数据
                        $goodsInfo = GoodsModel::where('id',$res->object_id)->first();
                        //修改商品销量
                        $salesNum = intval($goodsInfo->sales_num + 1);
                        GoodsModel::where('id',$goodsInfo->id)->update(['sales_num' => $salesNum]);
                        echo '支付成功';
                    }
                    echo 'success';
                }
                break;
            case 'buy service':
                break;
            case 'buy shop service':
                break;
            case 'vipshop':
                break;
            case 'task service':
                //查询未支付的订单
                $waitHandle = OrderModel::where('code', $data['code'])->first();
                if (!empty($waitHandle)){
                    switch ($waitHandle->status){
                        case 0:
                            //给用户充值
                            $res = UserDetailModel::recharge($waitHandle['uid'], 2, $data);
                            if (!$res) {
                                echo '支付失败！';
                            }
                            //余额支付
                            $status = TaskModel::buyServiceTaskBid($waitHandle->cash, $waitHandle->task_id, $waitHandle->uid, $data['code'], 2);

                            $result = $status;
                            break;
                        case 1:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }
                    if ($result){
                        echo '支付成功';
                    }
                    echo '支付失败';
                }
                break;
        }
    }

    public function wechatNotifyHandle($type, $data)
    {
        $content = '<xml>
                    <return_code><![CDATA[SUCCESS]]></return_code>
                    <return_msg><![CDATA[OK]]></return_msg>
                    </xml>';
        switch($type){
            case 'cash':
                $orderInfo = OrderModel::where('code', $data['code'])->first();
                $data['money'] = $orderInfo['cash'];
                if (!empty($orderInfo) && $orderInfo['status'] == 0 && empty($orderInfo->task_id)) {
                    $result = UserDetailModel::recharge($orderInfo->uid, 3, $data);
                }
                if (!$result) {
                    return $this->formateResponse(2022, '支付失败');
                }
                return response($content)->header('Content-Type', 'text/xml');
                break;
            case 'pub task':
                Log::info('微信支付回调,订单编号'.$data['code']);

                $orderInfo = OrderModel::where('code', $data['code'])->first();
                $data['money'] = $orderInfo['cash'];
                if (!empty($orderInfo) && $orderInfo['status'] == 0 && $orderInfo->task_id) {

                    $uid = $orderInfo->uid;
                    $money = $data['money'];
                    $task_id = $orderInfo->task_id;
                    $code = $data['code'];

                    $status = UserDetailModel::recharge($uid, 3, $data);
                    if (!$status) {
                        echo '支付失败！';
                    }

                    Log::info('微信支付回调,订单编号'.$data['code'].'金额'.$money.'任务id'.$task_id);
                    $task = TaskModel::find($task_id['task_id']);
                    $taskTypeAlias = TaskTypeModel::getTaskTypeAliasById($task['type_id']);
                    switch($taskTypeAlias){
                        case 'xuanshang':
                            TaskModel::bounty($money, $task_id, $uid, $code, 3);
                            break;
                        case 'zhaobiao':
                            TaskModel::bidBounty($money, $task_id, $uid, $code, 3);
                            break;
                    }
                }

                Log::info('微信支付回调,订单编号'.$data['code'].'成功');
                return response($content)->header('Content-Type', 'text/xml');
                break;
            case 'pub goods':
                break;
            case 'employ':
                $orderInfo = ShopOrderModel::where('code', $data['code'])->first();
                $data['money'] = $orderInfo['cash'];
                $status = false;
                if (!empty($orderInfo) && $orderInfo['status'] == 0) {
                    //给用户充值
                    $result = UserDetailModel::recharge($orderInfo['uid'], 2, $data);
                    if (!$result) {
                        $status = false;
                    }
                    $result2 = EmployModel::employBounty($data['money'], $orderInfo['object_id'], $orderInfo['uid'], $data['code'],3);
                    if ($result2) {
                        $status = true;;
                    }
                    if($status)
                        return response($content)->header('Content-Type', 'text/xml');
                }
                break;
            case 'pub service':
                break;
            case 'buy goods':
                $data['pay_type'] = 3;
                $res = ShopOrderModel::where(['code'=>$data['code'],'status'=>0,'object_type' => 2])->first();
                if (!empty($res)){
                    $status = ShopOrderModel::thirdBuyGoods($res->code, $data);
                    if ($status) {
                        //查询商品数据
                        $goodsInfo = GoodsModel::where('id',$res->object_id)->first();
                        //修改商品销量
                        $salesNum = intval($goodsInfo->sales_num + 1);
                        GoodsModel::where('id',$goodsInfo->id)->update(['sales_num' => $salesNum]);
                        return response($content)->header('Content-Type', 'text/xml');
                    }
                }
                break;
            case 'buy service':
                break;
            case 'buy shop service':
                break;
            case 'vipshop':
                break;
            case 'task service':
                $waitHandle = OrderModel::where('code', $data['code'])->first();
                $status = false;
                if (!empty($waitHandle)){
                    if($waitHandle->status == 0){
                        //给用户充值
                        $res = UserDetailModel::recharge(Auth::user()['id'], 3, $data);
                        if ($res) {
                            //余额支付
                            $status = TaskModel::buyServiceTaskBid($waitHandle->cash, $waitHandle->task_id, $waitHandle->uid, $data['code'], 3);

                        }else{
                            $status = false;
                        }

                    }else{
                        $status = true;
                    }

                }
                if($status)
                    return response($content)->header('Content-Type', 'text/xml');
                break;
        }
    }
}
