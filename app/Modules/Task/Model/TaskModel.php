<?php

namespace App\Modules\Task\Model;

use App\Modules\Advertisement\Model\RecommendModel;
use App\Modules\Employ\Models\EmployModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\MessageTemplateModel;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Manage\Model\UserCouponModel;
use App\Modules\Order\Model\OrderModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\UserCouponLogModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\Task\Model\TaskCateModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Log;


class TaskModel extends Model
{
    protected $table = 'task';
    protected $fillable = [
        'title',
        'numbers',
        'desc',
        'type_id',
        'field_id',
        'cate_id',
        'phone',
        'wechat',
        'qq',
        'email',
        'region_limit',
        'status',
        'bounty',
        'bounty_status',
        'created_at',
        'updated_at',
        'verified_at',
        'begin_at',
        'end_at',
        'delivery_deadline',//竞标截止时间
        'show_cash',
        'real_cash',
        'deposit_cash',
        'province',
        'city',
        'area',
        'view_count',
        'delivery_count',
        'uid',
        'username',
        'worker_num',
        'work_at',//第一次投标时间
        'selected_work_at',//选标时间
        'publicity_at',//托管赏金时间
        'checked_at',//验收期进入时间
        'comment_at',//双方互评开始
        'top_status',
        'task_success_draw_ratio',
        'task_fail_draw_ratio',
        'engine_status',
        'work_status',
        'from_to',
        'is_del',
        'is_recommend',
        'is_open',
        'transaction_id',
        'is_car',
        'car_cash',
        'is_top',
        'is_fast',
        'reason',
        'project_agent',
        'level',
        'is_free',
        'appreciationsource',
        'is_support',
    ];
    public function province()
    {
        return $this->hasOne('App\Modules\User\Model\DistrictModel','id','province');
    }
    public function city()
    {
        return $this->hasOne('App\Modules\User\Model\DistrictModel','id','city');
    }

    /**
     * 应用领域
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function field()
    {
        return $this->hasOne('App\Modules\Task\Model\TaskCateModel','id','field_id');
    }
    /**
     * 技术标签
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function skill()
    {
        return $this->hasOne('App\Modules\Task\Model\TaskCateModel','id','cate_id');
    }


    /**
     * 雇主信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function user()
    {
        return $this->hasOne('App\Modules\User\Model\UserModel','id','uid');
    }

    /**
     * 雇主更多信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function userinfo()
    {
        return $this->hasOne('App\Modules\User\Model\UserDetailModel','uid','uid');
    }

    /**
     * 最新跟进信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function follow()
    {
        return $this->hasOne('App\Modules\Task\Model\TaskFollowModel','task_id','id');
    }



    /**
     * 首页 方案超市
     * @param int $type 1：推荐任务 2：热门任务 3：最新任务 4：免费任务
     * @return array|bool|mixed
     */
    static public function getHomeTaskByType($type=1,$data = [])
    {
        $task = [];
        if(!in_array($type,[1,2,3,4])){
            return $task;
        }
        switch($type){
            case 1://推荐任务
                $task = RecommendModel::getRecommendByCode('HOME_TASK','task',$data);
                break;
            case 2://热门任务改为个人团队任务（3万以下）
                $task = self::where('type_id',1)->where('is_del',0)->where('is_open',1)->where('task.status','>=',2)->where('task.status','!=',3)->where('task.status','!=',10)->where('task.bounty','<=','30000');
                if(isset($data['field_id']) && !empty($data['field_id'])){
                    $task = $task->where('task.field_id',$data['field_id']);
                }
                $task = $task->leftJoin('cate','cate.id','=','task.field_id')->select('task.id','cate.name as task_field_name','task.view_count','task.bounty','task.title','task.is_free')
                    ->orderBy('task.created_at','desc')
                    ->limit(12)->get()->toArray();
                break;
            case 3://最新任务改为企业项目（3万以上）
                $task = self::where('type_id',1)->where('is_del',0)->where('is_open',1)
                    ->where('task.status','>=',2)->where('task.status','!=',3)->where('task.status','!=',10)->where('task.bounty','>=','30000');
                if(isset($data['field_id']) && !empty($data['field_id'])){
                    $task = $task->where('task.field_id',$data['field_id']);
                }
                $task = $task->leftJoin('cate','cate.id','=','task.field_id')->select('task.id','cate.name as task_field_name','task.view_count','task.bounty','task.title','task.is_free')
                    ->orderBy('task.created_at','desc')
                    ->limit(12)->get()->toArray();
                break;
            case 4://免费任务
                $task = self::where('type_id',1)->where('is_del',0)->where('is_open',1)
                    ->where('task.status','>=',2)->where('task.status','!=',3)->where('task.status','!=',10)
                    ->where('task.is_free',0);
                if(isset($data['field_id']) && !empty($data['field_id'])){
                    $task = $task->where('task.field_id',$data['field_id']);
                }
                $task = $task->leftJoin('cate','cate.id','=','task.field_id')->select('task.id','cate.name as task_field_name','task.view_count','task.bounty','task.title','task.is_free')
                    ->orderBy('task.created_at','desc')
                    ->limit(12)->get()->toArray();
                break;
        }
        return $task;
    }

    /**
     * 获取任务列表
     * @param $paginate
     * @param array $merge
     * @param array $status
     * @param array $taskBounty
     * @return mixed
     */
    static function getTaskList($paginate,$merge = [],$status = [],$taskBounty = [])
    {
        $list = TaskModel::where('type_id',1)->where('is_del',0)->where('status','>=',2)->where('status','<',10)->where('task.status','!=',3);
        if(isset($merge['is_open']) && !empty($merge['is_open'])){
            $list = $list->where('is_open',1);
        }
        if(isset($merge['is_free']) && !empty($merge['is_free'])){
            $list = $list->where('is_free',0);
        }
        if(isset($merge['task_id_arr'])){
            $list = $list->whereIn('id',$merge['task_id_arr']);
        }
        if(isset($merge['id']) && !empty($merge['id'])){
            $list = $list->where('id','!=',$merge['id']);
        }
        if(isset($merge['keywords']) && !empty($merge['keywords'])){
            $list = $list->where('title','like','%'.$merge['keywords'].'%');
            //有关键字添加搜索记录
            \CommonClass::get_keyword($merge['keywords'],3);
        }
        if(isset($merge['cate_id']) && !empty($merge['cate_id'])){
            $list = $list->where('field_id',$merge['cate_id']);
        }
        if(isset($merge['skill_id']) && !empty($merge['skill_id'])){
            $list = $list->where('cate_id',$merge['skill_id']);
        }
        if(isset($merge['district']) && !empty($merge['district'])){
            $districtId = $merge['district'];
            $list = $list->where(function($query) use ($districtId){
                $query->where('province',$districtId)->orWhere('city',$districtId)->orWhere('area',$districtId);
            });
        }
        if(isset($merge['task_bounty']) && !empty($merge['task_bounty']) && !empty($taskBounty)){
            $bountyMin = in_array($merge['task_bounty'],array_keys($taskBounty)) ? $taskBounty[$merge['task_bounty']]['min'] : 0;
            $bountyMax = in_array($merge['task_bounty'],array_keys($taskBounty)) ? $taskBounty[$merge['task_bounty']]['max'] : 0;
            if($bountyMin == 0){
                $list = $list->where('bounty','<=',$bountyMax);
            }elseif($bountyMax == 0){
                $list = $list->where('bounty','>=',$bountyMin);
            }else{
                $list = $list->where('bounty','>=',$bountyMin)->where('bounty','<=',$bountyMax);
            }
        }
        if(isset($merge['status']) && !empty($merge['status'])){
            switch($merge['status']){
                case '1':
                    $list = $list->where('status',2);
                    break;
                case '2':
                    $list = $list->where('status',4);
                    break;
                case '3':
                    $list = $list->where('bounty_status',1);
                    break;
                case '4':
                    $list = $list->whereIn('status',[8,9]);
                    break;
            }
        }
        if(isset($merge['task_type']) && !empty($merge['task_type'])){
            switch($merge['task_type']){
                case 'is_top':
                    $list = $list->where('is_top',1);
                    break;
                case 'is_fast':
                    $list = $list->where('is_fast',1);
                    break;
                case 'is_car':
                    $list = $list->where('is_car',1);
                    break;
                case 'project_agent':
                    $list = $list->where('project_agent',2);
                    break;
                case 'is_free':
                    $list = $list->where('is_free',0);
                    break;
            }
        }
        $list = $list->with('province','city','field');
        if(isset($merge['order']) && $merge['order']){
            $list = $list->orderBy($merge['order'],'desc');
        }
        $list = $list->orderBy('top_status','desc')->orderBy('id','desc')->paginate($paginate);
        return $list;
    }

    /**
     * 创建一个任务
     * @param $data
     * @return mixed
     */
    static public function createTask($data)
    {
        $status = DB::transaction(function () use ($data) {
            $arr = [
                'type_id'           => 1,
                'uid'               => $data['uid'],
                'title'             => $data['title'],
                'desc'              => $data['desc'],
                'field_id'          => isset($data['cate_id'])?$data['cate_id']:'',
                'province'          => isset($data['province'])?$data['province']:'',
                'city'              => isset($data['city'])?$data['city']:'',
                'project_agent'     => $data['project_agent'],
                'bounty'            => $data['bounty'],
                'worker_num'        => 1,
                'begin_at'          => $data['begin_at'],
                'delivery_deadline' => date('Y-m-d 23:59:59',strtotime($data['delivery_deadline'])),
                'phone'             => isset($data['phone'])?$data['phone']:'',
                'from_to'           => isset($data['from_to'])?$data['from_to']:'1',
                'wechat'            => isset($data['wechat'])?$data['wechat']:'',
                'qq'                => isset($data['qq'])?$data['qq']:'',
                'email'             => isset($data['email'])?$data['email']:'',
                'show_cash'         => isset($data['show_cash'])?$data['show_cash']:'',
                'status'            => isset($data['status'])?$data['status']:'',
                'is_free'           => 1,
                'is_support'        => isset($data['is_support'])?$data['is_support']:'1',
            ];
            $arr['car_cash'] = 0.00;
            if(isset($data['product']) && !empty($data['product'])){
                $service = ServiceModel::find($data['product']);
                if($service && $service['identify'] == 'ZHITONGCHE'){
                    $arr['car_cash'] = $service['price'];
                }
            }
            $projectAgent = [];
            if(isset($data['project_agent']) && $data['project_agent'] == 2){
                $projectAgent = [
                    'plates_num'            => $data['plates_num'],
                    'plates_num_name'       => \CommonClass::platesNum($data['plates_num']),
                    'pieces_num'            => $data['pieces_num'],
                    'length'                => $data['length'],
                    'width'                 => $data['width'],
                    'veneers_num'           => $data['veneers_num'],
                    'plate_thickness'       => $data['plate_thickness'],
                    'plate_thickness_name'  => \CommonClass::plateThick($data['plate_thickness']),
                    'copper_thickness'      => $data['copper_thickness'],
                    'copper_thickness_name' => \CommonClass::copperThickne($data['copper_thickness']),
                    'spray_plating'         => $data['spray_plating'],
                    'spray_plating_name'    => \CommonClass::platingType($data['spray_plating']),
                    'soldering_color'       => $data['soldering_color'],
                    'soldering_color_name'  => \CommonClass::solderColor($data['soldering_color']),
                    'character_color'       => $data['character_color'],
                    'character_color_name'  => \CommonClass::characterColor($data['character_color']),
                    'is_connect'            => $data['is_connect'],
                    'delivery_cycle'        => $data['delivery_cycle'],
                    'delivery_cycle_name'   => \CommonClass::deliveryCycle($data['delivery_cycle']),
                ];
            }
            if(isset($data['task_id']) && !empty($data['task_id'])){
                $arr['updated_at'] = date('Y-m-d H:i:s');
                self::where("id",$data['task_id'])->update($arr);
                $result['id'] = $data['task_id'];
                if($projectAgent){
                    TaskAgentModel::where('task_id',$data['task_id'])->update($projectAgent);
                }
            }else{
                $arr['created_at'] = $data['created_at'];
                $arr['updated_at'] = date('Y-m-d H:i:s');
                $result = self::create($arr);
                if($projectAgent){
                    $projectAgent['task_id'] = $result['id'];
                    TaskAgentModel::create($projectAgent);
                }
            }
            if (!empty($data['file_ids'])) {
                //查询用户的附件记录，排除掉用户删除的附件记录
                $file_able_ids = AttachmentModel::fileAble($data['file_ids']);
                $file_able_ids = array_flatten($file_able_ids);
                if(isset($data['task_id'])){
                    TaskAttachmentModel::where('task_id',$data['task_id'])->delete();
                }
                foreach ($file_able_ids as $v) {
                    $attachment_data = [
                        'task_id' => $result['id'],
                        'attachment_id' => $v,
                        'created_at' => date('Y-m-d H:i:s', time()),
                    ];

                    TaskAttachmentModel::create($attachment_data);
                }
                //修改附件的发布状态
                $attachmentModel = new AttachmentModel();
                $attachmentModel->statusChange($file_able_ids);
            }

            if (!empty($data['product']) && $data['product'] > 0) {
                if(isset($data['task_id'])){
                    TaskServiceModel::where('task_id',$data['task_id'])->delete();
                }
                $service_data = [
                    'task_id'    => $result['id'],
                    'service_id' => $data['product'],
                    'created_at' => date('Y-m-d H:i:s', time()),
                ];
                TaskServiceModel::create($service_data);
            }

            //增加用户的发布任务数量
            UserDetailModel::where('uid', $data['uid'])->increment('publish_task_num', 1);
            ShopModel::where('uid', $data['uid'])->increment('publish_task_num', 1);
            //判断是否发消息
            //判断当前的任务发布成功之后是否需要发送系统消息
            $task_publish_success = MessageTemplateModel::where('code_name', 'task_publish_success')->where('is_open', 1)->first();
            if ($task_publish_success) {
                $task = self::find($result['id']);
                $user = UserModel::where('id', $data['uid'])->first();//必要条件
                $site_name = \CommonClass::getConfig('site_name');//必要条件
                $domain = \CommonClass::getDomain();
                //组织好系统消息的信息
                //发送系统消息
                $messageVariableArr = [
                    'username'            => $user['name'],
                    'task_number'         => $task['id'],
                    'task_title'          => $task['title'],
                    'task_status'         => '待审核',
                    'website'             => $site_name,
                    'href'                => $domain . '/kb/' . $task['id'],
                    'task_link'           => $task['title'],
                    'manuscript_end_time' => $task['delivery_deadline'],
                ];
                if($task_publish_success->is_on_site == 1){
                    \MessageTemplateClass::getMeaasgeByCode('task_publish_success',$user['id'],2,$messageVariableArr,$task_publish_success['name']);
                }
                //发送邮件
                if($task_publish_success->is_send_email == 1){
                    $email = $user->email;
                    \MessageTemplateClass::sendEmailByCode('task_publish_success',$email,$messageVariableArr,$task_publish_success['name']);
                }
            }
            return $result;
        });
        return $status;
    }

    /**
     * 创建一个雇佣
     * @param $data
     * @return mixed
     */
    static public function createEmploy($data)
    {
        $status = DB::transaction(function () use ($data) {
            $arr = [
                'type_id'           => 3,
                'uid'               => $data['uid'],
                'title'             => $data['title'],
                'desc'              => $data['desc'],
                'field_id'          => $data['cate_id'],
                'province'          => $data['province'],
                'city'              => $data['city'],
                'project_agent'     => $data['project_agent'],
                'bounty'            => $data['bounty'],
                'worker_num'        => 1,
                'begin_at'          => $data['begin_at'],
                'end_at' => date('Y-m-d 23:59:59',strtotime($data['end_at'])),
                'phone'             => isset($data['phone']) ? $data['phone'] :'',
                'wechat'            => isset($data['wechat']) ? $data['wechat'] :'',
                'qq'                => isset($data['qq']) ? $data['qq'] :'',
                'email'             => isset($data['email']) ? $data['email'] :'',
                'show_cash'         => $data['show_cash'],
                'status'            => $data['status'],
            ];
            $projectAgent = [];
            if(isset($data['project_agent']) && $data['project_agent'] == 2){
                $projectAgent = [
                    'plates_num'            => $data['plates_num'],
                    'plates_num_name'       => \CommonClass::platesNum($data['plates_num']),
                    'pieces_num'            => $data['pieces_num'],
                    'length'                => $data['length'],
                    'width'                 => $data['width'],
                    'veneers_num'           => $data['veneers_num'],
                    'plate_thickness'       => $data['plate_thickness'],
                    'plate_thickness_name'  => \CommonClass::plateThick($data['plates_num']),
                    'copper_thickness'      => $data['copper_thickness'],
                    'copper_thickness_name' => \CommonClass::copperThickne($data['copper_thickness']),
                    'spray_plating'         => $data['spray_plating'],
                    'spray_plating_name'    => \CommonClass::platingType($data['spray_plating']),
                    'soldering_color'       => $data['soldering_color'],
                    'soldering_color_name'  => \CommonClass::solderColor($data['soldering_color']),
                    'character_color'       => $data['character_color'],
                    'character_color_name'  => \CommonClass::characterColor($data['character_color']),
                    'is_connect'            => $data['is_connect'],
                    'delivery_cycle'        => $data['delivery_cycle'],
                    'delivery_cycle_name'   => \CommonClass::deliveryCycle($data['delivery_cycle']),
                ];
            }
            if(isset($data['task_id']) && !empty($data['task_id'])){
                $arr['updated_at'] = date('Y-m-d H:i:s');
                self::where("id",$data['task_id'])->update($arr);
                $result['id'] = $data['task_id'];
                if($projectAgent){
                    TaskAgentModel::where('task_id',$data['task_id'])->update($projectAgent);
                }
            }else{
                $arr['created_at'] = $data['created_at'];
                $arr['updated_at'] = date('Y-m-d H:i:s');
                $result = self::create($arr);
                if($projectAgent){
                    $projectAgent['task_id'] = $result['id'];
                    TaskAgentModel::create($projectAgent);
                }
                //创建雇佣关联关系
                EmployModel::create([
                    'employee_uid'  => $data['employee_uid'],
                    'employer_uid'  => $data['uid'],
                    'task_id'       => $result['id'],
                    'good_id'      =>$data['good_id'],
                ]);
            }
            if (!empty($data['file_ids'])) {
                //查询用户的附件记录，排除掉用户删除的附件记录
                $file_able_ids = AttachmentModel::fileAble($data['file_ids']);
                $file_able_ids = array_flatten($file_able_ids);
                if(isset($data['task_id'])){
                    TaskAttachmentModel::where('task_id',$data['task_id'])->delete();
                }
                foreach ($file_able_ids as $v) {
                    $attachment_data = [
                        'task_id' => $result['id'],
                        'attachment_id' => $v,
                        'created_at' => date('Y-m-d H:i:s', time()),
                    ];

                    TaskAttachmentModel::create($attachment_data);
                }
                //修改附件的发布状态
                $attachmentModel = new AttachmentModel();
                $attachmentModel->statusChange($file_able_ids);
            }

            //增加用户的发布任务数量
            UserDetailModel::where('uid', $data['uid'])->increment('publish_task_num', 1);
            ShopModel::where('uid', $data['uid'])->increment('publish_task_num', 1);

            return $result;
        });
        return $status;
    }

    /**
     * 快包任务 发布任务购买增值服务
     * @param float $money 订单金额
     * @param int $task_id 任务id
     * @param int $uid 购买人uid
     * @param string $code 订单编号
     * @param int $type 支付方式 1:余额 2:支付宝 3:微信
     * @return bool
     */
    static function payServiceTask($money, $task_id, $uid, $code, $type = 1, $pay_account='')
    {
        $status = DB::transaction(function () use ($money, $task_id, $uid, $code, $type, $pay_account) {
            if($type == 1){//余额支付扣除用户的余额
                DB::table('user_detail')->where('uid',$uid)->where('balance_status', '!=', 1)->decrement('balance', $money);
            }

            //生成财务记录，action 1 项目交易 2 方案交易 3 充值 4 提现 5 购买增值服务 6  购买工具 7 添加资讯 8 询价 9 缴纳保证金 10 维权退款  11 推广赏金 12 购买会员
            //查询购买的增值服务
            $product = TaskServiceModel::where('task_id',$task_id)
                ->lists('service_id')->toArray();
            $topStatus = 0;
            $taskData = [];
            if (!empty($product)) {
                foreach ($product as $k => $v) {
                    $server = ServiceModel::where('id', $v)->first();
                    if($server){
                        switch($server['identify']){
                            case 'ZHIDING':
                                $topStatus = $topStatus + 1;
                                $taskData['is_top'] = 1;
                                break;
                            case 'JIAJI':
                                $topStatus = $topStatus + 1;
                                $taskData['is_fast'] = 1;
                                break;
                            case 'SIMIDUIJIE':
                                $taskData['is_open'] = 2;
                                break;
                            case 'ZHITONGCHE':
                                $taskData['is_car'] = 1;
                                break;
                            case 'SOUSUOYINGQINGPINGBI':
                                $taskData['engine_status'] = 1;
                                break;
                            case 'GAOJIANPINGBI':
                                $taskData['work_status'] = 1;
                                break;
                        }
                    }
                }
                $taskData['top_status'] = $topStatus;
                self::where('id', $task_id)->update($taskData);
            }
            //修改订单状态
            OrderModel::where('code', $code)->update(['status' => 1]);
            //查询是否有未核销优惠券
            $couponPrice = 0.00;
            $useLog = UserCouponLogModel::where('order_num',$code)->where('status',1)->where('uid',$uid)->first();
            if($useLog){
                $couponPrice = $useLog->price;
                UserCouponLogModel::where('order_num',$code)->where('status',1)->where('uid',$uid)->update([
                    'status'     => 2,
                    'payment_at' => date('Y-m-d H:i:s')
                ]);
                UserCouponModel::where('id',$useLog->user_coupon_id)
                    ->update(['status' => 2]);
            }

            $remainder = DB::table('user_detail')->where('uid',$uid)->first()->balance;
            //获取子订单id
            $findSubOrderId=OrderModel::leftJoin("sub_order","order.id","=","sub_order.order_id")->where('order.code', $code)->first(["sub_order.id"]);
            $financial = [
                'action'     => 5,
                'pay_type'   => $type,
                'cash'       => $money,
                'uid'        => $uid,
                'created_at' => date('Y-m-d H:i:s', time()),
                'coupon'     => $couponPrice,
                'status'     => 2,//用户支出
                'remainder'  => $remainder,
                'related_id'=>$findSubOrderId['id'],
                'pay_account'=>$pay_account,
            ];
            FinancialModel::create($financial);
        });

        return is_null($status) ? true : false;
    }

    /**
     * 赏金托管数据操作
     * @param float $money 订单金额
     * @param int $uid 购买人uid
     * @param int $task_id 任务id
     * @param string $code 订单编号
     * @param int $type 支付方式 1:余额 2:支付宝 3:微信
     * @return bool
     */
    static function bounty($money, $task_id, $uid, $code, $type = 1,$pay_account='')
    {
        $status = DB::transaction(function () use ($money, $task_id, $uid, $code, $type, $pay_account) {
            $task = TaskModel::find($task_id);
            if($type == 1 && $money>0){
                //扣除用户的余额
                DB::table('user_detail')->where('uid', '=', $uid)->where('balance_status', '!=', 1)->decrement('balance', $money);
            }
            $taskData = [
                'bounty_status' => 1,
                'status'        => 5,//开始工作
                'publicity_at'  => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ];
            if($task['is_car'] == 1){
                $carCash = ($task['car_cash'] - $task['bounty']) > 0 ? $task['car_cash'] - $task['bounty'] : 0.00;
                $taskData['car_cash'] = $carCash;
            }

            //修改任务的赏金托管状态
            self::where('id', $task_id)->update($taskData);
            //修改订单状态
            OrderModel::where('code', $code)->update(['status' => 1]);

            //查询是否有未核销优惠券
            $couponPrice = 0.00;
            $useLog = UserCouponLogModel::where('order_num',$code)->where('status',1)->where('uid',$uid)->first();
            if($useLog){
                $couponPrice = $useLog->price;
                UserCouponLogModel::where('order_num',$code)->where('status',1)->where('uid',$uid)->update([
                    'status'     => 2,
                    'payment_at' => date('Y-m-d H:i:s')
                ]);
                UserCouponModel::where('id',$useLog->user_coupon_id)
                    ->update(['status' => 2]);
            }
            $financialMoney = $money > 0 ? $money : 0.00;
            //生成财务记录，action 1表示发布任务
            $remainder = DB::table('user_detail')->where('uid',$uid)->first()->balance;
            $financial = [
                'action'     => 1,
                'pay_type'   => $type,
                'cash'       => $financialMoney,
                'coupon'     => $couponPrice,
                'uid'        => $uid,
                'created_at' => date('Y-m-d H:i:s', time()),
                'status'     => 2,//用户支出
                'remainder'  => $remainder,
                'related_id' => $task_id,
                'pay_account'=> $pay_account,
            ];
            FinancialModel::create($financial);

            //通知雇主
            $userInfo = UserModel::where('id',$task->uid)->first();
            $user = [
                'uid'    => $userInfo->id,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $templateArr = [
                'username' => $userInfo->name,
                'title'    => $task->title
            ];
            \MessageTemplateClass::sendMessage('task_bounty',$user,$templateArr,$templateArr);

            //通知服务商
            $work = WorkModel::where('task_id',$task->id)->where('status',1)->first();
            if($work){
                $workUid = $work->uid;
                $userInfo1 = UserModel::where('id',$workUid)->first();
                $user1 = [
                    'uid'    => $userInfo1->id,
                    'email'  => $userInfo1->email,
                    'mobile' => $userInfo1->mobile
                ];
                $templateArr1 = [
                    'username' => $userInfo1->name,
                    'title'    => $task->title
                ];
                \MessageTemplateClass::sendMessage('task_bounty_accept',$user1,$templateArr1,$templateArr1);
            }

        });
        return is_null($status) ? true : false;
    }

    /**
     * 查询任务详情
     * @param $id
     * @return array
     */
    static function detail($id)
    {
        $arr = [];
        $data = self::where('task.id', $id)/*->with('province','city','field','skill','user')*/->first();
        if($data){
            $district = DistrictModel::whereIn('id',[$data->province,$data->city])->get()->toArray();
            $district = \CommonClass::setArrayKey($district,'id');
            $cate = TaskCateModel::whereIn('id',[$data->field_id,$data->cate_id])->get()->toArray();
            $cate = \CommonClass::setArrayKey($cate,'id');
            $arr = $data->toArray();
            $arr['province'] = in_array($arr['province'],array_keys($district)) ? $district[$arr['province']] : [];
            $arr['city'] = in_array($arr['city'],array_keys($district)) ? $district[$arr['city']] : [];
            $arr['field'] = in_array($arr['field_id'],array_keys($cate)) ? $cate[$arr['field_id']] : [];
            $arr['skill'] = in_array($arr['cate_id'],array_keys($cate)) ? $cate[$arr['cate_id']] : [];
        }
        return $arr;
    }

    /**
     * 查找相似的任务
     * @param $cate_id
     */
    static function findByCate($cate_id, $id=0,$paginate=5)
    {
        $merge = [
            'id'      => $id,
            'cate_id' => $cate_id
        ];
        $data = self::getTaskList($paginate,$merge);
        if(!empty($data->toArray()['data'])){
            foreach($data as $key => $val){
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
        return $data->toArray()['data'];
    }







    static public function myTasks($data)
    {
        $query = self::select('task.*', 'tt.name as type_name','tt.alias','us.name as nickname', 'ud.avatar', 'tc.name as cate_name', 'province.name as province_name', 'city.name as city_name')
            ->where('task.status', '>', 0)
            ->where('task.status', '<=', 11)->where('task.uid', $data['uid'])->where(function($query){
				$query->where(function($querys){
					 $querys->where('task.bounty_status',1)->where('tt.alias','xuanshang');
				 })->orwhere(function($querys){
					 $querys->whereIn('task.bounty_status',[0,1])->where('tt.alias','zhaobiao');
				 });
			});
        //状态筛选
        if (isset($data['status']) && $data['status'] != 0) {
            /* 状态值修改 by heike 2017-09-14
			switch ($data['status']) {
                case 1:
                    $status = [3, 4, 6];
                    break;
                case 2:
                    $status = [5];
                    break;
                case 3:
                    $status = [7];
                    break;
                case 4:
                    $status = [8, 9, 10];
                    break;
                case 5:
                    $status = [2, 11];
                    break;
            } */
			switch($data['status']){
				case 1:
                    $status = [6];
                    break;
                case 2:
                    $status = [4,5];
                    break;
                case 3:
                    $status = [7];
                    break;
                case 4:
                    $status = [8, 9, 10];
                    break;
                case 5:
                    $status = [2, 11];
                    break;
				case 6:
					$status = [1];
					break;
				case 7:
					$status = [3,4];
					break;
				case 8:
					$status = [5];
					break;
                case 9:
					$status = [6];
					break;
 				case 10:
					$status = [7];
					break;
                case 11:
					$status = [11];
					break;
                case 12:
					$status = [8,9];
					break;
                case 13:
					$status = [10];
					break; 
                case 14:
					$status = [8,9,10];
					break;
                case 15:
                    $status = [3];
                    break;
			}
            $query->whereIn('task.status', $status);
        }
        //时间段筛选
        if (isset($data['time'])) {
            switch ($data['time']) {
                case 1:
                    $query->whereBetween('task.created_at', [date('Y-m-d H:i:s', strtotime('-1 month')), date('Y-m-d H:i:s', time())]);
                    break;
                case 2:
                    $query->whereBetween('task.created_at', [date('Y-m-d H:i:s', strtotime('-3 month')), date('Y-m-d H:i:s', time())]);
                    break;
                case 3:
                    $query->whereBetween('task.created_at', [date('Y-m-d H:i:s', strtotime('-6 month')), date('Y-m-d H:i:s', time())]);
                    break;
            }

        }
        //任务模式筛选
		if(isset($data['type'])){
			$query->where('type_id',$data['type']);
		}
        $data = $query->join('task_type as tt', 'task.type_id', '=', 'tt.id')
            ->leftjoin('district as province', 'province.id', '=', 'task.province')
            ->leftjoin('district as city', 'city.id', '=', 'task.city')
            ->leftjoin('users as us', 'us.id', '=', 'task.uid')
            ->leftjoin('user_detail as ud', 'ud.uid', '=', 'task.uid')
            ->leftjoin('cate as tc', 'tc.id', '=', 'task.cate_id')
            ->orderBy('task.created_at', 'desc')
            ->paginate(5);
        return $data;
    }
    /**
     * 任务筛选
     * @param $data
     * @param $paginate
     * @return mixed
     * author: muker（qq:372980503）
     */
    public static function findBy($data,$paginate=10)
    {
        $query = self::select('task.*', 'b.name as type_name', 'b.alias as type_alias', 'us.name as user_name')->where('task.status', '>', 2)
            ->where(function($query){
				 $query->where(function($querys){
					 $querys->where('task.bounty_status',1)->where('b.alias','xuanshang');
				 })->orwhere(function($querys){
					 $querys->whereIn('task.bounty_status',[0,1])->where('b.alias','zhaobiao');
				 });
			})
			->where('task.status', '<=', 9)->where('begin_at', "<=", date('Y-m-d H:i:s', time()))
            ->orderBy('task.top_status', 'desc');
        //关键词筛选
        if (isset($data['keywords'])) {
            $query = $query->where('task.title', 'like', '%' . e($data['keywords']) . '%');
        }
		//任务模式筛选
		if(isset($data['taskType']) && $data['taskType']!=0){
			$query->where('task.type_id', $data['taskType']);
		}
        //类别筛选
        if (isset($data['category']) && $data['category'] != 0) {
            //查询所有的底层id
            $category_ids = TaskCateModel::findCateIds($data['category']);
            $query->whereIn('task.cate_id', $category_ids);
        }
        //地区筛选
        if (isset($data['province'])) {
            $query->where('task.province', intval($data['province']));
        }
        if (isset($data['city'])) {
            $query->where('task.city', intval($data['city']));
        }
        if (isset($data['area'])) {
            $query->where('task.area', intval($data['area']));
        }
        //任务状态
        if (isset($data['status'])) {
            switch ($data['status']) {
                case 1:
                    //$status = [4];
					$status=[3, 4, 6];
                    break;
                case 2:
                    $status = [5];
                    break;
                case 3:
                    $status = [6, 7];
                    break;
                case 4:
                    $status = [8, 9];
                    break;
				case 12:
				   $status = [8, 9,10];
                   break;
            }
            $query->whereIn('task.status', $status);
        }
        //排序
        if (isset($data['desc']) && $data['desc'] != 'created_at') {
            $query->orderBy('task.'.$data['desc'], 'desc');
        } elseif (isset($data['desc']) && $data['desc'] == 'created_at') {
            $query->orderBy('task.created_at');
        } else {
            $query->orderBy('task.created_at', 'desc');
        }
        $data = $query->join('task_type as b', 'task.type_id', '=', 'b.id')
            ->leftjoin('users as us', 'us.id', '=', 'task.uid')
            ->paginate($paginate);
        return $data;
    }

    /**
     * 任务筛选
     * @param $data
     * @return mixed
     * author: muker（qq:372980503）
     */
    static function findByCity($data, $city)
    {
        $query = self::select('task.*', 'b.name as type_name', 'us.name as user_name')->where('task.status', '>', 2)
            ->where('task.bounty_status', 1)->where('task.status', '<=', 9)->where('begin_at', "<=", date('Y-m-d H:i:s', time()))
            ->where('task.region_limit', 1)
            ->orderBy('top_status', 'desc');
        //关键词筛选
        if (isset($data['keywords'])) {
            $query = $query->where('task.title', 'like', '%' . e($data['keywords']) . '%');
        }
        //类别筛选
        if (isset($data['category']) && $data['category'] != 0) {
            //查询所有的底层id
            $category_ids = TaskCateModel::findCateIds($data['category']);
            $query->whereIn('cate_id', $category_ids);
        }
        //地区筛选
        if (isset($city)) {
            $query->where(function ($query) use ($city) {
                $query->where('province', $city)->orwhere('city', $city);
            });
        }

        if (isset($data['area'])) {
            $query->where(function ($query) use ($data) {
                $query->where('city', $data['area'])->orwhere('area', $data['area']);
            });
        }
        //任务状态
        if (isset($data['status'])) {
            switch ($data['status']) {
                case 1:
                    $status = [4];
                    break;
                case 2:
                    $status = [5];
                    break;
                case 3:
                    $status = [6, 7];
                    break;
                case 4:
                    $status = [8, 9];
                    break;
            }
            $query->whereIn('task.status', $status);
        }
        //排序
        if (isset($data['desc']) && $data['desc'] != 'created_at') {
            $query->orderBy($data['desc'], 'desc');
        } elseif (isset($data['desc']) && $data['desc'] == 'created_at') {
            $query->orderBy('created_at');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $data = $query->join('task_type as b', 'task.type_id', '=', 'b.id')
            ->leftjoin('users as us', 'us.id', '=', 'task.uid')
            ->paginate(10);

        return $data;
    }




    /**
     * 根据id查询任务
     * @param $id
     */
    static function findById($id)
    {
        $data = self::select('task.*', 'b.name as cate_name', 'c.name as type_name','c.alias as task_type')
            ->where('task.id', '=', $id)
            ->leftjoin('cate as b', 'task.cate_id', '=', 'b.id')
            ->leftjoin('task_type as c', 'task.type_id', '=', 'c.id')
            ->first();

        return $data;
    }


    /**
     * 判断是不是雇主
     */
    static function isEmployer($task_id, $uid)
    {
        $data = self::where('id', $task_id)->first();
        if ($data['uid'] == $uid)
            return true;
        return false;
    }







    /**
     * 获取某人发布某状态的任务数量
     * @param $uid
     * @param array $status 任务状态数组
     * @param int $typeId  任务类型
     * @param $alias
     * @return mixed
     */
    static public function myTaskCount($uid,$status,$typeId,$alias)
    {

        $tasks = TaskModel::where('task.uid',$uid);
        switch($alias) {
            case 'xuanshang'://悬赏
                $tasks = $tasks->where('task.type_id', $typeId)->where('task.bounty_status', 1);
                break;
            case 'zhaobiao'://招标
                $tasks = $tasks->where('task.type_id', $typeId);
                break;
            default:
                $tasks = $tasks->where('task.type_id', $typeId)->where('task.bounty_status', 1);
        }
        $count = $tasks->whereIn('task.status',$status)->count();
        return $count;
    }

    /**
     * 获取某人接受某状态的任务数量
     * @param $taskIdArr
     * @param $status
     * @param $typeId
     * @param $alias
     * @return mixed
     */
    static public function myAcceptCount($taskIdArr,$status,$typeId,$alias)
    {
        $tasks = TaskModel::whereIn('id',$taskIdArr);
        switch($alias) {
            case 'xuanshang'://悬赏
                $tasks = $tasks->where('type_id', $typeId)->where('task.bounty_status', 1);
                break;
            case 'zhaobiao'://招标
                $tasks = $tasks->where('type_id', $typeId);
                break;
            default:
                $tasks = $tasks->where('type_id', $typeId)->where('task.bounty_status', 1);
        }
        $count = $tasks->whereIn('status',$status)->count();
        return $count;
    }

    /**
     * app任务数组重组
     * @param $taskArr
     * @return mixed
     */
    static public function dealTaskArr($taskArr)
    {
        $taskIdArr = array_pluck($taskArr,'id');
        if(!empty($taskIdArr)){
            //任务相关增值服务
            $service = ServiceModel::where('type',1)->select('id','identify')->get()->toArray();
            $newService = array_reduce($service,function(&$newService,$v){
                $newService[$v['id']] = $v['identify'];
                return $newService;
            });
            //任务类型
            $taskTypeArr = TaskTypeModel::select('id','alias')->get()->toArray();
            $taskType = array_reduce($taskTypeArr,function(&$taskType,$v){
                $taskType[$v['alias']] = $v['id'];
                return $taskType;
            });
            $taskTypeA = array_reduce($taskTypeArr,function(&$taskTypeA,$v){
                $taskTypeA[$v['id']] = $v['alias'];
                return $taskTypeA;
            });
            //查询是否有购买增值服务成功的订单(因为招标任务未托管赏金即可发布,有可能有未支付的增值服务要过滤)
            $order = OrderModel::select('order.*','task.type_id')
                ->whereIn('order.task_id',$taskIdArr)
                ->where('order.status',1)
                ->where(function($query) use ($taskType){
                    $query->where(function($query) use ($taskType){
                        $query->where('task.type_id',$taskType['xuanshang']);
                    })->orWhere(function($query) use ($taskType){
                        $query->where('task.type_id',$taskType['zhaobiao'])
                            ->where('order.code','like','ts%');
                    });
                })
                ->leftJoin('task','task.id','=','order.task_id')
                ->get()->toArray();
            $taskIdArr = array_keys(\CommonClass::keyByGroup($order,'task_id'));
            $taskService = TaskServiceModel::whereIn('task_id',$taskIdArr)
                ->select('task_id','service_id')->get()->toArray();
            $newTaskService = array_reduce($taskService,function(&$newTaskService,$v) use ($newService) {
                if(in_array($v['service_id'],array_keys($newService))){
                    $newTaskService[$v['task_id']][] = $newService[$v['service_id']];
                }

                return $newTaskService;
            });
            //重组任务数组(增值服务)
            if(!empty($newTaskService)){
                foreach($taskArr as $k => $v){
                    foreach($newTaskService as $k1 => $v1){
                        if($v['id'] == $k1){
                            $taskArr[$k]['task_service'] = $v1;
                        }
                    }
                }
            }
            foreach($taskArr as $k => $v){
                if(!isset($v['task_service'])){
                    $taskArr[$k]['task_service'] = [];
                }
                if($v['bounty_status'] == 1){
                    $taskArr[$k]['bounty_status_desc'] = '已托管';
                }else{
                    $taskArr[$k]['bounty_status_desc'] = '未托管';
                }
                if(!isset($v['task_type']) && in_array($v['type_id'],array_keys($taskTypeA))){
                    $taskArr[$k]['task_type'] = $taskTypeA[$v['type_id']];
                }
                if($v['bounty_status'] == 0){
                    if(isset($v['task_type']) && $v['task_type'] == 'zhaobiao'){
                        $taskArr[$k]['bounty'] = '可议价';
                    }elseif(!isset($v['task_type']) && in_array($v['type_id'],array_keys($taskTypeA)) && $taskTypeA[$v['type_id']]=='zhaobiao'){
                        $taskArr[$k]['bounty'] = '可议价';
                    }

                }

            }
        }
        return $taskArr;
    }







}
