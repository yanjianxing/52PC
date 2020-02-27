<?php

namespace App\Modules\Substation\Http\Controllers;

use App\Http\Controllers\SubstationController;
use App\Http\Requests;
use App\Modules\Advertisement\Model\RecommendModel;
use App\Modules\Advertisement\Model\RePositionModel;
use App\Modules\Finance\Model\CashoutModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Manage\Model\SubstationModel;
use App\Modules\Task\Model\SuccessCaseModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskFocusModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskServiceModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\CommentModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class TaskController extends SubstationController
{
    public function __construct()
    {
        parent::__construct();
        $this->user = Auth::user();
        $this->initTheme('substation');
    }

    /**
     * 分站需求页面
     */
    public function getTasks($city, Request $request)
    {
        $city = SubstationModel::where('district_id', $city)->first();
        //seo配置信息
        $seoConfig = ConfigModel::getConfigByType('seo');
        if (!empty($seoConfig['seo_task']) && is_array($seoConfig['seo_task'])) {
            $this->theme->setTitle($seoConfig['seo_task']['title']);
            $this->theme->set('keywords', $seoConfig['seo_task']['keywords']);
            $this->theme->set('description', $seoConfig['seo_task']['description']);
        } else {
            $this->theme->setTitle('任务大厅');
        }
        //接收筛选条件
        $data = $request->all();
        //根据任务类型更新任务类型
        if (isset($data['category']) && $data['category'] != 0) {
            $category = TaskCateModel::findByPid([intval($data['category'])]);
            $pid = $data['category'];
            if (empty($category)) {
                $category_data = TaskCateModel::findById(intval($data['category']));
                $category = TaskCateModel::findByPid([intval($category_data['pid'])]);
                $pid = $category_data['pid'];
            }
        } else {
            //查询一级的分类,默认的是一级分类
            $category = TaskCateModel::findByPid([0]);
            $pid = 0;
        }

        if (isset($data['province'])) {
            $area_data = DistrictModel::findTree(intval($data['province']));
            $area_pid = $data['province'];
        } elseif (isset($data['city'])) {
            $area_data = DistrictModel::findTree(intval($data['city']));
            $area_pid = $data['city'];
        } elseif (isset($data['area'])) {
            $area = DistrictModel::where('id', '=', intval($data['area']))->first();
            $area_data = DistrictModel::findTree(intval($area['upid']));
            $area_pid = $area['upid'];
        } else {
            $area_data = DistrictModel::findTree($city['district_id']);
            $area_pid = 0;
        }

        //查询任务大厅的所有任务
        $list = TaskModel::findByCity($data,$city['district_id']);
        $lists = $list->toArray();
        $task_ids = array_pluck($lists['data'], ['id']);
        $task_service = TaskServiceModel::select('task_service.*', 'sc.title')->whereIn('task_id', $task_ids)
            ->join('service as sc', 'sc.id', '=', 'task_service.service_id')
            ->get()->toArray();
        $task_service = \CommonClass::keyByGroup($task_service, 'task_id');

        //判断当前是否登陆
        $my_focus_task_ids = [];
        if (Auth::check()) {
            //查询当前登录用户收藏的任务
            $my_focus_task_ids = TaskFocusModel::where('uid', Auth::user()['id'])->lists('task_id');
            $my_focus_task_ids = array_flatten($my_focus_task_ids);
        }

        //分站最新服务商
        $this->substation = $city['district_id'];
        $newShop = UserModel::select('user_detail.sign', 'users.name', 'user_detail.avatar', 'users.id',
            'users.email_status', 'user_detail.employee_praise_rate', 'user_detail.shop_status', 'shop.is_recommend', 'shop.id as shopId')
            ->leftJoin('user_detail', 'users.id', '=', 'user_detail.uid')
            ->leftJoin('shop', 'users.id', '=', 'shop.uid')
            ->where('users.status', '<>', 2)
            ->where(function ($list) {
                $list->where('user_detail.city', $this->substation)->orWhere('shop.city', $this->substation)
                    ->orwhere('user_detail.province', $this->substation)->orwhere('shop.province', $this->substation);
            })
            ->orderBy('shop.created_at', 'DESC')
            ->limit(5)->get()->toArray();
        if (count($newShop)) {
            foreach ($newShop as $k => $v) {
                $comment = CommentModel::where('to_uid', $v['id'])->count();
                $goodComment = CommentModel::where('to_uid', $v['id'])->where('type', 1)->count();
                if ($comment) {
                    $v['percent'] = intval(($goodComment / $comment) * 100);
                } else {
                    $v['percent'] = 100;
                }
                $newShop[$k] = $v;
            }
            $hotList = $newShop;
        } else {
            $hotList = [];
        }
        $this->theme->set('city', $city);
        $this->theme->set('menu_type', 3);
        //根据分站地区id获取地区名称
        $this->theme->set('substationID', $city['district_id']);
        $this->theme->set('substationNAME', $city['name']);
        $this->theme->setTitle($city['name'] . '需求');
        if(Session::get('substation_name')){
            Session::forget('substation_name');
            Session::put('substation_name',$city['name']);
        }else{
            Session::put('substation_name',$city['name']);
        }
        $view = [
            'list_array' => $lists,
            'list' => $list,
            'merge' => $data,
            'category' => $category,
            'pid' => $pid,
            'area' => $area_data,
            'area_pid' => $area_pid,
            'hotList' => $hotList,
            'my_focus_task_ids' => $my_focus_task_ids,
            'task_service' => $task_service,
            'city' => $city
        ];
        //执行任务调度
        \CommonClass::taskScheduling();
        return $this->theme->scope('substation.tasks', $view)->render();
    }

    /**
     * 分站首页
     *
     */
    public function index($city)
    {
        $city = SubstationModel::where('district_id', $city)->first();
        //首页banner
        $banner = \CommonClass::getHomepageBanner();
        //公告
        $notice = \CommonClass::getHomepageNotice();
        $this->theme->set('notice', $notice);

        //中标通知
        $taskWin = WorkModel::where('work.status', 1)->join('users', 'users.id', '=', 'work.uid')
            ->leftJoin('task', 'task.id', '=', 'work.task_id')
            ->select('work.*', 'users.name', 'task.show_cash', 'task.title')
            ->orderBy('work.bid_at', 'Desc')->limit(5)->get()->toArray();
        $this->theme->set('task_win', $taskWin);

        //提现
        $withdraw = CashoutModel::where('cashout.status', 1)->join('users', 'users.id', '=', 'cashout.uid')
            ->select('cashout.*', 'users.name')
            ->orderBy('cashout.updated_at', 'DESC')->limit(5)->get()->toArray();
        $this->theme->set('withdraw', $withdraw);

        //推荐店铺
        $recommendshops = ShopModel::where('status',1);
        $recommendshops = $recommendshops->where(function($recommendshops) use($city){
            $recommendshops->where('province',$city['district_id'])->orwhere('city',$city['district_id']);
        });
        $recommendshops = $recommendshops->orderBy('good_comment','desc')->limit(6)->get()->toArray();
        //服务
        $shop_ids = ShopModel::where('status',1);
        $shop_ids = $shop_ids->where(function($shop_ids) use($city){
            $shop_ids = $shop_ids->where('province',$city['district_id'])->orwhere('city',$city['district_id']);
        });
        $shop_ids = $shop_ids->orderBy('good_comment','desc')->lists('id');

        $shops = ShopModel::whereIn('id',$shop_ids)->get()->toArray();
        $recommendservice = GoodsModel::where('status',1)->where('type',2)
            ->where('is_delete',0)->whereIn('shop_id',$shop_ids)
            ->limit(8)->orderBy('sales_num','DESC')->get()->toArray();

        //作品
        $recommendgoods = GoodsModel::where('status',1)->where('type',1)
            ->where('is_delete',0)->whereIn('shop_id',$shop_ids)->limit(8)
            ->orderBy('sales_num','DESC')->get()->toArray();

        //最新任务
        $new_tasks = TaskModel::where('task.bounty_status',1)->where('task.status','>=',3)->where('task.region_limit',1);
        $new_tasks = $new_tasks->where(function($new_tasks) use($city){
            $new_tasks->where('task.province',$city['district_id'])->orwhere('task.city',$city['district_id']);
        });
        $new_tasks = $new_tasks->orderBy('created_at','DESC')
            ->leftjoin('users as us','us.id','=','task.uid')
            ->select('task.*','us.name as username')
            ->limit(12)->get()->toArray();
        //成功案例
        $uids = UserDetailModel::where('province',$city['district_id'])->orwhere('city',$city['district_id'])->lists('uid')->toArray();

        $success_case = SuccessCaseModel::whereIn('uid',$uids)
            ->leftjoin('cate as ct','ct.id','=','success_case.cate_id')
            ->select('success_case.*','ct.name as category')
            ->limit(5)->get()->toArray();
        //最新动态查询前8条
        $active = WorkModel::where('work.status', 1)
            ->whereIn('work.uid',$uids)
            ->join('users', 'users.id', '=', 'work.uid')
            ->leftJoin('task', 'task.id', '=', 'work.task_id')
            ->select('work.*', 'users.name', 'task.show_cash', 'task.title')
            ->orderBy('work.bid_at', 'Desc')->limit(8)->get()->toArray();
        //资讯
        $recommendPositionArticle = RePositionModel::where('code','HOME_BOTTOM')->where('is_open',1)->first();
        $article = RecommendModel::where('recommend.position_id',$recommendPositionArticle['id'])->where('recommend.type','article')->where('recommend.is_open',1)
            ->where(function($article){
                $article->where('recommend.end_time','0000-00-00 00:00:00')
                    ->orWhere('recommend.end_time','>',date('Y-m-d h:i:s',time()));
            })
            ->join('article','article.id','=','recommend.recommend_id')
            ->leftJoin('article_category','article_category.id','=','article.cat_id')
            ->select('recommend.*','article_category.cate_name','article.summary')
            ->orderBy('recommend.created_at','DESC')->limit(3)->get()->toArray();

        $this->theme->set('substationID', $city['district_id']);
        $this->theme->set('substationNAME', $city['name']);
        $this->theme->setTitle($city['name'] . '站');
        //session记住当前的站点信息
        Session::put('substation',$city);

        $view = [
            'news'=>$recommendPositionArticle,
            'banner'=>$banner,
            'active'=>$active,
            'recommendshops'=>$recommendshops,
            'recommendservice'=>$recommendservice,
            'recommendgoods'=>$recommendgoods,
            'new_tasks'=>$new_tasks,
            'success_case'=>$success_case,
            'article'=>$article,
            'uids'=>$uids,
            'shop_ids'=>$shop_ids,
            'shops'=>$shops
        ];
        if(Session::get('substation_name')){
            Session::forget('substation_name');
            Session::put('substation_name',$city['name']);
        }else{
            Session::put('substation_name',$city['name']);
        }
        $this->theme->set('city', $city);
        $this->theme->set('menu_type', 1);
        return $this->theme->scope('substation.index', $view)->render();
    }
}
