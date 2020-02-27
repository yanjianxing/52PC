<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Modules\Employ\Models\UnionAttachmentModel;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Shop\Models\GoodsCommentModel;
use App\Modules\Shop\Models\GoodsFollowModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Shop\Models\ProgrammeEnquiryMessageModel;
use App\Modules\Shop\Models\ProgrammeOrderModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\UserModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\Task\Model\TaskServiceModel;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Task\Model\WorkCommentModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\Task\Model\SuccessCaseModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\User\Model\UserLoginModel;
use Cache;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class BaoBiaoController extends ManageController
{
    public $user;
    public function __construct()
    {
        parent::__construct();
        $this->user = $this->manager;
        $this->initTheme('manage');
        $this->theme->setTitle('方案报表');
    }

    public function case_baobiao(request $request){
        $merge = $request->all();
        $goodsList = GoodsModel::whereRaw('1 = 1');
        //店主筛选
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $goodsList = $goodsList->where(function($query) use ($keywords){
                $query->where('goods.title', 'like', '%' . $keywords . '%')
                    ->orWhere('goods.id', 'like', '%' . $keywords . '%')
                    ->orWhere('users.name', 'like', '%' . $keywords . '%');

            });
        }

        $typeArr = [
            1 => '方案销售',
            2 => '参考设计',
        ];
        //用户筛选
        if($request->get('userid')){
            $goodsList = $goodsList->where('goods.uid', '=', $request->get('userid'));
        }

        //方案类型
        if ($request->get('type')) {
            $goodsList = $goodsList->where('goods.type', $request->get('type'));
        }
        //方案状态态筛选
        if (($request->get('status') || $request->get('status') == '0') && $request->get('status') != -1) {
            $goodsList = $goodsList->where('goods.status', $request->get('status'));
        }
        //应用领域
        $cateArr = TaskCateModel::where('type',1)->get()->toArray();
        if ($request->get('cate_id')) {
            $goodsList = $goodsList->where('goods.cate_id', $request->get('cate_id'));
        }
        //时间
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $goodsList = $goodsList->where('goods.created_at', '>=', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $goodsList = $goodsList->where('goods.created_at', '<=', $end);
        }

        $byarr = $request->get('by') ? $request->get('by') : '0';
        switch ($byarr) {
            case '1':
                $by = 'goods.view_num';
                break;
            case '2':
                $by = 'goods.sales_num';
                break;
            case '3':
                $by = 'goods.inquiry_num';
                break;
            default:
                $by = 'goods.id';
                break;
        }
        $orderarr = $request->get('order') ? $request->get('order') : '0';
        switch ($orderarr) {
            case '1':
                $order = 'ASC';
                break;
            default:
                $order = 'DESC';
                break;
        }
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $goodsList = $goodsList->where('goods.is_delete',0)->wherein('goods.status',[1,2])->leftJoin('users','users.id','=','goods.uid')
            ->select('goods.*','users.name','users.mobile','users.email')
            ->orderBy($by, $order)->paginate($paginate);
        $data = [
            'merge'      => $merge,
            'goods_list' => $goodsList,
            'type_arr'   => $typeArr,
            'cate_arr'   => $cateArr
        ];
        return $this->theme->scope('manage.caseBaoBiao',$data)->render();
    }
    //询价报表
    public function xunjia_baobiao(request $request){
        $merge = $request->all();
        $goodsList = ProgrammeEnquiryMessageModel::whereRaw('1 = 1')->where('programme_enquiry_message.type','1');
        //店主筛选
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $goodsList = $goodsList->where(function($query) use ($keywords){
                $query->where('goods.title', 'like', '%' . $keywords . '%')
                    ->orWhere('programme_enquiry_message.id', 'like', '%' . $keywords . '%')
                    ->orWhere('gzuser.mobile', 'like', '%' . $keywords . '%')
                    ->orWhere('gzuser.name', 'like', '%' . $keywords . '%')
                    ->orWhere('fwsuser.name', 'like', '%' . $keywords . '%');
            });
        }
        $typeArr = [
            1 => '方案销售',
            2 => '参考设计',
        ];
        //用户筛选
        if($request->get('userid')){
            $goodsList = $goodsList->where('programme_enquiry_message.uid', '=', $request->get('userid'));
        }
        //咨询类型
        if ($request->get('consult_type')) {
            $goodsList = $goodsList->where('programme_enquiry_message.consult_type', $request->get('consult_type'));
        }
        //支付状态
        if ($request->get('pay_type')) {
            $goodsList = $goodsList->where('programme_enquiry_message.pay_type', $request->get('pay_type'));
        }
        //时间
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $goodsList = $goodsList->where('programme_enquiry_message.created_at', '>=', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $goodsList = $goodsList->where('programme_enquiry_message.created_at', '<=', $end);
        }

        $byarr = $request->get('by') ? $request->get('by') : '0';
        switch ($byarr) {
            case '1':
                $by = 'goods.view_num';
                break;
            case '2':
                $by = 'goods.sales_num';
                break;
            default:
                $by = 'programme_enquiry_message.id';
                break;
        }
        $orderarr = $request->get('order') ? $request->get('order') : '0';
        switch ($orderarr) {
            case '1':
                $order = 'ASC';
                break;
            default:
                $order = 'DESC';
                break;
        }
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $goodsList = $goodsList->leftJoin('goods','goods.id','=','programme_enquiry_message.programme_id')
            ->leftjoin("users as gzuser",'gzuser.id','=','programme_enquiry_message.consultant_id')
            ->leftjoin("users as fwsuser",'fwsuser.id','=','programme_enquiry_message.uid')
            ->select('programme_enquiry_message.*','gzuser.name as gzname','fwsuser.mobile as gzmobile','fwsuser.name as fwsname','goods.title')
            ->orderBy($by, $order)->paginate($paginate);
        $data = [
            'merge'      => $merge,
            'goods_list' => $goodsList,
            'type_arr'   => $typeArr,
        ];
        $this->theme->setTitle('询价报表');
        return $this->theme->scope('manage.xunJiaBaoBiao',$data)->render();
    }

    //销售报表
    public function xiaoshou_baobiao(request $request){
        $merge = $request->all();
        $goodsList = ProgrammeOrderModel::whereRaw('1 = 1');
        //店主筛选
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $goodsList = $goodsList->where(function($query) use ($keywords){
                $query->where('goods.title', 'like', '%' . $keywords . '%')
                    ->orWhere('programme_order.id', 'like', '%' . $keywords . '%')
                    ->orWhere('gzuser.mobile', 'like', '%' . $keywords . '%')
                    ->orWhere('gzuser.name', 'like', '%' . $keywords . '%')
                    ->orWhere('fwsuser.name', 'like', '%' . $keywords . '%');

            });
        }
        $typeArr = [
            1 => '方案销售',
            2 => '参考设计',
        ];
        //方案状态态筛选
        if ($request->get('status')) {
            $goodsList = $goodsList->where('programme_order.status', $request->get('status'));
        }
        //时间
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $goodsList = $goodsList->where('programme_order.created_at', '>=', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $goodsList = $goodsList->where('programme_order.created_at', '<=', $end);
        }

        $byarr = $request->get('by') ? $request->get('by') : '0';
        switch ($byarr) {
            case '1':
                $by = 'goods.view_num';
                break;
            case '2':
                $by = 'goods.sales_num';
                break;
            default:
                $by = 'programme_order.id';
                break;
        }
        $orderarr = $request->get('order') ? $request->get('order') : '0';
        switch ($orderarr) {
            case '1':
                $order = 'ASC';
                break;
            default:
                $order = 'DESC';
                break;
        }
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $goodsList = $goodsList->leftJoin('goods','goods.id','=','programme_order.programme_id')
            ->leftjoin("users as gzuser",'gzuser.id','=','programme_order.uid')
            ->leftjoin("users as fwsuser",'fwsuser.id','=','goods.uid')
            ->select('programme_order.*','gzuser.name as gzname','gzuser.mobile as gzmobile','goods.title','goods.uid as gzid','fwsuser.name as fwsname')
            ->orderBy($by, $order)->paginate($paginate);
        $data = [
            'merge'      => $merge,
            'goods_list' => $goodsList,
            'type_arr'   => $typeArr,
        ];
        $this->theme->setTitle('销售报表');
        return $this->theme->scope('manage.xiaoShouBaoBiao',$data)->render();
    }

    //发包报表
    public function fabao_baobiao(request $request){
        $merge = $request->all();
        $goodsList = TaskModel::whereRaw('1 = 1');
        //店主筛选
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $goodsList = $goodsList->where(function($query) use ($keywords){
                $query->where('task.title', 'like', '%' . $keywords . '%')
                    ->orWhere('task.id', 'like', '%' . $keywords . '%')
                    ->orWhere('users.name', 'like', '%' . $keywords . '%')
                    ->orWhere('users.mobile', 'like', '%' . $keywords . '%');
            });
        }
        
        //状态筛选
        if($request->get('status') && $request->get('status') != 0){
            $status = $request->get('status');
            $goodsList = $goodsList->where(function ($goodsList) use ($status){
                  $goodsList->whereraw('wafaw_task.status='.$status.' and wafaw_task.type_id<>3')
                            ->orwhereRaw('wafaw_task.status='.$status.' and wafaw_task.type_id=3');
        });
        }

        //来源筛选
        if($request->get('from_to') && $request->get('from_to') != 0){
            $from_to = $request->get('from_to');
            $goodsList = $goodsList->where('task.from_to', '=', $from_to);
        }

        //用户筛选
        if($request->get('userid')){
            $goodsList = $goodsList->where('task.uid', '=', $request->get('userid'));
        }

        //发布时间筛选
        if($request->get('time_type') && $request->get('time_type') == 1){
            if ($request->get('start')) {
                $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
                $start = date('Y-m-d 00:00:00',strtotime($start));
                $goodsList = $goodsList->where('task.created_at', '>=', $start);
            }
            if ($request->get('end')) {
                $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
                $end = date('Y-m-d 23:59:59',strtotime($end));
                $goodsList = $goodsList->where('task.created_at', '<=', $end);
            }
        }
        //雇主注册时间筛选
        if($request->get('time_type') && $request->get('time_type') == 2){

            if ($request->get('start')) {
                $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
                $start = date('Y-m-d 00:00:00',strtotime($start));
                $goodsList = $goodsList->where('users.created_at', '>=', $start);
            }
            if ($request->get('end')) {
                $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
                $end = date('Y-m-d 23:59:59',strtotime($end));
                $goodsList = $goodsList->where('users.created_at', '<=', $end);
            }
        }
        $byarr = $request->get('by') ? $request->get('by') : '0';
        switch ($byarr) {
            case '1':
                $by = 'task.view_count';
                break;
            case '2':
                $by = 'goods.sales_num';
                break;
            default:
                $by = 'task.id';
                break;
        }
        $orderarr = $request->get('order') ? $request->get('order') : '0';
        switch ($orderarr) {
            case '1':
                $order = 'ASC';
                break;
            default:
                $order = 'DESC';
                break;
        }
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $goodsList = $goodsList
            ->leftjoin("users",'users.id','=','task.uid')
            ->leftjoin("cate",function($join){
                $join->on('cate.id','=','task.field_id')
                     ->where("cate.type","=","1");
            })
            ->leftjoin("task_type","task_type.id","=","task.type_id")
            ->select('task.*','task_type.name as tyname','users.name as gzname','users.id as gzid','users.mobile as gzmobile','users.email as gzemail','users.created_at as gzcreated_at','cate.name as cname')
            ->orderBy($by, $order)->paginate($paginate);
        foreach ($goodsList as $key => $value) {
            $goodsList[$key]['addres'] =   DistrictModel::getDistrictName($value['province']).','.DistrictModel::getDistrictName($value['city']);
            $goodsList[$key]['status_type'] =  \CommonClass::getTaskStatus($value['status'],$value['type_id']);
            $serviceId=TaskServiceModel::where("task_id",$value['id'])->lists("service_id")->toArray();
            if(!$serviceId){
                $goodsList[$key]['projectType']="免费";
            }else{
                $serviceName=ServiceModel::whereIn("id",$serviceId)->lists("title")->toArray();
                $goodsList[$key]['projectType']=implode($serviceName,"/");
            }
        }
        $data = [
            'merge'      => $merge,
            'goods_list' => $goodsList,
        ];
        $this->theme->setTitle('发包报表');
        return $this->theme->scope('manage.faBaoBaoBiao',$data)->render();
    }

    //接包报表
    public function jiebao_baobiao(request $request){
        $merge = $request->all();
        $goodsList = WorkModel::whereRaw('1 = 1')->whereIn('work.status',['0','1']);
        //店主筛选
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $goodsList = $goodsList->where(function($query) use ($keywords){
                $query->where('task.title', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('users.name', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('users.mobile', 'like', '%' . trim($keywords) . '%');
            });
        }
        //用户筛选
        if($request->get('userid')){
            $goodsList = $goodsList->where('work.uid', '=', $request->get('userid'));
        }
        //状态态筛选
        if (($request->get('status') || $request->get('status') == '0') && $request->get('status') != -1) {
            $goodsList = $goodsList->where('work.status', $request->get('status'));
        }
        
        //竞标时间筛选
        if($request->get('time_type') && $request->get('time_type') == 1){
            if ($request->get('start')) {
                $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
                $start = date('Y-m-d 00:00:00',strtotime($start));
                $goodsList = $goodsList->where('work.created_at', '>=', $start);
            }
            if ($request->get('end')) {
                $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
                $end = date('Y-m-d 23:59:59',strtotime($end));
                $goodsList = $goodsList->where('work.created_at', '<=', $end);
            }
        }
        //雇主注册时间筛选
        if($request->get('time_type') && $request->get('time_type') == 2){

            if ($request->get('start')) {
                $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
                $start = date('Y-m-d 00:00:00',strtotime($start));
                $goodsList = $goodsList->where('users.created_at', '>=', $start);
            }
            if ($request->get('end')) {
                $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
                $end = date('Y-m-d 23:59:59',strtotime($end));
                $goodsList = $goodsList->where('users.created_at', '<=', $end);
            }
        }

        $by = $request->get('by') ? $request->get('by') : 'work.id';
        $order = $request->get('order') ? $request->get('order') : 'DESC';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 100;

        $goodsList = $goodsList
            ->leftjoin("users",'users.id','=','work.uid')
            ->leftjoin("task","task.id","=","work.task_id")
            ->select('work.*','task.status as tsstatus','task.uid as tsuid','task.bounty as tsbounty','task.type_id as tstype','task.title as tstitle','task.created_at as tscreatedat','users.name as fwsname','users.id as fwsid','users.mobile as fwsmobile','users.created_at as fwscreated_at','users.level')
            ->orderBy($by, $order)->paginate($paginate);
        foreach ($goodsList as $key => $value) {
            $goodsList[$key]['status_type'] =  \CommonClass::getTaskStatus($value['tsstatus'],$value['tstype']);
            $goodsList[$key]['gzname'] =  UserModel::where('id','=',$value['tsuid'])->pluck('name');
            $goodsList[$key]['shop_name'] =  ShopModel::where('uid','=',$value['uid'])->pluck('shop_name');
            $serviceId=TaskServiceModel::where("task_id",$value['id'])->lists("service_id")->toArray();
            if(!$serviceId){
                $goodsList[$key]['projectType']="免费";
            }else{
                $serviceName=ServiceModel::whereIn("id",$serviceId)->lists("title")->toArray();
                $goodsList[$key]['projectType']=implode($serviceName,"/");
            }
        }
        $data = [
            'merge'      => $merge,
            'goods_list' => $goodsList,
        ];
        $this->theme->setTitle('接包报表');
        return $this->theme->scope('manage.jieBaoBaoBiao',$data)->render();
    }

    //选中报表
    public function xuanzhong_baobiao(request $request){
        $merge = $request->all();
        $goodsList = TaskModel::whereRaw('1 = 1')->where('work.status','=','1');
        //店主筛选
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $goodsList = $goodsList->where(function($query) use ($keywords){
                $query->where('task.title', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('gzusers.name', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('gzusers.mobile', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('fwsusers.name', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('fwsusers.mobile', 'like', '%' . trim($keywords) . '%');
            });
        }
        
        //用户筛选
        if($request->get('userid')){
            $goodsList = $goodsList->where('work.uid', '=', $request->get('userid'));
        }
        //时间
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $goodsList = $goodsList->where('work.bid_at', '>=', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $goodsList = $goodsList->where('work.bid_at', '<=', $end);
        }

        $by = $request->get('by') ? $request->get('by') : 'work.bid_at';
        $order = $request->get('order') ? $request->get('order') : 'DESC';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $goodsList = $goodsList
            ->leftjoin("users as gzusers",'gzusers.id','=','task.uid')
            ->leftjoin("work","work.task_id","=","task.id")
            ->leftjoin("users as fwsusers",'fwsusers.id','=','work.uid')
            ->leftjoin("cate",function($join){
                $join->on('cate.id','=','task.field_id')
                     ->where("cate.type","=","1");
            })
            ->select('task.*','gzusers.name as gzname','gzusers.id as gzid','gzusers.mobile as gzmobile','cate.name as cname','work.bid_at as zbtime','work.uid as fwsid','fwsusers.name as fwsname','fwsusers.mobile as fwsmobile','fwsusers.email as fwsemail','work.price')
            ->orderBy($by, $order)->paginate($paginate);
        
        $data = [
            'merge'      => $merge,
            'goods_list' => $goodsList,
        ];
        $this->theme->setTitle('选中报表');
        return $this->theme->scope('manage.xuanZhongBaoBiao',$data)->render();
    }

    //托管报表
    public function tuoguan_baobiao(request $request){
        $merge = $request->all();
        $goodsList = TaskModel::whereRaw('1 = 1')->where('task.status','>=','5')->where('task.status','<','8')->where('work.status','=','1');
        //店主筛选
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $goodsList = $goodsList->where(function($query) use ($keywords){
                $query->where('task.title', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('gzusers.name', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('gzusers.mobile', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('fwsusers.name', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('fwsusers.mobile', 'like', '%' . trim($keywords) . '%');
            });
        }
        
        //时间
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $goodsList = $goodsList->where('task.publicity_at', '>=', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $goodsList = $goodsList->where('task.publicity_at', '<=', $end);
        }

        $by = $request->get('by') ? $request->get('by') : 'task.id';
        $order = $request->get('order') ? $request->get('order') : 'DESC';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $goodsList = $goodsList
            ->leftjoin("users as gzusers",'gzusers.id','=','task.uid')
            ->leftjoin("work","work.task_id","=","task.id")
            ->leftjoin("users as fwsusers",'fwsusers.id','=','work.uid')
            ->leftjoin("cate",function($join){
                $join->on('cate.id','=','task.field_id')
                     ->where("cate.type","=","1");
            })
            ->select('task.*','gzusers.name as gzname','gzusers.id as gzid','gzusers.mobile as gzmobile','cate.name as cname','work.bid_at as zbtime','work.uid as fwsid','fwsusers.name as fwsname','fwsusers.mobile as fwsmobile','fwsusers.email as fwsemail','work.price')
            ->orderBy($by, $order)->paginate($paginate);
        $data = [
            'merge'      => $merge,
            'goods_list' => $goodsList,
        ];
        $this->theme->setTitle('接包报表');
        return $this->theme->scope('manage.tuoGuanBaoBiao',$data)->render();
    }

    //结案报表
    public function jiean_baobiao(request $request){
        $merge = $request->all();
        $goodsList = TaskModel::whereRaw('1 = 1')->where('work.status','=','3')->whereraw('wafaw_task.status=7');
        //店主筛选
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $goodsList = $goodsList->where(function($query) use ($keywords){
                $query->where('task.title', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('gzusers.name', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('gzusers.mobile', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('fwsusers.name', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('fwsusers.mobile', 'like', '%' . trim($keywords) . '%');
            });
        }
        
        //时间
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $goodsList = $goodsList->where('task.comment_at', '>=', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $goodsList = $goodsList->where('task.comment_at', '<=', $end);
        }

        $by = $request->get('by') ? $request->get('by') : 'work.id';
        $order = $request->get('order') ? $request->get('order') : 'DESC';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $goodsList = $goodsList
            ->leftjoin("users as gzusers",'gzusers.id','=','task.uid')
            ->leftjoin("work","work.task_id","=","task.id")
            ->leftjoin("users as fwsusers",'fwsusers.id','=','work.uid')
            ->leftjoin("cate",function($join){
                $join->on('cate.id','=','task.field_id')
                     ->where("cate.type","=","1");
            })
            ->select('task.*','gzusers.name as gzname','gzusers.id as gzid','gzusers.mobile as gzmobile','cate.name as cname','work.bid_at as zbtime','work.uid as fwsid','fwsusers.name as fwsname','fwsusers.mobile as fwsmobile','fwsusers.email as fwsemail','work.price')
            ->orderBy($by, $order)->groupBy('work.task_id')->paginate($paginate);
        $data = [
            'merge'      => $merge,
            'goods_list' => $goodsList,
        ];
        $this->theme->setTitle('结案报表');
        return $this->theme->scope('manage.jieAnBaoBiao',$data)->render();
    }

    //验收报表
    public function yanshou_baobiao(request $request){
        $merge = $request->all();
        $goodsList = TaskModel::where('work.status','>','1')->where(function ($goodsList){
            $goodsList->whereraw('wafaw_task.status>6');
                      // ->orwhereRaw('wafaw_task.status=8');
        });
        //店主筛选
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $goodsList = $goodsList->where(function($query) use ($keywords){
                $query->where('task.title', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('gzusers.name', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('gzusers.mobile', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('fwsusers.name', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('fwsusers.mobile', 'like', '%' . trim($keywords) . '%');
            });
        }
        
        //时间
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $goodsList = $goodsList->where('work.bid_at', '>=', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $goodsList = $goodsList->where('work.bid_at', '<=', $end);
        }
        $by = $request->get('by') ? $request->get('by') : 'work.id';
        $order = $request->get('order') ? $request->get('order') : 'DESC';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $goodsList = $goodsList
            ->leftjoin("users as gzusers",'gzusers.id','=','task.uid')
            ->leftjoin("work","work.task_id","=","task.id")
            ->leftjoin("users as fwsusers",'fwsusers.id','=','work.uid')
            ->leftjoin("cate",function($join){
                $join->on('cate.id','=','task.field_id')
                     ->where("cate.type","=","1");
            })
            ->select('task.*','gzusers.name as gzname','gzusers.id as gzid','gzusers.mobile as gzmobile','cate.name as cname','work.bid_at as zbtime','work.uid as fwsid','fwsusers.name as fwsname','fwsusers.mobile as fwsmobile','fwsusers.email as fwsemail','work.price')
            ->orderBy($by, $order)->paginate($paginate);
        $data = [
            'merge'      => $merge,
            'goods_list' => $goodsList,
        ];
        $this->theme->setTitle('验收报表');
        return $this->theme->scope('manage.yanShouBaoBiao',$data)->render();
    }

    //用户报表
    public function yonghu_baobiao(request $request){
        $merge = $request->all();
        
        $goodsList = UserModel::whereRaw('1 = 1');
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $goodsList = $goodsList->where(function($query) use ($keywords){
                $query->Where('users.name', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('users.mobile', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('users.id', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('users.email', 'like', '%' . trim($keywords) . '%');
            });
        }
        //注册时间筛选　
        if ($request->get('register_start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('register_start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $goodsList = $goodsList->where('user_detail.created_at', '>=', $start);
        }
        if ($request->get('register_end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('register_end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $goodsList = $goodsList->where('user_detail.created_at', '<=', $end);
        }

        //登录时间筛选
        if($request->get('login_start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('login_start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $goodsList = $goodsList->where('user_detail.last_login_time','>',$start);

        }
        if($request->get('login_end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('login_end'));
            $end = date('Y-m-d H:i:s',strtotime($end)+24*60*60);
            $goodsList = $goodsList->where('user_detail.last_login_time','<',$end);
        }

        //注册类型筛选
        if($request->get('type') && $request->get('type')!='0'){
            $goodsList = $goodsList->where('users.type','=',$request->get('type'));
        }

        //用户类型筛选
        if($request->get('auth_type') && $request->get('auth_type')!='0'){
            $goodsList = $goodsList->where('user_detail.auth_type','=',$request->get('auth_type'));
        }

        //来源筛选
        if($request->get('source') && $request->get('source')!='0'){
            $goodsList = $goodsList->where('users.source','=',$request->get('source'));
        }

        //地区筛选
        if($request->get('province') && $request->get('province')>='0'){
            $goodsList = $goodsList->where('user_detail.province','=',$request->get('province'));

        }

        //用户属性筛选
        if($request->get('level') && $request->get('level')!='0'){
            $goodsList = $goodsList->where('users.level','=',$request->get('level'));

        }
        
        $by = $request->get('by') ? $request->get('by') : 'users.id';
        $order = $request->get('order') ? $request->get('order') : 'DESC';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 100;

        // $sql = "SELECT count(wafaw_task.`uid`) as `res` FROM wafaw_users LEFT JOIN wafaw_task ON wafaw_task.`uid` = wafaw_users.`id` GROUP  BY wafaw_task.`uid`";
        // $data = DB::table(DB::raw("($sql) as res"))->paginate($paginate);
        // print_r($data);exit;

        $goodsList = $goodsList
            ->leftjoin("user_detail",'user_detail.uid','=','users.id')
            ->select('users.*','user_detail.province','user_detail.city','user_detail.area','user_detail.auth_type','user_detail.functional','user_detail.balance');
        $goodsList = $goodsList->orderBy($by, $order)->paginate($paginate);
        
        foreach ($goodsList as $key => $value) {
            $goodsList[$key]['fPackage']=TaskModel::where("uid",$value['id'])->count();
            $goodsList[$key]['jPackage']=WorkModel::where("uid",$value['id'])->count();
            $goodsList[$key]['ZPackage']=WorkModel::where("uid",$value['id'])->where('status','=',1)->count();
            $goodsList[$key]['XJackage']=ProgrammeEnquiryMessageModel::where("uid",$value['id'])->where('type','=',1)->count();
            $goodsList[$key]['caseNum']=goodsModel::where("uid",$value['id'])->where('goods.is_delete',0)->count();
            $goodsList[$key]['caseSalesNum']=goodsModel::where("uid",$value['id'])->where('goods.is_delete',0)->sum('sales_num');
            if(!empty($value['area'])){
                $goodsList[$key]['addres'] = DistrictModel::getDistrictName($value['province']).','.DistrictModel::getDistrictName($value['city']).','.DistrictModel::getDistrictName($value['area']);
            }elseif(!empty($value['city'])){
                $goodsList[$key]['addres'] = DistrictModel::getDistrictName($value['province']).','.DistrictModel::getDistrictName($value['city']);
            }elseif(!empty($value['province'])){
                $goodsList[$key]['addres'] = DistrictModel::getDistrictName($value['province']);
            }else{
                $goodsList[$key]['addres'] = '';
            }
            
        }
        // if($request->get('paixu')){
        //     $paixu = $request->get('paixu');
        //     $newArr = $valArr = array();
        //     foreach ($goodsList as $key=>$value) {
        //         $valArr[$key] = $value[$paixu]; 
        //     }
        //     arsort($valArr);//先利用keys对数组排序，目的是把目标数组的key排好序
        //     reset($valArr); //指针指向数组第一个值 
        //     foreach($valArr as $key=>$value) {
        //         $newArr[$key] = $valArr[$key];
        //     }
        //     $goodsList = $newArr;
        // }

        $province = DistrictModel::findTree(0);
        $data = [
            'merge'      => $merge,
            'goods_list' => $goodsList,
            'province'  => $province,
        ];
        $this->theme->setTitle('用户报表');
        return $this->theme->scope('manage.yongHuBaoBiao',$data)->render();
    }


    //用户登录报表
    public function yonghulogin_baobiao(Request $request){
        $merge = $request->all();
        //获取用户登录信息
        $list=UserLoginModel::where('id','>','0');
        //搜索
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $goodsList = $list->where(function($query) use ($keywords){
                $query->Where('user_login.uid', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('user_login.mobile', 'like', '%' . trim($keywords) . '%');
            });
        }

        //用户类型筛选
        if($request->get('type') && $request->get('type')!='0'){
            $list = $list->where('user_login.type','=',$request->get('type'));
        }

        //用户属性筛选
        if($request->get('level') && $request->get('level')!='0'){
            $list = $list->where('user_login.level','=',$request->get('level'));
        }

        //地区筛选
        if($request->get('province') && $request->get('province')>='0'){
            $list = $list->where('user_login.province','=',$request->get('province'));
        }

        //注册时间筛选　
        if ($request->get('created_at_start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('created_at_start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $list = $list->where('user_login.created_at', '>', $start);
        }
        if ($request->get('created_at_end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('created_at_end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $list = $list->where('user_login.created_at', '<', $end);
        }

        //登录时间筛选
        if($request->get('login_time_start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('login_time_start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $list = $list->where('user_login.login_time','>',$start);

        }
        if($request->get('login_time_end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('login_time_end'));
            $end = date('Y-m-d H:i:s',strtotime($end)+24*60*60);
            $list = $list->where('user_login.login_time','<',$end);
        }


        //获取分页
        $list = $list->orderBy('id','desc')->paginate(10);
        //获取省市区
        $province = DistrictModel::findTree(0);
        foreach($list as $k=>$v){
           if($v['province']){
               $province=DistrictModel::where('id',$v['province'])->pluck('name');

                if($province){
                    $list[$k]['province'] = $province;
                    $list[$k]['city'] = DistrictModel::getDistrictName($v['city']);
                    $list[$k]['area']= DistrictModel::getDistrictName($v['area']);
                }
           }
        }
        $province = DistrictModel::findTree(0);
        $data = [
            'list' => $list,
            'merge'      => $merge,
            'province'      => $province,
        ];
        $this->theme->setTitle('用户登录报表');
        return $this->theme->scope('manage.yongHuLoginBaoBiao',$data)->render();
    }


    //会员报表
    public function huiyuan_baobiao(request $request){
        $merge = $request->all();
        $goodsList = VipUserOrderModel::whereRaw('1 = 1');
        if ($request->get('keywords')) {
            $keywords = $request->get('keywords');
            $goodsList = $goodsList->where(function($query) use ($keywords){
                $query->Where('users.name', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('users.mobile', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('users.id', 'like', '%' . trim($keywords) . '%')
                    ->orWhere('users.email', 'like', '%' . trim($keywords) . '%');
            });
        }
        //注册时间筛选　
        if ($request->get('register_start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('register_start'));
            $start = date('Y-m-d 00:00:00',strtotime($start));
            $goodsList = $goodsList->where('user_detail.created_at', '>=', $start);
        }
        if ($request->get('register_end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('register_end'));
            $end = date('Y-m-d 23:59:59',strtotime($end));
            $goodsList = $goodsList->where('user_detail.created_at', '<=', $end);
        }

        //购买时间筛选
        if($request->get('buy_start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('buy_start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $goodsList = $goodsList->where('user_viporder.pay_time','>',$start);

        }
        if($request->get('buy_end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('buy_end'));
            $end = date('Y-m-d H:i:s',strtotime($end)+24*60*60);
            $goodsList = $goodsList->where('user_viporder.pay_time','<',$end);
        }

        //截止时间筛选
        if($request->get('jiezhi_start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('jiezhi_start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $goodsList = $goodsList->where('user_viporder.end_time','>',$start);

        }
        if($request->get('jiezhi_end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('jiezhi_end'));
            $end = date('Y-m-d H:i:s',strtotime($end)+24*60*60);
            $goodsList = $goodsList->where('user_viporder.end_time','<',$end);
        }

        //vip筛选
        if($request->get('level') && $request->get('level')!='0'){
            $goodsList = $goodsList->where('user_viporder.level','=',$request->get('level'));

        }

        $by = $request->get('by') ? $request->get('by') : 'user_viporder.id';
        $order = $request->get('order') ? $request->get('order') : 'DESC';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;

        $goodsList = $goodsList
            ->leftjoin("users",'users.id','=','user_viporder.uid')
            ->leftjoin("user_detail",'user_detail.uid','=','user_viporder.uid')
            ->select('user_viporder.*','users.name','users.mobile','user_detail.created_at as registertime','user_detail.balance','user_detail.auth_type')
            ->orderBy($by, $order)
            ->groupBy("user_viporder.uid","user_viporder.end_time")->paginate($paginate);
        foreach ($goodsList as $key => $value) {
            $goodsList[$key]['fPackage']=TaskModel::where("uid",$value['uid'])->count();
            $goodsList[$key]['jPackage']=WorkModel::where("uid",$value['uid'])->count();
            $goodsList[$key]['ZPackage']=WorkModel::where("uid",$value['uid'])->where('status','<>',0)->count();
            $goodsList[$key]['XJackage']=ProgrammeEnquiryMessageModel::where("uid",$value['uid'])->where('type','=',1)->count();
            $goodsList[$key]['caseNum']=goodsModel::where("uid",$value['uid'])->where('goods.is_delete',0)->count();
            $goodsList[$key]['usersum']=VipUserOrderModel::where("uid",$value['uid'])->where("type",'1')->count();
            $goodsList[$key]['caseSalesNum']=goodsModel::where("uid",$value['uid'])->where('goods.is_delete',0)->sum('sales_num');
        }
        $data = [
            'merge'      => $merge,
            'goods_list' => $goodsList,
        ];
        $this->theme->setTitle('会员报表');
        return $this->theme->scope('manage.huiYuanBaoBiao',$data)->render();
    }


}