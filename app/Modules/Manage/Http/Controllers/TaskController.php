<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\CateModel;
use App\Modules\Manage\Model\SeoModel;
use App\Modules\Manage\Model\SeoRelatedModel;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Manage\Model\VipModel;
use App\Modules\Pay\OrderModel;
use App\Modules\Shop\Models\ShopTagsModel;
use App\Modules\Task\Model\SuccessCaseModel;
use App\Modules\Task\Model\TaskAgentModel;
use App\Modules\Task\Model\TaskAttachmentModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskFollowModel;
use App\Modules\Task\Model\TaskInviteModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskPaySectionModel;
use App\Modules\Task\Model\TaskPayTypeModel;
use App\Modules\Task\Model\TaskServiceModel;
use App\Modules\Task\Model\TaskTypeModel;
use App\Modules\Task\Model\WorkCommentModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\CommentModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\EnterpriseAuthModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Theme;
use Excel;

class TaskController extends ManageController
{
    public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
        $this->theme->setTitle('任务列表');
        $this->theme->set('manageType', 'task');
    }

    /**
     * 任务列表
     *
     * @param Request $request
     * @return mixed
     */
    public function taskList(Request $request)
    {
        $merge = $request->all();
        $by = $request->get('by') ? $request->get('by') : 'created_at';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;
        $taskType = TaskTypeModel::select('id')->where('alias',"jingbiao")->first();
        $taskList = TaskModel::leftJoin('users','users.id','=','task.uid')->select('task.id','task.uid','task.numbers', /*'us.nickname as name',*/'task.phone', 'task.title', 'task.created_at', 'task.status', 'task.verified_at', 'task.bounty','task.bounty_status','task.is_recommend','task.delivery_deadline','task.delivery_count','task.is_open','task.is_fast','task.is_top','task.is_car','task.level','task.is_set_success','task.is_support','users.mobile')
            ->where('task.type_id',$taskType['id'])->where('task.is_del',0);
        //关键字搜索
        if ($request->get('task_title')) {
            $taskList = $taskList->where(function($query)use($request,$merge){
                $query->where("task.title","like","%".e($request->get('task_title'))."%")
                    ->orWhere("task.numbers","like","%".e($request->get('task_title'))."%");

            });
        }

        if(isset($merge['username']) && $merge['username']){
            $taskList = $taskList->whereHas('user' , function($q) use ($merge){
                $q->where('name','like','%'.trim($merge['username']).'%');
            });
        }

        // if(isset($merge['uname']) && $merge['uname']){
        //     $taskList = $taskList->whereHas('follow' , function($q) use ($merge){
        //         $q->where('uname','=',trim($merge['uname']));
        //     });
        // }

        //状态筛选
        if ($request->get('status') && $request->get('status') != 0) {
            $taskList = $taskList->where('task.status', $request->get('status'));
        }
        //时间筛选
        if ($request->get('time_type')) {
            if ($request->get('start')) {
                $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
                $start = date('Y-m-d H:i:s',strtotime($start));
                $taskList = $taskList->where($request->get('time_type'), '>', $start);
            }
            if ($request->get('end')) {
                $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
                $end = date('Y-m-d H:i:s',strtotime($end) + 24*60*60);
                $taskList = $taskList->where($request->get('time_type'), '<', $end);
            }

        }
        if(isset($merge['task_type']) ){
            switch($merge['task_type']){
                case 'is_top':
                    $taskList = $taskList->where('task.is_top',1);
                    break;
                case 'is_fast':
                    $taskList = $taskList->where('task.is_fast',1);
                    break;
                case 'is_car':
                    $taskList = $taskList->where('task.is_car',1);
                    break;
                case 'is_open':
                    $taskList = $taskList->where('task.is_open',2);
                    break;
            }
        }
        if(isset($merge['level']) && $merge['level'] > 0){
            $taskList = $taskList->where('task.level',$merge['level']);
        }
        //.项目扶持搜索
        if ($request->get('is_support') && $request->get('is_support') != 0) {
            $taskList = $taskList->where('task.is_support', $request->get('is_support'));
        }
        $taskList = $taskList->orderBy($by, $order)->paginate($paginate);
        // $taskList->load(['follow','userinfo']);
        $taskList->load(['follow' => function($q) use ($merge){
            /*if(isset($merge['uname']) && $merge['uname']){
                $q = $q->where('uname','like',"%".trim($merge['uname']."%"));
            }*/
            $q->orderBy('follow_time','desc');
        },'userinfo','user']);
        if(!empty($taskList->toArray()['data'])){
            $taskIdArr = array_pluck($taskList->toArray()['data'],'id');
            $service = TaskServiceModel::whereIn('task_id',$taskIdArr)->get()->toArray();
            $service = \CommonClass::setArrayKey($service,'task_id');
            $order = OrderModel::where('code','like','ts'.'%')->whereIn('task_id',$taskIdArr)->get()->toArray();
            $order = \CommonClass::setArrayKey($order,'task_id');
            foreach ($taskList as $key=>$val){
                if(!in_array($val['id'],array_keys($service))){
                    $taskList[$key]['servicePay']="未购买";
                }else{
                    if(in_array($val['id'],array_keys($order)) && $order[$val['id']]['status'] == 1){
                        $taskList[$key]['servicePay'] = "已支付";
                    }else{
                        $taskList[$key]['servicePay'] = "未支付";
                    }

                }
            }
        }
        //获取行业标签
        $industry = CateModel::where("type",1)->select("id","name")->get();
        //获取技能标签
        $skill = CateModel::where("type",2)->select("id","name")->get();
        //获取用户列表
        $userList = [];//UserModel::select("id","name")->get();

        //查询地区
        $province=DistrictModel::where("upid",0)->get();
        $city=DistrictModel::where("upid",$province[0]->id)->get();
        // dd($province);
        $authtype = [
                        '1'=>'个人',
                        '2'=>'企业'
                    ];

        //获取所有的seo 标签
        $seoList = SeoModel::all();
        //获取会员等级
        $vipGrade = VipModel::where("status",2)->select("name","id","grade")->get();
        if(isset($taskList) && !empty($taskList)){
            foreach ($taskList as $k=>$v){
                $uid=$v->uid;
                $taskList[$k]['countnums']=TaskModel::where('uid','=',$uid)->where('is_del','=',0)->select('id')->count();
            }
        }
        $data = array(
            'task'      => $taskList,
            'industry'  => $industry,
            'skill'     => $skill,
            'userList'  => $userList,
            'seoList'   => $seoList,
            'vipGrade'  => $vipGrade,
            'authtype'  => $authtype,
            'province'  => $province,
            'city'  => $city,
        );
        $data['merge'] = $merge;
        return $this->theme->scope('manage.tasklist', $data)->render();
    }
    /*
     * 任务推送
     * */
    public function taskPush(Request $request){
        // dd($request->all());
        $taskId = $request->get('task_id');
        $task = TaskModel::find($taskId);
        if(!$task ||  $task['status'] != 2){
            return back()->with(["message"=>"不能推送"]);
        }

        if($request->get("industry")){
            //获取店铺id
            $shopId=ShopTagsModel::whereIn("cate_id",[$request->get("industry"),$request->get("skill")])->whereIn("type",[1,2])->lists("shop_id")->toArray();
            //获取用户id
            $userId=UserModel::leftJoin('shop','users.id','=','shop.uid')
                ->where('users.level',$request->get('level'))->where("users.id",'!=',$task->uid)
                ->whereIn("shop.id",$shopId)->lists("users.id")->toArray();


        }else{
            if(!$request->get("uid")){
                return back()->with(["message"=>"请选择推送用户"]);
            }
            $userId=[$request->get("uid")];
        }

        foreach($userId as $v){
            $taskInvite=TaskInviteModel::where("task_id",$taskId)->where("uid",$v)->first();
            if($taskInvite){
                continue;
            }
            $res = TaskInviteModel::create([
                'task_id' => $taskId,
                'uid'     => $v
            ]);
            if($res){
                //发送邀请信息
                TaskInviteModel::sendInviteMsg($task,$v);
            }
        }
        return back()->with(["message"=>"操作成功"]);
    }
    //添加竞标任务
    public function taskAdd(){
        //查询应用领域等等分类
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
        // $userAll=UserModel::leftJoin("user_detail","users.id","=","user_detail.uid")
        //                     ->select("users.id","users.name","users.email","user_detail.mobile","user_detail.qq","user_detail.wechat")->get();
        $userAll = [];
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
        ];
        return $this->theme->scope('manage.taskAdd',$data)->render();
    }
    //任务添加数据提交
    public function taskAddData(Request $request){
        $res=DB::transaction(function()use($request){
            $taskType=TaskTypeModel::select('id')->where('alias',"jingbiao")->first();
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
            $taskData['type_id']=$taskType['id'];
            $taskData['status']=2;
            $taskData['username']=UserModel::where("id",$request->get('uid'))->pluck("name");
            $taskData['from_to']=2;
            $taskData['phone']=$request->get("mobile");
            $taskData['wechat']=$request->get("wechat");
            $taskData['qq']=$request->get("qq");
            $taskData['email']=$request->get("email");

            // $taskData['numbers']=\CommonClass::createNum("xm",4);
            // dd($taskData);
            //$taskData['service']=$request->get('service')?implode($request->get('service'),','):'';
            $taskId = TaskModel::insertGetId($taskData);
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
//            //修改增值服务
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
            /*
            * 附件处理
            * */
            if($request->get("file_ids")){
                $attachIdList=[];
                foreach($request->get("file_ids") as $fik=>$fiv){
                    $attachIdList[$fik]['task_id']=$taskId;
                    $attachIdList[$fik]['attachment_id']=$fiv;
                    $attachIdList[$fik]['created_at']=date('Y-m-d H:i:s');

                }
                TaskAttachmentModel::insert($attachIdList);
            }
            //.内部人员在后台添加项目时,对应雇主的发包数量增加1
            if (!empty($request->get('uid'))){
                UserDetailModel::where('uid',$request->get('uid'))->increment('publish_task_num');
            }
//            $attachment=$request->file("attachment");
//            if($attachment){
//                $updateFile=\FileClass::uploadFile($attachment,"task");
//                $attachmentId=AttachmentModel::insertGetId([
//                    "name"=>json_decode($updateFile)->data->name,
//                    "type"=>json_decode($updateFile)->data->type,
//                    "size"=>json_decode($updateFile)->data->size,
//                    "url"=>json_decode($updateFile)->data->url,
//                    "disk"=>json_decode($updateFile)->data->disk,
//                ]);
//                TaskAttachmentModel::insert([
//                    'task_id'=>$taskId,
//                    'attachment_id'=>$attachmentId,
//                    'created_at'=>date('Y-m-d H:i:s')
//                ]);
//            }
        });
        if (!is_null($res)){
            return redirect()->back()->with(['error' => '更新失败！']);
        }
        return redirect("/manage/taskList")->with(['massage' => '更新成功！']);
    }
    //任务跟进
    public function taskFollow($id){
        $data=[
            'id'=>$id,
        ];
        return $this->theme->scope('manage.taskFollow', $data)->render();
    }
    //任务跟进数据提交
    public function taskFollowData(Request $request){
        $data=$request->except("_token");
        $task=TaskModel::find($data['task_id']);
        if(!$task){
            return redirect("/manage/taskList")->with(["message"=>"该任务不存在"]);
        }
        $data['follow_time'] = preg_replace('/([\x80-\xff]*)/i', '', $data['follow_time']);
        $data['follow_time']=date("Y-m-d",strtotime($data['follow_time']));
        $data['created_at']=date("Y-m-d H:i:s");
        $data['updated_at']=date("Y-m-d H:i:s");
        $data['manager_id']=$this->manager['id'];
        $insert=TaskFollowModel::insert($data);
        if($insert){
            return back()->with(["message"=>"跟进记录生成成功"]);
        }
        return back()->with(["message"=>"跟进记录生成失败"]);
    }
    //任务跟进列表
    public function taskFollowList(){
        $followList=TaskFollowModel::orderBy("id","desc")->paginate(15);
        $data=[
            "followList"=>$followList,
        ];
        return $this->theme->scope('manage.taskFollowList', $data)->render();
    }
    //任务跟进新增
    public function taskFollowAdd(){
        //获取所有的竞标任务
        $taskType=TaskTypeModel::select('id')->where('alias',"jingbiao")->first();
        $taskList = TaskModel::select('task.id','task.title')
            ->where('type_id',$taskType['id'])->get();
        $data=[
            'taskList'=>$taskList,
        ];
        return $this->theme->scope('manage.taskFollowAdd', $data)->render();
    }
    //任务跟进编辑
    public function taskFollowExit($id){
        $taskFollow=TaskFollowModel::find($id);
        //获取所有的竞标任务
        $taskType=TaskTypeModel::select('id')->where('alias',"jingbiao")->first();
        $taskList = TaskModel::select('task.id','task.title')
            ->where('id',$taskFollow['task_id'])->get();
        $data=[
            'id'=>$id,
            'taskList'=>$taskList,
            'taskFollow'=>$taskFollow,
        ];
        return $this->theme->scope('manage.taskFollowExit', $data)->render();
    }
    //任务跟进删除
    public function taskFollowDel($id){
        $follow=TaskFollowModel::find($id);
        if(!$follow){
            return back()->with(["message"=>"该跟进记录不存在"]);
        }
        $res=$follow->delete();
        if($res){
            return back()->with(["message"=>"该跟进记录删除成功"]);
        }
        return back()->with(["message"=>"该跟进记录删除失败"]);
    }
    //任务跟进记录处理
    public function taskFollowOpera(Request $request){
        $data=$request->except("_token,follow_id");
        unset($data['_token']);
        $data['follow_time'] = preg_replace('/([\x80-\xff]*)/i', '', $data['follow_time']);
        $data['follow_time']=date("Y-m-d",strtotime($data['follow_time']));
        $data['updated_at']=date("Y-m-d H:i:s");
        if($request->get("follow_id")){//修改任务跟进进度
            $follow=TaskFollowModel::find($request->get("follow_id"));
            if(!$follow){
                return redirect("/manage/taskFollowList")->with(["message"=>"该跟进记录不存在"]);
            }
            $res=$follow->update($data);
        }else{
            $data['created_at']=date("Y-m-d H:i:s");
            $res=TaskFollowModel::insert($data);
        }
        if($res){
            if($request->get("follow_id")){
                return redirect("manage/taskDetail/".$follow['task_id'])->with(["message"=>"操作成功"]);
            }
            return back()->with(["message"=>"操作成功"]);
        }
        return back()->with(["message"=>"操作失败"]);
    }
    /**
     * 任务处理
     *
     * @param $id
     * @param $action
     * @return \Illuminate\Http\RedirectResponse|mixed
     */
    public function taskHandle($id, $action,Request $request)
    {
        $all = $request->all();
        if (!$id) {
            return \CommonClass::showMessage('参数错误');
        }
        $id = intval($id);

        switch ($action) {
            //审核通过
            case 'pass':
                $status = 2;
                break;
            //审核失败
            case 'deny':
                $status = 3;
                break;
            //关闭
            case 'close':
                $status = 10;
                break;
        }
        //审核失败和成功 发送系统消息
        $task = TaskModel::where('id', $id)->first();
        if (isset($status) && $status == 2) {
            $result = TaskModel::where('id', $id)->whereIn('status', [1, 2])->update(array('status' => $status,'verified_at'=>date('Y-m-d H:i:s')));
            if (!$result) {
                return redirect()->back()->with(['error' => '操作失败！']);
            }

            UserModel::sendfreegrant($task->uid,2);//审核成功自动发放
            $userInfo = UserModel::where('id',$task->uid)->first();
            $user = [
                'uid'    => $task->uid,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username' => $userInfo->name,
                'title'    => $task->title
            ];
            \MessageTemplateClass::sendMessage('audit_success',$user,$templateArr,$templateArr);

            if($task->type_id == 3){
                //雇佣通知
                $employ = EmployModel::where('task_id',$id)->first();
                if($employ){
                    $userInfo1 = UserModel::where('id',$employ->employee_uid)->first();
                    $user1 = [
                        'uid'    => $employ->employee_uid,
                        'email'  => $userInfo1->email,
                        'mobile' => $userInfo1->mobile
                    ];
                    $templateArr1 = [
                        'employee_name' => $userInfo1->name,
                        'employer_name' => $userInfo->name
                    ];
                    \MessageTemplateClass::sendMessage('employ_notice',$user1,$templateArr1,$templateArr1);
                }
            }
        } elseif (isset($status) && $status == 3) {
            $result = DB::transaction(function () use ($id, $status, $task,$all) {
                $reason = isset($all['reason']) ? $all['reason'] : '';
                TaskModel::where('id', $id)->whereIn('status', [1, 2])->update([
                    'status'        => $status,
                    'verified_at'   => date('Y-m-d H:i:s'),
                    'reason'        => $reason
                ]);
                //判断任务是否需要退款
                $order = OrderModel::where('task_id',$id)->where('status',1)->where('uid',$task['uid'])->first();
                if($order){
                    UserDetailModel::where('uid', $task['uid'])->increment('balance', $task['bounty']);
                    $remainder = UserDetailModel::where('uid',$task['uid'])->first()->balance;
                    //生成财务记录
                    $finance = [
                        'action'     => 7,
                        'pay_type'   => 1,
                        'cash'       => $order['cash'],
                        'uid'        => $order['uid'],
                        'created_at' => date('Y-m-d H:i:d', time()),
                        'updated_at' => date('Y-m-d H:i:d', time()),
                        'status'     => 1,//用户收入
                        'remainder'  => $remainder
                    ];
                    FinancialModel::create($finance);
                }
            });
            if (!is_null($result)) {
                return redirect()->back()->with(['error' => '操作失败！']);
            }
            $userInfo = UserModel::where('id',$task->uid)->first();
            $user = [
                'uid'    => $task->uid,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username' => $userInfo->name,
                'title'    => $task->title
            ];
            \MessageTemplateClass::sendMessage('task_audit_failure',$user,$templateArr,$templateArr);
        }elseif(isset($status) && $status == 10){
            TaskModel::where('id', $id)->whereIn('status', [2,4,5,6,7,9])->update([
                'status' => $status,
                'end_at' =>date('Y-m-d H:i:s')
            ]);
        }
        return redirect()->back()->with(['message' => '操作成功！']);
    }


    /**
     * 任务批量处理
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|mixed
     */
    public function taskMultiHandle(Request $request)
    {
        if (!$request->get('ckb')) {
            return redirect()->back()->with(['error'=>'操作成功！']);
        }
        switch ($request->get('action')) {
            case 'pass':
                $status = 3;
                break;
            case 'deny':
                $status = 10;
                break;
            default:
                $status = 3;
                break;
        }
        TaskModel::whereIn('task.id', $request->get('ckb'))->join('task_type','task_type.id','=','task.type_id')->Where(function($query){
            $query->where(function($querys){
                $querys->where('task_type.alias','xuanshang')->where('task.status',2);
            })->orwhere(function($querys){
                $querys->where('task_type.alias','zhaobiao')->where('task.status',1);
            });
        })->update(array('task.status' => $status));
        return redirect()->back()->with(['message'=>'操作成功！']);

    }
    /*
     * 任务推荐
     * */
    public function taskRecommend($id){
        $task=TaskModel::find($id);
        if(!$task){
            return back()->with(['message'=>"该任务不存在"]);
        }
        if($task['is_recommend'] ==1){
            return back()->with(['message'=>"该任务已推荐"]);
        }
        $res=DB::transaction(function() use($task){
            //获取中标人
            $userName=WorkModel::leftJoin("users","work.uid","=","users.id")->where("work.task_id",$task['id'])
                ->where("work.status","!=",0)->select("users.name","users.id")->first();
            //获取竞标人数
            $bidNum=WorkModel::where("task_id",$task['id'])->count();
            SuccessCaseModel::insert([
                'uid'=>$userName['id'],
                'username'=>$userName['name'],
                'desc'=>$task['desc'],
                'title'=>$task['title'],
                'province'=>$task['province'],
                'city'=>$task['city'],
                'area'=>$task['area'],
                'cate_id'=>$task['cate_id'],
                'deal_at'=>$task['publicity_at'],
                'cash'=>$task['bounty'],
                'bidd_num'=>$bidNum,
                'type'=>3,
                'created_at'=>date('Y-m-d H:i:s'),
                'view_count'=>$task['view_count'],
                'pub_uid'=>$this->manager->id,
            ]);
            $task->update(['is_recommend'=>1]);
            return $task;
        });
        if($res){
            return back()->with(["message"=>"推荐成功"]);
        }
        return back()->with(["message"=>"推荐失败"]);
    }
    /**
     * 任务详情
     * @param $id
     */
    public function taskDetail($id,Request $request)
    {
        $task = TaskModel::where('id', $id)->first();
        if (!$task) {
            return redirect()->back()->with(['error' => '当前任务不存在，无法查看稿件！']);
        }
        $query = TaskModel::select('task.*', 'us.name as nickname')->where('task.id', $id);
        $taskDetail = $query->join('user_detail as ud', 'ud.uid', '=', 'task.uid')
            ->leftjoin('users as us', 'us.id', '=', 'task.uid')
            ->first();
        if(isset($taskDetail)){
            $taskDetail=$taskDetail->toArray();
        }else{
            return redirect()->back()->with(['error' => '当前任务不存在，无法查看稿件！']);
        }
        if (!$taskDetail) {
            return redirect()->back()->with(['error' => '当前任务已经被删除！']);
        }
        $taskDetail['service']=explode(',',$taskDetail['service']);
        //查询应用领域等等分类
        //应用领域
        $fieldArr=TaskCateModel::where("type",1)->orderBy("sort","asc")->get()->toArray();
        //技能标签
        $cateArr=TaskCateModel::where("type",2)->orderBy("sort","asc")->get()->toArray();
        //开放平台
        $openArr=TaskCateModel::where("type",3)->orderBy("sort","asc")->get()->toArray();
        //交易形式
        $transactionArr=TaskCateModel::where("type",4)->orderBy("sort","asc")->get()->toArray();
        //查询地区
        $province=DistrictModel::where("upid",0)->get();
        if($task['province']){
            $city=DistrictModel::where("upid",$task['province'])->get();
        }else{
            $city=DistrictModel::where("upid",$province[0]->id)->get();
        }
        $task_attachment = TaskAttachmentModel::select('task_attachment.*', 'at.url','at.name')->where('task_id', $id)
            ->leftjoin('attachment as at', 'at.id', '=', 'task_attachment.attachment_id')->get()->toArray();
        $domain = \CommonClass::getDomain();
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
        //获取代工费用
        $taskAgent=TaskAgentModel::where("task_id",$id)->first();
        //获取项目跟进进度
        $taskFollow=TaskFollowModel::where("task_id",$id)->get();
        //查询增值服务
        $service = ServiceModel::where('status',1)->where('type',1)->get()->toArray();
        //交付阶段
        $taskPayType = TaskPayTypeModel::where('task_id',$id)->first();
        $attachment = [];
        $paySection = [];
        if($taskPayType){
            $attachmentId = $taskPayType->attachment_id_str ? explode(',',$taskPayType->attachment_id_str) : [];
            if($attachmentId){
                $attachment = AttachmentModel::whereIn('id',$attachmentId)->get()->toArray();
            }
            $paySection = TaskPaySectionModel::where('task_id',$id)->orderBy('sort','asc')->get()->toArray();

        }
        //项目评价
        $comment = CommentModel::taskComment($id);
        //获取所有的seo 标签
        $seoList=SeoModel::all();
        //获取项目seo标签
        $taskSeo=SeoRelatedModel::where("related_id",$id)->where("type",1)->lists("seo_id")->toArray();
        //竞标详情
        $works = WorkModel::where('task_id',$id)->whereIn('status',[0,1])->with('shop','childrenAttachment')->orderBy('status','desc')->orderBy('show_sort','desc')->orderBy('id','desc')->paginate(20);
        //.获取雇主的企业名称和真实姓名
        $taskDetail['g_enterprise_auth']=EnterpriseAuthModel::where('uid',$task['uid'])->where('status',1)->pluck('company_name');
        $taskDetail['g_realname_auth']=RealnameAuthModel::where('uid',$task['uid'])->where('status',1)->pluck('realname');
        //.获取服务商的企业名称和真实姓名
        if(isset($works) && !empty($works)){
            foreach ($works as $k=>$v){
                $workUid=$v['uid'];
                $works[$k]['f_enterprise_auth']=EnterpriseAuthModel::where('uid',$workUid)->where('status',1)->pluck('company_name');
                $works[$k]['f_realname_auth']=RealnameAuthModel::where('uid',$workUid)->where('status',1)->pluck('realname');
            }
        }
        $data = [
            'task' => $taskDetail,
            'domain' => $domain,
            'fieldArr'=>$fieldArr,
            'cateArr'=>$cateArr,
            'openArr'=>$openArr,
            'transactionArr'=>$transactionArr,
            'province'=>$province,
            'city'=>$city,
            'taskAttachment' => $task_attachment,
            'works' => $works,
            'platesNum'=>$platesNum,
            'plateThick'=>$plateThick,
            'copperThickne'=>$copperThickne,
            'platingType'=>$platingType,
            'solderColor'=>$solderColor,
            'characterColor'=>$characterColor,
            'deliveryCycle'=>$deliveryCycle,
            'taskAgent'=>$taskAgent,
            'taskFollow'=>$taskFollow,
            'service'  => $service,
            'pay_type'=> $taskPayType,
            'pay_section'   => $paySection,
            'attachment' =>$attachment,
            'comment'               => $comment,
            'seoList'      =>$seoList,
            'taskSeo'   =>$taskSeo,
            'merge' => $request->all(),
        ];
        return $this->theme->scope('manage.taskdetail', $data)->render();
    }
    //任务删除
    public function taskDel($id){
        //查询该任务是否还存在
        $task=TaskModel::find($id);
        if(!$task){
            return back()->with(["message"=>"该任务不存在"]);
        }
        $res=$task->update(["updated_at"=>date("Y-m-d H:i:s"),'is_del'=>1]);
        if($res){
            return back()->with(["message"=>"该任务删除成功"]);
        }
        return back()->with(["message"=>"该任务删除失败"]);

    }

    /**
     * 任务详情提交
     * @param Request $request
     */
    public function taskDetailUpdate(Request $request)
    {
        $res=DB::transaction(function()use($request){
            $old=$request->get('old');//原增值服务标识identify
            $service_id=$request->get('product');//新增值服务id
            $identify=ServiceModel::where('id',$service_id)->pluck('identify');//新增值服务标识identify
            $topStatus = 0;
            $taskData = [];
            if(strcmp($old,$identify)!=0){//修改了增值服务
                if(!empty($service_id)){
                    TaskServiceModel::where("task_id",$request->get('task_id'))->delete();//删除原来的增值服务
                    TaskServiceModel::insert([
                        'task_id'=>$request->get('task_id'),
                        'service_id'=>$service_id,
                        'created_at'=>date("Y-m-d H:i:s"),
                    ]);
                    $taskData['appreciationsource'] ='2';
                    switch($identify){
                        case 'ZHIDING':
                            $topStatus = 1;
                            $taskData['is_top'] = 1;
                            $taskData['is_fast'] = 0;
                            $taskData['is_open'] = 1;
                            $taskData['is_car'] = 0;
                            break;
                        case 'JIAJI':
                            $topStatus = 1;
                            $taskData['is_fast'] = 1;
                            $taskData['is_top'] = 0;
                            $taskData['is_open'] = 1;
                            $taskData['is_car'] = 0;
                            break;
                        case 'SIMIDUIJIE':
                            $taskData['is_open'] = 2;
                            $taskData['is_fast'] = 0;
                            $taskData['is_top'] = 0;
                            $taskData['is_car'] = 0;
                            break;
                        case 'ZHITONGCHE':
                            $taskData['is_car'] = 1;
                            $taskData['is_open'] = 1;
                            $taskData['is_fast'] = 0;
                            $taskData['is_top'] = 0;
                            break;
                        default:
                            $taskData['is_top'] = 0;
                            $taskData['is_fast'] = 0;
                            $taskData['is_open'] = 1;
                            $taskData['is_car'] = 0;
                    }
                }
            }
            $taskData['top_status'] = $topStatus;
            $taskData['title']=$request->get('title');
            $taskData['field_id']=$request->get('field_id');
            $taskData['cate_id']=$request->get('cate_id');
            $taskData['transaction_id']=$request->get('transaction_id');
            $taskData['province']=$request->get('province');
            $taskData['city']=$request->get('city');
            $taskData['project_agent']=$request->get('project_agent');
            $taskData['bounty']=$request->get('bounty');
            $delivery_deadline = preg_replace('/([\x80-\xff]*)/i', '', $request->get('delivery_deadline'));
            $delivery_deadline = date('Y-m-d H:i:s',strtotime($delivery_deadline));
            $taskData['delivery_deadline']=$delivery_deadline;//截止时间
            $taskData['open_id']=$request->get('open_id');//开放平台
            $taskData['is_free'] = $request->get('is_free');
            $taskData['desc']=$request->get('desc');
            //$taskData['status']=2;
//            $taskData['service']=$request->get('service')?implode($request->get('service'),','):'';
            $taskData['level']=$request->get('level');
            $taskData['phone']=$request->get('mobile');
            $taskData['wechat']=$request->get('wechat');
            $taskData['qq']=$request->get('qq');
            $taskData['email']=$request->get('email');
            $taskData['is_support']=$request->get('is_support');
            TaskModel::where('id', $request->get('task_id'))->update($taskData);

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
                $taskAgentData['task_id']=$request->get('task_id');
                $taskAgent=TaskAgentModel::where("task_id",$request->get('task_id'))->first();
                if($taskAgent){
                    TaskAgentModel::where("task_id",$request->get('task_id'))->update($taskAgentData);
                }else{
                    TaskAgentModel::insert($taskAgentData);
                }
            }
            //修改增值服务
            /*TaskServiceModel::where("task_id",$request->get('task_id'))->delete();
            if($request->get('service')){
                foreach($request->get('service') as $key=>$val){
                    TaskServiceModel::insert([
                        'task_id'=>$request->get('task_id'),
                        'service_id'=>$val,
                        'created_at'=>date("Y-m-d H:i:s"),
                    ]);
                }
            }*/
            /*
           * 附件处理
           * */
            /*
             * 附件删除处理
             * */
            $delAttach=TaskAttachmentModel::where("task_id",$request->get('task_id'));
            if($request->get("attach_id")){
                $delAttach=$delAttach ->whereNotIn("attachment_id",$request->get("attach_id"));
            }
            $delAttach=$delAttach->delete();
            //$attachment=$request->file("attachment");
            if($request->get("file_ids")){
                $attachIdList=[];
                foreach($request->get("file_ids") as $fik=>$fiv){
                    $attachIdList[$fik]['task_id']=$request->get('task_id');
                    $attachIdList[$fik]['attachment_id']=$fiv;
                    $attachIdList[$fik]['created_at']=date('Y-m-d H:i:s');

                }
                TaskAttachmentModel::insert($attachIdList);
//                $updateFile=\FileClass::uploadFile($attachment,"task");
//                $attachmentId=AttachmentModel::insertGetId([
//                    "name"=>json_decode($updateFile)->data->name,
//                    "type"=>json_decode($updateFile)->data->type,
//                    "size"=>json_decode($updateFile)->data->size,
//                    "url"=>json_decode($updateFile)->data->url,
//                    "disk"=>json_decode($updateFile)->data->disk,
//                ]);
//                TaskAttachmentModel::insert([
//                    'task_id'=>$request->get('task_id'),
//                    'attachment_id'=>$attachmentId,
//                    'created_at'=>date('Y-m-d H:i:s')
//                ]);
            }
            //清楚该项目的seo标签
            SeoRelatedModel::where('related_id',$request->get('task_id'))->where("type",1)->delete();
            //添加seo标签
            if($request->get('seo_laber')){
                SeoModel::seoHandle(1,1,[$request->get('task_id')],$request->get('seo_laber'));
            }
        });
        if (!is_null($res)){
            return redirect()->back()->with(array('message' => '更新失败'));
        }
        $info = TaskModel::where('id',$request->get('task_id'))->first();
        if($info->type_id == 1){
            return redirect("/manage/taskDetail/".$request->get('task_id'))->with(array('message' => '更新成功'));
        }else{
            return redirect("/manage/taskDetail/".$request->get('task_id'))->with(array('message' => '更新失败'));
        }

    }

    /**
     * 删除任务留言
     */
    public function taskMassageDelete($id)
    {
        $result = WorkCommentModel::destroy($id);

        if (!$result) {
            return redirect()->to('/manage/taskList')->with(['error' => '留言删除失败！']);
        }
        return redirect()->to('/manage/taskList')->with(['massage' => '留言删除成功！']);
    }

    /**下载附件
     * @param $id
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download($id)
    {
        $pathToFile = AttachmentModel::where('id', $id)->first();
        $pathToFile = $pathToFile['url'];
        return response()->download($pathToFile);
    }

    /**
     * 一对一推送匹配用户
     * @param Request $request
     * @return array
     */
    public function searchUser(Request $request)
    {
        $nickname = $request->get('nickname');
        if(!$nickname){
            return $data = [
                'code' => 0,
                'msg'  => '请输入用户昵称'
            ];
        }
        $task = TaskModel::find($request->get('taskId'));
        if($task){
            $list = UserDetailModel::where('nickname','like','%'.$nickname.'%')->where('uid','!=',$task->uid)->select('uid','nickname')->get()->toArray();
            if($list){
                $html = '';
                foreach($list as $k => $v){
                    $html = $html.'<option value="'.$v['uid'].'">'.$v['nickname'].'</option>';
                }
                return $data = [
                    'code' => 1,
                    'msg'  => 'success',
                    'data' => $html
                ];
            }else{
                return $data = [
                    'code' => 0,
                    'msg'  => '没有匹配的用户'
                ];
            }
        }else{
            return $data = [
                'code' => 0,
                'msg'  => '参数错误'
            ];
        }


    }

    /*
     * 多对多任务推送
     * */
    public function posttsPush(Request $request){
        $taskId = $request->get('task_id');
        $task = TaskModel::find($taskId);
        if(!$task ||  $task['status'] != 2){
            return $data = [
                    'code' => 0,
                    'msg'  => '不能推送',
                ];
        }
        $userId = $request->get("tsuid");
        $userId = explode(',',$userId);
        foreach($userId as $v){
            $taskInvite=TaskInviteModel::where("task_id",$taskId)->where("uid",$v)->first();
            if($taskInvite){
                continue;
            }
            $res = TaskInviteModel::create([
                'task_id' => $taskId,
                'uid'     => $v
            ]);

            if($res){
                //发送邀请信息
                TaskInviteModel::sendInviteMsg($task,$v);
            }
        }
        return $data = [
                    'code' => 1,
                    'msg'  => '推送成功！',
                ];
       
    }

    /**
     * 多对多推送筛选用户
     * @param Request $request
     * @return array
     */
    public function searchPush(Request $request)
    {
        $merge = $request->all();

        $authType_arr = ['1'=>'个人','2'=>'企业'];
        $level_arr = ['1'=>'普通用户','2'=>'青铜会员','3'=>'白银会员','4'=>'黄金会员','5'=>'铂金会员','6'=>'王者会员'];
        $task = TaskModel::find($request->get('taskId'));
        $industry = $merge['industry'] ? $merge['industry'] : '';
        $skill   = $merge['skill'] ? $merge['skill'] : '';
        $shopId=ShopTagsModel::whereRaw(" 1=1");
        $arrres = [];
        if(!empty($industry) && !empty($skill) ){
            $arrres = [$merge['industry'],$merge['skill']];
        }elseif(!empty($industry)){
            $arrres = [$merge['industry']];
        }elseif(!empty($skill)){
            $arrres = [$merge['skill']];
        }
        if(count($arrres) > 0){
            $shopId = $shopId->whereIn("cate_id",$arrres)->distinct()->lists("shop_id")->toArray();
        }else{
            $shopId = $shopId->distinct()->lists("shop_id")->toArray();
        }
        if($task){
            //去除已经推送的用户
            $pushYes = TaskInviteModel::where("task_id",$request->get('taskId'))->lists("uid")->toArray();
            
            //获取用户id
            $userId=UserModel::leftJoin('shop','users.id','=','shop.uid')->leftjoin("user_detail",'users.id','=','user_detail.uid')
                ->where("users.id",'!=',$task->uid);

            // ->where()
            if(!empty($merge['level'])){
                $userId = $userId->where("users.level",'=',$merge['level']);
            }
            if(!empty($merge['auth_type'])){
                $userId = $userId->where("shop.type",'=',$merge['auth_type']);
            }
            if(!empty($merge['province'])){
                $userId = $userId->where("shop.province",'=',$merge['province']);
            }
            if(!empty($merge['city'])){
                $userId = $userId->where("shop.city",'=',$merge['city']);
            }
            $userId = $userId->whereNotIn("users.id",$pushYes)
                ->whereIn("shop.id",$shopId)->select("users.id","shop.id as shop_id","shop.shop_name","shop.province","shop.city","shop.type","users.mobile","user_detail.auth_type","users.level")->orderBy(DB::raw('RAND()'))->take(10)->get()->toArray();
            
            if($userId){
                $html = '';
                $uid = [];
                foreach($userId as $k => $v){
                    $industrys = '';
                    $skills = '';
                    array_push($uid,$v['id']);
                    $industry = ShopTagsModel::gettagname($v['shop_id']);
                    if(is_array($industry) && count($industry)>0){
                        $num = count($industry);
                        for($i=0;$i<$num;$i++){
                            $industrys =  $industrys. $industry[$i].',';
                        }
                    }
                    $industrys = rtrim($industrys, ',');
                    $userId[$k]['industry'] = $industrys;

                    $skill = ShopTagsModel::gettagname($v['shop_id'],'2');
                    if(is_array($skill) && count($skill)>0){
                        $num = count($skill);
                        for($i=0;$i<$num;$i++){
                            $skills =  $skills. $skill[$i].',';
                        }
                    }
                    $skills = rtrim($skills, ',');
                    $userId[$k]['skill'] = !empty($skills)?$skills:'';
                    //地区
                    $province = DistrictModel::where('id', $v['province'])->first();
                    $city = DistrictModel::where('id', $v['city'])->first();
                    if (!empty($province)) {
                        if(!empty($city)){
                            $userId[$k]['province'] = $province->name.','.$city->name;
                        }else{
                            $userId[$k]['province'] = $province->name;
                        }
                        
                    }
                }
                foreach($userId as $k => $v){
                    if($v['level'] < 7 && isset($level_arr[$v['level']])){  $level = $level_arr[$v['level']]; }else{ $level = '普通用户';}
                    $html = $html."<tr><td>".$v['id']."</td><td>".$v['shop_name']."</td><td>".$v['mobile']."</td><td>".$v['industry']."</td><td>".$v['skill']."</td><td>". $level ."</td><td>". $authType_arr[$v['type']] ."</td><td>".$v['province']."</td></tr>";
                }
                return $data = [
                    'code' => 1,
                    'msg'  => 'success',
                    'data' => $html,
                    'tsuid' => $uid,
                ];
            }else{
                return $data = [
                    'code' => 0,
                    'msg'  => '没有匹配的用户'
                ];
            }
        }else{
            return $data = [
                'code' => 0,
                'msg'  => '参数错误'
            ];
        }


    }

}
