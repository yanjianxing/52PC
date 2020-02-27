<?php

namespace App\Modules\Employ\Http\Controllers;

use App\Http\Controllers\IndexController as BasicIndexController;
use App\Http\Requests;
use App\Modules\Advertisement\Model\AdTargetModel;
use App\Modules\Advertisement\Model\RecommendModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\SeoModel;
use App\Modules\Manage\Model\UserCouponModel;
use App\Modules\Manage\Model\VipConfigModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\Manage\Model\ZdfastbuyModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\UserCouponLogModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use App\Modules\Manage\Model\ArticleCategoryModel;
use App\Modules\Manage\Model\ArticleModel;
use App\Modules\Manage\Model\ArticlePayModel;
use App\Modules\Manage\Model\CateModel;
use App\Modules\Employ\Models\AnswerModel;
use App\Modules\Employ\Models\SharesModel;
use Illuminate\Session\SessionInterface;
use App\Modules\Manage\Model\SpecialModel;
use App\Modules\Manage\Model\SpecialNewsModel;
use App\Modules\Manage\Model\SeoRelatedModel;
use App\Modules\Manage\Model\PromoteFreegrantModel;
use App\Modules\Manage\Model\PromoteFreegrantUserlistModel;
use Omnipay;
use Theme;
use QrCode;

class IndexController extends BasicIndexController
{
    public function __construct()
    {
        parent::__construct();
        $this->user = Auth::user();
        $this->initTheme('employ');
    }

    /*
      方案讯
    */
    public function casenews(request $request)
    {
        $this->initTheme('shop');
        /*屏蔽异常访问*/
        $hmsr = $request->get('hmsr');
        $hmpl = $request->get('hmpl');
        if(isset($hmsr) || isset($hmpl) ){
            exit('请稍后访问！');
        }
        $title = ArticleCategoryModel::where('id',1)->first()->cate_name;
        $this->theme->setTitle($title);
        $merge = $request->all();
        //方案讯分类
        $res = ArticleCategoryModel::where('pid','=','1')->orderBy('display_order','asc')->get()->toArray();
        $res=\CommonClass::setArrayKey($res,"id");

        $m = ArticleCategoryModel::get()->toArray();
        //获取所有子分类
        $rCategory = ArticleCategoryModel::_children($m, 1);
        $catIds_Category = array_merge($rCategory, array(1));
        foreach ($catIds_Category as $key =>$value) {   //列表剔除知识百科
            if($value == '73'){
                unset($catIds_Category[$key]);
            }
        }
        //文章列表
        $articleList = ArticleModel::where("article.status","=","1")->where("online_time",'<',date('Y-m-d H:i:s',time()));
        $merge['catID'] = $request->get('catID') ? $request->get('catID') :0;
        $merge['industry'] = $request->get('industry') ? $request->get('industry') :0;
        if($merge['catID'] && $merge['catID'] !=0){
            $articleList = $articleList->where('article.cat_id', $merge['catID']);
        }else {
            $articleList = $articleList->whereIn('article.cat_id', $catIds_Category);
        }
        if(isset($merge['keywords']) && !empty($merge['keywords'])){
            $articleList = $articleList->where('article.title','like','%'.$merge['keywords'].'%');
            //有关键字添加搜索记录
            \CommonClass::get_keyword($merge['keywords'],5);
        }
        if($merge['industry'] && $merge['industry'] !=0){
        $articleList = $articleList->where('article.cate_id', $merge['industry']);
        }
        $by = $request->get('order') ? $request->get('order') : 'article.created_at';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 15;
        $articleList = $articleList
            ->leftjoin('article_category as c', 'article.cat_id', '=', 'c.id')
            ->where('article.is_delete','0')
            ->select('article.*', 'c.cate_name as cate_name')
            ->orderBy($by, 'desc')->paginate($paginate);
        $articleList->load(['skill']);
        $listArr = $articleList->toArray()['data'];
        foreach ($listArr as $key => $value) {
            if(!empty($value['attachment_id'])){
                $ids = array($value['attachment_id']);
                $attachment_pic = attachmentModel::findByIds($ids);
                $listArr[$key]['attachment_pic'] = $attachment_pic[0]['url'];
            }
        }
        $recommendedarticle = RecommendModel::getRecommendByCode('ARTICLE_RIGHT_NEWS','article',[]);
        //推荐专题
        $specialRecommendList = RecommendModel::getRecommendByCode('ARTICLE_RIGHT_SPECIAL','special',[]);
        //热门文章
        $hotarticle = [];
        $ad = AdTargetModel::getAdByCodePage('ARTICLE');
        $adH = [];
        if(isset($ad['ARTICLE_H'])){
            $adH = \CommonClass::arrOper($ad['ARTICLE_H'],3);
        }
        $order = [
            'view_times' => '浏览量',
            'created_at' => '发布时间',
        ];
        //推荐1
        $recommended1=ArticleModel::select('id','pic','title')->where('status',1)->where('recommended1',1)->get();
        //推荐2(每日导读)
        $recommended2=ArticleModel::select('id','title')->where('status',1)->where('recommended2',1)->get();
        //.右边推荐元器件获取
        $zdfastbuglist2=ZdfastbuyModel::where('id','>','0')->where('show_location',2)->where('is_del','0')->select('id','url','aurl')->get();
        //.获取行业分类
        $industryType = CateModel::where("type",1)->select('id','name','sort')->orderBy('sort','desc')->get()->toArray();
        //.获取热门seo 标签
        $seoLabel=SeoModel::where('id','>',0)->select('id','name','view_num')->orderBy("view_num","desc")->limit(8)->get();
        $data= [
            'article'            => $articleList,
            'merge'              => $merge,
            'article_category'   => $res,
            'listArr'            => $listArr,
            'recommendedarticle' => $recommendedarticle,
            'hotarticle'         => $hotarticle,
            'ad'                 => $ad,
            'adH'                => $adH,
            'specialRecommendList' => $specialRecommendList,
            'order'              => $order,
            'recommended1'       => $recommended1,
            'recommended2'       => $recommended2,
            'page'               => $request->get("page")?$request->get("page"):0,
            'zdfastbuglist2'     => $zdfastbuglist2,
            'industryType'       => $industryType,
            'seoLabel'           =>$seoLabel,
        ];
        $this->theme->set('nav_url', '/news');
        $seoConfig = ConfigModel::getConfigByType('seo');
        if(!empty($seoConfig['seo_article']) && is_array($seoConfig['seo_article'])){
            $this->theme->setTitle($seoConfig['seo_article']['title']);
            $this->theme->set('keywords',$seoConfig['seo_article']['keywords']);
            $this->theme->set('description',$seoConfig['seo_article']['description']);
        }else{
            $this->theme->setTitle('方案讯');
        }
        return $this->theme->scope('employ.casenews', $data)->render();
    }

    /**
     * 方案讯详情
     * @param Request $request
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function newsdetailpage(Request $request,$id)
    {
        $this->initTheme('shop');
        ArticleModel::where("id",$id)->increment("view_times");
        $article = ArticleModel::find($id);
        if(empty($article)){
            return redirect()->to('404');
            // return redirect()->back()->with(['error'=>'资讯已删除']);
        }
        $cateName = '其他';
        if(!empty($article['cate_id'])){
            $cateName = CateModel::where("id",$article['cate_id'])->first();
            if($cateName){
                $cateName = $cateName->name;
            }else{
                $cateName = '其他';
            }
        }
//      if(!$article  && !$request->get('admin')){
//            return back()->with(['message'=>"参数错误"]);
//      }
        if($article['status'] != '1'){
            return back()->with(['message'=>"后台待审核中"]);
        }

        //相关资讯 根据分类应用领域来找相关
        $aboutarticle = ArticleModel::select("article.*","cate.name")
            ->leftjoin("cate","article.cate_id","=","cate.id")
            ->where("status","1")
            ->where("article.cate_id",$article['cate_id'])
            ->where('article.id',"<>",$id)
            ->orderBy('article.id','desc')->limit(6)->get();
        //相关方案 根据分类应用领域来找相关
        $goodsRecommendList = GoodsModel::select("goods.id",'goods.title','goods.cover','goods.uid','goods.cate_id')
            ->with('cover','field','user')
            ->where("goods.status","1")
            ->where('is_delete',0)
            ->where("goods.cate_id",$article['cate_id'])
            ->orderBy('goods.id','desc')->limit(6)->get();
        //评论
        $islogin = '';    //是否登陆
        if(!empty(Auth::user()->id)){
            $islogin = Auth::user()->id;
        }
        $commentd = AnswerModel::select("answer.*","user_detail.avatar","user_detail.nickname as name")
            ->leftjoin("user_detail","user_detail.uid","=","answer.uid")
            ->leftjoin("users","users.id","=","answer.uid")
            ->where("article_id",$id)
            ->orderBy("answer.time",'desc')
            ->get();
        $ad = AdTargetModel::getAdByCodePage('ARTICLEDETAIL');
        //.右边推荐元器件获取
        $zdfastbuglist2=ZdfastbuyModel::where('id','>','0')->where('show_location',2)->where('is_del','0')->select('id','url','aurl')->get();
        $data = [
            'article'       => $article,
            'cateName'      => $cateName,
            'aboutarticle'  => $aboutarticle,
            'goodsRecommendList'=> $goodsRecommendList,
            'islogin'       => $islogin,
            'commentd'      => $commentd,
            'ad'            => $ad,
            'zdfastbuglist2'      => $zdfastbuglist2,
        ];
        /*seo配置信息*/
        $SeoRelated = SeoRelatedModel::where("related_id",$id)->where("type","3")->leftjoin("seo","seo.id","=","seo_related.seo_id")->lists('seo.name');
        $seorelatedname = '';
        if($SeoRelated){
           foreach ($SeoRelated as $key => $value) {
                $seorelatedname .= $value.'、';
            } 
        }
        $seoConfig = ConfigModel::getConfigByType('seo');
        if(!empty($seoConfig['seo_articledetail']) && is_array($seoConfig['seo_articledetail'])){
            $this->theme->setTitle($article['title']." - ".$seoConfig['seo_articledetail']['title']);
            $this->theme->set('keywords',$seorelatedname.$cateName.$seoConfig['seo_articledetail']['keywords']);
        }else{
            $this->theme->setTitle($article['title']);
            $this->theme->set('keywords',$article['title'].'、'.$cateName);
        }
        $this->theme->set('description',mb_substr(strip_tags($article['content']),0,200,'utf-8'));
        return $this->theme->scope('employ.newsdetailpage', $data)->render();
    }

    /*方案讯评价*/
    public function comment(request $request)
    {
        if(!Auth::check()){
            return redirect("/login");
        }
        $data= [
                'uid'=>Auth::user()->id,
                'article_id'=>$request->get('article_id'),
                'content'=>$request->get('content'),
                'time'=>date("Y-m-d H:i:s",time())
          ];
        $res = AnswerModel::CommentCreate($data);
        if($res){
            $article = ArticleModel::find($request->get('article_id'));
            $userInfo = UserModel::find($article['user_id']);
            if($userInfo){
               $user = [
                    'uid'    => $userInfo->id,
                    'email'  => $userInfo->email,
                    'mobile' => $userInfo->mobile
                ];
                $templateArr = [
                    'username'      => $userInfo->name,
                    'title'         => $article->title,

                ];
                $res = \MessageTemplateClass::sendMessage('article_comment',$user,$templateArr,$templateArr); 
                dd($res);
            }
            return redirect()->back()->with(array('message' => '评论成功！'));
        }
        return redirect()->back()->with('error','评论失败！');
    }

    //添加资讯
    public function addnewspage(){
        if(!Auth::check()){
            return redirect("/login");
        }
        $this->initTheme('fastpackage');
        $this->theme->setTitle("添加资讯");
        //资讯分类
        $res = ArticleCategoryModel::where('pid','=','1')->whereNotIn('id',['61','97'])->orderBy('display_order','asc')->get()->toArray();
        //查询应用领域分类
        $application = CateModel::where('pid','=','0')->where('type','=','1')->get()->toArray();
        //查询会员折扣
        $zhekou = '10';
        $auth = '';
        if(Auth::user()->id){
            $users = Auth::user()->id;
            $date = date("Y-m-d H:i:s",time());
            $zhekouarr = VipUserOrderModel::where("uid",$users)->where("status",1)->where("end_time",">",$date)->first();   //用户会员等级
            if($zhekouarr){
                $zhekou = VipConfigModel::join("vip","vip.vipconfigid","=","vip_config.id")->where("vip.id",$zhekouarr['vipid'])->first()->appreciation_zixun;
            }
            $auth = UserDetailModel::where('uid',$users)->first()->realname;
        }
        //询价
        $newsprice_prime=ConfigModel::where("alias","news_price")->first();
        $newsprice = sprintf('%.2f',floatval($newsprice_prime['rule'] * ($zhekou/10)));
        $data=[
                'category'=>$res,
                'application'=>$application,
                'newsprice'=>$newsprice,
                'zhekou'   => $zhekou,
                'newsprice_prime' => $newsprice_prime['rule'],
                'auth'  => $auth

            ];
      return $this->theme->scope('employ.addnewspage', $data)->render();
    }

    //支付保存资讯
    public function postsavenews(request $request){
        if(!Auth::check()){
            return redirect("/login");
        }
        $data = $request->except('_token');
        $is_free=$data['is_free']?$data['is_free']:1;
        if ($is_free==1){
            $arr = [
                'cat_id'    => isset($data['cat_id']) ? $data['cat_id'] : '',
                'title'     =>isset($data['title']) ? $data['title'] : '',
                'user_id'   => $this->user['id'],
                'author'    =>isset($data['author']) ? $data['author'] : '',
                'cate_id'   =>isset($data['cate_id']) ? $data['cate_id'] : '',
                'content'   =>isset($data['content']) ? $data['content'] : '',
                'summary'   =>isset($data['content']) ?  mb_substr($data['content'],0,150,'utf-8') : '',
                'pic'       =>isset($data['pic']) ? $data['pic'] : '',
                'from'      => '2',
                'price'     => 0,
                'status'    => 0 ,
                'is_free'    => 1,
                'created_at'=> date("Y-m-d H:i:s"),
                'online_time'=> date("Y-m-d H:i:s"),
            ];
            $res = ArticleModel::create($arr);
            if ($res){
                return redirect("/employ/articlesuccess/$res[id]");
            }else{
                return redirect('/employ/addnewspage')->with(array('message'=>'未知错误！'));
            }
        }else{
            //获取用户vip配置
            // $userVipConfigModel=UserVipConfigModel::getConfigByUid(Auth::user()->id);
            //查询会员折扣
            $zhekou = '10';
            if(Auth::user()->id){
                $users = Auth::user()->id;
                $date = date("Y-m-d H:i:s",time());
                $zhekouarr = VipUserOrderModel::where("uid",$users)->where("status",1)->where("end_time",">",$date)->first();   //用户会员等级
                if($zhekouarr){
                    $zhekou = VipConfigModel::join("vip","vip.vipconfigid","=","vip_config.id")->where("vip.id",$zhekouarr['vipid'])->first()->appreciation_zixun;
                }
            }
            //询价
            $newsprice=ConfigModel::where("alias","news_price")->first();
            $arr = [
                'cat_id'    => isset($data['cat_id']) ? $data['cat_id'] : '',
                'title'     =>isset($data['title']) ? $data['title'] : '',
                'user_id'   => $this->user['id'],
                'author'    =>isset($data['author']) ? $data['author'] : '',
                'cate_id'   =>isset($data['cate_id']) ? $data['cate_id'] : '',
                'content'   =>isset($data['content']) ? $data['content'] : '',
                'summary'   =>isset($data['content']) ?  mb_substr($data['content'],0,150,'utf-8') : '',
                'pic'       =>isset($data['pic']) ? $data['pic'] : '',
                'from'      => '2',
                'price'     => round($newsprice['rule'] * $zhekou/10,2),
                'status'    => 4 ,
                'is_free'    => 2,
                'created_at'=> date("Y-m-d H:i:s"),
                'online_time'=> date("Y-m-d H:i:s"),
            ];
            if(isset($data['inlineRadio']) && $data['inlineRadio'] == 2){
                $arr['articlefrom'] = '2';
                $arr['reprint_url'] = isset($data['reprint_url']) ? $data['reprint_url'] : '';
            }
            $res = ArticleModel::create($arr);
            //支付资讯金额
            $newsprice=ConfigModel::where("alias","news_price")->first();
            $money = sprintf('%.2f',floatval($newsprice['rule'] * ($zhekou/10)));

            if($res){
                //创建订单
                $order_num=\CommonClass::createNum('zx',4);
                $orderdata = [
                    'order_num'=>$order_num,
                    'article_id'=>$res['id'],
                    'uid'  =>Auth::user()->id,
                    'price'=>$money,
                    'created_at'=>date("Y-m-d H:i:s"),
                    'status'=>1
                ];
                $ArticlePayres = ArticlePayModel::create($orderdata);
                if($ArticlePayres){
                    return redirect("/employ/trusteemoney/$res[id]");
                }
            }
            return redirect('/employ/addnewspage')->with(array('message'=>'未知错误！'));
        }
    }
    /*支付资讯视图*/
    public function trusteemoney(request $request,$articleid){
        if(!Auth::check()){
            return redirect("/login");
        }
        $this->initTheme('fastpackage');
        $this->theme->setTitle("支付费用");
        $articleid = intval($articleid);
        //查询会员折扣
        $zhekou = '10';
        $userInfo = [];
        if(Auth::user()->id){
            $users = Auth::user()->id;
            $date = date("Y-m-d H:i:s",time());
            $zhekouarr = VipUserOrderModel::where("uid",$users)->where("status",1)->where("end_time",">",$date)->first();   //用户会员等级
            if($zhekouarr){
                $zhekou = VipConfigModel::join("vip","vip.vipconfigid","=","vip_config.id")->where("vip.id",$zhekouarr['vipid'])->first()->appreciation_zixun;
            }
            //查询账户余额
            $userInfo = UserDetailModel::where('uid', $users)->where('balance_status', 0)->select('balance')->first();
        }
        //询价
        $newsprice=ConfigModel::where("alias","news_price")->first();
        $newsprice = sprintf('%.2f',floatval($newsprice['rule'] * ($zhekou/10)));

        //判断第三方支付是否开启
        $payConfig = ConfigModel::getConfigByType('thirdpay');

        //判断用户的余额是否充足
        $balance_pay = false;
        if ($userInfo['balance'] >= $newsprice) {
            $balance_pay = true;
        }
        //根据金额获取对应的优惠券
        $userCoupon=UserCouponModel::getCoupon($newsprice,[0,6]);
        $data=[
                'newsprice'=>$newsprice,
                'userInfo'=>$userInfo,
                'articleid'=>$articleid,
                'payConfig'     => $payConfig,
                'balance_pay'   => $balance_pay,
                'userCoupon'  =>$userCoupon
            ];
        return $this->theme->scope('employ.trusteemoney', $data)->render();
    }

    /*支付资讯*/
    public function paytrusteemoney(Request $request){
        $data = $request->except('_token');
        //查询用户的余额
        $balance = UserDetailModel::where(['uid' => $this->user['id']])->first();
        $balance = (float)$balance['balance'];
        
        
        //判断用户所要支付的是否是自己的发布资讯和资讯审核未通过
        $news = ArticleModel::find($data['id']);
        // if ($news['user_id'] != $this->user['id'] || $news['status'] != '1') {
        //     return redirect()->to('/employ/addnewspage')->with('error', '非法操作！');
        // }
        $date = date("Y-m-d H:i:s",time());
        $timestrtotime = time();
        $zhekou='10';
        $zhekouarr = VipUserOrderModel::where("uid",$this->user['id'])->where("status",1)->where("end_time",">",$date)->first();   //用户会员等级
        if($zhekouarr){
            $zhekou = VipConfigModel::join("vip","vip.vipconfigid","=","vip_config.id")->where("vip.id",$zhekouarr['vipid'])->first()->appreciation_zixun;
        }
        //支付资讯金额
        $newsprice=ConfigModel::where("alias","news_price")->first();
        $money = sprintf('%.2f',floatval($newsprice['rule'] * ($zhekou/10)));
        //查看是否使用优惠券
        $couponPrice=0;

        //判断是否已经支付
        $ArticlePayres = ArticlePayModel::where('article_id','=',$data['id'])->where('uid','=',$this->user['id'])->first();
        if(!$ArticlePayres){
            return redirect()->to('/employ/addnewspage')->with('error', '您的订单不见了！请重新提交');
        }
        if ($ArticlePayres['status'] == 2) {
            return redirect()->to('/employ/addnewspage')->with('error', '您已经支付！请勿重复支付！');
        }
        if($request->get("userCoupon") >0){
            //获取优惠券减免金额
            $resPrice=UserCouponModel::getEndPrice($money,$data['userCoupon']);
            $money=round($resPrice['endPrice'],2);
            $couponPrice=round($resPrice['coupon'],2);
            if($resPrice['endPrice'] == 0){
                $data['pay_canel']=0;
            }
            //处理优惠券
            UserCouponLogModel::userCouponHandle($ArticlePayres['order_num'],Auth::user()->id,1,$request->get("userCoupon"));
        }
        
        //如果余额足够就直接余额付款
        if ($balance >= $money && $data['pay_canel'] == 0) {
            //验证用户的密码是否正确
            $password = UserModel::encryptPassword($data['password'], $this->user['salt']);
            if ($password != $this->user['alternate_password']) {
                return redirect()->back()->with(['error' => '您的支付密码不正确']);
            }
            //余额支付产生订单
            $result = ArticleModel::payarticle($money, $data['id'], $this->user['id'],$ArticlePayres['order_num'],$couponPrice);
            if (!$result) return redirect()->back()->with(['error' => '支付失败']);
            if($request->get("userCoupon") >0){
                //处理优惠券
                UserCouponLogModel::userCouponHandle($ArticlePayres['order_num'],Auth::user()->id,2,$request->get("userCoupon"));
            }
            return redirect()->to('/employ/articlesuccess/'.$data['id']);
        } else if (isset($data['pay_type']) && $data['pay_canel'] == 1) {
            //跳转支付赏金托管流程,返回成功之后就直接执行上面的托管
            if ($data['pay_type'] == 1) {//支付宝支付
                $config = ConfigModel::getPayConfig('alipay');
                $objOminipay = Omnipay::gateway('alipay');
                $objOminipay->setPartner($config['partner']);
                $objOminipay->setKey($config['key']);
                $objOminipay->setSellerEmail($config['sellerEmail']);
                $siteUrl = \CommonClass::getConfig('site_url');
                $objOminipay->setReturnUrl(\CommonClass::getDomain() . '/order/pay/alipay/return');
                $objOminipay->setNotifyUrl(\CommonClass::getDomain() . '/order/pay/alipay/notify');
                $response = Omnipay::purchase([
                    'out_trade_no' => $ArticlePayres['order_num'], //your site trade no, unique
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
                $out_trade_no = $ArticlePayres['order_num'];
                $params = array(
                    'out_trade_no' => $ArticlePayres['order_num'], // billing id in your system
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
                    'order_code' => $ArticlePayres['order_num'],
                    'href_url'   => '/user/consult'
                );
                return $this->theme->scope('employ.wechatpayarticle', $view)->render();
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
     * 成功支付资讯
     */
    public function articlesuccess($id)
    {	
		$this->initTheme('fastpackage');
		$this->theme->setTitle('文章发布成功');
        $id = intval($id);
//        //验证任务是否是状态2
//        $task = ArticleModel::where('id',$id)->first();
//
//        if($task['status']!='0'){
//            return redirect()->back()->with(['error'=>'数据错误，当前任务不处于等待审核状态！']);
//        }
        $view = [
            'id' => $id,
        ];

        return $this->theme->scope('employ.articlesuccess',$view)->render();
    }


    /**
     * 专题列表
     * @param Request $request
     * @return mixed
     */
    public function specialList(request $request){
        $this->initTheme('shop');
        $this->theme->setTitle('专题列表');
        $merge = $request->all();
        //专题列表
        $specialList = SpecialModel::where("special.status","=","1")->where("is_open",'1');
        
        $by = $request->get('by') ? $request->get('by') : 'special.id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 8;
        $specialList = $specialList->select('special.*')->orderBy($by, $order)->paginate($paginate);
        $listArr = $specialList->toArray()['data'];
        //推荐文章
        $recommendedarticle = RecommendModel::getRecommendByCode('SPACIAL_ARTICLE','article',[]);
        //推荐专题
        /*$specialRecommendList = RecommendModel::getRecommendByCode('ARTICLE_RIGHT_SPECIAL','special',[]);*/
        //推荐方案
        $recommendedGoods = RecommendModel::getRecommendByCode('SPACIAL_GOODS','goods',['shop_info' => true,'goods_field' => true]);
       /* $ad = AdTargetModel::getAdByTypeId(5);
        AdTargetModel::addViewCountByCode('ARTICLE');*/
        $ad = AdTargetModel::getAdByCodePage('ARTICLE');
        $adH = [];
        if(isset($ad['ARTICLE_H'])){
            $adH = \CommonClass::arrOper($ad['ARTICLE_H'],3);
        }
        $src="";
        //推荐1
        $recommended1=ArticleModel::select('id','pic','title')->where('status',1)->where('recommended1',1)->get();
        $data= [
            'special'            => $specialList,
            'recommended1'       => $recommended1,
            'merge'              => $merge,
            'listArr'            => $listArr,
            'ad'                 => $ad,
            'adH'                => $adH,
            'recommendedarticle' => $recommendedarticle,
            'goodsRecommendList' => $recommendedGoods,
            'src' => $src,

            //'specialRecommendList'  => $specialRecommendList,
        ];
        return $this->theme->scope('employ.special', $data)->render();
    }

    public function specialDetail(request $request,$id){
        $this->initTheme('shop');
        $this->theme->setTitle('专题详情');
        SpecialModel::where("id",$id)->increment("view_times");
        //专题详情
        $specialres = SpecialModel::where("id",$id)->where("is_open",'1')->first();
        if($specialres['status'] != '1' || empty($id)){
            return redirect()->to('404');
            // return redirect('/news/special')->with(['error' => '查看专题错误！']);
        }
        /*根据专题文章id获取文章详细信息*/
        $newsres = [];
        if($specialres['news_id']){
            $newsid = explode(',',$specialres['news_id']);
            $newsid = array_reverse($newsid);
            foreach ($newsid as$value) {
                $newsres[] = SpecialNewsModel::where("id",$value)->first();
            }
        }
        $data = [
            'specialres' => $specialres,
            'newsres'   => $newsres
        ];
        return $this->theme->scope('employ.specialDetail',$data)->render();
    }

    //分享自动发放
    public function shares(request $request){
        $uid = Auth::check() ? Auth::user()->id : $request->get('shareuid');
        $share_id = $request->get('share_id') ? $request->get('share_id') : '';
        $type = $request->get('type') ? $request->get('type') : '1';
        $action = $request->get('action') ? $request->get('action') : '15';
        $arr = [
            'uid'=>$uid,
            'share_id' => $share_id,
            'type' => $type,
            'created_at' => date("Y-m-d H:i:s"),
        ];
        if($uid){
            $res = SharesModel::create($arr);
            //查找这周分享了多少次
            $time = date("Y-m-d H:i:s");
            $lastday=date("Y-m-d 23:59:59",strtotime("$time Sunday"));
            $firstday = date("Y-m-d 00:00:00",strtotime("$lastday - 6 days"));
            $shareTimes = SharesModel::where('uid',$uid)->where('created_at',">",$firstday)->where('created_at',"<",$lastday)->count();
            //是否有设置自动发放，如果有，查询这周是否有自动发送
            $result = PromoteFreegrantModel::where("action",$action)->where("is_open",1)->first();
            $user_freegrant = '0';
            if($result){
                $user_freegrant = PromoteFreegrantUserlistModel::where('uid',$uid)->where('action',$action)->where('prize',$result['prize'])->where('created_at',">",$firstday)->where('created_at',"<",$lastday)->count();
            }
            if($shareTimes>=3 && !empty($result) && empty($user_freegrant) ){
                UserModel::sendfreegrant($uid,15);
            }
        }
    }
}
