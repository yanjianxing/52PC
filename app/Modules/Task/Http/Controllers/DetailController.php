<?php
namespace App\Modules\Task\Http\Controllers;

use App\Http\Controllers\IndexController;
use App\Http\Requests;
use App\Modules\Manage\Model\AgreementModel;
use App\Modules\Manage\Model\ArticleModel;
use App\Modules\Manage\Model\MessageTemplateModel;
use App\Modules\Manage\Model\VipConfigModel;
use App\Modules\Manage\Model\VipModel;
use App\Modules\Manage\Model\ZdfastbuyModel;
use App\Modules\Shop\Models\GoodsModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\Task\Http\Requests\CommentRequest;
use App\Modules\Task\Http\Requests\WorkRequest;
use App\Modules\Task\Model\TaskAttachmentModel;
use App\Modules\Task\Model\TaskFeedbackModel;
use App\Modules\Task\Model\TaskFocusModel;
use App\Modules\Task\Model\TaskInviteModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskPaySectionModel;
use App\Modules\Task\Model\TaskPayTypeModel;
use App\Modules\Task\Model\TaskReportModel;
use App\Modules\Task\Model\TaskRightsModel;
use App\Modules\Task\Model\TaskServiceModel;
use App\Modules\Task\Model\TaskTypeModel;
use App\Modules\Task\Model\WorkCommentModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\CommentModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\EnterpriseAuthModel;
use App\Modules\User\Model\MessageReceiveModel;
use App\Modules\User\Model\RealnameAuthModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserVipCardModel;
use App\Modules\User\Model\UserVipConfigModel;
use App\Modules\Manage\Model\VipUserOrderModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use App\Modules\Advertisement\Model\AdTargetModel;
use App\Modules\Advertisement\Model\AdModel;
use App\Modules\Advertisement\Model\RePositionModel;
use App\Modules\Advertisement\Model\RecommendModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\SeoRelatedModel;
use Teepluss\Theme\Theme;


class DetailController extends IndexController
{
    public function __construct()
    {
        parent::__construct();
        $this->user = Auth::user();
        $this->initTheme('fastpackage');
    }


    /**
     * 任务详情
     * @param $id
     * @param Request $request
     * @return mixed
     */
	public function index($id,Request $request)
    {
        $this->theme->setTitle('任务详情');

        $data = $request->all();
        //查询任务详情
        $detail = TaskModel::detail($id);
        //非法访问重定向
        if(!$detail || $detail['type_id'] != 1){
            return redirect()->to('404');
        }
        //搜索引擎屏蔽
        if($detail['engine_status'] == 1){
            $this->theme->set('engine_status',1);
        }

        //增加一次任务的访问次数
        TaskModel::where('id',$id)->increment('view_count',1);

        //查询任务的附件
        $attatchment_ids = TaskAttachmentModel::where('task_id',$id)->lists('attachment_id')->toArray();
        $attatchment_ids = array_flatten($attatchment_ids);
        $attatchment = AttachmentModel::whereIn('id',$attatchment_ids)->get()->toArray();

        $is_collect = 0;//是否收藏任务
        $user_type = 3;//默认是游客 判断用户的类型是游客还是威客还是雇主
        $hasBid = 0;
        $hasBidWorker = [];

        $is_win_bid = 0;//默认投稿人没有中标
        $is_delivery = 0;//是否交付

        $works_count = 0;
        $works = [];//投稿记录
        $payCaseStatus = 0;//默认没有付款方式
        $paySectionStatus = 0;//默认没有待审核的阶段交付稿件
        $isContract = 0;
        $sort = 1;
        $lastSort = 1;
        $sectionStatus = 0;
        $delivery_count = 0;//稿件数量
        $delivery = [];//交付记录
        $works_rights_count = 0;//维权数量
        $works_rights = [];//维权稿件
        $shopList = [];//邀约高手
        $inviteUser = [];//已经邀约

        $comment = [];//评价数组
        $comment_count = 0;
        $isComment = 0;//当前登录人是否评价
        $isRight = 0;//当前登录人是否发起维权
        $isWork = WorkModel::where('task_id',$id)->whereIn('status',[0,1])->get()->toArray();
        $isWorkUser = \CommonClass::setArrayKey($isWork,'uid');
        if(Auth::check() && $detail['uid'] == $this->user['id']){
            $user_type = 1;
        }
        if(($detail['status'] == 1 || $detail['status'] == 3) && $user_type != 1){
            return redirect()->to('kb')->with(['error'=>'参数错误！']);
        }
        //判断当前状态是否需要区别三种角色,登陆之后的
        if($detail['status'] >= 2 && Auth::check()) {
            $focus = TaskFocusModel::where('uid',$this->user['id'])->where('task_id',$id)->first();
            if($focus){
                $is_collect = 1;
            }
            if(in_array($this->user['id'],array_keys($isWorkUser))) {
                $user_type = 2;
                if($detail['status'] >= 4 && $isWorkUser[$this->user['id']]['status'] > 0){
                    //判断用户投稿人是否入围
                    $is_win_bid = 1;
                }
            }
            //判断当前的角色是否是发布人,任务角色的优先级最高
            if($detail['uid'] == $this->user['id']) {
                $user_type = 1;
                if($detail['status'] == 2){
                    $shopList = ShopModel::getShopList(6,['task_uid' => $detail['uid'],'is_invite' => 1])->setPath('/kb/ajaxShopList/'.$id.'/'.$detail['uid']);
                    $inviteUser = TaskInviteModel::where('task_id',$id)->lists('uid')->toArray();
                }
                $isStatus = \CommonClass::setArrayKey($isWork,'status');
                if(in_array(1,array_keys($isStatus))){
                    $hasBid = 1;
                    $hasBidWorker = $isStatus[1];
                }
            }
        }
        if($detail['status'] >= 2){
            //查询投稿记录
            $works = WorkModel::findAll($id,5,$data)->setPath('/kb/ajaxWorksList/'.$id);
            $works_count = $works->total();
        }
        if(in_array($user_type,[1,2])){

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
                        $payCaseStatus = 3;
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
        }

        $deliveryList = [];
        if($detail['status'] >= 6){
            if($user_type == 1 || ($user_type == 2 && $is_win_bid == 1)) {
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
        }
        if($detail['status'] >= 7){//评价
            //查询任务评价
            $comment = CommentModel::taskComment($id);
            $comment_count = count($comment);
            if(Auth::check()){
                $isComment = CommentModel::where('from_uid',Auth::id())->where('task_id',$id)->count();
            }
        }
        if($user_type == 1 || ($user_type == 2 && $is_win_bid == 1)) {
            $works_rights = TaskRightsModel::findRights(['task_id' => $id]);
            $works_rights_count = count($works_rights);
            if(Auth::check()){
                $isRight = TaskRightsModel::where('from_uid',Auth::id())->whereIn('status',[0,1])->where('task_id',$id)->count();
            }
        }
        //雇主信息
        $shopUser = UserDetailModel::getUserInfo($detail['uid'],true);
        //.获取雇主选中任务数量
        $receive_task_nums=shopModel::where('uid',$detail['uid'])->pluck('receive_task_num');
        $shopUser['receive_task_nums']=isset($receive_task_nums)?$receive_task_nums:0;
        //.新增其它认证
        if(isset($shopUser)){
                $shopUser['mobile'] = UserModel::where('id', $detail['uid'])->pluck('mobile');//手机号认证
                $shopUser['email'] = UserModel::where('id', $detail['uid'])->pluck('email');//邮箱认证
                $shopUser['email_status'] = UserModel::where('id', $detail['uid'])->pluck('email_status');//邮箱状态
                $shopUser['level'] = UserModel::where('id', $detail['uid'])->pluck('level');//会员等级
        }

        $bidWorker = [];
        $workUid = array_pluck($isWork,'uid');
        $workUid = array_merge($workUid,[$detail['uid']]);
        $shopWorker = ShopModel::getShopByUid($workUid);
        //.新增其它认证
        if(isset($shopWorker)){
            foreach ($shopWorker as $k=>$v){
                    $shopWorker[$k]['mobile'] = UserModel::where('id', $v['uid'])->pluck('mobile');//手机号认证
                    $shopWorker[$k]['email'] = UserModel::where('id', $v['uid'])->pluck('email');//邮箱认证
                    $shopWorker[$k]['email_status'] = UserModel::where('id', $v['uid'])->pluck('email_status');//邮箱状态
                    $shopWorker[$k]['level'] = UserModel::where('id', $v['uid'])->pluck('level');//会员等级
            }
        }
        $shopWorker = \CommonClass::setArrayKey($shopWorker,'uid');

        if($hasBidWorker && isset($hasBidWorker['uid']) && in_array($hasBidWorker['uid'],array_keys($shopWorker))){
            $bidWorker = $shopWorker[$hasBidWorker['uid']];
        }
        $domain = \CommonClass::getDomain();


        /*项目详情获取最新中标 送高一级竞标卡*/
        $newbid = WorkModel::getDetailWorker();

        //查询相似类型的任务
        $likeTasks = TaskModel::findByCate($detail['field_id'],$id);
        //查看相关方案
        $likeGoods = GoodsModel::findByCate($detail['field_id']);

        //相关文章
        $aboutarticle = ArticleModel::select("article.*","cate.name")
            ->leftjoin("cate","article.cate_id","=","cate.id")
            ->where("status","1")
            ->where("article.cate_id",$detail['field_id'])
            ->where('article.id',"<>",$id)
            ->orderBy('article.id','desc')->limit(6)->get();

       /* $ad = AdTargetModel::getAdByTypeId(3);
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
        //.中间推荐元器件获取
        $zdfastbuglist1=ZdfastbuyModel::where('id','>','0')->where('show_location',1)->where('is_del','0')->select('id','url','aurl')->get();
        //.右边热门元器件获取
        $zdfastbuglist2=ZdfastbuyModel::where('id','>','0')->where('show_location',2)->where('is_del','0')->select('id','url','aurl')->get();
        //.当前用户是否进行企业认证或者是实名认证
        $enterprise_auth=EnterpriseAuthModel::where('uid',$this->user['id'])->where('status',1)->first();
        $realname_auth=RealnameAuthModel::where('uid',$this->user['id'])->where('status',1)->first();
        $view = [
            'merge'                 => $data,
            'detail'                => $detail,
            'inviteUser'            => $inviteUser,
            'shopList'              => $shopList,
            'shopUser'              => $shopUser,
            'shopWorker'            => $shopWorker,
            'bidWorker'             => $bidWorker,
            'domain'                => $domain,
            'attatchment'           => $attatchment,
            'user_type'             => $user_type,
            'hasBid'                => $hasBid,
            'is_win_bid'            => $is_win_bid,//是否中标
            'works_count'           => $works_count,
            'works'                 => $works,
            'newbid'                 => $newbid,

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

            'works_rights'          => $works_rights,
            'works_rights_count'    => $works_rights_count,
            'isRight'               => $isRight,
            'rightType'             => $rightType,

            'is_collect'            => $is_collect,
            'likeTasks'             => $likeTasks,
            'likeGoods'             => $likeGoods,
            'aboutarticle'          => $aboutarticle,

            'ad'                    => $ad,
            'agree'                 => $agree,
            'workUid'               => $workUid,
            'zdfastbuglist1'               => $zdfastbuglist1,
            'zdfastbuglist2'               => $zdfastbuglist2,
            'enterprise_auth'               => $enterprise_auth,
            'realname_auth'               => $realname_auth,
        ];
        $fieldName = isset($detail['field']['name']) ? $detail['field']['name'] : '';
        $skillName = isset($detail['skill']['name']) ? $detail['skill']['name'] : '';
        //seo配置信息
        $SeoRelated = SeoRelatedModel::where("related_id",$id)->where("type","1")->leftjoin("seo","seo.id","=","seo_related.seo_id")->lists('seo.name');
        $seorelatedname = '';
        if($SeoRelated){
           foreach ($SeoRelated as $key => $value) {
                $seorelatedname .= $value.'、';
            } 
        }
        $seoConfig = ConfigModel::getConfigByType('seo');
        if(!empty($seoConfig['seo_taskdetail']) && is_array($seoConfig['seo_taskdetail'])){
            $this->theme->setTitle($detail['title']." - ".$seoConfig['seo_taskdetail']['title']);
            $this->theme->set('keywords',$seorelatedname.$seoConfig['seo_taskdetail']['keywords'].'、'.$fieldName.'、'.$skillName);
        }else{
            $this->theme->setTitle($detail['title']);
            $this->theme->set('keywords',$detail['title'].'、'.$fieldName.'、'.$skillName);
        }
        $this->theme->set('description',mb_substr(strip_tags($detail['desc']),0,200,'utf-8'));
        return $this->theme->scope('task.detail', $view)->render();
    }

    /**
     * ajax获取竞标稿件
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function ajaxWorksList($id,Request $request)
    {
        $this->initTheme('ajaxpage');
        $detail = TaskModel::find($id);
        $works = WorkModel::findAll($id,5)->setPath('/kb/ajaxWorksList/'.$id);
        $workUid1 = WorkModel::where('task_id',$id)->lists('uid')->toArray();
        $workUid = array_merge($workUid1,[$detail['uid']]);
        $shopWorker = ShopModel::getShopByUid($workUid);
        //.新增其它认证
        if(isset($shopWorker)){
            foreach ($shopWorker as $k=>$v){
                $shopWorker[$k]['mobile'] = UserModel::where('id', $v['uid'])->pluck('mobile');//手机号认证
                $shopWorker[$k]['email'] = UserModel::where('id', $v['uid'])->pluck('email');//邮箱认证
                $shopWorker[$k]['email_status'] = UserModel::where('id', $v['uid'])->pluck('email_status');//邮箱状态
                $shopWorker[$k]['level'] = UserModel::where('id', $v['uid'])->pluck('level');//会员等级
            }
        }
        $shopWorker = \CommonClass::setArrayKey($shopWorker,'uid');
        $hasBid = 0;
        $user_type = 3;
        if($detail['uid'] == $this->user['id']) {
            $user_type = 1;
            $hasBidwork = WorkModel::where('task_id',$id)->where('status',1)->first();
            if($hasBidwork){
                $hasBid = 1;
            }
        }
        if(in_array($this->user['id'],$workUid1)){
            $user_type = 2;
        }
        $view = [
            'id'         => $id,
            'works'      => $works,
            'detail'     => $detail,
            'shopWorker' => $shopWorker,
            'hasBid'     => $hasBid,
            'user_type'  => $user_type,
            'workUid'    => $workUid
        ];
        return $this->theme->scope('task.ajaxworkslist', $view)->render();
    }

    /**
     * ajax获取交付稿件
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function ajaxDeliveryList($id,Request $request)
    {
        $this->initTheme('ajaxpage');
        $detail = TaskModel::find($id);
        $workUid1 = WorkModel::where('task_id',$id)->where('status',1)->lists('uid')->toArray();
        $workUid = array_merge($workUid1,[$detail['uid']]);
        $shopWorker = ShopModel::getShopByUid($workUid);
        //.新增其它认证
        if(isset($shopWorker)){
            foreach ($shopWorker as $k=>$v){
                $shopWorker[$k]['mobile'] = UserModel::where('id', $v['uid'])->pluck('mobile');//手机号认证
                $shopWorker[$k]['email'] = UserModel::where('id', $v['uid'])->pluck('email');//邮箱认证
                $shopWorker[$k]['email_status'] = UserModel::where('id', $v['uid'])->pluck('email_status');//邮箱状态
                $shopWorker[$k]['level'] = UserModel::where('id', $v['uid'])->pluck('level');//会员等级
            }
        }


        $shopWorker = \CommonClass::setArrayKey($shopWorker,'uid');
        $user_type = 2;
        if($detail['uid'] == $this->user['id']) {
            $user_type = 1;
        }

        $deliveryList = [];
        $delivery = [];
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
        };

        //文件交付协议
        $agree = AgreementModel::where('code_name','task_delivery')->first();
        $view = [
            'id'           => $id,
            'deliveryList' => $deliveryList,
            'delivery'     => $delivery,
            'detail'       => $detail,
            'shopWorker'   => $shopWorker,
            'user_type'    => $user_type,
            'agree'        => $agree
        ];
        return $this->theme->scope('task.ajaxdeliverylist', $view)->render();
    }


    /**
     * ajax获取邀约用户列表
     * @param Request $request
     * @return mixed
     */
    public function ajaxShopList(Request $request,$taskId,$uid)
    {
        $this->initTheme('ajaxpage');
        $shopList = ShopModel::getShopList(6,['task_uid' => $uid,'is_invite' => 1])->setPath('/kb/ajaxShopList/'.$taskId.'/'.$uid);
        $inviteUser = TaskInviteModel::where('task_id',$taskId)->lists('uid')->toArray();

        $view = [
            'shopList'    => $shopList,
            'inviteUser'  => $inviteUser,
            'taskId'      => $taskId,
        ];
        return $this->theme->scope('task.ajaxinviteuser', $view)->render();
    }

    /**
     * 邀请高手
     * @param Request $request
     * @return array
     */
    public function inviteUser(Request $request)
    {
        $taskId = $request->get('taskId');
        $uid = $request->get('uid');
        $task = TaskModel::find($taskId);
        if(!$task || $task->uid != $this->user['id'] || $task['status'] != 2){
            return $data = [
                'code' => 0,
                'msg'  => '不能邀约'
            ];
        }
        $res = TaskInviteModel::create([
            'task_id' => $taskId,
            'uid'     => $uid
        ]);
        if($res){
            //发送邀请信息
            TaskInviteModel::sendInviteMsg($task,$uid);
            return $data = [
                'code' => 1,
                'msg'  => '邀请成功'
            ];
        }else{
            return $data = [
                'code' => 0,
                'msg'  => '邀请失败'
            ];
        }
    }

    /**
     * ajax获取竞标信息
     */
    public function work($id)
    {
        
        //判断用户有没有进行手机认证
        if(empty(Auth::User()->mobile)){
             return $data = [
                'code' => 2,
                'msg'  => '请先<a href="/user/phoneAuth">请先进行手机认证并完善资料</a>再参与竞标'
            ];
        }
        $shop = ShopModel::where('uid',Auth::id())->first();  //->where('status',1)
        if(!$shop){
            return $data = [
                'code' => 3,
                'msg'  => '请先<a href="/user/shop">开通店铺</a>再参与竞标'
            ];
        }
        $task = TaskModel::find($id);
        
        //查询我的最高竞标金额和次数
        $vipConfig = UserVipConfigModel::getConfigByUid(Auth::id());
        $level = UserModel::where('id',Auth::id())->pluck('level');
        $levelTask = '普通会员';
        switch($level){
            case 1:
                $levelTask = '普通会员';
                break;
            case 2:
                $levelTask = '青铜会员';
                break;
            case 3:
                $levelTask = '白银会员';
                break;
            case 4:
                $levelTask = '黄金会员';
                break;
            case 5:
                $levelTask = '铂金会员';
                break;
            case 6:
                $levelTask = '王者会员';
                break;
        }
        $maxPrice = $vipConfig['bid_price'];
        if($task->is_free == 1){
            //查询vip是否过期
            // $vipstatus = VipUserOrderModel::getStatusByUid(Auth::id());
            // if(!$vipstatus){
            //     return $data = [
            //         'code' => 0,
            //         'msg'  => '您还没有购买vip或者vip已过期！请先购买vip'
            //     ];
            // }
            $card = UserVipCardModel::where('uid',Auth::id())->where('do_use','>',0)->where('max_price','>=',$task->bounty)->get()->toArray();
            //查询是否有大于bounty的次卡
            if($maxPrice < $task->bounty && empty($card)){
                $vipConfigId = VipConfigModel::where('jb_price','>=',$task->bounty)->lists('id')->toArray();
                $vip = VipModel::where('status',2)->whereIn('vipconfigid',$vipConfigId)->orderBy('grade','asc')->first();
                if($vip){
                    $name = $vip['name'];
                    return $data = [
                        'code' => 4, 
                        'configmsg'=> '该项目'.$name.'以上会员才能竞标<br>请：<a href="/user/vipPay/'.$vip['id'].'/vip" target="_blank">升级会员</a>|<a href="/user/vipPay/'.$vip['vipconfigid'].'/cika" target="_blank">购买次卡</a>',
                        'msg'  => '该项目'.$name.'以上会员才能竞标，请<a href="/user/myVip" target="_blank">升级'.$name.'或者购买'.$name.'单次体验卡</a>',
                        'vip_id' => $vip['id']
                    ];
                }else{
                    return $data = [
                        'code' => 0,
                        'msg'  => '您的会员等级不能竞标该项目,<a href="/user/myVip" target="_blank">去购买</a>'
                    ];
                }

            }
        }else{
            $card = UserVipCardModel::where('uid',Auth::id())->where('do_use','>',0)->get()->toArray();
        }
        //查询今日竞标次数
        $times = WorkModel::where('uid',Auth::id())->whereIn('status',[0,1])->where('created_at','>=',date('Y-m-d 00:00:00'))->where('created_at','<=',date('Y-m-d H:i:s'))->where('type',1)->count();

        $maxTimes = $vipConfig['bid_num'];
        $maxTimes = $maxTimes - $times;
        if($maxTimes == 0 && empty($card)){
            $vipConfigId = VipConfigModel::where('jb_price','>=',$task->bounty)->lists('id')->toArray();
            $vip = VipModel::where('status',2)->whereIn('vipconfigid',$vipConfigId)->orderBy('grade','asc')->first();
            if($vip){
                $name = $vip['name'];
                return $data = [
                    'code' => 0,
                    'msg'  => '该项目'.$name.'以上会员才能竞标，您今日的竞标次数已使用完毕,请<a href="/user/myVip">购买'.$name.'单次体验卡</a>'
                ];
            }else{
                return $data = [
                    'code' => 0,
                    'msg'  => '您今日的竞标次数已使用完毕,<a href="/user/myVip">去购买</a>'
                ];
            }

        }

        //验证是否可以立即参与投标
        $isAllowHours = WorkModel::isOkWork(Auth::id());
        $diffTime = (time()-strtotime($task->verified_at))/3600;
        if($isAllowHours > 0 && $diffTime<$isAllowHours){
            $hours = number_format($isAllowHours - $diffTime,2);
            return $data = [
                'code' => 0,
                'msg'  => '请您在'.$hours.'小时后竞标'
            ];
        }

        $html = '';
        if($card){
            foreach($card as $k => $v){
                if($v['type'] == 1){
                    $type = '次卡';
                }else{
                    $type = '日卡';
                }
                $checked = '';
                if((($task->is_free == 1 && $maxPrice < $task->bounty) || $maxTimes == 0) && $k == 0){
                    $checked = 'checked="checked"';
                }
                $html = $html.'<label for="inputEmail3" style="text-align:left;" class="col-sm-11 col-sm-offset-1 control-label"><input class="radioCart01" type="radio" name="card" '.$checked.' value="'.$v['id'].'" data-price="'.$v['max_price'].'">'.$v['card_name']/*.$type*/.'剩余次数 <span style="color:#004ea2">'.$v['do_use'].'</span> 次</label>';
            }
            if(!(($task->is_free == 1 && $maxPrice < $task->bounty) || $maxTimes == 0) ){
                $html = $html.'<label for="inputEmail3" style="text-align:left;" class="col-sm-11 col-sm-offset-1 control-label"><input class="radioCart01" type="radio" name="card" value="0" data-price="0">不使用次卡</label>';
            }
        }

        return $data = [
            'code'       => 1,
            'id'         => $id,
            'card'       => $card,
            'html'       => $html,
            'times'      => $maxTimes,
            'price'      => $maxPrice,
            'level_desc' => $levelTask,
            'is_free'    => $task->is_free,
        ];
    }

    /**
     * 竞标投稿
     * @param WorkRequest $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function workCreate(WorkRequest $request)
    {
        //$domain = \CommonClass::getDomain();
        $data = $request->except('_token');
        $data['desc'] = \CommonClass::removeXss($data['desc']);
        $data['uid'] = $this->user['id'];
        $data['created_at'] = date('Y-m-d H:i:s',time());
        $shop = ShopModel::where('uid',$data['uid'])->first();
        if($shop){
            $data['shop_id'] = $shop['id'];
        }
        //判断当前用户是否有资格投标
        $is_work_able = $this->isWorkAble($data['task_id']);
        //返回为何不能投标的原因
        if(!$is_work_able['able']) {
            return redirect()->back()->with('error',$is_work_able['errMsg']);
        }
        $task = TaskModel::find($data['task_id']);
        if($task->type_id == 1){
            if(isset($data['card']) && !empty($data['card'])){
                $data['type'] = 0;
                if($task->is_free > 0){
                    $card = UserVipCardModel::find($data['card']);
                    $maxPrice = $card['max_price'];
                    if($task['bounty'] > $maxPrice){
                        return redirect()->back()->with('error','竞标金额最高'.$maxPrice.'元');
                    }
                }
            }else{
                //计算今日竞标次数
                $times = WorkModel::where('uid',$data['uid'])->whereIn('status',[0,1])->where('created_at','>=',date('Y-m-d 00:00:00'))->where('created_at','<=',date('Y-m-d H:i:s'))->where('type',1)->count();
                $vipConfig = UserVipConfigModel::getConfigByUid(Auth::id());
                $maxTimes = $vipConfig['bid_num'];
                if($maxTimes <= $times){
                    return redirect()->back()->with('error','今日竞标次数已经使用完');
                }
                if($task->is_free > 0){
                    $maxPrice = $vipConfig['bid_price'];
                    //判断竞标金额是否符合
                    if($task['bounty'] > $maxPrice){
                        return redirect()->back()->with('error','竞标金额最高'.$maxPrice.'元');
                    }
                }
                $data['type'] = 1;
            }

        }
        if((strtotime($data['end_time']) - strtotime($data['start_time'])) < 3600*24){
            return redirect()->back()->with('error','工作周期至少一天！');
        }
        if($task->type_id == 1){
            $data['status'] = 0;
        }else{
            $data['status'] = 1;
        }
        //创建一个新的稿件
        $result = WorkModel::workCreate($data);

        if(!$result) return redirect()->back()->with('error','投稿失败！');

        UserModel::sendfreegrant($this->user['id'],3);//投稿成功自动发放
        $userInfo = UserModel::where('id',$task->uid)->first();
        $user = [
            'uid'    => $task->uid,
            'email'  => $userInfo->email,
            'mobile' => $userInfo->mobile
        ];
        $templateArr = [
            'username' => $userInfo->name,
            'title'    => $task->title
        ];
        if($task->type_id == 1){
            \MessageTemplateClass::sendMessage('task_delivery',$user,$templateArr,$templateArr);
            return redirect()->to('kb/'.$data['task_id']);
        }else{
            //接受雇佣
            \MessageTemplateClass::sendMessage('employ_work',$user,$templateArr,$templateArr);
            return redirect()->to('employ/workIn/'.$data['task_id']);
        }

    }

    /**
     * 雇主选TA
     * @param $work_id
     * @param $task_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function winBid($work_id,$task_id)
    {
        $data['task_id'] = $task_id;
        $data['work_id'] = $work_id;

        //检查当前选标的人是不是任务的发布者
        //查询任务的发布者
        $task_user = TaskModel::where('id',$task_id)->lists('uid');
        $task_title = TaskModel::where('id',$task_id)->lists('title');
        if($task_user[0] != $this->user['id']) {
            return redirect()->back()->with(['error'=>'非法操作,你不是任务的发布者不能选择中标人选！']);
        }

        //当前任务的入围人数统计
        $win_bid_num = WorkModel::where('task_id',$task_id)->where('status',1)->count();

        //判断当前是否可以选择中标
        if($win_bid_num == 0) {
            $result = WorkModel::winBid($data);
            if(!$result) return redirect()->back()->with(['error'=>'操作失败！']);
        }else{
            return redirect()->back()->with(['error'=>'已经选择，不能再次选择！']);
        }
        /*选中发送信息*/
        $info = WorkModel::find($work_id);

        /*
        * 累计选中自动发送
        */
        UserModel::sendFreeVipCoupon(Auth::user()->id,16);//.雇主选中自动发放
        UserModel::sendFreeVipCoupon($info->uid,13);//.服务商被选中自动发放
        

        $userInfo = UserModel::where('id',$info->uid)->first();
        $user = [
            'uid'    => $info->uid,
            'email'  => $userInfo->email,
            'mobile' => $userInfo->mobile
        ];
        $templateArr = [
            'username' => $userInfo->name,
            'title' => $task_title[0]
        ];
        \MessageTemplateClass::sendMessage('task_win',$user,$templateArr,$templateArr);
        /*end*/
        UserModel::sendfreegrant($task_user[0],4);//选中自动发放
        return redirect()->back()->with(['massage'=>'选稿成功！']);
    }

    /**
     * 确认协议
     * @param int $id 任务id
     * @return mixed
     */
    public function payType($id,Request $request)
    {
        //.删除协议的某一个阶段
        if($request->get('id')&& !empty($request->get('id'))){
            $res = TaskPaySectionModel::where('id',$request->get('id'))->delete();
            if($res){
                return redirect()->to('kb/payType/'.$id.'?pay_again=1')->with(['message' => '删除成功']);
            }else{
                return redirect()->to('kb/payType/'.$id.'?pay_again=1')->with(['message' => '删除失败']);
            }
        }
        $this->theme->setTitle('竞标付款方式');
        $task = TaskModel::find($id);
        if($request->get('pay_again') && $request->get('pay_again') == 1){
            $taskPayType = TaskPayTypeModel::where('task_id',$id)->where('status',2)->first();
        }else{
            $taskPayType = TaskPayTypeModel::where('task_id',$id)->first();
        }
        $attachment = [];
        $paySection = [];
        $counts='';
        if($taskPayType){
            $attachmentId = $taskPayType->attachment_id_str ? explode(',',$taskPayType->attachment_id_str) : [];
            if($attachmentId){
                $attachment = AttachmentModel::whereIn('id',$attachmentId)->get()->toArray();
            }
            $paySection = TaskPaySectionModel::where('task_id',$id)->orderBy('sort','asc')->get()->toArray();
            $counts=count($paySection);
        }

        $userType = 3;//默认是游客
        $isWinBid = WorkModel::isWinBid($id,$this->user['id']);
        if($isWinBid){
            $userType = 2;//威客
        }
        if($task['uid'] == $this->user['id']) {
            $userType = 1;
        }
        $work = WorkModel::where('task_id',$id)->where('status',1)->first();

        $workUid = WorkModel::where('status',1)->where('task_id',$id)->first()->uid;
        //.获取服务商的企业名称和真实姓名
        $fws=[];
        $fws['f_enterprise_auth']=EnterpriseAuthModel::where('uid',$workUid)->where('status',1)->pluck('company_name');
        $fws['f_realname_auth']=RealnameAuthModel::where('uid',$workUid)->where('status',1)->pluck('realname');
        $shopArr = ShopModel::whereIn('uid',[$task['uid'],$workUid])->select('shop_name','uid')->get()->toArray();
        $shopArr = \CommonClass::setArrayKey($shopArr,'uid');
        //雇主信息
        $shopUser = UserDetailModel::getUserInfo($task['uid']);
        //.获取雇主的企业名称和真实姓名
        $gz=[];
        $gz['g_enterprise_auth']=EnterpriseAuthModel::where('uid',$task['uid'])->where('status',1)->pluck('company_name');
        $gz['g_realname_auth']=RealnameAuthModel::where('uid',$task['uid'])->where('status',1)->pluck('realname');
        //查看相关方案
        $likeGoods = GoodsModel::findByCate($task['field_id']);

        /*$ad = AdTargetModel::getAdByTypeId(3);
        AdTargetModel::addViewCountByCode('TASKDETAIL');*/
        $ad = AdTargetModel::getAdByCodePage('TASKDETAIL');
        $view = [
            'task'          => $task,
            'uid'           => $this->user['id'],
            'workUid'       => $workUid,
            'shopArr'       => $shopArr,
            'pay_type'      => $taskPayType,
            'work'          => $work,
            'attachment'    => $attachment,
            'pay_section'   => $paySection,
            'shopUser'      => $shopUser,
            'likeGoods'     => $likeGoods,
            'user_type'     => $userType,
            'ad'            => $ad,
            'counts'            => $counts,
            'fws'            => $fws,
            'gz'            => $gz,
        ];
        return $this->theme->scope('task.payType',$view)->render();
    }

    /**
     * 保存协议
     * @param Request $request
     * @return mixed
     */
    public function postPayType(Request $request)
    {
        $data = $request->except('_token');
        $task = TaskModel::find($data['task_id']);
        $isWinBid = WorkModel::isWinBid($data['task_id'],$this->user['id']);

        if($task['uid'] != $this->user['id'] && !$isWinBid){
            return redirect()->to('/kb/'.$data['task_id'])->with(['message' => '没有权限']);
        }
        if(array_sum($data['price']) != $data['bounty']){
            return redirect()->back()->with(['message' => '项目金额设定错误']);
        }
        $isEx = TaskPayTypeModel::where('task_id',$data['task_id'])->whereIn('status',[0,1])->first();
        if($isEx){
            return redirect()->back()->with(['message' => '协议已存在']);
        }
        TaskPayTypeModel::where('task_id',$data['task_id'])->delete();
        TaskPaySectionModel::where('task_id',$data['task_id'])->delete();
        $data['uid'] = Auth::id();
        $status = TaskPayTypeModel::saveTaskPayType($data);
        if($status){
            if($task['uid'] == $this->user['id']){
                $from = '雇主';
                $work = WorkModel::where('task_id',$task->id)->where('status',1)->first();
                if($work){
                    $workUid = $work->uid;
                    $userInfo = UserModel::where('id',$workUid)->first();
                }
            }else{
                $from = '服务商';
                $userInfo = UserModel::where('id',$task->uid)->first();
            }
            if(isset($userInfo)){
                $user = [
                    'uid'    => $userInfo->id,
                    'email'  => $userInfo->email,
                    'mobile' => $userInfo->mobile
                ];
                $templateArr = [
                    'username' => $userInfo->name,
                    'from'     => $from,
                    'title'    => $task->title
                ];
                \MessageTemplateClass::sendMessage('task_pay_type',$user,$templateArr,$templateArr);
            }

            if($task['type_id'] == 1){
                return redirect()->to('kb/'.$data['task_id']);
            }else{
                return redirect()->to('employ/workIn/'.$data['task_id']);
            }
        }else{
            if($task['type_id'] == 1){
                return redirect()->to('kb/'.$data['task_id'])->with(['message' => '操作失败']);
            }else{
                return redirect()->to('employ/workIn/'.$data['task_id'])->with(['message' => '操作失败']);
            }
        }
    }

    /**
     * 是否同意协议
     * @param int $taskId 任务id
     * @param int $status 1:同意  2:不同意
     * @return \Illuminate\Http\RedirectResponse
     */
    public function checkPayType($taskId,$status)
    {
        $isWinBid = WorkModel::isWinBid($taskId,$this->user['id']);
        $task = TaskModel::find($taskId);
        if(!$isWinBid && $task['uid'] != $this->user['id']){
            return redirect()->to('/kb/'.$taskId)->with(['message' => '没有权限']);
        }
        $uid = $this->user['id'];
        $res = TaskPayTypeModel::checkTaskPayType($taskId,$status,$uid);

        if($task['uid'] == $this->user['id']){
            $from = '雇主';
            $text = '请您及时与雇主确认资金托管并准备开发工作';
            $work = WorkModel::where('task_id',$task->id)->where('status',1)->first();
            if($work){
                $workUid = $work->uid;
                $userInfo = UserModel::where('id',$workUid)->first();
            }
        }else{
            $from = '服务商';
            $text = '请您及时托管佣金';
            $userInfo = UserModel::where('id',$task->uid)->first();
        }
        if(isset($userInfo)){
            $user = [
                'uid'    => $userInfo->id,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username' => $userInfo->name,
                'from'     => $from,
                'title'    => $task->title,
            ];
            if($status == 1){
                $templateArr['text'] = $text;
                \MessageTemplateClass::sendMessage('task_pay_type_success',$user,$templateArr,$templateArr);
            }else{
                \MessageTemplateClass::sendMessage('task_pay_type_failure',$user,$templateArr,$templateArr);
            }

        }

        if($res){
            if($task['type_id'] == 1){
                return redirect()->to('kb/'.$taskId);
            }else{
                return redirect()->to('employ/workIn/'.$taskId);
            }
        }else{
            if($task['type_id'] == 1){
                return redirect()->to('kb/'.$taskId)->with(['message' => '操作失败']);
            }else{
                return redirect()->to('employ/workIn/'.$taskId)->with(['message' => '操作失败']);
            }
        }
    }

    /**
     * 雇主再次编辑协议
     * @param $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function payTypeAgain($id)
    {
        return redirect('/kb/payType/'.$id.'?pay_again=1');
    }


    /**
     * ajax获取交付阶段
     */
    public function delivery($id)
    {
        $sort = 1;
        $paySection = TaskPaySectionModel::where('task_id',$id)->orderby('sort','asc')->get()->toArray();
        if(!empty($paySection)){
            foreach($paySection as $k => $v){
                if((!empty($v['work_id']) && $v['verify_status'] == 2) || empty($v['work_id'])){
                    $sort = $v['sort'];
                    break;
                }
            }
        }

        return $data = [
            'id'         => $id,
            'sort'       => $sort,
            'num'        => count($paySection),
            'paySection' => $paySection,
        ];
    }

    /**
     * 交付稿件提交
     */
    public function deliverCreate(Request $request)
    {
        $data = $request->except('_token');
        $data['desc'] = \CommonClass::removeXss($data['delivery_desc']);
        $data['sort'] = $data['sort_delivery'];
        $data['uid'] = $this->user['id'];
        $data['status'] = 2;//表示用户交付
        $data['created_at'] = date('Y-m-d H:i:s',time());

        //判断数据合法性
        if(empty($data['task_id'])) {
            return redirect()->back()->with(['error'=>'交付失败']);
        }
        //判断当前用户是否有验收投稿资格
        if(!WorkModel::isWinBid($data['task_id'],$this->user['id'])) {
            return redirect()->back()->with('error','您的稿件没有中标不能通过交付！');
        }
        $shop = ShopModel::where('uid',$data['uid'])->first();
        if($shop){
            $data['shop_id'] = $shop['id'];
        }
        $result = WorkModel::delivery($data);
        if(!$result) return redirect()->back()->with('error','交付失败！');
        $task = TaskModel::where('id',$data['task_id'])->first();
        $userInfo1 =  UserModel::where('id',$data['uid'])->first();
        //通知雇主
        $userInfo = UserModel::where('id',$task->uid)->first();
        $user = [
            'uid'    => $userInfo->id,
            'email'  => $userInfo->email,
            'mobile' => $userInfo->mobile
        ];
        $templateArr = [
            'username'      => $userInfo->name,
            'employee_name' => $userInfo1->name,
            'title'         => $task->title,
            'sort'          => $data['sort']
        ];
        \MessageTemplateClass::sendMessage('agreement_documents',$user,$templateArr,$templateArr);

        if($task['type_id'] == 1){
            return redirect()->to('kb/'.$data['task_id']);
        }else{
            return redirect()->to('employ/workIn/'.$data['task_id']);
        }

    }

    /**
     * 交付稿件验收
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function bidWorkCheck(Request $request)
    {
        $data = $request->except('_token');
        $work_data = WorkModel::where('id',$data['work_id'])->first();
        $data['uid'] = $work_data['uid'];
        //验证用户是否是雇主
        if(!TaskModel::isEmployer($work_data['task_id'],$this->user['id']))
            return redirect()->back()->with(['error'=>'您不是雇主，您的操作有误！']);
        //验证当前稿件是否符合验收标准
        $paySection = TaskPaySectionModel::where('work_id',$data['work_id'])->first();
        if($work_data['status'] != 2 && $paySection['verify_status'] != 0){
            return redirect()->back()->with(['error'=>'当前稿件不具备验收资格！']);
        }
        $data['task_id'] = $work_data['task_id'];
        $shop = ShopModel::where('uid',$data['uid'])->first();
        if($shop){
            $data['shop_id'] = $shop['id'];
        }
        $result = WorkModel::bidWorkCheck($data);
        if(!$result) {
            return redirect()->back()->with(['error'=>'操作失败！']);
        }else{
            $task = TaskModel::find($data['task_id']);
            if($task['type_id'] == 1){
                return redirect()->to('kb/'.$data['task_id'])->with(['message'=>'操作成功！']);
            }else{
                return redirect()->to('employ/workIn/'.$data['task_id'])->with(['message'=>'操作成功！']);

            }
        }
    }

    /**
     * 任务维权
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function ajaxBidRights(Request $request)
    {
        $data = $request->except('_token');
        $data['desc'] = e($data['desc']);
        $data['status'] = 0;
        $data['created_at'] = date("Y-m-d H:i:s", time());
        //查询任务信息
        $task = TaskModel::where('id',$data['task_id'])->first();
        if($data['work_id'] == 0){
            $workArr = WorkModel::where('task_id',$data['task_id'])->get()->toArray();
            $workArr = \CommonClass::setArrayKey($workArr,'status');
            if(/*(!isset($workArr[1]) && !isset($workArr[2]) && !isset($workArr[3])) || */$task['uid']!=$this->user['id']){
                return redirect()->back()->with(['error'=>'不能维权']);
            }
            $data['role'] = 1;
            $data['from_uid']  = $this->user['id'];
            $data['to_uid'] = $workArr[1]['uid'];
        }else{
            $work = WorkModel::where('id',$data['work_id'])->first();
            if($work['status'] == 4){
                return redirect()->back()->with(['error'=>'当前稿件正在维权']);
            }
            //判断当前维权的人是否具有维权资格
            $is_checked = WorkModel::where('id',$data['work_id'])
                ->where('task_id',$data['task_id'])
                ->whereIn('status',[2,5,6])
                ->where('uid',$this->user['id'])
                ->first();
            //验证是否维权过
            if(!$is_checked && $task['uid']!=$this->user['id']){
                return redirect()->back()->with(['error'=>'你不具备维权资格！']);
            }
            //判断当前维权的是雇主还是威客
            if($is_checked){
                $data['role'] = 0;
                $data['from_uid'] = $this->user['id'];
                $data['to_uid'] = $task['uid'];
            }else if($task['uid'] == $this->user['id']){
                $data['role'] = 1;
                $data['from_uid']  = $this->user['id'];
                $data['to_uid'] = $work['uid'];
            }
        }

        $result = TaskRightsModel::bidRightCreate($data);

        if(!$result)
            return redirect()->back()->with(['error'=>'维权失败！']);
        //维权提交完成，发送系统消息
        $userInfo = UserModel::find($data['to_uid']);
        $userInfo1 = UserModel::find($data['from_uid']);
        $user = [
            'uid'    => $userInfo->id,
            'email'  => $userInfo->email,
            'mobile' => $userInfo->mobile
        ];
        $from = $data['role'] == 1 ? '雇主' : '服务商';
        $templateArr = [
            'username'      => $userInfo->name,
            'from'          => $from,
            'righter_name'  => $userInfo1->name,
            'title'         => $task->title
        ];
        \MessageTemplateClass::sendMessage('trading_rights',$user,$templateArr,$templateArr);
        $task = TaskModel::where('id',$data['task_id'])->first();
        if($task['type_id'] == 1){
            return redirect()->to('kb/'.$data['task_id'])->with(['error'=>'维权成功！']);
        }else{
            return redirect()->to('employ/workIn/'.$data['task_id'])->with(['error'=>'维权成功！']);

        }


    }

    /**
     * //.任务(项目)反馈
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function ajaxFeedback(Request $request,$data=[])
    {
        $uid='';
        $data = $request->except('_token');
        if(Auth::check()){
            $uid=$this->user['id'];
        }

        //.判断发布任务之人和反馈之人是否是同一个
        $task_id=isset($data['task_id'])?$data['task_id']:'';
        $task_uid=TaskModel::where('id',$task_id)->pluck('uid');
        if($uid==$task_uid){
            return redirect()->back()->with(['error'=>'亲爱的小主，禁止自己反馈自己哦！']);
        }

        //.判断当前用户是否竞标过当前项目(竞标过才有反馈资格)
        $bid=WorkModel::where('uid',$uid)->where('task_id',$task_id)->first();
        if(empty($bid)){
            return redirect()->back()->with(['error'=>'亲爱的小主，你尚未拥有反馈资格哦！']);
        }
        //.判断当前用户是否反馈过
        $data['uid']=isset($uid)?$uid:'';
        $feedback=TaskFeedbackModel::where('uid',$data['uid'])->where('task_id',$task_id)->pluck('id');
        if(isset($feedback)&&!empty($feedback)){
            return redirect()->back()->with(['error'=>'亲爱的小主，您已反馈过该项目，请勿重复反馈！']);
        }

        $res=TaskFeedbackModel::CreateFeedback($data);
        if(!$res){
            return redirect()->back()->with(['error'=>'反馈失败！']);
        }else{
            return redirect()->back()->with(['message'=>'反馈成功！']);
        }

    }



    /**
     * 评价页面
     */
    public function evaluate(Request $request,$id)
    {
        //判断当前评价的人是否具有评价资格
        $is_checked = WorkModel::where('task_id',$id)
            ->where('uid',$this->user['id'])
            ->where('status',3)->first();
        $task = TaskModel::where('id',$id)->first();

        if(!$is_checked && $task['uid']!=$this->user['id']) {
            return [
                'code' => 0,
                'msg'  => '你不具备评价资格',
            ];
        }
        $comment = CommentModel::where('task_id',$id)->where('from_uid',$this->user['id'])->count();
        if($comment > 0){
            return [
                'code' => 0,
                'msg'  => '已经评论完成',
            ];
        }
        return [
            'code' => 1,
            'msg'  => 'success',
            'data' => [
                'task_id' => $id
            ]
        ];
    }

    /**
     * 交易评论
     */
    public function evaluateCreate(Request $request)
    {
        $data = $request->except('token');
        //判断当前评价的人是否具有评价资格
        $is_checked = WorkModel::where('task_id',$data['task_id'])
            ->where('uid',$this->user['id'])
            ->where('status',3)->first();
        //查询雇主信息
        $task = TaskModel::where('id',$data['task_id'])->first();

        if(!$is_checked && $task['uid']!=$this->user['id']){
            return redirect()->back()->with('error','你不具备评价资格！');
        }
        //保存评论数据
        $data['from_uid'] = $this->user['id'];
        $data['comment'] = e($data['comment']);
        $data['created_at'] = date('Y-m-d H:i:s',time());
        //评论雇主
        if($is_checked) {
            $data['to_uid'] = $task['uid'];
            $data['comment_by'] = 0;
        }else if($task['uid']==$this->user['id']) {
            $work = WorkModel::where('task_id',$data['task_id'])->where('status',3)->first();
            $data['to_uid'] = $work['uid'];
            $data['comment_by'] = 1;
        }
        if($data['type'] == 1){
            $avg = round(($data['speed_score'] + $data['quality_score'] + $data['attitude_score'])/3,2);
        }else{
            $avg = round(($data['speed_score'] + $data['quality_score'])/2,2);
        }
        if($avg >=0 && $avg < 2){
            $data['type'] = 3;
        }elseif($avg >= 2 && $avg < 4){
            $data['type'] = 2;
        }else{
            $data['type'] = 1;
        }

        $is_evaluate =  CommentModel::where('from_uid',$this->user['id'])
            ->where('task_id',$data['task_id'])->where('to_uid',$data['to_uid'])
            ->first();

        if($is_evaluate){
            return redirect()->back()->with(['error'=>'你已经评论过了！']);
        }
        $result = CommentModel::commentCreate($data);

        if(!$result) {
            return redirect()->back()->with('error','评论失败！');
        }
        if($task['type_id'] == 1){
            return redirect()->to('kb/'.$data['task_id'])->with('massage','评论成功！');
        }else{
            return redirect()->to('employ/workIn/'.$data['task_id'])->with('massage','评论成功！');

        }
    }




    /**
     * 判断当前用户是否有投稿的资格,便于扩展
     */
    private function isWorkAble($task_id)
    {
        //判断当前任务是否处于投稿期间
        $task_data = TaskModel::where('id',$task_id)->first();
        if($task_data['status']!=(2) || strtotime($task_data['begin_at'])>time()) {
            return ['able' => false, 'errMsg' => '当前任务还未开始投稿！'];
        }
        //判断当前用户是否登录
        if (!isset($this->user['id'])) {
            return ['able' => false, 'errMsg' => '请登录后再操作！'];
        }
        //判断用户是否为当前任务的投稿人，如果已经是的，就不能投稿
        if (WorkModel::isWorker($this->user['id'], $task_id)) {
            return ['able' => false, 'errMsg' => '你已经投过稿了'];
        }
        //判断当前用户是否为任务的发布者，如果是用户的发布者，就不能投稿
        if (TaskModel::isEmployer($task_id, $this->user['id']))
        {
            return ['able' => false, 'errMsg'=>'你是任务发布者不能投稿！'];
        }
        return ['able'=>true];
    }



    /**
     * 下载附件
     * @param $id
     */
    public function download($id)
    {
        $pathToFile = AttachmentModel::where('id',$id)->first();
        $pathToFile = $pathToFile['url'];
        return response()->download($pathToFile);
    }

    /**
     * 登录特别跳转回原来页面
     * @param Request $request
     * @param $type
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Routing\Redirector
     */
    public function recordLogin(Request $request,$type)
    {
        if($type == 1){
            if($request->get('task_id')){
                return redirect('/kb/'.$request->get('task_id'));
            }
            return redirect('/kb');
        }elseif($type == 2){
            if($request->get('goods_id')){
                return redirect('/facs/'.$request->get('goods_id'));
            }
            return redirect('/facs');
        }
        return redirect('/');
    }
















}
