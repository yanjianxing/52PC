<?php
namespace App\Modules\User\Http\Controllers;

use App\Http\Controllers\UserCenterController as BasicUserCenterController;
use App\Http\Controllers\AuthController;
use App\Http\Requests;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\ActivityModel;
use App\Modules\Manage\Model\CateModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Manage\Model\UserCouponModel;
use App\Modules\Manage\Model\VipModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use App\Modules\Pay\OrderModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ProgrammeEnquiryMessageModel;
use App\Modules\Shop\Models\ProgrammeInquiryPayModel;
use App\Modules\Shop\Models\ProgrammeOrderModel;
use App\Modules\Shop\Models\ShopFocusModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskFocusModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskTypeModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Http\Requests\PasswordRequest;
use App\Modules\User\Http\Requests\UserInfoRequest;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\BankAuthModel;
use App\Modules\User\Model\CollectionModel;
use App\Modules\User\Model\CommentModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\MessageReceiveModel;
use App\Modules\User\Model\PromoteModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\User\Model\TagsModel;
use App\Modules\User\Model\UserDepositModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserFocusModel;
use App\Modules\User\Model\UserTagsModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\UserVipCardModel;
use App\User;
use Auth;
use Illuminate\Http\Request;
use Gregwar\Image\Image;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Theme;

class UserCenterController extends BasicUserCenterController
{

    public function __construct()
    {
        parent::__construct();
        $this->user ="";
        //查看该用户是否开启的店铺
        if(Auth::check()){
            $this->user = Auth::user();
            $shop=ShopModel::where("uid",Auth::user()->id)->where("status",1)->first();
            $this->theme->set("shop_open",false);
            $this->theme->set("shop_com",false);
            if($shop){
                $this->theme->set("shop_open",true);
				$this->theme->set("shopInfo",$shop);
                if($shop['type'] ==2){
                    $this->theme->set("shop_com",true);
                }
            }
            $this->initTheme('accepttask');//主题初始化
        }
    }

    /**
     * 用户中心首页页面
     */
    public function index()
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userIndex");
        $this->theme->setTitle('个人主页');
        $this->theme->set("userIndex",13);
        //查看用户信息
        $userDetail=UserDetailModel::leftJoin('users','users.id','=','user_detail.uid')->where("user_detail.uid",Auth::user()->id)->select('user_detail.*','users.level')->first();
        //.获取竞标卡
        $res=UserVipCardModel::getBidCard();
        //.最新活动
        $activity = ActivityModel::where('status',1)->where('type',1)->orderBy('pub_at','desc')->limit(6)->get()->toArray();
        $this->theme->set('HOME_ACTIVITY', $activity);
        //获取可用优惠券个数
        $couponCount=UserCouponModel::where("status",1)->where("uid",Auth::user()->id)->count();
        //查询认证信息
         $authInfo=[
             'is_real'=>false,
             'is_email'=>false,
             'is_bank'=>false,
             'is_phone'=>false,
             //.新增其它认证
             'is_enterprise'=>false,//.企业
             'is_promise'=>false,//.保证金
             'is_alipay'=>false,//.支付
         ];
         //查询实名认证
        $realnameAuth=RealnameAuthModel::where("uid",Auth::user()->id)->where("status",1)->first();
        if($realnameAuth){
            $authInfo['is_real']=true;
        }
        //查询邮箱认证
        $authInfo['is_email']=Auth::user()->email_status ==2?true:false;
        //查询银行认证
        $bankAuth=BankAuthModel::where("uid",Auth::user()->id)->where("status",2)->first();
        if($bankAuth){
            $authInfo['is_bank']=true;
        }
        //手机认证
        $authInfo['is_phone']=!empty(Auth::user()->mobile)?true:false;
        //.新增认证
        //.企业认证
        $enterprise=AuthRecordModel::where("uid",Auth::user()->id)->where('auth_code','enterprise')->where("status",1)->first();
        if(isset($enterprise)){
            $authInfo['is_enterprise']=true;
        }
        //.保证金认证
        $promise=AuthRecordModel::where("uid",Auth::user()->id)->where('auth_code','promise')->where("status",1)->first();
        if(isset($promise)){
            $authInfo['is_promise']=true;
        }
        //.支付认证
        $alipay=AuthRecordModel::where("uid",Auth::user()->id)->where('auth_code','alipay')->where("status",2)->first();
        if(isset($alipay)){
            $authInfo['is_alipay']=true;
        }
        //未读消息
        $messageCount = MessageReceiveModel::where('js_id', Auth::user()->id)->where('status', 0)->count();

        $this->theme->set("userColumnLeft","userIndex");;
        //我的发包进度
        $fbTask=WorkModel::leftJoin('task',"work.task_id","=","task.id")->where("task.uid",Auth::user()->id)
                                  ->orderBy("work.id","desc")->select("task.id","task.status","task.type_id","task.title","work.created_at")->limit(3)->get();
        
        //我的接包进度
        $jbTask=WorkModel::leftJoin('task',"work.task_id","=","task.id")->where("work.uid",Auth::user()->id)
            ->orderBy("work.id","desc")->select("task.id","task.status","task.type_id","task.title","work.created_at")->limit(3)->get();
        //工具列表
        $toolList=ServiceModel::where("type",4)->where("status",1)->get();
        $data=[
            'userDetail'=>$userDetail,
            'couponCount'=>$couponCount,
            'authInfo'=>$authInfo,
            'newMessage'=>$messageCount,
            'fbTask'=>$fbTask,
            'jbTask'=>$jbTask,
            'toolList'=>$toolList,
            'biddingcardall' =>$res['biddingcardall'],
        ];
        return $this->theme->scope('user.index', $data)->render();
    }


    /**
     * 用户详细信息修改页面
     * @return mixed
     */
    public function info()
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","employer");
        $this->theme->setTitle('我的资料');
        $this->theme->set("employer",8);
        $this->theme->set("userOneColumn","我是雇主");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","我的资料");
        $this->theme->set("userSecondColumnUrl","/user/info");
        //创建用户的信息
        $uinfo = UserDetailModel::findByUid($this->user['id']);
        //查询省信息
        $province = DistrictModel::findTree(0);
        //查询城市数据
        if (!is_null($uinfo['province'])) {
            $city = DistrictModel::findTree($uinfo['province']);
        } else {
            $city = DistrictModel::findTree($province[0]['id']);
        }
        //查询地区信息
        if (!is_null($uinfo['city'])) {
            $area = DistrictModel::findTree($uinfo['city']);
        } else {
            $area = DistrictModel::findTree($city[0]['id']);
        }
        //查找用户绑定手机号
        $user=UserModel::where('id', Auth::id())->first();
        //获取职能数据
        $functional=TaskCateModel::where("type",6)->get();
        //获取职位等级
        $jobLevel=TaskCateModel::where("type",7)->get();
        $view = array(
            'uinfo' => $uinfo,
            'province' => $province,
            'city' => $city,
            'area' => $area,
            'user' => $this->user,
            'mobile'=>$user['mobile'],
            'functional'=>$functional,
            'jobLevel' =>$jobLevel
        );
        return $this->theme->scope('user.info', $view)->render();
    }

    /**
     * 用户信息更新，在第一次的时候创建
     * @param UserInfoRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function infoUpdate(UserInfoRequest $request)
    {
        $data = $request->except('_token','name','email');
        //查询用户详情是否存在
        $findUserDetail=UserDetailModel::where("uid",$this->user['id'])->first();
        if($findUserDetail){
            $result = UserDetailModel::where('uid', $this->user['id'])->update($data);
        }else{
            $data['uid']=$this->user['id'];
            $result =UserDetailModel::create($data);
        }

//        UserModel::where("id",$this->user['id'])->update([
//            'name'=>$data['nickname'],
//        ]);
        if (!$result) {
            return redirect()->back()->with(['error' => '修改失败！']);
        }

        return redirect()->back()->with(['massage' => '修改成功！']);
    }
    /*
     * 账号绑定
     * */
    public function bindAccout(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userInfo");
        $this->theme->setTitle('账号绑定');
        $this->theme->set("userInfo",8);
        $this->theme->set("userOneColumn","账号设置");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","账号绑定");
        $this->theme->set("userSecondColumnUrl","/user/bindAccout");
		$oauthConfig = ConfigModel::getConfigByType('oauth');
		$data = array(
            'oauth' => $oauthConfig,
        );
        return $this->theme->scope('user.bindAccout',$data)->render();
    }
    /**
     * 用户修改密码页
     * @return mixed
     */
    public function loginPassword()
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userInfo");
        $this->theme->setTitle('登录密码修改');
        $this->theme->set("userInfo",6);
        $this->theme->set("userOneColumn","账号设置");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","登录密码修改");
        $this->theme->set("userSecondColumnUrl","/user/loginPassword");
        $view = [
            'user' => $this->user,
        ];
        return $this->theme->scope('user.userpassword', $view)->render();
    }

    /**
     * 用户修改密码
     * @param PasswordRequest $request
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function passwordUpdate(PasswordRequest $request)
    {
        //验证用户的密码
        $data = $request->except('_token');
        if($data['password'] != $data['confirmPassword']){
            return redirect()->back()->with('error', '新密码前后不一致！');
        }
        $user = UserModel::find($this->user['id']);
        if ($user) {
            $password = UserModel::encryptPassword($data['oldpassword'], $user->salt);
            if ($user->password !== $password) {
                return redirect()->back()->with('error', '原始密码错误！');
            }
        }else{
            return redirect()->back()->with('error', '原始密码错误！');
        }
//        //验证原密码是否正确
//        if (!UserModel::checkPassword($this->user['email'], $data['oldpassword'])) {
//
//        }
        $result = UserModel::psChange($data, $this->user);

        if (!$result) {
            return redirect()->back()->with('error' . '密码修改失败！');  //回传错误信息
        }
        Auth::logout();
        return redirect('login')->with(['message' => '修改密码成功，请重新登录']);
    }

    /**
     * 用户修改支付密码
     * @return mixed
     */
    public function payPassword()
    {
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userInfo");
        $this->theme->setTitle('修改支付密码');
        $this->theme->set("userInfo",7);
        $this->theme->set("userOneColumn","账号设置");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","修改支付密码");
        $this->theme->set("userSecondColumnUrl","/user/payPassword");
        UserDetailModel::closeTips();

        $view = [
            'user' => $this->user,
        ];
        return $this->theme->scope('user.paypassword', $view)->render();
    }
    /*
     * 修改支付密码页面
     * */
    public function payPasswordEdit(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userInfo");
        $this->theme->setTitle('修改支付密码');
        $this->theme->set("userInfo",7);
        $this->theme->set("userOneColumn","账号设置");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","修改支付密码");
        $this->theme->set("userSecondColumnUrl","/user/payPasswordEdit");
        $view = [

        ];
        return $this->theme->scope('user.payPasswordEdit', $view)->render();
    }
    /*
     * 获取支付的验证码
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
            Session::put('pay_mobile_info', $data);
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
     * 修改支付密码
     * */
    public function payPasswordEditPost(Request $request){

        $data = $request->all();
        $taskMobileInfo = session('pay_mobile_info');
        if ($data['code'] == $taskMobileInfo['code'] && $data['mobile'] == $taskMobileInfo['mobile']) {
            Session::forget('pay_mobile_info');
                //查询用户是否存在
//               $user = UserModel::where('id',Auth::user()->id)->first();
//               $payWord=123456;
//                UserModel::where("id",Auth::user()->id)->update(
//                    ["alternate_password"=>UserModel::encryptPassword($payWord,$user['salt'])]
//                );
//                //发送通知短信
//                $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
//                $templateId = ConfigModel::phpSmsConfig('sendUserPassword');
//                $templates = [
//                    $scheme => $templateId,
//                ];
//                $tempData = [
//                    'username' => $data['mobile'],
//                    'password' => $payWord,
//                    'website' => $this->theme->get('site_config')['site_url']
//                ];
//                \SmsClass::sendSms($data['mobile'], $templates, $tempData);
//                return $data;
                //});
          //  }
           // Auth::loginUsingId($uid);
            return redirect("/user/updatePay");
        }else{
            return back()->with(["message"=>"手机验证码错误"]);
        }
    }
    /*
     * 修改支付密码
     * */
    public function updatePay(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userInfo");
        $this->theme->setTitle('修改支付密码');
        $this->theme->set("userInfo",7);
        $this->theme->set("userOneColumn","账号设置");
        $this->theme->set("userOneColumnUrl","/user/info");
        $this->theme->set("userSecondColumn","修改支付密码");
        $this->theme->set("userSecondColumnUrl","/user/payPassword");
        return $this->theme->scope('user.updatePay')->render();
    }
    /**
     * 检测发送邮件倒计时时间(修改支付密码)
     */
    public function checkInterVal(){
        $sendTime = Session::get('send_code_time');
        $nowTime = time();
        if(empty($sendTime)){
            return response()->json(['errCode'=>3]);
        }else{
            if($nowTime - $sendTime < 60 ){//时间在0-60
                return response()->json(['errCode'=>1,'interValTime'=>60-($nowTime - $sendTime)]);
            }else{
                return response()->json(['errCode'=>2]);//大于60
            }
        }
    }

    /**
     * 用户修改密码发送邮件
     */
    public function sendEmail(Request $request)
    {
        $email = $request->get('email');
        //验证用户填写邮箱
        if ($email != $this->user['email']) {
            return response()->json(['errCode' => 0, 'errMsg' => '请输入注册时候填写的邮箱！']);
        }
        $result = \MessagesClass::sendCodeEmail($this->user);

        if (!$result) {
            return response()->json(['errCode' => 0, 'errMsg' => $result]);
        } else {
            Session::put('send_code_time', time());
            return response()->json(['errCode' => 1]);
        }
    }

    /**
     * 验证用户输入邮箱是否注册邮箱
     * @param Request $request
     * @return mixed
     */
    public function checkEmail(Request $request)
    {
        $sendTime = Session::get('send_code_time');
        $nowTime = time();
        if ($nowTime - $sendTime < 60) {
            return response()->json(['errCode' => 0, 'errMsg' => '请稍后点击发送验证码！']);
        }
        $email = $request->get('email');
        //验证用户填写邮箱
        if ($email != $this->user['email']) {
            return response()->json(['errCode' => 0, 'errMsg' => '请输入注册时候填写的邮箱！']);
        } else {
            return response()->json(['errCode' => 1]);
        }
    }

    /**
     * 验证用户的验证码跳转修改密码页面
     */
    public function validateCode(Request $request)
    {
        $this->initTheme('userinfo');
        $this->theme->setTitle('修改支付密码');
        //验证验证码
        $code = $request->get('code');
        $email = $request->get('email');
        $session_code = Session::get('payPasswordCode');
        if ($code != $session_code) {
            return redirect()->to('user/payPassword')->withInput(['email' => $email, 'code' => $code])->withErrors(['code' => '验证码错误']);
        }

        return $this->theme->scope('user.paypasswordupdate')->render();
    }

    /**
     * 用户修改支付密码提交
     * @param PasswordRequest $request
     * @return $this|\Illuminate\Http\RedirectResponse
     */
    public function payPasswordUpdate(PasswordRequest $request)
    {
        $data = $request->except('_token');
        if($data['password'] != $data['confirmPassword']){
            return redirect()->back()->with('error', '新密码前后不一致！');
        }
        if($request->get("oldpassword")){
            //验证原密码是否正确
            if (!UserModel::checkPayPassword($this->user['id'], $data['oldpassword'])) {
                return redirect()->back()->with('error', '原始密码错误！');
            }
        }
        $result = UserModel::payPsUpdate($data, $this->user);

        if (!$result) {
            return redirect()->back()->with('error', '密码修改失败！');  //回传错误信息
        }

        return redirect()->to('user/payPassword')->with('message', '密码修改成功！');
    }

    /**
     * 标签修改页面
     * @return mixed
     */
    public function skill()
    {
        $this->initTheme('userinfo');
        $this->theme->setTitle('标签设置');
        //查询用户原有的标签id
        $tag = UserTagsModel::myTag($this->user['id']);
        $tags = array_flatten($tag);
        //查询所有标签
        $hotTag = TagsModel::findAllSkill();

        $view = array(
            'hotTag' => $hotTag,
            'tags' => $tags,
            'user' => $this->user,
        );
        return $this->theme->scope('user.sign', $view)->render();
    }

    /**
     * 用户设置标签一次性添加
     * @param Request $request
     */
    public function skillSave(Request $request)
    {
        $data = $request->all();

        $tags = explode(',', $data['tags']);
        //查询用户所有的标签id
        $old_tags = UserTagsModel::myTag($this->user['id']);
        $old_tags = array_flatten($old_tags);
        //验证用户更改了标签
        if ((empty($data['tags']) && $data['tags'] != 'change')) {
            return redirect()->back()->withErrors(['tags_name' => '请更新标签后提交！']);
        }

        //判断是在添加标签还是在删除标签
        if (count($tags) > count($old_tags)) {
            //判断用户有多少个标签
            if (count($tags) > 5) {
                return redirect()->back()->withErrors(['tags_name' => '一个用户最多只能有五个标签']);
            }
            $dif_tags = array_diff($tags, $old_tags);
            $result = UserTagsModel::insert($dif_tags, $this->user['id']);
            if (!$result)
                return redirect()->back()->with('error', '更新标签错误');  //回传错误信息
        } else if (count($tags) < count($old_tags)) {
            $dif_tags = array_diff($old_tags, $tags);
            $result = UserTagsModel::tagDelete($dif_tags, $this->user['id']);
            if (!$result)
                return redirect()->back()->with('error', '更新标签错误');  //回传错误信息
        } else if (count($tags) == count($old_tags)) {
            //增加新标签
            $dif_tags = array_diff($tags, $old_tags);
            if(empty($dif_tags))
            {
                return redirect()->back()->withErrors(['tags_name' => '请更新标签后提交！']);
            }
            $result2 = UserTagsModel::insert($dif_tags, $this->user['id']);
            //删除老标签
            $dif_tags = array_diff($old_tags, $tags);
            $result = UserTagsModel::tagDelete($dif_tags, $this->user['id']);
            if (!$result && !$result2)
                return redirect()->back()->with('error', '更新标签错误');  //回传错误信息
        }

        return redirect()->back()->with('massage', '标签更新成功');
    }

    /**
     * 用户头像设置页
     * @return mixed
     */
    public function userAvatar()
    {
        $theme = Theme::uses('default')->layout('usercenter');
        $theme->setTitle('头像设置');
        //查询用户的头像信息
        $user_detail = UserDetailModel::findByUid($this->user['id']);

        $view = [
            'avatar' => $user_detail['avatar'],
            'id' => $this->user['id']
        ];

        return $this->theme->scope('user.avatar', $view)->render();
    }

    /**
     * ajax头像裁剪
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function avatarEdit(Request $request)
    {
        $data = $request->except('_token');
        $data = $data['data'];
        //查询用户头像路径
        $user_head = UserDetailModel::findByUid($this->user['id']);
        $path = $user_head['avatar'] . md5($this->user['id'] . 'large') . '.jpg';
        $img = Image::open($path);
        $img->crop(intval($data['x']), intval($data['y']), intval($data['width']), intval($data['height']));
        $result = $img->save($path);
        $domain = \CommonClass::getDomain();
        $json = [
            'status' => 1,
            'message' => '成功保存',
            'url' => $path,
            'path' => $domain . '\\' . $path
        ];
        //生成三张图片
        $result2 = \FileClass::headHandle($json, $this->user['id']);

        if (!$result || !$result2) {
            array_replace($json, ['status' => 0, 'message' => '编辑失败']);
        }
        return response()->json($json);
    }

    /**
     * ajax头像上传
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxAvatar(Request $request)
    {
        $file = $request->file('avatar');
        //处理上传图片
        $result = \FileClass::uploadFile($file, $path = 'user');
        $result = json_decode($result, true);

        //判断文件是否上传
        if ($result['code'] != 200) {
            return response()->json(['code' => 0, 'message' => $result['message']]);
        }
        //产生一条新纪录
        $attachment_data = array_add($result['data'], 'status', 1);
        $attachment_data['created_at'] = date('Y-m-d H:i:s', time());
        //将记录写入到attchement表中
        $result2 = AttachmentModel::create($attachment_data);
        if (!$result2)
            return response()->json(['code' => 0, 'message' => $result['message']]);

        //删除原来的头像
        $avatar = \CommonClass::getAvatar($this->user['id']);
        if (file_exists($avatar)) {
            $file_delete = unlink($avatar);
            if ($file_delete) {
                AttachmentModel::where('url', $avatar)->delete();
            } else {
                AttachmentModel::where('url', $avatar)->update(['status' => 0]);
            }
        }
        //修改用户头像
        $data = [
            'avatar' => $result['data']['url']
        ];
        $result3 = UserDetailModel::updateData($data, $this->user['id']);
        if (!$result3) {
            return \CommonClass::formatResponse('文件上传失败');
        }

        return response()->json($result);
    }

    /**
     * ajax获取城市、地区数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxCity(Request $request)
    {
        $id = intval($request->get('id'));
        if (!$id) {
            return response()->json(['errMsg' => '参数错误！']);
        }
        $province = DistrictModel::findTree($id);
        //查询第一个市的数据
        $area = DistrictModel::findTree($province[0]['id']);
        $data = [
            'province' => $province,
            'area' => $area
        ];
        return response()->json($data);
    }

    /**
     * ajax获取地区的数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxArea(Request $request)
    {
        $id = intval($request->get('id'));
        if (!$id) {
            return response()->json(['errMsg' => '参数错误！']);
        }
        $area = DistrictModel::findTree($id);
        return response()->json($area);
    }



    /**
     * 滑块请求图片生成
     */
    public function StartCaptchaServlet()
    {
        $res = [
            'id' => 'e20876c0cecee2f36887c48eaf85639d',
            'key' => '28f1e7dcd36e1af44273146ea8a19605'
        ];
        $GtSdk = $this->GtSdk = new \GeetestLib($res['id'], $res['key']);
        session_start();
        $data = array(
            "user_id" => uniqid(), # 网站用户id
            "client_type" => "web", #web:电脑上的浏览器；h5:手机上的浏览器，包括移动应用内完全内置的web_view；native：通过原生SDK植入APP应用的方式
            "ip_address" => $_SERVER["SERVER_ADDR"] # 请在此处传输用户请求验证时所携带的IP
        );
        $status = $GtSdk->pre_process($data, 1);
        $_SESSION['gtserver'] = $status;
        $_SESSION['user_id'] = $data['user_id'];
        echo $GtSdk->get_response_str();
    }
    /*
     * 我是需求方我买的方案
     * */
    public function payProgramme(Request $request){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","employer");
        $this->theme->setTitle("方案购买");
        $this->theme->set("employer","2");
        $this->theme->set("userOneColumn","我是雇主");
        $this->theme->set("userOneColumnUrl","/user/releaseTask");
        $this->theme->set("userSecondColumn","方案购买");
        $this->theme->set("userSecondColumnUrl","/user/payProgramme");
        //搜索条件
        $merge=[
            'title'=>$request->get("title"),
            'status'=>$request->get("status"),
        ];
        $programmeOrder=ProgrammeOrderModel::leftJoin("goods","programme_order.programme_id","=","goods.id")
            ->where("programme_order.uid",Auth::user()->id);
        if($request->get("status")){//搜索状态
            $programmeOrder=$programmeOrder->where("programme_order.status",$request->get("status"));
        }
        //关键字搜索
        if($request->get("title")){
            $programmeOrder=$programmeOrder->where("goods.title","like","%".$request->get("title")."%");
        }
        $programmeOrder=$programmeOrder->orderBy("programme_order.id","desc")->select("goods.title","programme_order.*")->paginate(10);
        $data=[
            'programmeOrder'=>$programmeOrder,
            'merge'=>$merge
        ];
        return $this->theme->scope('user.payProgramme', $data)->render();
    }
    //我去付款
    public function payProgrammePay($id){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","employer");
        $this->theme->set("employer","2");
        $this->theme->setTitle("方案购买");
        $this->theme->set("userOneColumn","我是雇主");
        $this->theme->set("userOneColumnUrl","/user/releaseTask");
        $this->theme->set("userSecondColumn","方案购买");
        $this->theme->set("userSecondColumnUrl","/user/payProgramme");
        $userBalance=UserDetailModel::where('uid',Auth::User()->id)->pluck("balance");
        $programmeOrder=ProgrammeOrderModel::find($id);
//        $programmeOrder=ProgrammeOrderModel::whereIn("id",$goodAllID)->get();
//        foreach ($programmeOrder as $key=>$val){
            $totalPrice=$programmeOrder['number']*$programmeOrder['price']+$programmeOrder['freight'];
//        }
        //查询用户绑定的银行卡信息
        $bank = BankAuthModel::where('uid', '=', Auth::User()->id)->where('status', '=', 4)->get();
        //判断第三方支付是否开启
        $payConfig = ConfigModel::getConfigByType('thirdpay');
        $balance_pay = false;
        if ($userBalance >= $totalPrice) {
            $balance_pay = true;
        }
        $data=[
            'userBalance'=>$userBalance,
            'totalPrice'=>$totalPrice,
            'balance_pay'   => $balance_pay,
            'bank'          => $bank,
            'payConfig'     => $payConfig,
            'id'=>json_encode([$id])
        ];
        return $this->theme->scope('user.payProgrammePay', $data)->render();
    }
    //订单其他的处理
    public function payProgrammeHandle($id,$action,Request $request){
        $programmeOrder = ProgrammeOrderModel::find($id);
        if(!$programmeOrder){
            return back()->with(["message"=>"该订单不存在"]);
        }
        switch ($action){
            case "cancel"://取消订单
                $res = $programmeOrder->delete();
                break;
            case "delivery"://收货
               /* $res = $programmeOrder->update(['status'=>4,"confirm_at"=>date("Y-m-d H:i:s")]);*/
                //新流程收货流程处理
                $res=DB::transaction(function()use($programmeOrder){
                    $programmeOrder->update([
                        'status'        => 5,
                        "confirm_at"    => date("Y-m-d H:i:s"),
                        'complete_at'   => date("Y-m-d H:i:s")
                    ]);
                    //查询支付方式
                    $pay_type=FinancialModel::where("related_id",$programmeOrder['id'])->where("uid",$programmeOrder['uid'])->pluck("pay_type");
                    //查询用户信息
                    $ownedUsers=GoodsModel::where("id",$programmeOrder['programme_id'])->select("uid","title")->first();
                    $ownedUsersInfo=UserDetailModel::LeftJoin('users',"user_detail.uid","=","users.id")->where("user_detail.uid",$ownedUsers['uid'])->select("user_detail.balance","users.*")->first();
                    //给用户生成记录
                    FinancialModel::createOne(
                        ["action"=>2,"pay_type"=>$pay_type,"cash"=>$programmeOrder['price']*$programmeOrder['number'] + $programmeOrder['freight'],
                            "uid"=>$ownedUsers['uid'],"created_at"=>date("Y-m-d H:i:s"),"related_id"=>$programmeOrder['id'],'status' =>1,'remainder'=>$ownedUsersInfo['balance']+$programmeOrder['price'] + $programmeOrder['freight']]
                    );
                    //给用户添加金额
                    UserDetailModel::where("uid",$ownedUsers['uid'])->increment("balance",$programmeOrder['price']*$programmeOrder['number'] + $programmeOrder['freight']);
                    return $programmeOrder;
                });
                break;
            case "rights"://维权
                $desc = $request->get('rights_desc') ? $request->get('rights_desc') : '';
                $res=ProgrammeOrderModel::orderRights($id,$programmeOrder,$desc);
                break;
        }
        if($res){
            return back()->with(["message"=>"操作成功"]);
        }
           return back()->with(["message"=>"操作失败"]);
    }
    //雇主方案咨询
    public function employerConsult(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","employer");
        $this->theme->setTitle('方案询价');
        $this->theme->set("employer","13");
        $this->theme->set("userOneColumn","我是雇主");
        $this->theme->set("userOneColumnUrl","/user/releaseTask");
        $this->theme->set("userSecondColumn","方案询价");
        $this->theme->set("userSecondColumnUrl","/user/employerConsult");
        $programmeEnquiry=ProgrammeEnquiryMessageModel::leftJoin("goods","programme_enquiry_message.programme_id","=","goods.id")
            ->leftJoin("user_detail","goods.uid","=","user_detail.uid")
            ->leftJoin("users","goods.uid","=","users.id")
            ->where("programme_enquiry_message.uid",Auth::user()->id)->where("programme_enquiry_message.type",1)->select("goods.title","user_detail.mobile","users.name","programme_enquiry_message.*")
            ->orderBy("programme_enquiry_message.id","desc")->paginate(10);
        foreach ($programmeEnquiry as $key => $value) {
            $programmeInquiry=ProgrammeInquiryPayModel::where("uid",$value['uid'])->where("status",2)->where("programme_id",$value['programme_id'])->whereIn("type",[1,3])->first();
            $programmeEnquiry[$key]['ispay'] = "0";
            if(!$programmeInquiry){
                $programmeEnquiry[$key]['name'] = "***";
                $programmeEnquiry[$key]['mobile'] = "******";
                $programmeEnquiry[$key]['ispay'] = "1";
            }
            
        }

        //获取服务商付款查看的记录
        $serviceSee=ProgrammeInquiryPayModel::where("uid",Auth::user()->id)->where("status",2)
            ->whereIn("type",[2,3])->lists("programme_id")->toArray();
        $data=[
            'programmeEnquiry'=>$programmeEnquiry,
            'serviceSee'=>$serviceSee
        ];
        return $this->theme->scope('user.employer.employerConsult', $data)->render();
    }
    //雇主方案留言
    public function employerLeavMessage(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","employer");
        $this->theme->setTitle('方案留言');
        $this->theme->set("employer","14");
        $this->theme->set("userOneColumn","我是雇主");
        $this->theme->set("userOneColumnUrl","/user/releaseTask");
        $this->theme->set("userSecondColumn","方案留言");
        $this->theme->set("userSecondColumnUrl","/user/employerLeavMessage");
        $programmeEnquiry=ProgrammeEnquiryMessageModel::leftJoin("goods","programme_enquiry_message.programme_id","=","goods.id")
            ->where("programme_enquiry_message.uid",Auth::user()->id)->where("programme_enquiry_message.type",2)->select("goods.title","programme_enquiry_message.*")
            ->orderBy("programme_enquiry_message.id","desc")->paginate(10);
        //获取服务商付款查看的记录
        $serviceSee=ProgrammeInquiryPayModel::where("uid",Auth::user()->id)->where("status",2)
            ->whereIn("type",[2,3])->lists("programme_id")->toArray();
        $data=[
            'programmeEnquiry'=>$programmeEnquiry,
            'serviceSee' =>$serviceSee,
        ];
        return $this->theme->scope('user.employer.employerLeavMessage', $data)->render();
    }
    //我的信誉评价
    public function emploEvaluaton(Request $request){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","employer");
        $this->theme->setTitle("信誉评价");
        $this->theme->set("employer","3");
        $this->theme->set("userOneColumn","我是雇主");
        $this->theme->set("userOneColumnUrl","/user/releaseTask");
        $this->theme->set("userSecondColumn","信誉评价");
        $this->theme->set("userSecondColumnUrl","/user/emploEvaluaton");
        $merge=[
            "from"=>$request->get("from")?$request->get("from"):1,
            "type"=>$request->get("type")?$request->get("type"):0

        ];
        //获取所有的任务id
        $taskId=TaskModel::where("uid",Auth::user()->id)->lists("id")->toArray();
        //获取所有的评价个数
        $commentCount=CommentModel::whereIn("task_id",$taskId)
             ->where(function($query){
                 $query->where("from_uid",Auth::user()->id)->orWhere("to_uid",Auth::user()->id);
             })->count();
        //获取所有的好评
        $commentGoodCount=CommentModel::where("type",1)->whereIn("task_id",$taskId)->where(function($query){
              $query->where("from_uid",Auth::user()->id)
                   ->orWhere("to_uid",Auth::user()->id);
        })->count();
        $list=CommentModel::leftJoin("task","comments.task_id","=","task.id")->whereIn("comments.task_id",$taskId);
        if($request->get("from") && $request->get("from") ==2){//服务商评价类型
            $list=$list->where("comments.to_uid",Auth::user()->id);
        }else{
            $list=$list->where("comments.from_uid",Auth::user()->id);
        }
        if($request->get("type")){
            $list=$list->where("comments.type",$request->get("type"));
        }
           $list=$list->select("task.title","task.type_id","comments.*")->orderBy("comments.id","desc")->paginate(10);
        $data=[
            'commentCount'=>$commentCount,
            'commentGoodCount'=>$commentGoodCount,
            'merge'=>$merge,
            'list'=>$list,
        ];
        //dd($list);
        return $this->theme->scope('user.evaluaton', $data)->render();
    }


    /*
     * 我是服务商
     * */
    //卖出方案
    public function serviceSellingPlan(Request $request){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle("方案出售");
        $this->theme->set("userShop","11");
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/myjointask");
        $this->theme->set("userSecondColumn","方案出售");
        $this->theme->set("userSecondColumnUrl","/user/serviceSellingPlan");
        //搜索条件
        $merge=[
           'title'=>$request->get("title"),
           'status'=>$request->get("status"),
        ];
        $programmeOrder=ProgrammeOrderModel::leftJoin("goods","programme_order.programme_id","=","goods.id")
                      ->where("goods.uid",Auth::user()->id);
        if($request->get("status")){//搜索状态
            $programmeOrder=$programmeOrder->where("programme_order.status",$request->get("status"));
        }else{
            $programmeOrder=$programmeOrder->where("programme_order.status",'>=',1);
        }
        //关键字搜索
        if($request->get("title")){
            $programmeOrder=$programmeOrder->where("goods.title","like","%".$request->get("title")."%");
        }
        $programmeOrder=$programmeOrder->orderBy("programme_order.id","desc")->select("goods.title","programme_order.*")->paginate(10);
        $data=[
            'programmeOrder'=>$programmeOrder,
            'merge'=>$merge
        ];
        return $this->theme->scope('user.service.serviceSellingPlan', $data)->render();
    }


    /**
     * 方案订单详情
     * @param $id
     * @param $index
     * @return mixed
     */
    public function serviceSellingPlanDetail($id,$index){
        $this->initTheme('personalindex');
        //方案详情
        $programmeOrder = ProgrammeOrderModel::programmeOrderInfo($id);
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->set("userShop",$index);
        if($programmeOrder['uid'] == Auth::id()){
            $userType = 1;
        }else{
            $userType = 2;
        }
        $data= [
            'programmeOrder' => $programmeOrder,
            'userType' => $userType
        ];
        return $this->theme->scope('user.service.serviceSellingPlanDetail', $data)->render();
    }
    //方案处理
    public function servicePlanHandle($id,$action,Request $request)
    {
        $programme = ProgrammeOrderModel::leftJoin("goods","programme_order.programme_id","=","goods.id")
                      ->where("goods.uid",Auth::user()->id)->where("programme_order.id",$id)->select("programme_order.*","goods.title")->first();
        if(!$programme){
            return back()->with(["message"=>"该方案不存在"]);
        }
        switch($action){
            case "send":
                $data['status']=3;
                $data['send_at']=date("Y-m-d H:i:s");
                $data['bill_no'] = $request->get('bill_no') ? $request->get('bill_no') : '';
                //发送短信通知
                //获取购买方案的用户信息
                $userBuyInfo=UserModel::find($programme['uid']);
                $user = [
                       'uid'    =>$userBuyInfo['id'],
                       'email'  =>$userBuyInfo['email'],
                       'mobile' =>$userBuyInfo['mobile']
                   ];
                   $templateArr = [
                       'username' =>$userBuyInfo['name'],
                       'title'     =>$programme['title'],
                   ];
                   \MessageTemplateClass::sendMessage("employer_goods_buy",$user,$templateArr,$templateArr);
                break;
            case "complete":
                $data['status']=5;
                $data['complete_at']=date("Y-m-d H:i:s");
                break;
            case "rights"://维权
                $desc = $request->get('rights_desc') ? $request->get('rights_desc') : '';
                $res=ProgrammeOrderModel::orderRights($id,$programme,$desc);
                break;
        }
        if(isset($data)){
            $res = ProgrammeOrderModel::where("id",$id)->update($data);
        }
        if($res){
            return back()->with(["message"=>"操作成功"]);
        }
            return back()->with(["message"=>"操作失败"]);

    }
    //方案咨询
    public function serviceConsult(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle("方案咨询");
        $this->theme->set("userShop","12");
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/myjointask");
        $this->theme->set("userSecondColumn","方案咨询");
        $this->theme->set("userSecondColumnUrl","/user/serviceConsult");
        // $programmeEnquiry=ProgrammeEnquiryMessageModel::leftJoin("goods","programme_enquiry_message.programme_id","=","goods.id")
        //                   ->where("goods.uid",Auth::user()->id)->where("programme_enquiry_message.type",1)->where("programme_enquiry_message.consult_type",1)->select("goods.title","programme_enquiry_message.*")
        //                   ->orderBy("programme_enquiry_message.id","desc")->paginate(10);
        $programmeEnquiry=ProgrammeEnquiryMessageModel::leftJoin("goods","programme_enquiry_message.programme_id","=","goods.id")
                          ->leftJoin("users","users.id","=","programme_enquiry_message.uid")
                          ->where("programme_enquiry_message.consultant_id",Auth::user()->id)->where("programme_enquiry_message.type",1)->select("goods.title","users.mobile","users.name","programme_enquiry_message.*")
                          ->orderBy("programme_enquiry_message.id","desc")->paginate(10);
        // dd($programmeEnquiry);
        //获取服务商付款查看的记录
        $serviceSee=ProgrammeInquiryPayModel::where("uid",Auth::user()->id)->where("status",2)
                      ->whereIn("type",[2,3])->lists("programme_id")->toArray();
        $data=[
            'programmeEnquiry'=>$programmeEnquiry,
            'serviceSee'=>$serviceSee
        ];
        return $this->theme->scope('user.service.serviceConsult', $data)->render();
    }
    //咨询服务
    public function serviceConsultPay($id){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle("方案咨询");
        $this->theme->set("userShop","12");
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/myjointask");
        $this->theme->set("userSecondColumn","方案咨询");
        $userDetial=UserDetailModel::where("uid",Auth::user()->id)->first();
        $money=ConfigModel::where('alias','inquiry_price')->where('type','site')->pluck("rule");
        $bank = BankAuthModel::where('uid', '=', Auth::user()->id)->where('status', '=', 4)->get();
        //判断第三方支付是否开启
        $payConfig = ConfigModel::getConfigByType('thirdpay');
        $balance_pay = false;
        if ($userDetial['balance'] >= $money) {
            $balance_pay = true;
        }
        //获取会员名称
        $member_name="普通会员";
        if(Auth::user()->level > 1){
            $member_name=VipModel::where("grade",Auth::user()->level)->pluck("name");
        }
        //获取当月剩余的次数
        $dayNum=UserModel::userRoot(Auth::user()->id,"accept_inquiry_num");
        //根据金额获取对应的优惠券
        $userCoupon=UserCouponModel::getCoupon($money,[0,4]);
        $data=[
            'totalPrice'=>$money,
            'userDetail'=>$userDetial,
            'balance_pay'   => $balance_pay,
            'bank'          => $bank,
            'payConfig'     => $payConfig,
            'id'=>$id,
            'member_name'=>$member_name,
            'dayNum'     =>$dayNum,
            'userCoupon' =>$userCoupon,
            'soruce'=>1,//表示来自询价
        ];
        return $this->theme->scope('user.service.serviceConsultPay', $data)->render();
    }
    //方案留言
    public function serviceLeavMessage(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle("方案留言");
        $this->theme->set("userShop","13");
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/myjointask");
        $this->theme->set("userSecondColumn","方案留言");
        $this->theme->set("userSecondColumnUrl","/user/serviceLeavMessage");
        $programmeEnquiry=ProgrammeEnquiryMessageModel::leftJoin("goods","programme_enquiry_message.programme_id","=","goods.id")
            ->where("goods.uid",Auth::user()->id)->where("programme_enquiry_message.type",2)->select("goods.title","programme_enquiry_message.*")
            ->orderBy("programme_enquiry_message.id","desc")->paginate(10);
        //获取服务商付款查看的记录
        $serviceSee=ProgrammeInquiryPayModel::where("uid",Auth::user()->id)->where("status",2)
            ->whereIn("type",[2,3])->lists("programme_id")->toArray();
        $data=[
            'programmeEnquiry'=>$programmeEnquiry,
            'serviceSee' =>$serviceSee,
        ];
        return $this->theme->scope('user.service.serviceLeavMessage', $data)->render();
    }
    //留言费用
    public function serviceLeavMessagePay($id){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle("方案留言");
        $this->theme->set("userShop","13");
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/myjointask");
        $this->theme->set("userSecondColumn","方案留言");
        $this->theme->set("userSecondColumnUrl","/user/serviceLeavMessage");
        $userDetial=UserDetailModel::where("uid",Auth::user()->id)->first();
        $money=ConfigModel::where('alias','inquiry_price')->where('type','site')->pluck("rule");
        $bank = BankAuthModel::where('uid', '=', Auth::user()->id)->where('status', '=', 4)->get();
        //判断第三方支付是否开启
        $payConfig = ConfigModel::getConfigByType('thirdpay');
        $balance_pay = false;
        if ($userDetial['balance'] >= $money) {
            $balance_pay = true;
        }
        //获取会员名称
        $member_name="普通会员";
        if(Auth::user()->level > 1){
            $member_name=VipModel::where("grade",Auth::user()->level)->pluck("name");
        }
        //获取当月剩余的次数
        $dayNum=UserModel::userRoot(Auth::user()->id);
        //根据金额获取对应的优惠券
        $userCoupon=UserCouponModel::getCoupon($money,[0,4]);
        $data=[
            'totalPrice'=>$money,
            'userDetail'=>$userDetial,
            'balance_pay'   => $balance_pay,
            'bank'          => $bank,
            'payConfig'     => $payConfig,
            'id'=>$id,
            'member_name'=>$member_name,
            'dayNum'     =>$dayNum,
            'userCoupon' =>$userCoupon,
            'soruce'=>2,//表示来自留言
        ];
        return $this->theme->scope('user.service.serviceConsultPay', $data)->render();
    }
    //信誉评价
    public function serverEvaluaton(Request $request){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userShop");
        $this->theme->setTitle("信誉评价");
        $this->theme->set("userShop","14");
        $this->theme->set("userOneColumn","我是服务商");
        $this->theme->set("userOneColumnUrl","/user/myjointask");
        $this->theme->set("userSecondColumn","信誉评价");
        $this->theme->set("userSecondColumnUrl","/user/serverEvaluaton");
        $merge=[
            "from"=>$request->get("from")?$request->get("from"):1,
            "type"=>$request->get("type")?$request->get("type"):0

        ];
        //获取所有我接到的任务id
        $taskId=WorkModel::where("uid",Auth::user()->id)->lists("task_id")->toArray();
        //获取所有的评价个数
        $commentCount=CommentModel::whereIn("task_id",$taskId)->where(function($query){
            $query->where("from_uid",Auth::user()->id)->orWhere("to_uid",Auth::user()->id);
        }) ->count();
        //获取所有的好评
        $commentGoodCount=CommentModel::where("type",1)->whereIn("task_id",$taskId)->where(function($query){
            $query->where("from_uid",Auth::user()->id)
                ->orWhere("to_uid",Auth::user()->id);
        })->count();
        $list=CommentModel::leftJoin("task","comments.task_id","=","task.id")->whereIn("comments.task_id",$taskId);
        if($request->get("from") && $request->get("from") ==2){//服务商评价类型
            $list=$list->where("comments.from_uid",Auth::user()->id);
        }else{
            $list=$list->where("comments.to_uid",Auth::user()->id);
        }
        if($request->get("type")){
            $list=$list->where("comments.type",$request->get("type"));
        }
        $list=$list->select("task.title","task.type_id","comments.*")->orderBy("comments.id","desc")->paginate(10);
		
        $data=[
            'commentCount'=>$commentCount,
            'commentGoodCount'=>$commentGoodCount,
            'merge'=>$merge,
            'list'=>$list,
        ];
        return $this->theme->scope('user.serverEvaluaton', $data)->render();
    }
    //我的保证金
    public function myDeposit(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userVip");
        $this->theme->setTitle("我的保证金");
        $this->theme->set("userVip","10");
        $this->theme->set("userOneColumn","增值服务");
        $this->theme->set("userOneColumnUrl","/user/myjointask");
        $this->theme->set("userSecondColumn","我的保证金");
        $this->theme->set("userSecondColumnUrl","/user/myDeposit");
        $userDetial=UserDetailModel::where("uid",Auth::user()->id)->first();
        //查询该用户是否缴纳保证
        $userDeposit=UserDepositModel::where("uid",Auth::user()->id)->where("status",2)->first();
        $data=[
            'userDetial'=>$userDetial,
            'userDeposit'=>$userDeposit
        ];
        return $this->theme->scope('user.myDeposit', $data)->render();
    }
    //交保证金
    public function marginDeposit(){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userVip");
        $this->theme->setTitle("我的保证金");
        $this->theme->set("userVip","10");
        $this->theme->set("userOneColumn","增值服务");
        $this->theme->set("userOneColumnUrl","/user/myjointask");
        $this->theme->set("userSecondColumn","我的保证金");
        $this->theme->set("userSecondColumnUrl","/user/myDeposit");
        $userDetial=UserDetailModel::where("uid",Auth::user()->id)->first();
        //查询该用户是否缴纳保证
        $userDeposit=UserDepositModel::where("uid",Auth::user()->id)->whereIn("type",[1,2,4])->where("status",2)->first();
        if($userDetial['deposit'] >0 || $userDeposit){
            return back()->with(["message"=>"您已缴纳保证金，无须重复缴纳"]);
        }
        $deposit=ConfigModel::where("type",'site')->where("alias","deposit")->first();
        $bank = BankAuthModel::where('uid', '=', Auth::user()->id)->where('status', '=', 4)->get();
        //判断第三方支付是否开启
        $payConfig = ConfigModel::getConfigByType('thirdpay');
        $balance_pay = false;
        if ($userDetial['balance'] >= $deposit['rule']) {
            $balance_pay = true;
        }
        $data=[
            'totalPrice'=>$deposit['rule'],
            'userDetail'=>$userDetial,
            'balance_pay'   => $balance_pay,
            'bank'          => $bank,
            'payConfig'     => $payConfig,
            'deposit'=>$deposit
        ];
        return $this->theme->scope('user.marginDeposit', $data)->render();
    }
    //申请退回保证金
    public function myDepositHandle(){
        $userDeposit=UserDepositModel::where("uid",Auth::user()->id)->whereIn("type",[1,2,4])->where("status",2)->first();
        if(!$userDeposit){
            return back()->with(["message"=>"尚未缴纳保证金"]);
        }
        //修改保证金状态
        UserDepositModel::where("uid",Auth::user()->id)->where("status",2)->whereIn("type",[1,2,4])->update(["type"=>2]);
        return back()->with(["message"=>"您的申请已提交，等待平台审核"]);
    }
    //我的收藏
    public function myCollect(Request $request){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userIndex");
        $this->theme->setTitle("我的收藏");
        
        $this->theme->set("userOneColumn","我的收藏");
        $this->theme->set("userOneColumnUrl","/user/myCollect");
        $this->theme->set("userSecondColumn","我的收藏");
        $this->theme->set("userSecondColumnUrl","/user/myCollect");
        if($request->get("type")){
            $type=$request->get("type");
        }else{
            $type=1;
        }
		switch($type){
		    case 1:
		        $this->theme->set("userIndex","19");
		        break;
		    case 2:
		        $this->theme->set("userIndex","20");
		        break;
		    case 3:
		        $this->theme->set("userIndex","21");
		        break;
		    case 4:
				$this->theme->set("userIndex","22");
		        break;
			default:
				$this->theme->set("userIndex","11");
				break;
		}
        $merge=[
            'type'=>$request->get("type")?$request->get("type"):1,
            'genre'=>$request->get("genre")?$request->get("genre"):0,
            'keywords'=>$request->get("keywords")?$request->get("keywords"):'',
        ];
        //获取地址，
        $district=DistrictModel::get()->toArray();
        $district=\CommonClass::setArrayKey($district,"id");
        switch($type){
            case 1:
                $list=TaskFocusModel::leftJoin("task","task_focus.task_id","=","task.id")
                                    ->where("task_focus.uid",Auth::user()->id);
                if($request->get("keywords")){
                    $list=$list->where("task.title","like","%".$request->get('keywords')."%");
                }
                $list =$list ->select("task.title","task.province","task.city","task.area","task.type_id as type","task_focus.*")->groupBy('task_id')->orderBy("task_focus.id","desc")->paginate(10);
//				foreach($list as $k=>$v){
//					$list[$k]['adder']=DistrictModel::where("id",$v['province'])->pluck("name") . DistrictModel::where("id",$v['city'])->pluck("name").DistrictModel::where("id",$v['area'])->pluck("name");
//				}
//                $genre=[
//                   1=>"竞标任务",
//                    2=>"快捷任务",
//                    3=>"雇佣任务",
//                ];
                break;
            case 2:
                $list= ShopFocusModel::leftJoin("shop","shop_focus.shop_id",'=',"shop.id");
                    if($request->get("keywords")){
                        $list=$list->where("shop.shop_name","like","%".$request->get('keywords')."%");
                    }
                    $list=$list->where("shop_focus.uid",Auth::user()->id)->select("shop.shop_name as title","shop.shop_pic as avatar","shop.shop_pic as province","shop.shop_pic as city","shop.shop_pic as area","shop.type","shop_focus.*")->groupBy('shop_id')->paginate(10);
//                if($request->get("genre") ==1 || !$request->get("genre")){
//                    $merge['genre']=1;
//                    $list= ShopFocusModel::leftJoin("shop","shop_focus.shop_id",'=',"shop.id");
//                    if($request->get("keywords")){
//                        $list=$list->where("shop.shop_name","like","%".$request->get('keywords')."%");
//                    }
//                    $list=$list->where("shop_focus.uid",Auth::user()->id)->select("shop.shop_name as title","shop.shop_pic as avatar","shop.shop_pic as province","shop.shop_pic as city","shop.shop_pic as area","shop.type","shop_focus.*")->groupBy('shop_id')->paginate(10);
//                }else{
                    /*$list = UserFocusModel::leftJoin("users","user_focus.focus_uid","=","users.id")->leftJoin("user_detail","users.id","=","user_detail.uid")->where("user_focus.uid",Auth::user()->id);
                    if($request->get("keywords")){
                        $list=$list->where("users.name","like","%".$request->get('keywords')."%");
                    }
                    $list =$list ->select("users.name as title","users.type","user_detail.avatar","user_detail.province","user_detail.city","user_detail.area","user_focus.*")->groupBy('focus_uid')->orderBy("user_focus.id","desc")->paginate(10);*/
              //  }
//              foreach($list as $k=>$v){
//					$list[$k]['adder']=DistrictModel::where("id",$v['province'])->pluck("name") . DistrictModel::where("id",$v['city'])->pluck("name").DistrictModel::where("id",$v['area'])->pluck("name");
//				}
//                $genre=[
//                    1=>"店铺",
//                    2=>"雇主"
//                ];
                break;
            case 3:
                $list=CollectionModel::leftJoin("goods","collection.collec_id","=","goods.id")->leftJoin("attachment","goods.cover","=","attachment.id")
                    ->where("collection.uid",Auth::user()->id)->where("collection.type",1);
                if($request->get("keywords")){
                    $list=$list->where("goods.title","like","%".$request->get('keywords')."%");
                }
//                if($request->get("genre")){
//                    $list=$list->where("goods.type",$request->get("genre"));
//                }
                $list=$list->select("goods.title","goods.type","attachment.url as avatar","collection.*")->orderBy("collection.id","desc")->paginate(10);
//                $genre=[
//                    1=>"方案销售",
//                    2=>"参考设计"
//                ];
                break;
            case 4:
                $list=CollectionModel::leftJoin("success_case","collection.collec_id","=","success_case.id")
                    ->where("collection.uid",Auth::user()->id)->where("collection.type",2);
                if($request->get("keywords")){
                    $list=$list->where("success_case.title","like","%".$request->get('keywords')."%");
                }
//                if($request->get("genre")){
//                    $list=$list->where("success_case.type",$request->get("genre"));
//                }else{
                   // $list=$list->where("success_case.type",0);
                //}
                $list=$list->select("success_case.title","success_case.type","success_case.url as avatar","collection.*")->orderBy("collection.id","desc")->paginate(10);
//                $genre=[
//                    0=>"方案超市",
//                    1=>"用户添加",
//                    2=>"任务推荐",
//                ];
                break;

        }
        $data=[
            'list'=>$list,
//            'genre'=>$genre,
            'merge'=>$merge,
            'district'=>$district
        ];

        return $this->theme->scope('user.myCollect', $data)->render();
    }
    //取消收藏
    public function myCollectHandle($id,$type){
         switch($type){
             case 1:
                 $res=TaskFocusModel::where("id",$id)->where("uid",Auth::user()->id)->delete();
                 break;
             case 2:
                 $res=UserFocusModel::where("id",$id)->where("uid",Auth::user()->id)->delete();
                 break;
             case 3:
                 $res=CollectionModel::where("id",$id)->where("uid",Auth::user()->id)->delete();
                 break;
             case 4:
                 break;
             case 5://店铺取消收藏
                 $res=ShopFocusModel::where("id",$id)->where("uid",Auth::user()->id)->delete();
                 break;
         }
        if($res){
            return back()->with(["message"=>"取消成功"]);
        }
            return back()->with(["message"=>"取消失败"]);
    }

    //我的推关
    public function myExtend(Request $request){
        $this->initTheme('personalindex');
        $this->theme->set("userColumnLeft","userIndex");
        $this->theme->setTitle("我的推广");
        $this->theme->set("userIndex","12");
        $this->theme->set("userOneColumn","我的推广");
        $this->theme->set("userOneColumnUrl","/user/myExtend");
        $this->theme->set("userSecondColumn","我的推广");
        $this->theme->set("userSecondColumnUrl","/user/myExtend");
        $domain=$_SERVER['SERVER_NAME'];
        $userId=Crypt::encrypt(Auth::user()->id);
        $url=$domain.'/register?uid='.$userId;
        //获取推广总金额
        $promoteAllCount=PromoteModel::where("from_uid",Auth::user()->id)->sum("price");
        //推广注册
        $promoteRegisterCount=PromoteModel::where("from_uid",Auth::user()->id)->where('type',1)->sum("price");
        //推广发包
        $promoteAwardCount=PromoteModel::where("from_uid",Auth::user()->id)->where('type',2)->sum("price");
        //推广接包
        $promotePackageCount=PromoteModel::where("from_uid",Auth::user()->id)->where('type',3)->sum("price");
        //获取推广记录
        $list=PromoteModel::leftJoin('users','promote.to_uid','=','users.id')->where("promote.from_uid",Auth::user()->id)->select("users.name","promote.*")->orderBy("promote.id","desc")->paginate(10);
        $data=[
            'url'                   => $url,
            'promoteAllCount'       => $promoteAllCount,
            'promoteRegisterCount'  => $promoteRegisterCount,
            'promoteAwardCount'     => $promoteAwardCount,
            'promotePackageCount'   => $promotePackageCount,
            'list'                  => $list
        ];
        return $this->theme->scope('user.myExtend', $data)->render();
    }
    //使用vip 特权
    public function useVip(Request $request,$id){
//        $goods=GoodsModel::find($id);
//        if(!$goods){
//            return back()->with(["message"=>"该方案不存在"]);
//        }
        $inquiryPrice=ConfigModel::where("alias","inquiry_price")->first();
        $programmeEnquiryMessage=ProgrammeEnquiryMessageModel::where("id",$id)->first();
        if($programmeEnquiryMessage){
            ProgrammeEnquiryMessageModel::where("id",$id)->update(["pay_type"=>2]);
        }else{
            //获取方案
            $programme = GoodsModel::where("id",$id)->first();
            //留言记录表存储
            ProgrammeEnquiryMessageModel::create([
                'programme_id'=>$id,
                'consult_type'=>2,
                'content'=>'',
                'uid'=>Auth::user()->id,
                'consultant_id'=>$programme['uid'],
                'created_at'=>date("Y-m-d H:i:s"),
                'pay_type'=>2
            ]);
        }
        //$res=DB::transaction(function()use($id,$goods,$inquiryPrice){
            //存储购买该方案存储表
          $res=ProgrammeInquiryPayModel::insert([
                'order_num'=>\CommonClass::createNum('fa',4),
                'programme_id'=>$id,
                'uid'  =>Auth::user()->id,
                //'consultant_id'=>$goods['uid'],
                'price'=>$inquiryPrice['rule'],
                'created_at'=>date("Y-m-d H:i:s"),
                'payment_at'=>date("Y-m-d H:i:s"),
                'status'=>2,
                'type'=>3,
            ]);
        if($request->get("soruce")){
            if($request->get("soruce") ==1){
                return redirect("/user/serviceConsult")->with(["message"=>"使用成功"]);
            }
            return redirect("/user/serviceLeavMessage")->with(["message"=>"使用成功"]);
        }
        return back()->with(["message"=>"使用成功"]);
        //});
    }

    //登录 ->用户登录跳转
    /*
     *
     * */
    public function collect(Request $request,$type){
         switch($type){
             case 1://固定栏上传方案 或者首页上传方案 或者上传方案
                 return redirect('/shop/pubGoods');
                 break;
             case 2://首页发布需求
                 return redirect("/kb/create");
                 break;
             case 3://方案详情-》购买和加入购物车
                 if($request->get('_id')){
                    return redirect("/facs/".$request->get('_id'));
                 }else{
                    return redirect("/facs");
                 }
                 break;
             case 4://方案详情-》询价
                 if($request->get('_id')){
                     return redirect("/shop/inquiry/".$request->get('_id'));
                 }else{
                     return redirect("/facs");
                 }
                 break;
             case 5://方案详情-》留言
                 if($request->get('_id')){
                     return redirect("/shop/leavMessage/".$request->get('_id'));
                 }else{
                     return redirect("/facs");
                 }
                 break;
             case 6://快包任务详情-》立即申请
                 if($request->get('_id')){
                     return redirect("/kb/".$request->get('_id'));
                 }else{
                     return redirect("/kb");
                 }
                 break;
             case 7://找服务商详情-》收藏店铺||雇佣TA
                 if($request->get('_id')){
                     return redirect("/fuwus/".$request->get('_id'));
                 }else{
                     return redirect("/fuwus");
                 }
                 break;
             case 8://找服务商详情-》服务商档案-》雇佣TA
                 if($request->get('_id')){
                     return redirect("/fuwus/info/".$request->get('_id'));
                 }else{
                     return redirect("/fuwus");
                 }
                 break;
             case 9://找服务商详情-》相关咨询-》雇佣TA
                 if($request->get('_id')){
                     return redirect("/fuwus/article/".$request->get('_id'));
                 }else{
                     return redirect("/fuwus");
                 }
                 break;
             case 10://找服务商详情-》相关咨询-》雇佣TA
                 if($request->get('_id')){
                     return redirect("/anli/".$request->get('_id'));
                 }else{
                     return redirect("/anli");
                 }
                 break;
             case 11://方案讯
                     return redirect("/employ/addnewspage");
                 break;
             case 12://vip服务->vip购买
                 if($request->get('_id')){
                     return redirect("/user/vipPay/".$request->get('_id')."/vip");
                 }else{
                     return redirect("/vipshop/vipServer");
                 }
                 break;
             case 13://vip服务->次卡购买
                 if($request->get('_id')){
                     return redirect("/user/vipPay/".$request->get('_id')."/cika");
                 }else{
                     return redirect("/vipshop/vipServer");
                 }
                 break;
                  case 14://快包详情（立即雇佣）->直接雇佣
                 if($request->get('_id')){
                     return redirect("employ/create/".$request->get('_id'));
                 }else{
                     return redirect("/kb/".$request->get('task_id'));
                 }
                 break;
         }
    }
}
