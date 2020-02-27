<?php

namespace App\Modules\User\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class OauthBindModel extends Model
{
    //
    protected $table = 'oauth_bind';

    protected $fillable = [
        'oauth_id', 'oauth_nickname', 'oauth_type', 'uid', 'created_at'
    ];

    public $timestamps = false;


    /**
     * 第三方授权事务
     *
     * @param $oauthInfo
     * @return bool
     */
    static function oauthLoginTransaction($oauthInfo)
    {
        $res = UserModel::where('name', $oauthInfo['oauth_nickname'])->first();
        if (!empty($res)){
            $oauthInfo['oauth_nickname'] = $oauthInfo['oauth_nickname'] . \CommonClass::random(4);
        }

        $status = DB::transaction(function() use ($oauthInfo){
            $salt = \CommonClass::random(4);
            $randPassword = \CommonClass::random(6);
            $username = time().\CommonClass::random(4);
            $now = date('Y-m-d H:i:s');
            $userArr = [
                'name' => $username,
                // 'name' =>$_SERVER['SERVER_NAME'].date("YmdHis").$salt, //$oauthInfo['oauth_nickname'],
                'salt' => $salt,
                'password' => UserModel::encryptPassword($randPassword, $salt),
                'alternate_password' => UserModel::encryptPassword($randPassword, $salt),
                'last_login_time' => $now,
                'created_at' => $now,
                'updated_at' => $now,
                'status' => 1
            ];
            $uid = UserModel::insertGetId($userArr);
            $oauthInfo['uid'] = $uid;
            $oauthInfo['created_at'] = $now;
            OauthBindModel::create($oauthInfo);
            $userDetail = [
                'uid' => $uid,
                'last_login_time' => $now,
                'created_at' => $now,
                'updated_at' => $now,
                'nickname'=>$oauthInfo['oauth_nickname']?$oauthInfo['oauth_nickname']:$username,
            ];
            UserDetailModel::create($userDetail);
            $messageVariableArr= [
                    'username'  => $oauthInfo['oauth_nickname'],
                    'password'  => $randPassword,
                ];

            UserModel::sendfreegrant($uid,1);//自动发放
            
            /*发送站内信*/
            \MessageTemplateClass::getMeaasgeByCode('register_password',$uid,1,$messageVariableArr,$oauthInfo['oauth_nickname']);
            return $uid;
        });
        return $status;

    }


}
