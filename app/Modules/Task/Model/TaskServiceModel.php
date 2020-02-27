<?php

namespace App\Modules\Task\Model;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Gregwar\Captcha\CaptchaBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class TaskServiceModel extends Model
{
    protected $table = 'task_service';
    protected $fillable = ['task_id','service_id','created_at'];
    public  $timestamps = false;  //关闭自动更新时间戳
    /**
     * 创建任务服务关系数据
     * @param $task_id
     * @param $service_ids
     */
    static function createOne($task_id, $service_ids)
    {
        foreach($service_ids as $k=>$v)
        {
            $model = new TaskServiceModel;
            $model->task_id = $task_id;
            $model->service_id = $v;
            $model->created_at = date('Y-m-d H:i:s',time());
            $result = $model->save();
            if(!$result)
            {
                return false;
            }
        }
        return true;
    }

    /**
     * 根据任务id查询任务的服务id
     * @param $id
     */
    static function findByTid($id)
    {
        $data = TaskServiceModel::select('task_service.service_id')
            ->where('task_id','=',$id)->get()->toArray();
        return $data;
    }

    /**
     * 根据任务id查找任务的服务信息
     * @param $id
     * @return mixed
     */
    static function findBy($id)
    {
        $data = TaskServiceModel::select('task_service.*','b.price','b.id as product_id')
            ->where('task_service.task_id','=',$id)
            ->join('service as b','b.id','=','task_service.service_id')
            ->get()->toArray();
        return $data;
    }

}
