<?php

namespace App\Modules\Task\Model;
use Illuminate\Database\Eloquent\Model;


//use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
//use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
//use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class TaskFollowModel extends Model
{
    protected $table = 'task_follow';
    public $timestamps=false;
    protected $fillable = [
        'id',
        'task_id',
        'manager_id',
        'uname',
        'follow_time',
        'desc',
        'created_at',
        'updated_at'
    ];


}
