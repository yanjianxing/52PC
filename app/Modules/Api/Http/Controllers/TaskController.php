<?php
/**
 * Created by PhpStorm.
 * User: xuanke
 * Date: 2016/6/28
 * Time: 10:55
 */

namespace App\Modules\Api\Http\Controllers;

use App\Http\Controllers\ApiBaseController;
use App\Modules\Manage\Model\MessageTemplateModel;
use App\Modules\Manage\Model\ServiceModel;
use App\Modules\Task\Model\TaskAttachmentModel;
use App\Modules\Task\Model\TaskCateModel;
use App\Modules\Task\Model\TaskModel;
use App\Modules\Task\Model\TaskPaySectionModel;
use App\Modules\Task\Model\TaskPayTypeModel;
use App\Modules\Task\Model\TaskRightsModel;
use App\Modules\Task\Model\TaskServiceModel;
use App\Modules\Task\Model\WorkAttachmentModel;
use App\Modules\Task\Model\WorkCommentModel;
use App\Modules\Task\Model\WorkModel;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\AuthRecordModel;
use App\Modules\User\Model\CommentModel;
use App\Modules\User\Model\DistrictModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\Manage\Model\ConfigModel;
use App\Modules\User\Model\UserModel;
use App\Modules\Task\Model\TaskTypeModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Validator;
use Illuminate\Support\Facades\Crypt;
use DB;

class TaskController extends ApiBaseController
{
    protected $uid;

    public function __construct(Request $request)
    {
        $tokenInfo = Crypt::decrypt(urldecode($request->get('token')));
        $this->uid = $tokenInfo['uid'];
        $this->name = $tokenInfo['name'];
    }

    /**
     * 任务大厅
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getTaskList(Request $request)
    {
        $data = $request->all();
        $data['limit'] = (isset($data['limit'])&&$data['limit']) ? $data['limit'] : 15;
        $tasks = TaskModel::select('task.id', 'task.title', 'task.view_count', 'task.delivery_count', 'task.created_at', 'task.bounty' ,'task.bounty_status','cate.name', 'task.uid','task_type.alias as task_type')
            ->leftjoin('cate', 'task.cate_id', '=', 'cate.id')
            ->leftJoin('task_type','task.type_id','=','task_type.id')
            ->where('task.status','>',2)//任务审核通过
            ->where('task.begin_at','<',date('Y-m-d H:i:s',time()))//已经开始投稿的任务
            ->where('task.status','!=',10);//任务没有失败
        if(isset($data['cate_id']) && $data['cate_id']){
            $tasks = $tasks->where('task.cate_id',$data['cate_id']);
        }
        if(isset($data['desc']) && $data['desc']){
            switch($data['desc']){
                case 1://综合
                    $tasks = $tasks->orderBy('task.top_status','desc')->orderBy('task.created_at','desc');
                    break;
                case 2://发布时间
                    $tasks = $tasks->orderBy('task.created_at','desc')->orderBy('task.top_status','desc');
                    break;
                case 3://稿件数
                    $tasks = $tasks->orderBy('task.delivery_count','desc');
                    break;
                case 4://赏金
                    $tasks = $tasks->orderBy('task.bounty','desc');
                    break;
                default://默认综合排序
                    $tasks = $tasks->orderBy('task.top_status','desc')->orderBy('task.created_at','desc');
            }
        }else{
            //默认综合排序
            $tasks = $tasks->orderBy('task.top_status','desc')->orderBy('task.created_at','desc');
        }
        if(isset($data['type']) && $data['type']){
            switch($data['type']){
                case 1://悬赏
                    $tasks = $tasks ->where('task.bounty_status',1)->where('task_type.alias','xuanshang');
                    break;
                case 2://招标
                    $tasks = $tasks ->where('task_type.alias','zhaobiao');
                    break;
                default:
                    $tasks = $tasks ->where(function($query){
                        $query->where(function($querys){//悬赏要托管赏金
                            $querys->where('task.bounty_status',1)->where('task_type.alias','xuanshang');
                        })->orwhere(function($querys){
                            $querys->where('task_type.alias','zhaobiao');
                        });
                    });
            }
        }else{
            $tasks = $tasks ->where(function($query){
                $query->where(function($querys){//悬赏要托管赏金
                    $querys->where('task.bounty_status',1)->where('task_type.alias','xuanshang');
                })->orwhere(function($querys){
                    $querys->where('task_type.alias','zhaobiao');
                });
            });
        }

        $tasks = $tasks->paginate($data['limit'])->toArray();
        if(!empty($tasks['data'])){
            $tasks['data'] = TaskModel::dealTaskArr($tasks['data']);
        }
        if(empty($tasks['data'])){
            $recommend = TaskModel::select('task.id', 'task.title', 'task.view_count', 'task.delivery_count', 'task.created_at', 'task.bounty' ,'task.bounty_status','cate.name', 'task.uid','task_type.alias as task_type')
                ->leftjoin('cate', 'task.cate_id', '=', 'cate.id')
                ->leftJoin('task_type','task.type_id','=','task_type.id')
                ->where('task.status','>',2)//任务审核通过

                ->where('task.begin_at','<',date('Y-m-d H:i:s',time()))//已经开始投稿的任务
                ->where('task.status','!=',10)//任务没有失败
                ->orderBy('task.top_status','desc')//增值服务排序
                ->orderBy('task.created_at', 'desc')
                ->limit(5)->get()->toArray();
            $tasks['recommend'] = [];
            if(!empty($recommend)){
                $tasks['recommend'] = TaskModel::dealTaskArr($recommend);
            }
        }
        return $this->formateResponse(1000,'success',$tasks);
    }

    /**
     * 我发布的任务列表(雇主)
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function myPubTasks(Request $request)
    {
        $data = $request->all();
        $data['limit'] = (isset($data['limit'])&&$data['limit']) ? $data['limit'] : 10;

        $tasks = TaskModel::select('task.*','cate.name as cate_name')
            ->leftjoin('cate','task.cate_id','=','cate.id')
            ->where('task.uid',$this->uid);

        $taskType = TaskTypeModel::select('id','alias')->get()->toArray();
        $taskType = array_reduce($taskType,function(&$taskType,$v){
            $taskType[$v['alias']] = $v['id'];
            return $taskType;
        });
        $verifyStatus = [2];
        //任务类型
        if (isset($data['type']) && $data['type']){
            switch($data['type']){
                case 1://悬赏
                $tasks = $tasks->where('task.type_id',$taskType['xuanshang'])->where('task.bounty_status',1);
                    break;
                case 2://招标
                    $tasks = $tasks->where('task.type_id',$taskType['zhaobiao']);
                    $verifyStatus = [1,2];
                    break;
                default:
                    $tasks = $tasks->where('task.type_id',$taskType['xuanshang'])->where('task.bounty_status',1);
            }
        }else{//默认查询悬赏任务
            $tasks = $tasks->where('task.type_id',$taskType['xuanshang'])->where('task.bounty_status',1);
        }

        if (isset($data['status']) && $data['status']){
            switch($data['status']){
                case 1://待审核
                    $status = $verifyStatus;
                    break;
                case 2://投标中
                    $status = [3,4];
                    break;
                case 3://选标中
                    $status = [5];
                    break;
                case 4://工作中
                    $status = [6,7];
                    break;
                case 5://评价中
                    $status = [8];
                    break;
                case 6://交易成功
                    $status = [9];
                    break;
                case 7://维权中
                    $status = [11];
                    break;
                case 8://交易关闭
                    $status = [10];
                    break;
                default:
                    $status = [1,2,3,4,5,6,7,8,9,10,11];
            }
            $tasks = $tasks->whereIn('task.status',$status);
        }

        $tasks = $tasks->where('task.status','>=',1)->where('task.status','<=',11)->orderBy('task.created_at','desc')->paginate($data['limit'])->toArray();
        if($tasks['data']){
            $status = [
                    1=>'审核中',
                    2=>'审核中',
                    3=>'定时发布',
                    4=>'投稿中',
                    5=>'选稿中',
                    6=>'选稿中',
                    7=>'交付中',
                    8=>'待评价',
                    9=>'交易成功',
                    10=>'交易关闭',
                    11=>'维权中'
            ];
            if(isset($data['status'])){
                $tasks['workStatus'] = $data['status'];
            } else{
                $tasks['workStatus'] = 0;
            }
            foreach($tasks['data'] as $k=>$v){
                $tasks['data'][$k]['status'] = $status[$v['status']];
            }
            //重组任务数据 拼接购买的增值服务等
            $tasks['data'] = TaskModel::dealTaskArr($tasks['data']);

        }
        return $this->formateResponse(1000,'success',$tasks);
    }

    /**
     * 威客的任务列表(我接受的任务列表)
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function myAcceptTask(Request $request)
    {
        $data = $request->all();
        $data['limit'] = (isset($data['limit'])&&$data['limit']) ? $data['limit'] : 15;
        $taskIDs = WorkModel::where('uid',$this->uid)->select('task_id')->distinct()->get()->toArray();
        $taskType = TaskTypeModel::select('id','alias')->get()->toArray();
        $taskType = array_reduce($taskType,function(&$taskType,$v){
            $taskType[$v['alias']] = $v['id'];
            return $taskType;
        });
        if(count($taskIDs)){
            $tasks = TaskModel::whereIn('id',$taskIDs);

            //任务类型
            if (isset($data['type']) && $data['type']){
                switch($data['type']){
                    case 1://悬赏
                        $tasks = $tasks->where('task.type_id',$taskType['xuanshang'])->where('task.bounty_status',1);
                        break;
                    case 2://招标
                        $tasks = $tasks->where('task.type_id',$taskType['zhaobiao']);
                        break;
                    default:
                        $tasks = $tasks->where('task.type_id',$taskType['xuanshang'])->where('task.bounty_status',1);
                }
            }else{//默认查询悬赏任务
                $tasks = $tasks->where('task.type_id',$taskType['xuanshang'])->where('task.bounty_status',1);
            }

            if (isset($data['status']) && $data['status']){
                switch($data['status']){
                    case 2://投标中
                        $status = [3,4];
                        break;
                    case 3://选标中
                        $status = [5];
                        break;
                    case 4://工作中
                        $status = [6,7];
                        break;
                    case 5://评价中
                        $status = [8];
                        break;
                    case 6://交易成功
                        $status = [9];
                        break;
                    case 7://维权中
                        $status = [11];
                        break;
                    case 8://交易关闭
                        $status = [10];
                        break;
                    default:
                        $status = [3,4,5,6,7,8,9,10,11];
                }
                $tasks = $tasks->whereIn('task.status',$status);
            }

            $tasks = $tasks->where('task.status','>=',3)->where('task.status','<=',11)->orderBy('task.created_at','desc')->paginate($data['limit'])->toArray();
            $status = [
                2=>'审核中',
                3=>'定时发布',
                4=>'投稿中',
                5=>'选稿中',
                6=>'选稿中',
                7=>'交付中',
                8=>'待评价',
                9=>'交易成功',
                10=>'交易关闭',
                11=>'维权中'
            ];
            if(isset($data['status'])){
                $tasks['workStatus'] = $data['status'];
            } else{
                $tasks['workStatus'] = 0;
            }
            if(!empty($tasks['data'])){
                foreach($tasks['data'] as $k=>$v){
                    $tasks['data'][$k]['status'] = $status[$v['status']];
                }
                //重组任务数据 拼接购买的增值服务等
                $tasks['data'] = TaskModel::dealTaskArr($tasks['data']);
            }
        }else{
            $tasks = [];
        }
        return $this->formateResponse(1000,'success',$tasks);
    }


    /**
     * 创建任务(3.0接口弃用)
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function createTaskBak(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data,[
            'title' => 'required',
            'desc' => 'required',
            'cate_id' => 'required',
            'type_id' => 'required',
            'bounty' => 'required|numeric',
            'worker_num' => 'required|integer|min:1',
            'province' => 'required',
            'city' => 'required',
            'delivery_deadline' => 'required',
            'begin_at' => 'required',
            'phone' => 'required'
        ],[
            'title.required' => '请填写任务标题',
            'desc.required' => '请填写任务描述',
            'cate_id.required' => '请选择行业类型',
            'type_id.required' => '请选择任务类型',
            'bounty.required' => '请输入您的预算',
            'bounty.numeric' => '请输入正确的预算格式',
            'worker_num.required' => '中标人数不能为空',
            'worker_num.integer' => '中标人数必须为整形',
            'worker_num.min' => '中标人数最少为1人',

            'province.required' => '请选择省份',
            'city.required' => '请选择城市',
            'delivery_deadline.required' => '请选择投稿截止时间',
            'begin_at.required' => '请选择任务开始时间',
            'phone.required' => '请输入手机号'
        ]);

        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(2001,$error[0]);
        }
        if(strtotime($data['begin_at']) < time()){
            if($data['begin_at'] == date('Y-m-d',time())){
                $data['begin_at'] = date('Y-m-d H:i:s');
            }
            else{
                return $this->formateResponse(2003,'任务开始时间不得小于当前时间');
            }
        }
        if(strtotime($data['delivery_deadline']) <= strtotime($data['begin_at'])){
            return $this->formateResponse(2004,'截稿时间必须大于发布时间一天');
        }
        $taskTypeInfo = TaskTypeModel::where('alias','xuanshang')->select('id')->first();
        $arrTaskInfo = array(
            'uid' => $this->uid,
            'title' => $data['title'],
            'desc' => $data['desc'],
            'cate_id' => $data['cate_id'],
            'bounty' => $data['bounty'],
            'show_cash' => $data['bounty'],
            'worker_num' => $data['worker_num'],
            'province' => $data['province'],
            'city' => $data['city'],
            'delivery_deadline' => $data['delivery_deadline'],
            'status' => 0,
            'begin_at' => $data['begin_at'],
            'type_id' => $taskTypeInfo->id,
            'phone' => $data['phone']
        );
        $file_id = $request->get('file_id');
        $result = DB::transaction(function() use ($arrTaskInfo,$file_id){
            $task = TaskModel::create($arrTaskInfo);
            if(!empty($file_id)){
                //查询用户的附件记录，排除掉用户删除的附件记录
                $file_able_ids = AttachmentModel::fileAble($file_id);
                $file_able_ids = array_flatten($file_able_ids);

                foreach($file_able_ids as $v){
                    $attachment_data = [
                        'task_id'=>$task['id'],
                        'attachment_id'=>$v,
                        'created_at'=>date('Y-m-d H:i:s', time()),
                    ];
                    TaskAttachmentModel::create($attachment_data);
                }
                //修改附件的发布状态
                $attachmentModel = new AttachmentModel();
                $attachmentModel->statusChange($file_able_ids);
            }

            $taskInfo = TaskModel::findById($task['id']);

            return $taskInfo;
        });
        if($result){
            return $this->formateResponse(1000,'success',$result);
        }else{
            return $this->formateResponse(2002,'创建失败');
        }
    }

    /**
     * 创建任务
     * author quanke
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function createTask(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data,[
            'title'             => 'required',
            'desc'              => 'required',
            'cate_id'           => 'required',
            'type_id'           => 'required',
            'phone'             => 'required',
            'worker_num'        => 'required|integer|min:1',
            'delivery_deadline' => 'required',
            'begin_at'          => 'required',
        ],[
            'title.required'             => '请填写任务标题',
            'desc.required'              => '请填写任务描述',
            'cate_id.required'           => '请选择行业类型',
            'type_id.required'           => '请选择任务类型',
            'phone.required'             => '请输入手机号',
            'worker_num.required'        => '中标人数不能为空',
            'worker_num.integer'         => '中标人数必须为整形',
            'worker_num.min'             => '中标人数最少为1人',
            'delivery_deadline.required' => '请选择投稿截止时间',
            'begin_at.required'          => '请选择任务开始时间',
        ]);

        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(2001,$error[0]);
        }

        if(strtotime($data['begin_at']) < time()){
            if($data['begin_at'] == date('Y-m-d',time())){
                $data['begin_at'] = date('Y-m-d H:i:s');
            }
            else{
                return $this->formateResponse(2003,'任务开始时间不得小于当前时间');
            }
        }
        if(strtotime($data['delivery_deadline']) <= strtotime($data['begin_at'])){
            return $this->formateResponse(2004,'截稿时间必须大于发布时间一天');
        }
        $data['delivery_deadline'] = date('Y-m-d H:i:s',strtotime($data['delivery_deadline']));


        //查询任务类型id
        $taskTypeInfo = TaskTypeModel::getTaskTypeIdByAlias($data['type_id']);

        switch($data['type_id']){// 任务类型别名
            case 'xuanshang':
                //查询当前的任务成功抽成比率
                $task_percentage = \CommonClass::getConfig('task_percentage');
                $task_fail_percentage = \CommonClass::getConfig('task_fail_percentage');
                if(isset($data['bounty'])){
                    //验证赏金
                    $begin_at = $data['begin_at'];
                    //检测赏金额度是否在后台设置的范围之内
                    $task_bounty_max_limit = \CommonClass::getConfig('task_bounty_max_limit');
                    $task_bounty_min_limit = \CommonClass::getConfig('task_bounty_min_limit');

                    //判断赏金必须大于最小限定
                    if ($task_bounty_min_limit > $data['bounty']) {
                        $info = '赏金应该大于' . $task_bounty_min_limit . '小于' . $task_bounty_max_limit;
                        return $this->formateResponse(2001, $info);
                    }
                    //赏金必须小于最大限定
                    if ($task_bounty_max_limit < $data['bounty'] && $task_bounty_max_limit != 0) {
                        $info = '赏金应该大于' . $task_bounty_min_limit . '小于' . $task_bounty_max_limit;
                        return $this->formateResponse(2001, $info);
                    }

                    //匹配查询当前的任务交稿截止时间最大规则
                    $task_delivery_limit_time = \CommonClass::getConfig('task_delivery_limit_time');
                    $task_delivery_limit_time = json_decode($task_delivery_limit_time, true);
                    $task_delivery_limit_time_key = array_keys($task_delivery_limit_time);

                    $task_delivery_limit_time_key = \CommonClass::get_rand($task_delivery_limit_time_key, $data['bounty']);
                    if(in_array($task_delivery_limit_time_key,array_keys($task_delivery_limit_time))){
                        $task_delivery_limit_time = $task_delivery_limit_time[$task_delivery_limit_time_key];
                    }else{
                        $task_delivery_limit_time = 100;
                    }
                    $maxDeadLineTime = date('Y-m-d',strtotime($begin_at)+$task_delivery_limit_time*24*3600);
                    if(strtotime($data['delivery_deadline']) > strtotime($maxDeadLineTime)){
                        $info = '您当前的发布的任务金额是' . $data['bounty'] . '最大截稿日期是'.$maxDeadLineTime;
                        return $this->formateResponse(2001, $info);
                    }
                }else{
                    return $this->formateResponse(2001, '托管赏金不能为空');
                }
                break;
            case 'zhaobiao':
                //招标成功失败提成比例
                $task_percentage = \CommonClass::getConfig('bid_percentage');
                $task_fail_percentage = \CommonClass::getConfig('bid_fail_percentage');
                //招标投稿截止时间判断
                $delivery_deadline = strtotime($data['delivery_deadline']);
                $begin_at = strtotime($data['begin_at']);
                $max_limit_delivery = \CommonClass::getConfig('bid_delivery_max');
                $max_limit_delivery = $max_limit_delivery * 24 * 3600;
                $deadlineMax = $begin_at + $max_limit_delivery;
                if ($deadlineMax < $delivery_deadline) {
                    $info = '当前截稿时间最晚可设置为' . date('Y-m-d', $deadlineMax);
                    return $this->formateResponse(2001, $info);
                }
                if($data['worker_num'] > 1){
                    $info = '招标模式任务最多一人中标';
                    return $this->formateResponse(2001,$info);
                }
                break;
            default:
                //查询当前的任务成功抽成比率
                $task_percentage = \CommonClass::getConfig('task_percentage');
                $task_fail_percentage = \CommonClass::getConfig('task_fail_percentage');
        }
        $arrTaskInfo = array(
            'uid' => $this->uid,
            'title' => $data['title'],
            'desc' => $data['desc'],
            'cate_id' => $data['cate_id'],
            'region_limit' => isset($data['province']) ? 1 : 0,
            'province' => isset($data['province']) ? $data['province'] : 0,
            'city' => isset($data['city']) ? $data['city'] : 0,
            'status' => isset($data['status']) ? $data['status'] : 0,
            'type_id' => $taskTypeInfo,
            'phone' => $data['phone'],
            'bounty' => isset($data['bounty']) ? $data['bounty'] : 0,
            'show_cash' => isset($data['bounty']) ? $data['bounty'] : 0,
            'worker_num' => $data['worker_num'],
            'delivery_deadline' => $data['delivery_deadline'],
            'begin_at' => $data['begin_at'],
            'service' => isset($data['service_id']) ? $data['service_id'] : ''
        );
        $arrTaskInfo['task_success_draw_ratio'] = $task_percentage; //任务成功抽成比例
        $arrTaskInfo['task_fail_draw_ratio'] = $task_fail_percentage;//任务失败抽成比例
        //附件记录
        $file_id = $request->get('file_id');
        if($file_id && !is_array($file_id)){
            $file_id = explode(',',$file_id);
        }
        $result = DB::transaction(function() use ($arrTaskInfo,$file_id,$data){
            if(isset($data['task_id']) && !empty($data['task_id'])){
                TaskModel::where('id',$data['task_id'])->update($arrTaskInfo);
                $task['id'] = $data['task_id'];

                //删除已经关联的附件
                TaskAttachmentModel::where('task_id',$data['task_id'])->delete();

            }else{
                $task = TaskModel::create($arrTaskInfo);
            }

            if(!empty($file_id)){
                //查询用户的附件记录，排除掉用户删除的附件记录
                $file_able_ids = AttachmentModel::fileAble($file_id);
                $file_able_ids = array_flatten($file_able_ids);

                if(!empty($file_able_ids)){
                    foreach($file_able_ids as $v){
                        $attachment_data = [
                            'task_id'=>$task['id'],
                            'attachment_id'=>$v,
                            'created_at'=>date('Y-m-d H:i:s', time()),
                        ];
                        TaskAttachmentModel::create($attachment_data);
                    }
                    //修改附件的发布状态
                    $attachmentModel = new AttachmentModel();
                    $attachmentModel->statusChange($file_able_ids);
                }

            }
            $servicePrice = 0;
            //添加增值服务
            if(isset($data['service_id']) && !empty($data['service_id'])){
                $serviceIdArr = explode(',',$data['service_id']);
                if(!empty($serviceIdArr)){
                    foreach($serviceIdArr as $k => $v){
                        if(!empty($v)){
                            $serviceArr[] = [
                                'task_id' => $task['id'],
                                'service_id' => $v,
                                'created_at' => date('Y-m-d H:i:s')
                            ];
                        }
                    }
                    if(!empty($serviceArr)){
                        //先删除已有的增值服务
                        TaskServiceModel::where('task_id',$task['id'])->delete();
                        TaskServiceModel::insert($serviceArr);
                    }

                    $servicePrice = \App\Modules\Task\Model\ServiceModel::whereIn('id',$serviceIdArr)->sum('price');
                }
            }

            //查询账户余额
            $userDetail = UserDetailModel::where('uid', $this->uid)->first();
            $balance = (float)$userDetail->balance;

            //产生订单
            $createOrder = true;
            //应付金额
            switch($data['type_id']){
                case 'xuanshang' :
                    $money = $arrTaskInfo['bounty'] + $servicePrice;
                    break;
                case 'zhaobiao':
                    $money = $servicePrice;
                    if($money == 0){
                        $createOrder = false;
                    }
                    break;
                default:
                    $money = $arrTaskInfo['bounty'] + $arrTaskInfo;
            }
            $taskInfo = TaskModel::findById($task['id']);
            $arr = [
                'task_id'      => $task['id'],
                'title'        => $arrTaskInfo['title'],
                'balance'      => $balance,
                'money'        => $money,
                'create_order' => $createOrder,
                'task_info'    => $taskInfo
            ];
            return $arr;
        });
        if($result){
            return $this->formateResponse(1000,'创建任务成功',$result);
        }else{
            return $this->formateResponse(2002,'创建失败');
        }
    }



    /**
     * 创建中标稿件(威客投稿或报价)
     * author quanke
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function createWinBidWork(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data,[
            'task_id'   => 'required',
            'desc' => 'required|str_length:2048'
        ],[
            'task_id.required' => '任务id必传',
            'desc.required' => '请输入稿件描述',
            'desc.str_length'=> '字数超过限制',
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(2001,$error[0]);
        }

        $task = TaskModel::findById($data['task_id']);
        if(!$task){
            return $this->formateResponse(2001,'任务不存在');
        }

        if($task->task_type == 'zhaobiao'){
            if(!isset($data['price']) || empty($data['price'])){
                return $this->formateResponse(2001,'任务报价不能为空');
            }
        }

        //判断当前用户是否有资格投标
        $data['status'] = 0;
        $result = $this->isWorkAble($data['task_id']);
        if($result['status'] == 0){
            return $this->formateResponse(2002,$result['message']);
        }

        $data['uid'] = $this->uid;
        $data['desc'] = e($data['desc']);
        $data['created_at'] = date('Y-m-d H:i:s');
        if(isset($data['file_id']) && !is_array($data['file_id'])){
            $data['file_id'] = explode(',',$data['file_id']);
        }
        $workModel = new WorkModel();
        $result = $workModel->workCreate($data);
        if(!$result){
            return $this->formateResponse(2003,'投稿失败');
        }

        return $this->formateResponse(1000,'稿件提交成功');
    }



    /**
     * 创建交付稿件(威客交付)
     * author quanke
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function createDeliveryWork(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data,[
            'task_id' => 'required',
            'desc' => 'required|str_length:2048'
        ],[
            'task_id.required' => '任务id必传',
            'desc.required' => '请输入稿件描述',
            'desc.str_length'=> '字数超过限制',
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(2001,$error[0]);
        }

        $task = TaskModel::findById($data['task_id']);
        if(!$task){
            return $this->formateResponse(2001,'任务不存在');
        }

        //判断用户是否有验收投稿资格
        $able = WorkModel::isWinBid($data['task_id'],$this->uid);
        if(!$able){
            return $this->formateResponse(2001,'你的稿件没有中标不能通过交付');
        }

        $data['uid'] = $this->uid;
        $data['status'] = 2;//表示用户
        $data['created_at'] = date('Y-m-d H:i:s',time());

        if(isset($data['file_id']) && !is_array($data['file_id'])){
            $data['file_id'] = explode(',',$data['file_id']);
        }

        switch($task->task_type){
            case 'xuanshang':
                //判断用户是否有已交付稿件
                $is_delivery = WorkModel::where('task_id',$data['task_id'])
                    ->where('uid',$this->uid)
                    ->where('status','>=',2)->first();
                if($is_delivery){
                    return $this->formateResponse(2003,'你已交付过稿件');
                }
                $result = WorkModel::delivery($data);
                break;
            case 'zhaobiao':
                if(!isset($data['sort'])){
                    return $this->formateResponse(2001,'交付阶段不能为空丶');
                }
                $result = WorkModel::bidDelivery($data);
                break;
        }

        if(isset($result) && $result){
            $agreement_documents = MessageTemplateModel::where('code_name','agreement_documents')->where('is_open',1)->first();
            if($agreement_documents){
                $task = TaskModel::where('id',$data['task_id'])->first();
                $user = UserModel::where('id',$task['uid'])->first();//必要条件
                $site_name = \CommonClass::getConfig('site_name');//必要条件
                $user_name =  $this->name;
                $domain = \CommonClass::getDomain();
                //组织好系统消息的信息
                //发送系统消息
                $messageVariableArr = [
                    'username'=>$user['name'],
                    'initiator'=>$user_name,
                    'agreement_link'=>'<a target="_blank" href="'.$domain.'/task/'.$task['id'].'">'.$domain.'/task/'.$task['id'].'</a>',
                    'website'=>$site_name,
                ];
                if($agreement_documents->is_on_site == 1){
                    \MessageTemplateClass::getMeaasgeByCode('agreement_documents',$user['id'],2,$messageVariableArr,$agreement_documents['name']);
                }

                if($agreement_documents->is_send_email == 1){
                    $email = $user->email;
                    \MessageTemplateClass::sendEmailByCode('agreement_documents',$email,$messageVariableArr,$agreement_documents['name']);
                }
            }
            return $this->formateResponse(1000,'稿件提交成功');
        }else{
            return $this->formateResponse(2004,'交付失败');
        }
    }


    /**
     * 雇主 招标任务 托管赏金 (选人中标后)
     * @param Request $request
     * @return \Illuminate\Http\Response
     * @author quanke
     */
    public function bountyBidTask(Request $request)
    {
        $taskId = $request->get('id');
        if(!$taskId){
            return $this->formateResponse(2001,'参数错误');
        }
        $task = TaskModel::findById($taskId);
        if(!$task){
            return $this->formateResponse(2001,'参数错误');
        }
        if($task->uid != $this->uid || $task->bounty_status == 1){
            return $this->formateResponse(2001,'没有托管赏金权限');
        }
        if($task->task_type == 'zhaobiao' && $task->status != 5){
            return $this->formateResponse(2001,'没有托管赏金权限');
        }
        //查询账户余额
        $userDetail = UserDetailModel::where('uid', $this->uid)->first();
        $balance = (float)$userDetail->balance;

        $arr = [
            'task_id'      => $task['id'],
            'title'        => $task['title'],
            'balance'      => $balance,
            'money'        => $task['bounty'],
            'task_info'    => $task
        ];
        return $this->formateResponse(1000,'招标任务托管赏金',$arr);
    }

    /**
     * 查看付款方式
     * @author quanke
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function paySection(Request $request)
    {
        $taskId = $request->get('id');
        if(!$taskId){
            return $this->formateResponse(2001,'参数错误');
        }
        $task = TaskModel::findById($taskId);
        if($task->task_type == 'zhaobiao' && $task->status >= 7 && $task->bounty_status == 1){
            $uid = $this->uid;
            if($uid == $task->uid){
                $role = 'employer';
            }else{
                //判断威客是否中标
                $isWid = WorkModel::isWinBid($taskId,$uid);
                if($isWid){
                    $role = 'employee';
                }else{
                    return $this->formateResponse(2001,'没有权限');
                }
            }
            if(isset($role)){
                $payCaseStatus = 0;//没有付款方式
                //查看是否有付款方式
                $payCase = TaskPayTypeModel::where('task_id',$taskId)->first();
                if($payCase){
                    $paySection = TaskPaySectionModel::select('id','name','sort','percent','price','desc')->where('task_id',$taskId)->orderBy('sort','ASC')->get()
->toArray();
                    if($payCase->status == 1){
                        $payCaseStatus = 1;//已经通过付款方式(没有操作按钮)
                    }elseif($payCase->status == 2){
                        $payCaseStatus = 2;//威客拒绝(雇主再次提交付款方式按钮)
                    }elseif($payCase->status == 0){
                        $payCaseStatus = 3;//等待威客审核
                    }
                    $data = [
                        'role' => $role,
                        'pay_type_status' => $payCaseStatus,
                        'task_bounty' => $task->bounty,
                        'pay_type' => $payCase->pay_type,//默认一次性  2=>50:50 3=>50:30:20 4=>自定义
                        'pay_type_append' => $payCase->pay_type_append,
                        'pay_section' => $paySection
                    ];
                }else{
                    if($role == 'employer'){
                        $data = [
                            'role' => $role,
                            'pay_type_status' => $payCaseStatus,
                            'task_bounty' => $task->bounty,
                            'pay_type' => 1,//默认一次性  2=>50:50 3=>50:30:20 4=>自定义
                            'pay_type_append' => '',
                            'pay_section' => [
                                [
                                    'name' => '第1阶段',
                                    'sort' => 1,
                                    'percent' => 100,
                                    'price' => $task->bounty,
                                    'desc' => ''
                                ]
                            ]
                        ];
                    }else{
                        $data = [
                            'role' => $role,
                            'pay_type_status' => $payCaseStatus,
                            'task_bounty' => $task->bounty,
                            'pay_type' => 1,//默认一次性  2=>50:50 3=>50:30:20 4=>自定义
                            'pay_type_append' => '',
                            'pay_section' => []
                        ];
                    }

                }
                return $this->formateResponse(1000,'查看成功',$data);
            }else{
                return $this->formateResponse(2001,'没有权限');
            }
        }else{
            return $this->formateResponse(2001,'没有权限');
        }
    }

    /**
     * 雇主保存付款方式
     * @author quanke
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function postPayType(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data,[
            'task_id'  => 'required',
            'pay_type' => 'required',
        ],[
            'task_id.required'  => ' 任务id必传',
            'pay_type.required' => '付款方式不能为空',

        ]);

        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(2001,$error[0]);
        }

        $task = TaskModel::find($data['task_id']);

        if(!$task){
            return $this->formateResponse(2001,'任务不存在');
        }

        if($task->uid != $this->uid){
            return $this->formateResponse(2001,'没有权限');
        }

        $role = '';
        if($task->uid == $this->uid){
            $role = 'employer';
        }


        $payCase = TaskPayTypeModel::where('task_id',$data['task_id'])->first();
        if($payCase){
            if($role == 'employer' && $request->get('submit_again') && $request->get('submit_again') == 1 && $payCase->status == 2){//被雇主拒绝
                //删除已有的付款方式
                TaskPayTypeModel::where('task_id',$data['task_id'])->delete();
                TaskPaySectionModel::where('task_id',$data['task_id'])->delete();
            }else{
                return $this->formateResponse(2001,'已有支付阶段,不能重复提交');
            }
        }
        if(isset($data['desc'])){
            $data['desc'] = explode(':',$data['desc']);
        }else{
            $data['desc'] = [];
        }
        switch($data['pay_type']){
            case 1:
                $data['sort'] = [1];
                $data['percent'] = [100];
                $data['price'] = [$task->bounty];
                break;
            case 2:
                $data['sort'] = [1,2];
                $data['percent'] = [50,50];
                $data['price'] = [floor($task->bounty * 50 / 100),floor($task->bounty * 50 / 100)];
                break;
            case 3:
                $data['sort'] = [1,2,3];
                $data['percent'] = [50,30,20];
                $data['price'] = [floor($task->bounty * 50 / 100),floor($task->bounty * 30 / 100),floor($task->bounty * 20 / 100)];
                break;
            case 4:
                if(!isset($data['pay_type_append'])){
                    return $this->formateResponse(2001,'自定义付款方式不能为空');
                }
                //验证自定义方式
                $payTypeAppend = explode(':',$data['pay_type_append']);
                if(array_sum($payTypeAppend) != 100){
                    return $this->formateResponse(2001,'自定义付款方式错误');
                }
                if(!empty($data['pay_type_append'])){
                    foreach($payTypeAppend as $k => $v){
                        $data['sort'][$k] = $k+1;
                        $data['percent'][$k] = $v;
                        $data['price'][$k] = floor($task->bounty * $v / 100);
                    }
                }
                break;
        }
        $res = TaskPayTypeModel::saveTaskPayType($data);

        if($res){
            return $this->formateResponse(1000,'付款方式提交成功');
        }else{
            return $this->formateResponse(1001,'保存失败');
        }

    }


    /**
     * 威客处理支付方式(同意或拒绝)
     * @author quanke
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function dealPayType(Request $request)
    {
        $taskId = $request->get('task_id');
        $type = $request->get('type');
        if(!$taskId || !$type){
            return $this->formateResponse(2001,'缺少参数');
        }
        $task = TaskModel::findById($taskId);

        if(!$task){
            return $this->formateResponse(2001,'任务不存在');
        }

        if($task->status != 7 || $task->task_type != 'zhaobiao'){
            return $this->formateResponse(2001,'没有权限');
        }

        $isWin = WorkModel::isWinBid($taskId,$this->uid);
        if(!$isWin){
            return $this->formateResponse(2001,'没有权限');
        }

        $payCase = TaskPayTypeModel::where('task_id',$taskId)->where('status',0)->first();
        if($payCase) {
            if($type == 1){
                $res = TaskPayTypeModel::checkTaskPayType($taskId,1,$this->uid);
            }else{
                $res = TaskPayTypeModel::checkTaskPayType($taskId,2,$this->uid);
            }
            if($res){
                return $this->formateResponse(1000,'处理成功');
            }else{
                return $this->formateResponse(1001,'failure');
            }
        }else{
            return $this->formateResponse(2001,'没有权限');
        }


    }
    /**
     * 根据用户id查询其好评率
     * @param $uid
     * @return \Illuminate\Http\Response
     */
    public function applauseRate(Request $request)
    {
        $applauseRate = \CommonClass::applauseRate($request->get('uid'));
        $data = array(
            'applauseRate' => $applauseRate,
        );
        return $this->formateResponse(1000,'success',$data);
    }


    /**
     * 稿件中标
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function workWinBid(Request $request)
    {
        $id = $request->get('id');
        $work = WorkModel::where('id',$id)->first();
        if(!$work){
            return $this->formateResponse(2001,'未找到对应的稿件信息');
        }

        //判断当前用户及已中标稿件数量
        $task = TaskModel::findById($work->task_id);
        $work_num = WorkModel::where('task_id',$work->task_id)->where('status',1)->count();
        if($this->uid != $task->uid){
            return $this->formateResponse(2002,'你不是任务发布者，无权操作！');
        }
        if($task->worker_num > $work_num){
            $data = array(
                'task_id' => $work->task_id,
                'work_id' => $id,
                'worker_num' => $task->worker_num,
                'win_bid_num' => $work_num
            );
            $work_model = new WorkModel();
            if($task->task_type == 'xuanshang'){
                $result = $work_model->winBid($data);
            }else{
                $result = $work_model->bidWinBid($data);
            }
            if($result){
                return $this->formateResponse(1000,'选中成功');
            }else{
                return $this->formateResponse(2001,'稿件状态修改失败');
            }
        }else{
            return $this->formateResponse(2003,'当前中标人数已满');
        }

    }

    /**
     * 交付稿件验收成功
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function deliveryWorkAgree(Request $request)
    {
        $data = $request->all();
        $work_id = intval($data['work_id']);
        $work = WorkModel::where('id',$work_id)->first();
        if(!$work){
            return $this->formateResponse(2003,'此稿件不存在');
        }
        $task = TaskModel::findById($work->task_id);

        //判断用户是否为雇主
        if($task->uid != $this->uid){
            return $this->formateResponse(2001,'你不是雇主，无权操作');
        }

        $work = WorkModel::where('task_id',$work->task_id)->where('uid',$work->uid)->where('status',2)->first();
        //判断稿件是否符合验收标准
        if($work->status != 2){
            return $this->formateResponse(2002,'当前稿件不具备验收资格');
        }
        $data['task_id'] = $work->task_id;
        $data['uid'] = $work->uid;
        $data['work_id'] = $work->id;
        switch($task->task_type){
            case 'xuanshang':
                //任务所需人数
                $worker_num = $task->worker_num;
                //任务验收通过人数
                $win_check = WorkModel::where('task_id',$work->task_id)->where('status','>=',3)->count();

                $data['worker_num'] = $worker_num;
                $data['win_check'] = $win_check;
                $workModel = new WorkModel();
                $result = $workModel->workCheck($data);
                break;
            case 'zhaobiao':
                if(!isset($data['status'])){//status=1验收通过 2:验收失败
                    return $this->formateResponse(2002,'招标任务验收模式必传');
                }
                $result = WorkModel::BidWorkCheck($data);
                break;
        }

        if(isset($result) && $result){
            return $this->formateResponse(1000,'success');
        }else{
            return $this->formateResponse(2004,'failure');
        }
    }

    /**
     * 交付稿件维权
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function deliveryWorkRight(Request $request)
    {
        if(!$request->get('work_id') or !$request->get('desc')){
            return $this->formateResponse(2003,'传送参数不能为空');
        }
        $data = $request->all();
        $work_id = intval($data['work_id']);
        $work = WorkModel::where('id',$work_id)->first();
        $task = TaskModel::findById($work->task_id);

        //判断当前用户是否有维权资格
        if(($work->uid != $this->uid) && ($task->uid != $this->uid)){
            return $this->formateResponse(2001,'你不具备维权资格');
        }
        if($work['status']==4) {
            return $this->formateResponse(2001,'当前稿件正在维权');
        }

        //判断当前维权用户是雇主还是威客
        if($work->uid == $this->uid){
            $data['role'] = 0;
            $data['from_uid'] = $this->uid;
            $data['to_uid'] = $task->uid;
        }
        if($task->uid == $this->uid){
            $data['role'] = 1;
            $data['from_uid'] = $this->uid;
            $data['to_uid'] = $work->uid;
        }
        $data['status'] = 0;
        $data['created_at'] = date('Y-m-d H:i:s',time());

        switch($task->task_type){
            case 'xuanshang':
                if($work->status != 2){
                    return $this->formateResponse(2001,'你不具备维权资格');
                }
                $result = TaskRightsModel::rightCreate($data);
                break;
            case 'zhaobiao':
                //判断当前维权的人是否具有维权资格
                if(!in_array($work->status,[2,5])){
                    return $this->formateResponse(2001,'你不具备维权资格');
                }
                $result = TaskRightsModel::bidRightCreate($data);
                break;
        }
        if(isset($result) && $result){
            return $this->formateResponse(1000,'success');
        }else{
            return $this->formateResponse(2002,'维权失败');
        }
    }

    /**
     * 回复稿件
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function commentCreate(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data,[
            'comment'=>'required',
            'task_id'=>'required',
            'work_id'=>'required',
        ],[
            'comment.required' => '回复内容不能为空',
            'task_id.required' => '所属任务id不能为空',
            'work_id.required' => '所属稿件id不能为空'
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(2001,$error[0]);
        }
        $data['comment'] = e($data['comment']);
        $data['uid'] = $this->uid;
        $userDetail = UserDetailModel::where('uid',$this->uid)->first();
        $data['nickname'] = $userDetail->nickname;
        $data['created_at'] = date('Y-m-d H:i:s');

        $workComment = WorkCommentModel::create($data);
        $result = WorkCommentModel::where('id',$workComment)->first();
        if($result){
            $result->avatar = $userDetail->avatar;
            return $this->formateResponse(1000,'success',$result);
        }else{
            return $this->formateResponse(2001,'回复失败');
        }
    }

    /**
     * 交易评论
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function evaluateCreate(Request $request)
    {
        $data = $request->all();
        //判断用户是否有评价权限
        $work = WorkModel::where('task_id',$data['task_id'])
            ->where('uid',$this->uid)
            ->where('status',3)
            ->first();
        $task = TaskModel::where('id',$data['task_id'])->first();
        if(!$work && $task->uid != $this->uid){
            return $this->formateResponse(2001,'你没有评价此稿件的权限');
        }
        //保存评论数据
        $data['from_uid'] = $this->uid;
        $data['comment'] = e($data['comment']);

        if($work)
        {
            $data['to_uid'] = $task->uid;
            $data['comment_by'] = 0;
        }
        if($task->uid == $this->uid)
        {
            $work = WorkModel::where('id',$data['work_id'])->first();
            $data['to_uid'] = $work['uid'];
            $data['comment_by'] = 1;
        }

        $is_evaluate =  CommentModel::where('from_uid',$this->uid)
            ->where('task_id',$data['task_id'])->where('to_uid',$data['to_uid'])
            ->first();
        if($is_evaluate){
            return $this->formateResponse(2002,'你已评论过此稿件');
        }
        $data['created_at'] = date('Y-m-d H:i:s',time());


        $res = CommentModel::commentCreate($data);
        /*$comment = CommentModel::create($data);
        $evaluateInfo =  CommentModel::where('from_uid',$data['to_uid'])
            ->where('task_id',$data['task_id'])->where('to_uid',$this->uid)
            ->first();
        if(!empty($evaluateInfo)){
            TaskModel::where('id',$data['task_id'])->update(['status' => 9]);
        }*/
        $result = CommentModel::where(['from_uid'=>$data['from_uid'],'to_uid'=>$data['to_uid'],'task_id'=>$data['task_id']])->first();
        if($res){
            return $this->formateResponse(1000,'success',$result);
        }else{
            return $this->formateResponse(2003,'评论失败');
        }
    }

    /**
     * 查看评价信息
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getEvaluate(Request $request)
    {
        $work_id = $request->get('work_id');
        $workInfo = WorkModel::where(['id' => $work_id,'status' => 1])->first();
        if(empty($workInfo)){
            return $this->formateResponse(2003,'传送参数错误');
        }
        $work = WorkModel::where('task_id',$workInfo->task_id)->where('uid',$workInfo->uid)->where('status',3)->first();
        if(!$work){
            return $this->formateResponse(2002,'稿件交易未完成，暂无评价信息');
        }
        $task = TaskModel::where('id',$work->task_id)->first();
        //判断当前用户是否有查看评价的权限
        if(($this->uid != $work->uid) && ($this->uid != $task->uid)){
            return $this->formateResponse(2002,'你没有查看该稿件评价信息的权限');
        }
        if($this->uid == $work->uid){
            $evaluate = CommentModel::where('task_id',$task->id)->where('from_uid',$task->uid)->first();
        }
        if($this->uid == $task->uid){
            $evaluate = CommentModel::where('task_id',$task->id)->where('from_uid',$work->uid)->first();
        }
        if($evaluate){
            return $this->formateResponse(1000,'success',$evaluate);
        }else{
            return $this->formateResponse(2001,'暂无相关评价信息');
        }
    }

    /**
     * 判断用户是否有权投稿
     * @param $task_id
     * @return array
     */
    public function isWorkAble($task_id)
    {
        $data = array(
            'status' => 1,
            'message' => '',
        );
        if(!$this->uid){
            $data['status'] = 0;
            $data['message'] = '请先登录';
        }
        $task = TaskModel::where('id',$task_id)->first();
        if($task){

            if(!in_array($task->status,[3,4,5]) || strtotime($task->begin_at)>time()){
                $data['status'] = 0;
                $data['message'] = '当前任务还没开始';
            }

            //判断投稿人是否为任务发布者
            if($task->uid == $this->uid){
                $data['status'] = 0;
                $data['message'] = '你是任务发布者，无法投稿';
            }
            //判断投稿人是否已投过稿
            $work = WorkModel::where('task_id',$task_id)->where('uid',$this->uid)->first();
            if($work){
                $data['status'] = 0;
                $data['message'] = '你已投稿或中标';
            }

        }else{
            $data['status'] = 0;
            $data['message'] = '任务不存在，无法投稿';
        }

        return $data;
    }

    /**
     * 文件上传
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function fileUpload(Request $request)
    {
        $file = $request->file('file');
        //将文件上传的数据存入到attachment表中
        $attachment = \FileClass::uploadFile($file,'task');
        $attachment = json_decode($attachment, true);
        //判断文件是否上传
        if($attachment['code']!=200)
        {
            return $this->formateResponse(2001,$attachment['message']);
        }
        $attachment_data = array_add($attachment['data'], 'status', 1);
        $attachment_data['created_at'] = date('Y-m-d H:i:s', time());
        //将记录写入attachment表中
        $result = AttachmentModel::create($attachment_data);
        $data = AttachmentModel::where('id',$result['id'])->first();
        $domain = ConfigModel::where('alias','site_url')->where('type','site')->select('rule')->first();
        if(isset($data)){
            $data->url = $data->url?$domain->rule.'/'.$data->url:$data->url;
        }
        if($result){
            return $this->formateResponse(1000,'success',$data);
        }else{
            return $this->formateResponse(2002,'文件上传失败');
        }
    }

    /**
     * 附件删除
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function fileDelete(Request $request)
    {
        $id = $request->get('id');
        $result = AttachmentModel::del($id,$this->uid);
        if($result){
            return $this->formateResponse(1000,'success');
        }else{
            return $this->formateResponse(2001,'附件删除失败');
        }
    }


    /**
     * 草稿箱
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function noPubTask(Request $request){
        $taskTypeArr = TaskTypeModel::select('id','alias')->get()->toArray();
        $taskType = array_reduce($taskTypeArr,function(&$taskType,$v){
            $taskType[$v['id']] = $v['alias'];
            return $taskType;
        });

        $newTaskType =  array_reduce($taskTypeArr,function(&$newTaskType,$v){
            $newTaskType[$v['alias']] = $v['id'];
            return $newTaskType;
        });
        $tasks = TaskModel::where('task.uid',$this->uid)
            ->where(function($query) use ($newTaskType){
                $query->where(function($query) use ($newTaskType){
                    $query->where('task.type_id',$newTaskType['xuanshang'])
                        ->whereIn('task.status',[0,1]);
                })->orWhere(function($query) use ($newTaskType){
                    $query->where('task.type_id',$newTaskType['zhaobiao'])
                        ->where('task.status',0);
                });
            })
            ->select('task.*','cate.name as cate_name')
            ->leftjoin('cate','task.cate_id','=','cate.id')
            ->orderBy('task.created_at','desc')
            ->paginate()->toArray();

        if(!empty($tasks['data'])){
            foreach($tasks['data'] as $k=>$v){
                $tasks['data'][$k]['task_type'] = $taskType[$v['type_id']];
                $tasks['data'][$k]['task_service'] = [];
                $tasks['data'][$k]['bounty_status_desc'] = '未托管';
                if($v['bounty_status'] == 1){
                    $tasks['data'][$k]['bounty_status_desc'] = '已托管';
                }
            }
        }
        return $this->formateResponse(1000,'success',$tasks);
    }

    /**
     * 草稿箱任务删除
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function noPubTaskDelete(Request $request)
    {
        $id = $request->get('id');
        if(!$id){
            return $this->formateResponse(1001,'缺少参数');
        }
        $task = TaskModel::findById($id);
        if(!$task){
            return $this->formateResponse(1001,'参数错误');
        }
        if($task->uid != $this->uid){
            return $this->formateResponse(1001,'参数错误');
        }
        if($task->task_type == 'xuanshang'){
            if($task->bounty_status == 1 || $task->status > 1){
                return $this->formateResponse(1001,'参数错误');
            }
        }elseif($task->task_type == 'zhaobiao' && $task->status > 0){
            return $this->formateResponse(1001,'参数错误');
        }
        $res = TaskModel::where('id',$id)->delete();
        TaskServiceModel::where('task_id',$id)->delete();
        if($res){
            return $this->formateResponse(1000,'删除成功');
        }else{
            return $this->formateResponse(1001,'删除失败');
        }
    }

    /**
     * 雇主端协议交付稿件详情
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function agreeDelivery(Request $request){
        if(!$request->get('task_id') or !$request->get('id')){
            return $this->formateResponse(1060,'传送参数不能为空');
        }
        $deliveryInfo = [];
        $userInfo = UserModel::select('users.name')
            ->leftjoin('task','users.id','=','task.uid')
            ->where('task.id',intval($request->get('task_id')))
            ->where('task.uid',intval($this->uid))
            ->first();
        if(!isset($userInfo)){
            return $this->formateResponse(1061,'传送任务id错误');
        }
        $deliveryInfo['gname'] = $userInfo->name;
        $serverInfo = UserModel::select('users.name','work.id','work.desc')
            ->leftjoin('work','users.id','=','work.uid')
            ->where('work.uid',intval($request->get('id')))
            ->where('work.task_id',intval($request->get('task_id')))
            ->where('work.status','>=','2')
            ->first();
        if(!isset($serverInfo)){
            return $this->formateResponse(1062,'传送威客id错误');
        }
        $deliveryInfo['wname'] = $serverInfo->name;
        $deliveryInfo['desc'] = $serverInfo->desc;
        $attachIds = WorkAttachmentModel::where('task_id',intval($request->get('task_id')))
            ->where('work_id',$serverInfo->id)
            ->select('attachment_id')
            ->get()
            ->toArray();
        $attachInfo = [];
        if(isset($attachIds)){
            $attachIds = array_flatten($attachIds);
            $attachInfo = AttachmentModel::whereIn('id',$attachIds)
                ->select('url')
                ->get()
                ->toArray();
            $attachInfo = array_flatten($attachInfo);
            $domain = ConfigModel::where('alias','site_url')->where('type','site')->select('rule')->first();
            foreach($attachInfo as $k=>$v){
                $attachInfo[$k] = $attachInfo[$k]?$domain->rule.'/'.$attachInfo[$k]:$attachInfo[$k];
            }
        }
        $deliveryInfo['attachInfo'] = $attachInfo;
        return $this->formateResponse(1000,'获取协议信息成功',$deliveryInfo);

    }


    /**
     * 威客端协议交付稿件详情
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function guestDelivery(Request $request){
        if(!$request->get('task_id')){
            return $this->formateResponse(1060,'传送参数不能为空');
        }
        $deliveryInfo = [];
        $userInfo = UserModel::select('users.name')
            ->leftjoin('task','users.id','=','task.uid')
            ->where('task.id',intval($request->get('task_id')))
            ->first();
        if(!isset($userInfo)){
            return $this->formateResponse(1061,'传送任务id错误');
        }
        $deliveryInfo['gname'] = $userInfo->name;
        $serverInfo = UserModel::select('users.name','work.id','work.desc')
            ->leftjoin('work','users.id','=','work.uid')
            ->where('work.uid',intval($this->uid))
            ->where('work.task_id',intval($request->get('task_id')))
            ->where('work.status','>=','2')
            ->first();
        if(!isset($serverInfo)){
            return $this->formateResponse(1062,'传送威客id错误');
        }
        $deliveryInfo['wname'] = $serverInfo->name;
        $deliveryInfo['desc'] = $serverInfo->desc;
        $attachIds = WorkAttachmentModel::where('task_id',intval($request->get('task_id')))
            ->where('work_id',$serverInfo->id)
            ->select('attachment_id')
            ->get()
            ->toArray();
        $attachInfo = [];
        if(isset($attachIds)){
            $attachIds = array_flatten($attachIds);
            $attachInfo = AttachmentModel::whereIn('id',$attachIds)
                ->select('url')
                ->get()
                ->toArray();
            $attachInfo = array_flatten($attachInfo);
            $domain = ConfigModel::where('alias','site_url')->where('type','site')->select('rule')->first();
            foreach($attachInfo as $k=>$v){
                $attachInfo[$k] = $attachInfo[$k]?$domain->rule.'/'.$attachInfo[$k]:$attachInfo[$k];
            }
        }
        $deliveryInfo['attachInfo'] = $attachInfo;
        return $this->formateResponse(1000,'获取协议信息成功',$deliveryInfo);
    }


    /**
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function getTaskType(Request $request)
    {
        $aliasArr = [
            'xuanshang','zhaobiao'
        ];
        // 查询任务类型
        $taskType = TaskTypeModel::select('id','name','alias')->whereIn('alias',$aliasArr)->get()->toArray();

        return $this->formateResponse(1000,'获取任务类型信息成功',$taskType);
    }

    /**
     * 获取任务增值服务列表
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function gettaskService(Request $request)
    {
        $service = ServiceModel::select('id','title','price','identify')->where('type',1)->get()->toArray();
        return $this->formateResponse(1000,'获取任务增值服务信息成功',$service);
    }

    /**
     * 威客投稿(招标)  弃用
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function createBidWinBidWork(Request $request)
    {
        $data = $request->all();
        $validator = Validator::make($data,[
            'desc' => 'required|str_length:2048',
            'price' => 'required'
        ],[
            'desc.required' => '请输入稿件描述',
            'desc.str_length'=> '字数超过限制',
            'price.required' => '任务报价必填'
        ]);
        $error = $validator->errors()->all();
        if(count($error)){
            return $this->formateResponse(2001,$error[0]);
        }

        //判断当前用户是否有资格投标
        $data['status'] = 0;
        $result = $this->isWorkAble($data['task_id']);
        if($result['status'] == 0){
            return $this->formateResponse(2002,$result['message']);
        }

        $data['uid'] = $this->uid;
        $data['desc'] = e($data['desc']);
        $data['created_at'] = date('Y-m-d H:i:s');

        $workModel = new WorkModel();
        $result = $workModel->workCreate($data);
        if(!$result){
            return $this->formateResponse(2003,'投稿失败');
        }

        return $this->formateResponse(1000,'success');
    }


    /**
     * 我发布的任务统计(雇主角色)
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function myPubTasksCount(Request $request)
    {
        $data = $request->all();

        $taskType = TaskTypeModel::select('id','alias')->get()->toArray();
        $taskType = array_reduce($taskType,function(&$taskType,$v){
            $taskType[$v['alias']] = $v['id'];
            return $taskType;
        });
        //任务类型
        if (isset($data['type']) && $data['type'] == 2){
            $alias = 'zhaobiao';
            $typeId = $taskType['zhaobiao'];
        }else{
            $alias = 'xuanshang';
            $typeId = $taskType['xuanshang'];
        }

        $taskStatus = [
            'verify'  => [1,2],//待审核
            'bid'     => [3,4], //投标中
            'choose'  => [5],//选标中
            'work_in' => [6,7],//工作中
            'comment' => [8],//评价中
            'success' => [9], //交易成功
            'right'   => [11],//维权中
            'failure' => [10] //交易关闭
        ];
        $count = [];
        if(!empty($taskStatus)){
            foreach($taskStatus as $k => $v){
                $count[$k] = TaskModel::myTaskCount($this->uid,$v,$typeId,$alias);
            }
        }

        return $this->formateResponse(1000,'success',$count);
    }

    /**
     * 雇主草稿箱任务(弃用)
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function draftsList(Request $request)
    {
        $data = $request->all();
        $data['limit'] = (isset($data['limit'])&&$data['limit']) ? $data['limit'] : 10;

        $tasks = TaskModel::select('task.*','cate.name as cate_name')
            ->leftjoin('cate','task.cate_id','=','cate.id')
            ->where('task.uid',$this->uid);

        $taskType = TaskTypeModel::select('id','alias')->get()->toArray();
        $taskType = array_reduce($taskType,function(&$taskType,$v){
            $taskType[$v['id']] = $v['alias'];
            return $taskType;
        });


        $tasks = $tasks->whereIn('task.status',[0,1])->orderBy('task.created_at','desc')->paginate($data['limit'])->toArray();
        if($tasks['total']){
            foreach($tasks['data'] as $k=>$v){
                $tasks['data'][$k]['task_type'] = $taskType[$v['type_id']];
            }
        }
        return $this->formateResponse(1000,'success',$tasks);
    }

    /**
     * 我接受的任务统计(威客角色)
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function myAcceptTasksCount(Request $request)
    {
        $data = $request->all();

        $taskType = TaskTypeModel::select('id','alias')->get()->toArray();
        $taskType = array_reduce($taskType,function(&$taskType,$v){
            $taskType[$v['alias']] = $v['id'];
            return $taskType;
        });
        //任务类型
        if (isset($data['type']) && $data['type'] == 2){
            $alias = 'zhaobiao';
            $typeId = $taskType['zhaobiao'];
        }else{
            $alias = 'xuanshang';
            $typeId = $taskType['xuanshang'];
        }

        $taskStatus = [
            'bid'     => [3,4], //投标中
            'choose'  => [5],//选标中
            'work_in' => [6,7],//工作中
            'comment' => [8],//评价中
            'success' => [9], //交易成功
            'right'   => [11],//维权中
            'failure' => [10] //交易关闭
        ];
        $taskWork = WorkModel::where('uid',$this->uid)->select('task_id')->distinct()->get()->toArray();
        $taskIDs = array_flatten($taskWork);
        $count = [];
        //dd($typeId,$alias,$taskIDs);
        if(!empty($taskStatus)){
            foreach($taskStatus as $k => $v){
                $count[$k] = TaskModel::myAcceptCount($taskIDs,$v,$typeId,$alias);
            }
        }

        return $this->formateResponse(1000,'success',$count);
    }

    /**
     * 编辑未发布的任务获取详情
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function editTask(Request $request)
    {
        $taskId = $request->get('id');
        if(!$taskId){
            return $this->formateResponse(2001,'缺少参数');
        }
        $task = TaskModel::findById($taskId);
        if(!$task || $task->status > 1){
            return $this->formateResponse(2001,'参数错误1');
        }
        if($task->uid != $this->uid){
            return $this->formateResponse(2001,'参数错误2');
        }
        $task->desc = htmlspecialchars_decode($task->desc);
        $domain = ConfigModel::where('alias', 'site_url')->where('type', 'site')->select('rule')->first();
        //获取任务附件
        $attachment = [];
        $arrAttachmentIDs = TaskAttachmentModel::findByTid($taskId);
        if (count($arrAttachmentIDs)) {
            $attachment = AttachmentModel::findByIds($arrAttachmentIDs);
            if (isset($attachment)) {
                foreach ($attachment as $k => $v) {
                    $attachment[$k]['url'] = $attachment[$k]['url'] ? $domain->rule . '/' . $attachment[$k]['url'] : $attachment[$k]['url'];
                }
            }
        }
        $task->attachment = $attachment;

        //城市名字
        $task->province_name = '';
        $task->city_name = '';
        if($task->region_limit == 1){
            $province = DistrictModel::find($task->province);
            if($province){
                $task->province_name = $province->name;
            }
            $city = DistrictModel::find($task->city);
            if($city){
                $task->city_name = $city->name;
            }
        }
        return $this->formateResponse(1000,'success',$task);

    }

}