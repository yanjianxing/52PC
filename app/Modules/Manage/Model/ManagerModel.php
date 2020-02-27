<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Zizaco\Entrust\Traits\EntrustUserTrait;

class ManagerModel extends Model
{
    use EntrustUserTrait;
    //
    protected $table = 'manager';

    protected $fillable = [
        'username', 'password', 'salt'
    ];


    /**
     * 后台账户密码加密
     *
     * @param $password
     * @param string $sign
     * @return string
     */
    static function encryptPassword($password, $sign = '')
    {
        return md5(md5($password . $sign));
    }

    /**
     * 验证后台登录用户密码
     *
     * @param $username
     * @param $password
     * @return bool
     */
    static function checkPassword($username, $password)
    {
        $user = ManagerModel::where('username', $username)->first();
        if ($user) {
            $password = self::encryptPassword($password, $user->salt);
            if ($user->password == $password) {
                return 'yes';
            }else{
                return false;
            }
        }else{
             return "no";
        }

    }

    /**
     * 后台登录保存管理员信息
     *
     * @param $manager
     * @return mixed
     */
    static function managerLogin($manager)
    {
        return Session::put('manager', $manager);
    }

    /**
     * 获得当前登录管理员信息
     *
     * @return mixed
     */
    static function getManager()
    {
        return Session::get('manager');
    }
}
