<?php

namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Modules\Manage\Model\CateModel;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Task\Model\TaskAgentModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskPublishingModel;
use App\Modules\Task\Model\TaskServiceModel;
use App\Modules\Task\Model\TaskTypeModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\TaskModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Theme;

class PublishController extends ManageController
{  
   public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
        $this->theme->setTitle('任务列表');
        $this->theme->set('manageType', 'task');
    }
    //需求列表
    public function requireList(Request $request){
        $merge=$request->all();
        $taskPublish=TaskPublishingModel::leftJoin("users","task_publishing.uid","=","users.id")->whereIn("task_publishing.status",[1,2,3,4]);
        //搜索标题
        if($request->get('title')){
            $taskPublish=$taskPublish->where(function($query)use($request){
                $query->where("task_publishing.title","like","%".$request->get("title")."%")
                      ->orwhere("task_publishing.demand_number","like","%".$request->get("title")."%")
                      ->orwhere("task_publishing.nickname","like","%".$request->get("title")."%")
                      ->orwhere("users.name","like","%".$request->get("title")."%");
            });
        }
        //搜索状态
        if($request->get("status")){
            $taskPublish=$taskPublish->where("task_publishing.status",$request->get("status"));
        }
        //搜索发布时间
        if($request->get("start")){//开始时间
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $taskPublish=$taskPublish->where("task_publishing.created_at",">",$start);
        }
        if($request->get("end")){//结束时间
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d H:i:s',strtotime($end) +24*60*60);
            $taskPublish=$taskPublish->where("task_publishing.created_at","<",$end);
        }
        $taskPublish=$taskPublish->select("task_publishing.*","users.name as uname")->orderBy("task_publishing.id","desc")->paginate(10);
        $data=[
            'merge'=>$merge,
            'taskPublish'=>$taskPublish,
        ];
        return $this->theme->scope('manage.publish.requireList',$data)->render();
    }
    //需求详情
    public function requireDetail($id){
        $taskPublish=TaskPublishingModel::find($id);
        $taskPublish['appliy']=CateModel::where("id",$taskPublish['cate_id'])->pluck("name");
        $taskPublish['userName']=UserModel::where("id",$taskPublish['uid'])->pluck("name");
       // dd($taskPublish);
        $data=[
            'taskPublish'=>$taskPublish
        ];
        return $this->theme->scope('manage.publish.requireDetail',$data)->render();
    }
    //创建任务
    public function createTask($id){
        //查询应用领域等等分类
        //查询快捷任务信息
        $taskPublish=TaskPublishingModel::find($id);
        //应用领域
        $fieldArr=TaskCateModel::where("type",1)->get()->toArray();
        //技能标签
        $cateArr=TaskCateModel::where("type",2)->get()->toArray();
        //开放平台
        $openArr=TaskCateModel::where("type",3)->get()->toArray();
        //交易形式
        $transactionArr=TaskCateModel::where("type",4)->get()->toArray();
        //查询地区
        $province=DistrictModel::where("upid",0)->get();
        $city=DistrictModel::where("upid",$province[0]->id)->get();

        /*查询服务*/
        //$serviceAll=ServiceModel::getAll();
        /*获取生产代工信息*/
        //板子层数
        $platesNum=\CommonClass::platesNum();
        //板子厚度
        $plateThick=\CommonClass::plateThick();
        //铜厚
        $copperThickne=\CommonClass::copperThickne();
        //喷镀类型
        $platingType=\CommonClass::platingType();
        //阻焊颜色
        $solderColor=\CommonClass::solderColor();
        //字符颜色
        $characterColor=\CommonClass::characterColor();
        //交货周期
        $deliveryCycle=\CommonClass::deliveryCycle();
        //获取用户信息
        $userAll=UserModel::leftJoin("user_detail","users.id","=","user_detail.uid")
            ->select("users.id","users.name","users.email","user_detail.mobile","user_detail.qq","user_detail.wechat")->limit(10000)->get();
        //查询增值服务
        $service = ServiceModel::where('status',1)->where('type',1)->get()->toArray();
        $data=[
            'fieldArr'=>$fieldArr,
            'cateArr'=>$cateArr,
            'openArr'=>$openArr,
            'transactionArr'=>$transactionArr,
            'province'=>$province,
            'city'=>$city,
            'service'=>$service,
            'platesNum'=>$platesNum,
            'plateThick'=>$plateThick,
            'copperThickne'=>$copperThickne,
            'platingType'=>$platingType,
            'solderColor'=>$solderColor,
            'characterColor'=>$characterColor,
            'deliveryCycle'=>$deliveryCycle,
            'userAll'=>$userAll,
            'id'=>$id,
            'taskPublish'=>$taskPublish,
        ];
        return $this->theme->scope('manage.publish.createTask',$data)->render();
    }
    //根据需求创建任务
    public function createTaskData(Request $request){
        $taskPublis=TaskPublishingModel::find($request->get("require_id"));
        if(!$taskPublis){
            return redirect("/manage/requireList")->with(["message"=>"该需求已不存在"]);
        }
        $res=DB::transaction(function()use($request){
            //$taskType=TaskTypeModel::select('id')->where('alias',"kuaijie")->first();
            $taskData['title']=$request->get('title');
            $taskData['field_id']=$request->get('field_id');
            $taskData['cate_id']=$request->get('cate_id');
           // $taskData['transaction_id']=$request->get('transaction_id');
            $taskData['province']=$request->get('province');
            $taskData['city']=$request->get('city');
            $taskData['project_agent']=$request->get('project_agent');
            $taskData['bounty']=$request->get('bounty');
            $delivery_deadline = preg_replace('/([\x80-\xff]*)/i', '', $request->get('delivery_deadline'));
            $delivery_deadline = date('Y-m-d H:i:s',strtotime($delivery_deadline));
            $taskData['delivery_deadline']=$delivery_deadline;//截止时间
            //$taskData['open_id']=$request->get('open_id');//开放平台
            $taskData['is_open']=$request->get('is_open');//是否开放
            $taskData['is_free']=$request->get('is_free');//是否免费
            $taskData['desc']=$request->get('desc');
            $taskData['uid']=$request->get('uid');
            $taskData['created_at']=date("Y-m-d H:i:s");
            $taskData['type_id']=1;
            $taskData['status']=2;
            $taskData['username']=UserModel::where("id",$request->get('uid'))->pluck("name");
            $taskData['from_to']=2;
            $taskData['phone']=$request->get("mobile");
            $taskData['wechat']=$request->get("wechat");
            $taskData['qq']=$request->get("qq");
            $taskData['email']=$request->get("email");
            //$taskData['service']=$request->get('service')?implode($request->get('service'),','):'';
            //$taskData['numbers']=\CommonClass::createNum('yj',4);
            $taskId=TaskModel::insertGetId($taskData);
            if($request->get('project_agent') ==2){//查询是否需要生产代工
                $taskAgentData['plates_num']=$request->get('plates_num');
                $taskAgentData['pieces_num']=$request->get('pieces_num');
                $taskAgentData['length']=$request->get('length');
                $taskAgentData['width']=$request->get('width');
                $taskAgentData['veneers_num']=$request->get('veneers_num');
                $taskAgentData['plate_thickness']=$request->get('plate_thickness');
                $taskAgentData['copper_thickness']=$request->get('copper_thickness');
                $taskAgentData['spray_plating']=$request->get('spray_plating');
                $taskAgentData['soldering_color']=$request->get('soldering_color');
                $taskAgentData['character_color']=$request->get('character_color');
                $taskAgentData['is_connect']=$request->get('is_connect');
                $taskAgentData['delivery_cycle']=$request->get('delivery_cycle');
                $taskAgentData['task_id']=$taskId;
                $taskAgent=TaskAgentModel::where("task_id",$request->get('task_id'))->first();
                if($taskAgent){
                    TaskAgentModel::where("task_id",$taskId)->update($taskAgentData);
                }else{
                    TaskAgentModel::insert($taskAgentData);
                }
            }
            //修改增值服务
//            TaskServiceModel::where("task_id",$taskId)->delete();
//            if($request->get('service')){
//                foreach($request->get('service') as $key=>$val){
//                    TaskServiceModel::insert([
//                        'task_id'=>$taskId,
//                        'service_id'=>$val,
//                        'created_at'=>date("Y-m-d H:i:s"),
//                    ]);
//                }
//            }
            //更改需求状态
            TaskPublishingModel::where("id",$request->get("require_id"))->update(["status"=>4,'task_id'=>$taskId,"auth_time"=>date('Y-m-d H:i:s')]);

            /*
           * 附件处理
           * */
            $attachment=$request->file("attachment");
            if($attachment){
                $updateFile=\FileClass::uploadFile($attachment,'task');
                $attachmentId=AttachmentModel::insertGetId([
                    "name"=>json_decode($updateFile)->data->name,
                    "type"=>json_decode($updateFile)->data->type,
                    "size"=>json_decode($updateFile)->data->size,
                    "url"=>json_decode($updateFile)->data->url,
                    "disk"=>json_decode($updateFile)->data->disk,
                ]);
                TaskAttachmentModel::insert([
                    'task_id'=>$taskId,
                    'attachment_id'=>$attachmentId,
                    'created_at'=>date('Y-m-d H:i:s')
                ]);
            }
        });
        if (!is_null($res)){
            return redirect()->back()->with(['error' => '更新失败！']);
        }
        return redirect("/manage/requireList")->with(['massage' => '更新成功！']);
    }
    //需求拒绝
    public function refuse($id,Request $request){
        $taskPublish=TaskPublishingModel::find($id);
        if(!$taskPublish){
            return redirect("/manage/requireList")->with(["message"=>"该需求不存在"]);
        }
        $taskPublish = $taskPublish->update([
              "status"      => 3,
              "reason"      => $request->get('reason') ? $request->get('reason') : '',
              "reasoname"      => $request->get('reasoname') ? $request->get('reasoname') : '',
              "updated_at"  => date("Y-m-d H:i:s"),
              "auth_time"   => date("Y-m-d H:i:s")
        ]);
        return back()->with(["message"=>"操作成功"]);
    }
    //需求删除
    public function delete($id){
        $taskPublish=TaskPublishingModel::find($id);
        if(!$taskPublish){
            return redirect("/manage/requireList")->with(["message"=>"该需求不存在"]);
        }
        $taskPublish=$taskPublish->update([
            "status"=>5,
            "updated_at"=>date("Y-m-d H:i:s")
        ]);
        return back()->with(["message"=>"删除成功"]);
    }

}
