<?php
namespace App\Modules\Manage\Http\Controllers;


use App\Http\Controllers\ManageController;
use App\Modules\Manage\Model\ConfigModel;
use Illuminate\Http\Request;
use Theme;

class KeeController extends ManageController
{

    public function __construct()
    {
        parent::__construct();

        $this->initTheme('manage');
    }

    /**
     * 接入交付台
     * @param Request $request
     * @return mixed
     */
    public function keeLoad(Request $request)
    {
        $this->theme->setTitle('接入交付台');
        //查询是否已经申请加入kee
        $keeKey = '';
        $keeKeyRule = ConfigModel::where('alias', 'kee_key')->first();
        if($keeKeyRule && !empty($keeKeyRule['rule'])){
            $keeKey = $keeKeyRule['rule'];
        }
        $status = 100;//没有申请接入
        if(!empty($keeKey)){
            $url = \CommonClass::getConfig('kee_path').'KPPWStateForKee?key='.$keeKey;
            $result = json_decode(\CommonClass::sendGetRequest($url),true);
            if($result['code'] == 1000){
                $status = $result['data']['statue'];//0:审核中 1:通过 99:拒绝
            }
        }
        $isOpen  = 1; //默认开启
        //查询接入kee功能是否开启
        $openKeeRule = ConfigModel::where('alias', 'open_kee')->first();
        if($status == 1){//通过
            if($openKeeRule){
                $isOpen = $openKeeRule['rule'];
            }else{
                ConfigModel::create(['alias'=> 'open_kee','rule'=> 1]);
            }
        }else{
            $isOpen = 0;
            if($openKeeRule){
                ConfigModel::where('alias', 'open_kee')->update(['rule'=> 0]);

            }else{
                ConfigModel::create(['alias'=> 'open_kee','rule'=> 0]);
            }
        }

        $view = [
            'kee_status' => $status,
            'is_open' => $isOpen
        ];
        return $this->theme->scope('manage.keeload.index', $view)->render();
    }

    /**
     * 初次接入kee
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function keeLoadFirst(Request $request)
    {
        $serviceSiteUrl = \CommonClass::getConfig('site_url');
        $serviceLogo = \CommonClass::getConfig('site_logo');
        $serviceName = \CommonClass::getConfig('company_name');
        $serviceContactInfo = \CommonClass::getConfig('contact_phone');
        $serviceContactEmail = \CommonClass::getConfig('contact_email');
        //查询接入状态
        $data = [
            'serviceSiteUrl' => $serviceSiteUrl,
            'serviceName' => $serviceName,
            'serviceContacts' => '',
            'serviceContactInfo' => $serviceContactInfo,
            'serviceContactEmail' => $serviceContactEmail,
            'serviceLogo' => $serviceLogo,
            'serviceType' => 'pfkppw',
        ];
        $url = \CommonClass::getConfig('kee_path').'KPPWApplyForKee';
        $result = json_decode(\CommonClass::sendPostRequest($url,json_encode($data)),true);
        if($result['code'] == 1000){
            //查询是否已经申请加入kee
            $keeKeyRule = ConfigModel::where('alias', 'kee_key')->first();
            if($keeKeyRule){
                $res = ConfigModel::where('alias', 'kee_key')->update(['rule'=> $result['data']['key']]);
            }else{
                $res = ConfigModel::create(['alias'=> 'kee_key','rule'=> $result['data']['key']]);
            }
            if($res){
                return redirect('/manage/keeLoad')->with(['message' => '申请成功']);
            }else{
                return redirect('/manage/keeLoad')->with(['message' => '申请失败']);
            }

        }else{
            return redirect('/manage/keeLoad')->with(['message' => '申请失败']);
        }
    }

    /**
     * 首次接入kee被拒绝后再次申请接入kee
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function keeLoadAgain(Request $request)
    {
        //查询是否已经申请加入kee
        $keeKeyRule = ConfigModel::where('alias', 'kee_key')->first();
        if($keeKeyRule){
            $keeKey = $keeKeyRule['rule'];
        }else{
            $keeKey = '';
        }
        if($keeKey){
            $url = \CommonClass::getConfig('kee_path').'KPPWAgainApplyForKee?key='.$keeKey;
            $result = json_decode(\CommonClass::sendGetRequest($url),true);
            if($result['code'] == 1000){
                return redirect('/manage/keeLoad')->with(['message' => '申请成功']);
            }else{
                return redirect('/manage/keeLoad')->with(['message' => '申请失败']);
            }
        }

    }


    /**
     * 是否开启接入kee
     * @param Request $request
     * @return array
     */
    public function isOpenKee(Request $request)
    {
        $value = $request->get('value');
        $info = [
            'rule' => $value
        ];
        $res = ConfigModel::where('alias','open_kee')->update($info);
        if($res){
            $arr = [
                'code' => 1,
                'msg' => 'success',
            ];
            return $arr;
        }else{
            $arr = [
                'code' => 0,
                'msg' => 'failure',
            ];
            return $arr;
        }
    }











}