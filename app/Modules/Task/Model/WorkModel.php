<?php

namespace App\Modules\Task\Model;

use App\Modules\Employ\Models\EmployModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\Manage\Model\MessageTemplateModel;
use App\Modules\Shop\Models\ShopModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\MessageReceiveModel;
use App\Modules\User\Model\PromoteTypeModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use App\Modules\User\Model\UserVipCardModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class WorkModel extends Model
{
    protected $table = 'work';
    public  $timestamps = false;  //关闭自动更新时间戳
    public $fillable = [
        'desc',
        'task_id',
        'status',
        'uid',
        'bid_at',
        'created_at',
        'price',
        'start_time',
        'end_time',
        'show_sort',//竞标排序
        'type'
    ];

    /**
     * 查询所有的附件
     */
    public function childrenAttachment()
    {
        return $this->hasMany('App\Modules\Task\Model\WorkAttachmentModel', 'work_id', 'id')->with('attachment');
    }

    public function user()
    {
        return $this->hasOne('App\Modules\User\Model\UserModel','id','uid')->select('id','name');
    }
    /**
     * 查询所有的评论
     */
    public function childrenComment()
    {
        return $this->hasMany('App\Modules\Task\Model\WorkCommentModel', 'work_id', 'id');
    }

    public function shop()
    {
        return $this->hasOne('App\Modules\Shop\Models\ShopModel', 'uid', 'uid')->with('provinces','citys','user');
    }

    /**
     * 判断用户是否是当前任务的投稿人
     */
    static function isWorker($uid,$task_id)
    {
        $query = self::where('uid',$uid)->where('task_id',$task_id)->first();
        if($query) return true;
        return false;
    }



    /**
     * 判断用户是否中标
     * @param $task_id
     * @param $uid
     * @return bool
     */
    static function isWinBid($task_id,$uid)
    {
        $query = self::where('task_id',$task_id)->where('status',1)->where('uid',$uid);

        $result = $query->first();

        if($result) return $result['status'];

        return false;
    }

    /**
     * 关联查询所有的投标记录
     * @param $id
     * @param array $data
     * @return array
     */
    static function findAll($id,$paginate=1,$data=array())
    {
        $query = self::select('work.*')
            ->where('work.task_id',$id)->whereIn('work.status',[0,1])->where('forbidden',0);
        //筛选
        if(isset($data['work_type'])){
            switch($data['work_type'])
            {
                case 1:
                    $query->where('work.status','=',0);
                    break;
                case 2:
                    $query->where('work.status','=',1);
                    break;
            }
        }
        $list = $query->with('childrenAttachment')
            ->orderBy('show_sort','desc')
            ->orderBy('status','desc')
            ->orderBy('id','desc')
            ->paginate($paginate);
        return $list;
    }

    /**
     * 查询交付投稿
     * @param $id
     * @param $data
     * @return mixed
     */
    static public function findDelivery($id,$data,$paginate=5)
    {
        $query = self::select('work.*')
            ->where('work.task_id',$id)->where('work.status','>=',2);
        //筛选
        if(isset($data['evaluate']) && !empty($data['evaluate'])){
            switch($data['evaluate'])
            {
                case 1:
                    $query = $query->where('status','>=',0);
                    break;
                case 2:
                    $query = $query->where('status','>=',1);
                    break;
                case 3:
                    $query = $query->where('status','>=',2);
            }
        }
        if(isset($data['uid']) && !empty($data['uid'])){
            $query = $query->where('uid',$data['uid']);
        }
        $data = $query->with('childrenAttachment')
            ->orderBy('id','desc')->paginate($paginate);
        return $data;
    }

    /**
     * 查找当前正在维权的账户
     * @param $id
     * @return mixed
     */
    static public function findRights($id)
    {
        $data = Self::select('work.*')
            ->where('task_id',$id)->where('work.status',4)
            ->with('childrenAttachment')
            ->paginate(5);
        return $data;
    }

    /**
     * 统计某一任务某一状态下的稿件数量
     * @param $task_id
     * @param $status
     * @return mixed
     */
    static function countWorker($task_id,$status)
    {
        $count = self::where('status',$status)->where('task_id',$task_id)->count();
        return $count;
    }

    /**
     * 创建一个竞标稿件
     * @param $data
     * @return bool
     */
    static public function workCreate($data)
    {
        $status = DB::transaction(function() use($data){
            $level = UserModel::where('id',$data['uid'])->pluck('level');
            //将数据写入到work表中
            $arr = [
                'desc'       => isset($data['desc']) ? $data['desc'] : '',
                'task_id'    => isset($data['task_id']) ? $data['task_id'] : '',
                'status'     => isset($data['status']) ? $data['status'] : 0,
                'uid'        => isset($data['uid']) ? $data['uid'] : '',
                'shop_id'    => isset($data['shop_id']) ? $data['shop_id'] : '',
                'created_at' => isset($data['created_at']) ? $data['created_at'] : '',
                'price'      => isset($data['price']) ? $data['price'] : '',
                'start_time' => isset($data['start_time']) ? $data['start_time'] : '',
                'end_time'   => isset($data['end_time']) ? $data['end_time'] : '',
                'show_sort'  => isset($level) ? $level : 0,
                'type'       => isset($data['type']) && $data['type'] == 1 ? 1 : 0
            ];
            $result = WorkModel::create($arr);

            if(isset($data['file_ids'])){
                $file_able_ids = AttachmentModel::select('attachment.id','attachment.type')->whereIn('id',$data['file_ids'])->get()->toArray();
                //创建投稿记录和附件关联关系
                foreach($file_able_ids as $v){
                    $work_attachment = [
                        'task_id'       => $data['task_id'],
                        'work_id'       => $result['id'],
                        'attachment_id' => $v['id'],
                        'type'          => $v['type'],
                        'created_at'    => date('Y-m-d H:i:s',time()),
                    ];
                    WorkAttachmentModel::create($work_attachment);
                }
            }

            if(isset($data['card']) && !empty($data['card'])){//使用次卡记录
                //增加使用记录
                $cardData = [
                    'uid'        => isset($data['uid']) ? $data['uid'] : '',
                    'card_id'    => $data['card'],
                    'work_id'    => $result['id'],
                    'created_at' => date('Y-m-d H:i:s',time()),
                ];
                UseCardLogModel::create($cardData);
                //增加使用次数 减少可用次数
                UserVipCardModel::where('id',$data['card'])->increment('has_use',1);
                UserVipCardModel::where('id',$data['card'])->decrement('do_use',1);
            }
            //修改用户的竞标量
            ShopModel::where('uid',$data['uid'])->increment('delivery_count',1);
            $task = TaskModel::find($data['task_id']);
            if($task['delivery_count'] == 0){
                TaskModel::where('id',$data['task_id'])->update([
                    'work_at'        => date('Y-m-d H:i:s'),
                    'delivery_count' => $task['delivery_count']+1
                ]);
            }else{
                //修改任务的投稿数量
                TaskModel::where('id',$data['task_id'])->increment('delivery_count',1);
            }
            //修改雇佣流程，服务商响应即为选中，去除雇主确认操作，直接进入签订协议20190411
            if($data['status'] == 1){
                $data1 = [
                    'work_id' => $result['id'],
                    'task_id' => $data['task_id']
                ];
                self::winBid($data1);
            }
            //查询是否发送定制短信
            //读取雇佣信息
            $employInfo=EmployModel::where("task_id",$data['task_id'])->first();
            if($employInfo['good_id'] && $employInfo['good_id']>0){
                $userEmployerInfo = UserModel::where('id',$employInfo['employer_uid'])->first();
                $user = [
                    'uid'    => $userEmployerInfo->id,
                    'email'  => $userEmployerInfo->email,
                    'mobile' => $userEmployerInfo->mobile
                ];
                $templateArr = [
                    'username' => $userEmployerInfo->name,
                    'type'    =>"同意"
                ];
                \MessageTemplateClass::sendMessage('employer_goods_special_buy',$user,$templateArr,$templateArr);
            }

        });

        return is_null($status)?true:false;
    }

    /**
     * 中标
     * @param $data
     * @return bool
     */
    static public function winBid($data)
    {
        $work = self::where('id',$data['work_id'])->first();
        $status = DB::transaction(function() use($data,$work){
            //修改当前稿件为中标状态
            self::where('id',$data['work_id'])->update([
                'status'     => 1,
                'bid_at'     => date('Y-m-d H:i:s')
            ]);
            //将任务状态修改成选稿状态
            TaskModel::where('id',$data['task_id'])->update([
                'bounty'            => $work['price'],
                'begin_at'          => $work['start_time'],
                'end_at'            => $work['end_time'],
                'status'            => 4,
                'selected_work_at'  => date('Y-m-d H:i:s'),
                'updated_at'        => date('Y-m-d H:i:s')
            ]);
            //修改用户的承接的任务数量
            UserDetailModel::where('uid',$work['uid'])->increment('receive_task_num',1);
            ShopModel::where('uid',$work['uid'])->increment('receive_task_num',1);
        });
        //如果中标成功就发送一条系统消息
        if(is_null($status)){
            /*判断会员等级 自动发放对应奖品*/
            $actions = UserModel::where("id",$work['uid'])->pluck('level');
            $action = 9;
            switch ($actions) {
                case '1':
                    $action = 9;
                    break;
                case '2':
                    $action = 10;
                    break;
                case '3':
                    $action = 11;
                    break;
                case '4':
                    $action = 12;
                    break;
                default:
                    $action = 9;
                    break;
            }
            UserModel::sendfreegrant($work['uid'],$action);//选中自动发放
            //判断当前的任务发布成功之后是否需要发送系统消息
            $arr = [
                'task_id' => $data['task_id'],
                'uid'     => $work['uid'],
            ];
            self::sendTaskWidMessage($arr);
        }
        return is_null($status)?true:false;
    }

    /**
     * 发送任务中标信息
     * @param $arr
     * @return bool|static
     */
    static public function sendTaskWidMessage($arr)
    {
        $task = TaskModel::find($arr['task_id']);
        $userInfo = UserModel::where('id',$arr['uid'])->first();
        $user = [
            'uid'    => $userInfo->id,
            'email'  => $userInfo->email,
            'mobile' => $userInfo->mobile
        ];
        $templateArr = [
            'username'      => $userInfo->name,
            'title'         => $task->title,
        ];
        \MessageTemplateClass::sendMessage('task_win',$user,$templateArr,$templateArr);
    }



    /**
     * 阶段交付稿件
     * @param $data
     * @return bool
     */
    static public function delivery($data)
    {
        $status = DB::transaction(function() use($data){
            //判断该阶段是否提交过稿件
            $paySection = TaskPaySectionModel::where('task_id',$data['task_id'])->where('case_status',1)->where('sort',$data['sort'])->first();
            if(!empty($paySection['work_id']) && $paySection['verify_status'] == 2){
                //删除该稿件
                WorkModel::where('id',$paySection['work_id'])->delete();
                WorkAttachmentModel::where('work_id',$paySection['work_id'])->delete();
            }
            //将数据写入到work表中
            $workInfo = [
                'desc'       => $data['desc'],
                'task_id'    => $data['task_id'],
                'status'     => 2,//威客交付稿件
                'forbidden'  => 0,
                'uid'        => $data['uid'],
                'shop_id'    => isset($data['shop_id']) ? $data['shop_id'] : '',
                'created_at' => date('Y-m-d H:i:s')
            ];
            $result = WorkModel::create($workInfo);

            if(isset($data['file_ids'])){
                $file_able_ids = AttachmentModel::select('attachment.id','attachment.type')->whereIn('id',$data['file_ids'])->get()->toArray();
                //创建投稿记录和附件关联关系
                foreach($file_able_ids as $v){
                    $work_attachment = [
                        'task_id'       => $data['task_id'],
                        'work_id'       => $result['id'],
                        'attachment_id' => $v['id'],
                        'type'          => $v['type'],
                        'created_at'    => date('Y-m-d H:i:s',time()),
                    ];
                    WorkAttachmentModel::create($work_attachment);
                }
            }

            //关联稿件和支付阶段
            $paySectionInfo = [
                'work_id'        => $result['id'],
                'verify_status'  => 0,
                'section_status' => 1, //支付阶段进行中
                'updated_at'     => date('Y-m-d H:i:s')
            ];
            TaskPaySectionModel::where('task_id',$data['task_id'])->where('case_status',1)->where('sort',$data['sort'])->update($paySectionInfo);
            if($data['sort'] == 1){
                TaskModel::where('id',$data['task_id'])->update([
                    'status'     => 6,
                    'checked_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        });

        return is_null($status)?true:false;
    }

    /**
     * 验收稿件
     * @param $data
     * @return bool
     */
    static public function bidWorkCheck($data)
    {
        if($data['status'] == 1){//验收通过
            $status = DB::transaction(function() use($data) {
                //修改稿件的状态
                self::where('id', $data['work_id'])->update(['status' => 3, 'bid_at' => date('Y-m-d H:i:s', time())]);
                $paySection = TaskPaySectionModel::where('task_id',$data['task_id'])->where('work_id',$data['work_id'])->first();
                //修改支付阶段状态
                $paySectionInfo = [
                    'status'         => 1,//已支付
                    'verify_status'  => 1,//稿件审核通过
                    'section_status' => 3,//阶段完成
                    'updated_at'     => date('Y-m-d H:i:s'),
                    'pay_at'         => date('Y-m-d H:i:s'),//支付时间
                ];

                TaskPaySectionModel::where('task_id',$data['task_id'])
                    ->where('work_id',$data['work_id'])
                    ->update($paySectionInfo);
                $price =  $paySection['price'];
                //增加用户余额
                UserDetailModel::where('uid', $data['uid'])->increment('balance', $price);
                $remainder = DB::table('user_detail')->where('uid',$data['uid'])->first()->balance;
                //产生一笔财务流水 表示接受任务产生的钱
                $finance_data = [
                    'action'     => 23,
                    'pay_type'   => 1,
                    'cash'       => $price,
                    'uid'        => $data['uid'],
                    'created_at' => date('Y-m-d H:i:s', time()),
                    'updated_at' => date('Y-m-d H:i:s', time()),
                    'status'     => 1,//用户收入
                    'remainder'  => $remainder,
                    'related_id'  => $data['task_id'],
                ];
                FinancialModel::create($finance_data);

                //判断是不是完成最后支付阶段
                $isFinish = TaskPaySectionModel::where('task_id',$data['task_id'])->where('section_status','<',3)->first();
                if(empty($isFinish)){
                    TaskModel::where('id',$data['task_id'])->update(['status'=>7,'comment_at'=>date('Y-m-d H:i:s',time())]);
                    $task = TaskModel::find($data['task_id']);
                    //给雇主退回直通车金额
                    if($task['is_car'] == 1 && $task['car_cash'] > 0){
                        UserDetailModel::where('uid', $task['uid'])->increment('balance', $task['car_cash']);
                        $remainder1 = DB::table('user_detail')->where('uid',$task['uid'])->first()->balance;
                        //产生一条财务记录
                        $finance_data = [
                            'action'     => 7,//退款
                            'pay_type'   => 1,
                            'cash'       => $task['car_cash'],
                            'uid'        => $task['uid'],
                            'created_at' => date('Y-m-d H:i:s',time()),
                            'updated_at' => date('Y-m-d H:i:s',time()),
                            'status'     => 1,//用户收入
                            'remainder'  => $remainder1
                        ];
                        FinancialModel::create($finance_data);
                    }

                    //计算推广金额 雇主的推广人和威客的推广人
                    PromoteTypeModel::putPromote($task['uid'],2);
                    PromoteTypeModel::putPromote($data['uid'],3);
                }

            });
            //稿件结算发送系统消息
            if(is_null($status))
            {
                $task = TaskModel::find($data['task_id']);
                $userInfo1 = UserModel::where('id',$task->uid)->first();
                $userInfo = UserModel::where('id',$data['uid'])->first();
                $paySection = TaskPaySectionModel::where('work_id',$data['work_id'])->first();
                $user = [
                    'uid'    => $userInfo->id,
                    'email'  => $userInfo->email,
                    'mobile' => $userInfo->mobile
                ];
                $templateArr = [
                    'username'      => $userInfo->name,
                    'employer_name' => $userInfo1->name,
                    'title'         => $task->title,
                    'sort'          => $paySection['sort']
                ];
                \MessageTemplateClass::sendMessage('manuscript_settlement',$user,$templateArr,$templateArr);

            }
        }else{
            $status = DB::transaction(function() use($data) {

                //修改稿件的状态
                self::where('id', $data['work_id'])->update(['status' => 5]);
                //修改支付阶段状态
                $paySectionInfo = [
                    'verify_status'  => 2,//稿件审核失败
                    'section_status' => 1,//阶段进行中
                    'updated_at'     => date('Y-m-d H:i:s'),
                ];
                TaskPaySectionModel::where('task_id',$data['task_id'])
                    ->where('work_id',$data['work_id'])->update($paySectionInfo);

            });
            //稿件审核失败发送系统消息
            if(is_null($status))
            {
                $task = TaskModel::find($data['task_id']);
                $userInfo1 = UserModel::where('id',$task->uid)->first();
                $userInfo = UserModel::where('id',$data['uid'])->first();
                $paySection = TaskPaySectionModel::where('work_id',$data['work_id'])->first();
                $user = [
                    'uid'    => $userInfo->id,
                    'email'  => $userInfo->email,
                    'mobile' => $userInfo->mobile
                ];
                $templateArr = [
                    'username'      => $userInfo->name,
                    'employer_name' => $userInfo1->name,
                    'title'         => $task->title,
                    'sort'          => $paySection['sort']
                ];
                \MessageTemplateClass::sendMessage('manuscript_settlement',$user,$templateArr,$templateArr);
            }
        }

        return is_null($status)?true:false;
    }

    /**
     * 任务配置竞标限制时间
     * @param $uid
     * @return int
     */
    static public function isOkWork($uid)
    {
        $configsBid = ConfigModel::where('type','task_config')->get()->toArray();
        $configs_data = array();
        foreach($configsBid as $k => $v) {
            $configs_data[$v['alias']] = $v;
            if (!is_array($v['rule']) && \CommonClass::isJson($v['rule'])) {

                $rule = json_decode($v['rule'], true);
                $configs_data[$v['alias']]['rule'] = $rule;
            }
        }
        $isAllowHours = 0;
        if(isset($configs_data['task_config_switch']['rule']) && $configs_data['task_config_switch']['rule'] == 1){
            $start = isset($configs_data['task_config_time']['rule']['start']) ? $configs_data['task_config_time']['rule']['start'] : '';
            $end = isset($configs_data['task_config_time']['rule']['end']) ? $configs_data['task_config_time']['rule']['end'] : '';
            //查询用户时间段内中标次数
            $timesBid = WorkModel::where('uid',$uid)->where('status',1);
            if($start){
                $timesBid = $timesBid->where('created_at','>=',$start);
            }
            if($end){
                $timesBid = $timesBid->where('created_at','<=',$end);
            }
            $timesBid = $timesBid->count();
            if(isset($configs_data['task_config_rule']['rule']['times'])){
               foreach($configs_data['task_config_rule']['rule']['times'] as $k => $v){
                   if($timesBid<$v && $configs_data['task_config_rule']['rule']['sort'][$k] == 2){
                       $isAllowHours = $configs_data['task_config_rule']['rule']['hours'][$k];
                           break;
                   }
                   if($timesBid>=$v && $configs_data['task_config_rule']['rule']['sort'][$k] == 1){
                       $isAllowHours = $configs_data['task_config_rule']['rule']['hours'][$k];
                           break;
                   }
               }
            }

            return $isAllowHours;
        }

    }

    /*项目详情获取最新中标 送高一级竞标卡*/
    static public function getDetailWorker(){
        $list = self::where('work.status','1')
                      ->leftjoin("users","users.id","=","work.uid")
                      ->leftjoin("task","task.id","=","work.task_id")  
                      ->select("work.task_id","work.uid","users.name as shop_name","users.level","task.title")
                      ->orderBy('bid_at','desc')->limit(9)->get()->toArray();
        $viplist = ['普通','青铜','白银','黄金'];
        foreach ($list as $key => $v) {
            $v['level'] = !empty($v['level'])?$v['level']:'0';
            if($v['level'] >=4){
                $list[$key]['jbk'] = '黄金竞标卡';
            }else{
                $list[$key]['jbk'] = $viplist[$v['level']].'竞标卡';
            }
        }
        return $list;
    }


}
