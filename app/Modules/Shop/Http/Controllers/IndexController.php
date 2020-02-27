<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\IndexController as BasicIndexController;
use App\Modules\Advertisement\Model\AdTargetModel;
use App\Modules\Advertisement\Model\RecommendModel;
use App\Modules\Employ\Models\EmployCommentsModel;
use App\Modules\Employ\Models\EmployGoodsModel;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Employ\Models\UnionAttachmentModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\CateModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\SeoModel;
use App\Modules\Manage\Model\UserCouponModel;
use App\Modules\Manage\Model\VipModel;
use App\Modules\Manage\Model\ZdfastbuyModel;
use App\Modules\Shop\Models\GoodsCommentModel;
use App\Modules\Shop\Models\ProgrammeEnquiryMessageModel;
use App\Modules\Shop\Models\ProgrammeInquiryPayModel;
use App\Modules\Shop\Models\ProgrammeOrderModel;
use App\Modules\Shop\Models\ProgrammeOrderSubModel;
use App\Modules\Shop\Models\ShopViewLogModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\BankAuthModel;
use App\Modules\User\Model\CollectionModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\UserAdderModel;
use App\Modules\User\Model\UserCouponLogModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserVipConfigModel;
use Illuminate\Http\Request;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Shop\Models\ShopFocusModel;
use App\Modules\Manage\Model\SeoRelatedModel;
use Auth;
use DB;
use Illuminate\Support\Facades\Session;
use Omnipay;
use QrCode;
use Toplan\TaskBalance\Task;

class IndexController extends BasicIndexController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('common');
    }
    
    /**
     * 方案超市
     * @param Request $request
     * @return mixed
     */
    public function fananshop(Request $request)
    {
        $this->initTheme('shop');
        /*屏蔽异常访问*/
        $hmsr = $request->get('hmsr');
        $hmpl = $request->get('hmpl');
        if(isset($hmsr) || isset($hmpl) ){
            exit('请稍后访问！');
        }
        $merge = [
          'industry'    => $request->get('industry')?$request->get('industry'):0,
          'type'        => $request->get('type')?$request->get('type'):0,
          'desc'        => $request->get('desc')?$request->get('desc'):'default',
          'label'       => $request->get('label')?$request->get('label'):0,
          'keywords'    => $request->get('keywords')?$request->get('keywords'):'',
        ];
        //排序问题
        switch ($merge['desc']){
            case 'release'://发布时间
                $order = "created_at";
                break;
            case 'popularity'://人气量
                $order = "view_num";
                break;
            case 'volume'://成交量
                $order = "sales_num";
                break;
            default:
                $order = "id";
                break;
        }
        //获取行业分类
        $industryType = CateModel::where("type",1)->orderBy('sort','desc')->get()->toArray();
        //获取技能标签
        $labelType = CateModel::where("type",2)->get()->toArray();
        //方案列表
        $programmeList = GoodsModel::select("cate.name as ca_name","goods.*","attachment.url as at_url")
             ->leftJoin("cate","goods.cate_id","=","cate.id")
             ->leftJoin('attachment','goods.cover','=','attachment.id')
             ->where('goods.status',1)->where('is_delete',0);
        //行业分类筛选
        if($request->get('industry')){
            $programmeList = $programmeList->where('goods.cate_id',$request->get('industry'));
        }
        //方案类型
        if($request->get('type')){
            $programmeList=$programmeList->where('goods.type',$request->get('type'));
        }
        //根据关键字搜索
        if($request->get('keywords')){
            $programmeList=$programmeList->where('goods.title','like',"%".$request->get('keywords')."%");
            //有关键字添加搜索记录
            \CommonClass::get_keyword($request->get('keywords'),4);
        }
        //技能标签
        if($request->get('label')){
            $programmeList=$programmeList->where('goods.skill_id',$request->get('type'));
        }
       $programmeList = $programmeList->orderBy('goods.index_sort','desc')->orderBy($order,'desc')->paginate(12);
        //方案横向列表
        $programmeTranList=GoodsModel::select("cate.name as ca_name","goods.*","attachment.url as at_url")
           ->leftJoin("cate","goods.cate_id","=","cate.id")
           ->leftJoin('attachment','goods.cover','=','attachment.id')
           ->where('goods.status',1)->where('is_delete',0);
        //行业分类筛选
        if($request->get('industry')){
            $programmeTranList=$programmeTranList->where('goods.cate_id',$request->get('industry'));
        }
        //方案类型
        if($request->get('type')){
            $programmeTranList=$programmeTranList->where('goods.type',$request->get('type'));
        }
        if($request->get('keywords')){
            $programmeTranList=$programmeTranList->where('goods.title','like',"%".$request->get('keywords')."%");
        }
        //技能标签
        if($request->get('label')){
            $programmeTranList=$programmeTranList->where('goods.skill_id',$request->get('type'));
        }
       $programmeTranList=$programmeTranList->orderBy($order,'desc')->paginate(8);

       /*$recommProgram = GoodsModel::select("cate.name as ca_name","users.name as u_name","goods.*","attachment.url as at_url")
           ->leftJoin("cate","goods.cate_id","=","cate.id")->leftJoin('users','goods.uid','=','users.id')
           ->leftJoin('attachment','goods.cover','=','attachment.id')
           ->where('goods.status',1)->where('is_delete',0)->where('is_recommend',1)->limit(13)->get();*/

       //今天发布数量
       $todaySum = GoodsModel::where('created_at','>=',date('Y-m-d 00:00:00'))->where('created_at','<=',date('Y-m-d H:i:s'))->count();
       $todaySum = $todaySum + 20;
        //总发布数量
        $totalSum = GoodsModel::whereRaw('1=1')->count();
        $ad = AdTargetModel::getAdByCodePage('GOODS');
        /*$ad = AdTargetModel::getAdByTypeId(2);
        AdTargetModel::addViewCountByCode('GOODS');*/
        /*//推荐方案
        $goodsRecommendList = RecommendModel::getRecommendByCode('GOODS_LIST','goods',['shop_info' => true,'goods_field' => true]);*/
        //方案排行榜
        /*$goodRank = GoodsModel::select('users.name as u_name','cate.name as cate_name','goods.*','attachment.url as at_url')->leftJoin('cate','goods.cate_id','=','cate.id')
            ->leftJoin('users','goods.uid','=','users.id')->leftJoin('attachment','goods.cover','=','attachment.id')->orderBy('goods.sales_num','desc')->limit(4)->get();*/
        //推荐方案轮播
        $goodsRecommendWindow = RecommendModel::getRecommendByCode('GOODS_WINDOW','goods',['shop_info' => true,'goods_field' => true]);
        $goodsRecommendWindow = \CommonClass::arrOper($goodsRecommendWindow,3);
        //推荐项目
        $taskRecommendList = RecommendModel::getRecommendByCode('GOODS_LIST','task');
        //方案询价(最新20个)
        $inquiry = ProgrammeInquiryPayModel::select('id','programme_id','uid','created_at')->with('goods','user')->orderBy('created_at','desc')->limit(20)->get()->toArray();
        //获取热门seo 标签
        $seoLabel=SeoModel::orderBy("view_num","desc")->limit(8)->get();
        $data=[
            'industryType'         => $industryType,
            'labelType'            => $labelType,
            'programmeList'        => $programmeList,
            'programmeTranList'    => $programmeTranList,
            'merge'                => $merge,
            'todaySum'             => $todaySum,
            'totalSum'             => $totalSum,
            'ad'                   => $ad,
            //'goodsRecommendList'   => $goodsRecommendList,
            'goodsRecommendWindow' => $goodsRecommendWindow,
            'taskRecommendList'    => $taskRecommendList,
            'inquiry'              => $inquiry,
            //'goodRank'      => $goodRank,
            'page'  =>$request->get("page") ? $request->get("page"):0,
            'seoLabel'=>$seoLabel,
        ];
        $this->theme->set('nav_url', '/facs');
        $seoConfig = ConfigModel::getConfigByType('seo');
        if(!empty($seoConfig['seo_supermarket']) && is_array($seoConfig['seo_supermarket'])){
            $this->theme->setTitle($seoConfig['seo_supermarket']['title']);
            $this->theme->set('keywords',$seoConfig['seo_supermarket']['keywords']);
            $this->theme->set('description',$seoConfig['seo_supermarket']['description']);
        }else{
            $this->theme->setTitle('方案超市');
        }
        return $this->theme->scope('shop.fananshop', $data)->render();
    }

    /**
     * 方案超市详情
     * @param $id
     * @return mixed
     */
    public function fananshopDetail($id){
        GoodsModel::where("id",$id)->increment("view_num");
        $goods = GoodsModel::find($id);
        if(!$goods){
            return redirect()->to('404');
            // return back()->with(['message'=>"参数错误"]);
        }
        //获取交付形式
        $deliveryCateId=explode(",",$goods['delivery_cate_id']);
        $deliveryCate=TaskCateModel::whereIn("id",$deliveryCateId)->lists("name")->toArray();
        //获取行业分类
        $industryType = CateModel::where("type",1)->lists('name','id')->toArray();
        //开发平台
        $ideCateId=explode(",",$goods['ide_cate_id']);
        $ideCate=TaskCateModel::whereIn("id",$ideCateId)->lists("name")->toArray();
        //获取封面
        $goods['at_url']=AttachmentModel::where("id",$goods['cover'])->pluck("url");
        //店铺信息
        $findShop = ShopModel::shopOneInfo($goods['uid']);
        $this->theme->set("shopInfo",$findShop);
        $this->theme->set('SHOPID',$findShop['id']);
        $this->theme->set('SHOPSORT',2);
        if(!$findShop){
               $this->initTheme('fastpackage');
        }else{
            if($findShop->shop_template_stauts == 1){//普通店铺
                $this->initTheme('fastpackage');
            }elseif($findShop->shop_template_stauts == 2){//店铺装修模板1
                $this->initTheme('serviceshop');
            }elseif($findShop->shop_template_stauts == 3){//店铺装修模板1
                $this->initTheme('templatemaintow');
            }
            //查询认证
            $auth = ShopModel::getShopAuth([$findShop['uid']]);
            //任务选中记录
            $taskIdArr = WorkModel::where('uid',$findShop['uid'])->where('status',1)->lists('task_id')->toArray();
            $findShop['PriceCount'] = TaskModel::where('type_id',1)
                        ->where('is_del',0)
                        ->where('status','>=',2)
                        ->where('status','<',10)
                        ->where('task.status','!=',3)
                        ->where('is_open',1)
                        ->whereIn('id',$taskIdArr)
                        ->sum('bounty');
        }
        $cateName = CateModel::where("id",$goods['cate_id'])->first();
        //方案排行榜
        /*$goodRank = GoodsModel::select('users.name as u_name','cate.name as cate_name','goods.*','attachment.url as at_url')->leftJoin('cate','goods.cate_id','=','cate.id')
            ->leftJoin('users','goods.uid','=','users.id')->leftJoin('attachment','goods.cover','=','attachment.id')->orderBy('goods.sales_num','desc')->limit(4)->get();*/
        //方案详情其他图片
        $goodOtherPic = UnionAttachmentModel::leftJoin('attachment','union_attachment.attachment_id','=','attachment.id')
            ->where('union_attachment.object_id',$id)->where('union_attachment.object_type','5')->select('attachment.url')->get();
        //方案PDF附件其他图片
        $gooddocPic = UnionAttachmentModel::leftJoin('attachment','union_attachment.attachment_id','=','attachment.id')
            ->where('union_attachment.object_id',$id)->where('union_attachment.object_type','7')->select('attachment.id','attachment.url')->get();
        foreach ($gooddocPic as $key => $value) {
                $gooddocPic[$key]['name'] = '附件'.($key+1);
        }
        //查询是否收藏过
        $is_collect = false;
        if(Auth::check()){
            $collect = CollectionModel::where("uid",Auth::user()->id)->where("type",1)->where("collec_id",$id)->first();
            if($collect){
                $is_collect = true;
            }
        }
        $is_enquiry=true;
        $getConfigByUid=UserVipConfigModel::getConfigByUid($goods['uid']);
        //获取该用户的询价总数
        $date = date("Y-m-d");
        $firstday = date('Y-m-01', strtotime($date));
        $lastday = date('Y-m-d', strtotime("$firstday +1 month -1 day"));
        $enquiryCount = ProgrammeEnquiryMessageModel::where("consultant_id",$goods['uid'])
                                                    ->where("created_at",">=",$firstday)
                                                    ->where("created_at","<=",$lastday)
                                                    ->where("type",1)
                                                    ->count();
        if($enquiryCount >=$getConfigByUid['inquiry_num']){
            $is_enquiry=false;
        }
        /*方案留言列表*/
        $leavMessageList = ProgrammeEnquiryMessageModel::where("consult_type",'1')->where("programme_id",$id)->paginate("10");
        

        /*
         * 给店铺添加浏览记录以及日志记录
        */
        ShopModel::addShopView($goods['uid'],$findShop['id']);
        //查看店铺浏览量
        //获取当天
//         $shopView['day'] = ShopViewLogModel::where('user_id',$goods['uid'])->where("create_at","like","%".date("Y-m-d",strtotime('-1 day'))."%")->count();
        //获取时间节点
        $weekDate = \CommonClass::getWeekStartEnd();
        // $shopView['week'] = ShopViewLogModel::where('user_id',$goods['uid'])->where("create_at",">=",$weekDate['start'])->where("create_at","<=",$weekDate['end'])->count();
        //获取所有的浏览量
//         $shopView['all'] = ShopViewLogModel::where('user_id',$goods['uid'])->count();
		//获取店铺的收藏量
//		$shopCollect = ShopFocusModel::where("shop_id",$goods['shop_id'])->count();

        $fieldId = $goods->cate_id;

        //相关项目
        $likeTask = TaskModel::where('type_id',1)->where('is_del',0)->where('status','>=',2)
            ->where('status','<',10)->where('task.status','!=',3)
            ->where('field_id',$fieldId)->where('is_open',1)
            ->select('title','id','bounty')
            ->orderBy('top_status','desc')->orderBy('id','desc')->limit(20)->get()->toArray();
        //相关方案
        $likeGoods = GoodsModel::where('cate_id',$fieldId)->where('id','!=',$id)->where('status','1')
            ->with('cover','field','user')
            ->orderBy('sales_num','desc')->limit(5)->get()->toArray();
        //.右边推荐元器件获取
        $zdfastbuglist2=ZdfastbuyModel::where('id','>','0')->where('show_location',2)->where('is_del','0')->select('id','url','aurl')->get();
        //.获取热门seo 标签
        $seoLabel=SeoModel::where('id','>',0)->select('id','name','view_num')->orderBy("view_num","desc")->get()->toArray();
        $data= [
            'goods'         => $goods,
            'shopInfo' => $findShop,
            'leavMessageList' => $leavMessageList,
            'cateName'      => $cateName,
            //'goodRank'      => $goodRank,
            'likeGoods'     => $likeGoods,
            'likeTask'      => $likeTask,
            'goodOtherPic'  => $goodOtherPic,
            'gooddocPic'    => $gooddocPic,
            'is_collect'    => $is_collect,
//             'shopView'      => $shopView,
//			'shopCollect'   => $shopCollect,
            'is_enquiry'   =>$is_enquiry,
            'deliveryCate'=>$deliveryCate,
            'ideCate'=>$ideCate,
            'industryType'=>$industryType,
            'zdfastbuglist2'=> $zdfastbuglist2,
            'enquiryCount'=> $enquiryCount,
            'seoLabel'   =>$seoLabel,
        ];

        /*seo配置信息*/
        $SeoRelated = SeoRelatedModel::where("related_id",$id)->where("type","2")->leftjoin("seo","seo.id","=","seo_related.seo_id")->lists('seo.name');
        $seorelatedname = '';
        if($SeoRelated){
           foreach ($SeoRelated as $key => $value) {
                $seorelatedname .= $value.'、';
            } 
        }
        $seoConfig = ConfigModel::getConfigByType('seo');
        if(!empty($seoConfig['seo_supermarketdetail']) && is_array($seoConfig['seo_supermarketdetail'])){
            $this->theme->setTitle($goods['title']." - ".$seoConfig['seo_supermarketdetail']['title']);
            $this->theme->set('keywords',$seorelatedname.$cateName['name'].$seoConfig['seo_supermarketdetail']['keywords']);
        }else{
            $this->theme->setTitle($goods['title']);
            $this->theme->set('keywords',$goods['title'].'、'.$cateName['name']);
        }
        $this->theme->set('description',mb_substr(strip_tags($goods['desc']),0,200,'utf-8'));
        return $this->theme->scope('shop.casedetailshop', $data)->render();
    }
    //方案超市 方案收藏
    public function  programmeCollect(Request $request){
        if(!Auth::check()){
            return redirect("/login");
        }
        //判断是否已经收藏过
        $collect=CollectionModel::where("uid",Auth::user()->id)
                   ->where("collec_id",$request->get("programme_id"))->where("type",1)->first();
        if($collect){
            return ["code" =>1001];
        }
        $res=CollectionModel::insert([
            'uid'=>Auth::user()->id,
            'collec_id'=>$request->get("programme_id"),
            'type'=>1,
            'created_at'=>date("Y-m-d"),
            ]);
        if($res){
            return ["code"=>1000];
        }
           return ["code"=>1002];
    }
    //方案超市 方案取消收藏
    public function  programmeCancelCollect(Request $request){
        if(!Auth::check()){
            return redirect("/login");
        }
        //判断是否已经收藏过
        $collect=CollectionModel::where("uid",Auth::user()->id)
            ->where("collec_id",$request->get("programme_id"))->where("type",1)->first();
        if(!$collect){
            return ["code" =>1001];
        }
        $res=CollectionModel::where("uid",Auth::user()->id)
            ->where("collec_id",$request->get("programme_id"))->where("type",1)->delete();
        if($res){
            return ["code"=>1000];
        }
        return ["code"=>1002];
    }
    //方案超市/询价
    public function inquiry($programme_id){
      if(!Auth::check()){
          return redirect("/login");
      }
      $this->initTheme('shopenquiry');
        //获取当月剩余的次数
      $dayNum=UserModel::userRoot(Auth::user()->id,"inquiry_num");
        //获取方案信息
      $programme=GoodsModel::find($programme_id);
        //方案用户信息
      $prograUserInfo=UserDetailModel::select("user_detail.*","users.email")->leftJoin('users',"user_detail.uid","=","users.id")->where("user_detail.uid",$programme['uid'])->first();
        //获取当前用户信息
      $userDetail=UserDetailModel::where("uid",Auth::user()->id)->first();
      //获取会员名称
       $member_name="普通会员";
       if(Auth::user()->level > 1){
           $member_name=VipModel::where("grade",Auth::user()->level)->pluck("name");
       }
      //查看联系方式费用
      $inquiryPrice=ConfigModel::where("alias","inquiry_price")->first();
       //查询是否购买的询价查看信息服务
       $is_pay=false;
       $programmeInquiry=ProgrammeInquiryPayModel::where("uid",Auth::user()->id)->where("status",2)->where("programme_id",$programme_id)->whereIn("type",[1,3])->first();
        if($programmeInquiry){
            $is_pay=true;
        }
        //.根据金额获取对应的优惠券
        $userCoupon=UserCouponModel::getCoupon($inquiryPrice['rule'],[0,4]);

        //查询用户绑定的银行卡信息
        $bank = BankAuthModel::where('uid', '=', Auth::user()->id)->where('status', '=', 4)->get();
        //判断第三方支付是否开启
        $payConfig = ConfigModel::getConfigByType('thirdpay');
        $balance_pay = false;
        if ($userDetail['balance'] >= $inquiryPrice['rule']) {
            $balance_pay = true;
        }
      $data=[
          'programme'=>$programme,
          'prograUserInfo'=>$prograUserInfo,
          'userDetail'=>$userDetail,
          'inquiryPrice'=>$inquiryPrice,
          'is_pay'=>$is_pay,
          'balance_pay'   => $balance_pay,
          'bank'          => $bank,
          'payConfig'     => $payConfig,
          'dayNum'       =>$dayNum,
          'member_name' =>$member_name,
          'userCoupon' =>$userCoupon,
      ];
      return $this->theme->scope('shop.inquiry', $data)->render();
    }
    //询价付款
    public function inquiryPay(Request $request){
        if(!Auth::check()){
            return redirect("/login");
        }
        $data=$request->except("_token");
        $userDetail = UserDetailModel::where("uid",Auth::user()->id)->first();
        $inquiryPrice = ConfigModel::where("alias","inquiry_price")->first();
        $resPrice=[];
        if(isset($data['userCoupon']) && $data['userCoupon']>0){
            //获取优惠券减免金额
            $resPrice = UserCouponModel::getEndPrice($inquiryPrice['rule'],$data['userCoupon']);
            $inquiryPrice['rule']=round($resPrice['endPrice'],2);
            if($resPrice['endPrice'] == 0){
                $data['pay_canel']=0;
            }
        }
        if($data['pay_canel'] ==0){//余额支付
            if(Auth::user()->alternate_password !=UserModel::encryptPassword($data['zfpwd'],Auth::user()->salt)){
                return back()->with(["message"=>"该支付密码错误"]);
            }
            //判断支付金额是否满足订单
            if(floatval($userDetail['balance']) < floatval($inquiryPrice['rule'])){
                return back()->with(["message"=>"用户余额不足"]);
            }
            $order_num=\CommonClass::createNum('fa',4);

            //进行支付业务逻辑处理
            $res=DB::transaction(function() use($data,$userDetail,$inquiryPrice,$order_num,$resPrice){
                //获取方案
                $programme = GoodsModel::where("id",$data['programme_id'])->first();
                //用户余额减少
                UserDetailModel::where("uid",Auth::user()->id)->decrement("balance",$inquiryPrice['rule']);
                //存储购买该方案存储表
                ProgrammeInquiryPayModel::insert([
                    'order_num'=>$order_num,
                    'programme_id'=>$data['programme_id'],
                    'uid'  =>Auth::user()->id,
                   // 'consultant_id'=>$programme['uid'],
                    'price'=>$inquiryPrice['rule'],
                    'created_at'=>date("Y-m-d H:i:s"),
                    'payment_at'=>date("Y-m-d H:i:s"),
                    'status'=>2,
                    'type'=>1,
                ]);
                //留言记录表存储
                ProgrammeEnquiryMessageModel::create([
                    'programme_id'=>$data['programme_id'],
                    'consult_type'=>2,
                    'content'=>'',
                    'uid'=>Auth::user()->id,
                    'consultant_id'=>$programme['uid'],
                    'created_at'=>date("Y-m-d H:i:s"),
                    'pay_type'=>2
                ]);
                $data['action']=8;
                $data['pay_type']=1;
                $data['cash']=$inquiryPrice['rule'];
                $data['uid']=Auth::user()->id;
                $data['status']=2;
                $data['remainder']=floatval($userDetail['balance']) - floatval($inquiryPrice['rule']);
                if(isset($data['userCoupon']) && $data['userCoupon']>0){
                    //处理优惠券
                    UserCouponLogModel::userCouponHandle($order_num,$data['uid'],2,$data['userCoupon']);
                    $data['coupon']=$resPrice['coupon'];
                }
                //平台收益存储
                FinancialModel::createOne($data);
                return $data;
            });
            if($res){
                return back()->with(["message"=>"购买成功"]);
            }
            return back()->with(["message"=>"购买失败"]);
        }else if (isset($data['pay_type']) && $data['pay_canel'] == 1) {
            $order_num=\CommonClass::createNum('xj',4);
            //获取方案
            $programme = GoodsModel::where("id",$data['programme_id'])->first();
            
            ProgrammeInquiryPayModel::insert([
                'order_num'=>$order_num,
                'programme_id'=>$data['programme_id'],
                'uid'  =>Auth::user()->id,
                'price'=>$inquiryPrice['rule'],
                'created_at'=>date("Y-m-d H:i:s"),
                'status'=>1
            ]);
            //留言记录表存储
            ProgrammeEnquiryMessageModel::create([
                'programme_id'=>$data['programme_id'],
                'consult_type'=>2,
                'content'=>'',
                'uid'=>Auth::user()->id,
                'consultant_id'=>$programme['uid'],
                'created_at'=>date("Y-m-d H:i:s"),
                'pay_type'=>1
            ]);
            if($request->get("action") !='deposit' && $request->get("userCoupon") >0){
                //处理优惠券
                UserCouponLogModel::userCouponHandle($order_num,Auth::user()->id,1,$request->get("userCoupon"));
            }
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
                    'out_trade_no' => $order_num, //your site trade no, unique
                    'subject'      => \CommonClass::getConfig('site_name'), //order title
                    'total_fee'    => $inquiryPrice['rule'], //order total fee $money
                ])->send();
                $response->redirect();
            } else if ($data['pay_type'] == 2) {//微信支付
                $this->initTheme('shopenquiry');
                $config = ConfigModel::getPayConfig('wechatpay');
                $wechat = Omnipay::gateway('wechat');
                $wechat->setAppId($config['appId']);
                $wechat->setMchId($config['mchId']);
                $wechat->setAppKey($config['appKey']);
                $out_trade_no = $order_num;
                $params = array(
                    'out_trade_no' => $order_num, // billing id in your system
                    'notify_url'   => \CommonClass::getDomain() . '/order/pay/wechat/notify?out_trade_no=' . $out_trade_no, // URL for asynchronous notify
                    'body'         => \CommonClass::getConfig('site_name') . '余额充值', // A simple description
                    'total_fee'    => $inquiryPrice['rule'], // Amount with less than 2 decimals places
                    'fee_type'     => 'CNY', // Currency name from ISO4217, Optional, default as CNY
                );
                $response = $wechat->purchase($params)->send();

                $img = QrCode::size('280')->generate($response->getRedirectUrl());
                $view = array(
                    'cash'       => $inquiryPrice['rule'],
                    'img'        => $img,
                    'order_code' => $order_num,
                    'href_url'   => '/user/index/'
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
    //加入购物车
    public function programmeAddCart(Request $request){
        if(!Auth::check()){//未登录跳转登录页面
            return redirect("/login");
        }
        $data=$request->all();
        //判断购物车中是否已经存在该方案订单了
        $findProgram=ProgrammeOrderModel::where("programme_id",$data['programme_id'])
              ->where("uid",Auth::user()->id)->where("status",1)->first();
        if($findProgram){//当订单中已存在该商品时 进行自增
            $res=ProgrammeOrderModel::where("id",$findProgram['id'])->increment("number");
        }else{
            $res=DB::transaction(function()use($data,$request){
                ///获取该方案信息
                $program=GoodsModel::find($data['programme_id']);
                $data['order_num']=\CommonClass::createNum('fa',4);
                $data['number']=1;
                $data['uid']=Auth::user()->id;
                $data['nickname']=Auth::user()->name;
                $data['price']=$program['cash'];
                $data['freight']=$program['freight'];
                $data['status']=1;
                $data['created_at']=date("Y-m-d H:i:s");
                $orderId=ProgrammeOrderModel::insertGetId($data);
                ProgrammeOrderSubModel::insert([
                    'order_id'=>$orderId,
                    'programme_id'=>$data['programme_id'],
                    'number'=>1,
                    'created_at'=>date("Y-m-d H:i:s"),
                    'cash'=>$program['cash'],
                    'freight'=>$program['freight'],
                    'status'=>1,
                ]);
                return $orderId;
            });
        }
        if($res){
            return ["code"=>1000];
        }
           return ["code"=>1001];
    }
    //方案超市/给服务商留言
    public function leavMessage($programme_id){
      if(!Auth::check()){
          return redirect("/login");
      }
      $this->initTheme('shopenquiry');
      $data=[
          'programme_id'=>$programme_id,
      ];
      return $this->theme->scope('shop.leavMessage', $data)->render();
    }
    //获取手机验证码
    public function leavMessageGetCode(Request $request){
        $code = rand(1000, 9999);
        $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
        $templateId = ConfigModel::phpSmsConfig('sendMobileCode');
        $templates = [
            $scheme => $templateId,
        ];

        $tempData = [
            'code' => $code,
        ];

        $status = \SmsClass::sendSms($request->get("phone"), $templates, $tempData);

        if ($status['success'] == true) {
            $data = [
                'code' => $code,
                'mobile' => $request->get("phone")
            ];
            Session::put('leav_message_phone', $data);
            return ['code' => 1000, 'msg' => '短信发送成功'];
        }
    }
    //留言信息提交
    public function leavInquiryPost(Request $request){
        if(!Auth::check()){
            return redirect("/login");
        }
        $data=$request->except("_token","code","reason");
        if(Session::get("leav_message_phone")["code"] !=$request->get("code") || Session::get("leav_message_phone")["mobile"] !=$request->get("consultant_mobile") ){
            return back()->with(["message"=>"手机验证码输入错误"]);
        }
        //判断是否已留言
        $find=ProgrammeEnquiryMessageModel::where("uid",Auth::user()->id)->where("programme_id",$data['programme_id'])->where("type",$data['type'])->first();
        if($find){
            return redirect("/facs/".$data['programme_id'])->with(["message"=>"请不要重复留言"]);
        }
        //查询该方案
        $programme=GoodsModel::where("id",$data['programme_id'])->first();
        $data['consultant_id']=$programme['uid'];
        $data['created_at']=date("Y-m-d H:i:s");
        $data['uid']=Auth::user()->id;
        $data['nickname']=Auth::user()->name;
        $data['pay_type']=$data['type']==1?1:2;
        $res=ProgrammeEnquiryMessageModel::insert($data);
        //获取方案拥有者信息
        $goodUserInfo=UserModel::find($programme['uid']);
        if($request->get('type')==2){
            //给用户发送短信
            $user = [
                'uid'    => $goodUserInfo->id,
                'email'  => $goodUserInfo->email,
                'mobile' => $goodUserInfo->mobile
            ];
            $templateArr = [
                'username' => $goodUserInfo->name,
                'employer_name'     =>Auth::user()->name,
                'title'   =>$programme['title']
            ];
            \MessageTemplateClass::sendMessage('employee_goods_message',$user,$templateArr,$templateArr);
            $message="留言成功";
        }else{
            //给雇主方案询价发送短信
            $user = [
                'uid'    => $goodUserInfo->id,
                'email'  => $goodUserInfo->email,
                'mobile' => $goodUserInfo->mobile
            ];
            $templateArr = [
                'username' => $goodUserInfo->name,
                'employer_name'     =>Auth::user()->name,
                'title'   =>$programme['title']
            ];
            \MessageTemplateClass::sendMessage('employee_goods_asked',$user,$templateArr,$templateArr);
            //服务商方案询价
            $user = [
                'uid'    => Auth::user()->id,
                'email'  => Auth::user()->email,
                'mobile' => Auth::user()->mobile
            ];
            $templateArr = [
                'username' => Auth::user()->name,
                'employer_name'     =>$goodUserInfo->name,
                'title'   =>$programme['title']
            ];
            \MessageTemplateClass::sendMessage('employer_goods_ask',$user,$templateArr,$templateArr);
            //添加询价次数
            GoodsModel::where("id",$data['programme_id'])->increment("inquiry_num");
            $message="询价成功";
        }
        if($res){
           return redirect("/facs/".$data['programme_id'])->with(["message"=>$message]);
        }
         return back()->with(["message"=>"留言失败"]);
    }

//    //方案超市/购物车
//    public function shopcart(){
//      $this->initTheme('shopenquiry');
//      $data=[];
//      return $this->theme->scope('shop.shopcart', $data)->render();
//    }
    //方案超市/付款
    public function payment($id){
      $this->initTheme('shopenquiry');
      if(!Auth::User()){
          return redirect('/login');
      }
      $goodAllID=json_decode($id);
      $userBalance=UserDetailModel::where('uid',Auth::User()->id)->pluck("balance");
      $totalPrice=0;
      $programmeOrder=ProgrammeOrderModel::whereIn("id",$goodAllID)->get();
      foreach ($programmeOrder as $key=>$val){
          $totalPrice+=$val['number']*$val['price']+$val['freight'];
      }
        //查询用户绑定的银行卡信息
        $bank = BankAuthModel::where('uid', '=', Auth::User()->id)->where('status', '=', 4)->get();
        //判断第三方支付是否开启
        $payConfig = ConfigModel::getConfigByType('thirdpay');
        $balance_pay = false;
        if ($userBalance >= $totalPrice) {
            $balance_pay = true;
        }
        //.根据金额获取对应的优惠券
      $userCoupon=UserCouponModel::getCoupon($totalPrice,[0,5]);
      $data=[
          'userBalance'=>$userBalance,
          'totalPrice'=>$totalPrice,
          'balance_pay'   => $balance_pay,
          'bank'          => $bank,
          'payConfig'     => $payConfig,
          'id'=>$id,
          'userCoupon'=>$userCoupon
      ];
      return $this->theme->scope('shop.payment', $data)->render();
    }
    //方案超市/方案订单提交订单
    public  function programPay(Request $request){
        if(!Auth::check()){
            return redirect("/login");
        }
        $data=$request->except("_token");
        //查询该待支付的订单是否存在
        $programmeOrder=ProgrammeOrderModel::whereIn("id",json_decode($data['order_id']))
                            ->where("uid",Auth::user()->id)->where("status",1)->orderBy("price",'desc')->get();
        if(!$programmeOrder){
            return redirect()->with(["message"=>"该订单不存在"]);
        }

        //计算总金额
        $totalPrice=0;
        foreach($programmeOrder as $key=>$val){
            $totalPrice+=$val['price']*$val['number']+$val['freight'];
        }
        //获取优惠券的金额
        $couponPrice=0;
        //获取优惠券减免金额
        if($request->get('userCoupon')){
            $endPrice=UserCouponModel::getEndPrice($totalPrice,$data['userCoupon']);
            $totalPrice=$endPrice['endPrice'];
            $couponPrice=$endPrice['coupon'];
        }
        //dd($totalPrice,$couponPrice);
        //查询用户信息
        $userDetail=UserDetailModel::where("uid",Auth::user()->id)->first();
        //判断支付密码是否正确
        if(Auth::user()->alternate_password !=UserModel::encryptPassword($data['zfpwd'],Auth::user()->salt)){
            return back()->with(["message"=>"该支付密码错误"]);
        }
        //判断支付金额是否满足订单
        if(floatval($userDetail['balance']) < floatval($totalPrice)){
            return back()->with(["message"=>"用户余额不足"]);
        }
        //进行支付业务逻辑处理
        $res=DB::transaction(function() use($data,$programmeOrder,$userDetail,$totalPrice,$couponPrice){
            //购买用户余额减少
            UserDetailModel::where("uid",Auth::user()->id)->decrement("balance",$totalPrice);
            foreach ($programmeOrder as $key=>$val){
                 if($key ==0){
                     $coupon=$couponPrice;
                 }else{
                     $coupon=0;
                 }
                 ProgrammeOrderModel::programmeSuccessHandle($data,$val,1,$coupon);
            }
            if(isset($data['userCoupon'])){//处理优惠券
                UserCouponLogModel::userCouponHandle(\CommonClass::createNum('fa',4),Auth::user()->id,2,$data['userCoupon']);
            }
            return $data;
        });
         if($res){
             return redirect("/user/payProgramme")->with(['message'=>"支付成功"]);
         }
          return back()->with(["message"=>"支付失败"]);
    }
    /*
     * 方案购买第三方支付
     *
     * */
    public function programmeThirdPay(Request $request){
        if(!Auth::check()){
            return redirect("/login");
        }
        $data = $request->except('_token');
        //订单编号
        $order_num=\CommonClass::createNum('fa',4);
        //查询用户发布的数据
        $programme = ProgrammeOrderModel::whereIn("id",json_decode($data['order_id']))->get();
        ProgrammeOrderModel::whereIn("id",json_decode($data['order_id']))->update(["order_num"=>$order_num]);
        $money=0;
        foreach ($programme as $key=>$val){
            if ($val['uid'] != Auth::user()->id || $val['status'] != 1) {
                continue;
            }
            $money+=$val['price']*$val['number']+$val['freight'];

        }
        //获取优惠券的金额
        $couponPrice=0;
        //获取优惠券减免金额
        if($request->get('userCoupon')){
            $endPrice=UserCouponModel::getEndPrice($money,$data['userCoupon']);
            $money=$endPrice['endPrice'];
            $couponPrice=$endPrice['coupon'];
            //处理优惠券
            UserCouponLogModel::userCouponHandle($order_num,Auth::user()->id,1,$request->get("userCoupon"));
        }
        //计算订单费用
        //$money=$programme['price']+$programme['freight'];
        //判断用户所要支付的是否是自己的任务和任务是否已经支付
        if ($money == 0) {
            return redirect()->to('/user/index')->with('error', '该订单已支付或者不存在');
        }
        //查询用户的余额
        $balance = UserDetailModel::where(['uid' => Auth::user()->id])->first();
        $balance = (float)$balance['balance'];
        //如果余额足够就直接余额付款
        if ($balance >= $money && $data['pay_canel'] == 0) {
            //验证用户的密码是否正确
//            $password = UserModel::encryptPassword($data['password'], $this->user['salt']);
//            if ($password != $this->user['alternate_password']) {
//                return redirect()->back()->with(['error' => '您的支付密码不正确']);
//            }
//            //余额支付产生订单
//            $result = TaskModel::payServiceTask($money, $data['id'], $this->user['id'], $is_ordered->code);
//            if (!$result) return redirect()->back()->with(['error' => '订单支付失败！']);
//            $url = '/task/tasksuccess/'.$data['id'];
//            return redirect()->to($url);
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
                    'out_trade_no' => $order_num, //your site trade no, unique
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
                $out_trade_no = $order_num;
                $params = array(
                    'out_trade_no' => $order_num, // billing id in your system
                    'notify_url'   => \CommonClass::getDomain() . '/order/pay/wechat/notify?out_trade_no=' . $out_trade_no, // URL for asynchronous notify
                    'body'         => \CommonClass::getConfig('site_name') . '余额充值', // A simple description
                    'total_fee'    => $money, // Amount with less than 2 decimals places
                    'fee_type'     => 'CNY', // Currency name from ISO4217, Optional, default as CNY
                );
                $response = $wechat->purchase($params)->send();

                $img = QrCode::size('280')->generate($response->getRedirectUrl());
                $view = array(
                    'cash'       => $money,
                    'img'        => $img,
                    'order_code' => $order_num,
                    'href_url'   => '/user/index/'
                );
		$this->initTheme('shopenquiry');
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
    //方案超市/确认订单->单个订单提交
    public function affirmorder($id,$num)
    {
        if(!Auth::check()){
          return redirect("/login");
        }
        $this->initTheme('shopenquiry');
        $goods = GoodsModel::select("goods.*","attachment.url as at_url")->leftJoin('attachment','goods.cover','=','attachment.id')->where('goods.id',$id)->first();
        if($num>1){
            $goods['totalPrice'] = floatval($goods['cash']*$num);
        }else{
            $goods['totalPrice'] = $goods['cash'];
        }
        //获取该用户的地址
        $addDefault = 0;
        $userAddr = UserAdderModel::where("uid",Auth::user()->id)->orderBy('status','desc')->orderBy('id','desc')->get();
        foreach ($userAddr as $key => $val){
            if($val->status == 2){
                $addDefault = $val->id;
            }
            $userAddr[$key]['provinceName']=DistrictModel::where('id',$val['province'])->pluck("name");
            $userAddr[$key]['cityName']=DistrictModel::where('id',$val['city'])->pluck("name");
            $userAddr[$key]['cityArea']=DistrictModel::where('id',$val['area'])->pluck("name");
        }

        //获取省份
        $province = DistrictModel::where("upid",0)->get()->toArray();
        //获取城市
        $city = DistrictModel::where("upid",$province[0]['id'])->get()->toArray();
        //获取地区
        $area = DistrictModel::where("upid",$city[0]['id'])->get()->toArray();
        $data = [
            'goods'=>$goods,
            'userAddr'=>$userAddr,
            'province'=>$province,
            'city'=>$city,
            'area'=>$area,
            'addDefault' => $addDefault,
            'num' =>$num,
        ];
      return $this->theme->scope('shop.affirmorder', $data)->render();
    }
   
    /*
     * 方案订单提交
     * */
    public function programSub(Request $request){
        if(!Auth::user()->id){
            return redirect("/login");
        }
        $data=$request->except("_token","userAddr_id","inlineRadioOptions","com_type");
        $res = DB::transaction(function()use($data,$request){
            //获取商品价格
            $goods=GoodsModel::where("id",$data['programme_id'])->first();
            $data['price']=$goods['cash'];
            $data['order_num']=\CommonClass::createNum("fa",4);
            $userAdder=UserAdderModel::find($request->get("userAddr_id"));
            $data['number']=isset($data['number']) ? $data['number'] : 1 ;
            $data['uid']=Auth::user()->id;
            $data['nickname']=Auth::user()->name;
            $data['consignee']=$userAdder['uname'];
            $data['mobile']=$userAdder['umobile'];
            $data['email']=$userAdder['uemail'];
            $data['province']=$userAdder['province'];
            $data['city']=$userAdder['city'];
            $data['area']=$userAdder['area'];
            $data['addr']=$userAdder['address'];
            $data['created_at']=date("Y-m-d H:i:s");
			$data['status']=1;
            $data['freight']=$goods['freight'];
            if($request->get('is_invoice') ==1){
                $data['invoice_type']=1;
                $data['com_name']=$request->get("com_name");
                $data['invoices_raised']=2;
                if($request->get("com_type") && $request->get("com_type") == 1){
                    $data['com_name']='';
                    $data['invoices_raised']=1;
                }
            }else{
                $data['invoice_type']=2;
            }
            $orderId=ProgrammeOrderModel::insertGetId($data);
            ProgrammeOrderSubModel::insert([
                'order_id'=>$orderId,
                'programme_id'=>$data['programme_id'],
                'number'=>1,
                'created_at'=>date("Y-m-d H:i:s"),
                'cash'=>$goods['cash'],
                'freight'=>$goods['freight'],
                'status'=>1,
            ]);
            return $orderId;
        });


        if($res){
			 //return redirect("/user/payProgramme")->with(['message'=>"支付成功"]);
            return redirect("/shop/payment/".json_encode([$res]));
        }
            return back()->with(['message'=>"提交失败"]);

    }


}
