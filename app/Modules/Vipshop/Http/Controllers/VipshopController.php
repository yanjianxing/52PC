<?php

namespace App\Modules\Vipshop\Http\Controllers;

use App\Http\Controllers\IndexController;
use App\Http\Requests;
use App\Modules\Advertisement\Model\AdTargetModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\FeedbackModel;
use App\Modules\Manage\Model\VipModel;
use App\Modules\Order\Model\OrderModel;
use App\Modules\Order\Model\ShopOrderModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\Vipshop\Http\Requests\PayOrderRequest;
use App\Modules\Vipshop\Models\InterviewModel;
use App\Modules\Vipshop\Models\PackageModel;
use App\Modules\Vipshop\Models\PackagePrivilegesModel;
use App\Modules\Vipshop\Models\PrivilegesModel;
use App\Modules\Vipshop\Models\ShopPackageModel;
use App\Modules\Vipshop\Models\VipshopOrderModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Session;
use DB;
use Omnipay;
use QrCode;
use Validator;
class VipshopController extends IndexController
{
    public function __construct()
    {
        parent::__construct();
        $this->user = Auth::user();
        $this->initTheme('vipshop');

        $touchMe = ConfigModel::getConfigByAlias('vip_shop_config');
        if($touchMe && !empty($touchMe['rule'])){
            $touchMe = json_decode($touchMe['rule'], true);
        }else{
            $touchMe = [
                'hot_line' => '',
                'logo1' => '',
                'logo2' => ''
            ];
        }
        $this->theme->set('vip_touch', $touchMe);
    }


    /*
      服务商店铺首页
    */
    public function serviceshop(){
        $this->initTheme('serviceshop');

        $data=[];
        return $this->theme->scope('vipshop.serviceshoppage', $data)->render();
    }

    //vip详情介绍
    public function vipDetail(){
        $this->initTheme('vipmain');
        $data=[];
        return $this->theme->scope('vipshop.vipdetial', $data)->render();
    }

    //vip服务
    public function vipServer(Request $request){
        $this->initTheme('vipmain');
        $requestData=[
            'level'=>$request->get("level")?$request->get("level"):0,
            'type'=>$request->get("type")?$request->get("type"):0,
        ];
        $PriceTimeArr = [
            '1'=>'季',
            '2'=>'年',
            '3'=>'月',
            '4'=>'半年',
        ];
        //获取vip列表
        $vipList=VipModel::getVipList();
        foreach ($vipList as $key => $val) {
            $days='30';
            if(isset($val['price_time']) && $val['price_time'] == 1){
                $days = '90';
            }elseif(isset($val['price_time']) && $val['price_time'] == 2){
                $days = '365';
            }elseif(isset($val['price_time']) && $val['price_time'] == 4){
                $days = '180';
            }
            if(isset($val['vip_cika_price'])){
                $price = $val['vip_cika_price'];
            }elseif(isset($val['vip_rika_price'])){
                $price = $val['vip_rika_price'];
            }else{
                $price = $val['price'];
            }
            $vipList[$key]["AVGprice"] = number_format($price / $days,2);
        }
        $data=[
            'vipList'=>$vipList,
            'PriceTimeArr'=>$PriceTimeArr,
            'request'=>$requestData,
        ];
        return $this->theme->scope('vipshop.vipServer', $data)->render();
    }

    //直通车服务
    public function vipCartServer(){
        $this->initTheme('vipmain');

        $data=[];
        return $this->theme->scope('vipshop.vipCartServer', $data)->render();
    }

    //项目置顶服务
    public function vipTopServer(){
        $this->initTheme('vipmain');

        $data=[];
        return $this->theme->scope('vipshop.vipTopServer', $data)->render();
    }

    //项目加急服务
    public function vipUrgentServer(){
        $this->initTheme('vipmain');

        $data=[];
        return $this->theme->scope('vipshop.vipUrgentServer', $data)->render();
    }

    //私密项目对接服务
    public function vipSicServer(){
        $this->initTheme('vipmain');

        $data=[];
        return $this->theme->scope('vipshop.vipSicServer', $data)->render();
    }

    //发展历程
    public function developmentHistory(){
        $this->initTheme('vipmain');

        $data=[];
        return $this->theme->scope('vipshop.developmentHistory', $data)->render();
    }

    //联系我们
    public function linkWe(){
        $this->initTheme('vipmain');

        $data=[];
        return $this->theme->scope('vipshop.linkWe', $data)->render();
    }

    //商务合作
    public function cooperation(){
        $this->initTheme('cooperationpage');
        $data=[];
        return $this->theme->scope('vipshop.cooperation', $data)->render();
    }

    //网站广告
    public function bannerShowPic(){
        $this->initTheme('vipmain');
        $data=[];
        return $this->theme->scope('vipshop.bannerShowPic', $data)->render();
    }

    //列表排名
    public function listsort(){
        $this->initTheme('vipmain');
        $data=[];
        return $this->theme->scope('vipshop.listsort', $data)->render();
    }

    /**
     * vip首页
     */
    public function Index()
    {
        //vip广告
        $ad = AdTargetModel::getAdInfo('VIP_TOP_SLIDE');
        //获取导航名称
		$NavName= \CommonClass::getNavName('/vipshop');
		if(!$NavName){
			$NavName="VIP特权";
		}
        //套餐列表
        $arrPackage = PackageModel::where('status', 0)
            ->orderBy('list', 'asc')->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()->toArray();
        if (!empty($arrPackage)) {
            foreach ($arrPackage as $k => $v) {
                $arrPackage[$k]['price'] = collect(array_pluck(json_decode($v['price_rules'], true), 'cash'))->sort()->first();
            }
        }
        //特权信息
        $arrPrivilege = PrivilegesModel::select('ico', 'title', 'desc')
            ->where('status', 0)
            ->orderBy('list', 'desc')->limit(6)->get();

        //vip店铺
        $arrVipshop = ShopPackageModel::select('shop.shop_name', 'package.logo', 'shop.shop_pic','shop.id')
            ->join('shop', 'shop.id', '=', 'shop_package.shop_id')
            ->join('package', 'package.id', '=', 'shop_package.package_id')
            ->where('shop_package.status', 0)->orderBy('shop_package.created_at', 'desc')
            ->limit(15) ->get();

        //访谈
        $arrInterview = InterviewModel::select('interview.title', 'interview.desc', 'shop.id', 'shop.shop_pic', 'shop.shop_name', 'shop.uid', 'interview.id as vid')
            ->join('shop', 'shop.id', '=', 'interview.shop_id')
            ->orderBy('list', 'desc')->limit(4)->get();

        $data = [
            'ad' => $ad,
            'package_list' => $arrPackage,
            'privilege_list' => $arrPrivilege,
            'vishop_list' => $arrVipshop,
            'interview_list' => $arrInterview,
			'NavName'    =>$NavName
        ];
        $this->theme->set('vipactive', 'vipindex');
        return $this->theme->scope('vipshop.index', $data)->render();
    }

    /**
     * 访谈首页
     *
     * @return mixed
     */
    public function Page()
    {
        $ad = AdTargetModel::getAdInfo('VIP_TOP_SLIDE');

        $perPage = 15;
        //访谈
        $arrInterview = InterviewModel::select('interview.title', 'interview.desc', 'shop.id', 'shop.shop_pic', 'shop.shop_name', 'shop.uid', 'interview.id as vid')
            ->leftJoin('shop', 'shop.id', '=', 'interview.shop_id')
            ->orderBy('list', 'desc');

        $count = $arrInterview->count();

        $list = $arrInterview->paginate($perPage);

        //获取用户详情信息
        $userDetail = [];
        $user = Auth::User();
        if($user){
            $userDetail = UserDetailModel::where('uid',$user->id)->select('uid')->first();
        }
        $data = [
            'ad' => $ad,
            'list' => $arrInterview,
            'count' => $count,
            'list' => $list,
            'page' => Input::get('page') ? Input::get('page') : 1,
            'per_page' => $perPage,
            'userDetail' => $userDetail
        ];
        $this->theme->set('vipactive', 'interview');
        return $this->theme->scope('vipshop.page', $data)->render();
    }

    /**
     * vip访谈详情
     */
    public function details($id)
    {
        $info = InterviewModel::findOrFail($id);

        $info->increment('view_count', 1);

        $sideList = InterviewModel::orderBy('list', 'desc')->limit(5)
            ->get(['title', 'id', 'desc', 'shop_cover']);

        $arrInterviewId = $sideList->map(function ($v, $k) {
            $id = $v->id;
            return $id;
        });

        $headId = $arrInterviewId->first(function ($k, $v) use ($id) {
            return $v > $id;
        });

        $nextId = $arrInterviewId->first(function ($k, $v) use ($id) {
            return $v < $id;
        });

        $headInfo = InterviewModel::find($headId);

        $nextInfo = InterviewModel::find($nextId);

        $data = [
            'info' => $info,
            'side_list' => $sideList,
            'head_info' => $headInfo,
            'next_info' => $nextInfo
        ];
        $this->theme->set('vipactive', 'interview');
        return $this->theme->scope('vipshop.details', $data)->render();
    }

    /**
     * 购买套餐页面
     *
     * @return mixed
     */
    public function getPayvip()
    {
        $packages = PackageModel::where('status', 0)->orderBy('list', 'desc')
            ->get(['id', 'title', 'logo', 'price_rules'])->toArray();

        $arrPackageId = collect($packages)->map(function ($item, $key) {
            return $item['id'];
        })->toArray();

        $privileges = PackagePrivilegesModel::whereIn('package_privileges.package_id', $arrPackageId)
            ->where('privileges.status', 0)
            ->leftJoin('privileges', 'package_privileges.privileges_id', '=', 'privileges.id')
            ->orderBy('privileges.list', 'desc')
            ->get(['package_privileges.package_id', 'privileges.id', 'privileges.title', 'privileges.desc', 'privileges.ico'])
            ->toArray();

        $list = collect($packages)->map(function ($value, $key) use ($privileges) {
            $value['privileges'] = collect($privileges)->map(function ($v, $k) use ($value) {
                if ($value['id'] == $v['package_id']) {
                    return $v;
                }
            })->toArray();
            return $value;
        });

        $list->transform(function ($v, $k) {
            $v['price_rules'] = json_decode($v['price_rules'], true);
            $v['min_price'] = collect($v['price_rules'])->sortBy('cash')->first()['cash'];
            return $v;
        });

        $data = [
            'list' => $list
        ];
        $this->theme->set('vipactive', 'payvip');
        return $this->theme->scope('vipshop.payvip', $data)->render();
    }

    /**
     * 购买vip店铺
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postPayvip()
    {
        $packageId = Input::get('packag_id');
        $ruleId = Input::get('price_rule_id');

        $package = PackageModel::findOrFail($packageId);

        $priceRules = json_decode($package->price_rules, true)[$ruleId];

        $code = ShopOrderModel::randomCode(Auth::id(), 'vs');

        $shopInfo = ShopModel::where('uid', Auth::id())->where('status', '1')->first();

        if (empty($shopInfo)) {
            return back()->with(['message' => '请先开启店铺']);
        }

        $havePackage = ShopPackageModel::where('uid', Auth::id())->where('status', 0)->first();

        if ($havePackage) {
            return back()->with(['message' => '你已购买vip套餐']);
        }


        $data = [
            'code' => $code,
            'cash' => $priceRules['cash'],
            'title' => '开通vip店铺',
            'uid' => Auth::id(),
            'shop_id' => $shopInfo->id,
            'package_id' => $packageId,
            'time_period' => $priceRules['time_period']
        ];
        $status = VipshopOrderModel::create($data);
        if ($status) {
            session(['vipshopcode' => $status->code]);
            return redirect('vipshop/vipPayorder');
        }


    }

    /**
     * 特权介绍
     */
    public function vipinfo()
    {
        //套餐
        $packages = PackageModel::where('status', 0)
            ->orderBy('list', 'desc')
            ->get();

        $packages->each(function ($item, $key) {
            $ruleInfo = collect(json_decode($item['price_rules'], true))->sortBy('cash')->first();

            if (!empty($ruleInfo) && isset($ruleInfo['cash'])) {
                $item['price'] = $ruleInfo['cash'];
            }
        });

        $privileges = PrivilegesModel::where('status', 0)->orderBy('list', 'desc')->get(['id', 'title', 'desc']);

        $arrStatus = [];

        foreach ($privileges as $key => $item) {

            $packagesPrivileges = PackagePrivilegesModel::where('privileges_id', $item['id'])->get(['package_id'])->toArray();

            $packageId = collect($packagesPrivileges)->pluck('package_id')->toArray();

            $arrPackage = $packages->toArray();

            foreach ($arrPackage as $k => $v) {
                if (in_array($v['id'], $packageId)) {
                    $arrStatus[$key][] = 1;
                } else {
                    $arrStatus[$key][] = 0;
                }
            }
        }

        $data = [
            'packages' => $packages,
            'privileges' => $privileges,
            'arrStatus' => $arrStatus,
        ];
        $this->theme->set('vipactive', 'vipinfo');
        return $this->theme->scope('vipshop.vipinfo', $data)->render();
    }

    /**
     * 套餐支付
     */
    public function vipPayorder()
    {
        $vipcode = session('vipshopcode');

        $orderInfo = VipshopOrderModel::where('code', $vipcode)->firstOrFail();

        $userInfo = UserDetailModel::where('uid', Auth::id())->first();

        $payConfig = ConfigModel::getConfigByType('thirdpay');
        if($userInfo['balance']< $orderInfo['cash'] && empty($payConfig['alipay']['status']) && empty($payConfig['wechatpay']['status'])){
			return back()->with(['message' => '你的余额不足，请先充值']);
		}
        $data = [
            'order' => $orderInfo,
            'userInfo' => $userInfo,
            'payConfig' => $payConfig
        ];

        return $this->theme->scope('vipshop.vipPayorder', $data)->render();
    }

    /**
     * 余额支付vip店铺订单
     *
     * @param PayOrderRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postVipPayorder(PayOrderRequest $request)
    {
        $data = $request->except('_token');

        $userInfo = UserModel::find(Auth::id());

        $password = UserModel::encryptPassword($data['password'], $userInfo->salt);

        $havePackage = ShopPackageModel::where('uid', Auth::id())->where('status', 0)->first();

        if ($havePackage) return back()->with(['message' => '你已购买vip套餐']);

        if ($password == $userInfo->alternate_password) {
            $vipcode = session('vipshopcode');
            $status = DB::transaction(function () use ($vipcode) {
                $orderInfo = VipshopOrderModel::where('code', $vipcode)->first();
                UserDetailModel::where('uid', Auth::id())->decrement('balance', $orderInfo->cash);
                FinancialModel::create([
                    'action' => 15,
                    'pay_type' => 1,
                    'cash' => $orderInfo->cash,
                    'uid' => Auth::id()
                ]);
                VipshopOrderModel::where('code', $orderInfo->code)->update(['status' => 1]);
                $arrPrivilegeId = PackagePrivilegesModel::where('package_id', $orderInfo->package_id)->get(['privileges_id'])
                    ->map(function ($v, $k) {
                        return $v['privileges_id'];
                    });
                ShopPackageModel::create([
                    'shop_id' => $orderInfo->shop_id,
                    'package_id' => $orderInfo->package_id,
                    'privileges_package' => json_encode($arrPrivilegeId),
                    'uid' => Auth::id(),
                    'username' => Auth::User()->name,
                    'duration' => $orderInfo->time_period,
                    'price' => $orderInfo->cash,
                    'start_time' => date('Y-m-d H:i:s', time()),
                    'end_time' => date('Y-m-d H:i:s', strtotime('+' . $orderInfo->time_period . ' month')),
                    'status' => 0
                ]);
            });

            if (is_null($status)) {
                return redirect('vipshop/vipsucceed');
            }
            return redirect('vipshop/vipfailure');
        }

        return back()->withErrors(['password' => '请输入正确的支付密码']);
    }

    /**
     * 第三方支付VIP店铺订单
     *
     * @return mixed
     */
    public function thirdPayorder()
    {
        $type = Input::get('pay_type');

        $vipcode = session('vipshopcode');

        $vipOrder = VipshopOrderModel::where('code', $vipcode)->first();

        switch ($type) {
            case 'alipay':
                $config = ConfigModel::getPayConfig('alipay');
                $objOminipay = Omnipay::gateway('alipay');
                $objOminipay->setPartner($config['partner']);
                $objOminipay->setKey($config['key']);
                $objOminipay->setSellerEmail($config['sellerEmail']);
                $objOminipay->setReturnUrl(env('ALIPAY_RETURN_URL', url('/order/pay/alipay/return')));
                $objOminipay->setNotifyUrl(env('ALIPAY_NOTIFY_URL', url('/order/pay/alipay/notify')));
                $response = Omnipay::purchase([
                    'out_trade_no' => $vipOrder->code, //your site trade no, unique
                    'subject' => \CommonClass::getConfig('site_name') . '余额充值', //order title
                    'total_fee' => $vipOrder->cash, //order total fee
                ])->send();
                $response->redirect();
                break;
            case 'wechatpay':
                $config = ConfigModel::getPayConfig('wechatpay');
                $wechat = Omnipay::gateway('wechat');
                $wechat->setAppId($config['appId']);
                $wechat->setMchId($config['mchId']);
                $wechat->setAppKey($config['appKey']);
                $params = array(
                    'out_trade_no' => $vipOrder->code, // billing id in your system
                    'notify_url' => env('WECHAT_NOTIFY_URL', url('order/pay/wechat/notify')), // URL for asynchronous notify
                    'body' => \CommonClass::getConfig('site_name') . '余额充值', // A simple description
                    'total_fee' => $vipOrder->cash, // Amount with less than 2 decimals places
                    'fee_type' => 'CNY', // Currency name from ISO4217, Optional, default as CNY
                );
                $response = $wechat->purchase($params)->send();

                $img = QrCode::size('280')->generate($response->getRedirectUrl());
                $view = array(
                    'cash' => $vipOrder->cash,
                    'img' => $img
                );
                $this->initTheme('userfinance');
                return $this->theme->scope('pay.wechatpay', $view)->render();
                break;
        }
    }

    /**
     * 套餐支付成功
     */
    public function vipSucceed()
    {
        return $this->theme->scope('vipshop.vipsucceed')->render();
    }

    /**
     *  套餐支付失败
     */
    public function vipFailure()
    {
        return $this->theme->scope('vipshop.vipfailure')->render();
    }

    /**
     * vip反馈添加
     * @param  $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function feedback(Request $request){
        $data = $request->except('_token');
        $validator = Validator::make($data,[
            'desc' => 'required|max:255',
            'phone' => 'mobile_phone'
        ],
            [
                'desc.required' => '请输入投诉建议',
                'desc.max'      => '投诉建议字数超过限制',
                'phone.mobile_phone' => '请输入正确的手机格式'

            ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return back()->with(['error'=>$validator->errors()->first()]);
        }
        $newdata = [
            'desc'          => $data['desc'],
            'created_time'  => date('Y-m-d h:i:s',time()),
            'phone'         => $data['phone'],
            'type'          => 1
        ];
        if($data['uid']){
            $newdata['uid'] = $data['uid'];
        }
        $res = FeedbackModel::create($newdata);
        if($res){
            return back()->with(['message'=>'投诉建议提交成功！']);
        }
        return back()->with(['error'=>'投诉建议提交失败！']);
    }
}