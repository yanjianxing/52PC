<?php

namespace App\Modules\User\Model;
use App\Modules\Shop\Models\ProgrammeEnquiryMessageModel;
use App\Modules\Shop\Models\ProgrammeInquiryPayModel;
use App\Modules\Manage\Model\PromoteFreegrantModel;
use App\Modules\Manage\Model\PromoteFreegrantUserlistModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\UserCouponModel;
use App\Modules\Manage\Model\CouponModel;
use App\Modules\User\Model\MessageReceiveModel;
use App\Modules\Manage\Model\MessageTemplateModel;
use App\Modules\Task\Model\WorkModel;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserModel extends Model implements AuthenticatableContract,
    AuthorizableContract,
    CanResetPasswordContract
{
    use Authenticatable, Authorizable, CanResetPassword;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';

    protected $primaryKey = 'id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'id','name', 'email', 'email_status', 'mobile', 'password', 'alternate_password', 'salt', 'status', 'overdue_date', 'validation_code', 'expire_date',
        'reset_password_code', 'remember_token','source','type',"birth_date","shop_template","shop_template_stauts",'forbidden_at','is_phone_login','unique','openid'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token','alternate_password'];


    public function detail()
    {
        return $this->hasOne('App\Modules\User\Model\UserDetailModel','uid','id')->select('uid','nickname','avatar');
    }

    public function shop()
    {
        return $this->hasOne('App\Modules\Shop\Models\ShopModel','uid','id');
    }

    public function goods()
    {
        return $this->hasMany('App\Modules\Shop\Models\GoodsModel','uid','id');
    }

    /**
     * 密码加密
     *
     * @param $password
     * @param string $sign
     * @return string
     */
    static function encryptPassword($password, $sign = '')
    {
        return md5(md5($password).$sign);
    }

    /**
     * 检查账户密码
     *
     * @param $username
     * @param $password
     * @return bool
     */
    static function checkPassword($username, $password)
    {
        $user = UserModel::where('name', $username)
            ->orWhere('email', $username)->orWhere('mobile', $username)->first();
        if ($user) {
            $password = self::encryptPassword($password, $user->salt);
            if ($user->password === $password) {
                return true;
            }
        }
        return false;
    }
    /**
     * 检查用户支付密码
     *
     * @param $email
     * @param $password
     * @return bool
     */
    static function checkPayPassword($email, $password)
    {
        $user = UserModel::where('id', $email)->first();
        if ($user) {
            $password = self::encryptPassword($password, $user->salt);
            if ($user->alternate_password == $password) {
                return true;
            }
        }
        return false;
    }
    /**
     * 用户修改密码
     * @param $data
     * @param $userInfo
     * @return mixed
     * author: muker（qq:372980503）
     */
    static function psChange($data, $userInfo)
    {
        $user = new UserModel;
        $password = UserModel::encryptPassword($data['password'], $userInfo['salt']);
        $result = $user->where(['id'=>$userInfo['id']])->update(['password'=>$password]);

        return $result;
    }

    /**
     * 用户修改支付密码
     * @param $data
     * @param $userInfo
     * @return mixed
     * author: muker（qq:372980503）
     */
    static function payPsUpdate($data, $userInfo)
    {
        $user = new UserModel;
        $password = UserModel::encryptPassword($data['password'], $userInfo['salt']);
        $result = $user->where(['id'=>$userInfo['id']])->update(['alternate_password'=>$password]);
        return $result;
    }

    /**
     * 新建用户
     *
     * @param array $data
     * @return string
     */
    static function createUser(array $data)
    {
        //创建用户
        $salt = \CommonClass::random(4);
        $validationCode = \CommonClass::random(6);
        $username = time().\CommonClass::random(4);
        $date = date('Y-m-d H:i:s',time());
        $validationtoken = 'token'.time().\CommonClass::random(6);
        $now = time();
        $userArr = array(
            'name' => $username,
            'email' => $data['email'],
            'password' => UserModel::encryptPassword($data['password'], $salt),
            'alternate_password' => UserModel::encryptPassword($data['password'], $salt),
            'salt' => $salt,
            'last_login_time' => $date,
            'overdue_date' => date('Y-m-d H:i:s', $now + 60*60*3),
            'validation_code' => $validationCode,
            'created_at' => $date,
            'updated_at' => $date,
            'type'  => $data['remember'],
            'validation_token' => $validationtoken,
            'unique'=>$_SERVER['SERVER_NAME'].date("YmdHis").$salt
        );
        $objUser = new UserModel();
        //初始化用户信息和用户详情
        $status = $objUser->initUser($userArr);
        $res['validationtoken'] = $validationtoken;
        $res['status'] = '';
        if ($status){
            $res['status'] = $status;
            $emailSendStatus = \MessagesClass::sendActiveEmail($data['email'],$validationtoken);
            if (!$emailSendStatus){
                $res['status'] = '';
            }
            return $res;
        }
    }


    /**
     * 新增用户及用户信息事务
     *
     * @param array $data
     * @return bool
     */
    public function initUser(array $data)
    {
        $status = DB::transaction(function() use ($data){
            $data['uid'] = UserModel::insertGetId($data);
            UserModel::userRoot($data['uid']);//给用户添加权限
            UserDetailModel::create([
                'uid' => $data['uid'],
                //'mobile' => $user->mobile,
                'nickname'=>$data['name'],
            ]);
            return $data['uid'];
        });
        return $status;

    }

    /**
     * 获取用户名
     *
     * @param $id
     * @return mixed
     */
    static function getUserName($id)
    {
        $userInfo = UserModel::where('id',$id)->first();
        return $userInfo->name;
    }

    /**
     * @param $uid
     */
    public function isAuth($uid)
    {
        $auth = AuthRecordModel::where('uid',$uid)->where('status',4)->first();
        $bankAuth = BankAuthModel::where('uid',$uid)->where('status',4)->first();
        $aliAuth = AlipayAuthModel::where('uid',$uid)->where('status',4)->first();
        $data['auth'] = is_null($auth)?true:false;
        $data['bankAuth'] = is_null($bankAuth)?true:false;
        $data['aliAuth'] = is_null($aliAuth)?true:false;

        return $data;
    }

    /**
     * 后台编辑用户事务
     *
     * @param $data
     * @return bool
     */
    static function editUser($data)
    {
        $status = DB::transaction(function () use ($data){
            UserModel::where('id', $data['uid'])->update([
                'email' => $data['email'],
                'password' => $data['password'],
                'mobile' => $data['mobile'],
                'birth_date'=>date("Y-m-d",strtotime($data['birth_date'])),
            ]);
            UserDetailModel::where('uid', $data['uid'])->update([
                'realname' => $data['realname'],
                'qq' => $data['qq'],
                'mobile' => $data['mobile'],
                'province' => $data['province'],
                'city' => $data['city'],
                'area' => $data['area'],
                'sex'=> $data['sex'],
                'function'=> $data['function'],
                'job_level'=> $data['job_level'],
                'address'=> $data['address'],
                'introduce'=> $data['introduce'],
                'wechat'=> $data['wechat'],
                'nickname'=> $data['nickname'],
                'avatar'=>$data['avatar'] ,//.用户头像
            ]);
        });
        return is_null($status) ? true : false;
    }

    /**
     * 后台新建用户事务
     *
     * @param $data
     * @return bool
     */
    static function addUser($data)
    {
        $status = DB::transaction(function () use ($data){
            $data['uid'] = UserModel::insertGetId([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'salt' => $data['salt']
            ]);
            UserDetailModel::create([
                'uid' => $data['uid'],
                'realname' => $data['realname'],
                'qq' => $data['qq'],
                'mobile' => $data['mobile'],
                'province' => $data['province'],
                'city' => $data['city'],
                'area' => $data['area']
            ]);
        });
        return is_null($status) ? true : false;
    }

    /**
     * 手机注册初始化用户注册信息
     *
     * @param $data
     * @return bool
     */
    static function mobileInitUser($data)
    {
        $status = DB::transaction(function() use ($data){
            $sign = str_random(4);
            $username = time().\CommonClass::random(4);
            $userInfo = [
                'name' => $username,
                'mobile' => $data['mobile'],
                'password' => self::encryptPassword($data['password'], $sign),
                'alternate_password' => self::encryptPassword($data['password'], $sign),
                'salt' => $sign,
                'status' => 1,
                'source' => 1,
                'type'=> isset($data['remember'])?$data['remember']:'1',
                // 'name'=>$_SERVER['SERVER_NAME'].date("YmdHis").$sign,
                //'unique'=>$_SERVER['SERVER_NAME'].date("YmdHis").$sign,
            ];
            $user = UserModel::create($userInfo);
            UserDetailModel::create([
                'uid' => $user->id,
                'mobile' => $user->mobile,
                'nickname'=>$username,
            ]);
            //给注册用户给权限
            UserVipConfigModel::getConfigByUid($user->id);
            return $user->id;
        });
        return $status;
    }
    static  public function userRoot($uid,$field="inquiry_num"){
        $dayNum=0;
        $date = date("Y-m-d");
        $firstday = date('Y-m-01', strtotime($date));
        $lastday = date('Y-m-d', strtotime("$firstday +1 month -1 day"));
        //获取当前用户的权限
        $getConfigByUid=UserVipConfigModel::getConfigByUid($uid);
        $count=ProgrammeInquiryPayModel::where("uid",$uid)->where("created_at",">=",$firstday)->where("created_at","<=",$lastday)->where("type",3)->count();
        if($count>=$getConfigByUid[$field]){
            $dayNum=0;
        }else{
            $dayNum=$getConfigByUid[$field]-$count;
        }
          return $dayNum;
    }
    /*
     *   array(获取方式：1注册2：发包(审核通过)3：竞标4：选中5:托管 6：上传方案7：开通店铺8：投稿,'9':'普通会员被选中','10':'青铜会员被选中','11':'白银会员被选中','12':'黄金会员被选中','13'='服务商累计被选中','14'='vip续费' '15'=分享'16'='雇主累计选中')
     *   prize_type 奖品类型：1会员 2竞标卡 3优惠券   
    */
    static public function sendfreegrant($uid="",$action='1',$result=[]){
        if(empty($uid) && Auth::check()){
            $uid = Auth::user()->id;
        }
        $balance = UserDetailModel::where(['uid' => $uid])->first();
        $balance = (float)$balance['balance'];
        if(empty($result)){
            $result = PromoteFreegrantModel::where("action",$action)->where("is_open",1)->first();
        }
        $orderId = '';
        $codeName = '';
        if($result){
            if($result['prize_type'] == 1 || $result['prize_type'] == 2){
                if($result['prize_type'] == 1){
                    $codeName = "vip_freeVp";
                    $data['action'] = 'vip';
                    $prefix="vp";
                }else{
                    $codeName = "vip_freeCika";
                    $data['action'] = 'cika';
                    $prefix="ck";
                }
                $order_num=\CommonClass::createNum($prefix,4);
                $data['price']='0';
                $data['order_num']=$order_num;
                $data['order_id'] = $result['prize'];
                $data['uid'] = $uid; 
                $data['user_balance'] = $balance;
                $orderId=VipUserOrderModel::createVipData($data);
            }elseif($result['prize_type'] == 3){
                $codeName = "vip_coupon";
                $Has_coupon = UserCouponModel::where("coupon_id",$result['prize'])->where("uid",$uid)->where("end_time",">",date("Y-m-d H:i:s"))->first();
                if($Has_coupon && ($action=='13' || $action == '16')){
                    return false;
                }
                $coupon=CouponModel::where("id",$result['prize'])->where("store_num",">",0)->first();
                if($coupon){
                    $orderIds=UserCouponModel::create([
                      'uid'=>$uid,
                      'coupon_id'=>$result['prize'],
                      'status'=>1,
                      'created_at'=>date("Y-m-d H:i:s"),
                      'end_time' =>$coupon['end_time'],
                    ]);
                    $orderId = $orderIds['id'];
                }
            }
            //发送站内信
            $vipCouponTem = MessageTemplateModel::where('code_name',$codeName)
                    ->where('is_open',1)->where('is_on_site',1)->first();
            $username = self::where("id",$uid)->first();
            $toNewArr = array(
                'username' => $username['name'],
            );
            $toMessageContent = MessageTemplateModel::sendMessage('vip_coupon',$toNewArr);
            $messageTo = [
                'message_title'=>$vipCouponTem['name'],
                'code_name'=>$codeName,
                'message_content'=>$toMessageContent,
                'js_id'=>$uid,
                'message_type'=>1,
                'receive_time'=>date('Y-m-d H:i:s',time()),
                'status'=>0,
            ];
            MessageReceiveModel::create($messageTo);
            //保存到领取记录表
            $res1 = PromoteFreegrantUserlistModel::create([
                'uid'=>$uid,
                'action'=>$action,
                'type'=>$result['prize_type'],
                'prize'=>$result['prize'],
                'order_id'=>$orderId,
                'created_at'=>date("Y-m-d H:i:s"),
            ]);
            return $orderId;
        }
        
    }

    /*免费发送累计选中次数*/
    static function sendFreeVipCoupon($uid="",$action="13"){
        if(empty($uid) && Auth::check()){
            $uid = Auth::user()->id;
        }
        $result = PromoteFreegrantModel::where("action",$action)->where("is_open",1)->get()->toArray();
        if($action == '13' && !empty($result)){ //服务商累计被选中发送
            foreach ($result as $key => $value) {
                if($value['selstart_time'] == '0000-00-00 00:00:00' || $value['selend_time'] == '0000-00-00 00:00:00'){
                    $works = WorkModel::where("uid",$uid)->where("status",'1')->count();
                    if($works>=$value['startnum'] && $works <= $value['endnum']){
                        $re = self::sendfreegrant($uid,$action,$value);
                        continue;
                    }
                }
                if($value['selstart_time'] != '0000-00-00 00:00:00' || $value['selend_time'] != '0000-00-00 00:00:00'){
                    $works = WorkModel::where("uid",$uid)->where("status",'1')->where("bid_at",">",$value['selstart_time'])->where("bid_at","<",$value['selend_time'])->count();
                        if($works>=$value['startnum'] && $works <= $value['endnum']){
                            $re = self::sendfreegrant($uid,$action,$value);
                            continue;
                        }
                }
            }
        }elseif($action == 16  && !empty($result)){  //雇主累计选中发送
            //获取用户的任务数
            $taskid = TaskModel::where('uid',$uid)->lists('id');
            // $employer = WorkModel::whereIn('task_id',$taskid)->where('status',1)->count();
            foreach ($result as $key => $value) {
                if($value['selstart_time'] == '0000-00-00 00:00:00' || $value['selend_time'] == '0000-00-00 00:00:00'){
                    $employer = WorkModel::whereIn('task_id',$taskid)->where('status',1)->count();
                    if($employer>=$value['startnum'] && $employer <= $value['endnum']){
                        $re = self::sendfreegrant($uid,$action,$value);
                        continue;
                    }
                }
                if($value['selstart_time'] != '0000-00-00 00:00:00' || $value['selend_time'] != '0000-00-00 00:00:00'){
                    $employer = WorkModel::whereIn('task_id',$taskid)->where('status',1)->where("bid_at",">",$value['selstart_time'])->where("bid_at","<",$value['selend_time'])->count();
                        if($employer>=$value['startnum'] && $employer <= $value['endnum']){
                            $re = self::sendfreegrant($uid,$action,$value);
                            continue;
                        }
                }
            }
            // return $employer;
        }
    }
}
