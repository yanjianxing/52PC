<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/5/31
 * Time: 11:55
 */
namespace App\Modules\Advertisement\Model;

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

class RePositionModel extends Model
{
    protected $table = 'recommend_position';
    protected $fillable =
        [   'id',
            'name',
            'code',
            'position',
            'num',
            'pic',
            'is_open',
            'type_id'
        ];
    public  $timestamps = false;  //关闭自动更新时间戳

}
