<?php
namespace App\Modules\Manage\Http\Controllers;
use App\Http\Controllers\BasicController;
use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Modules\Manage\Http\Requests\AgreementRequest;
use App\Modules\Activity\Model\ActivityDrawListModel;
use App\Modules\Activity\Model\ActivityInforMationModel;
use App\Modules\Activity\Model\ActivityListModel;
use App\Modules\Manage\Model\ActivityModel;
use App\Modules\Manage\Model\AgreementModel;
use App\Modules\Manage\Model\ConfigModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
class ActivityController extends ManageController
{
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
    }

    /**
     * 活动列表
     * @param Request $request
     * @return mixed
     */
    public function activityList(Request $request)
    {
        $list = ActivityModel::whereRaw('1=1');
        if ($request->get('keywords')) {
            $keywords = trim($request->get('keywords'));
            $list = $list->where(function ($list) use ($keywords) {
                $list = $list->where('id', $keywords)->orWhere('title', 'like', '%' . $keywords . '%')->orWhere('username', 'like', '%' . $keywords . '%');
            });
        }
        if ($request->get('status') != -1 && $request->get('status') != null) {
            $list = $list->where('status', $request->get('status'));
        }
        //.活动区分
        if ($request->get('type') && $request->get('type') != -1) {
            $list = $list->where('type', $request->get('type'));
        }
        //时间
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00', strtotime($start));
            $list = $list->where('stoptime', '>=', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59', strtotime($end));
            $list = $list->where('stoptime', '<=', $end);
        }

        $by = $request->get('by') ? $request->get('by') : 'id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;
        $list = $list->orderBy($by, $order)->paginate($paginate);
        $type=[
            '1' => '最新活动',
            '2' => '通知公告',
        ];
        $data = array(
            'merge' => $request->all(),
            'list' => $list,
            'type' => $type,
        );
        $this->theme->setTitle('活动管理');
        return $this->theme->scope('manage.activity.list', $data)->render();
    }

    /**
     * 添加活动视图
     * @param Request $request
     * @return mixed
     */
    public function addActivity(Request $request)
    {
        $data = array();
        $this->theme->setTitle('添加活动');
        return $this->theme->scope('manage.activity.add', $data)->render();
    }

    /**
     * 添加活动
     * @param Request $request
     * @return mixed
     */
    public function postAddActivity(Request $request)
    {
        $data = $request->all();
        if(empty($data['title']) || empty($data['url'])){
            return redirect('/manage/addActivity')->with(array('message' => '请小主填写标题或者链接!'));
        }
        $arr = array(
            'title' => isset($data['title'])?$data['title']:'',
            'url' => isset($data['url'])?$data['url']:'',
            'username' => isset($data['username'])?$data['username']:'',
            'desc'=> isset($data['desc'])?$data['desc']:'',
            'pub_at' => date('Y-m-d H:i:s', time()),
            'created_at' => date('Y-m-d H:i:s', time()),
            'updated_at' => date('Y-m-d H:i:s', time()),
            'status' => isset($data['status'])?$data['status']:1,
            'type' => isset($data['type'])?$data['type']:1,
            'stoptime' => isset($data['stoptime'])?date('Y-m-d H:i:s', strtotime($data['stoptime'])):date('Y-m-d H:i:s', time()),
        );
        $file1 = $request->file('pic');
        if ($file1) {
            //上传文件
            $result1 = \FileClass::uploadFile($file1, 'sys');
            $result1 = json_decode($result1, true);
            $arr['pic'] = $result1['data']['url'];
        }
        if(isset($data['id']) && !empty($data['id'])){
            $res = ActivityModel::where('id',$data['id'])->update($arr);
        }else{
            $res = ActivityModel::create($arr);
        }
        if ($res) {
            return redirect('/manage/activity')->with(array('message' => '操作成功'));
        } else {
            return redirect('/manage/activity')->with(array('message' => '操作失败'));
        }
    }


    /**
     * 编辑活动视图
     * @param Request $request
     * @param $id 协议编号
     * @return mixed
     */
    public function editActivity(Request $request, $id)
    {
        $id = intval($id);
        $info = ActivityModel::where('id', $id)->first();
        $data = array(
            'info' => $info
        );
        $this->theme->setTitle('编辑活动');
        return $this->theme->scope('manage.activity.edit', $data)->render();
    }


    /**
     * IC检测报告列表
     * @param Request $request
     * @return mixed
     */
    public function special2List(Request $request){
        //获取数据
        $list=ActivityInforMationModel::select()->where('id','>',0)->paginate(5);
        if($list){
            $data = array(
                'list' => $list
            );
            $this->theme->setTitle('IC检测报告列表');
            return $this->theme->scope('manage.special2.list', $data)->render();
        }
    }



    /**
     * 活动列表
     * @param Request $request
     * @return mixed
     */
    public function activitylists(Request $request)
    {
        // 获取所有数据
        $lists = ActivityListModel::leftJoin('users','users.id','=','activity_list.uid')->where('activity_list.id', '>', 0)->select('users.name as username','users.id as ids','activity_list.*');
        //存type用于区分活动
        $arrtype1=[3,4,5,7,8,9,10];
        $arrtype2=[15,16,17,18,19,20];
        $arrtype3=[21,22,23];
        //活动标题搜索
        if ($request->get('activitytitle')) {
            $activitytitle = trim($request->get('activitytitle'));
            //判断是哪种活动
            if($activitytitle=="唯样活动"){
                $lists = $lists->where('activity_list.type', '1');
            }elseif($activitytitle=="成功案例页面下载第一版"){
                $lists = $lists->where('activity_list.type', '2');
            }elseif($activitytitle=="Silicon Labs白皮书名单"){
                $lists = $lists->wherein('activity_list.type',$arrtype1);
            }elseif($activitytitle=="工控页面成功案例下载"){
                $lists = $lists->where('activity_list.type', '6');
            }elseif($activitytitle=="世键白皮书名单"){
                $lists = $lists->wherein('activity_list.type',$arrtype2);
            }elseif($activitytitle=="NI白皮书名单"){
                $lists = $lists->wherein('activity_list.type',$arrtype3);
            }elseif($activitytitle=="成功案例页面下载第二版"){
                $lists = $lists->where('activity_list.type','24');
            }elseif($activitytitle=="成功案例页面下载第三版"){
                $lists = $lists->where('activity_list.type','25');
            }elseif($activitytitle=="国体智慧开放实验室"){
                $lists = $lists->where('activity_list.type','26');
            }elseif($activitytitle=="百万扶持计划"){
                $lists = $lists->where('activity_list.type','27');
            }
        }
        //用户名搜索
        if ($request->get('name')) {
            $name = $request->get('name');
            $lists = $lists->where(function ($query) use ($name) {
                $query = $query->Where('users.name', 'like', '%' . $name . '%');
             });
          }
        //手机号搜索
        if ($request->get('mobile')) {
            $mobile = $request->get('mobile');
            $lists = $lists->where(function ($query) use ($mobile) {
                $query = $query->Where('activity_list.mobile', 'like', '%' . $mobile . '%');
            });
        }
        //时间搜索
        if ($request->get('start')) {
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d 00:00:00', strtotime($start));
            $lists = $lists->where('activity_list.created_at', '>=', $start);
        }
        if ($request->get('end')) {
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d 23:59:59', strtotime($end));
            $lists = $lists->where('activity_list.created_at', '<=', $end);
        }
        //来源搜索
        if ($request->get('source')) {
            $lists = $lists->where('activity_list.source', $request->get('source'));
        }

        //排序分页
        $lists = $lists->orderby('activity_list.id','desc')->paginate(15);
        //放奖品
        $prizearr1= array(1=>'移动电源',2=>'竞标卡',3=>'书刊',4=>'现金劵');
        $prizearr2 = array(1=>'Thunderboard™ Sense 开发软件',2=>'50元竞标卡',3=>'200元竞标卡',4=>'500元黄金卡');
        $prizearr3 = array(1=>'手持小风扇',2=>'50元竞标卡',3=>'移动电源',4=>'苹果数据线');
        //判断是否中奖
       foreach($lists as $k=>$v){
               if(in_array($v['type'],$arrtype1)){
                   $prize_id=ActivityDrawListModel::where("uid",$v['uid'])->where("type",'2')->pluck("prize_id");
                   if($prize_id){
                       $lists[$k]['prize_id'] = $prize_id;
                       $lists[$k]['types']  = ActivityDrawListModel::where("uid",$v['uid'])->where("type",'2')->pluck("type");
                       $arrprize_id=[1,2,3,4];
                       if(in_array($prize_id,$arrprize_id)){
                           $lists[$k]['winprize']  = 1;
                       }
                   }else{
                       $lists[$k]['winprize']  = 2;
                   }
               }elseif(in_array($v['type'],$arrtype2)){
                   $prize_id=ActivityDrawListModel::where("uid",$v['uid'])->where("type",'1')->pluck("prize_id");
                   if($prize_id){
                       $lists[$k]['prize_id'] = $prize_id;
                       $lists[$k]['types']  = ActivityDrawListModel::where("uid",$v['uid'])->where("type",'1')->pluck("type");
                       $arrprize_id=[1,2,3,4];
                       if(in_array($prize_id,$arrprize_id)){
                           $lists[$k]['winprize']  = 1;
                       }
                   }else{
                       $lists[$k]['winprize']  = 1;
                   }
               }elseif(in_array($v['type'],$arrtype3)){
                   $prize_id=ActivityDrawListModel::where("uid",$v['uid'])->where("type",'3')->pluck("prize_id");
                   if($prize_id){
                       $lists[$k]['prize_id'] = $prize_id;
                       $lists[$k]['types']  = ActivityDrawListModel::where("uid",$v['uid'])->where("type",'3')->pluck("type");
                       $arrprize_id=[1,2,3,4];
                       if(in_array($prize_id,$arrprize_id)){
                           $lists[$k]['winprize']  = 1;
                       }
                   }else{
                       $lists[$k]['winprize']  = 2;
                   }
               }else{
                   $lists[$k]['prize_id'] = "";
                   $lists[$k]['types']  = "";
                   $lists[$k]['winprize']  = 2;
               }
           }
            $data = array(
                'lists' => $lists,
                'merge' => $request->all(),
                'arrtype1'=>$arrtype1,
                'arrtype2'=>$arrtype2,
                'arrtype3'=>$arrtype3,
                'prizearr1'=>$prizearr1,
                'prizearr2'=>$prizearr2,
                'prizearr3'=>$prizearr3
            );
        $this->theme->setTitle('活动列表');
            return $this->theme->scope('manage.activitylists', $data)->render();
    }

    /*世键抽奖*/
    public function lottery(Request $request){
        $this->theme->setTitle('世键抽奖');
        $merge = $request->all();
        $list = ActivityDrawListModel::whereRaw(" 1=1")
            ->leftJoin('users','users.id','=','activity_drawlist.uid')
            ->select("activity_drawlist.*","users.name");
        $paginate = $request->get('paginate') ? $request->get('paginate') : 50;
        $list = $list->paginate($paginate);
        //礼品
        $prize  = array('1' =>'二合一移动电源','2' =>'200元竞标卡','3' =>'《新概念模拟电路》书刊','4' =>'青铜会员400元现金券','5' =>'谢谢参与');
        $data = [
            'list'=>$list,
            'merge'=>$merge,
            'prize'=>$prize
        ];
        return $this->theme->scope('manage.activity.lottery', $data)->render();
    }

}

