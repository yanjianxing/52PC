<?php

namespace App\Modules\Task\Model;

use App\Modules\Employ\Models\UnionAttachmentModel;
use App\Modules\User\Model\AttachmentModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
class TaskFeedbackModel extends Model
{
    protected $table = 'task_feedback';
    public $timestamps = false;  //关闭自动更新时间戳
    public $fillable = [
        'id',
        'uid',
        'task_id',
        'task_name',
        'type',//.反馈类型：1、虚假项目，2、预算太低，3、工期太短，4、联系不上，5、需求不明，6、其它。
        'desc',//.反馈内容
        'created_at',
        'feedback_accessory_attachment_id',//.反馈附件id
    ];



    /**
     * //.新增任务(项目)反馈记录
     * @param $data
     * @return bool
     */
    static public function CreateFeedback($data=[]){
        $status = DB::transaction(function () use ($data) {
            //.判断是否存在附件
            if(isset($data['file_ids']) && !empty($data['file_ids'])){
                $data['attachment_id_str'] = implode(',',$data['file_ids']);
            }
            $arr = [
                'uid'               => isset($data['uid'])?$data['uid']:'',
                'task_id'           => isset($data['task_id'])?$data['task_id']:'',
                'task_name'         => isset($data['task_name'])?$data['task_name']:'',
                'type'              => isset( $data['type'])? $data['type']:'',
                'desc'              => isset($data['desc'])?$data['desc']:'',
                'created_at'        =>date('Y-m-d H:i:s', time()),
                'feedback_accessory_attachment_id'=> isset($data['attachment_id_str'] )?$data['attachment_id_str'] :'',
            ];
            $feedback = self::create($arr);
            if(isset($data['file_ids']) && !empty($data['file_ids'])){
                foreach($data['file_ids'] as $v){
                    $feedback_attachment = [
                        'object_id'        => $feedback->id,
                        'object_type'   => 8,
                        'attachment_id' => $v,
                        'created_at'    => date('Y-m-d H:i:s',time()),
                    ];
                    UnionAttachmentModel::create($feedback_attachment);
                }
            }
        });
        return is_null($status)?true:false;
    }


    /**
     * //.获取反馈列表
     * @param $data
     * @return bool
     */
    static public function getFeedbackList($merge=[],$paginate=10){
        //.获取反馈列表
        $task_feedbackList = TaskFeedbackModel::leftJoin('users','task_feedback.uid','=','users.id');

        //反馈编号、项目名称筛选、反馈用户名
        if (isset($merge['keywords'])&&!empty($merge['keywords'])) {
            $task_feedbackList=$task_feedbackList->where(function($task_feedbackList)use($merge){
                $task_feedbackList->Where('task_feedback.task_name','like','%'.$merge['keywords'].'%')
                    ->orWhere("users.name",'like','%'.$merge['keywords'].'%');
            });
        }

        //反馈类型筛选
        if (isset($merge['type'])&&!empty($merge['type'])) {
            $task_feedbackList = $task_feedbackList->where('task_feedback.type', $merge['type']);
        }

        //时间筛选
        $feedbackcreated_at = 'task_feedback.created_at';
        if(isset($merge['start'])&&!empty($merge['start'])){
            $start = preg_replace('/([\x80-\xff]*)/i', '', $merge['start']);
            $start = date('Y-m-d H:i:s',strtotime($start));
            $task_feedbackList = $task_feedbackList->where($feedbackcreated_at,'>',$start);

        }
        if(isset($merge['end'])&&!empty($merge['end'])){
            $end = preg_replace('/([\x80-\xff]*)/i', '', $merge['end']);
            $end = date('Y-m-d H:i:s',strtotime($end)+24*60*60);
            $task_feedbackList = $task_feedbackList->where($feedbackcreated_at,'<',$end);
        }

        $task_feedbackList = $task_feedbackList->select('task_feedback.*','users.name')->orderBy('id','DESC')->paginate($paginate);
        if (isset($task_feedbackList)&& !empty($task_feedbackList)){
            foreach ($task_feedbackList as $k=>$v){
                $accessoryids=$v->feedback_accessory_attachment_id;//.获取所有的附件id们
                $accessoryids=isset($accessoryids)?explode(',',$accessoryids):[];
                $task_feedbackList[$k]['accessoryids'] = AttachmentModel::whereIn('id',$accessoryids)->select('id','name','url')->get()->toArray();//.获取此反馈的所有附件
            }
        }
        $feedback_type = [
            '1'=>'虚假项目',
            '2'=>'预算太低',
            '3'=>'工期太短',
            '4'=>'联系不上',
            '5'=>'需求不明',
            '6'=>'其它',
        ];
        $task_feedbackList=isset($task_feedbackList)?$task_feedbackList:[];

        $data = [
            'task_feedbackList'=>$task_feedbackList,
            'feedback_type'=>$feedback_type,
        ];
        return $data;
    }


}