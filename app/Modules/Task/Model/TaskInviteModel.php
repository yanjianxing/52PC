<?php

namespace App\Modules\Task\Model;

use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\MessageTemplateModel;
use App\Modules\User\Model\CommentModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserTagsModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class TaskInviteModel extends Model
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'task_invite';
    public  $timestamps = false;  //关闭自动更新时间戳
    public $fillable = ['id','task_id','uid'];


    /**
     * 邀约高手通知
     * @param $task
     * @param $uid
     */
    static public function sendInviteMsg($task,$uid)
    {
        $template = MessageTemplateModel::where('code_name', 'invite_user')->where('is_open', 1)->first();
        if ($template) {
            $user = UserModel::where('id', $uid)->first();//必要条件
            $employerUser = UserModel::where('id', $task['uid'])->first();//必要条件
            $domain = \CommonClass::getDomain();
            //组织好系统消息的信息
            //发送系统消息
            $messageVariableArr = [
                'username'     => $user['name'],
                'employername' => $employerUser['name'],
                'title'        => '<a href="'.$domain.'/kb/'. $task['id'].'">'.$task['title'].'</a>',
            ];
            if($template->is_on_site == 1){
                \MessageTemplateClass::getMeaasgeByCode('invite_user',$user['id'],1,$messageVariableArr,$template['name']);
            }
            //发送邮件
            if($template->is_send_email == 1){
                $email = $user->email;
                \MessageTemplateClass::sendEmailByCode('invite_user',$email,$messageVariableArr,$template['name']);
            }
            if($template->is_send_mobile == 1 && $template->code_mobile && $user->mobile){
                $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                $templates = [
                    $scheme => $template->code_mobile,
                ];
                $messageVariableArr = [
                    'username'     => $user['name'],
                    'employername' => $employerUser['name'],
                    'title'        => $task['title'],
                ];

                \SmsClass::sendSms($user->mobile, $templates, $messageVariableArr);
            }
        }
    }

    /**
     *.统计推送给我的项目的总数
     * @param $task
     * @param $uid
     */
    static public function countTaskInvite($uid='')
    {
        if(!empty($uid)){
            //统计推送给我的项目的总数（1、用户没有竞标过的，2、此项目正在竞标中）

            //获取推送给用户的项目总数
            $taskId=TaskInviteModel::leftJoin('task','task_invite.task_id','=','task.id')
                ->where("task_invite.uid",$uid)//推送给当前用户
                ->where('task.status','2')//项目竞标中
                ->distinct()
                ->lists("task_invite.task_id",'task.title')
                ->toArray();

            //查询当前用户竞标过的所有项目
            $useralltask=WorkModel::where("uid",$uid)->distinct()->lists("task_id")->toArray();

            //去除用户竞标过得项目
            foreach ($taskId as $k=>$v){
                if(in_array($v,$useralltask)){
                    unset($taskId[$k]);
                }
            }
            //任务的统计
            $taskCount=count($taskId);
            return $taskCount ? $taskCount : 0;
        }
        return 0;
    }
}
