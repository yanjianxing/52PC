<?php

namespace App\Modules\User\Http\Controllers\Auth;

use App\Http\Controllers\IndexController;
use App\Modules\Advertisement\Model\AdTargetModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\User\Http\Requests\PasswordEmailRequest;
use App\Modules\User\Http\Requests\ResetRequest;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Auth;
use Validator;
use Theme;
use PhpSms;

class PasswordController extends IndexController
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    protected $redirectTo = '/user';

    /*
     * Create a new password controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->middleware('guest');
    }

    /**
     * 找回密码视图
     *
     * @return mixed
     */
    public function getEmail()
    {
        $code = \CommonClass::getCodes();
        //登录左侧广告
        $ad = AdTargetModel::getAdInfo('LOGIN_LEFT');
        $view = array(
            'code' => $code,
            'ad' => $ad
        );
        $this->theme->set('authAction', '找回密码');
        $this->initTheme('auth');
        $this->theme->setTitle('找回密码');
        return $this->theme->scope('user.password', $view)->render();
    }


    /**
     * 找回密码
     *
     * @param PasswordEmailRequest $request
     * @return $this
     */
    public function postEmail(PasswordEmailRequest $request)
    {

        $error = array();
        if (!\CommonClass::checkCode($request->get('code'))) {
            $error['code'] = '请输入正确的验证码';
        } else {
            $user = UserModel::where('email', $request->get('email'))->first();

            if(!$user){
                $error['code'] = '邮箱未注册';
            } elseif (!$user->status) {
                $error['code'] = '账号未激活';
            }
        }
        if (!empty($error)) {
            return redirect()->back()->with(array("message"=>$error['code']));
        }

        $status = \MessagesClass::sendPasswordEmail($request->get('email'));
        if ($status) {
            return redirect('waitValidation/' . Crypt::encrypt($request->get('email')));
        }
    }


    /**
     * 重置密码请求处理
     *
     * @param ResetRequest $request
     * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postReset(ResetRequest $request)
    {
        $validation = Crypt::decrypt($request->get('validation'));
        $email = $validation['email'];
        $user = UserModel::where('email', $email)->first();
        $user->password = UserModel::encryptPassword($request->get('password'), $user->salt);
        $status = $user->save();
        $this->initTheme('auth');
        $this->theme->set('authAction', '找回密码');
        $this->theme->setTitle('找回密码');
        //登录左侧广告
        $ad = AdTargetModel::getAdInfo('LOGIN_LEFT');
        $view = array(
            'ad' => $ad
        );
        if ($status)
            return $this->theme->scope('user.resetsuccess',$view)->render();
    }


    /**
     * 找回密码发送邮件等待验证
     *
     * @return mixed
     */
    public function waitValidation($email)
    {
        $email = Crypt::decrypt($email);
        //登录左侧广告
        $ad = AdTargetModel::getAdInfo('LOGIN_LEFT');
        $view = array(
            'email' => $email,
            'emailType' => substr($email, strpos($email, '@') + 1),
            'ad' => $ad
        );
        $this->initTheme('auth');
        $this->theme->set('authAction', '找回密码');
        $this->theme->setTitle('找回密码');
        return $this->theme->scope('user.waitvalidation', $view)->render();
    }

    /**
     * 密码重置邮件验证
     *
     * @param $validationInfo
     * @return mixed
     */
    public function resetValidation($validationInfo)
    {
        $info = Crypt::decrypt($validationInfo);
        $user = UserModel::where('email', $info['email'])->where('reset_password_code', $info['resetPasswordCode'])->first();
        //登录左侧广告
        $ad = AdTargetModel::getAdInfo('LOGIN_LEFT');
        $this->initTheme('auth');
        if (!$user || $user && time() > strtotime($user->expire_date)){
            $data = array(
                'email' => $validationInfo,
                'ad' => $ad
            );
            return $this->theme->scope('user.passwordfail',$data)->render();
        }
        $view = array(
            'validationInfo' => $validationInfo,
            'ad' => $ad
        );
        $this->theme->setTitle('找回密码');
        return $this->theme->scope('user.resetpassword', $view)->render();
    }

    /**
     * 检测邮箱是否注册
     *
     * @param Request $request
     * @return string
     */
    public function checkEmail(Request $request)
    {
        $email = $request->get('param');

        $status = UserModel::where('email', $email)->first();
        if (empty($status)){
            $status = 'n';
            $info = '邮箱未注册';
        } else {
            $info = '';
            $status = 'y';
        }
        $data = array(
            'info' => $info,
            'status' => $status
        );
        return json_encode($data);
    }

    /**
     * 检测验证码
     *
     * @param Request $request
     * @return string
     */
    public function checkCode(Request $request)
    {
        $code = $request->get('param');

        if (!\CommonClass::checkCode($code)){
            $data = array(
                'info' => '验证码错误',
                'status' => 'n'
            );
        } else {
            $data = array(
                'info' => '',
                'status' => 'y'
            );
        }
        return json_encode($data);
    }

    /**
     * 重新发送找回密码邮件
     *
     * @param $email
     * @return string
     */
    public function reSendPasswordEmail($email)
    {
        $email = Crypt::decrypt($email);
        $status = \MessagesClass::sendPasswordEmail($email);

        if ($status)
            return \CommonClass::formatResponse('success');
    }

    /**
     * 手机找回第一步页面
     *
     * @return mixed
     */
    public function getMobile()
    {
        $this->theme->set('authAction', '找回密码');
        $this->initTheme('auth');
        $this->theme->setTitle('找回密码');
        //登录左侧广告
        $ad = AdTargetModel::getAdInfo('LOGIN_LEFT');
        $view = array(
            'ad' => $ad
        );

        return $this->theme->scope('user.passwordmobile',$view)->render();
    }

    /**
     * 验证手机号验证码
     *
     * @param Request $request
     * @return $this
     */
    public function postMobile(Request $request)
    {
        $authMobileInfo = session('password_mobile_info');

        $data = $request->except('_token');

        if ($data['mobile'] == $authMobileInfo['mobile'] && $data['code'] == $authMobileInfo['code']){
            Session::forget('password_mobile_info');
            Session::put('mobile_reset', $data['mobile']);
            return redirect('password/mobileReset');
        }

        return back()->withInput()->withErrors(['code' => '请输入正确的验证码']);
    }

    /**
     * 手机找回密码重置密码视图
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function getMobileReset()
    {
        if (Session::get('mobile_reset')){
            $this->initTheme('auth');
            //登录左侧广告
            $ad = AdTargetModel::getAdInfo('LOGIN_LEFT');
            $view = array(
                'ad' => $ad
            );
            return $this->theme->scope('user.mobileresetpassword',$view)->render();
        }

    }

    /**
     * 手机找回重置密码
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postMobileReset(Request $request)
    {
        $data = $request->except('_token');

        if ($data['password'] == $data['confirmPassword']){
            $salt = str_random(4);
            $password = UserModel::encryptPassword($data['password'], $salt);
            $status = UserModel::where('mobile', Session::get('mobile_reset'))->update(['password' => $password, 'salt' => $salt]);
            if ($status)
                Session::forget('mobile_reset');
                return redirect('password/mobileResetSuccess');
        }

    }

    /**
     * 手机找回重置密码成功
     *
     * @return mixed
     */
    public function mobileResetSuccess()
    {
        $this->initTheme('auth');
        //登录左侧广告
        $ad = AdTargetModel::getAdInfo('LOGIN_LEFT');
        $view = array(
            'ad' => $ad
        );
        return $this->theme->scope('user.mobileresetsuccess',$view)->render();
    }


    /**
     * 手机找回验证码发送
     *
     * @param Request $request
     * @return string
     */
    public function sendMobilePasswordCode(Request $request)
    {
        $arr = $request->except('_token');

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
                //验证手机号是否注册
                $userExitsis = UserModel::where('mobile',$arr['mobile'])->first();
                $userInfoExitsis = UserDetailModel::where('mobile',$arr['mobile'])->first();
                if(empty($userExitsis) || empty($userInfoExitsis)){
                    return ['code' => 1002, 'msg' => '该手机号没有注册'];
                }
                //发送找回短信
                $code = rand(1000, 9999);

                $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                $templateId = ConfigModel::phpSmsConfig('sendMobilePasswordCode');
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
                    Session::put('password_mobile_info', $data);
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

                //验证手机号是否注册
                $userExitsis = UserModel::where('mobile',$arr['mobile'])->first();
                $userInfoExitsis = UserDetailModel::where('mobile',$arr['mobile'])->first();
                if(empty($userExitsis) || empty($userInfoExitsis)){
                    return ['code' => 1002, 'msg' => '该手机号没有注册'];
                }

                //发送注册短信
                $code = rand(1000, 9999);

                $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                $templateId = ConfigModel::phpSmsConfig('sendMobilePasswordCode');
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
                    Session::put('password_mobile_info', $data);
                    return ['code' => 1000, 'msg' => '短信发送成功'];
                } else {
                    return ['code' => 1001, 'msg' => '短信发送失败'];
                }
            } else {
                return ['info' => 0, 'msg' => '请先通过滑块验证'];
            }
        }

    }
}
