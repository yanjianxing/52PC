<?php
namespace App\Modules\Api\Http\Controllers;

use App\Http\Requests;
use App\Modules\Im\Model\ImAttentionModel;
use App\Modules\Im\Model\ImMessageModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\User\Model\TagsModel;
use App\Modules\User\Model\UserFocusModel;
use App\Modules\User\Model\UserTagsModel;
use Illuminate\Http\Request;
use App\Http\Controllers\ApiBaseController;
use Validator;
use Toplan\PhpSms\Sms;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\PhoneCodeModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\MessageReceiveModel;
use App\Modules\User\Model\OauthBindModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Manage\Model\AgreementModel;
use Config;
use Illuminate\Support\Facades\Crypt;
use Cache;
use DB;
use Socialite;
use Auth;
use Log;

class UserController extends ApiBaseController
{



    /**
     * 发送手机验证码
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function sendCode(Request $request){

        $validator = Validator::make($request->all(), [
            'phone' => 'required|mobile_phone',
        ],[
            'phone.required' => '请输入手机号码',
            'phone.mobile_phone' => '请输入正确的手机号码格式'
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(1001, $error[0]);
        }
        //接收人手机号
        $to = $request->get('phone');
        /*
         * 模板数据
         *
         */
        $code = mt_rand(100000,999999);

        if($request->get('type') && $request->get('type') == 2){
            //发送找回密码验证码短信模板id
            $templateId = ConfigModel::phpSmsConfig('sendMobilePasswordCode');
        }else{
            //查询用户已经注册
            $user = UserModel::where('mobile',$to)->first();
            $info = UserDetailModel::where('mobile',$to)->first();
            if($user || $info){
                return $this->formateResponse(1001,'手机号已经注册');
            }
            //发送注册验证码短信模板id
            $templateId = ConfigModel::phpSmsConfig('sendMobileCode');
        }

        //配置类型
        $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');

        $templates = [
            $scheme => $templateId,
        ];
        $tempData = [
            'code' => $code,
        ];
        $result = \SmsClass::sendSms($to, $templates, $tempData);


        if(isset($result['success']) && $result['success']){

            $vertifyInfo = PhoneCodeModel::where('phone',$to)->first();
            $overdueDate = time()+5*60;
            $data = [
                'code' => $tempData['code'],
                'overdue_date' => date('Y-m-d H:i:s',$overdueDate),
                'created_at' => date('Y-m-d H:i:s',time())
            ];
            if(count($vertifyInfo)){
                $res = PhoneCodeModel::where('phone',$vertifyInfo->phone)->update($data);
            }
            else{
                $data['phone'] = $to;
                $res = PhoneCodeModel::create($data);
            }
            if(isset($res)){
                return $this->formateResponse(1000,'success');
            }
            else{
                return $this->formateResponse(1003,'手机验证信息创建失败');
            }

        }
        else{
            return $this->formateResponse(1002,'手机验证码发送失败');
        }

    }

    /**
     * 用户注册
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function register(Request $request){
        $validator = Validator::make($request->all(), [
            'username' => 'required|min:4|max:15|alpha_num|unique:users,name',
            //'phone' => 'required|mobile_phone|unique:user_detail,mobile',
            'password' => 'required|min:6|max:16|alpha_num',
            //'code' => 'required',
            'source' => 'required',
        ],[
            'username.required' => '请输入用户名',
            'username.min' => '用户名长度不得小于4',
            'username.max' => '用户名长度不得大于15',
            'username.alpha_num' => '用户名请输入字母或数字',
            'username.unique' => '此用户名已存在',
            //'phone.required' => '请输入手机号码',
            //'phone.mobile_phone' => '请输入正确的手机号码格式',
            //'phone.unique' => '该手机号已绑定用户',
            'password.required' => '请输入密码',
            'password.min' => '密码长度不得小于6',
            'password.max' => '密码长度不得大于16',
            'password.alpha_num' => '密码请输入字母或数字',
            //'code.required' => '请输入验证码',
            'source.required' => '请输入注册来源',
        ]);
        // 获取验证错误信息
        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(1001,$error[0]);
        }
        /*$vertifyInfo = PhoneCodeModel::where('phone',$request->get('phone'))->first();
        if(count($vertifyInfo)){
            if(time() > strtotime($vertifyInfo->overdue_date)){
                return $this->formateResponse(1004,'手机验证码已过期');
            }
            if($vertifyInfo->code != $request->get('code')){
                return $this->formateResponse(1005,'手机验证码错误');
            }*/
            $salt = \CommonClass::random(4);
            $validationCode = \CommonClass::random(6);
            $date = date('Y-m-d H:i:s');
            $now = time();
            $password = UserModel::encryptPassword($request->get('password'), $salt);
            $userArr = array(
                'name' => $request->get('username'),
                'password' => $password,
                'alternate_password' => $password,
                'salt' => $salt,
                'mobile' => $request->get('phone'),
                'last_login_time' => $date,
                'overdue_date' => date('Y-m-d H:i:s', $now + 60*60*3),
                'validation_code' => $validationCode,
                'created_at' => $date,
                'updated_at' => $date,
                'source' => $request->get('source'),
                'status' => 1
            );
            $this->mobile = $request->get('phone');
            $res =  DB::transaction(function() use ($userArr){
               $userInfo = UserModel::create($userArr);
                $data = [
                    'uid' => $userInfo->id,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                    'mobile' => $this->mobile
                ];

                UserDetailModel::create($data);
                return $userInfo;

            });
            if(!isset($res)){
                return $this->formateResponse(1008,'注册失败');
            }
            return $this->formateResponse(1000,'success',$res);

       /* }
        else{
            return $this->formateResponse(1003,'找不到对应的验证码');
        }*/

    }

    /**
     * 登录
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function login(Request $request){
        $validator = Validator::make($request->all(), [
            'username' => 'required',
            'password' => 'required|min:6|max:16|alpha_num'
        ],[
            'username.required' => '请输入用户名',
            'password.required' => '请输入密码',
            'password.min' => '密码长度不得小于6',
            'password.max' => '密码长度不得大于16',
            'password.alpha_num' => '请输入字母或数字'
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(1001,$error[0]);
        }
        $username = $request->get('username');
        $userInfo = UserModel::leftjoin('user_detail','users.id','=','user_detail.uid')
            ->where('users.name',$username)
            ->orWhere('users.email',$username)
            ->orWhere('user_detail.mobile',$username)
            ->where('status','<>','2')
            ->select('users.*','user_detail.avatar')
            ->first();
        if(!count($userInfo)){
            return $this->formateResponse(1006,'用户不存在');
        }
        $domain = ConfigModel::where('alias','site_url')->where('type','site')->select('rule')->first();
        $userInfo->avatar = $domain->rule.'/'.$userInfo->avatar;
        $password = UserModel::encryptPassword($request->get('password'), $userInfo->salt);
        if($password != $userInfo->password){
            return $this->formateResponse(1007,'您输入的密码不正确');
        }
        $akey = md5(Config::get('app.key'));
        $tokenInfo = [
            'uid'=>$userInfo->id,
            'name' => $userInfo->name,
            'email' => $userInfo->email,
            'akey'=>$akey,
            'expire'=> time()+Config::get('session.lifetime')*60
        ];//生成token
        //$continueTime = 10*24*60*60;
        //$tokenInfo = ['uid'=>$userInfo->id, 'name' => $userInfo->name,'email' => $userInfo->email, 'akey'=>$akey, 'expire'=> time()+$continueTime];
        $userDetail = [
            'id' => $userInfo->id,
            'name' => $userInfo->name,
            'email' => $userInfo->email,
            'token' => Crypt::encrypt($tokenInfo),
            'avatar' => $userInfo->avatar,
            'im_password' => substr($userInfo->password, 0, 10)
        ];
        Cache::put($userInfo->id, $userDetail,Config::get('session.lifetime')*60);
        //Cache::put($userInfo->id, $userDetail,$continueTime);
        UserDetailModel::where('uid',$userInfo->id)->update(['shop_status' => 1]);

        //百川云旺添加客户
        $messageConfig = [];
        $config = ConfigModel::getConfigByAlias('app_message');
        if($config && !empty($config['rule'])){
            $messageConfig = json_decode($config['rule'],true);
        }
        if(!empty($messageConfig)){
            $username = strval($userInfo['id']);
            $c = new \TopClient();
            $c->appkey = isset($messageConfig['appkey']) ? $messageConfig['appkey'] : '';
            $c->secretKey = isset($messageConfig['secretKey']) ? $messageConfig['secretKey'] : '';

            //查询用户是否存在
            $req = new \OpenimUsersGetRequest();
            $req->setUserids($username);
            $userInfos = $c->execute($req);
            if(isset($userInfos->userinfos->userinfos)){
                //更新用户信息
                $req = new \OpenimUsersUpdateRequest();
                $userinfos = new \Userinfos();
                $userinfos->nick     = $userDetail['name'];
                $userinfos->icon_url = $userInfo->avatar;
                $userinfos->email    = $userDetail['email'];
                $userinfos->mobile   = $userInfo['mobile'];
                $userinfos->userid   = $userInfo['id'];
                $userinfos->password =  substr($userInfo->password, 0, 10);
                $userinfos->name     = $userDetail['name'];
                $req->setUserinfos(json_encode($userinfos));
                $c->execute($req);
            }else{
                //新增用户信息
                $req = new \OpenimUsersAddRequest();
                $userinfos = new \Userinfos();
                $userinfos->nick     = $userDetail['name'];
                $userinfos->icon_url = $userInfo->avatar;
                $userinfos->email    = $userDetail['email'];
                $userinfos->mobile   = $userInfo['mobile'];
                $userinfos->userid   = $userInfo['id'];
                $userinfos->password =  substr($userInfo->password, 0, 10);
                $userinfos->name     = $userDetail['name'];
                $req->setUserinfos(json_encode($userinfos));
                $c->execute($req);
            }
        }
        return $this->formateResponse(1000, '登录成功', $userDetail);


    }

    /**
     * 找回密码验证
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function vertify(Request $request){
        $validator = Validator::make($request->all(), [
            'phone' => 'required|mobile_phone',
            'code' => 'required'
        ],[
            'phone.required' => '请输入手机号码',
            'phone.mobile_phone' => '请输入正确的手机号码格式',
            'code.required' => '请输入验证码'
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(1001,$error[0]);
        }
        $userInfo = UserModel::leftjoin('user_detail','users.id','=','user_detail.uid')
            ->where('user_detail.mobile',$request->get('phone'))
            ->first();
        if(!count($userInfo)){
            return $this->formateResponse(1008,'找不到对应的用户信息');
        }
        $vertifyInfo = PhoneCodeModel::where('phone',$request->get('phone'))->where('code',$request->get('code'))->first();
        if(!count($vertifyInfo)){
            return $this->formateResponse(1009,'手机验证码错误');
        }
        return $this->formateResponse(1000,'success',['token'=>Crypt::encrypt($request->get('phone'))]);
    }

    /**
     * 找回密码
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function passwordReset(Request $request){
        $validator = Validator::make($request->all(), [
            'password' => 'required|min:6|max:16|alpha_num',
            'repassword' => 'required|same:password',
            'token' => 'required'

        ],[
            'password.required' => '请输入密码',
            'password.min' => '密码长度不得小于6',
            'password.max' => '密码长度不得大于16',
            'password.alpha_num' => '请输入字母或数字',
            'repassword.required' => '请输入确认密码',
            'repassword.same' => '两次输入的密码不一致',
            'token.required' => '请输入token',
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(1001,$error[0]);
        }
        /*
         * TODO 验证token是否合法
         */
        $phone = Crypt::decrypt($request->get('token'));
        if(!isset($phone)){
            return $this->formateResponse(1021,'传入的token不合法');
        }
        $userInfo = UserModel::leftjoin('user_detail','users.id','=','user_detail.uid')
            ->where('user_detail.mobile',$phone)
            ->first();
        if(!count($userInfo)){
            return $this->formateResponse(1022,'手机号传送错误');
        }
        $password = UserModel::encryptPassword($request->get('password'), $userInfo->salt);
        UserModel::where('name',$userInfo->name)->update(['password' => $password]);
        return $this->formateResponse(1000,'success');
    }

    /**
     * 修改登录密码
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function updatePassword(Request $request){
        $validator = Validator::make($request->all(), [
            'oldPass' => 'required|min:6|max:16|alpha_num',
            'password' => 'required|min:6|max:16|alpha_num',
            'repassword' => 'required|same:password'

        ],[
            'oldPass.required' => '请输入原密码',
            'oldPass.min' => '原密码长度不得小于6',
            'oldPass.max' => '原密码长度不得大于16',
            'oldPass.alpha_num' => '请输入字母或数字',
            'password.required' => '请输入新密码',
            'password.min' => '新密码长度不得小于6',
            'password.max' => '新密码长度不得大于16',
            'password.alpha_num' => '请输入字母或数字',
            'repassword.required' => '请输入确认密码',
            'repassword.same' => '两次输入的密码不一致'
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(1001,$error[0]);
        }
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $userInfo = UserModel::where('id',$tokenInfo['uid'])->first();
        if(!count($userInfo)){
            return $this->formateResponse(1023,'手机号传送错误');
        }
        $oldPass = UserModel::encryptPassword($request->get('oldPass'), $userInfo->salt);
        if($oldPass != $userInfo->password){
            return $this->formateResponse(1024,'原密码不正确');
        }
        $newPass = UserModel::encryptPassword($request->get('password'), $userInfo->salt);
        $userInfo->update(['password' => $newPass]);
        return $this->formateResponse(1000,'success');
    }

    /**
     * 修改支付密码
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function updatePayCode(Request $request){
        $validator = Validator::make($request->all(), [
            'oldPass' => 'required|min:6|max:16|alpha_num',
            'password' => 'required|min:6|max:16|alpha_num',
            'repassword' => 'required|same:password'

        ],[
            'oldPass.required' => '请输入原密码',
            'oldPass.min' => '原密码长度不得小于6',
            'oldPass.max' => '原密码长度不得大于16',
            'oldPass.alpha_num' => '请输入字母或数字',
            'password.required' => '请输入新密码',
            'password.min' => '新密码长度不得小于6',
            'password.max' => '新密码长度不得大于16',
            'password.alpha_num' => '请输入字母或数字',
            'repassword.required' => '请输入确认密码',
            'repassword.same' => '两次输入的密码不一致'
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(1001,$error[0]);
        }
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $userInfo = UserModel::where('id',$tokenInfo['uid'])->first();
        if(!count($userInfo)){
            return $this->formateResponse(1023,'手机号传送错误');
        }
        $oldPass = UserModel::encryptPassword($request->get('oldPass'), $userInfo->salt);
        if($oldPass != $userInfo->alternate_password){
            return $this->formateResponse(1024,'原密码不正确');
        }
        $newPass = UserModel::encryptPassword($request->get('password'), $userInfo->salt);
        $userInfo->update(['alternate_password' => $newPass]);
        return $this->formateResponse(1000,'success');
    }

    /**
     * 找回支付密码
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function payCodeReset(Request $request){
        $validator = Validator::make($request->all(), [
            'phone' => 'required|mobile_phone',
            'code' => 'required',
            'password' => 'required|min:6|max:16|alpha_num',
            'repassword' => 'required|same:password',
        ],[
            'phone.required' => '请输入手机号码',
            'phone.mobile_phone' => '请输入正确的手机号码格式',
            'code.required' => '请输入验证码',
            'password.required' => '请输入密码',
            'password.min' => '密码长度不得小于6',
            'password.max' => '密码长度不得大于16',
            'password.alpha_num' => '请输入字母或数字',
            'repassword.required' => '请输入确认密码',
            'repassword.same' => '两次输入的密码不一致',
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(1001,$error[0]);
        }
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $userInfo = UserModel::leftjoin('user_detail','users.id','=','user_detail.uid')
            ->where(['user_detail.mobile' => $request->get('phone'),'users.id' => $tokenInfo['uid']])
            ->first();
        if(!count($userInfo)){
            return $this->formateResponse(1025,'找不到对应的用户信息');
        }
        $vertifyInfo = PhoneCodeModel::where('phone',$request->get('phone'))->where('code',$request->get('code'))->first();
        if(!count($vertifyInfo)){
            return $this->formateResponse(1026,'手机验证码错误');
        }
        $password = UserModel::encryptPassword($request->get('password'), $userInfo->salt);
        UserModel::where('name',$userInfo->name)->update(['alternate_password' => $password]);
        return $this->formateResponse(1000,'success');
    }


    /**
     * 查询用户信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getUserInfo(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $userInfo = UserModel::where('users.id',$tokenInfo['uid'])->leftJoin('user_detail','user_detail.uid','=','users.id')
            ->select('users.name','users.email','user_detail.*')->first()->toArray();
        $userInfo['nickname'] = $userInfo['name'];
        $url = ConfigModel::getConfigByAlias('site_url');
        $userInfo['avatar'] = $url['rule'].'/'.$userInfo['avatar'];
        $realNameAuth = RealnameAuthModel::where('uid',$tokenInfo['uid'])->select('status')->get()->toArray();
        if(isset($realNameAuth)){
            $realNameAuth = array_flatten($realNameAuth);
            if(in_array(1,$realNameAuth)){
                $userInfo['isRealName'] = 1;
            }elseif(in_array(2,$realNameAuth)){
                $userInfo['isRealName'] = 2;
            }else{
                $userInfo['isRealName'] = 0;
            }
        }
        else{
            $userInfo['isRealName'] = null;
        }
        //查询个人标签
        $userTag = UserTagsModel::where('uid',$tokenInfo['uid'])->select('tag_id')->get()->toArray();
        $userTag = array_flatten($userTag);
        $tag = TagsModel::whereIn('id',$userTag)->select('tag_name')->get()->toArray();
        $tag = array_flatten($tag);
        $userInfo['tag'] = $tag;
        if(!empty($userInfo)){
            return $this->formateResponse(1000,'success',$userInfo);
        }else{
            return $this->formateResponse(1001,'找不到对应的用户信息');
        }
    }

    /**
     * 获取用户昵称
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getNickname(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $user = UserDetailModel::where('uid',$tokenInfo['uid'])->first();
        if(!empty($user)){
            $nickname = $user->nickname;
            $data = array(
                'nickname' => $nickname
            );
            return $this->formateResponse(1000,'success',$data);
        }else{
            return $this->formateResponse(1001,'找不到对应的用户昵称');
        }

    }

    /**
     * 修改用户昵称
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function updateNickname(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $nickname = $request->get('nickname');
        if(!empty($nickname)){
            $data = array(
                'nickname' => $nickname
            );
            $user = UserDetailModel::where('uid',$tokenInfo['uid'])->update($data);
            if(!empty($user)){
                return $this->formateResponse(1000,'success');
            }else{
                return $this->formateResponse(1001,'failure');
            }
        }else{
            return $this->formateResponse(1002,'缺少参数');
        }
    }

    /**
     * 获取用户头像
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getAvatar(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $user = UserDetailModel::where('uid',$tokenInfo['uid'])->first();
        $url = ConfigModel::getConfigByAlias('site_url');
        if(!empty($user)){
            $avatar =  $url['rule'].'/'.$user->avatar;
            $data = array(
                'avatar' => $avatar
            );
            return $this->formateResponse(1000,'success',$data);
        }else{
            return $this->formateResponse(1001,'找不到对应的用户昵称');
        }
    }

    /**
     * 修改用户头像
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function updateAvatar(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $avatar = $request->file('avatar');
        $allowExtension = array('jpg', 'gif', 'jpeg', 'bmp', 'png');
        if ($avatar) {
            $uploadMsg = json_decode(\FileClass::uploadFile($avatar, 'user', $allowExtension));
            if ($uploadMsg->code != 200) {
                return $this->formateResponse(1024,$uploadMsg->message);
            } else {
                $userAvatar = $uploadMsg->data->url;
            }
        }
        if(!empty($userAvatar)){
            $data = array(
                'avatar' => $userAvatar
            );
            $user = UserDetailModel::where('uid',$tokenInfo['uid'])->update($data);
            $domain = ConfigModel::where('alias','site_url')->where('type','site')->select('rule')->first();
            if(!empty($user)){
                $avatar = $userAvatar?$domain->rule.'/'.$userAvatar:$userAvatar;
                return $this->formateResponse(1000,'success',['avatar' => $avatar]);
            }else{
                return $this->formateResponse(1001,'failure');
            }
        }else{
            return $this->formateResponse(1002,'缺少参数');
        }
    }

    /**
     * 修改用户信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function updateUserInfo(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $avatar = $request->file('avatar');
        $allowExtension = array('jpg', 'gif', 'jpeg', 'bmp', 'png');
        if ($avatar) {
            $uploadMsg = json_decode(\FileClass::uploadFile($avatar, 'user', $allowExtension));
            if ($uploadMsg->code != 200) {
                return $this->formateResponse(1024,$uploadMsg->message);
            } else {
                $userAvatar = $uploadMsg->data->url;
            }
        }
        $qq = $request->get('qq');
        $wechat = $request->get('wechat');
        $data = array();
        if(!empty($userAvatar)){
            $data['avatar'] = $userAvatar;
        }
        if(!empty($qq)){
            $data['qq'] = $qq;
        }
        if(!empty($wechat)){
            $data['wechat'] = $wechat;
        }
        $user = UserDetailModel::where('uid',$tokenInfo['uid'])->update($data);
        $userInfo = UserDetailModel::where('uid',$tokenInfo['uid'])->first();
        $domain = ConfigModel::where('alias','site_url')->where('type','site')->select('rule')->first();
        $userInfo->avatar = $userInfo->avatar?$domain->rule.'/'.$userInfo->avatar:$userInfo->avatar;
        if(!empty($user)){
            return $this->formateResponse(1000,'success',$userInfo);
        }else{
            return $this->formateResponse(1001,'failure');
        }
    }

    /**
     * 我的消息
     * @param Request $request
     * @param $messageType 1=>系统消息 2=>交易动态 3=>发信箱 4=>收信箱
     * @return \Illuminate\Http\Response
     */
    public function messageList(Request $request)
    {
        if($request->get('messageType')){
            $messageType = intval($request->get('messageType'));
            $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
            switch($messageType)
            {
                case 1:
                    $message = MessageReceiveModel::where('js_id',$tokenInfo['uid'])->where('message_type',1)
                        ->orderBy('receive_time','DESC')->paginate(4)->toArray();
                    $messageCount = MessageReceiveModel::where('js_id',$tokenInfo['uid'])->where('message_type',1)->where('status',0)->count();
                    break;
                case 2:
                    $message = MessageReceiveModel::where('js_id',$tokenInfo['uid'])->where('message_type',2)
                        ->orderBy('receive_time','DESC')->paginate(4)->toArray();
                    //查询未读消息
                    $messageCount = MessageReceiveModel::where('js_id',$tokenInfo['uid'])->where('message_type',2)->where('status',0)->count();
                    break;
                case 3:
                    $message = MessageReceiveModel::where('fs_id',$tokenInfo['uid'])->where('message_type',3)
                        ->orderBy('receive_time','DESC')->paginate(4)->toArray();
                    //查询未读消息
                    $messageCount = MessageReceiveModel::where('fs_id',$tokenInfo['uid'])->where('message_type',3)->where('status',0)->count();
                    break;
                case 4:
                    $message = MessageReceiveModel::where('js_id',$tokenInfo['uid'])->where('message_type',3)
                        ->orderBy('receive_time','DESC')->paginate(4)->toArray();
                    //查询未读消息
                    $messageCount = MessageReceiveModel::where('js_id',$tokenInfo['uid'])->where('message_type',3)->where('status',0)->count();
                    break;

            }
            if($message['total'] > 0){
                foreach($message['data'] as $key => $value){
                    $message['data'][$key]['message_content'] = htmlspecialchars_decode($value['message_content']);
                }
                $data = array(
                    'message_list' => $message,
                    'no_read' => $messageCount
                );
            }else{
                $data = array(
                    'message_list' => $message,
                    'no_read' => 0
                );
            }
            return $this->formateResponse(1000,'success',$data);
        }else{
            return $this->formateResponse(1002,'缺少参数');
        }

    }


    /**
     * 创建第三方登录的用户信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function oauthLogin(Request $request){
        if(!$request->get('uid') or !$request->get('nickname') or $request->get('sex') == NULL or !$request->get('source')){
            return $this->formateResponse(1053,'传送数据不能为空');
        }
        if($request->get('type') == 'qq' || $request->get('type') == 'weibo' || $request->get('type') == 'weixinweb'){
            $oauthStatus = OauthBindModel::where(['oauth_id' => $request->get('uid'), 'oauth_type' => 3])
                ->first();
            if (!empty($oauthStatus)){
                $userInfo = UserModel::where('id',$oauthStatus->uid)->first();
                $userDetail = UserDetailModel::where('uid',$oauthStatus->uid)->first();
                $password = UserModel::encryptPassword('123456', $userInfo->salt);
                if($password != $userInfo->alternate_password){
                    $status = false;
                }else{
                    $status = true;
                }
                $akey = md5(Config::get('app.key'));
                $tokenInfo = ['uid'=>$userInfo->id, 'name' => $userInfo->name,'email' => $userInfo->email, 'akey'=>$akey, 'expire'=> time()+Config::get('session.lifetime')*60];//生成token
                $information = [
                    'uid' => $userInfo->id,
                    'status' => $status,
                    'token' => Crypt::encrypt($tokenInfo),
                    'im_password' => substr($userInfo->password, 0, 10)
                ];
                Cache::put($userInfo->id, $information,Config::get('session.lifetime')*60);
                $res = $information;

                //百川云旺添加客户
                $messageConfig = [];
                $config = ConfigModel::getConfigByAlias('app_message');
                if($config && !empty($config['rule'])){
                    $messageConfig = json_decode($config['rule'],true);
                }
                if(!empty($messageConfig)){
                    $username = strval($userInfo['id']);
                    $c = new \TopClient();
                    $c->appkey = isset($messageConfig['appkey']) ? $messageConfig['appkey'] : '';
                    $c->secretKey = isset($messageConfig['secretKey']) ? $messageConfig['secretKey'] : '';

                    //查询用户是否存在
                    $req = new \OpenimUsersGetRequest();
                    $req->setUserids($username);
                    $userInfos = $c->execute($req);
                    if(isset($userInfos->userinfos->userinfos)){
                        //更新用户信息
                        $req = new \OpenimUsersUpdateRequest();
                        $userinfos = new \Userinfos();
                        $userinfos->nick     = $userInfo->name;
                        $userinfos->icon_url = $userDetail->avatar;
                        $userinfos->email    = $userInfo->email;
                        $userinfos->mobile   = $userInfo['mobile'];
                        $userinfos->userid   = $userInfo['id'];
                        $userinfos->password =  substr($userInfo->password, 0, 10);
                        $userinfos->name     = $userInfo['name'];
                        $req->setUserinfos(json_encode($userinfos));
                        $c->execute($req);
                    }else{
                        //新增用户信息
                        $req = new \OpenimUsersAddRequest();
                        $userinfos = new \Userinfos();
                        $userinfos->nick     = $userInfo->name;
                        $userinfos->icon_url = $userDetail->avatar;
                        $userinfos->email    = $userInfo->email;
                        $userinfos->mobile   = $userInfo['mobile'];
                        $userinfos->userid   = $userInfo['id'];
                        $userinfos->password =  substr($userInfo->password, 0, 10);
                        $userinfos->name     = $userInfo['name'];
                        $req->setUserinfos(json_encode($userinfos));
                        $c->execute($req);
                    }
                }

            } else{
                $salt = \CommonClass::random(4);
                $validationCode = \CommonClass::random(6);
                $date = date('Y-m-d H:i:s');
                $now = time();
                $pass = '123456';
                $password = UserModel::encryptPassword($pass, $salt);
                $userInfo = UserModel::where('name',$request->get('nickname'))->get();
                $userName = isset($userInfo)?$request->get('nickname').$salt:$request->get('nickname');
                $userArr = array(
                    'name' => $userName,
                    'password' => $password,
                    'alternate_password' => $password,
                    'salt' => $salt,
                    'last_login_time' => $date,
                    'overdue_date' => date('Y-m-d H:i:s', $now + 60*60*3),
                    'validation_code' => $validationCode,
                    'created_at' => $date,
                    'updated_at' => $date,
                    'source' => $request->get('source')
                );
                $this->sex = $request->get('sex');
                $this->oauth_id = $request->get('uid');
                $res =  DB::transaction(function() use ($userArr){
                    $userInfo = UserModel::create($userArr);
                    $data = [
                        'uid' => $userInfo->id,
                        'sex' => $this->sex,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];

                    $userDetail = UserDetailModel::create($data);
                    $oauthInfo = [
                        'oauth_id' => $this->oauth_id,
                        'oauth_nickname' => $userInfo->name,
                        'oauth_type' => 3,
                        'uid' => $userInfo->id,
                        'created_at' => date('Y-m-d H:i:s')
                    ];
                    OauthBindModel::create($oauthInfo);
                    $akey = md5(Config::get('app.key'));
                    $tokenInfo = ['uid'=>$userInfo->id, 'name' => $userInfo->name,'email' => $userInfo->email, 'akey'=>$akey, 'expire'=> time()+Config::get('session.lifetime')*60];//生成token
                    $information = [
                        'uid' => $userInfo->id,
                        'status' => true,
                        'token' => Crypt::encrypt($tokenInfo),
                        'im_password' => substr($userArr['password'], 0, 10)
                    ];
                    Cache::put($userInfo->id, $information,Config::get('session.lifetime')*60);
                    //百川云旺添加客户
                    $messageConfig = [];
                    $config = ConfigModel::getConfigByAlias('app_message');
                    if($config && !empty($config['rule'])){
                        $messageConfig = json_decode($config['rule'],true);
                    }
                    if(!empty($messageConfig)){
                        $username = strval($userInfo['id']);
                        $c = new \TopClient();
                        $c->appkey = isset($messageConfig['appkey']) ? $messageConfig['appkey'] : '';
                        $c->secretKey = isset($messageConfig['secretKey']) ? $messageConfig['secretKey'] : '';

                        //查询用户是否存在
                        $req = new \OpenimUsersGetRequest();
                        $req->setUserids($username);
                        $userInfos = $c->execute($req);
                        if(isset($userInfos->userinfos->userinfos)){
                            //更新用户信息
                            $req = new \OpenimUsersUpdateRequest();
                            $userinfos = new \Userinfos();
                            $userinfos->nick     = $userInfo->name;
                            $userinfos->icon_url = $userDetail->avatar;
                            $userinfos->email    = $userInfo->email;
                            $userinfos->mobile   = $userInfo['mobile'];
                            $userinfos->userid   = $userInfo['id'];
                            $userinfos->password =  substr($userInfo->password, 0, 10);
                            $userinfos->name     = $userInfo['name'];
                            $req->setUserinfos(json_encode($userinfos));
                            $c->execute($req);
                        }else{
                            //新增用户信息
                            $req = new \OpenimUsersAddRequest();
                            $userinfos = new \Userinfos();
                            $userinfos->nick     = $userInfo->name;
                            $userinfos->icon_url = $userDetail->avatar;
                            $userinfos->email    = $userInfo->email;
                            $userinfos->mobile   = $userInfo['mobile'];
                            $userinfos->userid   = $userInfo['id'];
                            $userinfos->password =  substr($userInfo->password, 0, 10);
                            $userinfos->name     = $userInfo['name'];
                            $req->setUserinfos(json_encode($userinfos));
                            $c->execute($req);
                        }
                    }

                    return $information;
                });
            }

            if(isset($res)){
                return $this->formateResponse(1000,'创建第三方登录信息成功',$res);
            }
            return $this->formateResponse(1055,'创建第三方登录信息失败');
        }
        return $this->formateResponse(1054,'传送数据类型不符合要求');
    }


    /**
     * 退出登录
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function loginOut(Request $request){
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        Cache::forget($tokenInfo['uid']);
        return $this->formateResponse(1000,'退出登录');
    }


    /**
     * 任务大厅
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getTaskList(Request $request)
    {
        $data = $request->all();
        $data['limit'] = (isset($data['limit'])&&$data['limit']) ? $data['limit'] : 15;
        $tasks = TaskModel::select('task.id', 'task.title', 'task.view_count', 'task.delivery_count', 'task.created_at', 'task.bounty' ,'task.bounty_status','cate.name', 'task.uid','task_type.alias as task_type')
            ->leftjoin('cate', 'task.cate_id', '=', 'cate.id')
            ->leftJoin('task_type','task.type_id','=','task_type.id')
            ->where('task.status','>',2)//任务审核通过
            ->where('task.begin_at','<',date('Y-m-d H:i:s',time()))//已经开始投稿的任务
            ->where('task.status','<=',11);
        if(isset($data['cate_id']) && $data['cate_id']){
            //查询该分类是一级分类还是二级分类
            $cate = TaskCateModel::where('id',$data['cate_id'])->first();
            if($cate){
                if($cate->pid == 0){
                    //查询所有子类
                    $childCate =  TaskCateModel::where('pid',$data['cate_id'])->select('id')->get()->toArray();
                    $cateIdArr = array_flatten($childCate);
                    $tasks = $tasks->whereIn('task.cate_id',$cateIdArr);
                }else{
                    $tasks = $tasks->where('task.cate_id',$data['cate_id']);
                }
            }else{
                return $this->formateResponse(2001,'参数错误');
            }

        }
        if(isset($data['taskName']) && !empty($data['taskName'])){
            $tasks = $tasks->where('task.title','like','%'.$data['taskName'].'%');
        }
        if(isset($data['desc']) && $data['desc']){
            switch($data['desc']){
                case 1://综合
                    $tasks = $tasks->orderBy('task.top_status','desc')->orderBy('task.created_at','desc');
                    break;
                case 2://发布时间
                    $tasks = $tasks->orderBy('task.created_at','desc')->orderBy('task.top_status','desc');
                    break;
                case 3://稿件数
                    $tasks = $tasks->orderBy('task.delivery_count','desc');
                    break;
                case 4://赏金
                    $tasks = $tasks->orderBy('task.bounty','desc');
                    break;
                default://默认综合排序
                    $tasks = $tasks->orderBy('task.top_status','desc')->orderBy('task.created_at','desc');
            }
        }else{
            //默认综合排序
            $tasks = $tasks->orderBy('task.top_status','desc')->orderBy('task.created_at','desc');
        }
        if(isset($data['type']) && $data['type']){
            switch($data['type']){
                case 1://悬赏
                    $tasks = $tasks ->where('task.bounty_status',1)->where('task_type.alias','xuanshang');
                    break;
                case 2://招标
                    $tasks = $tasks ->where('task_type.alias','zhaobiao');
                    break;
                default:
                    $tasks = $tasks ->where(function($query){
                        $query->where(function($querys){//悬赏要托管赏金
                            $querys->where('task.bounty_status',1)->where('task_type.alias','xuanshang');
                        })->orwhere(function($querys){
                            $querys->where('task_type.alias','zhaobiao');
                        });
                    });
            }
        }else{
            $tasks = $tasks ->where(function($query){
                $query->where(function($querys){//悬赏要托管赏金
                    $querys->where('task.bounty_status',1)->where('task_type.alias','xuanshang');
                })->orwhere(function($querys){
                    $querys->where('task_type.alias','zhaobiao');
                });
            });
        }

        $tasks = $tasks->paginate($data['limit'])->toArray();
        if(!empty($tasks['data'])){
            $tasks['data'] = TaskModel::dealTaskArr($tasks['data']);
        }
        if(empty($tasks['data'])){
            $recommend = TaskModel::select('task.id', 'task.title', 'task.view_count', 'task.delivery_count', 'task.created_at', 'task.bounty' ,'task.bounty_status','cate.name', 'task.uid','task_type.alias as task_type')
                ->leftjoin('cate', 'task.cate_id', '=', 'cate.id')
                ->leftJoin('task_type','task.type_id','=','task_type.id')
                ->where('task.status','>',2)//任务审核通过

                ->where('task.begin_at','<',date('Y-m-d H:i:s',time()))//已经开始投稿的任务
                ->where('task.status','!=',10)//任务没有失败
                ->orderBy('task.top_status','desc')//增值服务排序
                ->orderBy('task.created_at', 'desc')
                ->limit(5)->get()->toArray();
            $tasks['recommend'] = [];
            if(!empty($recommend)){
                $tasks['recommend'] = TaskModel::dealTaskArr($recommend);
            }
        }
        return $this->formateResponse(1000,'success',$tasks);
    }


    /**
     * 协议详情
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function agreementDetail(Request $request){
        if(!$request->get('code_name')){
            return $this->formateResponse(1060,'传送参数不能为空');
        }
        switch($request->get('code_name')){
            case '1':
                $agreeInfo = AgreementModel::where('code_name','register')->select('content')->first();
                break;
            case '2':
                $agreeInfo = AgreementModel::where('code_name','task_delivery')->select('content')->first();
                break;
            default:
                $agreeInfo = null;
        }

        if(isset($agreeInfo)){
            $agreeInfo = htmlspecialchars_decode('<html><body>'.$agreeInfo->content.'</body></html>');
        }
        return $this->formateResponse(1000,'获取协议信息成功',['agreeInfo' => $agreeInfo]);

    }

    /**
     * 查询是否开启IM
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function hasIm(Request $request)
    {
        //查询配置项IM是否开启
        $basisConfig = ConfigModel::getConfigByType('basis');
        if(!empty($basisConfig)){
            if($basisConfig['open_IM'] == 1){
                $ImPath = app_path('Modules' . DIRECTORY_SEPARATOR . 'Im');
                //判断是否有Im目录
                if(is_dir($ImPath)){
                    $contact = 1;
                    $imIp = $basisConfig['IM_config']['IM_ip'];
                    $imPort = $basisConfig['IM_config']['IM_port'];
                    $data = array(
                        'is_IM' => $contact,
                        'IM_ip' => $imIp,
                        'IM_port' => $imPort
                    );
                }else{
                    $contact = 2;
                    $data = array(
                        'is_IM' => $contact
                    );
                }
            }else{
                $contact = 2;
                $data = array(
                    'is_IM' => $contact
                );
            }
            return $this->formateResponse(1000,'获取信息成功',$data);
        }else{
            return $this->formateResponse(1001,'获取信息失败');
        }
    }

    /**
     * 没有IM服务 发送消息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function sendMessage(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $fromUid = $tokenInfo['uid'];
        if($request->get('to_uid') || $request->get('content') || $request->get('title')){
            $toUid = $request->get('to_uid');
            $content = $request ->get('content');
            $title = $request ->get('title');
            $data = array(
                'message_title' => $title,
                'message_content' => $content,
                'js_id' => $toUid,
                'fs_id' => $fromUid,
                'message_type' => 3,
                'receive_time' => date('Y-m-d H:i:s',time())
            );
            $res = MessageReceiveModel::create($data);
            if($res){
                return $this->formateResponse(1000,'success');
            }else{
                return $this->formateResponse(1002,'failure');
            }

        }else{
            return $this->formateResponse(1001,'缺少参数');
        }
    }

    /**
     * Im聊天历史消息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function ImMessageList(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $fromUid = $tokenInfo['uid'];
        if($request->get('to_uid')){
            //paginate_num为-1时查询未读历史消息，其余为分页大小
            $paginateNum = $request->get('paginate_num') ? $request->get('paginate_num') : 10;
            if($request->get('message_id')){
                if($paginateNum == -1){
                    //最新未读消息
                    $messageList = ImMessageModel::where('id','>',$request->get('message_id'))->whereIn('from_uid',[$fromUid,$request->get('to_uid')])->whereIN('to_uid',[$fromUid,$request->get('to_uid')])
                        ->orderBy('id','DESC')->orderBy('created_at','DESC')
                        ->paginate(10000)->toArray();
                    //把未读消息变为已读
                    ImMessageModel::where('id','>',$request->get('message_id'))->where('to_uid',$fromUid)->update(['status' => 2]);
                }else{
                    //message_id为当前聊天页面最上一条消息id，查询小于该消息id的历史消息记录
                    $messageList = ImMessageModel::where('id','<',$request->get('message_id'))->whereIn('from_uid',[$fromUid,$request->get('to_uid')])->whereIN('to_uid',[$fromUid,$request->get('to_uid')])
                        ->orderBy('created_at','DESC')
                        ->paginate($paginateNum)->toArray();
                }
            }else{
                //查询历史消息记录
                $messageList = ImMessageModel::whereIn('from_uid',[$fromUid,$request->get('to_uid')])->whereIN('to_uid',[$fromUid,$request->get('to_uid')])
                    ->orderBy('created_at','DESC')
                    ->paginate($paginateNum)->toArray();
            }
            $url = ConfigModel::getConfigByAlias('site_url');
            $fromUser = UserModel::select('name')->where('id',$fromUid)->first();
            $fromUserInfo = UserDetailModel::select('uid','nickname','avatar')->where('uid',$fromUid)->first();
            $fromUserAvatar = $url['rule'].'/'.$fromUserInfo->avatar;
            $toUser = UserModel::select('name')->where('id',$request->get('to_uid'))->first();
            $toUserInfo = UserDetailModel::select('uid','nickname','avatar')->where('uid',$request->get('to_uid'))->first();
            $toUserAvatar = $url['rule'].'/'.$toUserInfo->avatar;
            if($messageList['total'] > 0){
                foreach($messageList['data'] as $key => $value){
                    if($value['from_uid'] == $fromUid){
                        $messageList['data'][$key]['from_username'] = $fromUser->name;
                        $messageList['data'][$key]['from_avatar'] = $fromUserAvatar;
                    }elseif($value['from_uid'] == $request->get('to_uid')){
                        $messageList['data'][$key]['from_username'] = $toUser->name;
                        $messageList['data'][$key]['from_avatar'] = $toUserAvatar;
                    }
                    if($value['to_uid'] == $fromUid){
                        $messageList['data'][$key]['to_username'] = $fromUser->name;
                        $messageList['data'][$key]['to_avatar'] = $fromUserAvatar;
                    }elseif($value['to_uid'] == $request->get('to_uid')){
                        $messageList['data'][$key]['to_username'] = $toUser->name;
                        $messageList['data'][$key]['to_avatar'] = $toUserAvatar;
                    }
                }
            }
            return $this->formateResponse(1000,'success',$messageList);
        }else{
            return $this->formateResponse(1001,'缺少参数');
        }
    }

    /**
     * 发起IM聊天时成为临时联系人
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function becomeFriend(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $fromUid = $tokenInfo['uid'];
        if($request->get('to_uid')){
            $toUid = $request->get('to_uid');
            $toUserInfo = UserModel::select('name')->where('id', $toUid)->first();
            if(!empty($toUserInfo)){
                $res = ImAttentionModel::where(['uid' => $fromUid, 'friend_uid' => $toUid])->first();
                if(empty($res)){
                    $result = ImAttentionModel::insert([
                        [
                            'uid' => $toUid,
                            'friend_uid' => $fromUid
                        ],
                        [
                            'uid' => $fromUid,
                            'friend_uid' => $toUid
                        ]

                    ]);
                    if($result){
                        return $this->formateResponse(1000,'success');
                    }else{
                        return $this->formateResponse(1002,'failure');
                    }
                }else{
                    return $this->formateResponse(1000,'success');
                }
            }else{
                return $this->formateResponse(1004,'好友uid无效');
            }
        }else{
            return $this->formateResponse(1001,'缺少参数');
        }
    }

    /**
     * Im消息同步
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function ImMessageInsert(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data,[
            'from_uid' => 'required',
            'content'     => 'required',
            'to_uid'   => 'required',
        ],[
            'from_uid.required' => '发信人必传',
            'content.required'     => '请填写任务描述',
            'to_uid.required'   => '收信人必传',
        ]);

        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(2001,$error[0]);
        }

        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $uid = $tokenInfo['uid'];

        if($uid != $data['from_uid']){
            return $this->formateResponse(2001,'参数错误,发送人uid不是登录用户');
        }

        $dataArr = [
            'from_uid' => $data['from_uid'],
            'to_uid' => $data['to_uid'],
            'content' => $data['content'],
            'status' => 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        $res = ImMessageModel::create($dataArr);
        if($res){
            return $this->formateResponse(1000,'success');
        }else{
            return $this->formateResponse(1002,'failure');
        }
    }

    /**
     * 判断某一用户是否被关注
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function isFocusUser(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        $uid = $tokenInfo['uid'];
        if($request->get('to_uid')){
            $focusUid = $request->get('to_uid');
            $res = UserFocusModel::where('uid',$uid)->where('focus_uid',$focusUid)->first();
            if(empty($res)){
                $data = array(
                    'is_focus' => 2
                );
                return $this->formateResponse(1000,'未关注',$data);
            }else{
                $data = array(
                    'is_focus' => 1
                );
                return $this->formateResponse(1000,'已关注',$data);
            }
        }else{
            return $this->formateResponse(1001,'缺少参数');
        }
    }


    /**
     * 验证手机验证码的正确性
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function phoneCodeVertiy(Request $request){
        $validator = Validator::make($request->all(), [
            'phone' => 'required|mobile_phone|unique:user_detail,mobile',
            'code' => 'required'
        ],[
            'phone.required' => '请输入手机号码',
            'phone.mobile_phone' => '请输入正确的手机号码格式',
            'phone.unique' => '该手机号已绑定用户',
            'code.required' => '请输入验证码'

        ]);
        // 获取验证错误信息
        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(1001,$error[0]);
        }
        $vertifyInfo = PhoneCodeModel::where('phone',$request->get('phone'))->first();
        if(count($vertifyInfo)){
            if(time() > strtotime($vertifyInfo->overdue_date)){
                return $this->formateResponse(1004,'手机验证码已过期');
            }
            if($vertifyInfo->code != $request->get('code')){
                return $this->formateResponse(1005,'手机验证码错误');
            }
            return $this->formateResponse(1000,'手机验证码验证成功');
         }
         else{
             return $this->formateResponse(1003,'找不到对应的验证码');
         }

    }


    /**
     * 获取某个人的头像
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function headPic(Request $request){
        if(!$request->get('id')){
            return $this->formateResponse(1002,'传送数据不能为空');
        }
        $userInfo = UserDetailModel::where('uid',intval($request->get('id')))->select('avatar')->first();
        if(empty($userInfo)){
            return $this->formateResponse(1003,'传送参数错误');
        }
        $domain = ConfigModel::where('alias','site_url')->where('type','site')->select('rule')->first();
        $avatar = $userInfo->avatar?$domain->rule.'/'.$userInfo->avatar:$userInfo->avatar;
        return $this->formateResponse(1000,'获取头像成功',['avatar' => $avatar]);

    }


    /**
     * 获取当前app安卓的版本
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function version(){
        $versionInfo = ConfigModel::where(['alias' => 'app_android_version','type' => 'app_android'])->select('rule')->first();

        $data['version'] = $versionInfo->rule;

        if(isset($versionInfo)){
            return $this->formateResponse(1000,'获取版本信息成功',$data);
        }
        return $this->formateResponse(1001,'获取版本信息失败');
    }

    /**
     * 获取当前appios的版本
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function iosVersion(){
        $versionInfo = ConfigModel::where(['alias' => 'app_ios_version','type' => 'app_ios'])->select('rule')->first();
        $pushInfo = ConfigModel::where(['alias' => 'app_ios_version_push','type' => 'app_ios'])->select('rule')->first();

        $data['version'] = $versionInfo->rule;
        $data['is_push'] = $pushInfo->rule;

        if(isset($versionInfo)){
            return $this->formateResponse(1000,'获取版本信息成功',$data);
        }
        return $this->formateResponse(1001,'获取版本信息失败');
    }


    /**
     * 系统消息和交易动态未读消息数
     * @param Request $request
     * @param $messageType 1=>系统消息 2=>交易动态 3=>发信箱 4=>收信箱
     * @return \Illuminate\Http\Response
     */
    public function messageNum(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->input('token')));
        //系统消息未读数
        $systemCount = MessageReceiveModel::where('js_id',$tokenInfo['uid'])->where('message_type',1)->where('status',0)->count();
        //交易动态未读数
        $tradeCount = MessageReceiveModel::where('js_id',$tokenInfo['uid'])->where('message_type',2)->where('status',0)->count();

        return $this->formateResponse(1000,'success',['systemCount' => $systemCount,'tradeCount' => $tradeCount]);

    }


    /**
     * 将系统消息和交易动态由未读更新为已读
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function messageStatus(Request $request)
    {
        $res = MessageReceiveModel::where('id',intval($request->get('id')))->update(['status' => 1]);
        if($res){
            return $this->formateResponse(1000,'success');
        }else{
            return $this->formateResponse(1009,'状态更新失败');
        }
    }

    /**
     * 获取im聊天(百川云旺appkey)
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getMessageAppKey(Request $request)
    {
        $appkey = '';
        $config = ConfigModel::getConfigByAlias('app_message');
        if($config && !empty($config['rule'])){
            $messageConfig = json_decode($config['rule'],true);
            if(isset($messageConfig['appkey'])){
                $appkey = $messageConfig['appkey'];
            }

        }
        return $this->formateResponse(1000,'success',$appkey);
    }

}
