<?php

namespace App\Modules\User\Model;

use App\Modules\Manage\Model\ConfigModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class UserVipConfigModel extends Model
{

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'user_vip_config';

    protected $primaryKey = 'id';

    protected $fillable = [
        'id',
        'uid',
        'bid_num',
        'bid_price',
        'skill_num',
        'inquiry_num',
        'accept_inquiry_num',
        'created_at',
        'scheme_num',
        'stick_discount',
        'urgent_discount',
        'private_discount',
        'level',
        'is_show',
        'is_invited',
        'appliy_num',
        'is_logo',
        'is_nav',
        'is_slide',
    ];
    public $timestamps =false;

    /**
     * 根据用户id获取vip配置
     * @param $uid
     * @return array
     */
    static public function getConfigByUid($uid)
    {
        $vipConfig = UserVipConfigModel::where('uid',$uid)->first();
        $topOff = 100;
        $fastOff = 100;
        $openOff = 100;
        $hgoldOff = 100;
        $lgoldOff = 100;
        $bidNum = 0;
        $bidPrice = 0.00;
        $skill_num = 0;
        $inquiry_num = 0;
        $accept_inquiry_num=0;
        $scheme_num = 0;
        $level = 1;
        $is_show = 0;
        $is_logo=0;
        $is_invited = 0;
        $appliy_num = 0;
        $is_nav=0;
        $is_slide=0;
        $consult_discount=10;
        if($vipConfig){
            $topOff = $vipConfig->stick_discount > 0 ? $vipConfig->stick_discount * 10 : 100;
            $fastOff = $vipConfig->urgent_discount > 0 ? $vipConfig->urgent_discount * 10 : 100;
            $openOff = $vipConfig->private_discount > 0 ? $vipConfig->private_discount * 10 : 100;
            $hgoldOff = $vipConfig->hgold_discount > 0 ? $vipConfig->hgold_discount * 10 : 100;
            $lgoldOff = $vipConfig->lgold_discount > 0 ? $vipConfig->lgold_discount * 10 : 100;
            $bidNum = $vipConfig->bid_num;
            $bidPrice = $vipConfig->bid_price;
            $skill_num = $vipConfig->skill_num;
            $inquiry_num = $vipConfig->inquiry_num;
            $accept_inquiry_num=$vipConfig->accept_inquiry_num;
            $scheme_num = $vipConfig->scheme_num;
            $level = $vipConfig->level;
            $is_show = $vipConfig->is_show;
            $is_invited = $vipConfig->is_invited;
            $appliy_num = $vipConfig->appliy_num;
            $is_logo=$vipConfig->is_logo;
            $is_nav=$vipConfig->is_nav;
            $is_slide=$vipConfig->is_slide;
            $consult_discount=$vipConfig->consult_discount;
        }else{
            $user=UserModel::where("id",$uid)->where("level",1)->first();
            if($user){
                $userConfig=ConfigModel::getConfigByType("user");
                self::create(
                    [
                        'uid'=>$uid,
                        'bid_num'=>$userConfig['user_bid_num'],
                        'bid_price'=>$userConfig['user_bid_price'],
                        'skill_num'=>$userConfig['user_skill_num'],
                        'appliy_num'=>$userConfig['user_appliy_num'],
                        //'appliy_num'=>$userConfig['user_bid_price'],
                        'inquiry_num'=>$userConfig['user_inquiry_num'],
                        'accept_inquiry_num'=>$userConfig['user_accept_inquiry_num'],
                        'scheme_num'=>$userConfig['user_scheme_num'],
                        'stick_discount'=>$userConfig['user_stick_discount'],
                        'urgent_discount'=>$userConfig['user_urgent_discount'],
                        'private_discount'=>$userConfig['user_private_discount'],
                        'train_discount'=>$userConfig['user_train_discount'],
                        'hgold_discount'=>$userConfig['user_hgold_discount'],
                        'lgold_discount'=>$userConfig['user_lgold_discount'],
                        'level'=>1,
                        'created_at'=>date("Y-m-d H:i:s"),
                        'is_show'=>$userConfig['user_is_show'],
                        //'is_Invited'=>$userConfig['user_is_show'],
                        'is_logo'=>$userConfig['user_is_logo'],
                        'is_nav'=>$userConfig['user_is_nav'],
                        'is_slide'=>$userConfig['user_is_slide'],
                    ]
                );
            }
        }

        return $config = [
            'top_off'       => $topOff,
            'fast_off'      => $fastOff,
            'open_off'      => $openOff,
            'hgold_off'      => $hgoldOff,
            'lgold_off'      => $lgoldOff,
            'bid_num'       => $bidNum,
            'bid_price'     => $bidPrice,
            'skill_num'     => $skill_num,
            'inquiry_num'   => $inquiry_num,
            'accept_inquiry_num'=>$accept_inquiry_num,
            'scheme_num'    => $scheme_num,
            'level'         => $level,
            'is_show'       => $is_show,
            'is_invited'    => $is_invited,
            'appliy_num'    => $appliy_num,
            'is_logo'      =>$is_logo,
            'is_nav'      =>$is_nav,
            'is_slide'   =>$is_slide,
            'consult_discount'=>$consult_discount,
        ];
    }

    //修改里面的配置
    static public function updateConfigData($data){
        self::where("level",1)->update($data);
        return ;
    }

}