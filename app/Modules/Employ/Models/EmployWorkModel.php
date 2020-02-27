<?php

namespace App\Modules\Employ\Models;

use App\Modules\Manage\Model\ConfigModel;
use App\Modules\User\Model\AttachmentModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class EmployWorkModel extends Model
{
    protected $table = 'employ_work';
    public $timestamps = false;
    protected $fillable = [
        'desc','employ_id','status','uid','created_at'
    ];

    static public function employDilivery($data,$uid)
    {
        $status = DB::transaction(function() use($data,$uid){
            //创建一条work记录
            $data['status'] = 0;//表示交付
            $data['uid'] = $uid;
            $data['created_at'] = date('Y-m-d H:i:s',time());
            $result = self::create($data);
            //如果有附件就创建附件
            if(isset($data['file_id'])){
                $file_able_ids = AttachmentModel::select('attachment.id','attachment.type')->whereIn('id',$data['file_id'])->get()->toArray();
                //创建投稿记录和附件关联关系
                foreach($file_able_ids as $v){
                    $work_attachment = [
                        'object_id'=>$result['id'],
                        'object_type'=>3,
                        'attachment_id'=>$v['id'],
                        'created_at'=>date('Y-m-d H:i:s',time()),
                    ];
                    UnionAttachmentModel::create($work_attachment);
                }
            }
            //计算验收时间限制和威客维权时间限制
                //查询所有的雇佣相关的配置信息
            $employ_configs = ConfigModel::where('type', 'employ')->get()->toArray();
            $employ_configs = \CommonClass::keyBy($employ_configs, 'alias');
                //验收时间限制
            $employer_delivery_time = $employ_configs['employer_delivery_time']['rule'];
            $accept_deadline = time()+$employer_delivery_time*3600*24;
                //威客维权时间限制
            $employee_right_time = $employ_configs['employee_right_time']['rule'];
            $employee_right_time = time()+$employee_right_time*3600;
            $employ_data = [
                'status'=>2,
                'accept_deadline'=>date('Y-m-d H:i:s',$accept_deadline),
                'right_allow_at'=>date('Y-m-d H:i:s',$employee_right_time),
            ];
            EmployModel::where('id',$data['employ_id'])->update($employ_data);
        });
        return is_null($status)?true:false;
    }
}
