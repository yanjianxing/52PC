<?php

namespace App\Modules\Order\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\UserCouponModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\Order\Model\OrderModel;
use App\Modules\Order\Model\ShopOrderModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\GoodsServiceModel;
use App\Modules\Shop\Models\ProgrammeEnquiryMessageModel;
use App\Modules\Shop\Models\ProgrammeInquiryPayModel;
use App\Modules\Shop\Models\ProgrammeOrderModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\UserCouponLogModel;
use App\Modules\User\Model\UserDepositModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserToolModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use App\Modules\Manage\Model\ArticlePayModel;
use App\Modules\Manage\Model\ArticleModel;
use Omnipay;
use Log;

class CallBackController extends Controller
{
    /**
     * 支付宝同步回调处理
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function alipayReturn(Request $request)
    {
        $type = ShopOrderModel::handleOrderCode($request->get('out_trade_no'));
        switch ($type) {
            case 'cash'://充值
                $res = OrderModel::where('code', $request->get('out_trade_no'))->first();
                if (!empty($res) && $res->status != 0) {
                    return redirect('/finance/cash')->with(array("message" => "充值成功"));
                }
                break;
            case 'task service':
                $waitHandle = OrderModel::where('code',$request->get('out_trade_no'))->first();
                if (!empty($waitHandle) && $waitHandle->status != 0) {
                    return redirect('/kb/tasksuccess/' . $waitHandle->task_id);
                }
                break;
            case 'pub task'://托管赏金
                //修改订单状态，产生财务记录，修改任务状态
                $waitHandle = OrderModel::where('code',$request->get('out_trade_no'))->first();
                if (!empty($waitHandle) && $waitHandle->status != 0) {
                    $task = TaskModel::find($waitHandle->task_id);
                    if ($task['type_id'] == 1) {
                        return redirect('/kb/' . $waitHandle->task_id);
                    } else {
                        return redirect('/employ/workIn/' . $waitHandle->task_id);
                    }
                }

                break;
            case "payarticle"://资讯支付
                $articlepay = ArticlePayModel::where('order_num', $request->get('out_trade_no'))->first();
                if (!empty($articlepay) && $articlepay->status!= 1) {
                    return redirect('/user/consult')->with(array("message" => "支付成功等待审核"));
                }
                break;
            case "buy programme"://购买方案
                $programme = ProgrammeOrderModel::where("order_num", $request->get('out_trade_no'))->get();
                if (!$programme) {
                    foreach ($programme as $key => $val) {
                        if($val->status != 1){
                            return redirect()->to('/user/shopCart')->withErrors(['errMsg' => '支付成功！']);
                        }
                    }
                }
                break;
            case "buy inquiry":
                log::info("alipay notify handle1 ---".$request->get('out_trade_no'));
                $findInquiry = ProgrammeInquiryPayModel::where("order_num", $request->get('out_trade_no'))->first();
                if (!$findInquiry && $findInquiry->status!=1) {
                    return redirect("/user/index")->withErrors(['errMsg' => '支付成功！']);
                }
                break;
            case "vip":
            case "ck":
            case "rk":
                $userVipOrder = VipUserOrderModel::where("order_num", $request->get('out_trade_no'))->first();
                if (!$userVipOrder && $userVipOrder->pay_status != 1) {
                    if($type =="vip"){
                        return redirect("/user/myVip")->withErrors(['errMsg' => '支付成功！']);
                    }else{
                        return redirect("/user/myVipCard")->withErrors(['errMsg' => '支付成功！']);
                    }

                }
                break;
            case "deposit":
                $userDeposit = UserDepositModel::where("order_num", $request->get('out_trade_no'))->first();
                if (!$userDeposit && $userDeposit->status != 1) {
                    return redirect("/user/myDeposit")->withErrors(['errMsg' => '支付成功！']);
                }
                break;
            case "tool":
                $subOrder = UserToolModel::where("order_num", $request->get('out_trade_no'))->first();
                if (!$subOrder && $subOrder->status != 1) {
                    return redirect("/user/toolAll")->withErrors(['errMsg' => '支付成功！']);
                }
                break;
            case "service":
                $inquiryPay = ProgrammeInquiryPayModel::where("order_num", $request->get('out_trade_no'))->first();
                //获取用户信息
                if (!$inquiryPay && $inquiryPay->status != 1) {
                    ProgrammeInquiryPayModel::where("order_num", $request->get('out_trade_no'))->update(['status'=>2,"payment_at"=>date("Y-m-d H:i:s")]);
                    ProgrammeEnquiryMessageModel::where("id",$inquiryPay['programme_id'])->update(["pay_type"=>2]);
                    $messageType=ProgrammeEnquiryMessageModel::where("id",$inquiryPay['programme_id'])->select("type")->first();
                    if($messageType['type'] ==2){
                        return redirect("/user/serviceLeavMessage")->withErrors(['errMsg' => '支付成功！']);
                    }else{
                        return redirect("/user/serviceConsult")->withErrors(['errMsg' => '支付成功！']);
                    }
                }
                break;
            case "article":
                $articlePay = ArticlePayModel::where("order_num", $request->get('out_trade_no'))->first();
                if (!$articlePay && $articlePay->status != 1) {
                    return redirect("/user/consult")->withErrors(['errMsg' => '支付成功！']);
                }
                break;
        }

        $gateway = Omnipay::gateway('alipay');

        $config = ConfigModel::getPayConfig('alipay');

        $gateway->setPartner($config['partner']);
        $gateway->setKey($config['key']);
        $gateway->setSellerEmail($config['sellerEmail']);
        $gateway->setReturnUrl(env('ALIPAY_RETURN_URL', url('/order/pay/alipay/return')));
        $gateway->setNotifyUrl(env('ALIPAY_NOTIFY_URL', url('/order/pay/alipay/notify')));

        $options = [
            'request_params' => $_REQUEST,
        ];

        $response = $gateway->completePurchase($options)->send();
        //解决方案网服务器验证失败问题
        if($request->get('trade_status') == 'TRADE_SUCCESS'){
        //if ($response->isSuccessful() && $response->isTradeStatusOk()) {
            $data = array(
                'pay_account' => $request->get('buyer_email'),//支付账号
                'code' => $request->get('out_trade_no'),//订单编号
                'pay_code' => $request->get('trade_no'),//支付宝订单号
                'money' => $request->get('total_fee'),//支付金额
            );
            $type = ShopOrderModel::handleOrderCode($request->get('out_trade_no'));
            return $this->alipayReturnHandle($type, $data);

        } else {
            //支付失败通知.
            return redirect("/user/index");
        }
    }

    /**
     * 支付宝异步回调
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function alipayNotify(Request $request)
    {
        $gateway = Omnipay::gateway('alipay');

        $config = ConfigModel::getPayConfig('alipay');

        $gateway->setPartner($config['partner']);
        $gateway->setKey($config['key']);
        $gateway->setSellerEmail($config['sellerEmail']);
        $gateway->setReturnUrl(env('ALIPAY_RETURN_URL', url('/order/pay/alipay/return')));
        $gateway->setNotifyUrl(env('ALIPAY_NOTIFY_URL', url('/order/pay/alipay/notify')));

        $options = [
            'request_params' => $_REQUEST,
        ];

        $response = $gateway->completePurchase($options)->send();

        if ($response->isSuccessful() && $response->isTradeStatusOk()) {
            $data = array(
                'pay_account' => $request->get('buyer_email'),//支付账号
                'code' => $request->get('out_trade_no'),//订单编号
                'pay_code' => $request->get('trade_no'),//支付宝订单号
                'money' => $request->get('total_fee'),//支付金额
            );
            log::info("out_trade_no".$request->get('out_trade_no'));
            $type = ShopOrderModel::handleOrderCode($request->get('out_trade_no'));

            return $this->alipayNotifyHandle($type, $data);

        } else {
            //支付失败通知.
            exit('支付失败');
        }
    }

    /**
     * 微信支付异步回调
     *
     * @return mixed
     */
    public function wechatNotify()
    {
        Log::info('进入回调1');

        //获取微信回调参数
        $arrNotify = \CommonClass::xmlToArray($GLOBALS['HTTP_RAW_POST_DATA']);

        Log::info('进入回调2'.$arrNotify['out_trade_no']);
        if ($arrNotify['result_code'] == 'SUCCESS' && $arrNotify['return_code'] == 'SUCCESS') {
            $data = array(
                'pay_account' => $arrNotify['openid'],
                'code' => $arrNotify['out_trade_no'],
                'pay_code' => $arrNotify['transaction_id'],
                'money' => $arrNotify['total_fee'] / 100,
            );

            $type = ShopOrderModel::handleOrderCode($data['code']);
            Log::info('进入回调3'.$arrNotify['out_trade_no'].'类型'.$type);
            return $this->wechatNotifyHandle($type, $data);
        }
    }

    /**
     * 根据订单类型处理同步回调逻辑
     *
     * @param $type
     * @param $data
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function alipayReturnHandle($type, $data)
    {
        Log::info('alipay return handle ---'.$data['code']);

        switch ($type){
            case 'cash'://充值
                $res = OrderModel::where('code', $data['code'])->first();
                if (!empty($res) && $res->status == 0) {
                    $orderModel = new OrderModel();
                    $status = $orderModel->recharge('alipay', $data);
                    if ($status) {
                        echo '支付成功';
                        return redirect()->to('finance/cash');
                    }
                }else{
                    return redirect()->to('finance/cash');
                }
                break;
            case 'task service':
                $waitHandle = OrderModel::where('code', $data['code'])->first();
                if (!empty($waitHandle)){
                    switch ($waitHandle->status){
                        case 0:
                            $result = TaskModel::payServiceTask($waitHandle->cash, $waitHandle->task_id, $waitHandle['uid'], $data['code'], 2, $data['pay_account']);
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
                        return redirect('/kb/tasksuccess/'.$waitHandle->task_id);
                    }
                    echo '支付失败';
                    return redirect()->to('/kb/buyServiceTask/'.$waitHandle->task_id)->withErrors(['errMsg' => '支付失败！']);

                }
                break;
            case 'pub task'://托管赏金
                //修改订单状态，产生财务记录，修改任务状态
                $waitHandle = OrderModel::where('code', $data['code'])->first();
                if (!empty($waitHandle)){
                    switch ($waitHandle->status){
                        case 0:
                            $result = TaskModel:: bounty($waitHandle->cash, $waitHandle->task_id, $waitHandle['uid'], $data['code'], 2 , $data['pay_account']);
                            break;
                        case 1:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }
                    $task = TaskModel::find($waitHandle->task_id);
                    if ($result){
                        echo '支付成功';
                        UserModel::sendfreegrant($waitHandle['uid'],5);//托管成功自动发放
                        if($task['type_id'] == 1){
                            return redirect('/kb/'.$waitHandle->task_id);
                        }else{
                            return redirect('/employ/workIn/'.$waitHandle->task_id);
                        }
                    }
                    echo '支付失败';
                    if($task['type_id'] == 1){
                        return redirect('/kb/'.$waitHandle->task_id)->with(['error' => '支付失败！']);
                    }else{
                        return redirect('/employ/workIn/'.$waitHandle->task_id)->with(['error' => '支付失败！']);
                    }
                }

                break;
            case "payarticle"://资讯支付
                $articlepay = ArticlePayModel::where('order_num', $data['code'])->first();
                if (!empty($articlepay)){
                    switch ($articlepay->status){
                        case 1:
                            //查询是否存在优惠券的
                            $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                            if($userCouponLog){
                                UserCouponLogModel::where("order_num",$data['code'])->update([
                                    'payment_at'=>date("Y-m-d H:i:s"),
                                    'status' =>2,
                                ]);
                                UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                    'status'=>2,
                                ]);
                            }
                            $couponPrice=$userCouponLog?$userCouponLog['price']:0;
                            $result = ArticleModel::payarticle($articlepay->price, $articlepay->article_id, $articlepay['uid'],$articlepay['order_num'],2,$data['pay_account'],$couponPrice);
                            break;
                        default:
                            $result = true;
                            break;
                    }
                    if ($result){
                        echo '支付成功';
                        return redirect('/user/consult')->with(array("message"=>"支付成功等待审核"));
                    }
                    echo '支付失败';
                    return redirect()->to('/employ/trusteemoney/'.$articlepay->article_id)->withErrors(['errMsg' => '支付失败！']);

                }
                break;
            case "buy programme"://购买方案
                $programme=ProgrammeOrderModel::where("order_num",$data['code'])->get();
                if($programme){
                    $result=false;
                    //查询是否存在优惠券的
                    $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                    if($userCouponLog){
                        UserCouponLogModel::where("order_num",$data['code'])->update([
                            'payment_at'=>date("Y-m-d H:i:s"),
                            'status' =>2,
                        ]);
                        UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                            'status'=>2,
                        ]);
                    }
                    $couponPrice=$userCouponLog?$userCouponLog['price']:0;
                    $tag=0;
                    foreach ($programme as $key=>$val){
                        switch ($val->status){
                            case 1:
                                $coupon=$tag ==0?$couponPrice:0;
                                $tag++;
                                $result=DB::transaction(function() use($data,$val,$coupon){
                                    $programme = ProgrammeOrderModel::where("order_num",$data['code'])->update(["status"=>2,"payment_at"=>date("Y-m-d")]);
                                    ProgrammeOrderModel::programmeSuccessHandle($data,$val,2,$coupon);
                                    return $programme;
                                });
                                break;
                            case 2:
                                $result = true;
                                break;
                            default:
                                $result = true;
                                break;
                        }
                    }
                    if ($result){
                        echo '支付成功';
                        return redirect("/user/payProgramme")->withErrors(['errMsg' => '支付成功！']);
                    }
                    echo '支付失败';
                    return redirect()->to('/user/shopCart')->withErrors(['errMsg' => '支付失败！']);
                }
                break;
            case "buy inquiry":
                log::info("alipay notify handle2 ---".$data['code']);
                $findInquiry=ProgrammeInquiryPayModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$findInquiry['uid'])->pluck("balance");
                if($findInquiry){
                    $result=false;
                    switch ($findInquiry->status){
                        case 1:
                            $result=DB::transaction(function() use($data,$findInquiry,$userBalance){
                                $programme = ProgrammeInquiryPayModel::where("order_num",$data['code'])->update(["status"=>2,"payment_at"=>date("Y-m-d")]);
                                //查询是否存在优惠券的
                                $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                                if($userCouponLog){
                                    UserCouponLogModel::where("order_num",$data['code'])->update([
                                         'payment_at'=>date("Y-m-d H:i:s"),
                                         'status' =>2,
                                    ]);
                                    UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                        'status'=>2,
                                    ]);
                                }
                                //生成询价记录
                                ProgrammeEnquiryMessageModel::where("programme_id",$findInquiry['programme_id'])
                                    ->where("uid",$findInquiry['uid'])->where("pay_type",1)->update(["pay_type"=>2]);

                                FinancialModel::createOne([
                                    'action'     => 5,
                                    'pay_type'   => 2,
                                    'cash'       => $findInquiry['price'],
                                    'uid'        => $findInquiry['uid'],
                                    'related_id'   =>$findInquiry['id'],
                                    'created_at' => date('Y-m-d H:i:s', time()),
                                    'status' =>2,
                                    'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                return $programme;
                            });
                            break;
                        case 2:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }
                    if ($result){
                        echo '支付成功';
                        return redirect("/user/employerConsult")->withErrors(['errMsg' => '支付成功！']);
                    }
                    echo '支付失败';
                    return redirect()->to('/user/shopCart')->withErrors(['errMsg' => '支付失败！']);
                }
                break;
            case "vip":
            case "ck":
            case "rk":
                $userVipOrder=VipUserOrderModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$userVipOrder['uid'])->pluck("balance");
                if($userVipOrder){
                    $result=false;
                    switch ($userVipOrder->pay_status){
                        case 1:
                            $result=DB::transaction(function() use($data,$userVipOrder,$type,$userBalance){
                                $data['order_num']=$data['code'];
                                $data['action']=$type;
                               // if($type=="vip"){
                                    $data['order_id']=$userVipOrder['vipid'];
                               // }else{
                                    //$data['order_id']=$userVipOrder['vipconfigid'];
                               // }
                                $data['user_balance']=$userBalance;
                                $data['price']=$userVipOrder['price'];
                                $data['uid']=$userVipOrder['uid'];
                                VipUserOrderModel::createVipData($data);
                                //查询是否存在优惠券的
                                $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                                if($userCouponLog){
                                    UserCouponLogModel::where("order_num",$data['code'])->update([
                                        'payment_at'=>date("Y-m-d H:i:s"),
                                        'status' =>2,
                                    ]);
                                    UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                        'status'=>2,
                                    ]);
                                }
                                FinancialModel::createOne([
                                    'action'     => 12,
                                    'pay_type'   => 2,
                                    'cash'       => $userVipOrder['price'],
                                    'uid'        => $userVipOrder['uid'],
                                    'related_id'   =>$userVipOrder['id'],
                                    'created_at' => date('Y-m-d H:i:s', time()),
                                    'status' =>2,
                                    'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                return $data;
                            });
                            break;
                        case 2:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }
                    if ($result){
                        echo '支付成功';
                        if($type =="vip"){
                            return redirect("/user/myVip")->withErrors(['errMsg' => '支付成功！']);
                        }else{
                            return redirect("/user/myVipCard")->withErrors(['errMsg' => '支付成功！']);
                        }

                    }
                    echo '支付失败';
                    if($type =="vip") {
                        return redirect()->to('/user/myVip')->withErrors(['errMsg' => '支付失败！']);
                    }else{
                        return redirect()->to('/user/myVipCard')->withErrors(['errMsg' => '支付失败！']);
                    }
                }
                break;
            case "deposit":
                $userDeposit=UserDepositModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$userDeposit['uid'])->pluck("balance");
                if($userDeposit){
                    $result=false;
                    switch ($userDeposit->status){
                        case 1:
                            $result=DB::transaction(function() use($data,$userDeposit,$type,$userBalance){
                                // $data['order_num']=$data['code'];
                                //VipUserOrderModel::createVipData($data);
                                FinancialModel::createOne([
                                    'action'     => 9,
                                    'pay_type'   => 2,
                                    'cash'       => $userDeposit['price'],
                                    'uid'        => $userDeposit['uid'],
                                    'related_id'   =>$userDeposit['id'],
                                    'created_at' => date('Y-m-d H:i:s', time()),
                                    'status' =>2,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                //修改用户保证金
                                UserDetailModel::where("uid",$userDeposit['uid'])->update(["deposit"=> $userDeposit['price']]);
                                //修改状态
                                UserDepositModel::where("order_num",$data['code'])->update(["status"=>2,"payment_at"=>date("Y-m-d H:i:s")]);
                                $userInfo=UserModel::find($userDeposit['uid']);
                                AuthRecordModel::create([
                                    'auth_id'=>$userDeposit['id'],
                                    'uid'=>$userDeposit['uid'],
                                    'username'=>$userInfo['name'],
                                    'auth_code' =>"promise",
                                    'status' =>1,
                                    'auth_time'=>date("Y-m-d H:i:s")

                                ]);
                                UserDepositModel::sendSms($userDeposit['uid'],"deposit_sub",$userDeposit['price']);
                                return $data;
                            });
                            break;
                        case 2:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }
                    if ($result){
                        echo '支付成功';
                        return redirect("/user/myDeposit")->withErrors(['errMsg' => '支付成功！']);
                    }
                    echo '支付失败';
                    return redirect()->to('/user/myDeposit')->withErrors(['errMsg' => '支付失败！']);
                }
                break;
            case "tool":
            $subOrder=UserToolModel::where("order_num",$data['code'])->first();
            //获取用户信息
            $userBalance=UserDetailModel::where("uid",$subOrder['uid'])->pluck("balance");
            if($subOrder){
                $result=false;
                switch ($subOrder->pay_status){
                    case 1:
                        $result=DB::transaction(function() use($data,$subOrder,$type,$userBalance){
                            // $data['order_num']=$data['code'];
                            //VipUserOrderModel::createVipData($data);
                            //修改状态
                            UserToolModel::where("order_num",$data['code'])->update(["status"=>1,"pay_status"=>2]);
                            //查询是否存在优惠券的
                            $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                            if($userCouponLog){
                                UserCouponLogModel::where("order_num",$data['code'])->update([
                                    'payment_at'=>date("Y-m-d H:i:s"),
                                    'status' =>2,
                                ]);
                                UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                    'status'=>2,
                                ]);
                            }
                            FinancialModel::createOne([
                                'action'     => 6,
                                'pay_type'   => 2,
                                'cash'       => $subOrder['price'],
                                'uid'        => $subOrder['uid'],
                                'related_id'   =>$subOrder['id'],
                                'created_at' => date('Y-m-d H:i:s', time()),
                                'status' =>2,
                                'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                'remainder'  =>$userBalance,
                                'pay_account'=>$data['pay_account'],
                            ]);
                            return $data;
                        });
                        break;
                    case 2:
                        $result = true;
                        break;
                    default:
                        $result = true;
                        break;
                }
                if ($result){
                    echo '支付成功';
                    return redirect("/user/toolAll")->withErrors(['errMsg' => '支付成功！']);
                }
                echo '支付失败';
                return redirect()->to('/user/toolAll')->withErrors(['errMsg' => '支付失败！']);
            }
            break;
            case "service":
                $inquiryPay=ProgrammeInquiryPayModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$inquiryPay['uid'])->pluck("balance");
                if($inquiryPay){
                    $result=false;
                    switch ($inquiryPay->status){
                        case 1:
                            $result=DB::transaction(function() use($data,$inquiryPay,$type,$userBalance){
                                // $data['order_num']=$data['code'];
                                //VipUserOrderModel::createVipData($data);
                                ProgrammeInquiryPayModel::where("order_num",$data['code'])->update(["status"=>2,"payment_at"=>date("Y-m-d H:i:s")]);
                                ProgrammeEnquiryMessageModel::where("id",$inquiryPay['programme_id'])->update(["pay_type"=>2]);
                                //查询是否存在优惠券的
                                $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                                if($userCouponLog){
                                    UserCouponLogModel::where("order_num",$data['code'])->update([
                                        'payment_at'=>date("Y-m-d H:i:s"),
                                        'status' =>2,
                                    ]);
                                    UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                        'status'=>2,
                                    ]);
                                }
                                FinancialModel::createOne([
                                    'action'     => 8,
                                    'pay_type'   => 2,
                                    'cash'       => $inquiryPay['price'],
                                    'uid'        => $inquiryPay['uid'],
                                    'related_id'   =>$inquiryPay['id'],
                                    'created_at' => date('Y-m-d H:i:s', time()),
                                    'status' =>2,
                                    'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                return $data;
                            });
                            break;
                        case 2:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }
                    if ($result){
                        echo '支付成功';
                        $messageType=ProgrammeEnquiryMessageModel::where("id",$inquiryPay['programme_id'])->select("type")->first();
                        if($messageType['type'] ==2){
                            return redirect("/user/serviceLeavMessage")->withErrors(['errMsg' => '支付成功！']);
                        }else{
                            return redirect("/user/serviceConsult")->withErrors(['errMsg' => '支付成功！']);
                        }
                    }
                    echo '支付失败';
                    return redirect()->to('/user/serviceLeavMessage')->withErrors(['errMsg' => '支付失败！']);
                }
                break;
            case "article":
                $articlePay=ArticlePayModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$articlePay['uid'])->pluck("balance");
                if($articlePay) {
                    $result = false;
                    switch ($articlePay->status) {
                        case 1:
                            $result = DB::transaction(function () use ($data, $articlePay, $type,$userBalance) {
                                ArticlePayModel::where("order_num", $data['code'])->update(["status" => 2, "payment_at" => date("Y-m-d H:i:s")]);
                                ArticleModel::where("id",$articlePay['article_id'])->update(["status"=>0]);
                                //查询是否存在优惠券的
                                $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                                if($userCouponLog){
                                    UserCouponLogModel::where("order_num",$data['code'])->update([
                                        'payment_at'=>date("Y-m-d H:i:s"),
                                        'status' =>2,
                                    ]);
                                    UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                        'status'=>2,
                                    ]);
                                }
                                FinancialModel::createOne([
                                    'action' => 7,
                                    'pay_type' => 2,
                                    'cash' => $articlePay['price'],
                                    'uid' => $articlePay['uid'],
                                    'related_id' => $articlePay['id'],
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'status' =>2,
                                    'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                return $data;
                            });
                            break;
                        case 2:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }
                    if ($result) {
                        echo '支付成功';
                        return redirect("/user/consult")->withErrors(['errMsg' => '支付成功！']);
                    }
                    echo '支付失败';
                    return redirect()->to('/user/consult')->withErrors(['errMsg' => '支付失败！']);
                }
                break;
        }
    }

    /**
     * 根据订单类型处理支付宝异步回调逻辑
     *
     * @param $type
     * @param $data
     */
    public function alipayNotifyHandle($type, $data)
    {
        Log::info('alipay notify handle ---'.$data['code']);

        switch ($type){
            case 'cash':
                $res = OrderModel::where('code', $data['code'])->first();
                if (!empty($res) && $res->status == 0) {
                    $orderModel = new OrderModel();
                    $staus = $orderModel->recharge('alipay', $data);
                    if ($staus) {
                        exit('支付成功');
                    }
                }
                break;
            case 'task service':
                $waitHandle = OrderModel::where('code', $data['code'])->first();
                if (!empty($waitHandle)){
                    switch ($waitHandle->status){
                        case 0:
                            //给用户充值
                            $result = TaskModel::payServiceTask($waitHandle->cash, $waitHandle->task_id, $waitHandle->uid, $data['code'],2);
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
            case 'pub task':
                //修改订单状态，产生财务记录，修改任务状态
                $waitHandle = OrderModel::where('code', $data['code'])->first();
                if (!empty($waitHandle)){
                    switch ($waitHandle->status){
                        case 0:
                            $result = TaskModel:: bounty($waitHandle->cash, $waitHandle->task_id, $waitHandle['uid'], $data['code'], 2);
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
                        UserModel::sendfreegrant($waitHandle['uid'],5);//托管成功自动发放
                    }
                    echo '支付失败';
                }

                break;
            case "payarticle"://资讯支付
                $articlepay = ArticlePayModel::where('order_num', $data['code'])->first();
                if (!empty($articlepay)){
                    switch ($articlepay->status){
                        case 1:
                            //查询是否存在优惠券的
                            $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                            if($userCouponLog){
                                UserCouponLogModel::where("order_num",$data['code'])->update([
                                    'payment_at'=>date("Y-m-d H:i:s"),
                                    'status' =>2,
                                ]);
                                UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                    'status'=>2,
                                ]);
                            }
                            $couponPrice=$userCouponLog?$userCouponLog['price']:0;
                            $result = ArticleModel::payarticle($articlepay->price, $articlepay->article_id, $articlepay['uid'],$articlepay['order_num'],2,$data['pay_account'],$couponPrice);
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
            case "buy programme":
                $programme=ProgrammeOrderModel::where("order_num",$data['code'])->get();
                if($programme){
                    $result=false;
                    //查询是否存在优惠券的
                    $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                    if($userCouponLog){
                        UserCouponLogModel::where("order_num",$data['code'])->update([
                            'payment_at'=>date("Y-m-d H:i:s"),
                            'status' =>2,
                        ]);
                        UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                            'status'=>2,
                        ]);
                    }
                    $couponPrice=$userCouponLog?$userCouponLog['price']:0;
                    $tag=0;
                    foreach ($programme as $key=>$val){
                        switch ($val->status){
                            case 1:
                                $coupon=$tag ==0?$couponPrice:0;
                                $tag++;
                                $result=DB::transaction(function() use($data,$val,$coupon){
                                    $programme = ProgrammeOrderModel::where("order_num",$data['code'])->update(["status"=>2,"payment_at"=>date("Y-m-d")]);
                                    ProgrammeOrderModel::programmeSuccessHandle($data,$val,2,$coupon);
                                    return $programme;
                                });
                                break;
                            case 2:
                                $result = true;
                                break;
                            default:
                                $result = true;
                                break;
                        }
                    }
                    if ($result){
                        echo '支付成功';

                    }
                    echo '支付失败';

                }
                break;
            case "buy inquiry":
                $findInquiry=ProgrammeInquiryPayModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$findInquiry['uid'])->pluck("balance");
                if($findInquiry){
                    $result=false;
                    switch ($findInquiry->status){
                        case 1:
                            $result=DB::transaction(function() use($data,$findInquiry,$userBalance){
                                $programme = ProgrammeInquiryPayModel::where("order_num",$data['code'])->update(["status"=>2,"payment_at"=>date("Y-m-d")]);
                                //查询是否存在优惠券的
                                $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                                if($userCouponLog){
                                    UserCouponLogModel::where("order_num",$data['code'])->update([
                                        'payment_at'=>date("Y-m-d H:i:s"),
                                        'status' =>2,
                                    ]);
                                    UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                        'status'=>2,
                                    ]);
                                }
                                //生成询价记录
                                ProgrammeEnquiryMessageModel::where("programme_id",$findInquiry['programme_id'])
                                    ->where("uid",$findInquiry['uid'])->where("pay_type",1)->update(["pay_type"=>2]);

                                FinancialModel::createOne([
                                    'action'     => 5,
                                    'pay_type'   => 2,
                                    'cash'       => $findInquiry['price'],
                                    'uid'        => $findInquiry['uid'],
                                    'related_id'   =>$findInquiry['id'],
                                    'created_at' => date('Y-m-d H:i:s', time()),
                                    'status' =>2,
                                    'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                return $programme;
                            });
                            break;
                        case 2:
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
            case "vip":
            case "ck":
            case "rk":
                    $userVipOrder=VipUserOrderModel::where("order_num",$data['code'])->first();
                    //获取用户信息
                    $userBalance=UserDetailModel::where("uid",$userVipOrder['uid'])->pluck("balance");
                    if($userVipOrder){
                        $result=false;
                        switch ($userVipOrder->pay_status){
                            case 1:
                                $result=DB::transaction(function() use($data,$userVipOrder,$type,$userBalance){
                                    $data['order_num']=$data['code'];
                                    $data['action']=$type;
                                    //if($type=="vip"){
                                        $data['order_id']=$userVipOrder['vipid'];
                                   // }else{
                                     //   $data['order_id']=$userVipOrder['vipconfigid'];
                                  //  }
                                    $data['user_balance']=$userBalance;
                                    $data['price']=$userVipOrder['price'];
                                    $data['uid']=$userVipOrder['uid'];
                                    VipUserOrderModel::createVipData($data);
                                    //查询是否存在优惠券的
                                    $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                                    if($userCouponLog){
                                        UserCouponLogModel::where("order_num",$data['code'])->update([
                                            'payment_at'=>date("Y-m-d H:i:s"),
                                            'status' =>2,
                                        ]);
                                        UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                            'status'=>2,
                                        ]);
                                    }
                                    FinancialModel::createOne([
                                        'action'     => 12,
                                        'pay_type'   => 2,
                                        'cash'       => $userVipOrder['price'],
                                        'uid'        => $userVipOrder['uid'],
                                        'related_id'   =>$userVipOrder['id'],
                                        'created_at' => date('Y-m-d H:i:s', time()),
                                        'status' =>2,
                                        'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                        'remainder'  =>$userBalance,
                                        'pay_account'=>$data['pay_account'],
                                    ]);
                                    return $data;
                                });

                                break;
                            case 2:
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
            case "deposit":
                $userDeposit=UserDepositModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$userDeposit['uid'])->pluck("balance");
                if($userDeposit){
                    $result=false;
                    switch ($userDeposit->status){
                        case 1:
                            $result=DB::transaction(function() use($data,$userDeposit,$type,$userBalance){
                               // $data['order_num']=$data['code'];
                                //VipUserOrderModel::createVipData($data);
                                FinancialModel::createOne([
                                    'action'     => 9,
                                    'pay_type'   => 2,
                                    'cash'       => $userDeposit['price'],
                                    'uid'        => $userDeposit['uid'],
                                    'related_id'   =>$userDeposit['id'],
                                    'created_at' => date('Y-m-d H:i:s', time()),
                                    'status' =>2,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                //修改用户保证金
                                UserDetailModel::where("uid",$userDeposit['uid'])->update(["deposit"=> $userDeposit['price']]);
                                //修改状态
                                UserDepositModel::where("order_num",$data['code'])->update(["status"=>2,"payment_at"=>date("Y-m-d H:i:s")]);
                                //获取user信息
                                $userInfo=UserModel::find($userDeposit['uid']);
                                AuthRecordModel::create([
                                    'auth_id'=>$userDeposit['id'],
                                    'uid'=>$userDeposit['uid'],
                                    'username'=>$userInfo['name'],
                                    'auth_code' =>"promise",
                                    'status' =>1,
                                    'auth_time'=>date("Y-m-d H:i:s")

                                ]);
                                UserDepositModel::sendSms($userDeposit['uid'],"deposit_sub",$userDeposit['price']);
                                return $data;
                            });
                            break;
                        case 2:
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
            case "tool":
                $subOrder=UserToolModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$subOrder['uid'])->pluck("balance");
                if($subOrder){
                    $result=false;
                    switch ($subOrder->pay_status){
                        case 1:
                            $result=DB::transaction(function() use($data,$subOrder,$type,$userBalance){
                                // $data['order_num']=$data['code'];
                                //VipUserOrderModel::createVipData($data);
                                //修改状态
                                UserToolModel::where("order_num",$data['code'])->update(["status"=>1,"pay_status"=>2]);
                                //查询是否存在优惠券的
                                $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                                if($userCouponLog){
                                    UserCouponLogModel::where("order_num",$data['code'])->update([
                                        'payment_at'=>date("Y-m-d H:i:s"),
                                        'status' =>2,
                                    ]);
                                    UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                        'status'=>2,
                                    ]);
                                }
                                FinancialModel::createOne([
                                    'action'     => 6,
                                    'pay_type'   => 2,
                                    'cash'       => $subOrder['price'],
                                    'uid'        => $subOrder['uid'],
                                    'related_id'   =>$subOrder['id'],
                                    'created_at' => date('Y-m-d H:i:s', time()),
                                    'status' =>2,
                                    'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                return $data;
                            });
                            break;
                        case 2:
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
            case "service":
                $inquiryPay=ProgrammeInquiryPayModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$inquiryPay['uid'])->pluck("balance");
                if($inquiryPay){
                    $result=false;
                    switch ($inquiryPay->status){
                        case 1:
                            $result=DB::transaction(function() use($data,$inquiryPay,$type,$userBalance){
                                // $data['order_num']=$data['code'];
                                //VipUserOrderModel::createVipData($data);
                                ProgrammeInquiryPayModel::where("order_num",$data['code'])->update(["status"=>2,"payment_at"=>date("Y-m-d H:i:s")]);
                                ProgrammeEnquiryMessageModel::where("id",$inquiryPay['programme_id'])->update(["pay_type"=>2]);
                                //查询是否存在优惠券的
                                $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                                if($userCouponLog){
                                    UserCouponLogModel::where("order_num",$data['code'])->update([
                                        'payment_at'=>date("Y-m-d H:i:s"),
                                        'status' =>2,
                                    ]);
                                    UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                        'status'=>2,
                                    ]);
                                }
                                FinancialModel::createOne([
                                    'action'     => 8,
                                    'pay_type'   => 2,
                                    'cash'       => $inquiryPay['price'],
                                    'uid'        => $inquiryPay['uid'],
                                    'related_id'   =>$inquiryPay['id'],
                                    'created_at' => date('Y-m-d H:i:s', time()),
                                    'status' =>2,
                                    'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                return $data;
                            });
                            break;
                        case 2:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }
                    if ($result){
                        echo '支付成功';
                        //return redirect("/user/toolAll")->withErrors(['errMsg' => '支付成功！']);

                    }
                    echo '支付失败';
                    //return redirect()->to('/user/toolAll')->withErrors(['errMsg' => '支付失败！']);

                }
                break;
            case "article":
                $articlePay=ArticlePayModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$articlePay['uid'])->pluck("balance");
                if($articlePay) {
                    $result = false;
                    switch ($articlePay->status) {
                        case 1:
                            $result = DB::transaction(function () use ($data, $articlePay, $type,$userBalance) {

                                ArticlePayModel::where("order_num", $data['code'])->update(["status" => 2, "payment_at" => date("Y-m-d H:i:s")]);
                                ArticleModel::where("id",$articlePay['article_id'])->update(["status"=>0]);
                                //查询是否存在优惠券的
                                $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                                if($userCouponLog){
                                    UserCouponLogModel::where("order_num",$data['code'])->update([
                                        'payment_at'=>date("Y-m-d H:i:s"),
                                        'status' =>2,
                                    ]);
                                    UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                        'status'=>2,
                                    ]);
                                }
                                FinancialModel::createOne([
                                    'action' => 7,
                                    'pay_type' => 2,
                                    'cash' => $articlePay['price'],
                                    'uid' => $articlePay['uid'],
                                    'related_id' => $articlePay['id'],
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'status' =>2,
                                    'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                return $data;
                            });
                            break;
                        case 2:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }
                    if ($result) {
                        echo '支付成功';

                    }
                    echo '支付失败';
                }
             break;
        }
    }

    /**
     * 根据订单类型处理微信异步回调逻辑
     *
     * @param $type
     * @param $data
     */
    public function wechatNotifyHandle($type, $data)
    {
        $content = '<xml>
                    <return_code><![CDATA[SUCCESS]]></return_code>
                    <return_msg><![CDATA[OK]]></return_msg>
                    </xml>';
        switch ($type){
            case 'cash':
                $res = OrderModel::where('code', $data['code'])->first();
                if (!empty($res) && $res->status == 0) {
                    $orderModel = new OrderModel();
                    $status = $orderModel->recharge('wechat', $data);
                }
                break;
            case 'task service':
                $waitHandle = OrderModel::where('code', $data['code'])->first();
                $status = false;
                if (!empty($waitHandle)){
                    if($waitHandle->status == 0){
                        //余额支付
                        $status = TaskModel::payServiceTask($waitHandle->cash, $waitHandle->task_id, $waitHandle->uid, $data['code'], 3);

                    }else{
                        $status = true;
                    }
                }
                break;
            case 'pub task':
                //修改订单状态，产生财务记录，修改任务状态
                $waitHandle = OrderModel::where('code', $data['code'])->first();
                $status = false;
                if (!empty($waitHandle)){
                    switch ($waitHandle->status){
                        case 0:
                            $status = TaskModel:: bounty($waitHandle->cash, $waitHandle->task_id, $waitHandle['uid'], $data['code'], 3);
                            break;
                        case 1:
                            $status = true;
                            break;
                        default:
                            $status = true;
                            break;
                    }
                }
                break;
            case "payarticle"://资讯支付
                $articlepay = ArticlePayModel::where('order_num', $data['code'])->first();
                if (!empty($articlepay)){
                    switch ($articlepay->status){
                        case 1:
                            //查询是否存在优惠券的
                            $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                            if($userCouponLog){
                                UserCouponLogModel::where("order_num",$data['code'])->update([
                                    'payment_at'=>date("Y-m-d H:i:s"),
                                    'status' =>2,
                                ]);
                                UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                    'status'=>2,
                                ]);
                            }
                            $couponPrice=$userCouponLog?$userCouponLog['price']:0;
                            $status = ArticleModel::payarticle($articlepay->price, $articlepay->article_id, $articlepay['uid'],$articlepay['order_num'],3,$couponPrice);
                            break;
                        default:
                            $status = true;
                            break;
                    }
                }
                break;
            case "buy programme":
                $programme=ProgrammeOrderModel::where("order_num",$data['code'])->get();
                if($programme){
                   foreach ($programme as $key=>$val){
                       switch ($val->status){
                           case 1:
                               $result=DB::transaction(function() use($data,$val){
                                   $programme = ProgrammeOrderModel::where("order_num",$data['code'])->update(["status"=>2,"payment_at"=>date("Y-m-d")]);
                                   ProgrammeOrderModel::programmeSuccessHandle($data,$val,3);
                                   return $programme;
                               });
                               break;
                           case 2:
                               $result = true;
                               break;
                           default:
                               $result = true;
                               break;
                       }
                   }
                }
                $status = $result;
                break;
            case "buy inquiry":
                log::info("alipay notify handle ---".$data['code']);
                $findInquiry=ProgrammeInquiryPayModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$findInquiry['uid'])->pluck("balance");
                $result=false;
                if($findInquiry){

                    switch ($findInquiry->status){
                        case 1:
                            $result=DB::transaction(function() use($data,$findInquiry,$userBalance){
                                $programme = ProgrammeInquiryPayModel::where("order_num",$data['code'])->update(["status"=>2,"payment_at"=>date("Y-m-d")]);

                                //查询是否存在优惠券的
                                $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                                if($userCouponLog){
                                    UserCouponLogModel::where("order_num",$data['code'])->update([
                                        'payment_at'=>date("Y-m-d H:i:s"),
                                        'status' =>2,
                                    ]);
                                    UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                        'status'=>2,
                                    ]);
                                }
                                //生成询价记录
                                ProgrammeEnquiryMessageModel::where("programme_id",$findInquiry['programme_id'])
                                    ->where("uid",$findInquiry['uid'])->where("pay_type",1)->update(["pay_type"=>2]);

                                FinancialModel::createOne([
                                    'action'     => 5,
                                    'pay_type'   => 3,
                                    'cash'       => $findInquiry['price'],
                                    'uid'        => $findInquiry['uid'],
                                    'related_id'   =>$findInquiry['id'],
                                    'created_at' => date('Y-m-d H:i:s', time()),
                                    'status' =>2,
                                    'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                return $programme;
                            });
                            break;
                        case 2:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }
                }
                $status = $result;
                if($result){
                    echo "支付成功";
                }
                   echo "支付失败";

                break;
            case "vip":
            case "ck":
            case "rk":
                $userVipOrder=VipUserOrderModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$userVipOrder['uid'])->pluck("balance");
                $result=false;
                if($userVipOrder){

                    switch ($userVipOrder->pay_status){
                        case 1:
                            $result=DB::transaction(function() use($data,$userVipOrder,$type,$userBalance){
                                $data['order_num']=$data['code'];
                                $data['action']=$type;
                                //if($type=="vip"){
                                    $data['order_id']=$userVipOrder['vipid'];
                               // }else{
                                   // $data['order_id']=$userVipOrder['vipconfigid'];
                               // }
                                $data['user_balance']=$userBalance;
                                $data['price']=$userVipOrder['price'];
                              //  dd();
                                $data['uid']=$userVipOrder['uid'];
                                VipUserOrderModel::createVipData($data);
                                //查询是否存在优惠券的
                                $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                                if($userCouponLog){
                                    UserCouponLogModel::where("order_num",$data['code'])->update([
                                        'payment_at'=>date("Y-m-d H:i:s"),
                                        'status' =>2,
                                    ]);
                                    UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                        'status'=>2,
                                    ]);
                                }
                                FinancialModel::createOne([
                                    'action'     => 12,
                                    'pay_type'   => 3,
                                    'cash'       => $userVipOrder['price'],
                                    'uid'        => $userVipOrder['uid'],
                                    'related_id'   =>$userVipOrder['id'],
                                    'created_at' => date('Y-m-d H:i:s', time()),
                                    'status' =>2,
                                    'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                return $data;

                            });
                            break;
                        case 2:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }
                }
                $status = $result;
                break;
            case "deposit":
                $userDeposit=UserDepositModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$userDeposit['uid'])->pluck("balance");
                $result=false;
                if($userDeposit){
                    switch ($userDeposit->status){
                        case 1:
                            $result=DB::transaction(function() use($data,$userDeposit,$type,$userBalance){
                                // $data['order_num']=$data['code'];
                                //VipUserOrderModel::createVipData($data);
                                FinancialModel::createOne([
                                    'action'     => 9,
                                    'pay_type'   => 3,
                                    'cash'       => $userDeposit['price'],
                                    'uid'        => $userDeposit['uid'],
                                    'related_id'   =>$userDeposit['id'],
                                    'created_at' => date('Y-m-d H:i:s', time()),
                                    'status' =>2,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                //修改用户保证金
                                UserDetailModel::where("uid",$userDeposit['uid'])->update(["deposit"=> $userDeposit['price']]);
                                //修改状态
                                UserDepositModel::where("order_num",$data['code'])->update(["status"=>2,"payment_at"=>date("Y-m-d H:i:s")]);
                                //获取user信息
                                $userInfo=UserModel::find($userDeposit['uid']);
                                AuthRecordModel::create([
                                    'auth_id'=>$userDeposit['id'],
                                    'uid'=>$userDeposit['uid'],
                                    'username'=>$userInfo['name'],
                                    'auth_code' =>"promise",
                                    'status' =>1,
                                    'auth_time'=>date("Y-m-d H:i:s")

                                ]);
                                UserDepositModel::sendSms($userDeposit['uid'],"deposit_sub",$userDeposit['price']);
                                return $data;
                            });
                            break;
                        case 2:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }
                }
                $status = $result;
                break;
            case "tool":
                $subOrder=UserToolModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$subOrder['uid'])->pluck("balance");
                $result=false;
                if($subOrder){
                    switch ($subOrder->status){
                        case 1:
                            $result=DB::transaction(function() use($data,$subOrder,$type,$userBalance){
                                // $data['order_num']=$data['code'];
                                //VipUserOrderModel::createVipData($data);

                                //修改状态
                                UserToolModel::where("order_num",$data['code'])->update(["status"=>1,"pay_status"=>2]);
                                //查询是否存在优惠券的
                                $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                                if($userCouponLog){
                                    UserCouponLogModel::where("order_num",$data['code'])->update([
                                        'payment_at'=>date("Y-m-d H:i:s"),
                                        'status' =>2,
                                    ]);
                                    UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                        'status'=>2,
                                    ]);
                                }
                                FinancialModel::createOne([
                                    'action'     => 6,
                                    'pay_type'   => 3,
                                    'cash'       => $subOrder['price'],
                                    'uid'        => $subOrder['uid'],
                                    'related_id'   =>$subOrder['id'],
                                    'created_at' => date('Y-m-d H:i:s', time()),
                                    'status' =>2,
                                    'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                return $data;
                            });
                            break;
                        case 2:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }

                }
                $status = $result;
                break;
            case "service":
                $inquiryPay=ProgrammeInquiryPayModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$inquiryPay['uid'])->pluck("balance");
                $result=false;
                if($inquiryPay){
                    switch ($inquiryPay->status){
                        case 1:
                            $result=DB::transaction(function() use($data,$inquiryPay,$type,$userBalance){
                                // $data['order_num']=$data['code'];
                                //VipUserOrderModel::createVipData($data);

                                ProgrammeInquiryPayModel::where("order_num",$data['code'])->update(["status"=>2,"payment_at"=>date("Y-m-d H:i:s")]);
                                //查询是否存在优惠券的
                                $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                                if($userCouponLog){
                                    UserCouponLogModel::where("order_num",$data['code'])->update([
                                        'payment_at'=>date("Y-m-d H:i:s"),
                                        'status' =>2,
                                    ]);
                                    UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                        'status'=>2,
                                    ]);
                                }
                                FinancialModel::createOne([
                                    'action'     => 8,
                                    'pay_type'   => 3,
                                    'cash'       => $inquiryPay['price'],
                                    'uid'        => $inquiryPay['uid'],
                                    'related_id'   =>$inquiryPay['id'],
                                    'created_at' => date('Y-m-d H:i:s', time()),
                                    'status' =>2,
                                    'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                return $data;
                            });
                            break;
                        case 2:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }
                }
                $status = $result;
                break;
            case "article":
                $articlePay=ArticlePayModel::where("order_num",$data['code'])->first();
                //获取用户信息
                $userBalance=UserDetailModel::where("uid",$articlePay['uid'])->pluck("balance");
                $result = false;
                if($articlePay) {
                    switch ($articlePay->status) {
                        case 1:
                            $result = DB::transaction(function () use ($data, $articlePay, $type,$userBalance) {
                                ArticlePayModel::where("order_num", $data['code'])->update(["status" => 2, "payment_at" => date("Y-m-d H:i:s")]);
                                ArticleModel::where("id",$articlePay['article_id'])->update(["status"=>0]);
                                //查询是否存在优惠券的
                                $userCouponLog=UserCouponLogModel::where("order_num",$data['code'])->where("status",1)->first();
                                if($userCouponLog){
                                    UserCouponLogModel::where("order_num",$data['code'])->update([
                                        'payment_at'=>date("Y-m-d H:i:s"),
                                        'status' =>2,
                                    ]);
                                    UserCouponModel::where("id",$userCouponLog['user_coupon_id'])->update([
                                        'status'=>2,
                                    ]);
                                }
                                FinancialModel::createOne([
                                    'action' => 7,
                                    'pay_type' => 3,
                                    'cash' => $articlePay['price'],
                                    'uid' => $articlePay['uid'],
                                    'related_id' => $articlePay['id'],
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'status' =>2,
                                    'coupon'=>$userCouponLog?$userCouponLog['price']:0,
                                    'remainder'  =>$userBalance,
                                    'pay_account'=>$data['pay_account'],
                                ]);
                                return $data;
                            });
                            break;
                        case 2:
                            $result = true;
                            break;
                        default:
                            $result = true;
                            break;
                    }
                }
                $status = $result;
                break;
        }

        if (isset($status) && $status )
            //回复微信端请求成功
            return response($content)->header('Content-Type', 'text/xml');
    }


    /**
     * 查询订单状态
     * @param Request $request
     * @return array
     */
    static public function searchOrder(Request $request)
    {
        $type = $request->get('type') ? $request->get('type') : 1;
        if($type == 1){
            $order = OrderModel::where('code',$request->get('orderCode'))->where('status',1)->first();
            if($order){
                return $data = [
                    'code' => 1
                ];
            }
        }
        return $data = [
            'code' => 0
        ];
    }


    /**
     * 查询资讯支付订单状态
     * @param Request $request
     * @return array
     */
    static public function searcharticleOrder(Request $request)
    {
        $type = $request->get('type') ? $request->get('type') : 1;
        if($type == 1){
            $order = ArticlePayModel::where('order_num',$request->get('orderCode'))->where('status',2)->first();
            if($order){
                return $data = [
                    'code' => 1
                ];
            }
        }
        return $data = [
            'code' => 0
        ];
    }
}
