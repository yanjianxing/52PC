<?php
namespace App\Modules\Task\Http\Controllers;

use App\Http\Controllers\IndexController as BasicIndexController;
use App\Http\Requests;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\UserCouponModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Task\Http\Requests\BountyRequest;
use App\Modules\Task\Model\ServiceModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskServiceModel;
use App\Modules\User\Model\BankAuthModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\Order\Model\OrderModel;
use App\Modules\User\Model\UserVipConfigModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Theme;
use QrCode;
use Cache;
use Omnipay;

class PayController extends BasicIndexController
{
    public function __construct()
    {
        parent::__construct();
        $this->user = Auth::user();
        $this->initTheme('fastpackage');
    }

    /**
     *  购买增值服务支付页面
     * @param int $id 任务id
     * @return mixed
     */
    public function buyServiceTask($id)
    {
        $this->theme->setTitle('购买增值服务');
        //查询用户发布的数据
        $task = TaskModel::find($id);
        $username = $this->user->name;
        //查询用户的余额
        $user_money = UserDetailModel::where(['uid' => $this->user['id']])->first();
        $user_money = $user_money['balance'];

        //查询用户的任务服务费用
        $serviceId = TaskServiceModel::where('task_id', $id)->lists('service_id')->toArray();
        //增值服务折扣
        $vipConfig = UserVipConfigModel::getConfigByUid($this->user['id']);
        $money = ServiceModel::serviceMoney($serviceId,$vipConfig);
        $service = ServiceModel::whereIn('id',$serviceId)->select('title','price','identify')->get()->toArray();
        //.根据金额获取对应的优惠券
        $userCoupon = UserCouponModel::getCoupon($money,[0,1])->toArray();
        //判断用户的余额是否充足
        $balance_pay = false;
        if ($user_money >= $money) {
            $balance_pay = true;
        }
        //查询用户绑定的银行卡信息
        $bank = BankAuthModel::where('uid', '=', $id)->where('status', '=', 4)->get();
        //判断第三方支付是否开启
        $payConfig = ConfigModel::getConfigByType('thirdpay');

        $view = [
            'task'          => $task,
            'bank'          => $bank,
            'username'      => $username,
            'service'       => $service,
            'vipConfig'     => $vipConfig,
            'service_money' => $money,
            'id'            => $id,
            'user_money'    => $user_money,
            'balance_pay'   => $balance_pay,
            'payConfig'     => $payConfig,
            'userCoupon'    => $userCoupon
        ];
        return $this->theme->scope('task.buyservice', $view)->render();
    }

    /**
     * 发布任务支付购买的增值服务
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postBuyServiceTask(Request $request)
    {
        $data = $request->except('_token');
        $data['id'] = intval($data['id']);
        //查询用户发布的数据
        $task = TaskModel::find($data['id']);

        //判断用户所要支付的是否是自己的任务和任务是否已经支付
        if ($task['uid'] != $this->user['id'] || $task['bounty_status'] != 0) {
            return redirect()->to('/kb/' . $task['id'])->with('error', '非法操作！');
        }


        //查询用户的余额
        $balance = UserDetailModel::where(['uid' => $this->user['id']])->first();
        $balance = (float)$balance['balance'];

        //查询用户的任务服务费用
        $service = TaskServiceModel::where('task_id', '=', $data['id'])->lists('service_id')->toArray();
        //增值服务折扣
        $vipConfig = UserVipConfigModel::getConfigByUid($this->user['id']);
        $money = ServiceModel::serviceMoney($service,$vipConfig);

        if(isset($data['userCoupon']) && $data['userCoupon']>0){
            //获取优惠券减免金额
            $resPrice = UserCouponModel::getEndPrice($money,$data['userCoupon']);
            $money = $resPrice['endPrice'];
            if($resPrice['endPrice'] == 0){
                $data['pay_canel'] = 0;
            }
        }
        //创建购买增值服务订单
        $is_ordered = OrderModel::buyServicebyTask($this->user['id'], $money, $task['id'],$data['userCoupon']);
        if (!$is_ordered) {
            return redirect()->back()->with(['error' => '任务发布失败']);
        }

        //如果余额足够就直接余额付款
        if ($balance >= $money && $data['pay_canel'] == 0) {
            if(!isset($data['password'])){
                return redirect()->back()->with(['error' => '请输入支付密码']);
            }
            //验证用户的密码是否正确
            $password = UserModel::encryptPassword($data['password'], $this->user['salt']);
            if ($password != $this->user['alternate_password']) {
                return redirect()->back()->with(['error' => '您的支付密码不正确']);
            }
            //余额支付产生订单
            $result = TaskModel::payServiceTask($money, $data['id'], $this->user['id'], $is_ordered->code);
            if (!$result) return redirect()->back()->with(['error' => '订单支付失败！']);
            $url = '/kb/tasksuccess/'.$data['id'];
            return redirect()->to($url);
        } else if (isset($data['pay_type']) && $data['pay_canel'] == 1) {
            //跳转支付赏金托管流程,返回成功之后就直接执行上面的托管
            if ($data['pay_type'] == 1) {//支付宝支付
                $config = ConfigModel::getPayConfig('alipay');
                $objOminipay = Omnipay::gateway('alipay');
                $objOminipay->setPartner($config['partner']);
                $objOminipay->setKey($config['key']);
                $objOminipay->setSellerEmail($config['sellerEmail']);
                $siteUrl = \CommonClass::getConfig('site_url');
                $objOminipay->setReturnUrl($siteUrl . '/order/pay/alipay/return');
                $objOminipay->setNotifyUrl($siteUrl . '/order/pay/alipay/notify');

                $response = Omnipay::purchase([
                    'out_trade_no' => $is_ordered->code, //your site trade no, unique
                    'subject'      => \CommonClass::getConfig('site_name'), //order title
                    'total_fee'    => $money, //order total fee $money
                ])->send();
                $response->redirect();
            } else if ($data['pay_type'] == 2) {//微信支付
                $config = ConfigModel::getPayConfig('wechatpay');
                $wechat = Omnipay::gateway('wechat');
                $wechat->setAppId($config['appId']);
                $wechat->setMchId($config['mchId']);
                $wechat->setAppKey($config['appKey']);
                $out_trade_no = $is_ordered->code;
                $params = array(
                    'out_trade_no' => $is_ordered->code, // billing id in your system
                    'notify_url'   => \CommonClass::getDomain() . '/order/pay/wechat/notify?out_trade_no=' . $out_trade_no . '&task_id=' . $data['id'], // URL for asynchronous notify
                    'body'         => \CommonClass::getConfig('site_name') . '余额充值', // A simple description
                    'total_fee'    => $money, // Amount with less than 2 decimals places
                    'fee_type'     => 'CNY', // Currency name from ISO4217, Optional, default as CNY
                );
                $response = $wechat->purchase($params)->send();

                $img = QrCode::size('280')->generate($response->getRedirectUrl());
                $view = array(
                    'cash'       => $money,
                    'img'        => $img,
                    'order_code' => $is_ordered->code,
                    'href_url'   => '/kb/tasksuccess/'.$data['id']
                );
                return $this->theme->scope('task.wechatpay', $view)->render();
            } else if ($data['pay_type'] == 3) {
                dd('银联支付！');
            }
        } else if (isset($data['account']) && $data['pay_canel'] == 2) {//银行卡支付
            dd('银行卡支付！');
        } else{//如果没有选择其他的支付方式
            return redirect()->back()->with(['error' => '请选择一种支付方式']);
        }
    }


    /**
     * 赏金托管页面
     * @param $id
     * @return mixed
     */
    public function bounty($id)
    {
        $this->theme->setTitle('赏金托管');
        //查询用户发布的数据
        $task = TaskModel::find($id);

        //判断用户所要支付的是否是自己的任务和任务是否已经支付
        if ($task['uid'] != $this->user['id'] || $task['bounty_status'] != 0) {
            return redirect()->back()->with(['error' => '非法操作！']);
        }

        //查询用户的余额
        $user_money = UserDetailModel::where(['uid' => $this->user['id']])->first();
        $user_money = $user_money['balance'];


        //判断用户的余额是否充足
        $balance_pay = false;
        if ($user_money >= $task['bounty']) {
            $balance_pay = true;
        }
        if($task['is_car'] == 1 && $user_money >= ($task['bounty']-$task['car_cash'])){
            $balance_pay = true;
        }

        //判断第三方支付是否开启
        $payConfig = ConfigModel::getConfigByType('thirdpay');

        //根据金额获取对应的优惠券
        $userCoupon = UserCouponModel::getCoupon($task['bounty'],[0,1])->toArray();

        $view = [
            'task'        => $task,
            'id'          => $id,
            'user_money'  => $user_money,
            'balance_pay' => $balance_pay,
            'payConfig'   => $payConfig,
            'userCoupon'  => $userCoupon
        ];
        return $this->theme->scope('task.bounty', $view)->render();
    }

    /**
     * 赏金托管提交，托管赏金
     * @param BountyRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bountyUpdate(BountyRequest $request)
    {
        $data = $request->except('_token');
        $data['id'] = intval($data['id']);
        //查询用户发布的数据
        $task = TaskModel::findById($data['id']);

        //判断用户所要支付的是否是自己的任务和任务是否已经支付
        if ($task['uid'] != $this->user['id'] || $task['bounty_status'] != 0) {
            return redirect()->to('/kb/' . $task['id'])->with('error', '非法操作！');
        }

        //查询用户的余额
        $balance = UserDetailModel::where(['uid' => $this->user['id']])->first();
        $balance = (float)$balance['balance'];

        $money = $task['bounty'];
        if($task['is_car'] == 1){
            $money = $task['bounty']-$task['car_cash'];
        }

        if(isset($data['userCoupon']) && $data['userCoupon']>0 && $money > 0){
            //获取优惠券减免金额
            $resPrice = UserCouponModel::getEndPrice($money,$data['userCoupon']);
            $money = $resPrice['endPrice'];
            if($resPrice['endPrice'] == 0){
                $data['pay_canel'] = 0;
            }
        }

        //创建订单
        $is_ordered = OrderModel::bountyOrder($this->user['id'], $money, $task['id'],$data['userCoupon']);

        if (!$is_ordered) return redirect()->back()->with(['error' => '任务托管失败']);

        //如果余额足够就直接余额付款
        if ($balance >= $money && $data['pay_canel'] == 0)
        {
            //验证用户的密码是否正确
            $password = UserModel::encryptPassword($data['password'], $this->user['salt']);
            if ($password != $this->user['alternate_password']) {
                return redirect()->back()->with(['error' => '您的支付密码不正确']);
            }
            //余额支付产生订单
            $result = TaskModel::bounty($money, $data['id'], $this->user['id'], $is_ordered->code);
            if (!$result) return redirect()->back()->with(['error' => '赏金托管失败！']);

            UserModel::sendfreegrant($this->user['id'],5);//托管成功自动发放
            
            if($task['type_id'] == 1){
                $url = 'kb/'.$data['id'];
            }else{
                $url = 'employ/workIn/'.$data['id'];
            }
            return redirect()->to($url);
        } else if (isset($data['pay_type']) && $data['pay_canel'] == 1) {
            if($money <= 0){
                return redirect()->to('/kb/' . $task['id'])->with('error', '非法操作！');
            }else{
                //跳转支付赏金托管流程,返回成功之后就直接执行上面的托管
                if ($data['pay_type'] == 1) {//支付宝支付
                    $config = ConfigModel::getPayConfig('alipay');
                    $objOminipay = Omnipay::gateway('alipay');
                    $objOminipay->setPartner($config['partner']);
                    $objOminipay->setKey($config['key']);
                    $objOminipay->setSellerEmail($config['sellerEmail']);
                    $siteUrl = \CommonClass::getConfig('site_url');
                    $objOminipay->setReturnUrl($siteUrl . '/order/pay/alipay/return');
                    $objOminipay->setNotifyUrl($siteUrl . '/order/pay/alipay/notify');

                    $response = Omnipay::purchase([
                        'out_trade_no' => $is_ordered->code, //your site trade no, unique
                        'subject' => \CommonClass::getConfig('site_name'), //order title
                        'total_fee' => $money, //order total fee $money
                    ])->send();
                    $response->redirect();
                } else if ($data['pay_type'] == 2) {//微信支付
                    $config = ConfigModel::getPayConfig('wechatpay');
                    $wechat = Omnipay::gateway('wechat');
                    $wechat->setAppId($config['appId']);
                    $wechat->setMchId($config['mchId']);
                    $wechat->setAppKey($config['appKey']);
                    $out_trade_no = $is_ordered->code;
                    $params = array(
                        'out_trade_no' => $is_ordered->code, // billing id in your system
                        'notify_url' => \CommonClass::getDomain() . '/order/pay/wechat/notify?out_trade_no=' . $out_trade_no . '&task_id=' . $data['id'], // URL for asynchronous notify
                        'body' => \CommonClass::getConfig('site_name') . '余额充值', // A simple description
                        'total_fee' => $money, // Amount with less than 2 decimals places
                        'fee_type' => 'CNY', // Currency name from ISO4217, Optional, default as CNY
                    );
                    $response = $wechat->purchase($params)->send();

                    $img = QrCode::size('280')->generate($response->getRedirectUrl());
                    $view = array(
                        'cash'       => $money,
                        'img'        => $img,
                        'order_code' => $is_ordered->code,
                        'href_url'   => '/kb/'.$data['id']
                    );
                    return $this->theme->scope('task.wechatpay', $view)->render();
                } else if ($data['pay_type'] == 3) {
                    dd('银联支付！');
                }
            }

        } else if (isset($data['account']) && $data['pay_canel'] == 2) {//银行卡支付
            dd('银行卡支付！');
        } else//如果没有选择其他的支付方式
        {
            return redirect()->back()->with(['error' => '请选择一种支付方式']);
        }

    }

}
