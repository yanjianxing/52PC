<?php

namespace App\Modules\User\Http\Controllers\Auth;

use App\Http\Controllers\IndexController;
use App\Modules\Manage\Model\AgreementModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\User\Http\Requests\LoginRequest;
use App\Modules\User\Http\Requests\RegisterPhoneRequest;
use App\Modules\User\Http\Requests\RegisterRequest;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\OauthBindModel;
use App\Modules\User\Model\PromoteModel;
use App\Modules\User\Model\UserCouponLogModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserLoginModel;
use App\Modules\User\Model\UserToolModel;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Validator;
use Auth;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
use Illuminate\Http\Request;
use Theme;
use Crypt;
use Cookie;
use Socialite;
use App\Modules\Advertisement\Model\AdTargetModel;
use Toplan\PhpSms;
use SmsManager;

class AuthController extends IndexController
{

    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    use AuthenticatesAndRegistersUsers, ThrottlesLogins;

    //认证成功后跳转路由
    protected $redirectPath = '/user/index';

    //认证失败后跳转路由
    protected $loginPath = '/login';

    /*
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->initTheme('auth');
        $this->theme->setTitle('威客|系统—客客出品,专业威客建站系统开源平台');
        $this->middleware('guest', ['except' => 'getLogout']);
        //退出登陆清空中电快购Cookies
        Cookie::queue('faw_status','','','/','.52solution.com');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected $code;

    protected function validator(array $data)
    {

    }

    /**
     * 创建用户信息
     *
     * @param array $data
     * @return string
     */
    protected function  create(array $data)
    {
        //创建新用户
        return UserModel::createUser($data);
    }


    /**
     * 登录视图
     *
     * @return mixed
     */
    public function getLogin()
    {
        $code = \CommonClass::getCodes();
        $oauthConfig = ConfigModel::getConfigByType('oauth');
        //登录左侧广告
        $ad = AdTargetModel::getAdInfo('LOGIN_LEFT');

        $view = array(
            'code' => $code,
            'oauth' => $oauthConfig,
            'ad' => $ad
        );

        $this->theme->set('authAction', '欢迎登录');
        $this->theme->setTitle('欢迎登录');
        return $this->theme->scope('user.login', $view)->render();
    }

    //获取用户登录信息
  /*  public function getUserlogin($uid)
    {
        $userLogin = new UserLoginModel();
        $userLogin->insertInformation($uid);
    }*/

    /*
    * 获取手机验证码
    * */
    public function getPhoneCode(Request $request){
        $arr = $request->all();
        $code = rand(1000, 9999);

        $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
        $templateId = ConfigModel::phpSmsConfig('sendTaskCode');
        $templates = [
            $scheme => $templateId,
        ];

        $tempData = [
            'code' => $code,
        ];
        $status = \SmsClass::sendSms($arr['mobile'], $templates, $tempData);
        if ($status['success'] == true) {
            $data = [
                'code' => $code,
                'mobile' => $arr['mobile']
            ];
            Session::put('task_mobile_info', $data);
            $status = 'y';
            $info = '短信发送成功';
            //  return ['code' => 1000, 'msg' => '短信发送成功','data' => $code];
        } else {
            $info = '短信发送失败';
            $status = 'n';
            //  return ['code' => 1001, 'msg' => '短信发送失败'];
        }
        $data = array(
            'info' => $info,
            'status' => $status
        );
        return json_encode($data);
    }
    /*
     * 手机直接登录
     * */
    public function phoneLogin(Request $request){
        $data = $request->all();
        $taskMobileInfo = session('task_mobile_info');
        if ($data['code'] == $taskMobileInfo['code'] && $data['mobile'] == $taskMobileInfo['mobile']) {
            Session::forget('task_mobile_info');
            //查询用户是否存在
            $user = UserModel::where('mobile',$data['mobile'])->first();
            if($user){
                $uid = $user['id'];
            }else{
                $uid=DB::transaction(function() use($data){
                    $username = $username = time().\CommonClass::random(4);
                    $userInfo = [
                        'username' => $username,
                        'mobile' => $data['mobile'],
                        'password' => $data['mobile']
                    ];
                    $uid = UserModel::mobileInitUser($userInfo);

                    $user = [
                        'uid'    => $uid,
                        'mobile' => $data['mobile']
                    ];
                    $templateArr = [
                        'password' => $userInfo['mobile']
                    ];
                    $res = \MessageTemplateClass::sendMessage('direct_login_pass',$user,$templateArr,$templateArr);
                    //发送通知短信
                    // $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                    // $templateId = ConfigModel::phpSmsConfig('sendLoinPassword');
                    // $templates = [
                    //     $scheme => $templateId,
                    // ];
                    //$tempData = [
                    //    'username' => $data['mobile'],
                    //    'password' => $data['mobile'],
                    //    'website' => $this->theme->get('site_config')['site_url']
                    //];
					// $tempData = [
					// 	'code' => $data['mobile'],
					// ];

                    // \SmsClass::sendSms($data['mobile'], $templates, $tempData);
                    return $uid;
                });
                UserModel::sendfreegrant($uid,1);//.自动发放
            }
            Auth::loginUsingId($uid);
            UserModel::where('id',$uid)->update(['last_login_time' => date('Y-m-d H:i:s'),'is_phone_login'=>1]);
            //获取用户登录信息
//            $this->getUserlogin($uid);
            //中电快购登陆
            Cookie::queue('faw_status',$data['mobile'].'$$True',120,'/','.52solution.com');
            // return redirect("/user/index");
            $throttles = $this->isUsingThrottlesLoginsTrait();
            return $this->handleUserWasAuthenticated($request, $throttles);
            //return $this->theme->scope('user.activesuccess')->render();
        }else{
            return back()->with(["message"=>"手机验证码错误"]);
        }
    }


    /**
     * 用户登录
     *
     * @param LoginRequest $request
     * @return $this|\Illuminate\Http\Response
     */
    public function postLogin(LoginRequest $request)
    {
        $error = array();
        if ($request->get('code') && !\CommonClass::checkCode($request->get('code'))) {
            $error['code'] = '请输入正确的验证码';
        } else {
            if (!UserModel::checkPassword($request->get('username'), $request->get('password'))) {
                $error['password'] = '请输入正确的帐号或密码';
            } else {
                $user = UserModel::where('unique', $request->get('username'))->first();
                if (!empty($user) && $user->status == 2){
                    $error['username'] = '该账户已禁用';
                }
            }
        }
        if (!empty($error)) {
            return redirect($this->loginPath())->withInput($request->only('username', 'remember'))->withErrors($error);
        }
        $throttles = $this->isUsingThrottlesLoginsTrait();
        $user = UserModel::where('email', $request->get('username'))
            ->orWhere('name', $request->get('username'))
            ->orWhere('mobile', $request->get('username'))->first();
        if ($user && !$user->status) {
            return redirect('waitActive/' . Crypt::encrypt($user->email))->withInput(array('email' => $request->get('email')));
        }
        Auth::loginUsingId($user->id);
        UserModel::where('id',$user->id)->update(['last_login_time' => date('Y-m-d H:i:s'),'is_phone_login'=>0]);
        //获取用户登录信息
        $uid=$user->id;
//        $this->getUserlogin($uid);
        //是否设置两周内自动登录
        if($request->get('remember') && $request->get('remember') == 'on'){
            setcookie("userid", $user->id, time()+14*24*3600);
        }
        //中电快购登陆
        if($user->mobile){
            Cookie::queue('faw_status' , $user->mobile.'$$True' , 120 , '/' ,'.52solution.com');
        }

        //结算推广者赏金
        PromoteModel::settlementByUid($user->id);

        //百川云旺添加客户
        $messageConfig = [];
        $config = ConfigModel::getConfigByAlias('app_message');
        if($config && !empty($config['rule'])){
            $messageConfig = json_decode($config['rule'],true);
        }
        if(!empty($messageConfig)){
            $username = strval($user->id);
            $userDetail = UserDetailModel::where('uid',$user->id)->first();
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
                $userinfos->nick     = $user->name;
                $userinfos->icon_url = $userDetail['avatar'];
                $userinfos->email    = $userDetail['email'];
                $userinfos->mobile   = $userDetail['mobile'];
                $userinfos->userid   = $user->id;
                $userinfos->password = substr(Auth::user()->password, 0, 10); //'123456';
                $userinfos->name     = $user->name;
                $req->setUserinfos(json_encode($userinfos));
                $c->execute($req);
            }else{
                //新增用户信息
                $req = new \OpenimUsersAddRequest();
                $userinfos = new \Userinfos();
                $userinfos->nick     = $user->name;
                $userinfos->icon_url = $userDetail['avatar'];
                $userinfos->email    = $userDetail['email'];
                $userinfos->mobile   = $userDetail['mobile'];
                $userinfos->userid   = $user->id;
                $userinfos->password = substr(Auth::user()->password, 0, 10);
                $userinfos->name     = $user->name;
                $req->setUserinfos(json_encode($userinfos));
                $c->execute($req);
            }
            Session::put('open_im_pass',substr(Auth::user()->password, 0, 10));
        }
        return $this->handleUserWasAuthenticated($request, $throttles);

    }


    /**
     * 用户注册视图
     *
     * @return mixed
     */
    public function getRegister(Request $request)
    {
        if($request->get('uid')){
            $uid = Crypt::decrypt($request->get('uid'));
            //获取用户登录信息
//            $this->getUserlogin($uid);
        }else{
            $uid = '';
        }

        $code = \CommonClass::getCodes();
        //注册左侧广告
        $ad = AdTargetModel::getAdInfo('LOGIN_LEFT');
        //查询注册协议
        $agree = AgreementModel::where('code_name','register')->first();
        $view = array(
            'code'      => $code,
            'ad'        => $ad,
            'agree'     => $agree,
            'from_uid'  => $uid
        );
        $this->initTheme('auth');
        $this->theme->set('authAction', '欢迎注册');
        $this->theme->setTitle('欢迎注册');
        return $this->theme->scope('user.register', $view)->render();
    }


    /**
     * 注册用户
     *
     * @param RegisterRequest $request
     * @return $this
     */
    public function postRegister(Request $request)
    {
        //新增用户
        $user = $this->create($request->except('from_uid'));
        if ($user){
            if(!empty($request->get('from_uid'))){
                //增加推广注册关系
                PromoteModel::createPromote($request->get('from_uid'),$user['status']);
            }
            
            return redirect('waitActive/' . Crypt::encrypt($request->get('email')) . '/'.$user['validationtoken']);
        }
        return back()->with(['message' => '注册失败']);
    }

    /**
     * 手机注册提交
     *
     * @param RegisterPhoneRequest $request
     * @return $this
     */
    public function phoneRegister(Request $request)
    {
        $authMobileInfo = session('auth_mobile_info');
        $data = $request->except('_token');
        if ($data['code'] == $authMobileInfo['code'] && $data['mobile'] == $authMobileInfo['mobile']){
            Session::forget('auth_mobile_info');
            $status = UserModel::mobileInitUser($data);
            if ($status){
                if(!empty($request->get('from_uid'))){
                    //增加推广注册关系
                    PromoteModel::createPromote($request->get('from_uid'),$status);
                }
                $user = UserModel::where('mobile', $data['mobile'])->first();
                UserModel::userRoot($user->id);//给用户添加权限
                UserModel::sendfreegrant($user->id,1);//自动发放
                Auth::loginUsingId($user->id);
                //获取用户登录信息
                $uid=$user->id;
//                $this->getUserlogin($uid);
                return $this->theme->scope('user.activesuccessnew')->render();
            }

        }
        return back()->withErrors(['code' => '请输入正确的验证码']);
    }

    /**
     * 注册成功
     *
     * @param RegisterPhoneRequest $request
     * @return $this
     */
    public function activesuccessnew(Request $request)
    {
        $this->theme->setTitle('欢迎注册');
        return $this->theme->scope('user.activesuccessnew')->render();
           
    }

    /**
     * 不需要验证滑块发送手机注册验证码
     *
     * @param Request $request
     * @return string
     */
    public function sendmobileCodenew(Request $request)
    {

        $arr = $request->except('_token');

        //查询手机号是否被占用
        $isUser = UserModel::where('mobile',$arr['mobile'])->first();
        if($isUser){
            return ['code' => 1001, 'msg' => '手机已占用'];
        }
        $res = [
            'id' => 'e20876c0cecee2f36887c48eaf85639d',
            'key' => '28f1e7dcd36e1af44273146ea8a19605'
        ];
        session_start();
        // $data = array(
        //     "user_id" => $_SESSION['user_id'], # 网站用户id
        //     "client_type" => "web", #web:电脑上的浏览器；h5:手机上的浏览器，包括移动应用内完全内置的web_view；native：通过原生SDK植入APP应用的方式
        //     "ip_address" => $_SERVER["SERVER_ADDR"] # 请在此处传输用户请求验证时所携带的IP
        // );
        
        //发送注册短信
        $code = rand(1000, 9999);
        $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
        $templateId = ConfigModel::phpSmsConfig('sendMobileCode');
        $templates = [
            $scheme => $templateId,
        ];

        $tempData = [
            'code' => $code,
        ];

        $status = \SmsClass::sendSms($arr['mobile'], $templates, $tempData);
        if ($status['success'] == true) {
            $data = [
                'code' => $code,
                'mobile' => $arr['mobile']
            ];
            Session::put('auth_mobile_info', $data);
            return ['code' => 1000, 'msg' => '短信发送成功'];
        } else {
            return ['code' => 1001, 'msg' => '短信发送失败'];
        }
    }

    /**
     * 发送手机注册验证码
     *
     * @param Request $request
     * @return string
     */
    public function sendMobileCode(Request $request)
    {
        $arr = $request->except('_token');
        //查询手机号是否被占用
        $isUser = UserModel::where('mobile',$arr['mobile'])->first();
        if($isUser){
            return ['code' => 1001, 'msg' => '手机已占用'];
        }
        $res = [
            'id' => 'e20876c0cecee2f36887c48eaf85639d',
            'key' => '28f1e7dcd36e1af44273146ea8a19605'
        ];
        session_start();
        $data = array(
            "user_id" => $_SESSION['user_id'], # 网站用户id
            "client_type" => "web", #web:电脑上的浏览器；h5:手机上的浏览器，包括移动应用内完全内置的web_view；native：通过原生SDK植入APP应用的方式
            "ip_address" => $_SERVER["SERVER_ADDR"] # 请在此处传输用户请求验证时所携带的IP
        );
        $GtSdk = $this->GtSdk = new \GeetestLib($res['id'], $res['key']);
        //服务器正常
        if ($_SESSION['gtserver'] == 1) {
            $result = $GtSdk->success_validate($request->geetest_challenge, $request->geetest_validate, $request->geetest_seccode, $data);
            if ($result) {
                //发送注册短信
                $code = rand(1000, 9999);

                $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                $templateId = ConfigModel::phpSmsConfig('sendMobileCode');
                $templates = [
                    $scheme => $templateId,
                ];

                $tempData = [
                    'code' => $code,
                ];

                $status = \SmsClass::sendSms($arr['mobile'], $templates, $tempData);

                if ($status['success'] == true) {
                    $data = [
                        'code' => $code,
                        'mobile' => $arr['mobile']
                    ];
                    Session::put('auth_mobile_info', $data);
                    return ['code' => 1000, 'msg' => '短信发送成功'];
                } else {
                    return ['code' => 1001, 'msg' => '短信发送失败'];
                }
            } else {
                return ['info' => 0, 'msg' => '请先通过滑块验证'];
            }
        } else {
            //服务器宕机,走failback模式
            if ($GtSdk->fail_validate($request->geetest_challenge, $request->geetest_validate, $request->geetest_seccode)) {
                //发送注册短信
                $code = rand(1000, 9999);

                $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                $templateId = ConfigModel::phpSmsConfig('sendMobileCode');
                $templates = [
                    $scheme => $templateId,
                ];

                $tempData = [
                    'code' => $code,
                ];


                $status = \SmsClass::sendSms($arr['mobile'], $templates, $tempData);

                if ($status['success'] == true) {
                    $data = [
                        'code' => $code,
                        'mobile' => $data['mobile']
                    ];
                    Session::put('auth_mobile_info', $data);
                    return ['code' => 1000, 'msg' => '短信发送成功'];
                } else {
                    return ['code' => 1001, 'msg' => '短信发送失败'];
                }
            } else {
                return ['info' => 0, 'msg' => '请先通过滑块验证'];
            }
        }




    }

    /**
     * 邮件激活
     *
     * @param $validationInfo
     * @return mixed
     */
    public function activeEmail($validationInfo)
    {
        $info = Crypt::decrypt($validationInfo);
        $user = UserModel::where('email', $info['email'])->where('validation_code', $info['validationCode'])->first();
        $this->initTheme('auth');
        $this->theme->set('authAction', '欢迎注册');
        $this->theme->setTitle('欢迎注册');
        //邮件激活失败
        if ($user && time() > strtotime($user->overdue_date) || !$user) {
            return $this->theme->scope('user.activefail')->render();
        }
        //邮件激活成功
        $user->status = 1;
        $user->email_status = 2;
        $status = $user->save();
        if ($status){
            Auth::login($user);
            UserModel::sendfreegrant($user->id,1);//自动发放
            //获取用户登录信息
            $uid=$user->id;
//            $this->getUserlogin($uid);
            return $this->theme->scope('user.activesuccessnew')->render();
        }
    }

    /**
     * 等待激活视图
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function waitActive($email,$token)
    {
        $email = Crypt::decrypt($email);

        $emailType = substr($email, strpos($email, '@') + 1);
        $view = array(
            'email' => $email,
            'emailType' => $emailType,
            'token' => $token
        );
        $this->initTheme('auth');
        $this->theme->set('authAction', '欢迎注册');
        $this->theme->setTitle('欢迎注册');
        return $this->theme->scope('user.waitactive', $view)->render();
    }


    /**
     * 异步刷新验证码
     *
     * @return string
     */
    public function flushCode()
    {
        $code = \CommonClass::getCodes();

        return \CommonClass::formatResponse('刷新成功', 200, $code);
    }

    /**
     * 检测用户名是否可用
     *
     * @param Request $request
     * @return string
     */
    public function checkUserName(Request $request)
    {
        $username = $request->get('param');

        $status = UserModel::where('name', $username)->first();
        if (empty($status)){
            $status = 'y';
            $info = '';
        } else {
            $info = '该用户名已经存在';
            $status = 'n';
        }
        $data = array(
            'info' => $info,
            'status' => $status
        );
        return json_encode($data);
    }

    /**
     * 检测邮箱是否可用
     *
     * @param Request $request
     * @return string
     */
    public function checkEmail(Request $request)
    {
        $email = $request->get('param');

        $status = UserModel::where('email', $email)->first();
        if (empty($status)){
            $status = 'y';
            $info = '';
        } else {
            $info = '邮箱已占用';
            $status = 'n';
        }
        $data = array(
            'info' => $info,
            'status' => $status
        );
        return json_encode($data);
    }



    /**
     * 重新发送激活邮件
     *
     * @param $email
     * @return string
     */
    public function reSendActiveEmail($email,$token)
    {
        $email = Crypt::decrypt($email);
        $status = UserModel::where('email', $email)->update(array('overdue_date' => date('Y-m-d H:i:s', time() + 60*60*3)));
        if ($status){
            $status = \MessagesClass::sendActiveEmail($email,$token);
            if ($status){
                $msg = 'success';
            } else {
                $msg = 'fail';
            }
            return \CommonClass::formatResponse($msg);
        }
    }

    public function getLogout()
    {
        Auth::logout();
        if(isset($_COOKIE['userid'])){
            setcookie('userid', '', time()-1);
        }
        return redirect('/');
    }

    /**
     * 第三方登录认证
     *
     * @param $type
     * @return mixed
     */
    public function oauthLogin($type)
    {
        switch ($type){
            case 'qq':
                $alias = 'qq_api';
                break;
            case 'weibo':
                $alias = 'sina_api';
                break;
            case 'weixinweb':
                $alias = 'wechat_api';
                break;
        }
        //读取第三方授权配置项
        $oauthConfig = ConfigModel::getOauthConfig($alias);
        $clientId = $oauthConfig['appId'];
        $clientSecret = $oauthConfig['appSecret'];
        $redirectUrl = url('oauth/' . $type . '/callback');
        $config = new \SocialiteProviders\Manager\Config($clientId, $clientSecret, $redirectUrl);
        return Socialite::with($type)->setConfig($config)->redirect();
    }

    /**
     * 第三方登录回调处理
     *
     * @param $type
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleOAuthCallBack(Request $request,$type)
    {

        switch ($type){
            case 'qq':
                $service = 'qq_api';
                break;
            case 'weibo':
                $service = 'sina_api';
                break;
            case 'weixinweb':
                $service = 'wechat_api';
                break;
        }
        $oauthConfig = ConfigModel::getOauthConfig($service);
        Config::set('services.' . $type . '.client_id', $oauthConfig['appId']);
        Config::set('services.' . $type . '.client_secret', $oauthConfig['appSecret']);
        Config::set('services.' . $type . '.redirect', url('oauth/' . $type . '/callback'));

        $user = Socialite::driver($type)->user();

        $userInfo = [];
        switch ($type){
            case 'qq':
                $userInfo['oauth_id'] = $user->id;
                $userInfo['oauth_nickname'] = $user->nickname;
                $userInfo['oauth_type'] = 0;
                break;
            case 'weibo':
                $userInfo['oauth_id'] = $user->id;
                $userInfo['oauth_nickname'] = $user->nickname;
                $userInfo['oauth_type'] = 1;
                break;
            case 'weixinweb':
                $userInfo['oauth_nickname'] = $user->nickname;
                $userInfo['oauth_id'] = $user->user['unionid']; //unionid 相对多应用有唯一性 所以不取openid
                $userInfo['oauth_type'] = 2;
                break;
        }
        //检查是否绑定
        $oauthStatus = OauthBindModel::where(['oauth_id' => $userInfo['oauth_id'], 'oauth_type' => $userInfo['oauth_type']])->first();
        if (!empty($oauthStatus)){
            Auth::loginUsingId($oauthStatus->uid);
            $uid = $oauthStatus->uid;
        } else {
            //绑定第三方授权信息新增账户
            $uid = OauthBindModel::oauthLoginTransaction($userInfo);
            Auth::loginUsingId($uid);
        }
        UserModel::where('id',$uid)->update(['last_login_time' => date('Y-m-d H:i:s')]);
        //百川云旺添加客户
        $messageConfig = [];
        $config = ConfigModel::getConfigByAlias('app_message');
        if($config && !empty($config['rule'])){
            $messageConfig = json_decode($config['rule'],true);
        }
        if(!empty($messageConfig)){
            $username = strval($uid);
            $userDetail = UserDetailModel::where('uid',$uid)->first();
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
                $userinfos->nick     = Auth::user()->name;
                $userinfos->icon_url = $userDetail['avatar'];
                $userinfos->email    = $userDetail['email'];
                $userinfos->mobile   = $userDetail['mobile'];
                $userinfos->userid   = Auth::user()->id;
                $userinfos->password = substr(Auth::user()->password, 0, 10);
                $userinfos->name     = Auth::user()->name;
                $req->setUserinfos(json_encode($userinfos));
                $c->execute($req);
            }else{
                //新增用户信息
                $req = new \OpenimUsersAddRequest();
                $userinfos = new \Userinfos();
                $userinfos->nick     = Auth::user()->name;
                $userinfos->icon_url = $userDetail['avatar'];
                $userinfos->email    = $userDetail['email'];
                $userinfos->mobile   = $userDetail['mobile'];
                $userinfos->userid   = Auth::user()->id;
                $userinfos->password = substr(Auth::user()->password, 0, 10);
                $userinfos->name     = Auth::user()->name;
                $req->setUserinfos(json_encode($userinfos));
                $c->execute($req);
            }
            //获取用户登录信息
//            $this->getUserlogin($uid);
            Session::put('open_im_pass',substr(Auth::user()->password, 0, 10));
        }
        return redirect()->intended($this->redirectPath());
    }

}
