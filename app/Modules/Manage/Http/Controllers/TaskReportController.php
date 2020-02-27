<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Http\Requests;
use App\Http\Controllers\BasicController;
use App\Modules\Manage\Model\MessageTemplateModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskReportModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\UserModel;
use EasyWeChat\User\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskReportController extends ManageController
{
    public $user;
    public function __construct()
    {
        parent::__construct();
        $this->user = $this->manager;
        $this->initTheme('manage');
        $this->theme->setTitle('交易举报');
        $this->theme->set('manageType', 'TaskReport');
    }

    /**
     * 用户举报列表
     * @param Request $request
     * @return mixed
     */
    public function reportList(Request $request)
    {
        $data = $request->all();
        $query = TaskReportModel::select('task_report.*','ud.name as from_nickname','userd.name as to_nickname','mg.username as handle_nickname');
        //举报人筛选
        if($request->get('username'))
        {
            $query = $query->where('ud.name','like','%'.$request->get('username').'%');
        }
        //举报类型筛选
        if($request->get('reportType') && $request->get('reportType')!=0)
        {
            $query = $query->where('task_report.type',$request->get('reportType'));
        }
        //举报状态筛选
        if($request->get('reportStatus') && $request->get('reportStatus')!=0)
        {
            $query = $query->where('task_report.status',$request->get('reportStatus')-1);
        }
        //时间筛选
        $timeType = 'task_report.created_at';
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
        $reports_page = $query->join('users as ud','ud.id','=','task_report.from_uid')
            ->leftjoin('users as userd','userd.id','=','task_report.to_uid')
            ->leftjoin('manager as mg','mg.id','=','task_report.handle_uid')
            ->orderBy('task_report.id','DESC')
            ->paginate($page_size);
        $reports = $reports_page->toArray();
        //查询举报的附件
        foreach($reports['data'] as $k=>$v)
        {
            $attachment_ids = json_decode($v['attachment_ids'],true);
            $attachment = AttachmentModel::whereIn('id',$attachment_ids)->get()->toArray();
            $reports['data'][$k]['attachment'] = $attachment;
        }
        $report_text = [
            'type'=>[
                1=>'滥发广告',
                2=>'违规信息',
                3=>'虚假交换',
                4=>'涉嫌抄袭',
                5=>'重复交稿',
                6=>'其他',
                0=>'其他'
            ]
        ];
        $reports['data'] = \CommonClass::intToString($reports['data'],$report_text);
        $view = [
            'reports'=>$reports,
            'reports_page'=>$reports_page,
            'merge'=>$data,
        ];

        return $this->theme->scope('manage.taskreport', $view)->render();
    }

    /**
     * 单个删除
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reportDelet($id)
    {
        //删除指定id的举报记录
        $result = TaskReportModel::destroy($id);
        if(!$result)
            return redirect()->to('/manage/reportList')->with(['error'=>'删除失败！']);

        return redirect()->to('/manage/reportList')->with(['massage'=>'删除成功！']);
    }
    /**
     * 批量删除
     * @param Request $request
     */
    public function reportDeletGroup(Request $request)
    {
        $data = $request->except('_token');

        $result = TaskReportModel::destroy($data['ids']);

        if(!$result)
            return redirect()->to('/manage/reportList')->with(['error'=>'删除失败!']);

        return redirect()->to('/manage/reportList')->with(['massage'=>'删除成功！']);
    }

    /**
     * 举报详情页面
     * @param $id
     */
    public function reportDetail($id)
    {
        //获取上一项id
        $preId = TaskReportModel::where('id', '>', $id)->min('id');
        //获取下一项id
        $nextId = TaskReportModel::where('id', '<', $id)->max('id');
        $report = TaskReportModel::where('id',$id)->first();
        $task = TaskModel::where('id',$report['task_id'])->first();
        $from_user = UserModel::select('users.*','users.name as nickname','ud.mobile','ud.qq')
            ->where('users.id',$report['from_uid'])
            ->leftjoin('user_detail as ud','ud.uid','=','users.id')
            ->first();

        $to_user = UserModel::select('users.*','users.name as nickname','ud.mobile','ud.qq')
            ->where('users.id',$report['to_uid'])
            ->leftjoin('user_detail as ud','ud.id','=','users.id')
            ->first();
        //查询所有的附件
        $attachment = [];
        if(!empty(json_decode($report['attachment_ids'])))
        {
            $attachment = AttachmentModel::whereIn('id',json_decode($report['attachment_ids']))->get();
        }
        $view = [
            'report'=>$report,
            'from_user'=>$from_user,
            'to_user'=>$to_user,
            'task'=>$task,
            'preId'=>$preId,
            'nextId'=>$nextId,
            'attachment'=>$attachment
        ];

        return $this->theme->scope('manage.reportdetail', $view)->render();
    }

    /**
     * 处理举报
     * @param $id
     */
    public function handleReport(Request $request)
    {
        $data = $request->except('_token');
        $report = TaskReportModel::where('id',$data['id'])->first();
        $user_id = $this->user['id'];
        //判断当前举报是否处理
        if($report['status']==1)
        {
            return redirect()->back()->with(['error'=>'当前举报已经处理，无需处理！']);
        }

        $status = DB::transaction(function() use($data,$report,$user_id){
            $status = 0;
            $error = '请选择处理方式';
            $handleDesc = '';
            //根据不同的处理方式作出不同的处理
            if($data['handle']==0)
            {
                //屏蔽稿件
                //验证稿件没有中标
                $work_data = WorkModel::where('id',$report['work_id'])->first();
                if($work_data['status']==0)
                {
                    WorkModel::where('id',$report['work_id'])->update(['forbidden'=>1]);
                    $status = 1;
                    $message = '处理成功';
                    $handleDesc = '屏蔽稿件';
                }else{
                    $error = '稿件已经中标无法屏蔽稿件！';
                }
            }else if($data['handle']==1)
            {
                $status = 1;
                $message = '处理成功';
                $handleDesc = '举报无效';
            }else if($data['handle']==2)
            {
                //禁用被告的账号
                UserModel::where('id',$report['to_uid'])->update(['status'=>2]);
                $status = 1;
                $message = '处理成功';
                $handleDesc = '账号禁用';
            }
            //修改举报记录状态
            if($status==1)
            {
                $task_report = [
                    'status'=>$status,
                    'handle_uid'=>$user_id,
                    'handled_at'=>date('Y-m-d H:i:s',time()),
                    'handle_type'=>$data['handle']
                ];
                TaskReportModel::where('id',$report['id'])->update($task_report);

                // 发送举报处理信息
                $template = MessageTemplateModel::where('code_name', 'report_result ')->where('is_open', 1)->first();
                if ($template) {
                    $task = self::where('id', $report['task_id'])->first()->toArray();
                    $user = UserModel::where('id', $report['uid'])->first();//必要条件
                    $site_name = \CommonClass::getConfig('site_name');//必要条件
                    $domain = \CommonClass::getDomain();

                    //信息变量
                    $messageVariableArr = [
                        'username' => $user['name'],
                        'href' => $domain . '/kb/' . $task['id'],
                        'tasktitle' => $task['title'],
                        'result' => $handleDesc,
                        'website' => $site_name,
                    ];
                    if($template->is_on_site == 1){
                        \MessageTemplateClass::getMeaasgeByCode('report_result',$user['id'],1,$messageVariableArr,$template['name']);
                    }

                    if($template->is_send_email == 1){
                        $email = $user->email;
                        \MessageTemplateClass::sendEmailByCode('report_result',$email,$messageVariableArr,$template['name']);
                    }

                }

                return $message;
            }else{
                return $error;
            }
        });
     //修改成功
        return redirect()->back()->with(['message'=>$status]);
    }
}
