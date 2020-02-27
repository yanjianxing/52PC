<?php

namespace App\Modules\Task\Model;

use App\Modules\Manage\Model\ConfigModel;
use App\Modules\User\Model\UserDetailModel;
use App\Modules\User\Model\UserModel;
use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Foundation\Auth\Access\Authorizable;
use Gregwar\Captcha\CaptchaBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class TaskPayTypeModel extends Model
{
    protected $table = 'task_pay_type';
    public  $timestamps = false;  //关闭自动更新时间戳
    public $fillable = [
        'id',
        'uid',
        'task_id',
        'start_time','end_time',
        'knowledge_own',
        'develop_desc',
        'desc',
        'attachment_id_str',
        'overdue_day',
        'overdue_all',
        'overdue_time',
        'return_time',
        'return_count',
        'grace_time',
        'grace_day',
        'grace_all',
        'status',
        'created_at',
        'updated_at'
    ];

    /**
     * 雇主保存支付方式(多阶段支付)
     * @param $data
     * @return bool
     */
    static public function saveTaskPayType($data)
    {
        $status = DB::transaction(function () use ($data) {
            TaskModel::where('id',$data['task_id'])->update([
                'bounty'     => $data['bounty'],
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            $payTypeInfo = [
                'task_id'       => $data['task_id'],
                'uid'           => $data['uid'],
                'start_time'    => isset($data['cycle_start_time']) ? $data['cycle_start_time'] : date('Y-m-d'),
                'end_time'      => isset($data['cycle_end_time']) ? $data['cycle_end_time'] : date('Y-m-d'),
                'knowledge_own' => isset($data['knowledge_own']) ? $data['knowledge_own'] : '',
                'develop_desc'  => isset($data['develop_desc']) ? $data['develop_desc'] : '',
                'desc'          => isset($data['desc']) ? $data['desc'] : '',
                'overdue_day'   => isset($data['overdue_day']) ? $data['overdue_day'] : '',
                'overdue_all'   => isset($data['overdue_all']) ? $data['overdue_all'] : '',
                'overdue_time'  => isset($data['overdue_time']) ? $data['overdue_time'] : '',
                'return_time'   => isset($data['return_time']) ? $data['return_time'] : '',
                'return_count'  => isset($data['return_count']) ? $data['return_count'] : '',
                'grace_time'    => isset($data['grace_time']) ? $data['grace_time'] : '',
                'grace_day'     => isset($data['grace_day']) ? $data['grace_day'] : '',
                'grace_all'     => isset($data['grace_all']) ? $data['grace_all'] : '',
                'status'        => 0,
                'created_at'    => date('Y-m-d H:i:s'),
                'updated_at'    => date('Y-m-d H:i:s'),
            ];
            if(isset($data['file_ids']) && !empty($data['file_ids']) && is_array($data['file_ids'])){
                $payTypeInfo['attachment_id_str'] = implode(',',$data['file_ids']);
            }
            TaskPayTypeModel::create($payTypeInfo);

            $sort = $data['sort'];
            $name = $data['name'];
            $price = $data['price'];
            $cycle_time = $data['start_time'];
            $end_time = $data['end_time'];
            $check_desc = $data['check_desc'];

            if(is_array($sort) && !empty($sort)){
                for ($i = 0; $i < count($sort); $i++) {
                    $paySectionInfo[] = [
                        'task_id'       => $data['task_id'],
                        'sort'          => $sort[$i],
                        'name'          => isset($name[$i]) ? $name[$i] : '',
                        'price'         => isset($price[$i]) ? $price[$i] : 0.00,
                        'start_time'    => isset($cycle_time[$i]) ? $cycle_time[$i] : date('Y-m-d'),
                        'end_time'    => isset($end_time[$i]) ? $end_time[$i] : '',
                        'desc'          => isset($check_desc[$i]) ? $check_desc[$i] : date('Y-m-d'),
                        'created_at'    => date('Y-m-d H:i:s'),
                        'updated_at'    => date('Y-m-d H:i:s')
                    ];
                }
                if (!empty($paySectionInfo)) {
                    TaskPaySectionModel::insert($paySectionInfo);
                }
            }
        });

        return is_null($status) ? true : false;
    }


    /**
     * @param int $taskId 任务id
     * @param int $type 1:同意 2:不同意
     * @param int $uid 威客uid
     * @return bool
     */
    static public function checkTaskPayType($taskId,$type,$uid)
    {
        $status = DB::transaction(function () use ($taskId,$type,$uid) {
            TaskPayTypeModel::where('task_id',$taskId)->update(['status' => $type,'updated_at' => date('Y-m-d H:i:s')]);
            TaskPaySectionModel::where('task_id',$taskId)->update(['case_status' => $type,'uid'=> $uid,'updated_at' => date('Y-m-d H:i:s')]);
            if($type == 1){
                $arrData = [
                    'checked_at' =>date('Y-m-d H:i:s',time()),
                ];
                TaskModel::where('id', $taskId)->update($arrData);
            }
        });

        return is_null($status) ? true : false;
    }

}
