<?php

namespace App\Modules\User\Model;

use Illuminate\Database\Eloquent\Model;

class UserDepositModel extends Model
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
     protected $table = 'user_deposit';

    public $timestamps = false;

    protected $fillable = [
        'id','order_num', 'uid','price','status','created_at','payment_at','type',"reason"
    ];

    //给用户发送短信
    static  public function sendSms($uid,$type,$money){
        $userInfo=UserModel::find($uid);
            $user = [
                'uid'    =>$userInfo->id,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
        //获取用户信息
          switch ($type){
              case 1:
                  break;
              case 2:
                  break;
              case 3:
                  break;
          }

        $templateArr = [
            'username' =>$userInfo->name,
            'price'     =>$money,
            'remainder'   =>UserDetailModel::where("uid",$uid)->pluck("balance"),
        ];
        \MessageTemplateClass::sendMessage($type,$user,$templateArr,$templateArr);
    }



}