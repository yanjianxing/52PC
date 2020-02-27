<?php

namespace App\Modules\Task\Model;

use App\Modules\Employ\Models\UnionAttachmentModel;
use App\Modules\Finance\Model\FinancialModel;
use App\Modules\Test\Model\Common;
use App\Modules\User\Model\AttachmentModel;
use App\Modules\User\Model\UserDetailModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class TaskRightsModel extends Model
{
    protected $table = 'task_rights';
    public $timestamps = false;
    protected $fillable = [
        'role',
        'type',
        'task_id',
        'work_id',
        'desc',
        'status',
        'from_uid',
        'to_uid',
        'created_at',
        'handled_at',
        'worker_bounty',
        'owner_bounty',
        'attachment_id_str',
        'deal_name'
    ];


    /**
     * 获取维权列表
     * @param $data
     * @return mixed
     */
    static public function findRights($data)
    {
        $list = self::whereRaw('1=1');
        if(isset($data['task_id']) && !empty($data['task_id'])){
            $list = $list->where('task_id',$data['task_id']);
        }
        $list = $list->orderBy('id','desc')->get()->toArray();
        if(!empty($list)){
            $idArr = array_pluck($list,'id');
            $attachment = UnionAttachmentModel::where('object_type',6)->whereIn('object_id',$idArr)->with('attachment')->get()->toArray();
            $attachment = \CommonClass::setArrayKey($attachment,'object_id',2);
            foreach($list as $k => $v){
                $list[$k]['attachment'] = [];
                if(in_array($v['id'],array_keys($attachment))){
                    $list[$k]['attachment'] = $attachment[$v['id']];
                }
            }
        }
        return $list;
    }

    /**
     * 任务维权
     * @param $data
     * @return bool
     */
    public static function bidRightCreate($data)
    {
        $status = DB::transaction(function() use($data){
            if(isset($data['file_ids']) && !empty($data['file_ids'])){
                $data['attachment_id_str'] = implode(',',$data['file_ids']);
            }
            $right = self::create($data);

            if(isset($data['file_ids']) && !empty($data['file_ids'])){
                foreach($data['file_ids'] as $v){
                    $right_attachment = [
                        'object_type'   => 6,
                        'object_id'        => $right->id,
                        'attachment_id' => $v,
                        'created_at'    => date('Y-m-d H:i:s',time()),
                    ];
                    UnionAttachmentModel::create($right_attachment);
                }
            }

            if($data['work_id'] > 0){
                //将work的状态修改成4
                WorkModel::where(['task_id' => $data['task_id'],'id' => $data['work_id']])->whereIn('uid',[$data['from_uid'],$data['to_uid']])->update(['status' => 4]);
                //修改支付阶段状态
                $paySectionInfo = [
                    'section_status' => 2,//维权中
                    'updated_at'     => date('Y-m-d H:i:s'),
                ];
                TaskPaySectionModel::where('task_id',$data['task_id'])
                    ->where('work_id',$data['work_id'])->update($paySectionInfo);
            }

            //任务状态变为维权中
            TaskModel::where('id',$data['task_id'])->update(['status'=>9,'updated_at' => date('Y-m-d H:i:s')]);

            //查询雇主是否购买直通车需要退款
            $task = TaskModel::find($data['task_id']);
            //给雇主退回直通车金额
            if($task['is_car'] == 1 && $task['car_cash'] > 0){
                UserDetailModel::where('uid', $task['uid'])->increment('balance', $task['car_cash']);
                //产生一条财务记录
                $finance_data = [
                    'action'     => 7,//退款
                    'pay_type'   => 1,
                    'cash'       => $task['car_cash'],
                    'uid'        => $task['uid'],
                    'created_at' => date('Y-m-d H:i:s',time()),
                    'updated_at' => date('Y-m-d H:i:s',time()),
                ];
                FinancialModel::create($finance_data);
            }

        });
        return is_null($status)?true:false;
    }
}
