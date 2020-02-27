<?php
namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\UserCenterController as BasicUserCenterController;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Model\TaskInviteModel;
use App\Modules\Task\Model\TaskPublishingModel;
use App\Modules\Task\Model\TaskTypeModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\TaskModel;
use App\Modules\User\Model\UserModel;
use Auth;
use Illuminate\Http\Request;
use Gregwar\Image\Image;
use Illuminate\Support\Facades\Session;
use Theme;

class TaskController extends BasicUserCenterController
{

    public function __construct()
    {
        parent::__construct();
        $this->user ="";
        //查看该用户是否开启的店铺
        if(Auth::check()){
            $this->user = Auth::user();
            $shop=ShopModel::where("uid",Auth::user()->id)->where("status",1)->first();
            $this->theme->set("shop_open",false);
            $this->theme->set("shop_com",false);
            if($shop){
                $this->theme->set("shop_open",true);
				$this->theme->set("shopInfo",$shop);
                if($shop['type'] ==2){
                    $this->theme->set("shop_com",true);
                }
            }
            $this->initTheme('accepttask');//主题初始化
        }
    }

    /**
     * 我发布的任务
     * @param Request $request
     * @return mixed
     */
    public function releaseTask(Request $request){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","employer");
        $this->theme->setTitle('我的发包');
        $this->theme->set("employer",1);
        $this->theme->set("userOneColumn","我是雇主");
        $this->theme->set("userOneColumnUrl","/user/releaseTask");
        $this->theme->set("userSecondColumn","我的发包");
        $this->theme->set("userSecondColumnUrl","/user/releaseTask");
        $merge=[
            'taskType'=>$request->get("taskType")?$request->get("taskType"):1,
            'status'=>$request->get("status")?$request->get("status"):98,
            'keyWord'=>$request->get("keyWord")?$request->get("keyWord"):'',
        ];
        if($request->get("taskType")){
            $taskType=$request->get("taskType");
        }else{
            $taskType=TaskTypeModel::where("alias","jingbiao")->pluck("id");
        }
        //任务的统计
        $taskCount['jb']=TaskModel::where("uid",Auth::user()->id)->where("type_id",1)->count();
        $taskCount['yj']=TaskPublishingModel::where("uid",Auth::user()->id)->count();
        $taskCount['gy']=TaskModel::where("uid",Auth::user()->id)->where("type_id",3)->count();
        $status = [];
        $employTaskIdArr = [];
        switch($taskType){
            case 1:
                $status=[
                    0 => "未发布",
                    1 => "待审核",
                    2 => "竞标中",
                    3 => "审核不通过",
                    4 => "已选中",
                    5 => "已托管",
                    6 => "待验收",
                    7 => "互评",
                    8 => "已完成",
                    9 => "维权中",
                    10=> "已关闭",
                    11=> "已结束",
                ];
                break;
            case 2:
                $status=[
                    1 => "待审核",
                    3 => "拒绝"
                ];
                break;
            case 3:
                $status = [
                    1   => "待审核",
                    2   => "响应中",
                    12  => "待确认",
                    3   => "审核不通过",
                    4   => "已选中",
                    5   => "已托管",
                    6   => "待验收",
                    7   => "双方互评",
                    8   => "已完成",
                    9   => "维权中",
                    10  => "已关闭",
                    11  => "已结束",
                ];
                break;
        }
        if($request->get("taskType") == 2){
            $task = TaskPublishingModel::leftJoin("cate","task_publishing.cate_id","=","cate.id")->where("uid",Auth::user()->id);
            if($request->get("status") && $request->get("status")!=98){//搜索状态
                $task=$task->where("task_publishing.status",$request->get("status"));
            }
            if($request->get("keyWord")){
                $task=$task->where("task_publishing.title","like","%".$request->get("keyWord")."%");
            }
            $task=$task->select("task_publishing.*","cate.name")->orderBy("task_publishing.id","desc")->paginate(10);
        }elseif($request->get("taskType") == 3){
            //我发布的雇佣id
            $employTaskId = EmployModel::where("employer_uid",Auth::user()->id)->lists('task_id')->toArray();
            //待确认的雇佣id
            $employTaskIdArr = WorkModel::whereIn('task_id',$employTaskId)->where('status',0)->lists('task_id')->toArray();
             $task= EmployModel::where("employer_uid",Auth::user()->id)->join("task","employ.task_id","=","task.id")->where("task.type_id",$taskType);
            if($request->get("status") && $request->get("status")!=98){//搜索状态
                $data['status']=$request->get("status");
                if($request->get("status") == 99){
                    $data['status']=0;
                }elseif($request->get("status") == 12){

                    $task = $task->where("task.status",2)->whereIn('task.id',$employTaskIdArr);
                }elseif($request->get("status") == 2){
                    $task = $task->where("task.status",2)->whereNotIn('task.id',$employTaskIdArr);
                }else{
                    $task=$task->where("task.status",$data['status']);
                }

            }
            if($request->get("keyWord")){
                $task=$task->where("task.title","like","%".$request->get("keyWord")."%");
            }
            $task=$task->select("task.*")->orderBy("employ.id","desc")->paginate(10);
        }else{
            $task=TaskModel::where("uid",Auth::user()->id)->where("type_id",$taskType);
            if($request->get("status") && $request->get("status")!= 98){//搜索状态
                $data['status']=$request->get("status");
                if($request->get("status") == 99){
                    $data['status']=0;
                }
                $task=$task->where("status",$data['status']);
            }
            if($request->get("keyWord")){
                $task=$task->where("title","like","%".$request->get("keyWord")."%");
            }
            $task=$task->orderBy("id","desc")->paginate(10);
        }
        $view   = [
            'task'            =>  $task,
            'merge'           => $merge,
            'status'          => $status,
            'taskCount'       => $taskCount,
            'employTaskIdArr' => $employTaskIdArr
        ];
        return $this->theme->scope('user.task.releaseTask', $view)->render();
    }

    /**
     * 任务状态处理
     * @param $id
     * @param $action
     * @return \Illuminate\Http\RedirectResponse
     */
    public function releaseTaskHandle($id,$action)
    {
        $task=TaskModel::where("uid",Auth::user()->id)->where("id",$id)->first();
        if(!$task){
            return back()->with(["message"=>"该任务不存在"]);
        }
        switch($action){
            case "release":
                $res=TaskModel::where("uid",Auth::user()->id)->where("id",$id)->update(["status"=>1]);
                break;
            case "del":
                $res=TaskModel::where("uid",Auth::user()->id)->where("id",$id)->delete();
                break;
        }
        if(isset($res) && $res){
            return back()->with(["message"=>"操作成功"]);
        }
        return back()->with(["message"=>"操作失败"]);
    }

    /**
     * 我是服务商 我参加的任务
     * @param Request $request
     * @return mixed
     */
    public function myjointask(Request $request)
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle("我参加的任务");
        
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/myjointask");
        $this->theme->set("userSecondColumn","我的竞标");
        $this->theme->set("userSecondColumnUrl","/user/myjointask");
        $merge= [
            'taskType' => $request->get("taskType")?$request->get("taskType"):1,
            'status'   => $request->get("status")?$request->get("status"):0,
            'keyWord'  => $request->get("keyWord")?$request->get("keyWord"):'',
        ];
        if($merge['taskType'] == '3'){
            $this->theme->set("userShop","10");
        }else{
            $this->theme->set("userShop","9");
        }
        if($request->get("taskType")){
            $taskType = $request->get("taskType");
        }else{
            $taskType = TaskTypeModel::where("alias","jingbiao")->pluck("id");
        }
        //获取所有一键发布的任务id
        $taskIdAll = TaskPublishingModel::where("status",4)->lists("task_id")->toArray();

        $taskIdArr = WorkModel::where("work.uid",Auth::user()->id)->lists('task_id')->toArray();
        $taskGyArr=  EmployModel::leftJoin("task","employ.task_id","=","task.id")->where("employ.employee_uid",Auth::user()->id)->where("task.status",">",1)->lists("task.id")->toArray();
        //任务的统计
        $taskCount['jb']= TaskModel::whereIn('id',$taskIdArr)->where("task.type_id",1)->count();
        $taskCount['gy']= TaskModel::whereIn('id',$taskGyArr)->where("task.type_id",3)->count();
        $status = [];
        $taskIdArr1 = [];
        switch($taskType){
            case 1:
            case 2:
                $status=[
                   // 0=>"未发布",
                   // 1=>"待审核",
                    2   => "竞标中",
                   // 3=>"审核不通过",
                    4   => "已选中",
                    5   => "已托管",
                    6   => "待验收",
                    7   => "互评",
                    8   => "已完成",
                    9   => "维权中",
                    10  => "已关闭",
                    11  => "已结束",
                ];
                break;
            case 3:
                $status=[
                  //  0=>"待审核",
                  //  1=>"审核不通过",
                    2   => "响应中",
                    12  => "待确认",
                    // 3=>"审核不通过",
                    4   => "已选中",
                    5   => "已托管",
                    6   => "待验收",
                    7   => "双方互评",
                    8   => "已完成",
                    9   => "维权中",
                    10  => "已关闭",
                    11  => "已结束",
                ];
                break;
        }
        if($taskType == 3){
            $task = TaskModel::whereIn('id',$taskGyArr);
            $taskIdArr1 = WorkModel::whereIn('task_id',$taskGyArr)->where('status',0)->where('uid',Auth::id())->lists('task_id')->toArray();
        }else{
            $task = TaskModel::whereIn('id',$taskIdArr);
        }

        if($request->get("status")){//搜索状态
            if($taskType == 3){
                if($request->get('status') == 12){
                    $task = $task->whereIn('task.id',$taskIdArr1)->where("task.status",2);
                }elseif($request->get('status') == 2){
                    $task = $task->whereNotIn('task.id',$taskIdArr1)->where("task.status",2);
                }else{
                    $task = $task->where("task.status",$request->get("status"));
                }
            }else{
                $task = $task->where("task.status",$request->get("status"));
            }

        }
        if($request->get("keyWord")){
            $task = $task->where("task.title","like","%".$request->get("keyWord")."%");
        }
        if($merge['taskType']){
            switch($merge['taskType']){
                case 1:
                    $task = $task->where("task.type_id",1);
                    break;
                case 2:
                    $task = $task->whereIn('id',$taskIdAll);
                    break;
                case 3:
                    $task = $task->where("task.type_id",3);
                    break;
            }
        }
        $task = $task->orderBy("id","desc")->distinct("task.id")->paginate(10);

        $data=[
            'task'          => $task,
            'merge'         => $merge,
            'status'        => $status,
            'taskCount'     => $taskCount,
            'taskIdArr1'    => $taskIdArr1
        ];
        return $this->theme->scope('user.task.myjointask', $data)->render();
    }

    //平台派单
    public function pushMyTask(Request $request){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle("平台派单");
        $this->theme->set("userShop","8");
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/myjointask");
        $this->theme->set("userSecondColumn","平台派单");
        $this->theme->set("userSecondColumnUrl","/user/pushMyTask");
        $merge=[
            'taskType'=>$request->get("taskType")?$request->get("taskType"):1,
            'status'=>$request->get("status")?$request->get("status"):98,
            'keyWord'=>$request->get("keyWord")?$request->get("keyWord"):'',
        ];
        if($request->get("taskType")){
            $taskType=$request->get("taskType");
        }else{
            $taskType=TaskTypeModel::where("alias","jingbiao")->pluck("id");
        }

       //获取所有推送的任务id
        $taskId=TaskInviteModel::where("uid",Auth::user()->id)->distinct()->lists("task_id")->toArray();
        //任务的统计
        $taskCount['jb']=count($taskId);
        //$taskCount['yj']=WorkModel::leftJoin("task","work.task_id","=","task.id")->where("work.status",">",0)->where("work.is_push",1)->where("work.uid",Auth::user()->id)->where("task.type_id",2)->count();
       // $taskCount['gy']=WorkModel::leftJoin("task","work.task_id","=","task.id")->where("work.status",">",0)->where("work.is_push",1)->where("work.uid",Auth::user()->id)->where("task.type_id",3)->count();
        switch($taskType){
            case 1:
            case 2:
                $status=[
                    0=>"未发布",
                    1=>"待审核",
                    2=>"竞标中",
                    3=>"审核不通过",
                    4=>"待托管",
                    5=>"工作中",
                    6=>"待验收",
                    7=>"互评",
                    8=>"已完成",
                    9=>"维权中",
                    10=>"已关闭",
                    11=>"维权成功",
                ];
                break;
            case 3:
                $status=[
                    0=>"待审核",
                    1=>"审核不通过",
                    2=>"响应中",
                    3=>"待托管",
                    4=>"工作中",
                    5=>"待验收",
                    6=>"双方互评",
                    7=>"已完成",
                    8=>"维权中",
                    9=>"已关闭",
                    10=>"失败",
                ];
                break;
        }
        $task=TaskModel::whereIn('id',$taskId);
        //$task=WorkModel::select("task.*")->leftJoin("task","work.task_id","=","task.id")->where("work.status",">",0)->where("work.is_push",1)->where("work.uid",Auth::user()->id)->where("task.type_id",$taskType);
        if($request->get("status")){//搜索状态
            $data['status']=$request->get("status");
            if($request->get("status") ==99){
                $data['status']=0;
            }
            $task=$task->where("status",$data['status']);
        }
        if($request->get("keyWord")){
            $task=$task->where("title","like","%".$request->get("keyWord")."%");
        }
        $task=$task->orderBy("id","desc")->paginate(10);
        $data=[
            'task'=>$task,
            'merge'=>$merge,
            'status'=>$status,
            'taskCount'=>$taskCount
        ];
        return $this->theme->scope('user.task.pushMyTask', $data)->render();
    }
}
