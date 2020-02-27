<?php
namespace App\Modules\Task\Http\Controllers;

use App\Http\Controllers\IndexController as BasicIndexController;
use App\Http\Requests;
use App\Modules\Manage\Model\AgreementModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\SeoModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Http\Requests\BountyRequest;
use App\Modules\Task\Http\Requests\TaskRequest;
use App\Modules\Task\Model\ServiceModel;
use App\Modules\Task\Model\TaskAgentModel;
use App\Modules\Task\Model\TaskAttachmentModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskPublishingModel;
use App\Modules\Task\Model\TaskServiceModel;
use App\Modules\Task\Model\TaskTemplateModel;
use App\Modules\Task\Model\TaskFocusModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserVipConfigModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Theme;
use QrCode;
use App\Modules\Advertisement\Model\AdTargetModel;
use App\Modules\Advertisement\Model\RecommendModel;
use Cache;
use Omnipay;

class IndexController extends BasicIndexController
{
    public function __construct()
    {
        parent::__construct();
        $this->user = Auth::user();
        $this->initTheme('fastpackage');
    }

    /**
     * 快包项目页面
     * @param Request $request
     * @return mixed
     */
    public function tasks(Request $request)
    {
        $this->initTheme('shop');
        $merge = $request->all();
        /*屏蔽异常访问*/
        if(isset($merge['hmsr']) || isset($merge['hmpl'])){
            exit('请稍后访问！');
        }
        //seo配置信息
        $seoConfig = ConfigModel::getConfigByType('seo');
        if(!empty($seoConfig['seo_task']) && is_array($seoConfig['seo_task'])){
            $this->theme->setTitle($seoConfig['seo_task']['title']);
            $this->theme->set('keywords',$seoConfig['seo_task']['keywords']);
            $this->theme->set('description',$seoConfig['seo_task']['description']);
        }else{
            $this->theme->setTitle('快包项目');
        }
        //任务推荐
        $taskRecommendList = RecommendModel::getRecommendByCode('TASK_LIST','task');
        $taskRecommendWindow = RecommendModel::getRecommendByCode('TASK_WINDOW','task');
        if($taskRecommendWindow){
            foreach($taskRecommendWindow as $key => $val){
                if((time()-strtotime($val['info']['created_at']))> 0 && (time()-strtotime($val['info']['created_at'])) < 3600){
                    $taskRecommendWindow[$key]['info']['show_publish'] = intval((time()-strtotime($val['created_at']))/60).'分钟前';
                }
                if((time()-strtotime($val['info']['created_at']))> 3600 && (time()-strtotime($val['info']['created_at'])) < 24*3600){
                    $taskRecommendWindow[$key]['info']['show_publish'] = intval((time()-strtotime($val['created_at']))/3600).'小时前';
                }
                if((time()-strtotime($val['info']['created_at']))> 24*3600){
                    $taskRecommendWindow[$key]['info']['show_publish'] = intval((time()-strtotime($val['created_at']))/(24*3600)).'天前';
                }
            }
        }
        $taskRecommendWindow = \CommonClass::arrOper($taskRecommendWindow,3);
        $merge['is_open'] = 1;
        $status = [
            1 => '竞标中',
            2 => '已选中',
            3 => '已托管',
            4 => '已完成'
        ];
        $taskBounty = [
            1 => [
                'name' => '个人',
                'min'  => 0,
                'max'  => 10000,
            ],
            2 => [
                'name' => '团队',
                'min'  => 10000,
                'max'  => 30000,
            ],
            3 => [
                'name' => '企业',
                'min'  => 30000,
                'max'  => 0,
            ],
        ];
        $order = [
            'created_at'     => '发布时间',
            'delivery_count' => '竞标数',
            'view_count'     => '人气',
            'bounty'         => '预算',
        ];
        //任务列表
        $taskList = TaskModel::getTaskList(15,$merge,$status,$taskBounty);
        if(!empty($taskList->toArray()['data'])){
            foreach($taskList as $key => $val){
                if((time()-strtotime($val['created_at']))> 0 && (time()-strtotime($val['created_at'])) < 3600){
                    $val->show_publish = intval((time()-strtotime($val['created_at']))/60).'分钟前';
                }
                if((time()-strtotime($val['created_at']))> 3600 && (time()-strtotime($val['created_at'])) < 24*3600){
                    $val->show_publish = intval((time()-strtotime($val['created_at']))/3600).'小时前';
                }
                if((time()-strtotime($val['created_at']))> 24*3600){
                    $val->show_publish = intval((time()-strtotime($val['created_at']))/(24*3600)).'天前';
                }
            }
        }
        $hotGoods = GoodsModel::where('is_delete',0)->with('cover','field','user')->orderBy('view_num','desc')->limit(4)->get()->toArray();
        $fieldArr = TaskCateModel::where('type',1)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        $skillArr = TaskCateModel::where('type',2)->select('id','name')->orderBy('sort','desc')->get()->toArray();
        if (isset($merge['district'])) {
            $pid = DistrictModel::find($merge['district']);
            if($pid){
                $area_pid = $pid->upid;
            }else{
                $area_pid = 0;
            }
            if($area_pid == 0){
                $area_data = DistrictModel::findTree(intval($merge['district']));
            }else{
                $area_data = DistrictModel::findTree($area_pid);
            }
        } else {
            $area_data = DistrictModel::findTree(0);
            $area_pid = 0;
        }
        //今日发布方案
        $todayGoods = TaskModel::where('created_at','>=',date('Y-m-d 00:00:00'))->where('created_at','<=',date('Y-m-d H:i:s'))->count();
        $todayGoods = $todayGoods + 10;
        //总发布方案
        $totalGoods = TaskModel::whereRaw('1=1')->count();
        /*$ad = AdTargetModel::getAdByTypeId(3);
        AdTargetModel::addViewCountByCode('TASK');*/
        $ad = AdTargetModel::getAdByCodePage('TASK');

        //选中动态
        $bidTask = WorkModel::select('task.title','task.id','work.uid')
            ->where('work.status',1)->where('task.type_id',1)
            ->where('task.is_open',1)->where('is_del',0)
            ->whereIn('task.status',[2,4,5,6,7,8,9])
            ->leftJoin('task','task.id','=','work.task_id')
            ->with('user')->orderBy('work.created_at','desc')
            ->limit(20)->get()->toArray();
        //获取热门seo 标签
        $seoLabel=SeoModel::orderBy("view_num","desc")->limit(8)->get();
        $view = [
            'taskRecommendList'   => $taskRecommendList,
            'taskRecommendWindow' => $taskRecommendWindow,
            'bidTask'             => $bidTask,
            'list'                => $taskList,
            'merge'               => $merge,
            'hotGoods'            => $hotGoods,
            'fieldArr'            => $fieldArr,
            'skillArr'            => $skillArr,
            'status'              => $status,
            'taskBounty'          => $taskBounty,
            'order'               => $order,
            'area_data'           => $area_data,
            'area_pid'            => $area_pid,
            'ad'                  => $ad,
            'todayGoods'          => $todayGoods,
            'totalGoods'          => $totalGoods,
            'page'                => $request->get("page") ? $request->get("page"):0,
            'seoLabel'            => $seoLabel,
        ];

        $this->theme->set('nav_url', '/kb');
        return $this->theme->scope('task.tasks', $view)->render();
    }

    /**
     * 文件上传控制
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fileUpload(Request $request)
    {
        $file = $request->file('file');
        $relation_type = $request->get('relation_type');
        $result = array();
        //判断关联对象是否存在
        if(!$relation_type){
            $result['status'] = 'failure';
            $result['message'] = '非法操作';
            return $result;
        }
        //判断文件是否上传成功
        $result1 = json_decode(\FileClass::uploadFile($file,$relation_type),true);
        
        if(!is_array($result1['data']) || $result1['code'] != 200) {
            $result['status'] = 'failure';
            $result['message'] = $result1['message'];
            return $result;
        }

        $attachment = AttachmentModel::create([
            'name'       => $result1['data']['name'],
            'type'       => $result1['data']['type'],
            'size'       => $result1['data']['size'],
            'url'        => $result1['data']['url'],
            'disk'       => $result1['data']['disk'],
            'user_id'    => $result1['data']['user_id'],
            'created_at' => date('Y-m-d H:i:s')
        ]);
        $data = $result1['data'];
        $data['id'] = $attachment['id'];
        $html = AttachmentModel::getTaskAttachmentHtml($data);//生成html
        $result['data'] = $data;
        $result['html'] = $html;
        $result['status'] = 'success';
        $result['code']=200;
        return $result;
    }

    /**
     * 附件删除
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function fileDelet(Request $request)
    {
        $id = $request->get('id');
        //查询当前的附件
        $file = AttachmentModel::where('id',$id)->first()->toArray();
        if(!$file)
        {
            return response()->json(['errCode' => 0, 'errMsg' => '附件没有上传成功！']);
        }
        //删除附件
        if(is_file($file['url']))
            unlink($file['url']);
        $result = AttachmentModel::destroy($id);
        if (!$result) {
            return response()->json(['errCode' => 0, 'errMsg' => '删除失败！']);
        }
        return response()->json(['errCode' => 1, 'errMsg' => '删除成功！']);
    }


    /**
     * 任务发布页面
     * @return mixed
     */
    public function create(Request $request)
    {
        $is_support=(isset($request->is_support)&& $request->is_support==2)?$request->is_support:'';
        $this->theme->setTitle('发布任务');
        //发布任务需要手机认证
        $user = UserModel::where('id', Auth::id())->first();
        if (empty($user['mobile'])){
                return redirect('user/phoneAuth')->with(["message"=>"你还未做手机认证,请先认证发布任务"]);
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

        //查询增值服务
        $service = ServiceModel::where('status',1)->where('type',1)->get()->toArray();
        //增值服务折扣
        $vipConfig = UserVipConfigModel::getConfigByUid(Auth::id());
        /*$ad = AdTargetModel::getAdByTypeId(3);
        AdTargetModel::addViewCountByCode('CREATE_TASK');*/
        $ad = AdTargetModel::getAdByCodePage('CREATE_TASK');
        $view = [
            'templateFiled'  => $templateFiled,
            'fieldArr'       => $fieldArr,
            'province'       => $province,
            'city'           => $city,
            'platesNum'      => $platesNum,
            'plateThick'     => $plateThick,
            'copperThickne'  => $copperThickne,
            'platingType'    => $platingType,
            'solderColor'    => $solderColor,
            'characterColor' => $characterColor,
            'deliveryCycle'  => $deliveryCycle,
            'service'        => $service,
            'vipConfig'      => $vipConfig,
            'ad'             => $ad,
            'is_support'             => $is_support,
        ];

        return $this->theme->scope('task.create', $view)->render();
    }

    /**
     * 任务提交，创建一个新任务
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createTask(Request $request)
    {
        $data = $request->except('_token');
        if(isset($data['edit_type']) && $data['edit_type'] == 1){
            unset($data['task_id']);
            unset($data['edit_type']);
        }
        $data['uid'] = $this->user['id'];
        // $data['desc'] = \CommonClass::removeXss($data['description']);
        $data['desc'] = \CommonClass::removeXss($data['desc']);
        $data['begin_at'] = date('Y-m-d H:i:s');
        $data['delivery_deadline'] = preg_replace('/([\x80-\xff]*)/i', '', $data['delivery_deadline']);
        $data['begin_at'] = date('Y-m-d H:i:s', strtotime($data['begin_at']));
        $data['delivery_deadline'] = date('Y-m-d H:i:s', strtotime($data['delivery_deadline']));
        $data['show_cash'] = $data['bounty'];
        $data['created_at'] = date('Y-m-d H:i:s');
        //发布和暂不发布切换
        $controller = '';
        if ($data['type'] == 1) {
            $data['status'] = 1;
            if(!empty($data['product'])){
                $controller = 'buyServiceTask';//购买增值服务要先支付
            }else{
                $controller = 'tasksuccess';
            }

        } elseif ($data['type'] == 2) {
            $data['status'] = 0;
        }
		$result = TaskModel::createTask($data);
        if (!$result) {
            return redirect()->back()->with('error', '创建任务失败！');
        }

        if($data['type']== 2){
            return redirect()->to('user/releaseTask?status=99');
        }
        return redirect()->to('kb/' . $controller . '/' . $result['id']);
    }


    /**
     * 成功发布任务
     */
    public function taskSuccess($id)
    {
        $this->theme->setTitle('发布项目');
        $id = intval($id);
        //验证任务是否是状态2
        $task = TaskModel::where('id',$id)->first();

        if($task['status']!=1){
            return redirect()->back()->with(['error'=>'数据错误，当前任务不处于等待审核状态！']);
        }
        $view = [
            'id' => $id,
        ];

        return $this->theme->scope('task.tasksuccess',$view)->render();
    }


    /**
     * ajax获取城市、地区数据
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxcity(Request $request)
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
    public function ajaxarea(Request $request)
    {
        $id = intval($request->get('id'));
        if (!$id) {
            return response()->json(['errMsg' => '参数错误！']);
        }
        $area = DistrictModel::findTree($id);
        return response()->json($area);
    }

    /**
     * 用户中心发布任务（暂不发布任务）
     * @param $id
     * @return mixed
     */
    public function release($id,Request $request)
    {
        $this->theme->setTitle('发布任务');
        $editType = $request->get('type') ? $request->get('type') : 0;
        //查询任务数据
        $task = TaskModel::where('id', $id)->first();
        if(!$task) {
            return redirect()->to('user/unreleasedTasks')->with(['error'=>'非法操作！']);
        }
        //任务的附件
        $taskAttachment = TaskAttachmentModel::where('task_id', $id)->lists('attachment_id')->toArray();
        $taskAttachmentData = AttachmentModel::whereIn('id', $taskAttachment)->get();
        //任务生产代工
        $taskAgent = TaskAgentModel::where('task_id',$id)->first();
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

        //查询增值服务
        $service = ServiceModel::where('status',1)->where('type',1)->get()->toArray();
        //增值服务折扣
        $vipConfig = UserVipConfigModel::getConfigByUid(Auth::id());
        $serviceId = [];
        if($editType == 0){
            $serviceId = TaskServiceModel::where('task_id',$id)->lists('service_id')->toArray();
        }
        /*$ad = AdTargetModel::getAdByTypeId(3);
        AdTargetModel::addViewCountByCode('CREATE_TASK');*/
        $ad = AdTargetModel::getAdByCodePage('CREATE_TASK');
        $view = [
            'task'           => $task,
            'attachmentData' => $taskAttachmentData,
            'taskAgent'      => $taskAgent,
            'editType'       => $editType,
            'templateFiled'  => $templateFiled,
            'fieldArr'       => $fieldArr,
            'province'       => $province,
            'city'           => $city,
            'platesNum'      => $platesNum,
            'plateThick'     => $plateThick,
            'copperThickne'  => $copperThickne,
            'platingType'    => $platingType,
            'solderColor'    => $solderColor,
            'characterColor' => $characterColor,
            'deliveryCycle'  => $deliveryCycle,
            'serviceId'      => $serviceId,
            'service'        => $service,
            'vipConfig'      => $vipConfig,
            'ad'             => $ad,
        ];

        return $this->theme->scope('task.release', $view)->render();
    }


    /**
     * 收藏或取消收藏任务
     * @param Request $request
     * @return mixed
     */
    public function postCollectionTask(Request $request)
    {
        //获取当前登录用户的id
        $userId = $this->user['id'];
        if(!empty($userId)){
            $taskId = $request->get('task_id');
            $type = $request->get('type');
            switch($type){
                //收藏
                case 0 :
                    //查询任务是否已经收藏
                    $focus = TaskFocusModel::where('uid',$userId)->where('task_id',$taskId)->first();
                    if($focus) {
                        $data = array(
                            'code' => 2,
                            'msg' => '该任务已经收藏过'
                        );
                    }else{
                        $focusArr = array(
                            'uid' => $userId,
                            'task_id' => $taskId,
                            'created_at' => date('Y-m-d H:i:s', time())
                        );
                        $task = TaskModel::find($taskId);
                        if($task['type_id'] == 1){
                            $focusArr['type'] = 1;
                        }else{
                            $focusArr['type'] = 2;
                        }
                        $res = TaskFocusModel::create($focusArr);
                        if ($res) {
                            $data = array(
                                'code' => 1,
                                'msg' => '收藏成功'
                            );

                        } else {
                            $data = array(
                                'code' => 2,
                                'msg' => '收藏失败'
                            );
                        }
                    }
                    break;
                //取消收藏
                case 1 :
                    //查询任务是否已经收藏
                    $focus = TaskFocusModel::where('uid',$userId)->where('task_id',$taskId)->first();
                    if(empty($focus)) {
                        $data = array(
                            'code' => 2,
                            'msg' => '该任务已经取消收藏'
                        );
                    }else{
                        $res = TaskFocusModel::where('uid',$userId)->where('task_id',$taskId)->delete();
                        if ($res) {
                            $data = array(
                                'code' => 1,
                                'msg' => '取消成功'
                            );

                        } else {
                            $data = array(
                                'code' => 2,
                                'msg' => '取消失败'
                            );
                        }
                    }
                    break;
            }
        }else{
            $data = array(
                'code' => 0,
                'msg' => '没有登录，不能收藏'
            );
        }
        return response()->json($data);
    }

    /**
     * 一键发布 获取领域
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function ajaxField(Request $request)
    {
        $arr = TaskCateModel::where('type',1)->select('id','name')->get()->toArray();
        $html = '';
        if(!empty($arr)){
            foreach($arr as $k => $v){
                $html = $html.'<option value="'.$v['id'].'">'.$v['name'].'</option>';
            }
        }
        $data = [
            'html' => $html
        ];
        return response()->json($data);
    }

    public function sendTaskCode(Request $request)
    {
        $arr = $request->all();
        if(Auth::check()){
            $user = UserModel::where('mobile',$arr['mobile'])->where('id','!=',Auth::id())->first();
            if($user){
                return ['code' => 1001, 'msg' => '手机号已占用'];
            }
        }

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
        //$status['success'] =  true;
        if ($status['success'] == true) {
            $data = [
                'code'   => $code,
                'mobile' => $arr['mobile']
            ];
            Session::put('task_mobile_info', $data);
            return ['code' => 1000, 'msg' => '短信发送成功'/*,'data' => $code*/];
        } else {
            return ['code' => 1001, 'msg' => '短信发送失败'];
        }

    }

    /**
     * 快速发布任务
     * @param Request $request
     * @return array
     */
    public function fastPub(Request $request)
    {
        $data = $request->all();
        $taskMobileInfo = session('task_mobile_info');
        if(Auth::check()){
            $uid = Auth::id();
            $username = Auth::user()->name;
            if(isset($data['code'])){
                if ($data['code'] == $taskMobileInfo['code'] && $data['phone'] == $taskMobileInfo['mobile']) {
                    Session::forget('task_mobile_info');
                }else{
                    return $arr = [
                        'code' => 0,
                        'msg'  => '验证码错误'
                    ];
                }
            }

        }else{

            if ($data['code'] == $taskMobileInfo['code'] && $data['phone'] == $taskMobileInfo['mobile']) {
                Session::forget('task_mobile_info');
                //查询用户是否存在
                $user = UserModel::where('mobile',$data['phone'])->first();
                if($user){
                    $username = $user['name'];
                    $uid = $user['id'];
                }else{
                    $username = \CommonClass::random(2).$data['phone'];
                    $userInfo = [
                        'username' => $username,
                        'mobile'   => $data['phone'],
                        'password' => $data['phone']
                    ];
                    $uid = UserModel::mobileInitUser($userInfo);
                    //发送通知短信
                    $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                    $templateId = ConfigModel::phpSmsConfig('sendUserPassword');
                    $templates = [
                        $scheme => $templateId,
                    ];
                    $tempData = [
                        'mobile'   => $data['phone'],
                        'password' => $data['phone'],
                        'website'  => $this->theme->get('site_config')['site_url']
                    ];

                    \SmsClass::sendSms($data['phone'], $templates, $tempData);

                }
            }else{
                return $arr = [
                    'code' => 0,
                    'msg'  => '验证码错误'
                ];
            }

        }

        $arrData = [
            'demand_number' => \CommonClass::createNum('YJ',4),
            'nickname'      => $username,
            'uid'           => $uid,
            'cate_id'       => $data['field_id'],
            'title'         => $data['title'],
            'content'       => $data['desc'],
            'mobile'        => isset($data['phone']) ? $data['phone'] : '',
            'status'        => 1,//待审核
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ];
        $res = TaskPublishingModel::create($arrData);
        if($res){
            return $arr = [
                'code' => 1,
                'msg'  => '发布成功'
            ];
        }else{
            return $arr = [
                'code' => 0,
                'msg'  => '发布失败'
            ];
        }
    }
    public function fastPubPhone(Request $request)
    {
        $data = $request->all();
        if(!empty($data['mobile'])){
            $data['phone'] = $data['mobile'];
        }
        $taskMobileInfo = session('task_mobile_info');
        if(Auth::check()){
            $uid = Auth::id();
            $username = Auth::user()->name;
            if(isset($data['code'])){
                if ($data['code'] == $taskMobileInfo['code'] && $data['phone'] == $taskMobileInfo['mobile']) {
                    Session::forget('task_mobile_info');
                }else{
                    return back()->with(['message'=>"验证码错误"]);
                }
            }

        }else{
            if ($data['code'] == $taskMobileInfo['code'] && $data['phone'] == $taskMobileInfo['mobile']) {
                Session::forget('task_mobile_info');
                //查询用户是否存在
                $user = UserModel::where('mobile',$data['phone'])->first();
                if($user){
                    $username = $user['name'];
                    $uid = $user['id'];
                }else{
                    $username = \CommonClass::random(2).$data['phone'];
                    $userInfo = [
                        'username' => $username,
                        'mobile'   => $data['phone'],
                        'password' => $data['phone']
                    ];
                    $uid = UserModel::mobileInitUser($userInfo);
                    //发送通知短信
                    $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                    $templateId = ConfigModel::phpSmsConfig('sendUserPassword');
                    $templates = [
                        $scheme => $templateId,
                    ];
                    $tempData = [
                        'mobile'   => $data['phone'],
                        'password' => $data['phone'],
                        'website'  => $this->theme->get('site_config')['site_url']
                    ];

                    \SmsClass::sendSms($data['phone'], $templates, $tempData);

                }
            }else{
                return back()->with(['message'=>"验证码错误"]);
            }

        }
        $arrData = [
            'demand_number' => \CommonClass::createNum('YJ',4),
            'nickname'      => $username,
            'uid'           => $uid,
            'cate_id'       => isset($data['field_id'])?$data['field_id']:'',
            'title'         => isset($data['title'])?$data['title']:mb_substr($data['desc'],0,20),
            'content'       => $data['desc'],
            'mobile'        => isset($data['phone']) ? $data['phone'] : '',
            'status'        => 1,//待审核
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ];
        $res = TaskPublishingModel::create($arrData);
        if($res){
            return back()->with(['message'=>"发布成功"]);
        }else{
            return back()->with(['message'=>"发布失败"]);
        }
    }
    /**
     * 手机端任务提交，创建一个新任务
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function createTaskPhone(Request $request)
    {
        $data = $request->all();
        $taskMobileInfo = session('task_mobile_info');
        if(isset($data['code'])){
            if ($data['code'] == $taskMobileInfo['code'] && $data['phone'] == $taskMobileInfo['mobile']) {
                Session::forget('task_mobile_info');
            }else{
                return back()->with(['message'=>"验证码错误"]);
            }
        }
        if($request->get('openid')){
            $user=UserModel::whereRaw("find_in_set(".$request->get('openid').",openid)")->first();
            $uid = $user['id'];
        }else{
            if(Auth::check()){
                $uid = Auth::id();
                $username = Auth::user()->name;
            }else{
                //查询用户是否存在
                $user = UserModel::where('mobile',$data['phone'])->first();
                if($user){
                    $username = $user['name'];
                    $uid = $user['id'];
                }else{
                    $username = \CommonClass::random(2).$data['phone'];
                    $userInfo = [
                        'username' => $username,
                        'mobile'   => $data['phone'],
                        'password' => $data['phone']
                    ];
                    $uid = UserModel::mobileInitUser($userInfo);
                    //发送通知短信
                    $scheme = ConfigModel::phpSmsConfig('phpsms_scheme');
                    $templateId = ConfigModel::phpSmsConfig('sendUserPassword');
                    $templates = [
                        $scheme => $templateId,
                    ];
                    $tempData = [
                        'mobile'   => $data['phone'],
                        'password' => $data['phone'],
                        'website'  => $this->theme->get('site_config')['site_url']
                    ];
                    \SmsClass::sendSms($data['phone'], $templates, $tempData);
                }

            }
        }
        $arrData = [
            'uid'      => $uid,
            'show_cash'     => $data['bounty'],
            'begin_at'      => date('Y-m-d H:i:s'),
            'created_at'    => date('Y-m-d H:i:s'),
            'delivery_deadline'    => date('Y-m-d',strtotime("+2 month")),
            'title'         =>$data['title'],
            'desc'          =>\CommonClass::removeXss($data['description']),
            'phone'         =>$data['phone'],
            'type_id'      =>1,
        ];
        //业务逻辑回滚处理
        $res=DB::transaction(function() use($arrData,$request){
            //任务的存储
            TaskModel::create($arrData);
            //储存openid
            if($request->get('openid')){
                $findOpenId=UserModel::where("id",$arrData['uid'])->pluck('openid');
                $openId=empty($findOpenId)?$request->get('openid'):$findOpenId.','.$request->get('openid');
                UserModel::where("id",$arrData['uid'])->update(['openid'=>$openId]);
            }
            return $arrData;
        });
        if($res){
            return redirect('/')->with(['message'=>"发布成功"]);
        }
        return back()->with(['message'=>"发布失败"]);

    }
    //推广落地页h5
    public function modelpacksend(Request $request){
//        $code=$request->get('code');
//        $accessToken=\CommonClass::sendGetRequest("https://api.weixin.qq.com/sns/oauth2/access_token?appid=wxdc99474586a23218&secret=41d64416ee0aa54954dec8c414b059c5&code={$code}&grant_type=authorization_code");
//        $accessToken=json_decode($accessToken);
//        $openid=$accessToken['openid'];
//        dd($accessToken);
        $this->initTheme('commonmodel');
        $this->theme->setTitle("专业电子软硬件开发外包平台-我爱方案网");
        $this->theme->set('keywords',"硬件开发、软件外包、我爱方案网");
        $this->theme->set('description',"我爱方案网提供软硬件开发外包与电子设计方案,平台汇聚优质的工程师与方案资源,一站式方案开发供应链平台!");
        //项目数量
        $taskCount = TaskModel::where('status','>',0)->count();
        return $this->theme->scope('task.modelpacksend',['taskCount'=>$taskCount])->render();
    }

    //快速发包的落地
    public function modelfastpack(){
        $this->initTheme('commonfast');
        $this->theme->setTitle("专业电子软硬件开发外包平台-我爱方案网");
        $this->theme->set('keywords',"硬件开发、软件外包、我爱方案网");
        $this->theme->set('description',"我爱方案网提供软硬件开发外包与电子设计方案,平台汇聚优质的工程师与方案资源,一站式方案开发供应链平台!");
        //服务商数量
        $shopCount = ShopModel::where('status',1)->count();
        return $this->theme->scope('task.modelfastpack',['shopCount'=>$shopCount])->render();
    }

}
