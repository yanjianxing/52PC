<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/6/6
 * Time: 16:19
 */
namespace App\Http\Middleware;

use App\Modules\Manage\Model\ManagerModel;
use Closure;
use Illuminate\Support\Facades\Session;
use App\Modules\Manage\Model\SystemLogModel;
use Illuminate\Support\Facades\Route;
use App\Modules\Manage\Model\Role;
class SystemLog
{

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        //逻辑处理
        $path = Route::currentRouteName();//路由别名
        $params = $request->all();//传送参数
        $operator = ManagerModel::getManager();//操作者信息
        if($path == 'loginCreate'){
            $username = $params['username'];
            $password = $params['password'];
            $userInfo = ManagerModel::where('username',$username)->select('id','password','salt')->first();
            if($userInfo){
                $password = ManagerModel::encryptPassword($password, $userInfo->salt);
                if ($userInfo->password == $password) {
                    $uid = $userInfo->id;
                }
                else{
                    $uid = 0;
                }
            }
            else{
                $uid = 0;
            }

        }
        else{
            $uid = $operator->id; //操作者id
            $username = $operator->username;//操作者用户名
        }
        $log_time = date('Y-m-d H:i:s');
        $log_content = '';
        $common_content = $username.'于'.$log_time;

        switch($path){
            case 'loginCreate'://登陆
                $log_content = $common_content .'登录';
                break;
            case 'baseConfigCreate'://任务模型开启或关闭
                $name = $params['name'];
                $desc = $params['desc'];
                if($params['status']){
                    $status = '开启';
                }
                else{
                    $status = '关闭';
                }
                $log_content = $common_content .'将模型名称：'.$name.'，是否开启：'.$status.'，模型说明：'.$desc.'修改成功';
                break;
            case 'rolesCreate'://创建用户组
                $log_content = $common_content .'创建了用户组';
                break;
            case 'managerCreate':
            case 'userCreate'://创建用户
                if($path == 'managerCreate'){
                    $name = $params['username'];
                }
                else if($path == 'userCreate'){
                    $name = $params['name'];
                }
                $log_content = $common_content .'创建用户'.$name;
                break;
            case 'userStatusUpdate'://禁用/激活用户
                $log_content = $common_content .'禁用/激活了用户';
                break;
            case 'managerDetailUpdate'://设置用户组
                $uid = $params['uid'];
                $userInfo = ManagerModel::find($uid);
                $name = $userInfo->username;
                $log_content = $common_content .'设置'.$name.'用户组';
                break;
            case 'messageUpdate': //修改信息模板
                $log_content = $common_content .'修改信息模板';
                break;
            case 'thirdLoginCreate'://配置第三方登录接口
                $log_content = $common_content .'配置第三方登陆接口';
                break;
            case 'cashoutUpdate'://审核申请提现或确认提现
                $log_content = $common_content .'进行提现审核处理';
                break;
            case 'articleUpdate'://编辑案例
                $log_content = $common_content .'编辑案例';
                break;
            case 'articleCreate'://添加案例
                $log_content = $common_content .'添加案例';
                break;
            case 'articleDelete'://删除案例
                $log_content = $common_content .'删除案例';
                break;
            case 'taskUpdate'://任务审核处理
                $log_content = $common_content .'审核任务处理';
                break;
            case 'handleRightsCreate'://维权处理
                $log_content = $common_content .'进行维权处理';
                break;
            case 'reportUpdate'://举报处理
                $log_content = $common_content .'进行举报处理';
                break;
            case 'attachmentDelete'://删除附件
                $log_content = $common_content .'删除附件';
                break;
        }
        

        //存入日志
        if($log_content && $uid){
            $user_type = Role::where('name',$username)->select('id')->first();
            $newData = [
                'uid'           => $uid,
                'username'      => $username,
                'log_content'   => $log_content,
                'created_at'    => $log_time,
                'user_type'     => isset($user_type)?$user_type->id:0,
                'IP'            => $request->ip()
            ];
            $system = SystemLogModel::create($newData);
        }

        return $next($request);

    }
}
