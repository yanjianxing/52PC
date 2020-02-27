<?php

namespace App\Modules\Employ\Http\Controllers;

use App\Http\Controllers\IndexController as BasicIndexController;
use App\Http\Requests;
use App\Modules\Advertisement\Model\AdTargetModel;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Manage\Model\AgreementModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Model\TaskAttachmentModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskFocusModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskPaySectionModel;
use App\Modules\Task\Model\TaskPayTypeModel;
use App\Modules\Task\Model\TaskRightsModel;
use App\Modules\Task\Model\TaskTemplateModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\CommentModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\UserDetailModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Session\SessionInterface;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\DB;
use App\Modules\User\Model\UserModel;
use App\Modules\Manage\Model\ConfigModel;
use Omnipay;
use Theme;
use QrCode;

class EmployController extends BasicIndexController
{
    public function __construct()
    {
        parent::__construct();
        $this->user = Auth::user();
        $this->initTheme('fastpackage');
    }

    /**
     * 创建雇佣页面
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function employCreate(Request $request,$id)
    {
        $this->theme->setTitle('雇佣服务商');
        //判断当前用户是不是在雇佣自己
        if(Auth::check()){
            if ($id == Auth::user()['id']) {
                return redirect()->back()->with(['error' => '自己不能雇佣自己！']);
            }
        }
        
        // if(empty(Auth::user()->mobile)){
        //     return redirect('/user/phoneAuth?type=el')->with(['error' => '请先进行手机认证']);
        // }
        $shop = ShopModel::where('uid',$id)->first();
        if(!$shop){
            return redirect()->back()->with(['error' => '没有开通店铺不能雇佣！']);
        }
        //查询模板
        $fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        $templateFiled = [];
        if($fieldArr){
            $fieldIdArr = array_pluck($fieldArr,'id');
            $template = TaskTemplateModel::whereIn('cate_id',$fieldIdArr)->select('id','cate_id','title','content')->get()->toArray();
            $template = \CommonClass::setArrayKey($template,'cate_id');
            foreach($fieldArr as $k => $v){
                if(in_array($v['id'],array_keys($template))){
                    $templateFiled[$k] = $v;
                    $templateFiled[$k]['template'] = $template[$v['id']];
                }
            }
        }
        //获取ip
        /*$ip = \CommonClass::getIp();
        $address = \CommonClass::getAddress($ip);*/

        //查询模板
        $skillArr = TaskCateModel::where('type',2)->select('id','name')->orderBy('sort','desc')->get()->toArray();

        $province = DistrictModel::findTree(0);
        $city = [];
        if($province){
            $city = DistrictModel::findTree($province[0]['id']);
        }

        //生产代工参数
        $platesNum = \CommonClass::platesNum();
        $plateThick = \CommonClass::plateThick();
        $copperThickne = \CommonClass::copperThickne();
        $platingType = \CommonClass::platingType();
        $solderColor = \CommonClass::solderColor();
        $characterColor = \CommonClass::characterColor();
        $deliveryCycle = \CommonClass::deliveryCycle();


        /*$ad = AdTargetModel::getAdByTypeId(3);
        AdTargetModel::addViewCountByCode('CREATE_TASK');*/
        $ad = AdTargetModel::getAdByCodePage('CREATE_TASK');
        $view = [
            'uid'            => $id,
            'fieldArr'       => $fieldArr,
            'templateFiled'  => $templateFiled,
            'skillArr'       => $skillArr,
            'province'       => $province,
            'city'           => $city,
            'platesNum'      => $platesNum,
            'plateThick'     => $plateThick,
            'copperThickne'  => $copperThickne,
            'platingType'    => $platingType,
            'solderColor'    => $solderColor,
            'characterColor' => $characterColor,
            'deliveryCycle'  => $deliveryCycle,
            'ad'             => $ad,
            'goodID'        =>$request->get('goodID')?$request->get('goodID'):0,
        ];
        return $this->theme->scope('employ.create', $view)->render();
    }

    /**
     * 提交雇佣任务
     * @param Request $request
     * @return Redirect
     */
    public function employUpdate(Request $request)
    {
        $data = $request->except('_token');
        if(!empty($data['mobile'])){
            $data['phone'] = $data['mobile'];
        }
        if(Auth::check()){
            $data['uid'] = $this->user['id'];
            if(isset($data['code'])){
                $authMobileInfo = session('task_mobile_info');
                if ($data['code'] == $authMobileInfo['code'] && $data['phone'] == $authMobileInfo['mobile']) {
                    Session::forget('task_mobile_info');
                }else{
                    return back()->with(['message'=>"验证码错误"]);
                }
            }
        }else{
            $authMobileInfo = session('task_mobile_info');
            if ($data['code'] == $authMobileInfo['code'] && $data['mobile'] == $authMobileInfo['mobile']){
                Session::forget('task_mobile_info');
                $user = UserModel::where('mobile',$data['mobile'])->first();
                if($user){
                    $data['uid'] = $user['id'];
                }else{
                    $data['uid'] = DB::transaction(function() use($data){
                        $username = $username = time().\CommonClass::random(4);
                        $userInfo = [
                            'username' => $username,
                            'mobile' => $data['mobile'],
                            'password' => $data['mobile']
                        ];
                        $uid = UserModel::mobileInitUser($userInfo);
                        //发送通知短信
                        $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                        $templateId = ConfigModel::phpSmsConfig('sendLoinPassword');
                        $templates = [
                            $scheme => $templateId,
                        ];
                        $tempData = [
                            'code' => $data['mobile'],
                        ];

                        \SmsClass::sendSms($data['mobile'], $templates, $tempData);
                        return $uid;
                    });
                }
                Auth::loginUsingId($data['uid']);
                UserModel::where('id',$data['uid'])->update(['last_login_time' => date('Y-m-d H:i:s'),'is_phone_login'=>1]);
            }else{
                return back()->with(["message"=>"手机验证码错误"])->withInput();
            }
            
        }
        // $data['uid'] = $this->user['id'];
        $data['desc'] = \CommonClass::removeXss($data['desc']);
        $data['begin_at'] = date('Y-m-d H:i:s');
        $data['end_at'] = preg_replace('/([\x80-\xff]*)/i', '', $data['end_at']);
        $data['begin_at'] = date('Y-m-d H:i:s', strtotime($data['begin_at']));
        $data['end_at'] = date('Y-m-d H:i:s', strtotime($data['end_at']));
        $data['show_cash'] = $data['bounty'];

        $data['created_at'] = date('Y-m-d H:i:s');
        //发布和暂不发布切换
        if ($data['type'] == 1) {
            // $data['status'] = 1;
            $data['status'] = 2;    //直接发布不需要审核
        } elseif ($data['type'] == 2) {
            $data['status'] = 0;
        }
        
        $result = TaskModel::createEmploy($data);
        if (!$result) {
            return redirect()->back()->with('error', '创建雇佣失败！');
        }
        /*直接雇佣发送信息*/
        $userInfo = UserModel::where('id',$data['employee_uid'])->first();
        $employerinfo = UserModel::where('id',$data['uid'])->first();
        $user = [
            'uid'    => $data['employee_uid'],
            'email'  => $userInfo->email,
            'mobile' => $userInfo->mobile
        ];
        $templateArr = [
            'employee_name' => $userInfo->name,
            'employer_name' => $employerinfo->name,
        ];
        \MessageTemplateClass::sendMessage('employ_notice',$user,$templateArr,$templateArr);
        /*end*/


        if($data['type']== 2){
            return redirect()->to('user/unreleasedEmploy');
        }
        return redirect()->to('employ/success/' . $result['id']);
    }

    /**
     * 成功发布雇佣
     */
    public function success($id)
    {
        $id = intval($id);
        //验证任务是否是状态2
        $task = TaskModel::where('id',$id)->first();
        /*不需要审核*/
        // if($task['status']!=1){
        //     return redirect()->back()->with(['error'=>'数据错误，当前雇佣不处于等待审核状态！']);
        // }
        $view = [
            'id' => $id,
        ];

        return $this->theme->scope('employ.success',$view)->render();
    }

    /**
     * 雇佣详情页面
     * @param $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function workin($id,Request $request)
    {
        $this->theme->setTitle('雇佣详情');

        $data = $request->all();

        $employ = EmployModel::where('task_id',$id)->first();
        if(!$employ || ($employ && !in_array($this->user['id'],[$employ['employer_uid'],$employ['employee_uid']]))){
            return redirect()->back()->with(['error'=>'您访问的雇佣不存在！']);
        }
        //查询任务详情
        $detail = TaskModel::detail($id);
        //非法访问重定向
        if(!$detail || $detail['type_id'] != 3){
            return redirect()->back()->with(['error'=>'您访问的雇佣不存在！']);
        }
        //增加一次任务的访问次数
        TaskModel::where('id',$id)->increment('view_count',1);

        //查询任务的附件
        $attatchment_ids = TaskAttachmentModel::where('task_id',$id)->lists('attachment_id')->toArray();
        $attatchment_ids = array_flatten($attatchment_ids);
        $attatchment = AttachmentModel::whereIn('id',$attatchment_ids)->get()->toArray();
        $is_collect = 0;//是否收藏任务

        $is_delivery = 0;//是否交付

        $payCaseStatus = 0;//默认没有付款方式
        $paySectionStatus = 0;//默认没有待审核的阶段交付稿件
        $delivery_count = 0;//稿件数量
        $delivery = [];//交付记录

        $comment = [];//评价数组
        $comment_count = 0;

        $isContract = 0;
        $sort = 1;
        $sectionStatus = 0;
        $lastSort = 1;
        $isComment = 0;//当前登录人是否评价
        $isRight = 0;//当前登录人是否发起维权

        $focus = TaskFocusModel::where('uid',$this->user['id'])->where('task_id',$id)->first();
        if($focus){
            $is_collect = 1;
        }
        if($this->user['id'] == $employ['employee_uid']) {
            $user_type = 2;
        }else{
            $user_type = 1;
        }
        //查询雇佣人是否响应
        $isWork = WorkModel::where('task_id',$id)->where('uid',$employ['employee_uid'])->whereIn('status',[0,1])->with('childrenAttachment')->first();
        if($detail['status'] >= 4){
            $payCase = TaskPayTypeModel::where('task_id',$id)->first();
            if(!empty($payCase)){
                if(Auth::check() && $payCase['uid'] == Auth::id()){
                    $isContract = 1;
                }
                if($payCase['status'] == 1){
                    $payCaseStatus = 1;//协议签订
                }elseif($payCase['status'] == 2){
                    $payCaseStatus = 2;//协议被拒绝
                }else{
                    $payCaseStatus = 3;//协议待签订
                }
            }
        }
        if($detail['status'] >= 5){
            //查询是否有阶段交付待审核
            $paySection = TaskPaySectionModel::where('task_id',$id)->where('verify_status',0)->whereIn('section_status',[1,4])->first();
            if(!empty($paySection)){
                $sort = $paySection->sort;
                $paySectionStatus = 1;
            }
            $lastSection = TaskPaySectionModel::where('task_id',$id)->whereNotNull('work_id')->orderBy('id','desc')->first();
            if($lastSection){
                $sectionStatus = $lastSection->verify_status;
                $lastSort = $lastSection->sort;
            }
        }

        $deliveryList = [];
        if($detail['status'] >= 6){
            $deliveryList = WorkModel::findDelivery($id,[],5)->setPath('/kb/ajaxdeliveryList/'.$id);
            $delivery = $deliveryList->toArray();
            if(!empty($delivery['data'])){
                $paySectionWork = TaskPaySectionModel::where('task_id',$id)->where('work_id','!=','')->select('work_id','sort','desc','verify_status','section_status')->get()->toArray();
                if(!empty($paySectionWork)){
                    $paySectionWork = \CommonClass::setArrayKey($paySectionWork,'work_id');
                    foreach($delivery['data'] as $k => $v){
                        $delivery['data'][$k]['sort'] = in_array($v['id'],array_keys($paySectionWork)) ? $paySectionWork[$v['id']]['sort'] : 0;
                        $delivery['data'][$k]['pay_desc'] = in_array($v['id'],array_keys($paySectionWork)) ? $paySectionWork[$v['id']]['desc'] : 0;
                        $delivery['data'][$k]['verify_status'] = in_array($v['id'],array_keys($paySectionWork)) ? $paySectionWork[$v['id']]['verify_status'] : 0;
                        $delivery['data'][$k]['section_status'] = in_array($v['id'],array_keys($paySectionWork)) ? $paySectionWork[$v['id']]['section_status'] : 0;
                    }
                }
            }
            $delivery_count = $deliveryList->total();
        }
        if($detail['status'] >= 7){//维权
            //查询任务评价
            $comment = CommentModel::taskComment($id);
            $comment_count = count($comment);
            if(Auth::check()){
                $isComment = CommentModel::where('from_uid',Auth::id())->where('task_id',$id)->count();
            }
        }
        //维权信息
        $works_rights = TaskRightsModel::findRights(['task_id' => $id]);
        $works_rights_count = count($works_rights);
        if(Auth::check()){
            $isRight = TaskRightsModel::where('from_uid',Auth::id())->where('task_id',$id)->count();
        }
        $shopWorker = ShopModel::getShopByUid([$employ['employer_uid'],$employ['employee_uid']]);
        $shopWorker = \CommonClass::setArrayKey($shopWorker,'uid');
        //$shopUser = $shopWorker[$employ['employer_uid']];
        $bidWorker = $shopWorker[$employ['employee_uid']];
        $shopUser = UserDetailModel::getUserInfo($detail['uid'],true);

        $domain = \CommonClass::getDomain();

        //查看相关方案
        $likeGoods = GoodsModel::findByCate($detail['field_id']);

        /*$ad = AdTargetModel::getAdByTypeId(3);
        AdTargetModel::addViewCountByCode('TASKDETAIL');*/
        $ad = AdTargetModel::getAdByCodePage('TASKDETAIL');
        //文件交付协议
        $agree = AgreementModel::where('code_name','task_delivery')->first();
        $rightType = [
            1 => '违规信息',
            2 => '虚假交稿',
            3 => '涉嫌抄袭',
            4 => '其他',
        ];
        $view = [
            'merge'                 => $data,
            'detail'                => $detail,

            'shopUser'              => $shopUser,
            'shopWorker'            => $shopWorker,
            'bidWorker'             => $bidWorker,
            'domain'                => $domain,
            'attatchment'           => $attatchment,
            'user_type'             => $user_type,

            'isWork'                => $isWork,

            'isContract'            => $isContract,
            'payCaseStatus'         => $payCaseStatus,
            'pay_section'           => $paySectionStatus,
            'sort'                  => $sort,
            'sectionStatus'         => $sectionStatus,
            'lastSort'              => $lastSort,

            'is_delivery'           => $is_delivery,
            'deliveryList'          => $deliveryList,
            'delivery'              => $delivery,
            'delivery_count'        => $delivery_count,

            'comment'               => $comment,
            'comment_count'         => $comment_count,
            'isComment'             => $isComment,

            'isRight'               => $isRight,
            'works_rights'          => $works_rights,
            'works_rights_count'    => $works_rights_count,
            'rightType'             => $rightType,

            'is_collect'            => $is_collect,
            'likeGoods'             => $likeGoods,

            'ad'                    => $ad,
            'agree'                 => $agree
        ];
        $fieldName = isset($detail['field']['name']) ? $detail['field']['name'] : '';
        $skillName = isset($detail['skill']['name']) ? $detail['skill']['name'] : '';
        $this->theme->setTitle($detail['title']);
        $this->theme->set('keywords',$detail['title'].'、'.$fieldName.'、'.$skillName);
        $this->theme->set('description',mb_substr(strip_tags($detail['desc']),0,200,'utf-8'));
        return $this->theme->scope('employ.workin', $view)->render();
    }

}
