<?php
namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\UserCenterController;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\User\Http\Requests\AlipayAuthRequest;
use App\Modules\User\Http\Requests\EmailAuthRequest;
use App\Modules\User\Http\Requests\VerifyAlipayCashRequest;
use App\Modules\User\Http\Requests\VerifyBankCashRequest;
use App\Modules\User\Model\AlipayAuthModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\BankAuthModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Http\Requests\BankAuthRequest;
use App\Modules\User\Http\Requests\RealnameAuthRequest;
use App\Modules\User\Model\EnterpriseAuthModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\Shop\Models\ShopModel;
use Illuminate\Http\Request;
use Auth;
use Crypt;
use Illuminate\Support\Facades\Session;
use Socialite;

class AuthController extends UserCenterController
{

    public function __construct()
    {
        parent::__construct();
        $this->initTheme('userinfo');
    }

    /**
     * 邮箱认证视图
     *
     * @return mixed
     */
    public function getEmailAuth()
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userInfo");
        $this->theme->set("userInfo","3");
        $this->theme->setTitle('邮箱认证');
        $this->theme->set("userOneColumn","账号设置");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","邮箱认证");
        $this->theme->set("userSecondColumnUrl","/user/emailAuth");
        $user = Auth::User();
        switch ($user->email_status){
            case 0:
                $data = array(
                    'email' => $user->email,
                    'step' => 1
                );
                break;
            case 1:
                $data = array(
                    'email' => $user->email,
                    'step' => 2,
                    'emailType' => substr($user->email, strpos($user->email, '@') + 1)
                );
                break;
            case 2:
                $data = array(
                    'email' => $user->email,
                    'step' => 3
                );
                break;
            case 3:
                $data = array(
                    'email' => $user->email,
                    'step' => 4
                );
        }
        return $this->theme->scope('user.emailauth', $data)->render();
    }
    //更改认证邮箱
    public function emailAuthChange(){
        $user = Auth::User();
           //修改邮箱状态
        UserModel::where("id",$user->id)->update([
            'email_status'=>3,
        ]);
        return  redirect("/user/emailAuth");
    }
    /**
     * 手机认证视图
     *
     * @return mixed
     */
    public function getPhoneAuth(Request $request)
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userInfo");
        $this->theme->setTitle('手机认证');
        $this->theme->set("userInfo",2);
        $this->theme->set("userOneColumn","账号设置");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","手机认证");
        $this->theme->set("userSecondColumnUrl","/user/phoneAuth");
        $user = UserModel::where('id', Auth::id())->first();
        
        $load = isset($request->load)?$request->load:'';
        $data = [
            'mobile' => $user->mobile ? $user->mobile : '',
            'load'  => $load
        ];

        return $this->theme->scope('user.phoneauth', $data)->render();
    }

    /**
     * 手机绑定提交验证
     *
     * @param Request $request
     * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postPhoneAuth(Request $request)
    {
        $data = $request->except('_token');
        $user = Auth::User();
        
        $auth = Session::get('mobile_bind_info');
        if ($data['mobile'] == $auth['mobile'] && $data['code'] == $auth['code']){
            $status = UserModel::where('id', Auth::id())->update(['mobile' => $data['mobile']]);
            UserDetailModel::where('uid', Auth::id())->update(['mobile' => $data['mobile']]);

            if ($status){
                Session::forget('mobile_bind_info');
                if(isset($data['load']) && $data['load'] == 1){
                    return redirect('user/shop');
                }
                return redirect('user/phoneAuth');
            }
        }
        return back()->withInput()->with(['error' => '请输入正确的验证码']);
    }
    /*
     * 修改手机号码
     *
     * */
    public function changePhone(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userInfo");
        $this->theme->setTitle('手机认证');
        $this->theme->set("userInfo",2);
        $this->theme->set("userOneColumn","账号设置");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","手机认证");
        $this->theme->set("userSecondColumnUrl","/user/phoneAuth");
        $data=[];
        return $this->theme->scope('user.changephone', $data)->render();
    }
    /**
     * 发送手机绑定验证
     *
     * @param Request $request
     * @return string
     */
    public function sendBindSms(Request $request)
    {
        $arr = $request->except('_token');
        $mobile = $arr['mobile'];

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
                //手机绑定模板
                $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                $templateId = ConfigModel::phpSmsConfig('sendBindSms');
                $templates = [
                    $scheme => $templateId,
                ];

                $tempData = [
                    'code' => rand(1000, 9999),
                    'time' => '5'
                ];

                $status = \SmsClass::sendSms($mobile, $templates, $tempData);

                if ($status['success']){
                    $info = [
                        'mobile' => $mobile,
                        'code' => $tempData['code']
                    ];
                    Session::put('mobile_bind_info', $info);
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

                //手机绑定模板
                $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                $templateId = ConfigModel::phpSmsConfig('sendBindSms');
                $templates = [
                    $scheme => $templateId,
                ];

                $tempData = [
                    'code' => rand(1000, 9999),
                    'time' => '5'
                ];

                $status = \SmsClass::sendSms($mobile, $templates, $tempData);

                if ($status['success']){
                    $info = [
                        'mobile' => $mobile,
                        'code' => $tempData['code']
                    ];
                    Session::put('mobile_bind_info', $info);
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
     * 解除手机绑定视图
     *
     * @return mixed
     */
    public function getUnbindMobile()
    {
        $user = UserModel::where('id', Auth::id())->whereNotNull('mobile')->first();

        if (!empty($user)){
            $data = [
                'mobile' => $user->mobile
            ];
            return $this->theme->scope('user.unbindmobile', $data)->render();
        }

    }

    /**
     * 手机解除绑定验证
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postUnbindMobile(Request $request)
    {
        $data = $request->except('_token');

        $info = Session::get('mobile_unbind_info');

        if ($data['mobile'] == $info['mobile'] && $data['code'] == $info['code']){
            $status = UserModel::where('id', Auth::id())->update(['mobile' => '']);
            if ($status){
                Session::forget('mobile_unbind_info');
                return redirect('user/phoneAuth');
            }
        }

        return back()->withErrors(['code' => '请输入正确的验证码']);
    }

    /**
     * 发送解绑短信
     *
     * @param Request $request
     * @return string
     */
    public function sendUnbindSms(Request $request)
    {
        $arr = $request->except('_token');
        $mobile = $arr['mobile'];

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

                //手机解除绑定模板
                $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                $templateId = ConfigModel::phpSmsConfig('sendUnbindSms');
                $templates = [
                    $scheme => $templateId,
                ];

                $tempData = [
                    'code' => rand(1000, 9999),
                    'time' => '5'
                ];

                $status = \SmsClass::sendSms($mobile, $templates, $tempData);

                if ($status['success']){
                    $info = [
                        'mobile' => $mobile,
                        'code' => $tempData['code']
                    ];
                    Session::put('mobile_unbind_info', $info);
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

                //手机解除绑定模板
                $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                $templateId = ConfigModel::phpSmsConfig('sendUnbindSms');
                $templates = [
                    $scheme => $templateId,
                ];

                $tempData = [
                    'code' => rand(1000, 9999),
                    'time' => '5'
                ];

                $status = \SmsClass::sendSms($mobile, $templates, $tempData);

                if ($status['success']){
                    $info = [
                        'mobile' => $mobile,
                        'code' => $tempData['code']
                    ];
                    Session::put('mobile_unbind_info', $info);
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
     * 发送邮箱验证邮件
     *
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function sendEmailAuth(EmailAuthRequest $request)
    {
        $user = Auth::User();
        $email = $user->email;
        if (empty($user->email)){
            $email = $request->get('email');
        }else{
            // if($email == trim($request->get('email'))){
            //     // UserModel::where("id",$user->id)->where("email_status",3)->update(["email_status"=>2]);
            //     return back()->withErrors(['emailChange' => '当前邮箱已绑定']);
            // }
            $email=$request->get('email');
        }
        $res = UserModel::where(['email' => $email, 'email_status' => 2])->first();
        if (!empty($res)){
            return back()->withErrors(['email' => '当前邮箱已绑定']);
        }
        $data = array(
            'email' => $email,
            'email_status' => 1,
            'validation_code' => \CommonClass::random(4),
            'overdue_date' => date('Y-m-d H:i:s', time() + 60*10)
        );
        $status = $user->update($data);
        if ($status){
            $status = \MessagesClass::sendEmailAuth($email);
            if ($status){
                return redirect('user/emailAuth');
            }
        }
    }

    /**
     * 再次发送邮件
     * @param $email
     * @return \Illuminate\Http\JsonResponse
     */
    public function reSendEmailAuth($email)
    {
        $email = Crypt::decrypt($email);
        $status = \MessagesClass::sendEmailAuth($email);

        if ($status){
            return response()->json([
                'status' => 'success'
            ]);
        }

        return response()->json([
            'status' => 'fail',
        ]);
    }


    /**
     * 绑定邮箱再次发送
     * @param $email
     * @return \Illuminate\Http\JsonResponse
     */
    public function reSendEmailAuthBand($email)
    {
        $status = \MessagesClass::sendEmailAuth($email);

        if ($status){
            return response()->json([
                'status' => 'success'
            ]);
        }

        return response()->json([
            'status' => 'fail',
        ]);
    }

    /**
     * 邮箱绑定验证
     *
     * @param $validatonInfo
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function verifyEmail($validatonInfo)
    {
        $validatonInfo = Crypt::decrypt($validatonInfo);
        $info = UserModel::where('email', $validatonInfo['email'])->where('validation_code', $validatonInfo['validationCode'])->first();
        if (!empty($info)){
            if (time() < strtotime($info->overdue_date)){
                $user = Auth::User();
                $status = $user->update(array('email_status' => 2));
                if ($status)
                    return redirect('user/emailAuth');
            }
        }

    }

    /**
     * 支付绑定列表视图
     *
     * @return mixed
     */
    public function getPayList()
    {
        $this->theme->setTitle('支付认证');
        $user = Auth::User();
        //查询认证记录数
        $bankAuth = BankAuthModel::where('uid', $user->id)->count();
        $alipayAuth = AlipayAuthModel::where('uid', $user->id)->count();
        $data = array(
            'bankAuth' => $bankAuth,
            'alipayAuth' => $alipayAuth
        );

        return $this->theme->scope('user.paylist', $data)->render();
    }


    //身份认证视图
    public function getRealnameAuth()
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userInfo");
        $this->theme->setTitle('实名认证');
        $this->theme->set("userInfo",1);
        $this->theme->set("userOneColumn","账号设置");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","实名认证");
        $this->theme->set("userSecondColumnUrl","/user/realnameAuth");
        $user = Auth::User();
        //获取个人认证信息
        $realnameInfo = RealnameAuthModel::where('uid', $user->id)->orderBy('created_at', 'desc')->first();
        $data = array();
        //获取用户认证信息
        if (isset($realnameInfo->status)) {
            $data = array(
                'realnameInfo' =>$realnameInfo,
                'card_number' =>\CommonClass::starReplace($realnameInfo->card_number, 4, 10),
            );
        }else {
            $data = array(
                'realnameInfo' =>null,
                'card_number' => null,
            );
        }
        $view = 'user.realnameauth';
        return $this->theme->scope($view, $data)->render();
    }

    //身份企业认证
    public function getEnterpriseAuth(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userInfo");
        $this->theme->setTitle('企业认证');
        $this->theme->set("userInfo",9);
        $this->theme->set("userOneColumn","账号设置");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","企业认证");
        $this->theme->set("userSecondColumnUrl","/user/enterpriseAuth");
        $user = Auth::User();
        //判断客户有没有进行实名认证
//        $realnameAuth=RealnameAuthModel::where("uid",$user->id)->where("status",1)->first();
//        if(!$realnameAuth){
//            return redirect("/user/realnameAuth")->with(["message"=>"请先进行实名认证"]);
//        }
       //获取企业认证信息
        $enterpriseInfo=EnterpriseAuthModel::where('uid', $user->id)->orderBy('created_at', 'desc')->first();
        $data = array();
        //获取用户认证信息
        if (isset($enterpriseInfo->status)) {
            $data = array(
                'enterpriseInfo'=>$enterpriseInfo,
                'card_number' =>\CommonClass::starReplace($enterpriseInfo->card_number, 4, 10),
            );
        }else {
            $data = array(
                'enterpriseInfo'=>null,
                'card_number' => null,
            );
        }
        $view = 'user.enterpriseAuth';
        return $this->theme->scope($view, $data)->render();
    }
    /**
     * 身份认证
     *
     * @param RealnameAuthRequest $request
     * @return $this|\Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postRealnameAuth(RealnameAuthRequest $request)
    {
        $status = RealnameAuthModel::realnameAuthPost($request);
        if ($status)
            return redirect('user/realnameAuth');
    }
    /*
     * 企业认证数据提交
     * */
    public function postEnterpriseAuth(Request $request){
        $status=RealnameAuthModel::realnameAuthPost($request);
        if ($status)
            return redirect('user/enterpriseAuth');
    }
    /**
     * 重新实名认证
     *
     * @return mixed
     */
    public function reAuthRealname()
    {
        $this->theme->setTitle('实名认证');
        $user = Auth::User();
        $realnameInfo = RealnameAuthModel::where('uid', $user->id)->where('status', 2)->orderBy('created_at', 'desc')->first();

        if ($realnameInfo){
            return $this->theme->scope('user.realnameauth')->render();
        }
    }


    /**
     * 银行认证视图
     *
     * @return mixed
     */
    public function getBankAuth(Request $request)
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userInfo");
        $this->theme->setTitle('银行卡认证');
        $this->theme->set("userInfo",5);
        $this->theme->set("userOneColumn","账号设置");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","银行卡认证");
        $this->theme->set("userSecondColumnUrl","/user/bankAuthList");
        $bankname=\CommonClass::bankAllList();

        $findBankAuth=[];
        $depositAre='';
        if($request->get("id")){
            $id=Crypt::decrypt($request->get("id"));
            $findBankAuth=BankAuthModel::find($id);
            $deposit_area=explode(",",$findBankAuth['deposit_area']);
            $depositAre = DistrictModel::where("id",$deposit_area[0])->pluck("name") . DistrictModel::where("id",$deposit_area[1])->pluck("name") . DistrictModel::where("id",$deposit_area[2])->pluck("name");
        }
            $province = DistrictModel::findTree(0);
            //查询城市数据
            $city = DistrictModel::findTree($province[0]['id']);
            //查询地区信息
            $area = DistrictModel::findTree($city[0]['id']);

        $data = array(
            'province' => $province,
            'city'  =>$city,
            'area'=>$area,
            'bankname' => $bankname,
            'findBankAuth'=>$findBankAuth,
            'depositAre'=>$depositAre
        );

        return $this->theme->scope('user.bankauth', $data)->render();
    }

    /**
     * 检测店铺名称是否可用
     *
     * @param Request $request
     * @return string
     */
    public function checkShopName(Request $request)
    {
        $ShopName = $request->get('param');
        $uid = '';
        if(Auth::check()){
            $uid = Auth::User()->id;
        }
        if(empty($uid)){
            return json_encode([
                  $status = 'n',
                  $info = '请先登陆！',  
                ]);
        }
        $status = ShopModel::where('shop_name', $ShopName)->where('uid','!=',$uid)->first();
        if (empty($status)){
            $status = 'y';
            $info = '';
        } else {
            $info = '店铺名称已占用';
            $status = 'n';
        }
        $data = array(
            'info' => $info,
            'status' => $status
        );
        return json_encode($data);
    }
    /**
     * 获取地区信息
     *
     * @param Request $request
     * @return string
     */
    public function getZone(Request $request)
    {
        $id = intval($request->get('id'));
        if (!$id) {
            return \CommonClass::formatResponse('参数错误', 1001);
        }

        $arrZones = DistrictModel::findTree($id);
        $html = '';
        if ($arrZones) {
            foreach ($arrZones as $k => $v) {
                $html .= "<option value='" . $v['id'] . "'>" . $v['name'] . "</option>";
            }
        } else {
            $html .= "<option value=''>没有了</option>";
        }

        return \CommonClass::formatResponse('success', 200, $html);
    }


    /**
     * 银行认证
     *
     * @param BankAuthRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|mixed
     */
    public function postBankAuth(BankAuthRequest $request)
    {
        $user = Auth::User();
        $userDetail = UserDetailModel::where('uid', $user->id)->first();
        //检测用户是否实名认证
        /*$realnameAuth = AuthRecordModel::checkUserAuth($user->id, 'realname');
        if (!$realnameAuth) {
            return back()->with(['message' => '请先进行实名认证']);
        }*/
        //批量赋值写入银行认证和认证记录表
        $depositArea = $request->get('province') . ',' . $request->get('city') . ',' . $request->get('area');
        $bankAuthInfo = array();
        $authRecordInfo = array();
        $now = time();
        $bankName=\CommonClass::bankAllList($request->get("bankname"));
        $bankAuthInfo['uid'] = $user->id;
        $bankAuthInfo['username'] = $user->name;
        $bankAuthInfo['realname'] = $request->get('realname');
        $bankAuthInfo['bank_name'] = $bankName['name'];
        $bankAuthInfo['bank_img'] = $bankName['img'];
        $bankAuthInfo['bank_account'] = $request->get('bankAccount');
        $bankAuthInfo['deposit_name'] = $request->get('depositName');
        $bankAuthInfo['deposit_area'] = $depositArea;
        $bankAuthInfo['created_at'] = date('Y-m-d H:i:s', $now);
        $bankAuthInfo['updated_at'] = date('Y-m-d H:i:s', $now);
        $authRecordInfo['uid'] = $user->id;
        $authRecordInfo['username'] = $user->name;
        $authRecordInfo['auth_code'] = 'bank';
        //写入银行认证及认证记录表
        $status = BankAuthModel::createBankAuth($bankAuthInfo, $authRecordInfo);
        if ($status)
            return redirect('/user/bankAuth?id=' . Crypt::encrypt($status));
    }

    /**
     * 等待银行认证
     *
     * @param $bankAuthId
     * @return mixed
     */
    public function getBankAuthSchedule($bankAuthId)
    {
        $this->theme->setTitle('银行认证');
        $bankAuthId = Crypt::decrypt($bankAuthId);
        $user = Auth::User();

        $authInfo = BankAuthModel::where('id', $bankAuthId)->where('uid', $user->id)->first();
        if (!empty($authInfo)){
            $arrDistrict = explode(',', $authInfo->deposit_area);

            $authInfo['districtname'] = DistrictModel::getDistrictName($arrDistrict);

            switch ($authInfo['status']) {
                //银行认证待打款
                case 0:
                    $path = 'user.waitbankauth';
                    break;
                //填写打款金额
                case 1:
                    $path = 'user.bankauthcash';
                    break;
                //银行认证成功
                case 2:
                    $path = 'user.bankauthsuccess';
                    break;
                //银行认证失败
                case 3:
                    $path = 'user.bankauthfail';
                    break;
            }
            $view = array(
                'authInfo' => $authInfo,
                'authId' => $bankAuthId
            );
            return $this->theme->scope($path, $view)->render();
        }
    }

    /**
     * 验证银行认证打款金额
     *
     * @param VerifyBankCashRequest $request
     * @return mixed
     */
    public function verifyBankAuthCash(VerifyBankCashRequest $request)
    {
        $this->theme->setTitle('银行认证');
        $authId = Crypt::decrypt($request->get('bankAuthId'));
        //查询认证信息
        $user = Auth::User();
        $bankAuthInfo = BankAuthModel::where('uid', $user->id)->where('id', $authId)->first();

        if ($bankAuthInfo) {
            //验证金额
            $bankAuthInfo->user_get_cash = $request->get('cash');
            if ($bankAuthInfo['pay_to_user_cash'] == $request->get('cash')) {
                //修改认证状态
                $res = BankAuthModel::bankAuthPass($authId);
                $error="认证成功";
            } else {
                $res = BankAuthModel::bankAuthDeny($authId);
                $error="认证失败";
            }
            $bankAuthInfo['districtname'] = DistrictModel::getDistrictName(explode(',', $bankAuthInfo->deposit_area));
            $data = array(
                'authInfo' => $bankAuthInfo
            );
            return redirect("/user/bankAuth?id=".Crypt::encrypt($authId))->with(["message"=>$error]);
        }
    }

    /**
     * 银行认证列表视图
     *
     * @return mixed
     */
    public function listBankAuth()
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userInfo");
        $this->theme->setTitle('银行卡认证');
        $this->theme->set("userInfo",5);
        $this->theme->set("userOneColumn","账号设置");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","银行卡认证");
        $this->theme->set("userSecondColumnUrl","/user/bankAuthList");
        $user = Auth::User();

        $arrBankAuth = BankAuthModel::where('uid', $user->id)->get();

        $view = array(
            'arrBankAuth' => $arrBankAuth
        );
        return $this->theme->scope('user.bankauthlist', $view)->render();
    }

    /**
     * 停用启用银行卡
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector|mixed
     */
    public function changeBankAuth(Request $request)
    {
        $authId = Crypt::decrypt($request->get('authId'));
        $bankAuthInfo = BankAuthModel::where('id', $authId)->first();
        if (!empty($bankAuthInfo) && $bankAuthInfo->status == 2 || $bankAuthInfo->status == 4){
            if ($bankAuthInfo->status == 2){
                $status = 4;
            } else {
                $status = 2;
            }
            $status = BankAuthModel::changeBankAuth($authId, $status);

            if ($status)
                return redirect('/user/bankAuthList');
        }
    }

    /**
     * 支付宝认证视图
     *
     * @return mixed
     */
    public function getAlipayAuth(Request $request)
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userInfo");
        $this->theme->setTitle('支付宝认证');
        $this->theme->set("userInfo",4);
        $this->theme->set("userOneColumn","账号设置");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","支付宝认证");
        $this->theme->set("userSecondColumnUrl","/user/alipayAuth");
        $alipayAuth=[];
        if($request->get('id')){
            $id= Crypt::decrypt($request->get('id'));
            $alipayAuth=AlipayAuthModel::find($id);
        }
        $data=[
            'alipayAuth'=>$alipayAuth,
        ];
//        $province = DistrictModel::findTree(0);
//        //查询城市数据
//        $city = DistrictModel::findTree($province[0]['id']);
//        //查询地区信息
//        $area = DistrictModel::findTree($city[0]['id']);
//
//        $data=[
//           'province'=>$province,
//            'city'   =>$city,
//            'area'   =>$area
//        ];
        return $this->theme->scope('user.alipayauth',$data)->render();
    }

    /**
     * 新增支付认证
     *
     * @param AlipayAuthRequest $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function postAlipayAuth(AlipayAuthRequest $request)
    {
        //写入认证记录表和支付宝认证表
        $user = Auth::User();
        $userDetail = UserDetailModel::where('uid', $user->id)->first();
        //判断用户是否进行了实名认证或者企业认证
        $realnameAuth=RealnameAuthModel::where("uid",$user->id)->where("status",1)->first();
        $enterpriseAuth=EnterpriseAuthModel::where("uid",$user->id)->where("status",1)->first();
        if(!sizeof($realnameAuth) && !sizeof($enterpriseAuth)){
            return redirect()->to("/user/realnameAuth")->with(["message"=>"请先进行实名认证"]);
        }
        $isError=true;
        if($realnameAuth && $realnameAuth['realname'] ==trim($request->get('alipayName'))){
            $isError=false;
        }
        if($enterpriseAuth && $enterpriseAuth['company_name'] ==trim($request->get('alipayName'))){
            $isError=false;
        }
        if($isError){
            return back()->with(["message"=>"支付宝姓名与认证信息不符"]);
        }
        $alipayAuthInfo = array();
        $alipayAuthInfo['uid'] = $user->id;
        $alipayAuthInfo['username'] = $user->name;
        $alipayAuthInfo['realname'] = $userDetail['realname'];
        $alipayAuthInfo['alipay_name'] = $request->get('alipayName');
        $alipayAuthInfo['alipay_account'] = $request->get('alipayAccount');
        $alipayAuthInfo['created_at'] = date('Y-m-d H:i:s');
        $alipayAuthInfo['updated_at'] = date('Y-m-d H:i:s');
        $alipayAuthInfo['status'] = 0;

        $authRecordInfo = array();
        $authRecordInfo['uid'] = $user->id;
        $authRecordInfo['username'] = $user->name;
        $authRecordInfo['auth_code'] = 'alipay';
        $status = AlipayAuthModel::createAlipayAuth($alipayAuthInfo, $authRecordInfo);
        if ($status)
            return redirect('/user/alipayAuth?id=' .Crypt::encrypt($status));
    }


    /**
     * 支付宝认证进度
     *
     * @param $alipayAuthId
     * @return mixed
     */
    public function getAlipayAuthSchedule($alipayAuthId)
    {
        $this->theme->setTitle('支付宝认证');
        $alipayAuthId = Crypt::decrypt($alipayAuthId);
        //查找认证信息
        $user = Auth::User();
        $alipayAuthInfo = AlipayAuthModel::where('id', $alipayAuthId)->where('uid', $user->id)->first();

        if (!empty($alipayAuthInfo)){
            $view = array();
            $view['alipayAuthInfo'] = $alipayAuthInfo;
            switch ($alipayAuthInfo['status']) {
                //支付宝认证待审核 or 打款中
                case 0:
                    $path = 'user.waitalipayauth';
                    break;
                case 1://验证金额
                    $path = 'user.alipayauthcash';
                    break;
                case 2://认证成功
                    $path = 'user.alipayauthsuccess';
                    break;
                case 3://认证失败
                    $path = 'user.alipayauthfail';
                    break;
            }
            return $this->theme->scope($path, $view)->render();
        }

    }

    /**
     * 验证支付宝金额
     *
     * @param VerifyAlipayCashRequest $request
     * @return mixed
     */
    public function verifyAlipayAuthCash(VerifyAlipayCashRequest $request)
    {
        $this->theme->setTitle('支付宝认证');
        $authId = Crypt::decrypt($request->get('alipayAuthId'));
        //查询认证信息
        $user = Auth::User();
        $alipayAuthInfo = AlipayAuthModel::where('uid', $user->id)->where('id', $authId)->first();

        if ($alipayAuthInfo) {
            //验证金额
            $view = array();
            $view['alipayAuthInfo'] = $alipayAuthInfo;
            $alipayAuthInfo->user_get_cash = $request->get('cash');
            $tpl="/user/alipayAuth?id=".Crypt::encrypt($authId);
            if ($alipayAuthInfo['pay_to_user_cash'] == $request->get('cash')) {
                $res = AlipayAuthModel::alipayAuthPass($authId);
                return redirect($tpl)->with(["message"=>"认证成功"]);
                //$tpl = 'user.alipayauthsuccess';
            } else {
                $res = AlipayAuthModel::alipayAuthDeny($authId);
                return redirect($tpl)->with(["message"=>"认证失败"]);
                //$tpl = 'user.alipayauthfail';
            }


//            if ($res) {
//                return $this->theme->scope($tpl, $view)->render();
//            }
        }

    }

    /**
     * 删除支付宝认证（待审核和认证失败的）
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function deleteAlipay(Request $request)
    {
        if($request->get('id')){
            $id= Crypt::decrypt($request->get('id'));
            $alipayAuth=AlipayAuthModel::find($id);
            if(!in_array($alipayAuth->status,[2,3])){
                return redirect()->back()->with(['error' => '缺少参数']);
            }
            $res = AlipayAuthModel::where('id',$id)->delete();
            AuthRecordModel::where('auth_id',$id)->where('auth_code','alipay')->delete();
            if($res){
                return redirect()->back()->with(['message' => '删除成功']);
            }else{
                return redirect()->back()->with(['error' => '删除失败']);
            }
        }else{
            return redirect()->back()->with(['error' => '缺少参数']);
        }
    }

    /**
     * 支付宝认证列表
     *
     * @return mixed
     */
    public function listAlipayAuth()
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userInfo");
        $this->theme->setTitle('支付宝认证');
        $this->theme->set("userInfo",4);
        $this->theme->set("userOneColumn","账号设置");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","支付宝认证");
        $this->theme->set("userSecondColumnUrl","/user/alipayAuthList");
        $user = Auth::User();

        $arrAlipayAuth = AlipayAuthModel::where('uid', $user->id)->where("is_del",0)->get()->toArray();
        // dd($arrAlipayAuth);
        $view = array(
            'arrAlipayAuth' => $arrAlipayAuth
        );
        return $this->theme->scope('user.alipayauthlist', $view)->render();
    }

    /**
     * 启用停用支付宝认证
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function changeAlipayAuth(Request $request)
    {
        $id = Crypt::decrypt($request->get('authId'));
        $alipayAuthInfo = AlipayAuthModel::where('id', $id)->first();

        if (!empty($alipayAuthInfo) && $alipayAuthInfo->status == 2 || $alipayAuthInfo->status == 4){
            if ($alipayAuthInfo->status == 2){
                $status = 4;
            } else {
                $status = 2;
            }
            $res = AlipayAuthModel::changeAlipayAuth($id, $status);

            if ($res)
                return redirect('/user/alipayAuthList');
        }
    }

    /**
     * 第三方账号绑定
     * @param $type
     * @return mixed
     */
    public function oauthBind($type){
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
        $redirectUrl = url('oauth/' . $type . '/callback?uid='.Auth::user()->id);
        $config = new \SocialiteProviders\Manager\Config($clientId, $clientSecret, $redirectUrl);
        return Socialite::with($type)->setConfig($config)->redirect();
    }

}