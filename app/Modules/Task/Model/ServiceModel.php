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

class ServiceModel extends Model
{

    protected $table = 'service';

    /**
     * 计算服务需要的金额
     * @param $product_ids
     * @param array $vipConfig vip折扣
     * @return int
     * author: muker（qq:372980503）
     */
    static public function serviceMoney($product_ids,$vipConfig)
    {
        $arr = self::whereIn('id',$product_ids)->get()->toArray();
        $money = 0.00;
        if($arr){
            foreach($arr as $k => $v){
                switch($v['identify']){
                    case 'JIAJI':
                        $money = $money + round(($v['price']*$vipConfig['fast_off'])/100,2);
                        break;
                    case 'SIMIDUIJIE':
                        $money = $money + round(($v['price']*$vipConfig['open_off'])/100,2);
                        break;
                    case 'ZHIDING':
                        $money = $money + round(($v['price']*$vipConfig['top_off'])/100,2);
                        break;
                    default:
                        $money = $money + $v['price'];

                }
            }
        }
        return $money;
    }

}
