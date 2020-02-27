<?php
namespace App\Modules\Activity\Model;

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

class ActivityListModel extends Model
{
    protected $table = 'activity_list';
    protected $fillable =
        [   'id',
            'uid',
            'img',
            'created_at',
            'type',
            'name',
            'company',
            'job',
            'department',
            'email',
            'mobile',
            'productname',
            'province',
            'city',
            'area',
            'money',
            'num',
        ];
    public  $timestamps = false;  //关闭自动更新时间戳

}
