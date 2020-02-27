<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/11/25
 * Time: 15:18
 */
namespace App\Modules\Api\Http\Controllers;

use App\Http\Requests;
use App\Modules\Employ\Models\EmployCommentsModel;
use App\Modules\Employ\Models\EmployGoodsModel;
use App\Modules\Employ\Models\EmployWorkModel;
use App\Modules\Employ\Models\UnionAttachmentModel;
use App\Modules\Employ\Models\UnionRightsModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Order\Model\ShopOrderModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\CommentModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiBaseController;
use Illuminate\Support\Facades\Input;
use Omnipay;
use Validator;
use Illuminate\Support\Facades\Crypt;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Shop\Models\GoodsModel;
use DB;

class EmployController extends ApiBaseController
{
    public function serviceEmploy(Request $request)
    {
        if (!$request->get('id') || !$request->get('uid')) {
            return $this->formateResponse(1018, '传送参数不能为空');
        }
        $id = intval($request->get('id'));
        $uid = intval($request->get('uid'));
        $service = GoodsModel::where('id', $id)->where('type', 2)->where('uid', $uid)->select('id', 'uid', 'title', 'desc', 'cash')->first();
        if (empty($service)) {
            return $this->formateResponse(1019, '传送参数错误');
        }
        $service->desc = htmlspecialchars_decode($service->desc);
        return $this->formateResponse(1000, '获取服务雇佣信息成功', $service);
    }


    /**
     * 创建雇佣
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function createEmploy(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'title' => 'required|max:25',
            'desc' => 'required|max:5000',
            'phone' => 'required',
            'bounty' => 'required|numeric',
            'delivery_deadline' => 'required',
            'employee_uid' => 'required'

        ], [
            'title.required' => '标题不能为空',
            'title.max' => '标题最多25个字符',
            'desc.required' => '需求描述不能为空',
            'desc.max' => '需求描述最多5000字符',
            'phone.required' => '手机号不能为空',
            'bounty.required' => '预算不能为空',
            'bounty.numeric' => '请输入正确的预算格式',
            'delivery_deadline.required' => '截止时间不能为空',
            'employee_uid.required' => '被雇用人id不能为空'
        ]);
        $error = $validator->errors()->all();
        if (count($error)) {
            return $this->formateResponse(1003, $error[0]);
        }
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));

        $data['title'] = $request->get('title');
        $data['desc'] = $request->get('desc');
        $data['phone'] = $request->get('phone');
        $data['bounty'] = $request->get('bounty');
        $time = date('Y-m-d', time());
        $employBountyMinLimit = \CommonClass::getConfig('employ_bounty_min_limit');
        //验证赏金最小值
        $taskBountyMinLimit = $employBountyMinLimit;
        //验证赏金大小合法性
        if ($data['bounty'] < $taskBountyMinLimit) {
            return $this->formateResponse(1003, '参数有误', array('赏金不能小于' . $taskBountyMinLimit));
        }
        //创建一条雇佣记录
        $data['employee_uid'] = intval($request->get('employee_uid'));
        //验证被雇佣者的uid是否是自己
        if($data['employee_uid'] == $tokenInfo['uid']){
            return $this->formateResponse(1003, '参数有误', array('自己不能雇佣自己'));
        }
        $data['employer_uid'] = $tokenInfo['uid'];
        $data['delivery_deadline'] = date('Y-m-d H:i:s', strtotime($request->get('delivery_deadline')));
        $data['status'] = 0;
        $data['created_at'] = $time;
        $data['updated_at'] = $time;
        //服务id（服务雇佣时传值）
        $data['service_id'] = $request->get('service_id') ? $request->get('service_id') : 0;
        //判断
        if ($data['service_id'] != 0) {
            $data['employ_type'] = 1;
        }
        //附件
        $fileIds = $request->get('file_id') ? $request->get('file_id') : '';
        if (!empty($fileIds)) {
            $data['file_id'] = explode(',', $fileIds);
        } else {
            $data['file_id'] = array();
        }
        //创建一条雇佣记录
        $result = EmployModel::employCreate($data);
        if ($result) {
            //创建订单
            $isOrdered = ShopOrderModel::employOrder($tokenInfo['uid'], $data['bounty'], $result);
            if ($isOrdered) {
                $data = array(
                    'employ_id' => $result['id'],
                    'order_id' => $isOrdered['id']
                );
                return $this->formateResponse(1000, '创建成功', $data);
            } else {
                return $this->formateResponse(1002, '创建雇佣订单失败');
            }

        } else {
            return $this->formateResponse(1001, '创建失败');
        }
    }

    /**
     * 余额支付雇佣托管赏金
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function cashPayEmploy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required',
            'employ_id' => 'required',
            'pay_type' => 'required',
            'password' => 'required'
        ], [
            'order_id.required' => '雇佣订单id不能为空',
            'employ_id.required' => '请选择要托管的雇佣',
            'pay_type.required' => '请选择支付方式',
            'password.required' => '请输入支付密码'
        ]);
        $error = $validator->errors()->all();
        if (count($error)) {
            return $this->formateResponse(1003, $error[0]);
        }
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $data = array(
            'id' => $request->get('employ_id'),
            'order_id' => $request->get('order_id'),
            'pay_type' => $request->get('pay_type'),
            'password' => $request->get('password')
        );

        //查询用户发布的数据
        $employ = EmployModel::where('id', $data['id'])->first();

        //判断用户所要支付的是否是自己的任务和任务是否已经支付
        if ($employ['employer_uid'] != $tokenInfo['uid'] || $employ['bounty_status'] != 0) {
            return $this->formateResponse(1002, '该雇佣已托管');
        }

        //查询用户的余额
        $balance = UserDetailModel::where('uid', $tokenInfo['uid'])->first();
        $balance = $balance['balance'];

        //查询订单信息
        $order = ShopOrderModel::where('id', $data['order_id'])->first();

        //判断用户如果选择的余额支付
        if ($balance >= $employ['bounty'] && $data['pay_type'] == 0) {
            //验证用户的密码是否正确
            $user = UserModel::where('id', $tokenInfo['uid'])->first();
            $password = UserModel::encryptPassword($data['password'], $user['salt']);
            if ($password != $user['alternate_password']) {
                return $this->formateResponse(1004, '您的支付密码不正确');
            }
            //支付产生订单
            $res = EmployModel::employBounty($employ['bounty'], $employ['id'], $tokenInfo['uid'], $order->code);
            if ($res) {
                return $this->formateResponse(1000, '支付成功');
            } else {
                return $this->formateResponse(1001, '支付失败，请重新支付');
            }
        } else {
            return $this->formateResponse(1005, '余额不足，请充值或切换支付方式');
        }

    }

    /**
     * 第三方支付雇佣托管赏金
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function ThirdCashEmployPay(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if ($request->get('employ_id') && $request->get('order_id')) {
            $employId = $request->get('employ_id');
            //判断用户是否有支付此任务的权限
            $employ = EmployModel::where('id', $employId)->first();
            if ($employ->employer_uid != $uid || $employ->status >= 2) {
                return $this->formateResponse(1071, '非法操作');
            }
            //查询订单信息
            $order = ShopOrderModel::where('id', $request->get('order_id'))->where('status', 0)->first();
        } else {
            return $this->formateResponse(1002, '缺少参数');
        }


        if ($order) {
            $payType = $request->get('pay_type');
            switch ($payType) {
                case 'alipay':
                    $config = ConfigModel::getConfigByAlias('app_alipay');
                    $info = [];
                    if($config && !empty($config['rule'])){
                        $info = json_decode($config['rule'],true);
                    }

                    if(!isset($info['alipay_type']) || (isset($info['alipay_type']) && $info['alipay_type']== 1)){

                        $alipay = app('alipay.mobile');
                        if(!empty($info) && isset($info['partner_id'])){
                            $alipay->setPartner($info['partner_id']);
                        }
                        if(!empty($info) && isset($info['seller_id'])){
                            $alipay->setSellerId($info['seller_id']);
                        }
                        $alipay->setNotifyUrl(url('api/alipay/notify'));
                        $alipay->setOutTradeNo($order->code);
                        $alipay->setTotalFee($order->cash);
                        $alipay->setSubject($order->title);
                        $alipay->setBody($order->note);
                        return $this->formateResponse(1000, '确认充值', ['payParam' => $alipay->getPayPara()]);
                    }else{
                        $Client = new \AopClient();//实例化支付宝sdk里面的AopClient类,下单时需要的操作,都在这个类里面
                        $seller_id = $appId = '';
                        if(!empty($info) && isset($info['appId'])){
                            $appId = $info['appId'];
                        }
                        if(!empty($info) && isset($info['seller_id'])){
                            $seller_id = $info['seller_id'];
                        }
                        $content = [
                            'seller_id' => $seller_id,
                            'out_trade_no' => $order->code,
                            'timeout_express' => "30m",
                            'subject'      => $order->title,
                            'total_amount'    => $order->cash,
                            'product_code'    => 'QUICK_MSECURITY_PAY',
                        ];
                        $con = json_encode($content);

                        $param['app_id'] = $appId;//'2017121700929928';
                        $param['method'] = 'alipay.trade.app.pay';//接口名称，固定值
                        $param['charset'] = 'utf-8';//请求使用的编码格式
                        $param['sign_type'] = 'RSA';//商户生成签名字符串所使用的签名算法类型
                        $param['timestamp'] = date("Y-m-d H:i:s");//发送请求的时间
                        $param['version'] = '1.0';//调用的接口版本，固定为：1.0
                        $param['notify_url'] = url('api/alipay/notify');
                        $param['biz_content'] = $con;//业务请求参数的集合,长度不限,json格式，即前面一步得到的
                        $private_path = storage_path('app/alipay/rsa_private_key.pem');
                        $paramStr = $Client->getSignContent($param);//组装请求签名参数
                        $sign = $Client->alonersaSign($paramStr, $private_path, 'RSA', true);//生成签名
                        $param['sign'] = $sign;
                        $str = $Client->getSignContentUrlencode($param);//最终请求参数
                        return $this->formateResponse(1000, '确认充值', ['payParam' => $str]);
                    }

                    break;
                case 'wechat':
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
                    $gateway->setNotifyUrl(url('api/wechatpay/notify'));
                    $data = [
                        'body' => $order->title,
                        'out_trade_no' => $order->code,
                        'total_fee' => $order->cash * 100, //=0.01
                        'spbill_create_ip' => Input::getClientIp(),
                        'fee_type' => 'CNY'
                    ];
                    $request = $gateway->purchase($data);
                    $response = $request->send();
                    if ($response->isSuccessful()) {
                        return $this->formateResponse(1000, '确认支付', ['params' => $response->getAppOrderData()]);
                    }
                    break;
            }
        } else {
            return $this->formateResponse(1072, '订单不存在或已经支付');
        }
    }


    /**
     * 获取雇佣订单另一方用户详情信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function employUserDetail(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if (!$request->get('employ_id')) {
            return $this->formateResponse(1002, '缺少参数');
        }

        $id = $request->get('employ_id');
        //查询当前
        $employ = EmployModel::where('id', $id)->first();
        //判断是否是一个雇佣任务
        if (empty($employ)) {
            return $this->formateResponse(1003, '参数错误');
        }
        $cash = $employ->bounty;
        $deliveryDeadline = $employ->delivery_deadline;
        $title = $employ->title;
        $type = 0;
        //判断是雇用者还是被雇佣者
        if ($uid == $employ->employer_uid) {
            $type = 1;//我是雇主
        } elseif ($uid == $employ->employee_uid) {
            $type = 2;//我是威客
        }
        $domain = \CommonClass::getDomain();
        $cardType = '';
        $status = '';
        $deal = '';
        $buttonStatus = 0;
        $shopId = '';
        $userId = '';
        if (isset($type)) {
            switch ($type) {
                case 1:
                    //查询店铺id
                    $shop = ShopModel::where('uid',$employ->employee_uid)->first();
                    if(!empty($shop)){
                        $shopId = $shop->id;
                    }
                    $cardType = '威客';
                    $userId = $employ->employee_uid;
                    //查询威客的店铺信息
                    $user = UserModel::where('users.id', $employ->employee_uid)
                        ->leftJoin('user_detail', 'user_detail.uid', '=', 'users.id')
                        ->select('users.id', 'users.name', 'user_detail.avatar','users.email_status')->first();
                    //好评率
                    $comments = CommentModel::where('to_uid',$employ->employee_uid)->count();
                    $good_comments = CommentModel::where('to_uid',$employ->employee_uid)->where('type',1)->count();
                    if($comments==0){
                        $applause_rate = 100;
                    }else{
                        $applause_rate = floor(($good_comments/$comments)*100);
                    }
                    $applauseRate = $applause_rate;
                    //完成任务数
                    $complete = $comments;

                    //用户认证关系
                    $userAuthOne = AuthRecordModel::select('auth_code')->where('uid', $employ->employee_uid)->where('status', 2)
                        ->whereIn('auth_code',['bank','alipay'])->get()->toArray();
                    $userAuthOne = array_flatten($userAuthOne);
                    $userAuthTwo = AuthRecordModel::select('auth_code')->where('uid', $employ->employee_uid)->where('status', 1)
                        ->whereIn('auth_code',['realname','enterprise'])->get()->toArray();
                    $userAuthTwo = array_flatten($userAuthTwo);
                    $emailAuth = [];

                    if($user->email_status == 2){
                        $emailAuth = ['email'];
                    }

                    $userAuth = array_unique(array_merge($userAuthOne,$userAuthTwo,$emailAuth));
                    //查询双方互评（对我和给他）
                    //对我
                    $commentToMe = EmployCommentsModel::where('employ_id', $id)
                        ->where('from_uid', $employ->employee_uid)
                        ->where('to_uid', $uid)->first();
                    if(!empty($commentToMe)){
                        $userToMe = $user;
                        if(!empty($userToMe)){
                            $commentToMe->username = $userToMe->name;
                            $commentToMe->avatar = $domain.'/'.$userToMe->avatar;
                        }else{
                            $commentToMe->username = '';
                            $commentToMe->avatar = '';
                        }
                    }
                    switch ($employ->status) {
                        case 0:
                            $status = '待受理';
                            $deal = '等待受理';
                            $buttonStatus = 0;
                            break;
                        case 1:
                            $status = '工作中';
                            $deal = '工作中';
                            $buttonStatus = 1;
                            break;
                        case 2:
                            $status = '验收中';
                            $deal = '处理作品';
                            $buttonStatus = 2;
                            break;
                        case 3:
                            $status = '已完成';
                            $deal = '给予评价';
                            $buttonStatus = 3;
                            break;
                        case 4:
                            $status = '已完成';
                            $deal = '完成交易';
                            $buttonStatus = 4;
                            break;
                        case 5:
                            $status = '已失败';
                            $deal = '已被拒绝';
                            $buttonStatus = 5;
                            break;
                        case 6:
                            $status = '已失败';
                            $deal = '取消任务';
                            $buttonStatus = 5;
                            break;
                        case 7:
                            $status = '维权中';
                            $deal = '维权中';
                            $buttonStatus = 6;
                            break;
                        case 8:
                            $status = '维权中';
                            $deal = '维权中';
                            $buttonStatus = 6;
                            break;
                        case 9:
                            $status = '已失败';
                            $deal = '雇佣过期';
                            $buttonStatus = 10;
                            break;
                    }
                    break;
                case 2:
                    //查询店铺id
                    $shop = ShopModel::where('uid',$uid)->first();
                    if(!empty($shop)){
                        $shopId = $shop->id;
                    }
                    $cardType = '雇主';
                    //查询雇主的店铺信息
                    $user = UserModel::where('users.id', $employ->employer_uid)
                        ->leftJoin('user_detail', 'user_detail.uid', '=', 'users.id')
                        ->select('users.id', 'users.name', 'user_detail.avatar','users.email_status')->first();
                    $userId = $employ->employer_uid;

                    //好评率
                    $comments = CommentModel::where('to_uid',$employ->employer_uid)->count();
                    $good_comments = CommentModel::where('to_uid',$employ->employer_uid)->where('type',1)->count();
                    if($comments==0){
                        $applause_rate = 100;
                    }else{
                        $applause_rate = floor(($good_comments/$comments)*100);
                    }
                    $applauseRate = $applause_rate;
                    //完成任务数
                    $complete = $comments;

                    //用户认证关系
                    $userAuthOne = AuthRecordModel::select('auth_code')->where('uid', $employ->employer_uid)->where('status', 2)
                        ->whereIn('auth_code',['bank','alipay'])->get()->toArray();
                    $userAuthOne = array_flatten($userAuthOne);
                    $userAuthTwo = AuthRecordModel::select('auth_code')->where('uid', $employ->employer_uid)->where('status', 1)
                        ->whereIn('auth_code',['realname','enterprise'])->get()->toArray();
                    $userAuthTwo = array_flatten($userAuthTwo);
                    $emailAuth = [];

                    if($user->email_status == 2){
                        $emailAuth = ['email'];
                    }

                    $userAuth = array_unique(array_merge($userAuthOne,$userAuthTwo,$emailAuth));

                    switch ($employ->status) {
                        case 0:
                            $status = '待受理';
                            $deal = '处理委托';
                            $buttonStatus = 7;
                            break;
                        case 1:
                            $status = '工作中';
                            $deal = '上传作品';
                            $buttonStatus = 8;
                            break;
                        case 2:
                            $status = '验收中';
                            $deal = '等待处理';
                            $buttonStatus = 9;
                            break;
                        case 3:
                            $status = '已完成';
                            $deal = '给予评价';
                            $buttonStatus = 3;
                            break;
                        case 4:
                            $status = '已完成';
                            $deal = '任务完成';
                            $buttonStatus = 4;
                            break;
                        case 5:
                            $status = '已失败';
                            $deal = '已经拒绝';
                            $buttonStatus = 5;
                            break;
                        case 6:
                            $status = '已失败';
                            $deal = '已被取消';
                            $buttonStatus = 5;
                            break;
                        case 7:
                            $status = '维权中';
                            $deal = '维权中';
                            $buttonStatus = 6;
                            break;
                        case 8:
                            $status = '维权中';
                            $deal = '维权中';
                            $buttonStatus = 6;
                            break;
                        case 9:
                            $status = '已失败';
                            $deal = '雇佣过期';
                            $buttonStatus = 10;
                            break;
                    }
                    break;
            }
        }
        $username = '';
        $avatar = '';
        if (isset($user) && !empty($user)) {
            $username = $user->name;
            $avatar = $domain . '/' . $user->avatar;
        }
        if(in_array('enterprise',$userAuth)){
            $isEnterprise = 1;
        }else{
            $isEnterprise = 0;
        }
        if(in_array('bank',$userAuth)){
            $bank = 1;
        }else{
            $bank = 0;
        }
        if(in_array('alipay',$userAuth)){
            $alipay = 1;
        }else{
            $alipay = 0;
        }
        if(in_array('email',$userAuth)){
            $email = 1;
        }else{
            $email = 0;
        }
        if(in_array('realname',$userAuth)){
            $realname = 1;
        }else{
            $realname = 0;
        }
        //计算剩余时间
        $days = (strtotime($employ->delivery_deadline) - time()) / (3600 * 24) > 0 ? (strtotime($employ->delivery_deadline) - time()) / (3600 * 24) : 0;
        if($days){
            $d = floor($days);
            $h = intval(($days-$d)*24);
            $days = $d.'天'.$h.'小时';
        }else{
            $days = '0天';
        }

        $data = array(
            'employ_id' => $id,
            'shop_id' => $shopId,
            'user_id' => $userId,
            'type' => $type,
            'card_type' => $cardType,
            'status' => $status,
            'button_word' => $deal,
            'button_status' => $buttonStatus,
            'days' => $days,
            'username' => $username,
            'avatar' => $avatar,
            'applauseRate' => isset($applauseRate) ? $applauseRate : 100,
            'complete' => isset($complete) ? $complete : 0,
            'isEnterprise' => isset($isEnterprise) ? $isEnterprise : 0,
            'bank' => isset($bank) ? $bank : 0,
            'alipay' => isset($alipay) ? $alipay : 0,
            'email' => isset($email) ? $email : 0,
            'realname' => isset($realname) ? $realname : 0,
            'cash' => $cash,
            'delivery_deadline' => $deliveryDeadline,
            'title' => $title

        );
        return $this->formateResponse(1000, '获取雇佣订单详情信息成功', $data);
    }

    /**
     * 获取雇佣订单服务详情信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function employServiceDetail(Request $request)
    {
        if (!$request->get('employ_id')) {
            return $this->formateResponse(1002, '缺少参数');
        }
        $id = $request->get('employ_id');
        //查询当前
        $employ = EmployModel::where('id', $id)->first();
        //判断是否是一个雇佣任务
        if (empty($employ)) {
            return $this->formateResponse(1003, '参数错误');
        }
        $employ->desc = htmlspecialchars_decode($employ->desc);
        $domain = \CommonClass::getDomain();
        //查询雇用附件
        $employAtt = UnionAttachmentModel::where('object_type', 2)->where('object_id', $id)
            ->select('attachment_id')->get()->toArray();
        $employAttachment = array();
        if (!empty($employAtt)) {
            //获取附件关联id
            $attId = array_flatten($employAtt);
            if (!empty($attId)) {
                //查询附件信息
                $employAttachment = AttachmentModel::whereIn('id', $attId)->get()->toArray();
                if (!empty($employAttachment)) {
                    foreach ($employAttachment as $k => $v) {
                        $employAttachment[$k]['url'] = $domain . '/' . $v['url'];
                    }
                }
            }
        }
        $data = array(
            'title' => $employ->title,
            'desc' => $employ->desc,
            'employ_att' => $employAttachment,

        );
        return $this->formateResponse(1000, '获取雇佣订单服务详情信息成功', $data);

    }


    /**
     * 获取雇佣订单作品详情信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function employWorkDetail(Request $request)
    {
        if (!$request->get('employ_id')) {
            return $this->formateResponse(1002, '缺少参数');
        }
        $id = $request->get('employ_id');
        //查询当前
        $employ = EmployModel::where('id', $id)->first();
        //判断是否是一个雇佣任务
        if (empty($employ)) {
            return $this->formateResponse(1003, '参数错误');
        }
        $domain = \CommonClass::getDomain();
        //查询威客作品信息（根据雇用id和威客id）
        $work = EmployWorkModel::where('employ_id', $id)->where('uid', $employ->employee_uid)->first();
        $workAtt = array();
        if (!empty($work)) {
            //查询作品附件
            $workAtt = UnionAttachmentModel::where('object_type', 3)
                ->where('object_id', $work->id)
                ->select('attachment_id')->get()->toArray();
        }
        $attachment = array();
        if (!empty($workAtt)) {
            //获取附件关联id
            $workId = array_flatten($workAtt);
            if (!empty($workId)) {
                //查询附件信息
                $attachment = AttachmentModel::whereIn('id', $workId)->get()->toArray();
                if (!empty($attachment)) {
                    foreach ($attachment as $k => $v) {
                        $attachment[$k]['url'] = $domain . '/' . $v['url'];
                    }
                }
            }
        }

        $data = array(
            'work' => $work,
            'work_att' => $attachment

        );
        return $this->formateResponse(1000, '获取雇佣订单作品详情信息成功', $data);

    }


    /**
     * 获取雇佣订单详情评价信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function employCommentDetail(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if (!$request->get('employ_id')) {
            return $this->formateResponse(1002, '缺少参数');
        }

        $id = $request->get('employ_id');
        //查询当前
        $employ = EmployModel::where('id', $id)->first();
        //判断是否是一个雇佣任务
        if (empty($employ)) {
            return $this->formateResponse(1003, '参数错误');
        }
        $type = 0;
        //判断是雇用者还是被雇佣者
        if ($uid == $employ->employer_uid) {
            $type = 1;//我是雇主
        } elseif ($uid == $employ->employee_uid) {
            $type = 2;//我是威客
        }
        $domain = \CommonClass::getDomain();
        $commentToMe = array();
        $commentToHe = array();
        if (isset($type)) {
            switch ($type) {
                case 1:
                    //查询威客的店铺信息
                    $user = UserModel::where('users.id', $employ->employee_uid)
                        ->leftJoin('user_detail', 'user_detail.uid', '=', 'users.id')
                        ->select('users.id', 'users.name', 'user_detail.avatar')->first();
                    //查询双方互评（对我和给他）
                    //对我
                    $commentToMe = EmployCommentsModel::where('employ_id', $id)
                        ->where('from_uid', $employ->employee_uid)
                        ->where('to_uid', $uid)->first();
                    if(!empty($commentToMe)){
                        $userToMe = $user;
                        if(!empty($userToMe)){
                            $commentToMe->username = $userToMe->name;
                            $commentToMe->avatar = $domain.'/'.$userToMe->avatar;
                        }else{
                            $commentToMe->username = '';
                            $commentToMe->avatar = '';
                        }
                    }
                    //对他
                    $commentToHe = EmployCommentsModel::where('employ_id', $id)
                        ->where('from_uid', $uid)
                        ->where('to_uid', $employ->employee_uid)->first();
                    if(!empty($commentToHe)){
                        $userToHe = UserModel::where('users.id', $uid)
                            ->leftJoin('user_detail', 'user_detail.uid', '=', 'users.id')
                            ->select('users.id', 'users.name', 'user_detail.avatar')->first();
                        if(!empty($userToHe)){
                            $commentToHe->username = $userToHe->name;
                            $commentToHe->avatar = $domain.'/'.$userToHe->avatar;
                        }else{
                            $commentToHe->username = '';
                            $commentToHe->avatar = '';
                        }
                    }
                    break;
                case 2:
                    //查询雇主的店铺信息
                    $user = UserModel::where('users.id', $employ->employer_uid)
                        ->leftJoin('user_detail', 'user_detail.uid', '=', 'users.id')
                        ->select('users.id', 'users.name', 'user_detail.avatar')->first();
                    //查询双方互评（对我和给他）
                    //对我
                    $commentToMe = EmployCommentsModel::where('employ_id', $id)
                        ->where('from_uid', $employ->employer_uid)
                        ->where('to_uid', $uid)->first();
                    if(!empty($commentToMe)){
                        $userToMe = $user;
                        if(!empty($userToMe)){
                            $commentToMe->username = $userToMe->name;
                            $commentToMe->avatar = $domain.'/'.$userToMe->avatar;
                        }else{
                            $commentToMe->username = '';
                            $commentToMe->avatar = '';
                        }
                    }
                    //对他
                    $commentToHe = EmployCommentsModel::where('employ_id', $id)
                        ->where('from_uid', $uid)
                        ->where('to_uid', $employ->employer_uid)->first();
                    if(!empty($commentToHe)){
                        $userToHe = UserModel::where('users.id', $uid)
                            ->leftJoin('user_detail', 'user_detail.uid', '=', 'users.id')
                            ->select('users.id', 'users.name', 'user_detail.avatar')->first();
                        if(!empty($userToHe)){
                            $commentToHe->username = $userToHe->name;
                            $commentToHe->avatar = $domain.'/'.$userToHe->avatar;
                        }else{
                            $commentToHe->username = '';
                            $commentToHe->avatar = '';
                        }
                    }

                    break;
            }
        }

        $data = array(
            'type' => $type,
            'comment_to_me' => $commentToMe,
            'comment_to_he' => $commentToHe,

        );
        return $this->formateResponse(1000, '获取雇佣订单详情评价信息成功', $data);

    }



    /**
     * 获取雇佣订单详情信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function employDetail(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if (!$request->get('employ_id')) {
            return $this->formateResponse(1002, '缺少参数');
        }

        $id = $request->get('employ_id');
        //查询当前
        $employ = EmployModel::where('id', $id)->first();
        //判断是否是一个雇佣任务
        if (empty($employ)) {
            return $this->formateResponse(1003, '参数错误');
        }
        $employ->desc = htmlspecialchars_decode($employ->desc);
        $type = 0;
        //判断是雇用者还是被雇佣者
        if ($uid == $employ->employer_uid) {
            $type = 1;//我是雇主

        } elseif ($uid == $employ->employee_uid) {
            $type = 2;//我是威客
        }
        $domain = \CommonClass::getDomain();
        $commentToMe = array();
        $commentToHe = array();
        $cardType = '';
        $status = '';
        $deal = '';
        $buttonStatus = 0;
        $shopId = '';
        $userAuth = [];
        if (isset($type)) {
            switch ($type) {
                case 1:
                    //查询店铺id
                    $shop = ShopModel::where('uid',$employ->employee_uid)->first();
                    if(!empty($shop)){
                        $shopId = $shop->id;
                    }
                    $cardType = '威客';
                    //查询威客的店铺信息
                    $user = UserModel::where('users.id', $employ->employee_uid)
                        ->leftJoin('user_detail', 'user_detail.uid', '=', 'users.id')
                        ->select('users.id', 'users.name', 'user_detail.avatar','users.email_status')->first();

                    //好评率
                    $comments = CommentModel::where('to_uid',$employ->employee_uid)->count();
                    $good_comments = CommentModel::where('to_uid',$employ->employee_uid)->where('type',1)->count();
                    if($comments==0){
                        $applause_rate = 100;
                    }else{
                        $applause_rate = floor(($good_comments/$comments)*100);
                    }
                    $applauseRate = $applause_rate;
                    //完成任务数
                    $complete = $comments;

                    //用户认证关系
                    $userAuthOne = AuthRecordModel::select('auth_code')->where('uid', $employ->employee_uid)->where('status', 2)
                        ->whereIn('auth_code',['bank','alipay'])->get()->toArray();
                    $userAuthOne = array_flatten($userAuthOne);
                    $userAuthTwo = AuthRecordModel::select('auth_code')->where('uid', $employ->employee_uid)->where('status', 1)
                        ->whereIn('auth_code',['realname','enterprise'])->get()->toArray();
                    $userAuthTwo = array_flatten($userAuthTwo);
                    $emailAuth = [];

                    if($user->email_status == 2){
                        $emailAuth = ['email'];
                    }

                    $userAuth = array_unique(array_merge($userAuthOne,$userAuthTwo,$emailAuth));
                    //查询双方互评（对我和给他）
                    //对我
                    $commentToMe = EmployCommentsModel::where('employ_id', $id)
                        ->where('from_uid', $employ->employee_uid)
                        ->where('to_uid', $uid)->first();
                    if(!empty($commentToMe)){
                        $userToMe = $user;
                        if(!empty($userToMe)){
                            $commentToMe->username = $userToMe->name;
                            $commentToMe->avatar = $domain.'/'.$userToMe->avatar;
                        }else{
                            $commentToMe->username = '';
                            $commentToMe->avatar = '';
                        }
                    }
                    //对他
                    $commentToHe = EmployCommentsModel::where('employ_id', $id)
                        ->where('from_uid', $uid)
                        ->where('to_uid', $employ->employee_uid)->first();
                    if(!empty($commentToHe)){
                        $userToHe = UserModel::where('users.id', $uid)
                            ->leftJoin('user_detail', 'user_detail.uid', '=', 'users.id')
                            ->select('users.id', 'users.name', 'user_detail.avatar')->first();
                        if(!empty($userToHe)){
                            $commentToHe->username = $userToHe->name;
                            $commentToHe->avatar = $domain.'/'.$userToHe->avatar;
                        }else{
                            $commentToHe->username = '';
                            $commentToHe->avatar = '';
                        }
                    }
                    switch ($employ->status) {
                        case 0:
                            $status = '待受理';
                            $deal = '等待受理';
                            $buttonStatus = 0;
                            break;
                        case 1:
                            $status = '工作中';
                            $deal = '工作中';
                            $buttonStatus = 1;
                            break;
                        case 2:
                            $status = '验收中';
                            $deal = '处理作品';
                            $buttonStatus = 2;
                            break;
                        case 3:
                            $status = '已完成';
                            $deal = '给予评价';
                            $buttonStatus = 3;
                            break;
                        case 4:
                            $status = '已完成';
                            $deal = '完成交易';
                            $buttonStatus = 4;
                            break;
                        case 5:
                            $status = '已失败';
                            $deal = '已被拒绝';
                            $buttonStatus = 5;
                            break;
                        case 6:
                            $status = '已失败';
                            $deal = '取消任务';
                            $buttonStatus = 5;
                            break;
                        case 7:
                            $status = '维权中';
                            $deal = '维权中';
                            $buttonStatus = 6;
                            break;
                        case 8:
                            $status = '维权中';
                            $deal = '维权中';
                            $buttonStatus = 6;
                            break;
                        case 9:
                            $status = '已失败';
                            $deal = '雇佣过期';
                            $buttonStatus = 10;
                            break;
                    }
                    break;
                case 2:
                    //查询店铺id
                    $shop = ShopModel::where('uid',$uid)->first();
                    if(!empty($shop)){
                        $shopId = $shop->id;
                    }
                    $cardType = '雇主';
                    //查询雇主的店铺信息
                    $user = UserModel::where('users.id', $employ->employer_uid)
                        ->leftJoin('user_detail', 'user_detail.uid', '=', 'users.id')
                        ->select('users.id', 'users.name', 'user_detail.avatar','users.email_status')->first();

                    //好评率
                    $comments = CommentModel::where('to_uid',$employ->employer_uid)->count();
                    $good_comments = CommentModel::where('to_uid',$employ->employer_uid)->where('type',1)->count();
                    if($comments==0){
                        $applause_rate = 100;
                    }else{
                        $applause_rate = floor(($good_comments/$comments)*100);
                    }
                    $applauseRate = $applause_rate;
                    //完成任务数
                    $complete = $comments;

                    //用户认证关系
                    $userAuthOne = AuthRecordModel::select('auth_code')->where('uid', $employ->employer_uid)->where('status', 2)
                        ->whereIn('auth_code',['bank','alipay'])->get()->toArray();
                    $userAuthOne = array_flatten($userAuthOne);
                    $userAuthTwo = AuthRecordModel::select('auth_code')->where('uid', $employ->employer_uid)->where('status', 1)
                        ->whereIn('auth_code',['realname','enterprise'])->get()->toArray();
                    $userAuthTwo = array_flatten($userAuthTwo);
                    $emailAuth = [];

                    if($user->email_status == 2){
                        $emailAuth = ['email'];
                    }

                    $userAuth = array_unique(array_merge($userAuthOne,$userAuthTwo,$emailAuth));

                    //查询双方互评（对我和给他）
                    //对我
                    $commentToMe = EmployCommentsModel::where('employ_id', $id)
                        ->where('from_uid', $employ->employer_uid)
                        ->where('to_uid', $uid)->first();
                    if(!empty($commentToMe)){
                        $userToMe = $user;
                        if(!empty($userToMe)){
                            $commentToMe->username = $userToMe->name;
                            $commentToMe->avatar = $domain.'/'.$userToMe->avatar;
                        }else{
                            $commentToMe->username = '';
                            $commentToMe->avatar = '';
                        }
                    }
                    //对他
                    $commentToHe = EmployCommentsModel::where('employ_id', $id)
                        ->where('from_uid', $uid)
                        ->where('to_uid', $employ->employer_uid)->first();
                    if(!empty($commentToHe)){
                        $userToHe = UserModel::where('users.id', $uid)
                            ->leftJoin('user_detail', 'user_detail.uid', '=', 'users.id')
                            ->select('users.id', 'users.name', 'user_detail.avatar')->first();
                        if(!empty($userToHe)){
                            $commentToHe->username = $userToHe->name;
                            $commentToHe->avatar = $domain.'/'.$userToHe->avatar;
                        }else{
                            $commentToHe->username = '';
                            $commentToHe->avatar = '';
                        }
                    }

                    switch ($employ->status) {
                        case 0:
                            $status = '待受理';
                            $deal = '处理委托';
                            $buttonStatus = 7;
                            break;
                        case 1:
                            $status = '工作中';
                            $deal = '上传作品';
                            $buttonStatus = 8;
                            break;
                        case 2:
                            $status = '验收中';
                            $deal = '等待处理';
                            $buttonStatus = 9;
                            break;
                        case 3:
                            $status = '已完成';
                            $deal = '给予评价';
                            $buttonStatus = 3;
                            break;
                        case 4:
                            $status = '已完成';
                            $deal = '任务完成';
                            $buttonStatus = 4;
                            break;
                        case 5:
                            $status = '已失败';
                            $deal = '已经拒绝';
                            $buttonStatus = 5;
                            break;
                        case 6:
                            $status = '已失败';
                            $deal = '已被取消';
                            $buttonStatus = 5;
                            break;
                        case 7:
                            $status = '维权中';
                            $deal = '维权中';
                            $buttonStatus = 6;
                            break;
                        case 8:
                            $status = '维权中';
                            $deal = '维权中';
                            $buttonStatus = 6;
                            break;
                        case 9:
                            $status = '已失败';
                            $deal = '雇佣过期';
                            $buttonStatus = 10;
                            break;
                    }
                    break;
            }
        }
        $username = '';
        $avatar = '';
        if (isset($user) && !empty($user)) {
            $username = $user->name;
            $avatar = $domain . '/' . $user->avatar;
        }

        if(in_array('enterprise',$userAuth)){
            $isEnterprise = 1;
        }else{
            $isEnterprise = 0;
        }
        if(in_array('bank',$userAuth)){
            $bank = 1;
        }else{
            $bank = 0;
        }
        if(in_array('alipay',$userAuth)){
            $alipay = 1;
        }else{
            $alipay = 0;
        }
        if(in_array('email',$userAuth)){
            $email = 1;
        }else{
            $email = 0;
        }
        if(in_array('realname',$userAuth)){
            $realname = 1;
        }else{
            $realname = 0;
        }

        //计算剩余时间
        $days = intval((strtotime($employ->delivery_deadline) - time()) / (3600 * 24)) > 0 ? intval((strtotime($employ->delivery_deadline) - time()) / (3600 * 24)) : 0;
        //查询威客作品信息（根据雇用id和威客id）
        $work = EmployWorkModel::where('employ_id', $id)->where('uid', $employ->employee_uid)->first();
        $workAtt = array();
        if (!empty($work)) {
            //查询作品附件
            $workAtt = UnionAttachmentModel::where('object_type', 3)
                ->where('object_id', $work->id)
                ->select('attachment_id')->get()->toArray();
        }
        $attachment = array();
        if (!empty($workAtt)) {
            //获取附件关联id
            $workId = array_flatten($workAtt);
            if (!empty($workId)) {
                //查询附件信息
                $attachment = AttachmentModel::whereIn('id', $workId)->get()->toArray();
                if (!empty($attachment)) {
                    foreach ($attachment as $k => $v) {
                        $attachment[$k]['url'] = $domain . '/' . $v['url'];
                    }
                }
            }
        }
        //查询雇用附件
        $employAtt = UnionAttachmentModel::where('object_type', 2)->where('object_id', $id)
            ->select('attachment_id')->get()->toArray();
        $employAttachment = array();
        if (!empty($employAtt)) {
            //获取附件关联id
            $attId = array_flatten($employAtt);
            if (!empty($attId)) {
                //查询附件信息
                $employAttachment = AttachmentModel::whereIn('id', $attId)->get()->toArray();
                if (!empty($employAttachment)) {
                    foreach ($employAttachment as $k => $v) {
                        $employAttachment[$k]['url'] = $domain . '/' . $v['url'];
                    }
                }
            }
        }
        $data = array(
            'shop_id' => $shopId,
            'type' => $type,
            'card_type' => $cardType,
            'status' => $status,
            'button_word' => $deal,
            'button_status' => $buttonStatus,
            'days' => $days,
            'username' => $username,
            'avatar' => $avatar,
            'applauseRate' => isset($applauseRate) ? $applauseRate : 100,
            'complete' => isset($complete) ? $complete : 0,
            'isEnterprise' => isset($isEnterprise) ? $isEnterprise : 0,
            'bank' => isset($bank) ? $bank : 0,
            'alipay' => isset($alipay) ? $alipay : 0,
            'email' => isset($email) ? $email : 0,
            'realname' => isset($realname) ? $realname : 0,
            'employ' => $employ,
            'employ_att' => $employAttachment,
            'comment_to_me' => $commentToMe,
            'comment_to_he' => $commentToHe,
            'work' => $work,
            'work_att' => $attachment

        );
        return $this->formateResponse(1000, '获取雇佣订单详情信息成功', $data);

    }

    /**
     * 处理雇佣接口（1：雇主取消 2：威客接受 3：威客拒绝）
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function dealEmploy(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if (!$request->get('employ_id') || !$request->get('type')) {
            return $this->formateResponse(1002, '缺少参数');
        }
        $type = $request->get('type'); //1：雇主取消 2：威客接受 3：威客拒绝
        $id = $request->get('employ_id');
        $result = EmployModel::employHandle($type, $id, $uid);

        if (!$result) {
            return $this->formateResponse(1001, '操作失败');
        }
        return $this->formateResponse(1000, '操作成功');

    }

    /**
     * 威客投稿
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function workCreate(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if (!$request->get('employ_id') || !$request->get('desc')) {
            return $this->formateResponse(1002, '缺少参数');
        }
        //作品内容
        $data['desc'] = \CommonClass::removeXss($request->get('desc'));
        $data['employ_id'] = $request->get('employ_id');
        //判断当前用户是否是被雇佣者
        $employ_id = intval($data['employ_id']);
        $employ = EmployModel::where('id', $employ_id)->where('employee_uid', $uid)->first();
        if (!$employ)
            return $this->formateResponse(1003, '你不是被雇佣者不需要交付当前雇佣稿件！');
        //判断当前稿件是否处于投稿期间
        if ($employ['status'] != 1) {
            return $this->formateResponse(1004, '当前雇佣不是处于交稿状态！');
        }
        if ($request->get('file_id')) {
            $data['file_id'] = explode(',', $request->get('file_id'));
        }
        //创建一条work记录，修改当前任务状态
        $result = EmployWorkModel::employDilivery($data, $uid);

        if (!$result) {
            return $this->formateResponse(1001, '投稿失败');
        }
        return $this->formateResponse(1000, '投稿成功');
    }

    /**
     * 雇主验收
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function acceptEmployWork(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if (!$request->get('employ_id')) {
            return $this->formateResponse(1002, '缺少参数');
        }
        $id = $request->get('employ_id');
        //验证当前任务验收合法性
        $employ = EmployModel::where('id', $id)->first();
        if ($employ['status'] != 2)
            return $this->formateResponse(1004, '当前任务不是处于验收状态！');

        if ($employ['employer_uid'] != $uid)
            return $this->formateResponse(1003, '你不是当前雇佣任务的雇主，不能验收！');

        //验收操作
        $result = EmployModel::acceptWork($id, $uid);
        if (!$result) {
            return $this->formateResponse(1001, '验收失败');
        }
        return $this->formateResponse(1000, '验收成功');
    }


    /**
     * 雇主或威客维权
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function employRights(Request $request)
    {
        //判断当前用户的角色是雇主还是被雇佣人和游客
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if (!$request->get('employ_id') || !$request->get('type') || !$request->get('desc')) {
            return $this->formateResponse(1002, '缺少参数');
        }
        $id = $request->get('employ_id');
        $type = $request->get('type');
        $desc = $request->get('desc');
        //查询当前
        $employ = EmployModel::where('id', $id)->first();
        //判断是否是一个雇佣任务
        if (empty($employ)) {
            return $this->formateResponse(1003, '参数错误！');
        }

        if ($employ['employer_uid'] == $uid) {
            $role = 1;//表示雇主
            $to_uid = $employ['employee_uid'];
        } else if ($employ['employee_uid'] == $uid) {
            $role = 2;//表示威客
            $to_uid = $employ['employer_uid'];
        } else {
            return $this->formateResponse(1003, '参数错误！');
        }
        $employ_rights = [
            'type' => intval($type),
            'object_id' => intval($id),
            'object_type' => 1,
            'desc' => \CommonClass::removeXss($desc),
            'status' => 0,
            'from_uid' => $uid,
            'to_uid' => $to_uid,
            'created_at' => date('Y-m-d H:i:s', time()),
        ];
        $result = UnionRightsModel::employRights($employ_rights, $role);

        if (!$result) {
            return $this->formateResponse(1001, '维权提交失败');
        }
        return $this->formateResponse(1000, '维权提交成功');

    }


    /**
     * 雇佣评价 （威客对雇主二维评价  雇主对威客三维评价）
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function employEvaluate(Request $request)
    {

        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $uid = $tokenInfo['uid'];
        if(!$request->get('employ_id') || !$request->get('comment') || !$request->get('speed_score') || !$request->get('quality_score') || !$request->get('type')){
            return $this->formateResponse(1002, '缺少参数');
        }
        $data = array(
            'employ_id' => $request->get('employ_id'),
            'comment' => $request->get('comment'),
            'speed_score' => $request->get('speed_score'),
            'quality_score' => $request->get('quality_score'),
            'attitude_score' => $request->get('attitude_score')?intval($request->get('attitude_score')):0,
            'type' => $request->get('type'),
        );
        //判断当前雇佣任务是否处于评价阶段
        $employ = EmployModel::where('id', $data['employ_id'])->first();
        if ($employ['status'] != 3) {
            return $this->formateResponse(1004, '当前任务不是处于评价状态！');
        }
        //判断当前角色
        if ($employ['employer_uid'] == $uid) {
            if(!$request->get('attitude_score')){
                return $this->formateResponse(1002, '缺少参数');
            }
            $comment_by = 1;//评价来自雇主
            $to_uid = $employ['employee_uid'];
        } else if ($employ['employee_uid'] == $uid) {
            $comment_by = 0;//评价来自威客
            $to_uid = $employ['employer_uid'];
        } else {
            return $this->formateResponse(1003, '你不是雇主也不是被雇佣的威客，不能评价！');
        }
        //判断是否已经进行评价
        $isComment = EmployCommentsModel::where('from_uid',$uid)->where('to_uid',$to_uid)->where('employ_id',$data['employ_id'])->first();
        if($isComment){
            return $this->formateResponse(1005, '你已经评价过，不能再次评价！');
        }
        //创建评价
        $evaluate_data = [
            'employ_id' => intval($data['employ_id']),
            'from_uid' => $uid,
            'to_uid' => $to_uid,
            'comment' => $data['comment'],
            'comment_by' => $comment_by,
            'speed_score' => intval($data['speed_score']),
            'quality_score' => intval($data['quality_score']),
            'attitude_score' => isset($data['attitude_score']) ? intval($data['attitude_score']) : 0,
            'type' => intval($data['type']),
            'created_at' => date('Y-m-d H:i:s', time()),
        ];

        $result = EmployCommentsModel::serviceCommentsCreate($evaluate_data, intval($data['employ_id']));

        if (!$result)
            return $this->formateResponse(1001, '评论失败');

        //增加服务的总评价数量和好评数
        if ($employ['employer_uid'] == $uid && $employ['employ_type'] == 1) {
            //查询当前雇佣是来源于哪一个服务
            $service_id = EmployGoodsModel::where('employ_id', $employ['id'])->first();
            //增加服务的总评价数量
            GoodsModel::where('id', $service_id['service_id'])->increment('comments_num', 1);
            //增加用户雇佣数量
            UserDetailModel::where('uid', $uid)->increment('publish_task_num', 1);
            //如果是好评就将数量加一
            if ($data['type'] == 1) {
                GoodsModel::where('id', $service_id['service_id'])->increment('good_comment', 1);
                UserDetailModel::where('uid', $uid)->increment('employer_praise_rate', 1);
            }
        } else {
            //增加用户承接数量
            UserDetailModel::where('uid', $uid)->increment('receive_task_num', 1);
            //如果是好评就将数量加一
            if ($data['type'] == 1) {
                UserDetailModel::where('uid', $uid)->increment('employee_praise_rate', 1);
            }
        }
        return $this->formateResponse(1000, '评论成功');
    }

}