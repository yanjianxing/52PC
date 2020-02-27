<?php

namespace App\Modules\Shop\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\IndexController as BasicIndexController;
use App\Modules\Advertisement\Model\AdTargetModel;
use App\Modules\Advertisement\Model\RecommendModel;
use App\Modules\Manage\Model\ArticleModel;
use App\Modules\Manage\Model\CateModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\SeoModel;
use App\Modules\Manage\Model\ZdfastbuyModel;
use App\Modules\Shop\Models\GoodsCommentModel;
use App\Modules\Shop\Models\ShopDecorateConfigModel;
use App\Modules\Shop\Models\ShopTagsModel;
use App\Modules\Shop\Models\ShopViewLogModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Http\Request;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Shop\Models\ShopFocusModel;
use Auth;
use DB;
use Illuminate\Support\Facades\Session;
use Omnipay;
use Teepluss\Theme\Facades\Theme;
use QrCode;

class ShopController extends BasicIndexController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('servermain');
    }

    /**
     * 找服务商
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $this->initTheme('shop');
        $merge = $request->all();
        /*屏蔽异常访问*/
        if(isset($merge['hmsr']) || isset($merge['hmpl'])){
            exit('请稍后访问！');
        }
        /*$shopRecommendList = RecommendModel::getRecommendByCode('SERVICE_LIST','shop',['goods' => false,'auth' => false]);*/
        //推荐方案
        $goodsRecommendList = RecommendModel::getRecommendByCode('SERVICE_LIST','goods',['shop_info' => true,'goods_field' => true]);
        //推荐服务商
        $shopRecommendWindow = RecommendModel::getRecommendByCode('SERVICE_WINDOW','shop',['goods' => false]);
        //.轮播图适用---新增认证
        if(isset($shopRecommendWindow)){
            foreach ($shopRecommendWindow as $k=>$v){
                $shopid=$v['recommend_id'];//店铺id
                $shopRecommendWindow[$k]['shopCount']=GoodsModel::getShopsCount($shopid);//.现成方案
                $shopRecommendWindow[$k]['serviceIndustry'] = ShopTagsModel::shopTag($shopid,1);//.查询服务行业
                $shopRecommendWindow[$k]['technicalClassify'] = ShopTagsModel::shopTag($shopid,2);//.查询技术分类
                if($v['info']){
                    $shopRecommendWindow[$k]['mobile'] = UserModel::where('id', $v['info']['uid'])->pluck('mobile');//手机号认证
                    $shopRecommendWindow[$k]['email'] = UserModel::where('id', $v['info']['uid'])->pluck('email');//邮箱认证
                    $shopRecommendWindow[$k]['email_status'] = UserModel::where('id', $v['info']['uid'])->pluck('email_status');//邮箱状态
                    $shopRecommendWindow[$k]['level'] = UserModel::where('id', $v['info']['uid'])->pluck('level');//会员等级
                }
            }
        }
        $shopRecommendWindow = \CommonClass::arrOper($shopRecommendWindow,3);

        //选中排名
        $shopBidOrder = ShopModel::where('status',1)->orderBy('receive_task_num','desc')->limit(5)->get()->toArray();

        $order = [
            'good_comment'     => '好评数',
            'receive_task_num' => '项目数',
        ];
        $shopList = ShopModel::getShopList(15,$merge);
        //.列表和宫格适用---新增认证
        if(isset($shopList)){
            foreach ($shopList as $k=>$v){
                $shopid=$v->id;//店铺id
                $shopList[$k]['shopCount']=GoodsModel::getShopsCount($shopid);//.现成方案
                $shopList[$k]['serviceIndustry']= ShopTagsModel::shopTag($shopid,1);//.查询服务行业
                $shopList[$k]['technicalClassify'] =ShopTagsModel::shopTag($shopid,2);//.查询技术分类
                $shopList[$k]['mobile'] = UserModel::where('id', $v['uid'])->pluck('mobile');//手机号认证
                $shopList[$k]['email'] = UserModel::where('id', $v['uid'])->pluck('email');//邮箱认证
                $shopList[$k]['email_status'] = UserModel::where('id', $v['uid'])->pluck('email_status');//邮箱状态
                $shopList[$k]['level'] = UserModel::where('id', $v['uid'])->pluck('level');//会员等级
            }
        }
        $list = [];
        if(!empty($shopList->toArray()['data'])){
            $uidArr = array_pluck($shopList->toArray()['data'],'uid');
            $auth = ShopModel::getShopAuth($uidArr);
            $list = $shopList->toArray()['data'];
            foreach($list as $k => $v){
                $list[$k]['auth'] = in_array($v['uid'],array_keys($auth)) ? $auth[$v['uid']] : [];
            }
        }
        $hotShop = ShopModel::where('status',1)->orderBy('view_count','desc')->limit(4)->get()->toArray();

        $fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        $skillArr = TaskCateModel::where('type',2)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        if (isset($merge['district'])) {
            $pid = DistrictModel::find($merge['district']);
            if($pid){
                $area_pid = $pid->upid;
            }else{
                $area_pid = 0;
            }
            if($area_pid == 0){
                $area_data = DistrictModel::findTree(intval($merge['district']));
            }else{
                $area_data = DistrictModel::findTree($area_pid);
            }
        } else {
            $area_data = DistrictModel::findTree(0);
            $area_pid = 0;
        }
        /*$ad = AdTargetModel::getAdByTypeId(4);
        AdTargetModel::addViewCountByCode('SERVICE');*/
        $ad = AdTargetModel::getAdByCodePage('SERVICE');
        //获取热门seo 标签
        $seoLabel=SeoModel::orderBy("view_num","desc")->limit(8)->get();
        $data = [
            //'shopRecommendList'   => $shopRecommendList,
            'goodsRecommendList'  => $goodsRecommendList,
            'shopRecommendWindow' => $shopRecommendWindow,
            'shopBidOrder'        => $shopBidOrder,
            'list'                => $shopList,
            'list_arr'            => $list,
            'merge'               => $merge,
            'hotShop'             => $hotShop,
            'fieldArr'            => $fieldArr,
            'skillArr'            => $skillArr,
            'order'               => $order,
            'area_data'           => $area_data,
            'area_pid'            => $area_pid,
            'ad'                  => $ad,
            'page'                => $request->get("page") ? $request->get("page") : 0,
            'seoLabel'=>$seoLabel,
        ];

        $this->theme->set('nav_url', '/fuwus');

        $seoConfig = ConfigModel::getConfigByType('seo');
        if(!empty($seoConfig['seo_service']) && is_array($seoConfig['seo_service'])){
            $this->theme->setTitle($seoConfig['seo_service']['title']);
            $this->theme->set('keywords',$seoConfig['seo_service']['keywords']);
            $this->theme->set('description',$seoConfig['seo_service']['description']);
        }else{
            $this->theme->setTitle('找服务商');
        }
        return $this->theme->scope('shop.shop.index', $data)->render();
    }

    /**
     * 店铺首页
     * @param $shopId
     * @param Request $request
     * @return mixed
     */
    public function home($shopId,Request $request)
    {
        //店铺信息
        $uid = ShopModel::where('id',$shopId)->pluck('uid');
        $shopInfo = ShopModel::shopOneInfo($uid);
        if(!$shopInfo){
            return redirect()->back()->with(['message' => '参数错误']);
        }
        if($shopInfo['status'] == 2){
            return redirect()->back()->with(['message' => '店铺已关闭']);
        }
        $this->theme->set('shopInfo',$shopInfo);
        $this->theme->set('SHOPSORT',1);
        $this->theme->set('SHOPID',$shopId);

        //任务选中记录
        $taskIdArr = WorkModel::where('uid',$uid)->where('status',1)->lists('task_id')->toArray();
        $shopInfo['PriceCount'] = TaskModel::where('type_id',1)
                    ->where('is_del',0)
                    ->where('status','>=',2)
                    ->where('status','<',10)
                    ->where('task.status','!=',3)
                    ->where('is_open',1)
                    ->whereIn('id',$taskIdArr)
                    ->sum('bounty');

        //店铺浏览
        $shopView = $this->ShopView($uid,$shopId);
        $data = [
            'shopId'   => $shopId,
            'shopInfo' => $shopInfo,
            'shopView' => $shopView
        ];
        //查询服务行业
        $fieldArr = ShopTagsModel::shopTag($shopId,1);
        //查询技术分类
        $skillArr = ShopTagsModel::shopTag($shopId,2);
        //seo配置信息
        $fielname = '';
        if($fieldArr){
            foreach ($fieldArr as $k => $v) {
                if(count($fieldArr)==$k+1){
                    $fielname .= $v['name'];
                }else{
                    $fielname .= $v['name'].',';
                }
                
            }
        }
        //.右边推荐元器件获取
        $shopInfo['zdfastbuglist2']=ZdfastbuyModel::where('id','>','0')->where('show_location',2)->where('is_del','0')->select('id','url','aurl')->get();
        //.获取热门seo 标签
        $shopInfo['seoLabel']=SeoModel::where('id','>',0)->select('id','name','view_num')->orderBy("view_num","desc")->get()->toArray();
        if($shopInfo->shop_template_stauts == 1){//普通店铺
            $this->initTheme('fastpackage');
            ///查询认证
            $auth = ShopModel::getShopAuth([$shopInfo['uid']]);
            //.新增认证
            if(isset($auth[$shopInfo['uid']])){
                $auth['mobilerz'] = UserModel::where('id', $uid)->pluck('mobile');//.手机号认证
                $auth['emailrz'] = UserModel::where('id', $uid)->pluck('email');//.邮箱认证
                $auth['email_statusrz'] = UserModel::where('id', $uid)->pluck('email_status');//.邮箱状态
                $auth['levelrz'] = UserModel::where('id', $uid)->pluck('level');//.会员等级
            }
            //热门方案
            $hotGoods = GoodsModel::where('is_delete',0)->with('cover','field','user')->orderBy('view_num','desc')->limit(4)->get()->toArray();
            //店铺方案
            $merge['shop_id'] = $shopId;
            $goodsList = GoodsModel::getGoodsList(5,$merge)->setPath('/fuwus/ajaxShopGoodsList/'.$shopId);

            //店铺选中记录
            $mergeTask = [
                'is_open'     => 1,
                'task_id_arr' => $taskIdArr
            ];
            $taskList = TaskModel::getTaskList(5,$mergeTask)->setPath('/fuwus/ajaxbidTaskList/'.$uid);
            if(!empty($taskList->toArray()['data'])){
                foreach($taskList as $key => $val){
                    if((time()-strtotime($val['created_at']))> 0 && (time()-strtotime($val['created_at'])) < 3600){
                        $val->show_publish = intval((time()-strtotime($val['created_at']))/60).'分钟前';
                    }
                    if((time()-strtotime($val['created_at']))> 3600 && (time()-strtotime($val['created_at'])) < 24*3600){
                        $val->show_publish = intval((time()-strtotime($val['created_at']))/3600).'小时前';
                    }
                    if((time()-strtotime($val['created_at']))> 24*3600){
                        $val->show_publish = intval((time()-strtotime($val['created_at']))/(24*3600)).'天前';
                    }
                }
            }
            //资讯列表
            $article = ArticleModel::getArticleList(5,['uid'=> $uid,'is_skill'=>1])->setPath('/fuwus/ajaxArticleList/'.$uid);
            $data['auth'] = $auth;
            $data['fieldArr'] = $fieldArr;
            $data['skillArr'] = $skillArr;
            $data['hotGoods'] = $hotGoods;
            $data['goodsList'] = $goodsList;
            $data['taskList'] = $taskList;
            $data['article'] = $article;
            $this->theme->set('shopHomeIndex',1);//设置普通店铺背景图显示
            $seoConfig = ConfigModel::getConfigByType('seo');
            if(!empty($seoConfig['seo_servicedetail']) && is_array($seoConfig['seo_servicedetail'])){
                $this->theme->setTitle($shopInfo['shop_name']." - ".$seoConfig['seo_servicedetail']['title']);
                $this->theme->set('keywords',$seoConfig['seo_servicedetail']['keywords'].$fielname);
            }else{
                $this->theme->setTitle($shopInfo['shop_name']);
                $this->theme->set('keywords',$shopInfo['shop_name']);
            }
            $this->theme->set('description',mb_substr(strip_tags($shopInfo['shop_desc']),0,200,'utf-8'));
            return $this->theme->scope('shop.shop.home', $data)->render();

        }elseif($shopInfo->shop_template_stauts == 2){//店铺装修模板1
            $this->initTheme('serviceshop');
            $template = ShopDecorateConfigModel::where('uid',$uid)->where('status',2)->orderBy('sort','asc')->get()->toArray();
            $template = \CommonClass::setArrayKey($template,'type',2);
            $data['template'] = $template;
            //资讯头条
            $articleId = isset($shopInfo['template']['consult']) && !empty($shopInfo['template']['consult']) ? explode(',',$shopInfo['template']['consult']) : [];
            $article = ArticleModel::whereIn('id',$articleId)->select('id','title','pic','summary')->get()->toArray();
            $data['article'] = $article;
            $seoConfig = ConfigModel::getConfigByType('seo');
            if(!empty($seoConfig['seo_servicedetail']) && is_array($seoConfig['seo_servicedetail'])){
                $this->theme->setTitle($shopInfo['shop_name']." - ".$seoConfig['seo_servicedetail']['title']);
                $this->theme->set('keywords',$seoConfig['seo_servicedetail']['keywords'].$fielname);
            }else{
                $this->theme->setTitle($shopInfo['shop_name']);
                $this->theme->set('keywords',$shopInfo['shop_name']);
            }
            $this->theme->set('description',mb_substr(strip_tags($shopInfo['shop_desc']),0,200,'utf-8'));
            return $this->theme->scope('shop.shop.viphome', $data)->render();

        }elseif($shopInfo->shop_template_stauts == 3){//店铺装修模板1
            $this->initTheme('templatemaintow');
            $template = ShopDecorateConfigModel::where('uid',$uid)->where('status',3)->orderBy('sort','asc')->get()->toArray();
            $template = \CommonClass::setArrayKey($template,'type',2);
            $data['template'] = $template;
            //资讯头条
            $articleId = isset($shopInfo['template']['consult']) && !empty($shopInfo['template']['consult']) ? explode(',',$shopInfo['template']['consult']) : [];
            $article = ArticleModel::whereIn('id',$articleId)->select('id','title','pic','summary')->get()->toArray();
            $data['article'] = $article;
            $seoConfig = ConfigModel::getConfigByType('seo');
            if(!empty($seoConfig['seo_servicedetail']) && is_array($seoConfig['seo_servicedetail'])){
                $this->theme->setTitle($shopInfo['shop_name']." - ".$seoConfig['seo_servicedetail']['title']);
                $this->theme->set('keywords',$seoConfig['seo_servicedetail']['keywords'].$fielname);
            }else{
                $this->theme->setTitle($shopInfo['shop_name']);
                $this->theme->set('keywords',$shopInfo['shop_name']);
            }
            $this->theme->set('description',mb_substr(strip_tags($shopInfo['shop_desc']),0,200,'utf-8'));
            return $this->theme->scope('shop.shop.viphometwo', $data)->render();
        }
    }

    /**
     * 店铺方案
     * @param $shopId
     * @param Request $request
     * @return mixed
     */
    public function goodsList($shopId,Request $request)
    {
        //店铺信息
        $uid = ShopModel::where('id',$shopId)->pluck('uid');
        $ShopInfo = ShopModel::shopOneInfo($uid);
        if(!in_array(1,$ShopInfo['nav_open'])){
            return redirect()->back()->with(['error' => '参数错误']);
        }

        $this->theme->set('shopInfo',$ShopInfo);
        $this->theme->set('SHOPID',$shopId);
        $this->theme->set('SHOPSORT',2);

        $shopView = $this->ShopView($uid,$shopId);

        $merge = $request->all();
        $merge['shop_id'] = $shopId;
        $goodsList = GoodsModel::getGoodsList(9,$merge);

        $hotGoods = GoodsModel::where('is_delete',0)->with('cover','field','user')->orderBy('view_num','desc')->limit(4)->get()->toArray();
        $fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        //.右边推荐元器件获取
        $zdfastbuglist2=ZdfastbuyModel::where('id','>','0')->where('show_location',2)->where('is_del','0')->select('id','url','aurl')->get();
        $data = [
            'shopId'   => $shopId,
            'list'     => $goodsList,
            'merge'    => $merge,
            'hotGoods' => $hotGoods,
            'fieldArr' => $fieldArr,
            'shopView' => $shopView,
            'zdfastbuglist2'=> $zdfastbuglist2,
        ];

        $this->theme->setTitle($ShopInfo['shop_name'].'方案');
        return $this->theme->scope('shop.shop.goodslist', $data)->render();
    }

    /**
     * 店铺 服务商档案
     * @param $shopId
     * @param Request $request
     * @return mixed
     */
    public function info($shopId,Request $request)
    {
        //店铺信息
        $uid = ShopModel::where('id',$shopId)->pluck('uid');
        $shopInfo = ShopModel::shopOneInfo($uid);
        //.新增认证
        if(isset($shopInfo)){
            $shopInfo['email_status'] = UserModel::where('id', $uid)->pluck('email_status');//邮箱状态
            $shopInfo['level'] = UserModel::where('id', $uid)->pluck('level');//会员等级
        }
        if(!in_array(2,$shopInfo['nav_open'])){
            return redirect()->back()->with(['error' => '参数错误']);
        }
        if($shopInfo['status'] == 2){
            return redirect()->back()->with(['message' => '店铺已关闭']);
        }
        $this->theme->set('shopInfo',$shopInfo);
        $this->theme->set('SHOPID',$shopId);
        $this->theme->set('SHOPSORT',3);
        $shopView = $this->ShopView($uid,$shopId);
        //店铺认证
        $auth = ShopModel::getShopAuth([$uid]);

        //服务商信息
        $userInfo = UserDetailModel::where('uid',$uid)->first();

        //应用领域
        $applyId=ShopTagsModel::where("shop_id",$shopId)->where('type',1)->lists('cate_id');
        $applyName=CateModel::whereIn('id',$applyId)->where("type",1)->lists("name")->toArray();
        $userInfo['function']=implode(',',$applyName);
        //技术标签
        $skillId=ShopTagsModel::where("shop_id",$shopId)->where('type',2)->lists('cate_id');
        $skillName=CateModel::whereIn('id',$skillId)->where("type",2)->lists("name")->toArray();
        $userInfo['job_level']=implode(',',$skillName);
        //热门方案
        $hotGoods = GoodsModel::where('is_delete',0)->with('cover','field','user')->orderBy('view_num','desc')->limit(4)->get()->toArray();
        //任务选中记录
        $taskIdArr = WorkModel::where('uid',$uid)->where('status',1)->lists('task_id')->toArray();
        $merge = [
            'is_open'     => 1,
            'task_id_arr' => $taskIdArr
        ];
        $taskList = TaskModel::getTaskList(5,$merge)->setPath('/fuwus/ajaxbidTaskList/'.$uid);
        if(!empty($taskList->toArray()['data'])){
            foreach($taskList as $key => $val){
                if((time()-strtotime($val['created_at']))> 0 && (time()-strtotime($val['created_at'])) < 3600){
                    $val->show_publish = intval((time()-strtotime($val['created_at']))/60).'分钟前';
                }
                if((time()-strtotime($val['created_at']))> 3600 && (time()-strtotime($val['created_at'])) < 24*3600){
                    $val->show_publish = intval((time()-strtotime($val['created_at']))/3600).'小时前';
                }
                if((time()-strtotime($val['created_at']))> 24*3600){
                    $val->show_publish = intval((time()-strtotime($val['created_at']))/(24*3600)).'天前';
                }
            }
        }
        $data = [
            'shopId'   => $shopId,
            'shopInfo' => $shopInfo,
            'userInfo' => $userInfo,
            'auth'     => $auth,
            'hotGoods' => $hotGoods,
            'taskList' => $taskList,
            'shopView' => $shopView
        ];
        $this->theme->setTitle($shopInfo['shop_name'].'服务商档案');
        return $this->theme->scope('shop.shop.info', $data)->render();
    }

    /**
     * 店铺相关资讯
     * @param $shopId
     * @param Request $request
     * @return mixed
     */
    public function article($shopId,Request $request)
    {
        //店铺信息
        $uid = ShopModel::where('id',$shopId)->pluck('uid');
        $shopInfo = ShopModel::shopOneInfo($uid);
        if(!in_array(3,$shopInfo['nav_open'])){
            return redirect()->back()->with(['error' => '参数错误']);
        }
        if($shopInfo['status'] == 2){
            return redirect()->back()->with(['message' => '店铺已关闭']);
        }
        $this->theme->set('shopInfo',$shopInfo);
        $this->theme->set('SHOPID',$shopId);
        $this->theme->set('SHOPSORT',4);
        $shopView = $this->ShopView($uid,$shopId);

        //热门资讯
        $hotArticle = ArticleModel::with('skill')->orderBy('view_times','desc')->limit(6)->get()->toArray();
        //热门方案
        $hotGoods = GoodsModel::where('is_delete',0)->with('cover','field','user')->orderBy('view_num','desc')->limit(4)->get()->toArray();

        //资讯列表
        $list = ArticleModel::getArticleList(10,['uid'=> $uid,'is_skill'=>1]);

        $data = [
            'shopId'     => $shopId,
            'shopInfo'   => $shopInfo,
            'list'       => $list,
            'hotArticle' => $hotArticle,
            'hotGoods'   => $hotGoods,
            'shopView'   => $shopView
        ];

        $this->theme->setTitle($shopInfo['shop_name'].'相关资讯');
        return $this->theme->scope('shop.shop.article', $data)->render();
    }

    /**
     * 店铺 服务商档案 ajax获取选中记录
     * @param Request $request
     * @param $uid
     * @return mixed
     */
    public function ajaxbidTaskList(Request $request,$uid)
    {
        $this->initTheme('ajaxpage');
        //任务选中记录
        $taskIdArr = WorkModel::where('uid',$uid)->where('status',1)->lists('task_id')->toArray();
        $merge = [
            'is_open'     => 1,
            'task_id_arr' => $taskIdArr
        ];
        $taskList = TaskModel::getTaskList(5,$merge)->setPath('/fuwus/ajaxbidTaskList/'.$uid);
        if(!empty($taskList->toArray()['data'])){
            foreach($taskList as $key => $val){
                if((time()-strtotime($val['created_at']))> 0 && (time()-strtotime($val['created_at'])) < 3600){
                    $val->show_publish = intval((time()-strtotime($val['created_at']))/60).'分钟前';
                }
                if((time()-strtotime($val['created_at']))> 3600 && (time()-strtotime($val['created_at'])) < 24*3600){
                    $val->show_publish = intval((time()-strtotime($val['created_at']))/3600).'小时前';
                }
                if((time()-strtotime($val['created_at']))> 24*3600){
                    $val->show_publish = intval((time()-strtotime($val['created_at']))/(24*3600)).'天前';
                }
            }
        }
        $data = [
            'taskList' => $taskList,
        ];

        return $this->theme->scope('shop.shop.ajaxbidtask', $data)->render();
    }

    /**
     * 店铺 ajax获取方案
     * @param Request $request
     * @param $shopId
     * @return mixed
     */
    public function ajaxShopGoodsList(Request $request,$shopId)
    {
        $this->initTheme('ajaxpage');
        $merge['shop_id'] = $shopId;
        $goodsList = GoodsModel::getGoodsList(5,$merge)->setPath('/fuwus/ajaxShopGoodsList/'.$shopId);
        $data = [
            'goodsList' => $goodsList,
        ];

        return $this->theme->scope('shop.shop.ajaxgoods', $data)->render();
    }

    public function ajaxArticleList(Request $request,$uid)
    {
        $this->initTheme('ajaxpage');
        $article = ArticleModel::getArticleList(5,['uid'=> $uid,'is_skill'=>1])->setPath('/fuwus/ajaxArticleList/'.$uid);
        $data = [
            'article' => $article,
        ];

        return $this->theme->scope('shop.shop.ajaxarticle', $data)->render();
    }

    /**
     * 店铺浏览操作
     * @param $uid
     * @param $shopId
     * @return mixed
     */
    private function ShopView($uid,$shopId)
    {
        //给店铺添加浏览记录以及日志记录
        ShopModel::addShopView($uid,$shopId);
        //查看店铺浏览量
        //获取当天
        $shopView['day']=ShopViewLogModel::where('user_id',$uid)->where("create_at","like","%".date("Y-m-d",strtotime('-1 day'))."%")->count();
        //获取时间节点
        $weekDate = \CommonClass::getWeekStartEnd();
        $shopView['week'] =ShopViewLogModel::where('user_id',$uid)->where("create_at",">=",$weekDate['start'])->where("create_at","<=",$weekDate['end'])->count();
        //获取所有的浏览量
        $shopView['all']=ShopViewLogModel::where('user_id',$uid)->count();
        //店铺收藏量 
        $shopView['focus'] = ShopFocusModel::where('shop_id',$shopId)->count();
        return $shopView;
    }


}
