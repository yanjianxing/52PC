<?php
namespace App\Modules\Manage\Http\Controllers;

use App\Http\Controllers\ManageController;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserVipConfigModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Validator;
use Cache;
use Theme;


class ConfigController extends ManageController
{

    public function __construct()
    {
        parent::__construct();
        $this->initTheme('manage');
    }


    /**
     * 站点配置视图
     * @return mixed
     */
    public function getConfigSite()
    {
        $this->theme->setTitle('站点配置');
        $config = ConfigModel::getConfigByType('site');
        $basisConfig = ConfigModel::getConfigByType('basis');
        $data = array(
            'site' => $config,
            'basic' => $basisConfig
        );
        return $this->theme->scope('manage.config.site', $data)->render();
    }

    /**
     * 保存站点配置
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function saveConfigSite(Request $request)
    {
        $data = $request->except('_token', 'web_logo_1','web_logo_2');
        $config = ConfigModel::getConfigByType('site');

        $file1 = $request->file('web_logo_1');
        if ($file1) {
            //上传文件
            $result1 = \FileClass::uploadFile($file1, 'sys');
            $result1 = json_decode($result1, true);
            $data['web_logo_1'] = $result1['data']['url'];
        }else{
            $data['web_logo_1'] = $config['site_logo_1'];
        }
        $file2 = $request->file('web_logo_2');
        if ($file2) {
            //上传文件
            $result2 = \FileClass::uploadFile($file2, 'sys');
            $result2 = json_decode($result2, true);
            $data['web_logo_2'] = $result2['data']['url'];
        }else{
            $data['web_logo_2'] = $config['site_logo_2'];
        }
        $file3 = $request->file('wechat_pic');
        if ($file3) {
            //上传文件
            $result3 = \FileClass::uploadFile($file3, 'sys');
            $result3 = json_decode($result3, true);
            $data['wechat_pic'] = $result3['data']['url'];
        }else{
            $data['wechat_pic'] = $config['wechat']['wechat_pic'];
        }
        $file4 = $request->file('browser_logo');
        if ($file4) {
            //上传文件
            $ico = $file4->getClientOriginalExtension();
            if($ico != 'ico'){
                return redirect('/manage/config/site')->with(array('message' => '浏览器显示logo格式不对'));
            }
            $result4 = \FileClass::uploadFile($file4, 'sys');
            $result4 = json_decode($result4, true);
            $data['browser_logo'] = $result4['data']['url'];
        }else{
            $data['browser_logo'] = isset($config['browser_logo']) ? $config['browser_logo'] : '';
        }
        $siteRule = array(
            'site_name' => $data['web_site'],
            'site_url' => $data['web_url'],
            'site_logo_1' => $data['web_logo_1'],
            'site_logo_2' => $data['web_logo_2'],
            'browser_logo' => $data['browser_logo'],
            'company_name' => $data['company_name'],
            'company_address' => $data['company_address'],
            'record_number' => $data['site_record_code'],
            'copyright' => $data['footer_copyright'],
            'site_close' => $data['site_switch'],
            'phone' => $data['phone'],
            'inquiry_price'=>$data['inquiry_price'],
            'news_price'=>$data['news_price'],
            'deposit'=>$data['deposit'],
            'Email' => $data['Email'],
            'site_version' => isset($data['site_version']) ? $data['site_version'] : 1,
//             'reg_com' => $data['reg_com'],
//             'fb_royalty' => $data['fb_royalty'],
//             'jb_royalty' => $data['jb_royalty'],
        );
        ConfigModel::updateConfig($siteRule);
        Cache::forget('site');
        $basicRule = array(
            'css_adaptive' => $data['css_adaptive'],
            //'open_IM' => $data['open_IM'],
            'qq' => $data['customer_service_qq'],
//            'IM_config' => json_encode(array(
//                    'IM_ip' => $data['IM_ip'],
//                    'IM_port' => $data['IM_port']
//                )
//            ),
        );
        ConfigModel::updateConfig($basicRule);
        Cache::forget('basis');
        return redirect('/manage/config/site')->with(array('message' => '保存成功'));
    }

    /**
     * 邮箱配置视图
     * @return mixed
     */
    public function getConfigEmail()
    {
        $this->theme->setTitle('邮箱配置');
        //启用加密连接(SSL)：
        $mailEncryption = \CommonClass::findEnvInfo('MAIL_ENCRYPTION');
        if(empty($mailEncryption)){
            $mailEncryption = config('mail.encryption');
        }
        //邮件发送服务器
        $mailHost = \CommonClass::findEnvInfo('MAIL_HOST');
        //服务器端口
        $mailPort = \CommonClass::findEnvInfo('MAIL_PORT');
        //发送邮件账号
        $mailUsername = \CommonClass::findEnvInfo('MAIL_USERNAME');
        //账号密码
        $mailPassword = \CommonClass::findEnvInfo('MAIL_PASSWORD');
        //邮件回复地址
        $mailFromAddress = \CommonClass::findEnvInfo('MAIL_FROM_ADDRESS');
        $mailFromName = \CommonClass::findEnvInfo('MAIL_FROM_NAME');
        $testEmail = \CommonClass::findEnvInfo('MAIL_TEST');
        $email = array(
            'mail_encryption' => $mailEncryption,
            'send_mail_server' => $mailHost,
            'server_port' => $mailPort,
            'email_account' => $mailUsername,
            'account_password' => $mailPassword,
            'reply_email_address' => $mailFromAddress,
            'reply_email_name' => $mailFromName,
            'test_email_address' => $testEmail
        );
        $data = array(
            'email' => $email
        );
        return $this->theme->scope('manage.config.email', $data)->render();
    }

    /**
     * 保存邮箱配置
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function saveConfigEmail(Request $request)
    {
        $data = $request->except('_token');

        $validator = Validator::make($request->all(), [
            'mail_encryption' => 'required',
            'send_mail_server' => 'required',
            'server_port' => 'required',
            'email_account' => 'required',
            'account_password' => 'required',
            'reply_email_name' => 'required',
        ],[
            'mail_encryption.required' => '请选择是否启用加密连接(SSL)',
            'send_mail_server.required' => '请输入邮件发送服务器',
            'server_port.required' => '请输入服务器端口',
            'email_account.required' => '请输入发送邮件账号',
            'account_password.required' => '请输入账号密码',
            'reply_email_name.required' => '请输入邮件回复名称',
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return  redirect('/manage/config/email')->with(array('message' => $error[0]));
        }

        $configData = [
            'MAIL_ENCRYPTION' => $data['mail_encryption'] ? trim($data['mail_encryption']) : 'tls',
            'MAIL_HOST' => $data['send_mail_server'] ? trim($data['send_mail_server']) : '',
            'MAIL_PORT' => $data['server_port'] ? trim($data['server_port']) : 25,
            'MAIL_USERNAME' => $data['email_account'] ? trim($data['email_account']) : '',
            'MAIL_PASSWORD' => $data['account_password'] ? trim($data['account_password']) : '',
            'MAIL_FROM_ADDRESS' => $data['reply_email_address'] ? trim($data['reply_email_address']) : '',
            'MAIL_FROM_NAME' => $data['reply_email_name'] ?  trim($data['reply_email_name']) : '',
            'MAIL_TEST' => $data['test_email_address'] ? trim($data['test_email_address']) : ''
        ];
        foreach ($configData as $key => $value){
            $path = base_path('.env');
            $originStr = file_get_contents($path);
            if(strstr($originStr,$key)){
                $str = $key . "=" . $value;
                $res = \CommonClass::checkEnvIsNull($key);
                if($res){
                    $newStr = $key."=".env($key);
                }else{
                    if(\CommonClass::findEnvInfo($key)){
                        $newStr = $key.'='.\CommonClass::findEnvInfo($key);
                    }else{
                        $newStr = $key.'=';
                    }
                }
                $updateStr = str_replace($newStr,$str,$originStr);
                file_put_contents($path,$updateStr);
            }else{
                $str = "\n" .$key . "=" . $value;
                file_put_contents($path,$str,FILE_APPEND);
            }
        }
        return redirect('/manage/config/email')->with(array('message' => '保存成功'));


    }

    /**
     * 全局配置 基本配置视图
     *
     * @return mixed
     */
    public function getConfigBasic()
    {
        $this->theme->setTitle('基本配置');
        $config = ConfigModel::getConfigByType('basis');
        $data = array(
            'basic' => $config
        );
        return $this->theme->scope('manage.config.basic', $data)->render();
    }

    /**
     * 保存基本配置
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function saveConfigBasic(Request $request)
    {
        $data = $request->except('_token');
        $basicRule = array(
          /*  'user_forbid_keywords' => $data['user_forbid_keywords'],
            'content_forbid_keywords' => $data['content_forbid_keywords'],*/
            'css_adaptive' => $data['css_adaptive'],
            'open_IM' => $data['open_IM'],
            'qq' => $data['customer_service_qq'],
           /* 'new_user_registration_time_limit' => $data['new_user_registration_time_limit'],
            'user_registration_email_activation' => $data['user_registration_email_activation']*/
        );
        ConfigModel::updateConfig($basicRule);
        return redirect('/manage/config/basic')->with(array('message' => '保存成功'));
    }

    /**
     * seo配置
     *
     * @return mixed
     */
    public function getConfigSEO()
    {
        $this->theme->setTitle('seo配置');
        $seoConfig = ConfigModel::getConfigByType('seo');
        $data = array(
            'seo' => $seoConfig
        );
        return $this->theme->scope('manage.config.seo', $data)->render();
    }

    /**
     * 保存seo配置
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function saveConfigSEO(Request $request)
    {
        $data = $request->except('taken');
        $seoRule = array(
          /*  'seo_pseudo_static' => $data['pseudo_static'],
            'seo_secondary_domain' => $data['secondary_domain'],*/
            'seo_index' => json_encode(array(
                'title' => $data['homepage_seo_title'],
                'keywords' => $data['homepage_seo_keywords'],
                'description' => $data['homepage_seo_desc']
            )),
            'seo_task' => json_encode(array(
                'title' => $data['task_seo_title'],
                'keywords' => $data['task_seo_keywords'],
                'description' => $data['task_seo_desc']
            )),
            'seo_taskdetail' => json_encode(array(
                'title' => $data['taskdetail_seo_title'],
                'keywords' => $data['taskdetail_seo_keywords'],
                'description' => $data['taskdetail_seo_description']
            )),
            'seo_service' => json_encode(array(
                'title' => $data['service_seo_title'],
                'keywords' => $data['service_seo_keywords'],
                'description' => $data['service_seo_desc']
            )),
            'seo_servicedetail' => json_encode(array(
                'title' => $data['servicedetail_seo_title'],
                'keywords' => $data['servicedetail_seo_keywords'],
                'description' => $data['servicedetail_seo_desc']
            )),
            //方案超市
            'seo_supermarket'=>json_encode(array(
                'title'=>$data['supermarket_seo_title'],
                'keywords' => $data['supermarket_seo_keywords'],
                'description' => $data['supermarket_seo_desc']
            )),
            'seo_supermarketdetail'=>json_encode(array(
                'title'=>$data['supermarketdetail_seo_title'],
                'keywords' => $data['supermarketdetail_seo_keywords'],
                'description' => $data['supermarketdetail_seo_desc']
            )),
            'seo_article' => json_encode(array(
                'title' => $data['article_seo_title'],
                'keywords' => $data['article_seo_keywords'],
                'description' => $data['article_seo_desc']
            )),
            'seo_articledetail' => json_encode(array(
                'title' => $data['articledetail_seo_title'],
                'keywords' => $data['articledetail_seo_keywords'],
                'description' => $data['articledetail_seo_desc']
            )),

            'seo_successcase' => json_encode(array(
                'title' => $data['successcase_seo_title'],
                'keywords' => $data['successcase_seo_keywords'],
                'description' => $data['successcase_seo_desc']
            )),
            'seo_successcasedetail' => json_encode(array(
                'title' => $data['successcasedetail_seo_title'],
                'keywords' => $data['successcasedetail_seo_keywords'],
                'description' => $data['successcasedetail_seo_desc']
            )),
            'seo_listnews' => json_encode(array(
                'title' => $data['listnews_seo_title'],
                'keywords' => $data['listnews_seo_keywords'],
            )),
            'seo_listtask' => json_encode(array(
                'title' => $data['listtask_seo_title'],
                'keywords' => $data['listtask_seo_keywords'],
            )),
            'seo_listfacs' => json_encode(array(
                'title' => $data['listfacs_seo_title'],
                'keywords' => $data['listfacs_seo_keywords'],
            )),

        );
        ConfigModel::updateConfig($seoRule);
        Cache::forget('seo');
        return redirect('/manage/config/seo')->with(array('message' => '保存成功'));
    }


    //导航配置
    public function getConfigNav()
    {
        //TODO：获取导航配置
        $navigation = NavigationModel::getAll();
        $data = array(
            'data' => $navigation
        );
        return $this->theme->scope('manage.config.nav', $data)->render();
    }

    public function deleteConfigNav($id)
    {
        //TODO：删除导航
        NavigationModel::deleteNavigation($id);
        return redirect()->to('/manage/config/nav')->with(['massage'=>'删除成功！']);
    }

    public function postConfigNav(Request $request)
    {
        //TODO：新增导航
        NavigationModel::updateConfigNav($request->all());
        return redirect('/manage/config/nav');
    }

    /**
     * 附件配置
     *
     * @return mixed
     */
    public function getAttachmentConfig()
    {
        $this->theme->setTitle('附件配置');
        $config = ConfigModel::getConfigByType('attachment');

        $data = [
            'config' => $config
        ];
        return $this->theme->scope('manage.config.attachment', $data)->render();
    }

    /**
     * 保存附件配置信息
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function postAttachmentConfig(Request $request)
    {
        $data = $request->except('_token');
        ConfigModel::updateConfig($data);
        Cache::forget('attachment');
        return redirect('manage/config/attachment')->with(['message' => '操作成功']);
    }

    /**
     * 发送测试邮件
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendEmail(Request $request)
    {
        $email = $request->get('email');
        if(empty($email)){
            $data = array(
                'code' => 0,
                'msg' => '缺少测试邮箱地址'
            );
        }else{
            $flag = Mail::raw('这是一封测试邮件', function ($message) use ($email) {
                $to = $email;
                $message ->to($to)->subject('测试邮件');
            });
            if($flag == 1){
                $data = array(
                    'code' => 1,
                    'msg' => '发送邮件成功，请查收！'
                );
            }else{
                $data = array(
                    'code' => 0,
                    'msg' => '发送邮件失败，请重试！'
                );
            }
        }
        return response()->json($data);

    }
    public function aboutUs()
    {
        $this->theme->setTitle('关于我们');

        return $this->theme->scope('manage.config.aboutus')->render();
    }

    /**
     * 关注链接
     * @return mixed
     */
    public function configLink()
    {
        $this->theme->setTitle('关注链接');
        $config = ConfigModel::getConfigByType('site');
        $data = array(
            'site' => $config,
        );
        return $this->theme->scope('manage.config.link',$data)->render();
    }

    public function link(Request $request)
    {
        $data = $request->except('_token');
        $config = ConfigModel::getConfigByType('site');
        $file3 = $request->file('wechat_pic');
        if ($file3) {
            //上传文件
            $result3 = \FileClass::uploadFile($file3, 'sys');
            $result3 = json_decode($result3, true);
            $data['wechat_pic'] = $result3['data']['url'];
        }else{
            $data['wechat_pic'] = $config['wechat']['wechat_pic'];
        }
        $siteRule = array(
            'statistic_code' => $data['third_party_code'],
            'sina' =>  json_encode(array(
                    'sina_url' => $data['sina_url'],
                    'sina_switch' => $data['sina_switch']
                )
            ),
            'tencent' => json_encode(array(
                    'tencent_url' => $data['tencent_url'],
                    'tencent_switch' => $data['tencent_switch']
                )
            ),
            'wechat' => json_encode(array(
                    'wechat_pic' => $data['wechat_pic'],
                    'wechat_switch' => $data['wechat_switch']
                )
            ),
        );
        ConfigModel::updateConfig($siteRule);
        Cache::forget('site');
        return redirect('/manage/config/link')->with(array('message' => '保存成功'));
    }

    /**
     * 短信配置视图
     * @return mixed
     */
    public function getConfigPhone()
    {
        $this->theme->setTitle('短信配置');

        //容联云通讯配置信息
        $yunTongXun = config('phpsms.agents.YunTongXun');

        //阿里大鱼配置信息
        $alidayu = config('phpsms.agents.Alidayu');

        //阿里云通讯
        $aliyun = config('phpsms.agents.Aliyun');

        $phpsmsConfig = ConfigModel::getConfigByType('phpsms');
        if(isset($phpsmsConfig['phpsms_scheme']) && !empty($phpsmsConfig['phpsms_scheme'])){
            $scheme = $phpsmsConfig['phpsms_scheme'];
        }else{
            //配置类型
            $scheme = config('phpsms.scheme');
            $scheme = (!empty($scheme) && is_array($scheme)) ? $scheme[0] : '';
        }
        switch($scheme){
            case 'YunTongXun':
                if(isset($phpsmsConfig['phpsms_config']) && !empty($phpsmsConfig['phpsms_config'])){
                    $yunTongXun = $phpsmsConfig['phpsms_config'];
                }
                break;
            case 'Alidayu':
                if(isset($phpsmsConfig['phpsms_config']) && !empty($phpsmsConfig['phpsms_config'])){
                    $alidayu = $phpsmsConfig['phpsms_config'];
                }
                break;
            case 'Aliyun':
                if(isset($phpsmsConfig['phpsms_config']) && !empty($phpsmsConfig['phpsms_config'])){
                    $aliyun = $phpsmsConfig['phpsms_config'];
                }
                break;
        }
        $sendMobileCode = '';
        $sendMobilePasswordCode = '';
        $sendBindSms = '';
        $sendUnbindSms = '';
        $sendTaskCode = '';
        $sendUserPassword = '';
        $config =  ConfigModel::where('type','phone')->get()->toArray();
        if(!empty($config)){
            foreach($config as $k => $v){
                switch($v['alias']){
                    case 'sendMobileCode':
                        $sendMobileCode = $v['rule'];
                        break;
                    case 'sendMobilePasswordCode':
                        $sendMobilePasswordCode = $v['rule'];
                        break;
                    case 'sendBindSms':
                        $sendBindSms = $v['rule'];
                        break;
                    case 'sendUnbindSms':
                        $sendUnbindSms = $v['rule'];
                        break;
                    case 'sendTaskCode':
                        $sendTaskCode = $v['rule'];
                        break;
                    case 'sendUserPassword' :
                        $sendUserPassword = $v['rule'];
                        break;
                    case 'sendLoinPassword':
                        $sendLoinPassword=$v['rule'];
                        break;
                }
            }
        }

        $phone = array(
            'scheme'        => $scheme,
            'yunTongXun'    => $yunTongXun,
            'alidayu'       => $alidayu,
            'aliyun'        => $aliyun,
        );
        $data = array(
            'phone'                     => $phone,
            'sendMobileCode'            => $sendMobileCode,
            'sendMobilePasswordCode'    => $sendMobilePasswordCode,
            'sendBindSms'               => $sendBindSms,
            'sendUnbindSms'             => $sendUnbindSms,
            'sendTaskCode'              => $sendTaskCode,
            'sendUserPassword'          => $sendUserPassword,
            'sendLoinPassword'         =>$sendLoinPassword,
        );
        return $this->theme->scope('manage.config.phone', $data)->render();
    }

    public function getConfigUser(){
        $this->theme->setTitle('普通会员配置');
        $site=ConfigModel::getConfigByType('user');
        $data=[
            'site'=>$site,
        ];
        return $this->theme->scope('manage.config.user', $data)->render();
    }
    /*
     * 保存会员配置
     * */
     public function configUserPost(Request $request){
           $arr=$request->except("_token");
           $data['bid_num']=$request->get("user_bid_num");
           $data['bid_price']=$request->get("user_bid_price");
           $data['skill_num']=$request->get("user_skill_num");
           $data['inquiry_num']=$request->get("user_inquiry_num");
           $data['accept_inquiry_num']=$request->get("user_accept_inquiry_num");
           $data['stick_discount']=$request->get("user_stick_discount");
           $data['urgent_discount']=$request->get("user_urgent_discount");
           $data['private_discount']=$request->get("user_private_discount");
           $data['train_discount']=$request->get("user_train_discount");
           $data['is_show']=$request->get("user_is_show");
           $data['is_logo']=$request->get("user_is_logo");
           $data['is_nav']=$request->get("user_is_nav");
           $data['is_slide']=$request->get("user_is_nav");
           $data['appliy_num']=$request->get("user_appliy_num");
           $res=DB::transaction(function()use($data,$arr){
               foreach($arr as $k=>$v){
                   ConfigModel::where("alias",$k)->where("type","user")->update(['rule'=>$v]);
               }
               UserVipConfigModel::updateConfigData($data);
               Cache::forget("user");
               return $data;
           });
          if($res){
              return back()->with(["message"=>"修改成功"]);
          }
              return back()->with(["message"=>"修改失败"]);
     }
    /**
     * 保存短信配置
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function saveConfigPhone(Request $request)
    {
        $data = $request->except('_token');
        if(!isset($data['scheme']) || empty($data['scheme'])){
            return  redirect('/manage/config/phone')->with(array('message' => '请选择短信服务商'));
        }
        $configData = [];
        switch($data['scheme']){
            case 'YunTongXun' :
                $validator = Validator::make($data, [
                    'accountSid'    => 'required',
                    'accountToken'  => 'required',
                    'appId'         => 'required',

                ],[
                    'accountSid.required'   => '请填写主帐号',
                    'accountToken.required' => '请填写主帐号令牌',
                    'appId.required'        => '请填写应用Id',

                ]);
                $error = $validator->errors()->all();
                if(count($error)){
                    return  redirect('/manage/config/phone')->with(array('message' => $error[0]));
                }
                $configData = [
                    'accountSid'    => $data['accountSid'] ? trim($data['accountSid']) : '',
                    'accountToken'  => $data['accountToken'] ? trim($data['accountToken']) : '',
                    'appId'         => $data['appId'] ? trim($data['appId']) : '',

                ];
                break;
            case 'Alidayu' :
                $validator = Validator::make($data, [
                    'sendUrl'         => 'required',
                    'appKey'          => 'required',
                    'secretKey'       => 'required',
                    'smsFreeSignName' => 'required',

                ],[
                    'sendUrl.required'          => '请填写请求地址',
                    'appKey.required'           => '请填写appKey',
                    'secretKey.required'        => '请填写secretKey',
                    'smsFreeSignName.required'  => '请填写短信签名',
                ]);
                $error = $validator->errors()->all();
                if(count($error)){
                    return  redirect('/manage/config/phone')->with(array('message' => $error[0]));
                }
                $configData = [
                    'sendUrl'         => $data['sendUrl'] ? trim($data['sendUrl']) : '',
                    'appKey'          => $data['appKey'] ? trim($data['appKey']) : '',
                    'secretKey'       => $data['secretKey'] ?  trim($data['secretKey']) : '',
                    'smsFreeSignName' => $data['smsFreeSignName'] ? trim($data['smsFreeSignName']) : '',
                ];
                break;
            case 'Aliyun' :
                $validator = Validator::make($data, [
                    'accessKeyId'       => 'required',
                    'accessKeySecret'   => 'required',
                    'signName'          => 'required',
                ],[
                    'accessKeyId.required'      => '请填写accessKeyId',
                    'accessKeySecret.required'  => '请填写accessKeySecret',
                    'signName.required'         => '请填写短信签名',
                ]);
                $error = $validator->errors()->all();
                if(count($error)){
                    return  redirect('/manage/config/phone')->with(array('message' => $error[0]));
                }
                $configData = [
                    'accessKeyId'       => $data['accessKeyId'] ? trim($data['accessKeyId']) : '',
                    'accessKeySecret'   => $data['accessKeySecret'] ? trim($data['accessKeySecret']) : '',
                    'signName'          => $data['signName'] ? trim($data['signName']) : '',
                ];
                break;
        }
        $arr = [
            'sendMobileCode'         => $data['sendMobileCode'] ? trim($data['sendMobileCode']) : '',
            'sendMobilePasswordCode' => $data['sendMobilePasswordCode'] ? trim($data['sendMobilePasswordCode']) : '',
            'sendBindSms'            => $data['sendBindSms'] ?  trim($data['sendBindSms']) : '',
            'sendUnbindSms'          => $data['sendUnbindSms'] ? trim($data['sendUnbindSms']) : '',
            'sendTaskCode'           => $data['sendTaskCode'] ? trim($data['sendTaskCode']) : '',
            'sendUserPassword'       => $data['sendUserPassword'] ? trim($data['sendUserPassword']) : '',
            'sendLoinPassword'        => $data['sendLoinPassword'] ? trim($data['sendLoinPassword']) : '',
        ];
        $count = 0;
        $total = 0;
        if(!empty($arr)){
            foreach($arr as $k => $v) {
                if(!empty($v)){
                    $total = $total + 1;
                    $isExits = ConfigModel::where('alias',$k)->first();
                    if($isExits){
                        $r = ConfigModel::where('alias',$k)->update(['rule' => $v]);
                        if($r){
                            $count = $count +1;
                        }
                    }else{
                        $newArr = [
                            'alias' => $k,
                            'rule'  => $v,
                            'type'  => 'phone'
                        ];
                        $r = ConfigModel::create($newArr);
                        if($r){
                            $count = $count +1;
                        }
                    }
                }

            }
        }

        //修改短信服务商
        $isExitsS = ConfigModel::where('alias','phpsms_scheme')->first();
        if($isExitsS){
            ConfigModel::where('alias','phpsms_scheme')->update(['rule' => trim($data['scheme'])]);
        }else{
            $newArrS = [
                'alias' => 'phpsms_scheme',
                'rule' => trim($data['scheme']),
                'type' => 'phpsms'
            ];
            ConfigModel::create($newArrS);
        }

        if(!empty($configData)){

            //修改短信配置
            $configData = json_encode($configData);
            $isExitsC = ConfigModel::where('alias','phpsms_config')->first();
            if($isExitsC){
                ConfigModel::where('alias','phpsms_config')->update(['rule' => $configData]);
            }else{
                $newArrS = [
                    'alias' => 'phpsms_config',
                    'rule' => $configData,
                    'type' => 'phpsms'
                ];
                ConfigModel::create($newArrS);
            }
            Cache::forget('phpsms');
            return redirect('/manage/config/phone')->with(array('message' => '保存成功'));

        }

        return redirect('/manage/config/phone')->with(array('message' => '保存失败'));

    }

    /**
     * app支付宝支付配置视图
     * @return mixed
     */
    public function getConfigAppAliPay()
    {
        $this->theme->setTitle('app支付宝支付配置');

        //合作身份者id
        $partner_id = config('latrell-alipay.partner_id');
        //卖家支付宝帐户
        $seller_id = config('latrell-alipay.seller_id');
        //安全检验码
        $key = config('latrell-alipay-mobile.key');

        $alipay_type = 1;
        $appId = '';

        $config = ConfigModel::getConfigByAlias('app_alipay');
        if($config && !empty($config['rule'])){
            $info = json_decode($config['rule'],true);
            $partner_id = isset($info['partner_id']) ? $info['partner_id'] : '';
            $seller_id = isset($info['seller_id']) ? $info['seller_id'] : '';
            $key = isset($info['key']) ? $info['key'] : '';
            $alipay_type = isset($info['alipay_type']) ? $info['alipay_type'] : 1;
            $appId = isset($info['appId']) ? $info['appId'] : '';
        }
        $isPrivate = false;
        $isPublic = false;
        //商户私钥
        $private_key_path = storage_path('app/alipay/rsa_private_key.pem');
        if(file_exists($private_key_path)){
            $isPrivate = true;
        }
        //阿里公钥
        $public_key_path = storage_path('app/alipay/rsa_public_key.pem');
        if(file_exists($public_key_path)){
            $isPublic = true;
        }
       //dd($partner_id,$seller_id,$key,$isPrivate,$isPublic);
        $data = array(
            'partner_id' => $partner_id,
            'seller_id' => $seller_id,
            'key' => $key,
            'alipay_type' => $alipay_type,
            'appId' => $appId,
            'private_key_path' => $private_key_path,
            'public_key_path' => $public_key_path,
            'isPrivate' => $isPrivate,
            'isPublic' => $isPublic
        );
        return $this->theme->scope('manage.config.appalipay', $data)->render();
    }

    /**
     * 保存app支付宝支付
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function saveConfigAppAliPay(Request $request)
    {
        $data = $request->except('_token');

        $validator = Validator::make($data, [
            'alipay_type' => 'required',
            //'partner_id' => 'required',
            'seller_id' => 'required',
            //'key' => 'required',
            'private_key_path' => 'required',
            'public_key_path' => 'required',
        ],[
            'alipay_type.required' => '请选择支付宝app支付版本',
            //'partner_id.required' => '请填写合作身份者id',
            'seller_id.required' => '请填写卖家支付宝帐户',
            //'key.required' => '请填写安全检验码',
            'private_key_path.required' => '请上传商户私钥',
            'public_key_path.required' => '请上传阿里私钥'
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return  redirect('/manage/config/appalipay')->with(array('message' => $error[0]));
        }

        if(isset($data['alipay_type']) && $data['alipay_type'] == 1){
            if(!isset($data['partner_id']) || $data['key']){
                return  redirect('/manage/config/appalipay')->with(array('message' => '合作身份者id或安全检验码必填'));
            }
        }elseif(isset($data['alipay_type']) && $data['alipay_type'] == 1){
            if(!isset($data['appId'])){
                return  redirect('/manage/config/appalipay')->with(array('message' => 'appId'));
            }
        }

        $configData = [
            'alipay_type' => $data['alipay_type'] ? trim($data['alipay_type']) : 1,
            'partner_id' => $data['partner_id'] ? trim($data['partner_id']) : '',
            'seller_id' => $data['seller_id'] ? trim($data['seller_id']) : '',
            'key' => $data['key'] ?  trim($data['key']) : '',
            'appId' => $data['appId'] ? trim($data['appId']) : '',

        ];

        $configData = json_encode($configData);
        //修改app支付宝支付配置
        $isExitsS = ConfigModel::where('alias','app_alipay')->first();
        if($isExitsS){
            $rr = ConfigModel::where('alias','app_alipay')->update(['rule' => $configData]);
        }else{
            $newArrS = [
                'alias' => 'app_alipay',
                'rule' => $configData,
                'type' => 'thirdpay'
            ];
            $rr = ConfigModel::create($newArrS);
        }

        //上传文件目标路径
        $path = storage_path().'/app/alipay';
        $privatefile = $request->file('private_key_path');
        $privatefileName = $privatefile->getClientOriginalName();
        $publicfile = $request->file('public_key_path');
        $publicfileName = $publicfile->getClientOriginalName();

        $uploadArr = [
            'private_key_path' => [
                $privatefile,
                $privatefileName
            ],
            'public_key_path' => [
                $publicfile,
                $publicfileName
            ]
        ];
        $count = 0;
        foreach($uploadArr as $k => $v){
            if(is_uploaded_file($_FILES[$k]['tmp_name'])){
                if(file_exists($path.'/'.$v[1])){
                    //删除该文件
                    unlink($path.'/'.$v[1]);
                }
                $res = \FileClass::uploadFileToDir($v[0]);
                $res = json_decode($res,true);
                if($res['code'] == 200){
                    $count = $count + 1;
                }
            }
        }

        if(!$rr && $count < 2){
            return redirect('/manage/config/appalipay')->with(array('message' => '保存失败'));
        }else{
            return redirect('/manage/config/appalipay')->with(array('message' => '保存成功'));
        }
    }


    /**
     * app微信支付配置
     * @return mixed
     */
    public function getConfigAppWeChat()
    {
        $this->theme->setTitle('app微信支付配置');


        $wechatConfig = config('laravel-omnipay.gateways.WechatPay.options');
        $config = ConfigModel::getConfigByAlias('app_wechat');
        if($config && !empty($config['rule'])){
            $wechatConfig = json_decode($config['rule'],true);

        }

        $data = array(
            'wechat' => $wechatConfig
        );
        return $this->theme->scope('manage.config.appwechat', $data)->render();
    }

    /**
     * 保存app微信支付配置
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveConfigAppWeChat(Request $request)
    {
        $data = $request->except('_token');

        $validator = Validator::make($data, [
            'appId' => 'required',
            'apiKey' => 'required',
            'mchId' => 'required',
        ],[
            'appId.required' => '请填写appId',
            'apiKey.required' => '请填写apiKey',
            'mchId.required' => '请填写mchId',
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return  redirect('/manage/config/appwechat')->with(array('message' => $error[0]));
        }

        $configData = [
            'appId' => $data['appId'] ? trim($data['appId']) : '',
            'apiKey' => $data['apiKey'] ? trim($data['apiKey']) : '',
            'mchId' => $data['mchId'] ?  trim($data['mchId']) : '',
        ];

        $configData = json_encode($configData);
        //修改app微信支付配置
        $isExitsS = ConfigModel::where('alias','app_wechat')->first();
        if($isExitsS){
            ConfigModel::where('alias','app_wechat')->update(['rule' => $configData]);
        }else{
            $newArrS = [
                'alias' => 'app_wechat',
                'rule' => $configData,
                'type' => 'thirdpay'
            ];
            ConfigModel::create($newArrS);
        }
        return redirect('/manage/config/appwechat')->with(array('message' => '保存成功'));
    }

    /**
     * app聊天配置(申请的淘宝百川应用)
     * @return mixed
     */
    public function getConfigAppMessage()
    {
        $this->theme->setTitle('app聊天配置');

        $messageConfig = [];
        $config = ConfigModel::getConfigByAlias('app_message');
        if($config && !empty($config['rule'])){
            $messageConfig = json_decode($config['rule'],true);

        }

        $data = array(
            'message' => $messageConfig
        );
        return $this->theme->scope('manage.config.appmessage', $data)->render();
    }

    /**
     * 保存app聊天配置
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function saveConfigAppMessage(Request $request)
    {
        $data = $request->except('_token');

        $validator = Validator::make($data, [
            'appkey' => 'required',
            'secretKey' => 'required',
        ],[
            'appkey.required' => '请填写appkey',
            'secretKey.required' => '请填写secretKey',
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return  redirect('/manage/config/appMessage')->with(array('message' => $error[0]));
        }

        $configData = [
            'appkey' => $data['appkey'] ? trim($data['appkey']) : '',
            'secretKey' => $data['secretKey'] ? trim($data['secretKey']) : '',
        ];

        $configData = json_encode($configData);
        //修改app微信支付配置
        $isExitsS = ConfigModel::where('alias','app_message')->first();
        if($isExitsS){
            ConfigModel::where('alias','app_message')->update(['rule' => $configData]);
        }else{
            $newArrS = [
                'alias' => 'app_message',
                'rule' => $configData,
                'type' => 'thirdpay'
            ];
            ConfigModel::create($newArrS);
        }
        return redirect('/manage/config/appMessage')->with(array('message' => '保存成功'));
    }

    public function insertUserOpenIm(Request $request)
    {
        //百川云旺添加客户
        $messageConfig = [];
        $config = ConfigModel::getConfigByAlias('app_message');
        if($config && !empty($config['rule'])){
            $messageConfig = json_decode($config['rule'],true);
        }
        $domain = ConfigModel::where('alias','site_url')->where('type','site')->select('rule')->first();

        if(!empty($messageConfig)){

            //查询所有用户（正常）
            $user = UserModel::select('id','name')->where('status',1)->get()->toArray();
            if(!empty($user)){
                foreach($user as $k => $v){
                    $username = strval($v['id']);
                    $userDetail = UserDetailModel::where('uid',$v['id'])->first(); $c = new \TopClient();
                    $c->appkey = isset($messageConfig['appkey']) ? $messageConfig['appkey'] : '';
                    $c->secretKey = isset($messageConfig['secretKey']) ? $messageConfig['secretKey'] : '';

                    //查询用户是否存在
                    $req = new \OpenimUsersGetRequest();
                    $req->setUserids($username);
                    $userInfos = $c->execute($req);
                    if(!isset($userInfos->userinfos->userinfos)){
                        //新增用户信息
                        $req = new \OpenimUsersAddRequest();
                        $userinfos = new \Userinfos();
                        $userinfos->nick     = $v['name'];
                        $userinfos->icon_url = $userDetail['avatar']?$domain->rule.'/'.$userDetail['avatar']:$userDetail['avatar'];
                        $userinfos->email    = $userDetail['email'];
                        $userinfos->mobile   = $userDetail['mobile'];
                        $userinfos->userid   = $v['id'];
                        $userinfos->password = '';
                        $userinfos->name     = $userDetail['name'];
                        $req->setUserinfos(json_encode($userinfos));
                        $c->execute($req);
                    }
                }
            }
            $data = [
                'code' => 1,
                'msg'  => 'success'
            ];

        }else{
            $data = [
                'code' => 0,
                'msg'  => '请先配置聊天配置'
            ];
        }
        return $data;
    }


    /**
     * 微信端配置
     * @return mixed
     */
    public function getConfigWeChatPublic()
    {
        $this->theme->setTitle('微信端配置');


        $appId = config('wechat-public.app_id');
        $secret = config('wechat-public.secret');
        $token = config('wechat-public.token');
        $aesKey = config('wechat-public.aes_key');
        $wechatConfig = [
            'app_id'  => $appId,
            'secret'  => $secret,
            'token'   => $token,
            'aes_key' => $aesKey
        ];
        $config = ConfigModel::getConfigByAlias('wechat_public');
        if($config && !empty($config['rule'])){
            $wechatConfig = json_decode($config['rule'],true);

        }

        $data = array(
            'wechat' => $wechatConfig
        );
        return $this->theme->scope('manage.config.wechatpublic', $data)->render();
    }

    public function saveConfigWeChatPublic(Request $request)
    {
        $data = $request->except('_token');

        $validator = Validator::make($data, [
            'app_id' => 'required',
            'secret' => 'required',
            'token' => 'required',
        ],[
            'app_id.required' => '请填写app_id',
            'secret.required' => '请填写secret',
            'token.required' => '请填写token',
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return  redirect('/manage/config/wechatpublic')->with(array('message' => $error[0]));
        }

        $configData = [
            'app_id' => $data['app_id'] ? trim($data['app_id']) : '',
            'secret' => $data['secret'] ? trim($data['secret']) : '',
            'token' => $data['token'] ?  trim($data['token']) : '',
            'aes_key' => $data['aes_key'] ?  trim($data['aes_key']) : '',
        ];

        $configData = json_encode($configData);
        //修改app微信支付配置
        $isExitsS = ConfigModel::where('alias','wechat_public')->first();
        if($isExitsS){
            ConfigModel::where('alias','wechat_public')->update(['rule' => $configData]);
        }else{
            $newArrS = [
                'alias' => 'wechat_public',
                'rule' => $configData,
                'type' => 'wechat_public',
                'title' => '微信端配置',
            ];
            ConfigModel::create($newArrS);
        }
        return redirect('/manage/config/wechatpublic')->with(array('message' => '保存成功'));
    }

    /**
     * 获取制定字符之间的字符串
     * @param $kw1
     * @param $mark1
     * @param $mark2
     * @return int|string
     */
    function getNeedBetween($kw1,$mark1,$mark2){
        $kw = $kw1;
        $st = strpos($kw,$mark1);
        $new = strstr($kw,$mark1);
        $ed = strpos($new,$mark2);
        $ed = $st + $ed;
        if(($st == false || $ed == false )||$st >= $ed){
            return 0;
        }
        $kw = substr($kw,($st+1),($ed-$st-1));
        return $kw;
    }

    function getNeedStrBetween($kw1,$mark1,$mark2){
        $kw = $kw1;
        $st = strpos($kw,$mark1);
        $new = strstr($kw,$mark1);
        $ed = strpos($new,$mark2);
        $ed = $st + $ed;
        if(($st === false || $ed === false )||$st >= $ed){
            return 0;
        }
        $kw = substr($kw,($st+strlen($mark1)),($ed-$st-strlen($mark2)-2));
        return $kw;
    }
}