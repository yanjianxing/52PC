<?php
namespace App\Modules\Finance\Http\Controllers;

use App\Modules\Advertisement\Model\AdTargetModel;
use App\Modules\Finance\Http\Requests\CashoutInfoRequest;
use App\Modules\Finance\Http\Requests\CashoutRequest;
use App\Modules\Finance\Http\Requests\PayRequest;
use App\Modules\Finance\Model\CashoutModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\ActivityModel;
use App\Modules\Manage\Model\CateModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\CouponModel;
use App\Modules\Manage\Model\SeoModel;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Manage\Model\UserCouponModel;
use App\Modules\Manage\Model\ArticleCategoryModel;
use App\Modules\Manage\Model\ArticleModel;
use App\Modules\Manage\Model\ZdfastbuyModel;
use App\Modules\Order\Model\OrderModel;
use App\Modules\Order\Model\ShopOrderModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\GoodsServiceModel;
use App\Modules\User\Http\Controllers\UserCenterController;
use App\Modules\User\Model\AlipayAuthModel;
use App\Modules\User\Model\BankAuthModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\CollectionModel;
use App\Modules\User\Model\EnterpriseAuthModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use App\Modules\Task\Model\SuccessCaseModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\Advertisement\Model\RecommendModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\Task\Model\TaskModel;
use Omnipay;
use DB;
Use QrCode;
use File;

class PayController extends UserCenterController
{
    //
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('userfinance');

    }

    /*
     成功案例
    */
    public function succecase(Request $request)
    {
        $this->initTheme('shop');
        $merge=[
            'industry'      => $request->get('industry')?$request->get('industry'):0,
            'cate_category' => $request->get('cate_category')?$request->get('cate_category'):0,
            'province'      => $request->get('province')?$request->get('province'):0,
            'desc'          => $request->get('desc')?$request->get('desc'):'default',
            'keywords'      => $request->get('keywords')?$request->get('keywords'):'',
        ];

        //排序问题
        switch ($merge['desc']){
            case 'release'://发布时间
                $order="created_at";
               break;
            case 'popularity'://人气量
               $order="view_count";
               break;
            case 'price'://成交量
               $order="cash";
               break;
            default:
               $order="id";
               break;
        }

        //案例列表
        $succecaseList = SuccessCaseModel::select("success_case.*","cate.name as ca_name")
            ->leftjoin("cate","success_case.cate_id","=","cate.id")
            ->where('success_case.status',2);
        //行业分类筛选
        if($request->get('industry')){
            $succecaseList=$succecaseList->where('success_case.cate_id',$request->get('industry'));
        }
        //方案类型
        if($request->get('cate_category')){
            $succecaseList=$succecaseList->where('success_case.cate_category',$request->get('cate_category'));
        }
        //地区筛选
        if($request->get('province')){
            $succecaseList=$succecaseList->where('success_case.province',$request->get('province'));
        }
        //关键字筛选
        if($request->get('keywords')){
            $succecaseList=$succecaseList->where('success_case.title',"like","%".$request->get('keywords')."%");
            //有关键字添加搜索记录
            \CommonClass::get_keyword($request->get('keywords'),7);
        }
        if($request->get('technology_id')){
            $succecaseList = $succecaseList->where('success_case.technology_id',$request->get('technology_id'));
        }

        $succecaseList = $succecaseList->with('shop')->orderBy($order,'desc')->paginate(16);

        //查询应用领域分类
        $industryType = CateModel::where('pid','=','0')->where('type','=','1')->orderBy('sort','desc')->get()->toArray();
        //地区
        $province = DistrictModel::findTree(0);
        //推荐方案
        $goodsRecommendList = RecommendModel::getRecommendByCode('SUCCESS_LIST','goods',['shop_info' => true,'goods_field' => true]);
        //推荐项目
        $taskRecommendList = RecommendModel::getRecommendByCode('SUCCESS_TASK','task');
        $ad = AdTargetModel::getAdByCodePage('SUCCESS');
        //获取热门seo 标签
        $seoLabel = SeoModel::orderBy("view_num","desc")->limit(8)->get();

        //最新活动
        $activity = ActivityModel::where('status',1)->where('type',1)->orderBy('pub_at','desc')->limit(6)->get()->toArray();
        $this->theme->set('HOME_ACTIVITY', $activity);
        //.右边推荐元器件获取
        $zdfastbuglist2=ZdfastbuyModel::where('id','>','0')->where('show_location',2)->where('is_del','0')->select('id','url','aurl')->get();
        $data= [
            'industryType'  => $industryType,
            'merge'         => $merge,
            'province'      => $province,
            'succecaseList' => $succecaseList,
            'goodsRecommendList' => $goodsRecommendList,
            'taskRecommendList'  => $taskRecommendList,
            'ad'            => $ad,
            'seoLabel'      => $seoLabel,
            'zdfastbuglist2'      => $zdfastbuglist2,
        ];
        $this->theme->set('nav_url', '/anli');
        $seoConfig = ConfigModel::getConfigByType('seo');
        if(!empty($seoConfig['seo_successcase']) && is_array($seoConfig['seo_successcase'])){
            $this->theme->setTitle($seoConfig['seo_successcase']['title']);
            $this->theme->set('keywords',$seoConfig['seo_successcase']['keywords']);
            $this->theme->set('description',$seoConfig['seo_successcase']['description']);
        }else{
            $this->theme->setTitle('案例列表');
        }
        return $this->theme->scope('finance.succecase', $data)->render();
    }


    //案例详情
    public function casedetailpage(Request $request,$id)
    {
        $this->initTheme('fastpackage');
        SuccessCaseModel::where("id",$id)->increment("view_count");
        $SuccessCase = SuccessCaseModel::find($id);
        if(!$SuccessCase){
            return redirect()->to('404');
            // return back()->with(['message'=>"参数错误"]);
        }
        $cateName = CateModel::where("id",$SuccessCase['cate_id'])->first();
        $addres = DistrictModel::getDistrictName($SuccessCase['province']).DistrictModel::getDistrictName($SuccessCase['city']).DistrictModel::getDistrictName($SuccessCase['area']);
        //服务商信息
        $userDetail =  UserDetailModel::select("user_detail.uid","user_detail.auth_type","user_detail.receive_task_num","user_detail.avatar","users.name","shop.id as shopid","shop.shop_name","shop.shop_pic")
            ->leftjoin("users","users.id","=","user_detail.uid")
            ->leftjoin("shop","shop.uid","=","user_detail.uid")
            ->where("user_detail.uid",$SuccessCase['uid'])->where("shop.uid",$SuccessCase['uid'])->first();
        //获取服务商好评率
        $applauseRate = \CommonClass::applauseRate($SuccessCase['uid']);
        //相关方案 根据分类应用领域来找相关
        $aboutGoods = GoodsModel::select("goods.id",'goods.title','goods.cover','goods.uid','goods.cate_id')
            ->with('cover','field','user')
            ->where("goods.status","1")
            ->where('is_delete',0)
            ->where("goods.cate_id",$SuccessCase['cate_id'])
            ->orderBy('goods.id','desc')->limit(5)->get()->toArray();
        //相关任务 根据分类应用领域来找相关
        $abouttask = TaskModel::select("task.*")->where("field_id",$SuccessCase['cate_id'])->where('type_id',1)->where('is_del',0)->whereIn('status',[2,4,5,6,7,8,9])->where('is_open',1)->orderBy('created_at','desc')->limit(5)->get()->toArray();
        //登陆状态下判断是否收藏
        if(!empty(Auth::user()->id)){
            $is_collection = CollectionModel::where("uid",Auth::user()->id)->where("type",2)->where("collec_id",$id)->first();
        }
        $ad = AdTargetModel::getAdByCodePage('SUCCESSDETAIL');
        //获取热门seo 标签
        $seoLabel=SeoModel::where('id','>',0)->select('id','name','view_num')->orderBy("view_num","desc")->get()->toArray();
        //.右边推荐元器件获取
        $zdfastbuglist1=ZdfastbuyModel::where('id','>','0')->where('show_location',1)->where('is_del','0')->select('id','url','aurl')->get();
        $data = [
            'SuccessCase'   => $SuccessCase,
            'cateName'      => $cateName,
            'addres'        => $addres,
            'applauseRate'  => $applauseRate,
            'userDetail'    => $userDetail,
            'aboutGoods'    => $aboutGoods,
            'abouttask'     => $abouttask,
            'is_collection' => !empty($is_collection)?'1':'0',
            'ad'            => $ad,
            'seoLabel'      => $seoLabel,
            'zdfastbuglist1' => $zdfastbuglist1,

        ];

        $this->theme->set('nav_url', '/anli');
        $seoConfig = ConfigModel::getConfigByType('seo');
        if(!empty($seoConfig['seo_successcasedetail']) && is_array($seoConfig['seo_successcasedetail'])){
            $this->theme->setTitle($SuccessCase['title']." - ".$seoConfig['seo_successcasedetail']['title']);
            $this->theme->set('keywords',$cateName['name'].$seoConfig['seo_successcasedetail']['keywords']);
        }else{
            $this->theme->setTitle($SuccessCase['title']);
            $this->theme->set('keywords',$SuccessCase['title'].'、'.$cateName['name']);
        }
        
        $this->theme->set('description',mb_substr(strip_tags($SuccessCase['desc']),0,200,'utf-8'));
        return $this->theme->scope('finance.casedetailpage', $data)->render();
    }
    //收藏
    public function collection(request $request ,$id,$status){
        if(!Auth::check()){
            return redirect("/login");
        }
        $data=[
                'uid'=>Auth::user()->id,
                'collec_id'=>$id,
                'type'=>2,
                'created_at'=>date("Y-m-d H:i:s",time())
              ];
        $colled = CollectionModel::collection($data,$status);
        return $colled;
    }


    /**
     * 帮助中心
     * @param Request $request
     * @return mixed
     */
    public function helpcenter(Request $request){
        $this->initTheme('helpcentermain');
        $this->theme->setTitle('帮助中心');
        $parentCate = ArticleCategoryModel::where('pid',2)->orderBy('display_order','asc')->get()->toArray();   //帮助中心分类
        /*分类下的文章*/
        foreach ($parentCate as $key => $value) {
            $categoryarticle[$value['id']] = ArticleModel::where('cat_id',$value['id'])->get();
            $articlecate[$value['id']]['cate_name'] = $value['cate_name'];
        }
        $this->theme->set('categoryarticle', $categoryarticle);
        $this->theme->set('articlecate', $articlecate);
        $content = '';
        $title = '';
        if($request->get('id')){
            $this->theme->set('id', $request->get('id'));
            $contentres =  ArticleModel::where('id',$request->get('id'))->first();
            if($contentres){
                $content = htmlspecialchars_decode($contentres['content']);
                $title = $contentres['title'];
                $this->theme->set('catid', $contentres['cat_id']);
            } 
          }
        $data = [
                'category'=>$parentCate,
                'categoryarticle'=>$categoryarticle,
                'content' =>$content,
                'title' => $title
            ];
      return $this->theme->scope('finance.helpcenter', $data)->render();
    }



    /**
     * 财务管理充值视图
     *
     * @return mixed
     */
    public function getCash()
    {
	//dd(phpinfo());
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","financial");
        $this->theme->set("financialType","1");
        $this->theme->setTitle('我要充值');
        $this->theme->set("userOneColumn","财务管理");
        $this->theme->set("userOneColumnUrl","/finance/list");
        $this->theme->set("userSecondColumn","我要充值");
        $this->theme->set("userSecondColumnUrl","/finance/cash");
        $user = Auth::User();
        $userInfo = UserDetailModel::select('balance')->where('uid', $user->id)->first();

        $payConfig = ConfigModel::getConfigByType('thirdpay');
        $cashConfig = ConfigModel::getConfigByAlias('cash');//支付配置
        if (!empty($userInfo)){
            $data = array(
                'balance' => $userInfo->balance,
                'payConfig' => $payConfig,
                'recharge_min' => json_decode($cashConfig->rule,true)['recharge_min']
            );

            return $this->theme->scope('finance.cash', $data)->render();
        }
    }

    /**
     * 确认充值
     *
     * @param PayRequest $request
     * @return $this
     */
    public function postCash(PayRequest $request)
    {
        $user = Auth::User();
        $config = ConfigModel::getConfigByAlias('cash');
        $config->rule = json_decode($config->rule, true);
        if ($request->get('cash') < $config->rule['recharge_min']) {
            return \CommonClass::formatResponse('充值金额不得小于' . $config->rule['recharge_min'] . '元', 201);
        }
        $data = array(
            'code' => OrderModel::randomCode($user->id),
            'title' => '余额充值',
            'cash' => $request->get('cash'),
            'uid' => $user->id,
            'created_at' => date('Y-m-d H:i:s', time())
        );
        $order = OrderModel::create($data);

        if ($order) {
            $payType = $request->get('pay_type');

            switch ($payType) {
                case 'alipay':
                    $config = ConfigModel::getPayConfig('alipay');
                    $objOminipay = Omnipay::gateway('alipay');
                    $objOminipay->setPartner($config['partner']);
                    $objOminipay->setKey($config['key']);
                    $objOminipay->setSellerEmail($config['sellerEmail']);
                    $objOminipay->setReturnUrl(env('ALIPAY_RETURN_URL', url('/order/pay/alipay/return')));
                    $objOminipay->setNotifyUrl(env('ALIPAY_NOTIFY_URL', url('/order/pay/alipay/notify')));
                    $response = Omnipay::purchase([
                        'out_trade_no' => $order->code, //your site trade no, unique
                        'subject' => \CommonClass::getConfig('site_name') . '余额充值', //order title
                        'total_fee' => $order->cash, //order total fee
                    ])->send();
                    return \CommonClass::formatResponse('确认充值', 200, array('url' => $response->getRedirectUrl(), 'orderCode' => Crypt::encrypt($order->code)));
                    break;
                case 'wechat':
                    return \CommonClass::formatResponse('确认充值', 200, array('url' => '/finance/wechatPay/' . Crypt::encrypt($order), 'orderCode' => Crypt::encrypt($order->code)));
                    break;
                case 'unionbank':
                    //TODO:暂未接入
                    break;
            }
        }
    }

    /**
     * 微信充值视图
     *
     * @param $order
     * @return mixed
     */
    public function getWechatPay($order)
    {
	Log::info('微信充值二维码页面');
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","financial");
        $this->theme->set("financialType","1");
        $this->theme->setTitle('我要充值');
        $this->theme->set("userOneColumn","财务管理");
        $this->theme->set("userOneColumnUrl","/finance/list");
        $this->theme->set("userSecondColumn","我要充值");
        $this->theme->set("userSecondColumnUrl","/finance/cash");

        $this->theme->setTitle('我要充值');
        $order = Crypt::decrypt($order);
        Log::info('微信充值'.$order);
        if ($order) {
            $config = ConfigModel::getPayConfig('wechatpay');
            $wechat = Omnipay::gateway('wechat');
            $wechat->setAppId($config['appId']);
            $wechat->setMchId($config['mchId']);
            $wechat->setAppKey($config['appKey']);
 Log::info('微信充值');
            $params = array(
                'out_trade_no' => $order->code, // billing id in your system
                'notify_url' => env('WECHAT_NOTIFY_URL', url('order/pay/wechat/notify')), // URL for asynchronous notify
                'body' => \CommonClass::getConfig('site_name') . '余额充值', // A simple description
                'total_fee' => $order->cash, // Amount with less than 2 decimals places
                'fee_type' => 'CNY', // Currency name from ISO4217, Optional, default as CNY
            );
            $response = $wechat->purchase($params)->send();
            $img = QrCode::size('280')->generate($response->getRedirectUrl());
            $view = array(
                'cash' => $order->cash,
                'img' => $img
            );
            return $this->theme->scope('pay.wechatpay', $view)->render();
        }

    }


    /**
     * 已完成付款验证订单状态
     *
     * @param $orderCode
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function verifyOrder($orderCode)
    {
        $orderCode = Crypt::decrypt($orderCode);

        $orderInfo = OrderModel::where('code', $orderCode)->first();

        if (!empty($orderInfo) && $orderInfo->status) {
            return \CommonClass::formatResponse('success', 200, array('url' => 'cash'));
        }
        return \CommonClass::formatResponse('fail');
    }

    /**
     * 提现视图
     *
     * @return mixed
     */
    public function getCashout()
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","financial");
        $this->theme->setTitle('我要提现');
        $this->theme->set("financialType","1");
        $this->theme->set("userOneColumn","财务管理");
        $this->theme->set("userOneColumnUrl","/finance/list");
        $this->theme->set("userSecondColumn","我要提现");
        $this->theme->set("userSecondColumnUrl","/finance/cashout");

        $user = Auth::User();
        $userInfo = UserDetailModel::select('balance')->where('uid', $user->id)->first();
        $cashRule = json_decode(ConfigModel::getConfigByAlias('cash')->rule, true);
        //获取绑定的支付宝、银行卡账号信息
        $alipayAccount = AlipayAuthModel::where('uid', $user->id)->where('status', 2)->orderBy('auth_time', 'desc')->get();
        $bankAccount = BankAuthModel::where('uid', $user->id)->where('status', 2)->orderBy('auth_time', 'desc')->get();
        $data = array(
            'balance' => $userInfo->balance,
            'alipayAccount' => $alipayAccount,
            'bankAccount' => $bankAccount,
            'cashRule' => $cashRule
        );
        return $this->theme->scope('finance.cashout', $data)->render();
    }

    /**
     * 提交提现
     *
     * @param CashoutRequest $request
     * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postCashout(CashoutRequest $request)
    {
        $user = Auth::User();
        $userInfo = UserDetailModel::select('balance')->where('uid', $user->id)->first();
        //获取提现配置项
        $cashConfig = ConfigModel::getConfigByAlias('cash');
        $cashConfig->rule = json_decode($cashConfig->rule, true);

        $now = strtotime(date('Y-m-d'));
        $start = date('Y-m-d H:i:s', $now);
        $end = date('Y-m-d H:i:s', $now + 24 * 3600);
        //用户当日提现总金额
        $cashoutSum = CashoutModel::where('uid', $user->id)->whereBetween('created_at', [$start, $end])->sum('cash');

        $error = array();
        if ($request->get('cash') > $userInfo->balance) {
            $error['cash'] = '提现金额不得大于账户余额';
        }
        if ($cashConfig->rule['withdraw_min'] && $request->get('cash') < $cashConfig->rule['withdraw_min']) {
            $error['cash'] = '单笔提现金额不得小于' . $cashConfig->rule['withdraw_min'] . '元';
        }
        if ($cashConfig->rule['withdraw_max'] && ($cashoutSum + $request->get('cash')) > $cashConfig->rule['withdraw_max']) {
            $error['cash'] = '提现金额不得大于' . $cashConfig->rule['withdraw_max'] . '元';
        }
        //判断用户是否实名认证
        $realName = RealnameAuthModel::where('uid',$user->id)->where('status',1)->first();
        $enterpriseAuth=EnterpriseAuthModel::where("uid",$user->id)->where("status",1)->first();
        if(empty($realName) && empty($enterpriseAuth)){
            $error['cash'] = '您还没有实名认证或者企业认证！请先进行实名认证';
        }
        if (!empty($error)) {
            // return back()->withErrors($error);
            return  back()->with('error',$error['cash']);
        }
        $account = $request->get('cashout_account');
        $alipayInfo = AlipayAuthModel::where('alipay_account', $account)->where('status', 2)->first();
        $bankInfo = BankAuthModel::where('bank_account', $account)->where('status', 2)->first();

        if (!empty($alipayInfo) || !empty($bankInfo)) {
            if (!empty($alipayInfo)) {
                $cashout_type = 1;
                $account_name = $alipayInfo->alipay_name;
            } elseif (!empty($bankInfo)) {
                $cashout_type = 2;
                $account_name = $bankInfo->realname;
            }
            $data = array(
                'cashout_type' => $cashout_type,
                'cashout_account' => $account,
                'cash' => $request->get('cash'),
                'account_name' => $account_name
            );
            return redirect('finance/cashoutInfo/' . Crypt::encrypt($data));
        }

    }


    /**
     * 提现记录详情
     *
     * @param $cashoutInfo
     * @return mixed
     */
    public function getCashoutInfo($cashoutInfo)
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","financial");
        $this->theme->setTitle('我要提现');
        $this->theme->set("financialType","1");
        $this->theme->set("userOneColumn","财务管理");
        $this->theme->set("userOneColumnUrl","/finance/list");
        $this->theme->set("userSecondColumn","我要提现");
        $this->theme->set("userSecondColumnUrl","/finance/cashout");
        $cashoutInfo = Crypt::decrypt($cashoutInfo);
        $user = Auth::User();
        $userInfo = UserDetailModel::select('balance')->where('uid', $user->id)->first();

        $is_bank = BankAuthModel::select('bank_name','bank_img')
            ->where(['bank_account' => $cashoutInfo['cashout_account'], 'status' => 2])->first();
        $data = array(
            'balance' => $userInfo->balance,
            'cashout_type' => $cashoutInfo['cashout_type'],
            'cashout_account' => $cashoutInfo['cashout_account'],
            'cash' => $cashoutInfo['cash'],
            'account_name' => $cashoutInfo['account_name'],
            'fees' => FinancialModel::getFees($cashoutInfo['cash']),
            'cashoutInfo' => Crypt::encrypt($cashoutInfo),
            'bank_name' => !empty($is_bank) ? $is_bank->bank_name : 'alipay',
            'bank_img' =>!empty($is_bank) ? $is_bank->bank_img : '',
        );
        return $this->theme->scope('finance.cashoutinfo', $data)->render();
    }

    /**
     * 确认提现处理
     *
     * @param CashoutInfoRequest $request
     * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postCashoutInfo(CashoutInfoRequest $request)
    {
        $alternate_password = $request->get('alternate_password');
        $cashoutInfo = Crypt::decrypt($request->get('cashInfo'));
        $user = Auth::User();
        if (UserModel::encryptPassword($alternate_password, $user->salt) === $user->alternate_password) {
            $data = array(
                'uid' => $user->id,
                'cash' => $cashoutInfo['cash'],
                'fees' => FinancialModel::getFees($cashoutInfo['cash']),
                'real_cash' => $cashoutInfo['cash'] - FinancialModel::getFees($cashoutInfo['cash']),
                'cashout_type' => $cashoutInfo['cashout_type'],
                'cashout_account' => $cashoutInfo['cashout_account'],
            );
            $status = CashoutModel::addCashout($data);

            if ($status)
                return redirect('/finance/Withdrawalrecord')->with(["message"=>"提现申请已提交"]);
        }
        return back()->withErrors(array('alternate_password' => '提现密码错误'));
    }

    /**
     * 提现处理视图
     *
     * @return mixed
     */
    public function waitCashout()
    {
        $this->initTheme('personalindex');
        return $this->theme->scope('finance.waitcashout')->render();
    }

    /**
     * 用户中心财务列表
     *
     * @return mixed
     */
    public function getFinanceList(Request $request)
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","financial");
        $this->theme->set("financialType","1");
        $this->theme->setTitle('财务明细');
        $this->theme->set("userOneColumn","财务明细");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","财务明细");
        $this->theme->set("userSecondColumnUrl","/user/list");
        $user = Auth::User();
        $userDetail=UserDetailModel::where("uid",Auth::user()->id)->first();
        $finance=FinancialModel::whereIn("action",[1,2,4,5,6,7,8,9,10,11,12,13,14])->where("uid",$user->id);
        //财务类型
        if($request->get("action")){
            $finance=$finance->where("action",$request->get("action"));
        }
        //财务流向
        if($request->get("status")){
          $finance=$finance->where("status",$request->get("status"));
        }
        //任务状态
        if($request->get("time")){
             $startTime=date("Y-m-d");
            switch($request->get("time")){
                case 1:
                    $startTime=date("Y-m-d",strtotime("-7 day"));
                    break;
                case 2:
                    $startTime=date("Y-m-d",strtotime("-1 month"));
                    break;
                case 3:
                    $startTime=date("Y-m-d",strtotime("-3 month"));
                    break;
                case 4:
                    $startTime=date("Y-m-d",strtotime("-6 month"));
                    break;
            }
            $finance=$finance->where("created_at",">",$startTime)->where("created_at","<=",date("Y-m-d H:i:s"));
        }
        $finance=$finance->orderBy("created_at","desc")->paginate(10);
        $merge=[
            'action'=>$request->get("action")?$request->get("action"):0,
            'status'=>$request->get("status")?$request->get("status"):0,
            'time'=>$request->get("time")?$request->get("time"):0,
            
        ];
        $data=[
            'user'=>$user,
             'userDetail'=>$userDetail,
             'merge'=>$merge,
             'finance'=>$finance,
        ];
        return $this->theme->scope('finance.financelist', $data)->render();
//        $user = Auth::User();
//
//        //结算推广者赏金
//        PromoteModel::settlementByUid($user->id);
//
//        if ($request->get('type')){
//            switch ($request->get('type')){
//                case 'cash':
//                    $list = FinancialModel::where('uid', $user->id)->where('action', 3)->orderBy('created_at', 'desc')->paginate(10);
//                    break;
//                case 'cashout':
//                    $list = CashoutModel::where('uid', $user->id)->orderBy('created_at', 'desc')->paginate(10);
//                    break;
//            }
//        } else {
//            $list = FinancialModel::where('uid', $user->id)->orderBy('created_at', 'desc')->paginate(10);
//        }
//        $userDetail = UserDetailModel::select('balance')->where('uid', $user->id)->first();
//
//        $actionArr = \CommonClass::getFinanceAction();
//        $incomeArr = \CommonClass::incomeArr();
//        $data = [
//            'balance' => $userDetail->balance,
//            'list' => $list,
//            'type' => $request->get('type') ? $request->get('type') : '',
//            'action_arr' => $actionArr,
//            'income_arr' => $incomeArr
//        ];
//
//        $this->initTheme('usercenterfinance');
//        $this->theme->setTitle('收支明细');
//        return $this->theme->scope('finance.financelist', $data)->render();
    }
    //充值记录
    public function rechargeRecord(Request $request){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","financial");
        $this->theme->setTitle('充值记录');
        $this->theme->set("financialType","2");
        $this->theme->set("userOneColumn","财务管理");
        $this->theme->set("userOneColumnUrl","/finance/list");
        $this->theme->set("userSecondColumn","充值记录");
        $this->theme->set("userSecondColumnUrl","/finance/rechargerecord");

        $finance = FinancialModel::where('uid', Auth::user()->id)->where('action', 3);
        if($request->get("time")){
            $startTime=date("Y-m-d");
            switch($request->get("time")){
                case 1:
                    $startTime=date("Y-m-d",strtotime("-7 day"));
                    break;
                case 2:
                    $startTime=date("Y-m-d",strtotime("-1 month"));
                    break;
                case 3:
                    $startTime=date("Y-m-d",strtotime("-3 month"));
                    break;
                case 4:
                    $startTime=date("Y-m-d",strtotime("-6 month"));
                    break;
            }
            $finance=$finance->where("created_at",">",$startTime)->where("created_at","<=",date("Y-m-d H:i:s"));
        }
        $finance=$finance->orderBy('created_at', 'desc')->paginate(10);
        $data=[
            'finance'=>$finance,
            'merge' =>["time"=>$request->get("time")?$request->get("time"):0],
        ];
        return $this->theme->scope('finance.rechargerecord', $data)->render();
    }

    //提现记录
    public function WithdrawalRecord(Request $request){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","financial");
        $this->theme->setTitle('提现记录');
        $this->theme->set("financialType","3");
        $this->theme->set("userOneColumn","财务管理");
        $this->theme->set("userOneColumnUrl","/finance/list");
        $this->theme->set("userSecondColumn","提现记录");
        $this->theme->set("userSecondColumnUrl","/finance/Withdrawalrecord");
        $cashOut = CashoutModel::where('uid', Auth::user()->id);
        if($request->get("time")){
            $startTime=date("Y-m-d");
            switch($request->get("time")){
                case 1:
                    $startTime=date("Y-m-d",strtotime("-7 day"));
                    break;
                case 2:
                    $startTime=date("Y-m-d",strtotime("-30 day"));
                    break;
                case 3:
                    $startTime=date("Y-m-d",strtotime("-90 day"));
                    break;
                case 4:
                    $startTime=date("Y-m-d",strtotime("-180 day"));
                    break;
            }
            $cashOut=$cashOut->where("created_at",">",$startTime)->where("created_at","<=",date("Y-m-d H:i:s"));
        }
        $cashOut=$cashOut->orderBy('created_at', 'desc')->paginate(10);
        $status=[
            0=>'待审核',
            1=>'提现成功',
            2=>'提现失败',
        ];
        $data=[
            'cashOut'=>$cashOut,
            'merge' =>["time"=>$request->get("time")?$request->get("time"):0],
            'status'=>$status,
        ];
        return $this->theme->scope('finance.Withdrawalrecord', $data)->render();
    }

    //我的现金券
    public function mycoupon(Request $request){
        $this->initTheme('personalindex');
        $this->theme->setTitle('我的现金劵');
        $this->theme->set("userColumnLeft","userVip");
        $this->theme->set("userVip","4");
        $this->theme->set("userOneColumn","财务管理");
        $this->theme->set("userOneColumnUrl","/finance/list");
        $this->theme->set("userSecondColumn","我的现金劵");
        $this->theme->set("userSecondColumnUrl","/finance/mycoupon");
        $merge=[
            'status'=>$request->get("status")?$request->get("status"):0,
        ];
        $user=Auth::user();
        $userHavCoupon=UserCouponModel::where("uid",$user->id)->lists("coupon_id")->toArray();
        //获取所有优惠券
        $userCoupon1=[];
        if($merge['status'] ==0){
            $userCoupon=CouponModel::where("store_num",">",0)->where("status",2)->where(function($query){
                $query->where('uid',null)->orwhere('uid','')->orwhere('uid','0');
            })->paginate(10);
            $userCoupon1 = CouponModel::where("store_num",">",0)->where("status",2)->where("uid",$user->id)->paginate(10);
        }else{
            $userCoupon=UserCouponModel::leftJoin("coupon","user_coupon.coupon_id","=","coupon.id")->where("user_coupon.uid",$user->id)->where('coupon.id','!=','');
            $userCoupon=$userCoupon->where("user_coupon.status",$merge['status']);
            $userCoupon=$userCoupon->select("coupon.name","coupon.price","coupon.start_time","coupon.end_time","user_coupon.status")
                ->orderBy("user_coupon.created_at","desc")->paginate(10);
        }
        
        $data=[
            'userCoupon'=>$userCoupon,
            'userCoupon1'=>$userCoupon1,
            'merge'=>$merge,
            'userHavCoupon'=>$userHavCoupon,
        ];
        return $this->theme->scope('finance.mycoupon', $data)->render();
    }
     /*
      * 领取优惠卷
      * */
    public function mycouponGet($id){
        $coupon=CouponModel::where("id",$id)->where("store_num",">",0)->first();
        if(!$coupon){
            return back()->with(["message"=>"该优惠卷已领完"]);
        }
        $res=UserCouponModel::create([
              'uid'=>Auth::user()->id,
              'coupon_id'=>$id,
              'status'=>1,
              'created_at'=>date("Y-m-d H:i:s"),
              'end_time' =>$coupon['end_time'],
        ]);
        if($res){
            //减少优惠券库存量
            CouponModel::where("id",$id)->decrement("store_num");
            return back()->with(["message"=>"领取成功"]);
        }
        return back()->with(["message"=>"领取失败"]);
    }
    /**
     * 资产明细列表
     * @return mixed
     */
    public function assetdetail(Request $request)
    {

        $user = Auth::User();
        $balance = UserDetailModel::where('uid', $user->id)->first()->balance;
        $list = FinancialModel::where('uid', $user->id);

        $cashIn = FinancialModel::where('uid', $user->id)->whereIn('action', [2, 3, 7, 8])->sum('cash');
        $cashOut = FinancialModel::where('uid', $user->id)->whereIn('action', [1, 4, 5, 6])->sum('cash');

        if ($request->get('start')) {
            $start = date('Y-m-d H:i:s', strtotime($request->get('start')));
            $list = $list->where('created_at', '>', $start);
        }
        if ($request->get('end')) {
            $end = date('Y-m-d H:i:s', strtotime($request->get('end')));
            $list = $list->where('created_at', '<', $end);
        }
        if ($request->get('type')) {
            $list = $list->where('action', $request->get('type'));
        }
        $list = $list->orderBy('created_at', 'desc')->paginate(10);
        $actionArr = \CommonClass::getFinanceAction();
        $incomeArr = \CommonClass::incomeArr();
        $paytype = \CommonClass::getPayType();
        $data = [
            'balance' => $balance,
            'list' => $list,
            'cashIn' => $cashIn,
            'cashOut' => $cashOut,
            'start' => $request->get('start'),
            'end' => $request->get('end'),
            'type' => $request->get('type'),
            'merge' => $request->all(),
            'action_arr' => $actionArr,
            'income_arr' => $incomeArr,
            'pay_type' => $paytype
        ];
        $this->initTheme('userfinance');
        $this->theme->setTitle('资产明细');
        return $this->theme->scope('finance.assetdetail', $data)->render();
    }

    /**
     * 收支详情
     *
     * @return mixed
     */
    public function assetDetailminute($id)
    {

        $user = Auth::User();
        $avatar = UserDetailModel::where('uid', $user->id)->first()->avatar;
        $info = FinancialModel::where('uid', $user->id)->where('id', $id)->first();
        if (!empty($info)) {
            $actionArr = \CommonClass::getFinanceAction();
            $paytype = \CommonClass::getPayType();
            $data = [
                'info' => $info,
                'avatar' => $avatar,
                'action_arr' => $actionArr,
                'pay_type' => $paytype
            ];

            $this->initTheme('userfinance');
            $this->theme->setTitle('收支详情');
            return $this->theme->scope('finance.assetDetailminute', $data)->render();
        }

    }

    /**
     * 购买商品增值服务付款视图
     *
     * @return mixed
     */
    public function getpay($id)
    {
        $uid = Auth::id();
        //查询用户余额
        $userInfo = UserDetailModel::select('balance')->where('uid', $uid)->first();

        $payConfig = ConfigModel::getConfigByType('thirdpay');

        //查询是否开启推荐商品增值工具
        $isOpenArr = ServiceModel::where('identify','ZUOPINTUIJIAN')->first();
        if(!empty($isOpenArr) && $isOpenArr->status == 1){
            $cash = $isOpenArr->price;
        }else{
            return redirect()->back()->with(array('message' => '没有开启该服务'));
        }

        $data = [
            'service_cash' => $cash,
            'pay_config' => $payConfig,
            'balance' => $userInfo->balance,
            'good_id' => $id
        ];
        $this->theme->setTitle('购买增值服务');
        return $this->theme->scope('finance.getpay', $data)->render();
    }

    /**
     * 余额购买增值服务
     *
     * @param Request $request
     * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function balancePayment(Request $request)
    {
        if ($request->get('password')){
            $user = UserModel::find(Auth::id());
            $pwd = UserModel::encryptPassword($request->get('password'), $user->salt);

            if ($pwd == $user->alternate_password){
                $good_id = $request->get('good_id');
                //购买推荐增值服务
                $status = ShopOrderModel::buyShopService($good_id);
                if ($status){
                    return redirect('user/waitGoodsHandle/'.$good_id);
                }
            } else {
                return back()->withErrors(['password' => '请输入正确的支付密码']);
            }
        }
    }


    /**
     * 第三方购买商品推荐增值服务
     * @param Request $request
     * @return mixed
     */
    public function thirdPayment(Request $request)
    {
        $payType = $request->get('pay_type');
        $goodId = $request->get('good_id');
        //查询是否开启推荐商品增值工具
        $isOpenArr = ServiceModel::where('identify','ZUOPINTUIJIAN')->first();
        if(!empty($isOpenArr) && $isOpenArr->status == 1){
            $cash = $isOpenArr->price;
        }else{
            $cash = '';
        }
        //写入增值服务商品关系表
        $serviceGoodsId = GoodsServiceModel::insertGetId(['service_id' => $isOpenArr->id, 'goods_id' => $goodId]);
        //写入订单
        $data = [
            'code' => ShopOrderModel::randomCode(Auth::id(), 'pg'),
            'title' => '购买商品推荐增值服务',
            'uid' => Auth::id(),
            'object_id' => $serviceGoodsId,
            'object_type' => 3,
            'cash' => $cash,
            'status' => 0,
            'created_at' => date('Y-m-d H:i:s',time())
        ];
        $shop = ShopOrderModel::create($data);



        switch ($payType){
            case 'alipay':
                $config = ConfigModel::getPayConfig('alipay');
                $objOminipay = Omnipay::gateway('alipay');
                $objOminipay->setPartner($config['partner']);
                $objOminipay->setKey($config['key']);
                $objOminipay->setSellerEmail($config['sellerEmail']);
                $objOminipay->setReturnUrl(env('ALIPAY_RETURN_URL', url('/order/pay/alipay/return')));
                $objOminipay->setNotifyUrl(env('ALIPAY_NOTIFY_URL', url('/order/pay/alipay/notify')));
                $response = Omnipay::purchase([
                    'out_trade_no' => $shop->code, //your site trade no, unique
                    'subject' => \CommonClass::getConfig('site_name'), //order title
                    'total_fee' => $shop->cash, //order total fee
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
                    'out_trade_no' => $shop->code, // billing id in your system
                    'notify_url' => env('WECHAT_NOTIFY_URL', url('order/pay/wechat/notify')), // URL for asynchronous notify
                    'body' => \CommonClass::getConfig('site_name') . '余额充值', // A simple description
                    'total_fee' => $shop->cash, // Amount with less than 2 decimals places
                    'fee_type' => 'CNY', // Currency name from ISO4217, Optional, default as CNY
                );
                $response = $wechat->purchase($params)->send();

                $img = QrCode::size('280')->generate($response->getRedirectUrl());
                $view = array(
                    'cash' => $shop->cash,
                    'img' => $img
                );
                return $this->theme->scope('pay.wechatpay', $view)->render();
                break;
        }

    }




    /*发布商品付款成功*/
    public function shopsuccess($id)
    {
        $data = array(
            'id' => $id
        );
        return $this->theme->scope('finance.shopsuccess', $data)->render();
    }
}
