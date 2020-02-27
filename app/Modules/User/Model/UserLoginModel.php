<?php

namespace App\Modules\User\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class UserLoginModel extends Model
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
     protected $table = 'user_login';

    public $timestamps = false;

    protected $fillable = [
        'id','uid', 'mobile','level','type','province','city','area','created_at','login_time','login_ip'
    ];


    /**
     * 获取当前用户登录信息
     */
    public function insertInformation($uid){
        //获取用户信息
        $userdate=UserModel::leftJoin('user_detail', 'users.id', '=', 'user_detail.uid')
            ->where('users.id',$uid)
            ->select('users.id','users.name','users.email','users.mobile','users.level','users.type','users.created_at','user_detail.province','user_detail.city','user_detail.area')
            ->first()
            ->toArray();
        //用户ip
        $ip=\CommonClass::getIp();
        if($ip=="::1"){
            $user_ip="101.201.50.189";
        }elseif(filter_var($ip, FILTER_VALIDATE_IP)){
            $user_ip=$ip;
        }else{
            $user_ip="101.201.50.189";
        }
        $location=\CommonClass::getAddress("$user_ip");
        $location=(filter_var($user_ip, FILTER_VALIDATE_URL))?$user_ip:"";
        if($location){
            //省
            $province=$location['region'];
            //市
            $city=$location['city'];
            //区
            $area=$location['area'];
        }else{
            $province='';
            $city='';
            $area='';
        }
        //存用户登录的信息
        $userinfo = array();
        $userinfo = [
                'uid' => $userdate['id'],
                'mobile' => $userdate['mobile'],
                'level' =>$userdate['level'],
                'type' =>$userdate['type'],
                'province' =>$province,
                'city' =>$city,
                'area' =>$area,
                'created_at' =>$userdate['created_at'],
                'login_ip' =>$user_ip,
                'login_time' => date('Y-m-d H:i:s', time())
        ];
        $users=DB::table('user_login')->insert($userinfo);
        return $users;
    }


}