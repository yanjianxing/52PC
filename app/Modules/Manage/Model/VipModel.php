<?php

namespace App\Modules\Manage\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class VipModel extends Model
{
    //
    protected $table = 'vip';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id','name','price','price_time','num','status','vipconfigid','grade','created_at','vip_pic','vip_sale_price','vip_sale','vip_recommend','sort'
    ];

    public $timestamps = false;

    //获取vip 所有购买vip列表
    static  public function getVipList(){
        //获取vip列表
        $vip=self::where("status",2)->select("id","price","name","price_time","vipconfigid","grade","vip_pic","vip_sale_price","vip_sale","vip_recommend")->orderBy("sort", 'desc')->get()->toArray();
        $vipConfigId=\CommonClass::setArrayKey($vip,"vipconfigid");
        //获取次卡
        $subCard=VipConfigModel::LeftJoin("vip","vip_config.id","=","vip.vipconfigid")->where("vip_config.vip_cika",1)->whereIn("vip_config.id",array_keys($vipConfigId))
                               ->select("vip.name","vip.grade","vip.price_time","vip_config.vip_cika_price","vip_config.id","vip_config.vip_cika_num","vip_config.vip_cika_pic")->get()->toArray();
        //获取日卡
        $dayCard=VipConfigModel::LeftJoin("vip","vip_config.id","=","vip.vipconfigid")->where("vip_config.vip_rika",1)->whereIn("vip_config.id",array_keys($vipConfigId))
            ->select("vip.name","vip.grade","vip_config.vip_rika_price","vip_config.id","vip_config.vip_rika_num","vip_config.vip_rika_pic")->get()->toArray();
        return array_merge($vip,$subCard,$dayCard);
    }
    //获取折扣
    static  public function getDiscount($level){
        return VipModel::leftJoin("vip_config","vip.vipconfigid","=","vip_config.id")
            ->where("vip.grade",$level)->pluck("tool_zk");
    }


}
