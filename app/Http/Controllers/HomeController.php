<?php
/**
 * ------------------------
 * Created by PhpStorm.
 * ------------------------
 *
 * ------------------------
 * Author: frank
 * Date: 16-4-19
 * Desc:
 * ------------------------
 *
 */

namespace App\Http\Controllers;

use App\Modules\Advertisement\Model\AdModel;
use App\Modules\Advertisement\Model\AdStatisticModel;
use App\Modules\Advertisement\Model\RecommendModel;
use App\Modules\Manage\Model\ActivityModel;
use App\Modules\Manage\Model\ArticleCategoryModel;
use App\Modules\Manage\Model\ArticleModel;
use App\Modules\Manage\Model\SeoModel;
use App\Modules\Manage\Model\SeoRelatedModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ProgrammeOrderModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Shop\Models\ShopTagsModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\UserModel;
use App\Modules\Manage\Model\ZdfastbuyModel;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use App\Modules\Advertisement\Model\AdTargetModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\CateModel;
use Cache;
use Teepluss\Theme\Theme;
use App\Modules\User\Model\AttachmentModel;


class HomeController extends IndexController
{
    public function __construct()
    {

        parent::__construct();
        $this->initTheme('common');
    }

    /**
     * 首页
     * @return mixed
     */
    public function index(request $request)
    {
        /*屏蔽异常访问*/
        $hmsr = $request->get('hmsr');
        $hmpl = $request->get('hmpl');
        if(isset($hmsr) || isset($hmpl) ){
            exit('请稍后访问！');
        }
        //首页广告
        $ad = AdTargetModel::getAdByCodePage('HOME');
        $this->theme->set('HOME_AD', $ad);
        //方案超市
        $goods = GoodsModel::getHomeGoodsByType(1);
        //获取行业分类
        $industryType = CateModel::where("type",1)->select('id','name')->get()->toArray();
        $this->theme->set('INDUSTRY_TYPE', $industryType);
        $this->theme->set('HOME_GOODS', $goods);

        //快包任务 物联网专区项目案例 电源/电机控制
        $task = TaskModel::getHomeTaskByType(1);
        $this->theme->set('HOME_TASK', $task);

        //首页头部
        $special_list = TaskModel::select("id","title","view_count","field_id","bounty")->where('type_id',1)->where('is_del',0)->where('status','>=',2)->where('status','<',10)
                           ->where('status','!=',3)->where('is_open','=','1')->whereIn('field_id',[1,3,4,9])->orderBy("created_at","desc")->limit(15)->get()->toArray();
        $specialzone_field = TaskCateModel::where('type',1)->lists('name','id')->toArray();

        $special3_list = TaskModel::select("id","title","view_count","field_id","bounty")->where('type_id',1)->where('is_del',0)->where('status','>=',2)->where('status','<',10)
                           ->where('status','!=',3)->where('is_open','=','1')->whereIn('field_id',[8,12,15])->orderBy("created_at","desc")->limit(15)->get()->toArray();
        $this->theme->set('SPECIELZONE_LIST', $special_list);
        $this->theme->set('SPECIELZONE3_LIST', $special3_list);
        $this->theme->set('SPECIELZONE_FIELD', $specialzone_field);
        //end首页头部 
        //领域
        $fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        $this->theme->set('FIELD_ARR', $fieldArr);

        //推荐服务商
        $shop = ShopModel::getHomeShopByType(1);
        //.判断推荐的服务商的会员等级以及是否认证手机号、邮箱
        if(isset($shop)){
           foreach($shop as $k=>$v){
               $shop[$k]['mobile'] = UserModel::where('id', $v['info']['uid'])->pluck('mobile');//.手机号认证
               $shop[$k]['email'] = UserModel::where('id', $v['info']['uid'])->pluck('email');//.邮箱认证
               $shop[$k]['email_status'] = UserModel::where('id', $v['info']['uid'])->pluck('email_status');//.邮箱状态
               $shop[$k]['level'] = UserModel::where('id', $v['info']['uid'])->pluck('level');//.会员等级
           }
        }
        $this->theme->set('HOME_SHOP', $shop);

        //.通知广告
        $AnnouncementsList = ActivityModel::getAnnouncementsList();
        $this->theme->set('ANNOUNCEMENTS', $AnnouncementsList);

        //推荐VIP服务商
        $vipShop = RecommendModel::getRecommendByCode('HOME_VIP_SERVICE','vipshop');
        $this->theme->set('HOME_VIPSHOP', $vipShop);

        //快包故事
        $story = RecommendModel::getRecommendByCode('HOME_STORY','story');
        $this->theme->set('HOME_STORY', $story);

        //成功案例
        $successCase = RecommendModel::getRecommendByCode('HOME_SUCCESS','successcase');
        $this->theme->set('HOME_SUCCESS', $successCase);

        //方案讯
        $article = RecommendModel::getRecommendByCode('HOME_ARTICLE','article');
        $this->theme->set('HOME_ARTICLE', $article);

        //最新动态
        $articleCate = ArticleCategoryModel::where('cate_name','网站公告')->first();
        $newArticle = [];
        if($articleCate){
            $articleCateId = $articleCate->id;
            $newArticle = ArticleModel::where('cat_id',$articleCateId)->orderBy('created_at','desc')->limit(3)->select('id','title','pic','created_at')->get()->toArray();
        }
        $this->theme->set('HOME_NEW_ARTICLE', $newArticle);

         //方案总发布数量
        $count['ordercount'] = GoodsModel::whereRaw('1=1')->count();
        //项目总发布数量
        $count['taskCount']  = TaskModel::whereRaw('1=1')->count();
        //服务商总发布数量
        $count['shopCount']  = ShopModel::whereRaw('1=1')->count();
        $count['shopCount']  = $count['shopCount']  + 40000;
        //服务商数量
        // $count['shopCount'] = ShopModel::where('status',1)->count();
        //项目数量
        // $count['taskCount'] = TaskModel::where('status','>',0)->count();
        //累计方案交易
        // $count['ordercount'] = GoodsModel::sum('sales_num');
        $this->theme->set('HOME_COUNT', $count);
        //首页中电快购中间部分
        $HOMECHIP = ZdfastbuyModel::where('is_del',0)->whereIn('show_location',[3,4])->get()->toArray();
        $this->theme->set('HOME_CHIP', $HOMECHIP);

        //seo配置信息
        $seoConfig = ConfigModel::getConfigByType('seo');

        if(!empty($seoConfig['seo_index']) && is_array($seoConfig['seo_index'])){
            $this->theme->setTitle($seoConfig['seo_index']['title']);
            $this->theme->set('keywords',$seoConfig['seo_index']['keywords']);
            $this->theme->set('description',$seoConfig['seo_index']['description']);
        }else{
            $this->theme->setTitle('电子方案开发供应链服务平台-找方案，上我爱方案网！');
            $this->theme->set('keywords','电子外包网、电子方案、设计方案、电子开发工程师,电子工程师平台、外包平台、元器件采购、BOM采购、原厂样片、元器件代购、元器件代理、解决技术问题、优化设计、修BUG、工程方案、IoT系统、AI、PCBA');
            $this->theme->set('description','我爱方案网提供软硬件开发外包、电子方案设计、电子工程师服务平台、元器件采购服务,平台汇聚一批优质的电子方案及优秀的专家级电子工程师,提供一站式电子方案开发供应链服务！！！');
        }
        $this->theme->set('nav_url', '/');
        return $this->theme->scope('bre.home.homepage')->render();

    }

    public function error(Request $request)
    {
        $this->initTheme('specialzone');
        $data = [
            
        ];
        return $this->theme->scope('bre.home.404',$data)->render();
    }

    /**
     * ajax获取首页案例超市
     * @param Request $request
     * @return mixed
     */
    public function ajaxHomeGoods(Request $request)
    {
        $this->initTheme('ajaxpage');
        $type = $request->get('type') ? $request->get('type') : 1;
        $goods = GoodsModel::getHomeGoodsByType($type);
        $ad = AdTargetModel::getAdByCode('HOME_C1');
        $data = [
            'type'  => $type,
            'goods' => $goods,
            'ad'    => $ad
        ];
        return $this->theme->scope('bre.home.ajaxgoods',$data)->render();
    }

    /**
     * ajax获取首页快包任务
     * @param Request $request
     * @return mixed
     */
    public function ajaxHomeTask(Request $request)
    {
        $this->initTheme('ajaxpage');
        $type = $request->get('type') ? $request->get('type') : 1;
        $arr['field_id'] = $request->get('field_id') ? $request->get('field_id') : 0;
        $task = TaskModel::getHomeTaskByType($type,$arr);

        $data = [
            'type'  => $type,
            'task'  => $task,
        ];
        return $this->theme->scope('bre.home.ajaxtask',$data)->render();
    }


    /**
     * ajax获取首页推荐服务商
     * @param Request $request
     * @return mixed
     */
    public function ajaxHomeShop(Request $request)
    {
        $this->initTheme('ajaxpage');
        $type = $request->get('type') ? $request->get('type') : 1;
        $shop = ShopModel::getHomeShopByTypeNew($type);
        //.判断推荐的服务商的会员等级以及是否认证手机号、邮箱
        if(isset($shop)){
            foreach($shop as $k=>$v){
                $shop[$k]['mobile'] = UserModel::where('id', $v['info']['uid'])->pluck('mobile');//手机号认证
                $shop[$k]['email'] = UserModel::where('id', $v['info']['uid'])->pluck('email');//邮箱认证
                $shop[$k]['email_status'] = UserModel::where('id', $v['info']['uid'])->pluck('email_status');//邮箱状态
                $shop[$k]['level'] = UserModel::where('id', $v['info']['uid'])->pluck('level');//会员等级
            }
        }
        
        $ad = AdTargetModel::getAdByCode('HOME_E');
        $data = [
            'type'  => $type,
            'shop'  => $shop,
            'ad'    => $ad
        ];
        return $this->theme->scope('bre.home.ajaxshop',$data)->render();
    }

    public function ajaxSearch(Request $request)
    {
        $keywords = $request->get('keywords') ? $request->get('keywords') : '';
        if($keywords){
            $goods = GoodsModel::select('id','title')->where('title','like','%'.$keywords.'%')->orderBy('view_num','desc')->limit(10)->get()->toArray();
            if($goods){
                foreach($goods as $k => $v){
                    $goods[$k]['url'] = '/facs/'.$v['id'];
                }
            }
            return [
                'data' => $goods
            ];
        }else{
            return [
                'data' => []
            ];
        }
    }

    /**
     * 搜索方案
     * @param Request $request
     * @return mixed
     */
    public function searchGoods(Request $request)
    {
        $this->initTheme('fastpackage');
        $this->theme->setTitle('搜方案');
        $this->theme->set('search_header',1);
        $keywords = $request->get('keywords') ? $request->get('keywords') : '';
        $this->theme->set('search_keywords',$keywords);

        $merge = $request->all();
        $goodsList = GoodsModel::getGoodsList(9,$merge);

        $hotGoods = GoodsModel::where('is_delete',0)->with('cover','field','user')->orderBy('view_num','desc')->limit(4)->get()->toArray();
        $fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        /*$ad = AdTargetModel::getAdByTypeId(1);
        AdTargetModel::addViewCountByCode('SEARCH_GOODS');*/
        $ad = AdTargetModel::getAdByCodePage('SEARCH_GOODS');
        $data = [
            'list'     => $goodsList,
            'merge'    => $merge,
            'hotGoods' => $hotGoods,
            'fieldArr' => $fieldArr,
            'ad'       => $ad
        ];
        return $this->theme->scope('bre.home.searchgoods', $data)->render();
    }


    /**
     * 搜索任务
     * @param Request $request
     * @return mixed
     */
    public function searchTask(Request $request)
    {
        $this->initTheme('fastpackage');
        $this->theme->setTitle('搜任务');
        $this->theme->set('search_header',2);
        $keywords = $request->get('keywords') ? $request->get('keywords') : '';
        $this->theme->set('search_keywords',$keywords);

        $merge = $request->all();
        $status = [
            1 => '竞标中',
            2 => '待托管',
            3 => '已托管',
            4 => '已完成'
        ];
        $taskBounty = [
            1 => [
                'name' => '3万以下',
                'min'  => 0,
                'max'  => 30000,
            ],
            2 => [
                'name' => '3到10万',
                'min'  => 30000,
                'max'  => 100000,
            ],
            3 => [
                'name' => '10万以上',
                'min'  => 100000,
                'max'  => 0,
            ],
        ];
        $order = [
            'created_at'     => '发布时间',
            'delivery_count' => '竞标数',
            'view_count'     => '人气',
            'bounty'         => '预算',
        ];
        $taskList = TaskModel::getTaskList(10,$merge,$status,$taskBounty);
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
        $hotTask = TaskModel::where('is_del',0)->whereIn('status',[2,3,4,5,6,7,8,9])->orderBy('view_count','desc')->limit(4)->get()->toArray();
        if(!empty($hotTask)){
            foreach($hotTask as $key => $val){
                if((time()-strtotime($val['created_at']))> 0 && (time()-strtotime($val['created_at'])) < 3600){
                    $hotTask[$key]['show_publish'] = intval((time()-strtotime($val['created_at']))/60).'分钟前';
                }
                if((time()-strtotime($val['created_at']))> 3600 && (time()-strtotime($val['created_at'])) < 24*3600){
                    $hotTask[$key]['show_publish'] = intval((time()-strtotime($val['created_at']))/3600).'小时前';
                }
                if((time()-strtotime($val['created_at']))> 24*3600){
                    $hotTask[$key]['show_publish'] = intval((time()-strtotime($val['created_at']))/(24*3600)).'天前';
                }
            }
        }
        $fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
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
        /*$ad = AdTargetModel::getAdByTypeId(1);
        AdTargetModel::addViewCountByCode('SEARCH_TASK');*/
        $ad = AdTargetModel::getAdByCodePage('SEARCH_TASK');
        $data = [
            'list'       => $taskList,
            'merge'      => $merge,
            'hotTask'    => $hotTask,
            'fieldArr'   => $fieldArr,
            'status'     => $status,
            'taskBounty' => $taskBounty,
            'order'      => $order,
            'area_data'  => $area_data,
            'area_pid'   => $area_pid,
            'ad'         => $ad
        ];
        return $this->theme->scope('bre.home.searchtask', $data)->render();
    }


    /**
     * 搜索服务商
     * @param Request $request
     * @return mixed
     */
    public function searchShop(Request $request)
    {
        $this->initTheme('fastpackage');
        $this->theme->setTitle('搜服务商');
        $this->theme->set('search_header',3);
        $keywords = $request->get('keywords') ? $request->get('keywords') : '';
        $this->theme->set('search_keywords',$keywords);

        $merge = $request->all();

        $order = [
            'good_comment'     => '好评数',
            'receive_task_num' => '成交量',
        ];
        $shopList = ShopModel::getShopList(12,$merge);
        $list = [];
        if(!empty($shopList->toArray()['data'])){
            $uidArr = array_pluck($shopList->toArray()['data'],'uid');
            $auth = ShopModel::getShopAuth($uidArr);
            $list = $shopList->toArray()['data'];
            //.新增认证
            foreach($list as $k => $v){
                $list[$k]['auth'] = in_array($v['uid'],array_keys($auth)) ? $auth[$v['uid']] : [];
                $list[$k]['mobile'] = UserModel::where('id', $v['uid'])->pluck('mobile');//.手机号认证
                $list[$k]['email'] = UserModel::where('id', $v['uid'])->pluck('email');//.邮箱认证
                $list[$k]['email_status'] = UserModel::where('id', $v['uid'])->pluck('email_status');//.邮箱状态
                $list[$k]['level'] = UserModel::where('id', $v['uid'])->pluck('level');//.会员等级
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
        /*$ad = AdTargetModel::getAdByTypeId(1);
        AdTargetModel::addViewCountByCode('SEARCH_SHOP');*/
        $ad = AdTargetModel::getAdByCodePage('SEARCH_SHOP');
        $data = [
            'list'       => $shopList,
            'list_arr'   => $list,
            'merge'      => $merge,
            'hotShop'    => $hotShop,
            'fieldArr'   => $fieldArr,
            'skillArr'   => $skillArr,
            'order'      => $order,
            'area_data'  => $area_data,
            'area_pid'   => $area_pid,
            'ad'         => $ad
        ];
        return $this->theme->scope('bre.home.searchshop', $data)->render();
    }


    /**
     * 搜索资讯
     * @param Request $request
     * @return mixed
     */
    public function searchArticle(Request $request)
    {
        $this->initTheme('fastpackage');
        $this->theme->setTitle('搜资讯');
        $this->theme->set('search_header',4);
        $keywords = $request->get('keywords') ? $request->get('keywords') : '';
        $this->theme->set('search_keywords',$keywords);

        $merge = $request->all();
        $order = [
            'view_times' => '浏览量',
            'created_at' => '发布时间',
        ];
        $articleList = ArticleModel::getArticleList(10,$merge);

        $catIdArr = ArticleCategoryModel::where('pid',1)->lists('id')->toArray();
        $hotArticle = ArticleModel::whereIn('cat_id',$catIdArr)->orderBy('view_times','desc')->limit(4)->get()->toArray();

        $fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();

        /*$ad = AdTargetModel::getAdByTypeId(1);
        AdTargetModel::addViewCountByCode('SEARCH_ARTICLE');*/
        $ad = AdTargetModel::getAdByCodePage('SEARCH_ARTICLE');
        $data = [
            'list'       => $articleList,
            'merge'      => $merge,
            'hotArticle' => $hotArticle,
            'fieldArr'   => $fieldArr,
            'order'      => $order,
            'ad'         => $ad
        ];
        return $this->theme->scope('bre.home.searcharticle', $data)->render();
    }
    /*根据seo搜索*/
    public function searchSeo(Request $request,$type,$id){
        $this->initTheme('shop');
        $this->theme->setTitle('标签搜索');
        $this->theme->set('search_header',4);
        $keywords = $request->get('keywords') ? $request->get('keywords') : '';
        $this->theme->set('search_keywords',$keywords);

        $merge =[
            'seo_id'=>$id,
            'seo_type'=>$type,
           // 'spelling'=>$request->get('spelling')?$request->get('spelling'):"0",
        ];
        // if($request->get('seo_id')){
          SeoModel::where("id",$id)->increment("view_num");
        // }
        //查询标签
        $seoFirst=SeoModel::find($id);
//        $seoList=SeoModel::whereRaw("1=1");
//        if($request->get('spelling')){
//            $seoList=$seoList->where("spelling",$request->get('spelling'));
//        }
//        $seoList=$seoList->get();
        //添加标签的浏览浏览量
        //查询所有的相关的id
        $relatedId=SeoRelatedModel::where("type",$type);
       // if($request->get('seo_id')){
        $relatedId=$relatedId->where("seo_id",$id);
       // }
//        if($request->get('spelling')){
//            $seoId=SeoModel::where("spelling",$request->get('spelling'))->lists("id")->toArray();
//            $relatedId=$relatedId->whereIn("seo_id",$seoId);
//        }
        $relatedId=$relatedId->lists("related_id")->toArray();
        $seo_name = 'seo_listtask';
        switch($merge['seo_type']){
            case 1:
                $seo_name = "seo_listtask";
                $list = TaskModel::whereIn("id",$relatedId)->orderBy('top_status','desc')->orderBy('id','desc')->paginate(10);
                foreach ($list as $key => $value) {
                    $list[$key]['seo_list'] = SeoRelatedModel::where("related_id",$value['id'])->where("type",$type)->get();
                }
                break;
            case 2;
                $seo_name = "seo_listfacs";
                $list = GoodsModel::whereIn("id",$relatedId)->orderBy('id','desc')->paginate(10);
                foreach ($list as $key => $value) {
                    //获取封面
                    $list[$key]['at_url']=AttachmentModel::where("id",$value['cover'])->pluck("url");
                    $list[$key]['seo_list'] = SeoRelatedModel::where("related_id",$value['id'])->where("type",$type)->get();
                }
                break;
            case 3:
                $seo_name = "seo_listnews";
                $list = ArticleModel::whereIn("id",$relatedId)->orderBy('id','desc')->paginate(10);
                foreach ($list as $key => $value) {
                    $list[$key]['seo_list'] = SeoRelatedModel::where("related_id",$value['id'])->where("type",$type)->get();
                }
                break;
        }
        $seoAll = SeoModel::lists("name","id")->toArray();
        //$catIdArr = ArticleCategoryModel::where('pid',1)->lists('id')->toArray();
        //$hotArticle = ArticleModel::whereIn('cat_id',$catIdArr)->orderBy('view_times','desc')->limit(4)->get()->toArray();

        //$fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        /*$ad = AdTargetModel::getAdByTypeId(1);
        AdTargetModel::addViewCountByCode('SEARCH_ARTICLE');*/
        $ad = AdTargetModel::getAdByCodePage('SEARCH_ARTICLE');
        //获取热门seo 标签
        $seoLabel=SeoModel::orderBy("view_num","desc")->limit(8)->get();
        $data = [
            'list'       => $list,
            'merge'      => $merge,
            'ad'         => $ad,
            'seoLabel'=>$seoLabel,
            'seoFirst' =>$seoFirst,
            'seoAll' =>$seoAll,
        ];
        $seoConfig = ConfigModel::getConfigByType('seo');
        if(!empty($seoConfig[$seo_name]) && is_array($seoConfig[$seo_name])){
            $seotitle = $seoFirst['name'];
            $titlearr = '';
            if($seoConfig[$seo_name]['title']){
                $titlearr = explode(',',$seoConfig[$seo_name]['title']);
                if(isset($titlearr[1]) && isset($titlearr[0])){
                    $seotitle = $seoFirst['name'].$titlearr[0].'、'.$seoFirst['name'].$titlearr[1];
                }else{
                    $seotitle = $seoFirst['name'].$titlearr[0];
                }
            }
            $this->theme->setTitle($seotitle);
            $this->theme->set('keywords',$seoFirst['name'].$seoConfig[$seo_name]['keywords']);
        }else{
            $this->theme->setTitle($seoFirst['name']);
            $this->theme->set('keywords',$seoFirst['name']);
        }
        $this->theme->set('description',mb_substr(strip_tags($seoFirst['desc']),0,200,'utf-8'));
        return $this->theme->scope('bre.home.searchSeo', $data)->render();
    }
    //搜索更多标签
    public function searchSeoMore(Request $request){
        $this->initTheme('shop');
        $this->theme->setTitle('标签搜索');
        $this->theme->set('search_header',4);
        $keywords = $request->get('keywords') ? $request->get('keywords') : '';
        $this->theme->set('search_keywords',$keywords);
        $merge=[
            'spelling'=>0,
        ];
        $seoList=SeoModel::whereRaw("1=1");
        $seoList=$seoList->orderBy("view_num","desc")->paginate(10);
        $data=[
            'seoList'=>$seoList,
            'merge'  =>$merge
        ];
        return $this->theme->scope('bre.home.searchSeoMore', $data)->render();
    }
    public function searchSeoMoreSpelling(Request $request,$spelling){
        $this->initTheme('shop');
        $this->theme->setTitle('标签搜索');
        $this->theme->set('search_header',4);
        $keywords = $request->get('keywords') ? $request->get('keywords') : '';
        $this->theme->set('search_keywords',$keywords);
        $merge=[
          'spelling'=>$spelling,
        ];
        $seoList=SeoModel::whereRaw("1=1");
        //if($request->get("spelling")){
            $seoList=$seoList->where("spelling",$spelling);
        //}
        $seoList=$seoList->orderBy("view_num","desc")->paginate(10);
        $data=[
            'seoList'=>$seoList,
            'merge'  =>$merge
        ];
        return $this->theme->scope('bre.home.searchSeoMore', $data)->render();
    }
    /**
     * 广告点击
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function adClick($id)
    {
        $ip = \CommonClass::getIp();

        $adArr = AdModel::find($id);
        $dataArr = [
            'ad_id'      => $id,
            'target_id'  => $adArr['target_id'],
            'created_at' => date('Y-m-d H:i:s'),
            'ip'         => $ip,
            'type'       => 2
        ];

        $n = date('Yn');
        (new AdStatisticModel())->setTable("ad_statistic_".$n)
            ->insert($dataArr);

        if($adArr->ad_type == 'image' && $adArr->ad_url){
            return redirect($adArr->ad_url);
        }else{
            return redirect()->back()->with(['error' => '参数错误']);
        }


    }

    public function adClickJs(Request $request)
    {
        $ip = \CommonClass::getIp();
        $id = $request->get('ad_id');
        $adArr = AdModel::find($id);
        $dataArr = [
            'ad_id'      => $id,
            'target_id'  => $adArr['target_id'],
            'created_at' => date('Y-m-d H:i:s'),
            'ip'         => $ip,
            'type'       => 2
        ];
        $n = date('Yn');
        (new AdStatisticModel())->setTable("ad_statistic_".$n)
            ->insert($dataArr);

        return ['code' => 1];
    }


}