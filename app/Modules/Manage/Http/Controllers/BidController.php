<?php

namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\MessageTemplateModel;
use App\Modules\Manage\Model\SeoModel;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Task\Model\TaskFollowModel;
use App\Modules\Task\Model\TaskAgentModel;
use App\Modules\Task\Model\TaskAttachmentModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskExtraModel;
use App\Modules\Task\Model\TaskExtraSeoModel;
use App\Modules\Task\Model\TaskInviteModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Task\Model\TaskPaySectionModel;
use App\Modules\Task\Model\TaskPayTypeModel;
use App\Modules\Task\Model\TaskTypeModel;
use App\Modules\Task\Model\WorkCommentModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\CommentModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\MessageReceiveModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Theme;

class BidController extends ManageController
{  
   public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
        $this->theme->setTitle('任务列表');
        $this->theme->set('manageType', 'task');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
	 * 招标列表
	 * 2017-09-11 by heike
     */
    public function bidList(Request $request)
    {
        //待确认的雇佣id
        $employTaskId = EmployModel::lists('task_id')->toArray();
        $employTaskIdArr = WorkModel::whereIn('task_id',$employTaskId)->where('status',0)->lists('task_id')->toArray();
        $search = $request->all();
        $by = $request->get('by') ? $request->get('by') : 'id';
        $order = $request->get('order') ? $request->get('order') : 'desc';
        $paginate = $request->get('paginate') ? $request->get('paginate') : 10;
		$taskType = TaskTypeModel::select('id')->where('alias',"guyong")->first();
        $taskList = EmployModel::select('task.id', 'task.title', 'task.created_at', 'task.status', 'task.verified_at', 'task.bounty_status','task.level','employ.employer_uid','employee_uid','employ.task_id')
		            ->where('task.type_id',$taskType['id'])->where('task.is_del',0)->where('task.status','!=',0);
        if ($request->get('task_title')) {
            $taskList = $taskList->where(function($query)use($request){
                   $query->where('task.title', 'like', '%' . e($request->get('task_title')). '%')
                          ->orWhere("task.id", 'like', '%' . e($request->get('task_title')). '%');
            });
        }
        //状态筛选
        if ($request->get('status') && $request->get('status') != 0) {
           $status=$request->get("status");
            if($status == -1){
                $status=0;
            }
            if($status == '12'){
                $taskList = $taskList->whereIn('task.id', $employTaskIdArr)->where('task.status',2);

            }elseif($status == '2') {
                $taskList = $taskList->whereNotIn('task.id', $employTaskIdArr)->where('task.status',2);
            }else
            {
                $taskList = $taskList->where('task.status', $status);
            }

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
                $end = date('Y-m-d H:i:s',strtotime($end)+24*60*60);
                $taskList = $taskList->where($request->get('time_type'), '<', $end);
            }

        }
        $merge = $request->all();
        if(isset($merge['level']) && $merge['level'] > 0){
            $taskList = $taskList->where('task.level',$merge['level']);
        }
        if(isset($merge['employer_name']) && $merge['employer_name']){
            $taskList = $taskList->whereHas('employerusers' , function($q) use ($merge){
                $q->where('name','like','%'.$merge['employer_name'].'%');
            });
        }
        if(isset($merge['employee_name']) && $merge['employee_name']){
            $taskList = $taskList->whereHas('employeeusers' , function($q) use ($merge){
                $q->where('name','like','%'.$merge['employee_name'].'%');
            });
        }
        $taskList = $taskList->orderBy($by, $order)
            ->leftJoin('task','task.id','=','employ.task_id')
            ->with([
                'employerusers' => function($q){
                   $q->select('id','name');
                },
                'employeeusers' => function($q){
                     $q->select('id','name');
                }
            ])
            ->with(['follow' => function($q) use ($merge){
                $q->orderBy('id','desc');
            }])
           /* ->whereHas('employer' , function($q) use ($merge){
                if(isset($merge['employer_name']) && $merge['employer_name']){
                    $q->where('name',$merge['employer_name']);
                }
            })
            ->whereHas('employee' , function($q) use ($merge){
                if(isset($merge['employee_name']) && $merge['employee_name']){
                    $q->where('name',$merge['employee_name']);
                }
            })*/
            ->paginate($paginate);
        //获取所有的seo 标签
        $seoList= SeoModel::all();
        $data = array(
            'task'    => $taskList,
            'employTaskIdArr' => $employTaskIdArr,
            'seoList' => $seoList,
        );
        $data['merge'] = $search;
        return $this->theme->scope('manage.bidList',$data)->render();
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
	 * 任务详情页面
	 * 2017-09-11 by heike
     */
    public function bidDetail($id,Request $request)
    {
        $task = TaskModel::where('id', $id)->first();
        if (!$task) {
            return redirect()->back()->with(['error' => '当前任务不存在，无法查看稿件！']);
        }
        $query = TaskModel::select('task.*', 'us.name as nickname')->where('task.id', $id);
        $taskDetail = $query->join('user_detail as ud', 'ud.uid', '=', 'task.uid')
            ->leftjoin('users as us', 'us.id', '=', 'task.uid')
            ->first()->toArray();
        //dd($taskDetail);
        if (!$taskDetail) {
            return redirect()->back()->with(['error' => '当前任务已经被删除！']);
        }
        $taskDetail['service']=explode(',',$taskDetail['service']);
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
        if($task['province']){
            $city=DistrictModel::where("upid",$task['province'])->get();
        }else{
            $city=DistrictModel::where("upid",$province[0]->id)->get();
        }
        $task_attachment = TaskAttachmentModel::select('task_attachment.*', 'at.url')->where('task_id', $id)
            ->leftjoin('attachment as at', 'at.id', '=', 'task_attachment.attachment_id')->get()->toArray();
        /*查询服务*/
        //$serviceAll=ServiceModel::getAll();
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
        //交付阶段
        $taskPayType = TaskPaySectionModel::where('task_id',$id)->first();
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
        //竞标详情
        $works = WorkModel::where('task_id',$id)->whereIn('status',[0,1])->with('shop','childrenAttachment')->orderBy('status','desc')->orderBy('show_sort','desc')->orderBy('id','desc')->paginate(20);
        $data = [
            'task'           => $taskDetail,
            'domain'         => $domain,
            'fieldArr'       => $fieldArr,
            'cateArr'        => $cateArr,
            'openArr'        => $openArr,
            'transactionArr' => $transactionArr,
            'province'       => $province,
            'city'           => $city,
            'taskAttachment' => $task_attachment,
            'works'          => $works,
            //'serviceAll'=>$serviceAll,
            'taskFollow'=>$taskFollow,
            'platesNum'      => $platesNum,
            'plateThick'     => $plateThick,
            'copperThickne'  => $copperThickne,
            'platingType'    => $platingType,
            'solderColor'    => $solderColor,
            'characterColor' => $characterColor,
            'deliveryCycle'  => $deliveryCycle,
            'taskAgent'      => $taskAgent,
            'pay_type'       => $taskPayType,
            'pay_section'    => $paySection,
            'attachment'     => $attachment,
            'comment'        => $comment,
            'merge'          => $request->all()
        ];
         return $this->theme->scope('manage.bidDetail',$data)->render();
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
	 * 任务配置
	 * 2017-09-11 by heike
     */
    public function bidConfig($id)
    {
	    $configs = ConfigModel::where('type','bid')->get()->toArray();
        $configs_data = array();
        foreach($configs as $k=>$v)
        {
            $configs_data[$v['alias']] = $v;
        }   
        $data = [
            'config'=>$configs_data,
            'id'=>$id
        ];
        return $this->theme->scope('manage.bidConfig', $data)->render();
    }
    /*
	*
	*任务配置修改
	*2017-09-11 by heike
	*/
	public function bidConfigUpdate(Request $request){
		$data = $request->except('_token');
			foreach($data as $Kda=>$Vta){
			   ConfigModel::where('type','bid')->where('alias',$Kda)->update(['rule'=>$Vta]);
			}			
			return redirect()->back()->with(['error'=>'修改成功！']);

		
	}
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}
