<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/6/23
 * Time: 16:35
 */
namespace App\Modules\User\Model;

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

class PhoneCodeModel extends Model
{
    protected $table = 'phone_auth';
    protected $fillable =
        [   'id',
            'phone',
            'code',
            'overdue_date',
            'created_at'
        ];
    public  $timestamps = false;  //关闭自动更新时间戳

}
