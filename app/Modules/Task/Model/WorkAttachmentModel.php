<?php

namespace App\Modules\Task\Model;

use App\Modules\User\Model\AttachmentModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class WorkAttachmentModel extends Model
{
    protected $table = 'work_attachment';
    public  $timestamps = false;  //关闭自动更新时间戳
    public $fillable = ['task_id','work_id','attachment_id','type','created_at'];

    public function attachment()
    {
        return $this->hasOne('App\Modules\User\Model\AttachmentModel', 'id', 'attachment_id');
    }
    /**
     *创建一条投稿附件记录
     */
    static function createOne($task_id,$work_id,$attachment_id)
    {
        if(is_array($attachment_id)){
            foreach($attachment_id as $v){
                $type = AttachmentModel::where('id',$v)->lists('type');
                $model = new WorkAttachmentModel();
                $model->task_id = $task_id;
                $model->work_id = $work_id;
                $model->type = $type[0];
                $model->attachment_id = $v;
                $model->created_at = date('Y-m-d H:i:s',time());
                $result = $model->save();
                if(!$result){
                    return false;
                }
            }
        }else{
            $type = AttachmentModel::where('id',$attachment_id)->lists('type');
            $model = new TaskAttatchmentModel;
            $model->task_id = $task_id;
            $model->work_id = $work_id;
            $model->type = $type[0];
            $model->attachment_id = $attachment_id;
            $model->created_at = date('Y-m-d H:i:s', time());
            $result = $model->save();
            if(!$result){
                return false;
            }
        }

        return true;
    }
    /**
     * 根据uid和attachment_id判断是否能够下载
     * @param $attachment_id
     * @param $uid
     */
    static function isDownAble($attachment_id,$uid)
    {
        $attachment_data = Self::where('attachment_id',$attachment_id)->first();
        //查询task信息
        $task_data = TaskModel::findById($attachment_data['task_id']);
        if($task_data['uid']==$uid)
        {
            return true;
        }
        return false;
    }

    /**
     * 根据稿件id查询其对应的附件id
     * @param $id
     * @return mixed
     */
    static function findById($id)
    {
        $data = WorkAttachmentModel::select('attachment_id')
            ->where('work_id',$id)
            ->get()->toArray();
        return $data;
    }
}
