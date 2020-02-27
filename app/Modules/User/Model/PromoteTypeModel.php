<?php
/**
 * Created by PhpStorm.
 * User: KEKE-1003
 * Date: 2016/7/12
 * Time: 16:08
 */
namespace App\Modules\User\Model;
use App\Modules\Finance\Model\FinancialModel;
use Illuminate\Database\Eloquent\Model;

class PromoteTypeModel extends Model
{
    protected $table = 'promote_type';

    public $timestamps = false;

    protected $fillable = [
        'id','name','code_name','finish_conditions','type','is_open','created_at','updated_at','register_price','apply_price','bags_price'
    ];

    /**
     * 获得推广赏金
     * @param $uid
     * @param $type
     * @return bool
     */
    static public function putPromote($uid,$type)
    {
        $promote = PromoteModel::where('to_uid',$uid)->first();
        if(!$promote){
            return true;
        }
        $config = self::where('code_name','ZHUCETUIGUANG')->where('is_open',1)->first();
        if(!$config){
            return true;
        }
        $fromUid = $promote->from_uid;
        $price = 0;
        switch($type){
            case 1://注册获得
                $price = $config->register_price;
                break;
            case 2://发布任务
                $price = $config->bags_price;
                break;
            case 3://申请任务
                $price = $config->apply_price;
                break;
        }
        if($price > 0){
            $res = UserDetailModel::where('uid',$fromUid)->increment('balance',$price);
            if($res){
                if($type != 1){
                    PromoteModel::create([
                        'from_uid'          => $fromUid,
                        'to_uid'            => $uid,
                        'price'             => $price,
                        'finish_conditions' => $type,
                        'type'              => $type,
                        'status'            => 2,
                        'created_at'        => date('Y-m-d H:i:s'),
                        'updated_at'        => date('Y-m-d H:i:s'),
                    ]);
                }
                $remainder = UserDetailModel::where('uid',$fromUid)->first()->balance;
                FinancialModel::create([
                    'action'     => 14,
                    'pay_type'   => 1,
                    'cash'       => $price,
                    'uid'        => $fromUid,
                    'created_at' => date('Y-m-d H:i:s'),
                    'status'     => 1,
                    'remainder'  => $remainder,
                    'related_id' => $uid
                ]);
            }
        }
        return true;
    }
}