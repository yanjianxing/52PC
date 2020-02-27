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
use Illuminate\Support\Facades\Crypt;
use DB;

class PromoteModel extends Model
{
    protected $table = 'promote';

    public $timestamps = false;

    protected $fillable = [
        'id', 'from_uid','to_uid','price','finish_conditions','type','status','created_at','updated_at'
    ];

    /**
     * 生成用户某一推广类型的推广链接
     * @param $uid   推广人uid
     * @return string  推广链接
     */
    public static function createPromoteUrl($uid)
    {
        $param = Crypt::encrypt($uid);
        $url = url('user/promote/'.$param);
        return $url;
    }


    /**
     * 根据推广链接参数获取用户id和推广类型
     * @param $param
     * @return array
     */
    public static function getUrlInfo($param)
    {
        $uid = Crypt::decrypt($param);
        return $uid;

    }


    /**
     * 结算推广人的推广赏金
     * @param $uid 推广人id
     * @return bool
     */
    public static function settlementByUid($uid)
    {
        //查询我的未结算推广关系
        $promote = PromoteModel::where('from_uid',$uid)->where('status',1)->get()->toArray();
        if(!empty($promote)){
            $realnameUid = array();
            $emailUid = array();
            $payUid = array();
            foreach($promote as $k => $v){
                if($v['finish_conditions'] == 1){
                    $realnameUid[] = $v['to_uid'];
                }
                if($v['finish_conditions'] == 2){
                    $emailUid[] = $v['to_uid'];
                }
                if($v['finish_conditions'] == 3){
                    $payUid[] = $v['to_uid'];
                }
            }
            if(!empty($realnameUid)){
                PromoteModel::getFinishPromoteByUid($uid,$realnameUid,1);
            }
            if(!empty($emailUid)){
                PromoteModel::getFinishPromoteByUid($uid,$emailUid,2);
            }
            if(!empty($payUid)){
                PromoteModel::getFinishPromoteByUid($uid,$payUid,3);
            }
        }else{
            return true;
        }
    }

    /**
     * @param $uid    推广人id
     * @param $toUid  被推广人id数组
     * @param $type   完成推广条件
     * @return bool
     */
    public static function getFinishPromoteByUid($uid,$toUid,$type)
    {
        switch($type){
            case 1:
                //查询完成实名认证的用户
                $res = RealnameAuthModel::whereIn('uid',$toUid)->where('status',1)->get()->toArray();
                if(!empty($res)){
                    $toUidArr = array();
                    foreach($res as $k => $v){
                        $toUidArr[] = $v['uid'];
                    }
                    if(!empty($toUidArr)){
                        $toUidArr = array_unique($toUid);
                        //查询需要结算的推广关系
                        PromoteModel::getFinishByUid($uid,$toUidArr);
                    }else{
                        return true;
                    }
                }else{
                    return true;
                }
                break;
            case 2:
                //查询完成邮箱认证的用户
                $res = UserModel::whereIn('id',$toUid)->where('email_status',2)->get()->toArray();
                if(!empty($res)){
                    $toUidArr = array();
                    foreach($res as $k => $v){
                        $toUidArr[] = $v['id'];
                    }
                    if(!empty($toUidArr)){
                        $toUidArr = array_unique($toUid);
                        //查询需要结算的推广关系
                        PromoteModel::getFinishByUid($uid,$toUidArr);
                    }else{
                        return true;
                    }
                }else{
                    return true;
                }
                break;
            case 3:
                //完成支付认证的用户
                $res = AuthRecordModel::where('uid',$toUid)->where('status',2)->whereIn('auth_code',['bank','alipay'])->get()->toArray();
                if(!empty($res)){
                    $toUidArr = array();
                    foreach($res as $k => $v){
                        $toUidArr[] = $v['uid'];
                    }
                    if(!empty($toUidArr)){
                        $toUidArr = array_unique($toUid);
                        //查询需要结算的推广关系
                        PromoteModel::getFinishByUid($uid,$toUidArr);
                    }else{
                        return true;
                    }
                }else{
                    return true;
                }
                break;
        }
    }

    /**
     * 结算推广人赏金
     * @param $fromUid 推广人uid
     * @param $toUid   被推广人id
     * @return mixed
     */
    public static function getFinishByUid($fromUid,$toUid)
    {
        $status = DB::transaction(function() use ($fromUid,$toUid){
            $price = PromoteModel::where('from_uid',$fromUid)->whereIn('to_uid',$toUid)->sum('price');
            //平台结算金额
            UserDetailModel::where('uid', $fromUid)->increment('balance', $price);
            //生成财务记录
            $financeData = [
                'action' => 14, //推广注册赏金
                'pay_type' => 1,
                'cash' => $price,
                'uid' => $fromUid,
                'created_at' => date('Y-m-d H:i:s', time()),
            ];
            FinancialModel::create($financeData);
            $arr = array(
                'status' => 2,
                'updated_at' => date('Y-m-d H:i:s',time())
                );
            PromoteModel::where('from_uid',$fromUid)->whereIn('to_uid',$toUid)
                ->update($arr);
            return true;

        });
        return $status;

    }

    /**
     * 创建推广关系
     * @param $fromUid
     * @param $toUid
     * @return bool|static
     */
    public static function createPromote($fromUid,$toUid)
    {
        //查询是否开启推广及详情
        $promoteType = PromoteTypeModel::where('code_name','ZHUCETUIGUANG')->first();
        if($promoteType){
            $arr = array(
                'from_uid'          => $fromUid,
                'to_uid'            => $toUid,
                'price'             => $promoteType->register_price,
                'finish_conditions' => 1,
                'type'              => 1,
                'status'            => 2,
                'created_at'        => date('Y-m-d H:i:s',time())
            );
            //增加推广注册关系
            $res = PromoteModel::create($arr);
            PromoteTypeModel::putPromote($fromUid,1);
            return $res;
        }
        return false;
    }


}