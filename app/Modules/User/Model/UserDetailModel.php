<?php

namespace App\Modules\User\Model;

use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\Order\Model\OrderModel;
use Illuminate\Database\Eloquent\Model;
use DB;
use Auth;

class UserDetailModel extends Model
{
    //
    protected $table = 'user_detail';

    protected $fillable = [
        'uid',
        'realname',
        'avatar',
        'mobile',
        'qq',
        'wechat',
        'card_number',
        'province',
        'city',
        'area',
        'address',
        'sign',
        'balance',
        'balance_status',
        'last_login_time',
        'alternate_tips',
        'nickname',
        'employer_praise_rate',
        'employee_praise_rate',
        'employee_num',
        'auth_type',
        'functional',
        'complay_name',
        'publish_task_num',
        'receive_task_num',
        'delivery_count',
        'goods_num',
        'inquiry_num',
        'sales_num',
        'function',
        'job_level'
    ];

    public function tags()
    {
        return $this->hasMany('App\Modules\User\Model\UserTagsModel', 'uid', 'uid');
    }

    public function user()
    {
        return $this->hasOne('App\Modules\User\Model\UserModel', 'id', 'uid');
    }

    public function shop()
    {
        return $this->hasOne('App\Modules\Shop\Models\ShopModel','uid','id');
    }

    public function goods()
    {
        return $this->hasMany('App\Modules\Shop\Models\GoodsModel','uid','id');
    }
    
    static public function getUserInfo($uid,$auth=false)
    {
        $infoArr = [];
        $info = self::where('uid',$uid)->with('user')->first();
        if($info){
            $district = DistrictModel::whereIn('id',[$info->province,$info->city])->select('id','name')->get()->toArray();
            $district = \CommonClass::setArrayKey($district,'id');
            $info->province = in_array($info['province'],array_keys($district)) ? $district[$info['province']] : [];
            $info->city = in_array($info['city'],array_keys($district)) ? $district[$info['city']] : [];
            $authArr = [];
            if($auth){
                $userAuth = AuthRecordModel::where('uid', $uid)
                    ->where(function($query){
                        $query->where(function($querys){
                            $querys->where('status', 2)->whereIn('auth_code',['bank','alipay']);
                        })->orwhere(function($querys){
                            $querys->where('status', 1)->whereIn('auth_code',['realname','enterprise','promise']);
                        });
                    })->select('uid','auth_code')->get()->toArray();
                $vipAuth = VipUserOrderModel::where('uid',$uid)->where('status',1)->select('uid')->get()->toArray();
                $vip = ['auth_code'=>'vip'];
                array_walk($vipAuth, function (&$value, $key, $vip) {
                    $value = array_merge($value, $vip);
                }, $vip);
                $authArr = array_pluck(array_merge($userAuth,$vipAuth),'auth_code');
            }
            $info->auth = $authArr;
            $comment = CommentModel::where('to_uid',$uid)->count();
            $goodComment = CommentModel::where('to_uid',$uid)->count();
            $info->good_comment = $goodComment;
            $info->total_comment = $comment;
            $infoArr = $info->toArray();
        }
        return $infoArr;
    }

    /**
     * 通过uid查询用户的信息
     * @param $uid
     * @return mixed
     */
    static public function findByUid($uid)
    {
        $result = UserDetailModel::where(['uid' => $uid])->first();

        return $result;
    }

    /**
     * 更新用户信息，第一次的时候创建
     * @param $data
     * @param $uid
     * @return mixed
     */
    static function updateData($data, $uid)
    {
        //如果用户是第一次设置资料就创建一条用户的资料
        UserDetailModel::firstOrCreate(['uid' => $uid]);
        $result = UserDetailModel::where('uid', '=', $uid)->update($data);
        return $result;
    }

    /**
     * 给用户充值
     * @param $uid
     * @param int $type  支付方式 1:余额 2:支付宝 3:微信
     * @param array $data
     * @return bool
     */
    static function recharge($uid, $type, array $data)
    {
        $status = DB::transaction(function () use ($uid, $type, $data) {
            //为用户充值
            UserDetailModel::where('uid', '=', $uid)->increment('balance', $data['money']);

            if(!empty($data['code']))
                OrderModel::where('code', $data['code'])->update(['status' => 1]);

            //产生财务记录 用户充值行为
            $financial = [
                'action'      => 3,
                'pay_type'    => $type,
                'pay_account' => $data['pay_account'],
                'pay_code'    => $data['pay_code'],
                'cash'        => $data['money'],
                'uid'         => $uid,
                'created_at'  => date('Y-m-d H:i:s', time()),
            ];
            FinancialModel::create($financial);

        });
        return is_null($status) ? true : false;
    }

    /**
     * 关闭支付密码提示
     *
     * @return mixed
     */
    static function closeTips()
    {
        $user = Auth::User();
        return self::where('uid', $user->id)->update(['alternate_tips' => 1]);
    }

    /**
     * 根据用户id获取用户地区信息
     * @param $uid 用户id
     * @return string
     */
    static function getAreaByUserId($uid)
    {
        $pre = UserDetailModel::join('district', 'user_detail.province', '=', 'district.id')
            ->select('district.name')->where('user_detail.uid', $uid)->first();
        $city = UserDetailModel::join('district', 'user_detail.city', '=', 'district.id')
            ->select('district.name')->where('user_detail.uid', $uid)->first();
        $province = $pre ? $pre->name : '';
        $city = $city ? $city->name : '';
        $addr = $province . $city;
        return $addr;
    }

    /**
     * 查询雇佣服务商信息
     * @param $uid
     * @return mixed
     */
    static function employeeData($uid)
    {
        $employee = self::select('user_detail.*', 'ur.name as user_name', 'ur.email_status', 'dp.name as province_name', 'dc.name as city_name')
            ->with('tags')
            ->where('user_detail.uid', $uid)
            ->join('users as ur', 'ur.id', '=', 'user_detail.uid')
            ->leftjoin('district as dp', 'dp.id', '=', 'user_detail.province')
            ->leftjoin('district as dc', 'dc.id', '=', 'user_detail.city')
            ->first()->toArray();
        $tags_id = \CommonClass::getList($employee['tags'], 'tag_id');

        //查询组装服务商的tags
        if (!empty($tags_id)) {
            $tags = TagsModel::findById($tags_id);
            $employee['tags'] = $tags;
        }

        //查询用户的认证情况
        $auth_data = AuthRecordModel::where('uid', $uid)->where('status', 1)->lists('auth_code')->toArray();

        $employee['auth'] = $auth_data;

        //计算好评率
        if ($employee['receive_task_num'] != 0) {
            $employee['good_rate'] = floor($employee['employee_praise_rate'] * 100 / $employee['receive_task_num']);
        } else {
            $employee['good_rate'] = 100;
        }

        return $employee;
    }

    static function employerData($uid)
    {
        $employee = self::select('user_detail.*', 'ur.name as user_name', 'ur.email_status', 'dp.name as province_name', 'dc.name as city_name')
            ->with('tags')
            ->where('user_detail.uid', $uid)
            ->join('users as ur', 'ur.id', '=', 'user_detail.uid')
            ->leftjoin('district as dp', 'dp.id', '=', 'user_detail.province')
            ->leftjoin('district as dc', 'dc.id', '=', 'user_detail.city')
            ->first()->toArray();

        //查询用户的认证情况
        $auth_data = AuthRecordModel::where('uid', $uid)->where('status', 1)->lists('auth_code')->toArray();

        $employee['auth'] = $auth_data;

        //计算好评率
        if ($employee['receive_task_num'] != 0) {
            $employee['good_rate'] = floor($employee['employer_praise_rate'] * 100 / $employee['receive_task_num']);
        } else {
            $employee['good_rate'] = 100;
        }

        return $employee;
    }
}
