<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Http\Controllers\BasicController;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\MessageTemplateModel;
use App\Modules\Task\Model\TaskFeedbackModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskPayTypeModel;
use App\Modules\Task\Model\TaskTypeModel;
use App\Modules\Task\Model\TaskReportModel;
use App\Modules\Task\Model\TaskRightsModel;
use App\Modules\Task\Model\TaskPaySectionModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\MessageReceiveModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\Manage\Model\ConfigModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskRightsController extends ManageController
{
    public $user;
    public function __construct()
    {
        parent::__construct();
        $this->user = $this->manager;
        $this->initTheme('manage');
        $this->theme->setTitle('交易维权');
        $this->theme->set('manageType', 'TaskRights');
    }

    /**
     * 维权列表
     */
    public function rightsList(Request $request)
    {
        $data = $request->all();
        $taskType = TaskTypeModel::getTaskTypeIdByAlias('jingbiao');
        $query = TaskRightsModel::select('task_rights.*', 'ud.name as from_nickname', 'userd.name as to_nickname','task.title')->where("task.type_id",$taskType);
        //维权人筛选
        if ($request->get('username')) {
            $query=$query->where(function($querys)use($request){
                $querys->where('ud.name','like','%'.$request->get('username').'%')
                    ->orWhere('userd.name','like','%'.$request->get('username').'%')
                    ->orWhere("task_rights.id",'like','%'.$request->get('username').'%')
                    ->orWhere("task_rights.deal_name",'like','%'.$request->get('username').'%')
                     ->orWhere("task.title",'like','%'.$request->get('username').'%');
            });
        }
        //举报类型筛选
        if ($request->get('reportType') && $request->get('reportType') != 0) {
            $query = $query->where('task_rights.type', $request->get('reportType'));
        }
        //举报状态筛选
        if ($request->get('reportStatus') && $request->get('reportStatus') != 0) {

            $query = $query->where('task_rights.status', $request->get('reportStatus') - 1);
        }
        //时间筛选
        $timeType = 'task_rights.created_at';
        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $query = $query->where($timeType,'>',$start);

        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d H:i:s',strtotime($end)+24*60*60);
            $query = $query->where($timeType,'<',$end);
        }
        //分页条数筛选
        $page_size = 10;
        $reports_page = $query->join('users as ud', 'ud.id', '=', 'task_rights.from_uid')
            ->leftjoin('users as userd', 'userd.id', '=', 'task_rights.to_uid')
            ->leftjoin('task', 'task_rights.task_id', '=', 'task.id')
            ->orderBy('task_rights.id','DESC')
            ->paginate($page_size);
        $reports = $reports_page->toArray();
        //处理维权类型
        $rights_type = [
            'type'=>[
                1=>'违规信息',
                2=>'虚假交换',
                3=>'涉嫌抄袭',
                4=>'其他',
            ],
        ];
        $reports['data'] = \CommonClass::intToString($reports['data'],$rights_type);

        $view = [
            'rights' => $reports,
            'merge' => $data,
            'reports_page'=>$reports_page,
            'type'=>"jingbiao",
        ];
        return $this->theme->scope('manage.taskrights', $view)->render();
    }

    /**
     * 维权详情
     * @param $id
     * @return mixed
     */
    public function rightsDetail($id)
    {
        //获取上一项id
        $preId = TaskRightsModel::where('task_rights.id', '>', $id)->min('task_rights.id');
        //获取下一项id
        $nextId = TaskRightsModel::where('task_rights.id', '<', $id)->max('task_rights.id');
        $rights = TaskRightsModel::where('id',$id)->first();
        $work = [];
        $task = TaskModel::where('id',$rights['task_id'])->first();
        if($rights['work_id'] > 0){
            $work = WorkModel::where('id',$rights['work_id'])->first();
            $taskPaySection = TaskPaySectionModel::select('price')->where('task_id',$rights['task_id'])->where('status',0)->where('section_status',2)->first();
            $price = TaskPaySectionModel::where('task_id',$rights['task_id'])->where('status',0)->whereIn('section_status',[0,2])->sum('price');
            $task['bounty'] = $taskPaySection ? $price:$task['bounty'];
        }else{
            $bounty = TaskPaySectionModel::where('task_id',$rights['task_id'])->where('status',0)->where('case_status',1)->where('section_status',0)->sum('price');
            $task['bounty'] = $bounty;
        }
        $from_user = UserModel::select('users.*','ud.nickname','ud.mobile','ud.qq')->where('users.id',$rights['from_uid'])->leftjoin('user_detail as ud','ud.uid','=','users.id')->first();

        $to_user = UserModel::select('users.*','ud.nickname','ud.mobile','ud.qq')
            ->where('users.id',$rights['to_uid'])
            ->leftjoin('user_detail as ud','ud.uid','=','users.id')
            ->first();
        //查询所有的附件
        $attachment = [];
        if(!empty(json_decode($rights['attachment_ids'])))
        {
            $attachment = AttachmentModel::whereIn('id',json_decode($rights['attachment_ids']))->get();
        }
        //处理维权类型
        $rights_type =[
            1 => '违规信息',
            2 => '虚假交换',
            3 => '涉嫌抄袭',
            4 => '其他',

        ];

        //交付阶段
        $taskPayType = TaskPayTypeModel::where('task_id',$rights->task_id)->first();
        $attachment1 = [];
        $paySection = [];
        if($taskPayType){
            $attachmentId = $taskPayType->attachment_id_str ? explode(',',$taskPayType->attachment_id_str) : [];
            if($attachmentId){
                $attachment1 = AttachmentModel::whereIn('id',$attachmentId)->get()->toArray();
            }
            $paySection = TaskPaySectionModel::where('task_id',$rights->task_id)->orderBy('sort','asc')->get()->toArray();

        }
        $view = [
            'report'        => $rights,
            'from_user'     => $from_user,
            'to_user'       => $to_user,
            'task'          => $task,
            'work'          => $work,
            'preId'         => $preId,
            'nextId'        => $nextId,
            'attachment'    => $attachment,
            'rights_type'   => $rights_type,
            'pay_type'      => $taskPayType,
            'pay_section'   => $paySection,
            'attachment1'   => $attachment1,
        ];

        return $this->theme->scope('manage.rightsdetail', $view)->render();
    }

    /**
     * 雇佣维权列表
     */
    public function bidRightsList(Request $request)
    {
        $data = $request->all();
        $taskType = TaskTypeModel::where("alias","guyong")->first();
        $query = TaskRightsModel::select('task_rights.*', 'ud.name as from_nickname', 'userd.name as to_nickname','task.title')
            ->where("task.type_id",$taskType["id"]);
        //维权人筛选
        if ($request->get('username')) {
            $query=$query->where(function($querys)use($request){
                $querys->where('ud.name','like','%'.$request->get('username').'%')
                    ->orWhere('userd.name','like','%'.$request->get('username').'%')
                    ->orWhere("task_rights.id",'like','%'.$request->get('username').'%')
                    ->orWhere("task.title",'like','%'.$request->get('username').'%');
            });
        }
        //举报类型筛选
        if ($request->get('reportType') && $request->get('reportType') != 0) {
            $query = $query->where('task_rights.type', $request->get('reportType'));
        }
        //举报状态筛选
        if ($request->get('reportStatus') && $request->get('reportStatus') != 0) {

            $query = $query->where('task_rights.status', $request->get('reportStatus') - 1);
        }
        //时间筛选
        $timeType = 'task_rights.created_at';
        if($request->get('start')){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $request->get('start'));
            $start = date('Y-m-d H:i:s',strtotime($start));
            $query = $query->where($timeType,'>',$start);

        }
        if($request->get('end')){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $request->get('end'));
            $end = date('Y-m-d H:i:s',strtotime($end)+24*60*60);
            $query = $query->where($timeType,'<',$end);
        }
        //分页条数筛选
        $page_size = 10;
        $reports_page = $query->join('users as ud', 'ud.id', '=', 'task_rights.from_uid')
            ->leftjoin('users as userd', 'userd.id', '=', 'task_rights.to_uid')
            ->leftjoin('task', 'task_rights.task_id', '=', 'task.id')
            ->orderBy('task_rights.id','DESC')
            ->paginate($page_size);
        $reports = $reports_page->toArray();
        //处理维权类型
        $rights_type = [
            'type'=>[
                1=>'违规信息',
                2=>'虚假交换',
                3=>'涉嫌抄袭',
                4=>'其他',
            ],
        ];
        $reports['data'] = \CommonClass::intToString($reports['data'],$rights_type);

        $view = [
            'rights' => $reports,
            'merge' => $data,
            'reports_page'=>$reports_page,
            'type'=>"guyong",
        ];

        return $this->theme->scope('manage.taskrights', $view)->render();
    }
    /**
     * 维权详情
     * @param $id
     * @return mixed
     */
    public function bidRightsDetail($id)
    {
        //获取类型id
        $taskType = TaskTypeModel::where("alias","guyong")->first();
        //获取上一项id
        $preId = TaskRightsModel::leftJoin("task","task_rights.task_id","=","task.id")->where("task.type_id",$taskType['id'])->where('task_rights.id', '>', $id)->min('task_rights.id');
        //获取下一项id
        $nextId = TaskRightsModel::leftJoin("task","task_rights.task_id","=","task.id")->where("task.type_id",$taskType['id'])->where('task_rights.id', '<', $id)->max('task_rights.id');
        $rights = TaskRightsModel::where('id',$id)->first();
        $work = [];
        $task = TaskModel::where('id',$rights['task_id'])->first();
        if($rights['work_id'] > 0){
            $work = WorkModel::where('id',$rights['work_id'])->first();
            $taskPaySection=TaskPaySectionModel::select('price')->where('task_id',$rights['task_id'])->where('status',0)->where('section_status',2)->first();
            $price = TaskPaySectionModel::where('task_id',$rights['task_id'])->where('status',0)->whereIn('section_status',[0,2])->sum('price');
            $task['bounty'] = $taskPaySection ? $price:$task['bounty'];
        }else{
            $bounty = TaskPaySectionModel::where('task_id',$rights['task_id'])->where('status',0)->where('case_status',1)->where('section_status',0)->sum('price');
            $task['bounty'] = $bounty;
        }

        $from_user = UserModel::select('users.*','ud.nickname','ud.mobile','ud.qq')
            ->where('users.id',$rights['from_uid'])
            ->leftjoin('user_detail as ud','ud.uid','=','users.id')
            ->first();

        $to_user = UserModel::select('users.*','ud.nickname','ud.mobile','ud.qq')
            ->where('users.id',$rights['to_uid'])
            ->leftjoin('user_detail as ud','ud.uid','=','users.id')
            ->first();
        //查询所有的附件
        $attachment = [];
        if(!empty(json_decode($rights['attachment_ids'])))
        {
            $attachment = AttachmentModel::whereIn('id',json_decode($rights['attachment_ids']))->get();
        }
        //处理维权类型
        $rights_type =[
            1=>'违规信息',
            2=>'虚假交换',
            3=>'涉嫌抄袭',
            4=>'其他',

        ];
        //交付阶段
        $taskPayType = TaskPayTypeModel::where('task_id',$rights->task_id)->first();
        $attachment1 = [];
        $paySection = [];
        if($taskPayType){
            $attachmentId = $taskPayType->attachment_id_str ? explode(',',$taskPayType->attachment_id_str) : [];
            if($attachmentId){
                $attachment1 = AttachmentModel::whereIn('id',$attachmentId)->get()->toArray();
            }
            $paySection = TaskPaySectionModel::where('task_id',$rights->task_id)->orderBy('sort','asc')->get()->toArray();

        }
        $view = [
            'report'        => $rights,
            'from_user'     => $from_user,
            'to_user'       => $to_user,
            'task'          => $task,
            'work'          => $work,
            'preId'         => $preId,
            'nextId'        => $nextId,
            'attachment'    => $attachment,
            'rights_type'   => $rights_type,
            'pay_type'      => $taskPayType,
            'pay_section'   => $paySection,
            'attachment1'   => $attachment1,
        ];


        return $this->theme->scope('manage.rightsdetail', $view)->render();
    }
    /**
     * 单个删除
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function rightsDelet($id)
    {
        //删除指定id的举报记录
        $result = TaskRightsModel::destroy($id);
        if(!$result)
            return redirect()->to('/manage/rightsList')->with(['error'=>'删除失败！']);

        return redirect()->to('/manage/rightsList')->with(['massage'=>'删除成功！']);
    }

    /**
     * 批量删除
     * @param Request $request
     */
    public function rightsDeletGroup(Request $request)
    {
        $data = $request->except('_token');

        $result = TaskRightsModel::whereIn($data['id'])->delete();

        if(!$result)
            return redirect()->to('/manage/rightsList')->with(['error'=>'删除失败!']);

        return redirect()->to('/manage/rightsList')->with(['massage'=>'删除成功！']);
    }

    /**
     * 处理维权
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
	 * 2017-09-12 修改该方法 by heike
     */
    public function handleRights(Request $request)
    {
        $data = $request->except('_token');
		if($data['worker_bounty']=='' || $data['owner_bounty']==''){
			return redirect()->back()->with(['error'=>'雇主或威客分配金额不能为空']);
		}
        $rights = TaskRightsModel::where('id',$data['id'])->first();
        //查询交付阶段 处于维权中的交付金额
		$taskPaySection = TaskPaySectionModel::select('price')->where('task_id',$rights['task_id'])->where('status',0)->where('section_status',2)->first();
        $price = TaskPaySectionModel::where('task_id',$rights['task_id'])->where('status',0)->whereIn('section_status',[0,2])->sum('price');
		//计算当前任务的赏金
        $task = TaskModel::where('id',$rights['task_id'])->first();
		if($task['status'] == 10 && $task['type_id'] == 1){
			return redirect()->back()->with(['error'=>'该任务已处于失败中，金额已处理完']);
		}
        $bounty = $taskPaySection ? floatval($price):floatval($task['bounty']);
		//判断赏金分配合理性
        if(($data['worker_bounty']+$data['owner_bounty'])>$bounty)
        {
            return redirect()->back()->with(['error'=>'赏金分配超额']);
        }
        if($rights['role'] == 0)
        {
            $worker_id = $rights['from_uid'];
            $owner_id = $rights['to_uid'];
        }else{
            $worker_id = $rights['to_uid'];
            $owner_id = $rights['from_uid'];
        }
        $dealName = $request->get('deal_name');
        //赏金分配
        $status = DB::transaction(function() use($data,$rights,$bounty,$worker_id,$owner_id,$task,$dealName)
        {
            //判断当前任务是否处于维权状态,修改成当前任务已经完成
            if($task['status'] == 9)
            {
                TaskModel::where('id',$task['id'])->update(['status'=>11,'end_at' => date('Y-m-d',time())]);
            }
            //修改当前的交易维权记录
            $handle = [
                'status'        => 1,
                'handle_uid'    => $this->user['id'],
                'handled_at'    => date('Y-m-d H:i:s',time()),
                'worker_bounty' => $data['worker_bounty'],
                'owner_bounty'  => $data['owner_bounty'],
                'deal_name'     => $dealName
            ];
            TaskRightsModel::where('id',$data['id'])->update($handle);

            //将赏金按照处理分给威客
            if($data['worker_bounty']!=0)
            {
                UserDetailModel::where('uid',$worker_id)->increment('balance',$data['worker_bounty']);
                $remainder = UserDetailModel::where('uid',$worker_id)->first()->balance;
                //产生一笔财务流水，接受任务产生收益
                $finance_data = [
                    'action'        => 10,
                    'pay_type'      => 1,
                    'cash'          => $data['worker_bounty'],
                    'uid'           => $worker_id,
                    'created_at'    => date('Y-m-d H:i:s',time()),
                    'status'        => 1,//用户收入
                    'remainder'     => $remainder,
                    'related_id'    =>$data['id'],
                ];
                FinancialModel::create($finance_data);
            }
            //将赏金按照处理分给雇主
            if($data['owner_bounty']!=0)
            {
                UserDetailModel::where('uid',$owner_id)->increment('balance',$data['owner_bounty']);
                $remainder1 = UserDetailModel::where('uid',$owner_id)->first()->balance;

                //产生一笔财务流水，任务失败产生的退款
                $finance_data = [
                    'action'     => 10,
                    'pay_type'   => 1,
                    'cash'       => $data['owner_bounty'],
                    'uid'        => $owner_id,
                    'created_at' => date('Y-m-d H:i:s',time()),
                    'status'     => 1,//用户收入
                    'remainder'  => $remainder1,
                    'related_id'    =>$data['id'],
                ];
                FinancialModel::create($finance_data);
            }
        });

        //事务处理失败
        if(!is_null($status)) {
            return redirect()->back()->with(['error'=>'维权处理失败！']);
        }

        $userInfo = UserModel::find($rights['from_uid']);
        $user = [
            'uid'    => $userInfo->id,
            'email'  => $userInfo->email,
            'mobile' => $userInfo->mobile
        ];
        $templateArr = [
            'username'      => $userInfo->name,
            'title'         => $task->title
        ];
        \MessageTemplateClass::sendMessage('task_right_deal',$user,$templateArr,$templateArr);

        return redirect()->back()->with(['massage'=>'维权处理成功！']);

    }

    public function cancelRights(Request $request,$id)
    {
        $rights = TaskRightsModel::where('id',$id)->first();
        $task = TaskModel::where('id',$rights['task_id'])->first();
        if($task['status'] == 10 && $task['type_id'] == 1){
            return redirect()->back()->with(['error'=>'该任务已处于失败中，金额已处理完']);
        }
        if($task['status'] == 9)
        {
            if($rights['work_id'] > 0){

                DB::transaction(function() use($task,$rights,$id) {
                    TaskModel::where('id',$task['id'])->update([
                        'status'        => 6,//工作中
                        'publicity_at'  => date('Y-m-d H:i:s'),
                    ]);
                    WorkModel::where('id',$rights['work_id'])->update([
                        'status'        => 6,//维权取消
                    ]);

                    $paySectionInfo = [
                        'section_status' => 4,//维权取消
                        'updated_at'     => date('Y-m-d H:i:s'),
                    ];
                    TaskPaySectionModel::where('task_id',$rights['task_id'])->where('status',0)->where('section_status',2)->update($paySectionInfo);

                    //维权取消
                    $handle = [
                        'status'        => 2,
                        'handle_uid'    => $this->user['id'],
                        'handled_at'    => date('Y-m-d H:i:s',time()),
                    ];
                    TaskRightsModel::where('id',$id)->update($handle);
                });


            }else{
                DB::transaction(function() use($task,$rights,$id) {
                    $secion = TaskPaySectionModel::where('task_id',$task['id'])->where('work_id','!=','')->count();
                    if($secion){
                        TaskModel::where('id',$task['id'])->update([
                            'status'        => 6,//工作中
                            'publicity_at'  => date('Y-m-d H:i:s'),
                        ]);
                    }else{
                        TaskModel::where('id',$task['id'])->update([
                            'status'        => 5,//开始工作
                            'publicity_at'  => date('Y-m-d H:i:s'),
                        ]);
                    }

                    //维权取消
                    $handle = [
                        'status'        => 2,
                        'handle_uid'    => $this->user['id'],
                        'handled_at'    => date('Y-m-d H:i:s',time()),
                    ];
                    TaskRightsModel::where('id',$id)->update($handle);
                });


            }
        }



        return redirect()->back()->with(['massage'=>'操作成功！']);
    }


    /**
     *.项目反馈列表
     */
    public function taskFeedbackList(Request $request,$merge=[],$paginate=10)
    {
        $merge = $request->all();
        $res=TaskFeedbackModel::getFeedbackList($merge,$paginate);
        $task_feedbackList=isset($res['task_feedbackList'])?$res['task_feedbackList']:'';
        $feedback_type=isset($res['feedback_type'])?$res['feedback_type']:'';
        $view = [
            'merge' => $merge,
            'task_feedbackList' => $task_feedbackList,
            'feedback_type' => $feedback_type,
        ];

        $this->theme->setTitle('反馈列表');
        return $this->theme->scope('manage.taskfeedbacklist', $view)->render();
    }



}
