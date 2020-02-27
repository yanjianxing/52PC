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

class TaskTypeModel extends Model
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'task_type';
    public  $timestamps = false;  //关闭自动更新时间戳
    public $fillable = ['id','name','status','desc','created_at','alias'];

    /**
     *  获取任务模式别名
     * @param int $id 任务类型id
     * @return string
     * @author quanke 2017-09-12
     */
    static public function getTaskTypeAliasById($id)
    {
        $taskTypeAlias = 'xuanshang';
        $taskType = self::find($id);
        if(!empty($taskType)){
            $taskTypeAlias = $taskType['alias'];
        }
        return $taskTypeAlias;
    }

    /**
     * 根据任务类型别名获取任务类型id
     * @param string $alias 任务模式别名
     * @return int
     * @author quanke 2017-09-15
     */
    static public function getTaskTypeIdByAlias($alias)
    {
        $taskTypeId = 1;
        $taskType = TaskTypeModel::where('alias',$alias)->first();
        if($taskType){
            $taskTypeId = $taskType['id'];
        }
        return $taskTypeId;
    }
	/*
	 *获取任务的模式
	 *2017-09-13
	 *by heike
	*/
	static public function getTaskTypeAll(){
		return self::select('id','name','alias')->where(function($query){
			  $query->where('alias','xuanshang')
			        ->orwhere('alias','zhaobiao');
		})->get();
	}
	
}
