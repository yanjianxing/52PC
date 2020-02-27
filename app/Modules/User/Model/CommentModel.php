<?php

namespace App\Modules\User\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CommentModel extends Model
{

    protected $table = 'comments';
    public $timestamps = false;
    protected $fillable = [
        'task_id',
        'from_uid',
        'to_uid',
        'comment',
        'comment_by',
        'speed_score',
        'quality_score',
        'attitude_score',
        'created_at',
        'type'
    ];



    static public function taskComment($id,$data=array())
    {
        $query = Self::select('comments.*'/*,'ud.avatar','us.name as nickname'*/)->where('task_id',$id);
        //筛选好中差评价
        if(!empty($data['evaluate_type']))
        {
            $query->where('type',$data['evaluate_type']);
        }
        //筛选来自威客的评价还是来自雇主的评价
        if(!empty($data['evaluate_from']))
        {
            switch($data['evaluate_from'])
            {
                case 1:
                    $query->where('uid','<>',$data['task_user_id']);
                    break;
                case 2:
                    $query->where('uid',$data['task_user_id']);
            }
        }
        $data = $query/*->leftjoin('user_detail as ud','comments.to_uid','=','ud.uid')
            ->leftjoin('users as us','us.id','=','comments.to_uid')*/
            ->get()->toArray();

        return $data;
    }
    //好评率统计
    static public function applauseRate($id)
    {
        //查询当前id的评价总数
        $comments = self::where('to_uid',$id)->count();
        $good_comments = self::where('to_uid',$id)->where('type',1)->count();
        if($comments==0){
            $applause_rate = 100;
        }else{
            $applause_rate = ($good_comments/$comments)*100;
        }

        return floor($applause_rate);
    }

    /**
     * 任务评价
     * @param $data
     * @return bool
     */
    static public function commentCreate($data)
    {
        $status = DB::transaction(function() use($data){
            //保存评价
            self::create($data);
            //检查当前任务是否评价完成
            $worker_num = TaskModel::where('id',$data['task_id'])->first();
            //检查当前任务的评价数量是否已满
            $comment_count = self::where('task_id',$data['task_id'])->count();
            //评价完成之后任务就直接结束了
            if(!empty($worker_num['worker_num']) && $worker_num['worker_num']*2==$comment_count) {
                TaskModel::where('id',$data['task_id'])->update(['status'=>8,'end_at'=>date('Y-m-d H:i:s',time())]);
            }
            //给雇主的评价
            if($data['comment_by']==0 && $data['type']==1)
            {
                //给雇主的好评加1
                UserDetailModel::where('uid',$data['to_uid'])->increment('employer_praise_rate',1);
            }elseif($data['comment_by']==1 && $data['type']==1)
            {
                //给威客的好评数加1
                UserDetailModel::where('uid',$data['to_uid'])->increment('employee_praise_rate',1);
            }

            $userInfo = UserModel::find($data['to_uid']);
            $userInfo1 = UserModel::find($data['from_uid']);
            $user = [
                'uid'    => $userInfo->id,
                'email'  => $userInfo->email,
                'mobile' => $userInfo->mobile
            ];
            $from = $data['comment_by'] == 0 ? '雇主' : '服务商';
            $templateArr = [
                'username'      => $userInfo->name,
                'title'         => $worker_num->title,
                'from'          => $from,
                'nickname'      => $userInfo1->name,

            ];
            \MessageTemplateClass::sendMessage('task_comment',$user,$templateArr,$templateArr);
        });

        return is_null($status)?true:false;
    }


}

?>