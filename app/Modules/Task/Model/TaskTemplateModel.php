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

class TaskTemplateModel extends Model
{
    protected $table = 'task_template';
    public  $timestamps = false;  //关闭自动更新时间戳
    protected $fillable = [
        'title','content','cate_id','status','created_at'
    ];

    /**
     * 返回所有的模板数据
     * @return mixed
     */
    static function findAll()
    {
        return Self::where('status','=',0)->get()->toArray();
    }
    /*
     * 通过id查询模板信息
     */
    static function findById($id)
    {
        return Self::where('id','=',$id)->first();
    }
}
