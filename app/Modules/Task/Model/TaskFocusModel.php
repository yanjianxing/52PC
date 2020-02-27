<?php

namespace App\Modules\Task\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class TaskFocusModel extends Model
{
    protected $table = 'task_focus';
    public  $timestamps = false;  //关闭自动更新时间戳
    public $fillable = ['uid','task_id','created_at','type'];

}
